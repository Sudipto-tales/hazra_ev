<?php

/**
 * Road matching: turns a 15-second GPS trace into the streets that were ridden.
 *
 * **Matching, not directions.** A directions call answers "what is the fastest
 * way from A to B" and will happily invent a bypass nobody took. A matcher is
 * given the whole trace and returns the road chain that best *explains* it —
 * an HMM whose emissions are fix-to-road distances and whose transitions
 * compare road distance against the straight-line hop. At a 15 s interval and
 * 40 km/h the hops are ~165 m, which is exactly the range where the wrong
 * parallel street becomes plausible, so every signal that disambiguates
 * (timestamps, per-point accuracy radius) is sent rather than defaulted.
 *
 * Two engines, one interface:
 *
 * | Engine | Two-wheeler | Cost |
 * | --- | --- | --- |
 * | OSRM `/match` | needs a forked `motorcycle.lua`; stock profiles are car/bike/foot and `bike` is a *bicycle* | fastest matcher, profile is baked into the graph at build time |
 * | Valhalla `/trace_attributes` | `motorcycle` and `motor_scooter` costings ship with it | costing chosen per request, slower, returns a confidence score |
 *
 * Valhalla is the recommended production target for a two-wheeler fleet — no
 * Lua fork to maintain and `motor_scooter` already models the "uses the car
 * network, tolerates rough and narrow, is not a highway vehicle" behaviour.
 * OSRM stays the default because the mobile client already speaks it, and a
 * demo host exists. Neither is safe to point at a fleet unhosted.
 *
 * Every failure returns null and the caller keeps the raw trace. A matcher
 * being down is a degraded map, never an error screen.
 */
final class MapMatch
{
    public const ENGINE_OSRM     = 'osrm';
    public const ENGINE_VALHALLA = 'valhalla';

    /** Fixes closer than this add nothing and eat the per-request budget. */
    private const MIN_POINT_SPACING_M = 8.0;

    /**
     * A run of fixes staying inside this radius is a parked vehicle, and gets
     * collapsed to its two endpoints before anything else.
     *
     * Distance thinning alone does not catch it: a phone parked with 15 m
     * accuracy drifts in ~13 m steps, so every fix clears
     * [self::MIN_POINT_SPACING_M] against the *last kept* point while the whole
     * cluster spans twelve metres. Ten of those fill a request with a blob the
     * matcher cannot resolve, and the trace that carried real information never
     * gets sent.
     *
     * Well under `stop_radius_metres` (75 m by default) so this only removes
     * standing still, never slow riding.
     */
    private const STATIONARY_RADIUS_M = 20.0;

    /** Shorter runs than this are drift, not a dwell, and are left alone. */
    private const STATIONARY_MIN_FIXES = 4;

    /**
     * Search radius handed to the matcher per point, clamped from the fix's
     * own reported accuracy. Too small and a fix parked off the road never
     * matches; too large and the matcher jumps to the next street over.
     */
    private const MIN_RADIUS_M = 6.0;
    private const MAX_RADIUS_M = 45.0;

    /**
     * Default per-request coordinate budget. Self-hosted OSRM allows 100
     * (`--max-matching-size`), but the public demo host caps /match at **10**,
     * so this is an env knob rather than a constant — the same code has to work
     * against both without silently returning TooBig for every segment.
     */
    private const DEFAULT_MAX_COORDINATES = 100;

    /**
     * Below this the match is a guess, and drawing a guess as a solid road is
     * worse than drawing the honest raw trace.
     *
     * **Valhalla only.** OSRM's `confidence` is a product of per-transition
     * probabilities, so it decays with the length of the trace and is not
     * comparable between requests: measured against this dataset, chunks that
     * matched real streets to within 8 % of the raw distance came back at
     * 0.002, 0.089, 0.258 and 0.486. There is no threshold that separates good
     * from bad there — the number answers a different question than the one
     * being asked. It is still reported, because it is the engine's own opinion
     * and worth seeing; it just never decides anything.
     *
     * Valhalla's `confidence_score` is normalised and does gate.
     */
    private const MIN_CONFIDENCE = 0.30;

    /**
     * The gate that actually works on both engines: how far the matched road
     * length may drift from the thinned raw length.
     *
     * Above the ceiling the matcher detoured to reach fixes that are not on the
     * network — a field, a factory yard, a trace recorded indoors — and the
     * "road" it drew is fiction. Below the floor it discarded most of the trace
     * and matched a fragment. Snapping legitimately shortens a noisy trace, so
     * the floor is the looser of the two.
     */
    private const MIN_DISTANCE_RATIO = 0.4;
    private const MAX_DISTANCE_RATIO = 2.5;

    private const DEFAULT_TIMEOUT = 12;

    // ------------------------------------------------------------ public API

    /**
     * Cached matched geometry for one employee-day, matching on a miss.
     *
     * Returns null when matching is disabled, the day has nothing to match, or
     * every engine call failed — all three mean "draw the raw trace".
     *
     * `$allowBuild = false` serves the cache and nothing else. That is the mode
     * a fan-out read wants: matching a day costs seconds, so a twenty-person
     * team map that filled its own misses inline would be a forty-second
     * request. Those misses are for `php vayu match` to fill, not a page load.
     *
     * @return array{engine: string, profile: string, status: string, confidence: float,
     *               distanceKm: float, segments: array<int, array>}|null
     */
    public static function forDay(
        string $employeeId,
        string $workDate,
        bool $force = false,
        bool $allowBuild = true,
    ): ?array {
        if (!self::enabled()) {
            return null;
        }

        $sourceCount = (int) (db_fetch_one(
            "SELECT point_count FROM day_routes WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        )['point_count'] ?? 0);

        if ($sourceCount < 2) {
            return null;
        }

        $cached = db_fetch_one(
            "SELECT * FROM route_geometry WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        // A late offline batch moves point_count; an ops profile change moves
        // engine/profile. Either makes the stored geometry a different answer
        // to a different question.
        $fresh = $cached
            && !$force
            && (int) $cached['source_point_count'] === $sourceCount
            && $cached['engine'] === self::engine()
            && $cached['profile'] === self::profile();

        if ($fresh) {
            return self::present($cached);
        }

        if (!$allowBuild) {
            // A stale row is still the wrong answer, so it is not served as a
            // consolation prize — the raw trace is honest, stale roads are not.
            return null;
        }

        return self::build($employeeId, $workDate, $sourceCount);
    }

    /**
     * Drops the cached geometry. Called wherever the raw route is rebuilt —
     * re-matching is deliberately *not* done there, because ingest is a write
     * path and the matcher is a network hop.
     */
    public static function invalidate(string $employeeId, string $workDate): void
    {
        db_execute(
            "DELETE FROM route_geometry WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );
    }

    public static function enabled(): bool
    {
        return filter_var(env('MATCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function engine(): string
    {
        $engine = strtolower(trim((string) env('MATCH_ENGINE', self::ENGINE_OSRM)));

        return $engine === self::ENGINE_VALHALLA ? self::ENGINE_VALHALLA : self::ENGINE_OSRM;
    }

    /**
     * **On OSRM this is a label, not a selector.** The profile is compiled into
     * the graph by `osrm-extract`, and the URL segment is ignored — the public
     * demo answers `/match/v1/banana/...` exactly as it answers `/driving/`.
     * So a MATCH_PROFILE of `motorcycle` pointed at a car graph yields car
     * routing while every payload claims otherwise. Two-wheeler routing on OSRM
     * is an infrastructure decision (build the graph from a motorcycle.lua),
     * never a config one.
     *
     * On Valhalla it *is* a selector: costing is chosen per request, so one
     * host serves motor_scooter, motorcycle and auto from the same tiles.
     */
    public static function profile(): string
    {
        $configured = trim((string) env('MATCH_PROFILE', ''));
        if ($configured !== '') {
            return $configured;
        }

        return self::engine() === self::ENGINE_VALHALLA ? 'motor_scooter' : 'driving';
    }

    private static function baseUrl(): string
    {
        $default = self::engine() === self::ENGINE_VALHALLA
            ? 'http://127.0.0.1:8002'
            : 'https://router.project-osrm.org';

        return rtrim((string) env('MATCH_BASE_URL', $default), '/');
    }

    private static function timeout(): int
    {
        return max(1, (int) env('MATCH_TIMEOUT_SECONDS', self::DEFAULT_TIMEOUT));
    }

    /** Set MATCH_MAX_COORDINATES=10 when pointing at router.project-osrm.org. */
    private static function maxCoordinates(): int
    {
        return max(2, (int) env('MATCH_MAX_COORDINATES', self::DEFAULT_MAX_COORDINATES));
    }

    // -------------------------------------------------------------- building

    private static function build(string $employeeId, string $workDate, int $sourceCount): ?array
    {
        $rows = db_fetch_all(
            "SELECT s.seq, f.lat, f.lng, f.recorded_at, f.accuracy_m
               FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY s.seq, f.recorded_at, f.id",
            [$employeeId, $workDate],
        );

        if (!$rows) {
            self::invalidate($employeeId, $workDate);
            return null;
        }

        /** @var array<int, array<int, array>> $bySeq */
        $bySeq = [];
        foreach ($rows as $row) {
            $bySeq[(int) $row['seq']][] = [
                'lat'      => (float) $row['lat'],
                'lng'      => (float) $row['lng'],
                'ts'       => (int) strtotime($row['recorded_at']),
                'accuracy' => (float) $row['accuracy_m'],
            ];
        }

        $segments = [];
        $matchedCount = 0;
        $matchableCount = 0;
        $distance = 0.0;
        $confidence = 1.0;

        foreach ($bySeq as $seq => $fixes) {
            if (count($fixes) < 2) {
                continue;
            }
            $matchableCount++;

            $matched = self::matchSegment($fixes);

            // The fallback is the raw session trace, encoded the same way, so
            // the client draws one kind of thing and the seam is a flag on the
            // segment rather than a different field.
            $points = $matched['points'] ?? array_map(
                static fn(array $f) => [$f['lat'], $f['lng']],
                $fixes,
            );

            $segmentDistance = self::pathDistanceKm($points);
            $distance += $segmentDistance;

            if ($matched !== null) {
                $matchedCount++;
                $confidence = min($confidence, $matched['confidence']);
            }

            $segments[] = [
                'seq'        => $seq,
                'polyline'   => Polyline::encode($points),
                'pointCount' => count($points),
                'distanceKm' => round($segmentDistance, 4),
                'matched'    => $matched !== null,
                // OSRM often reports 0 here even on a good match; `ratio` is the
                // number this code actually trusted, so both are sent.
                'confidence' => $matched === null ? 0.0 : round($matched['confidence'], 3),
                'ratio'      => $matched === null ? null : round($matched['ratio'], 3),
            ];
        }

        if ($matchableCount === 0) {
            self::invalidate($employeeId, $workDate);
            return null;
        }

        $status = match (true) {
            $matchedCount === 0              => 'failed',
            $matchedCount < $matchableCount  => 'partial',
            default                          => 'ok',
        };

        // A day where nothing matched is not worth a cache row: the next read
        // should retry, because the usual cause is a matcher that was down.
        if ($status === 'failed') {
            self::invalidate($employeeId, $workDate);
            return null;
        }

        $record = [
            'engine'              => self::engine(),
            'profile'             => self::profile(),
            'status'              => $status,
            'segments'            => $segments,
            'confidence'          => round($matchedCount === 0 ? 0.0 : $confidence, 3),
            'matched_distance_km' => round($distance, 4),
            'source_point_count'  => $sourceCount,
        ];

        db_execute(
            "INSERT INTO route_geometry
                (employee_id, work_date, engine, profile, status, segments,
                 confidence, matched_distance_km, source_point_count, matched_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (employee_id, work_date) DO UPDATE SET
                engine = excluded.engine,
                profile = excluded.profile,
                status = excluded.status,
                segments = excluded.segments,
                confidence = excluded.confidence,
                matched_distance_km = excluded.matched_distance_km,
                source_point_count = excluded.source_point_count,
                matched_at = excluded.matched_at",
            [
                $employeeId, $workDate, $record['engine'], $record['profile'], $record['status'],
                json_encode($record['segments']), $record['confidence'], $record['matched_distance_km'],
                $sourceCount, Wire::now(),
            ],
        );

        return self::present($record);
    }

    /** @param array<string, mixed> $row a DB row or a freshly built record */
    private static function present(array $row): array
    {
        $segments = is_string($row['segments'])
            ? Wire::json($row['segments'], [])
            : $row['segments'];

        return [
            'engine'     => (string) $row['engine'],
            'profile'    => (string) $row['profile'],
            'status'     => (string) $row['status'],
            'confidence' => Wire::float($row['confidence']),
            'distanceKm' => Wire::float($row['matched_distance_km']),
            'segments'   => $segments,
        ];
    }

    // -------------------------------------------------------------- matching

    /**
     * One session segment, matched end to end.
     *
     * A chunk that fails poisons the whole segment rather than being stitched
     * to its snapped neighbours: half road, half chord reads as a rendering bug
     * and hides the fact that the matcher failed at all.
     *
     * @param array<int, array{lat: float, lng: float, ts: int, accuracy: float}> $fixes
     * @return array{points: array<int, array{0: float, 1: float}>, confidence: float, ratio: float}|null
     */
    private static function matchSegment(array $fixes): ?array
    {
        $thinned = self::thin(self::collapseStationary($fixes));
        if (count($thinned) < 2) {
            return null;
        }

        $points = [];
        $confidence = 1.0;

        foreach (self::chunk($thinned) as $chunk) {
            $part = self::engine() === self::ENGINE_VALHALLA
                ? self::valhallaMatch($chunk)
                : self::osrmMatch($chunk);

            if ($part === null || count($part['points']) < 2) {
                return null;
            }

            $confidence = min($confidence, $part['confidence']);
            self::append($points, $part['points']);
        }

        if (count($points) < 2) {
            return null;
        }

        if (self::engine() === self::ENGINE_VALHALLA && $confidence < self::MIN_CONFIDENCE) {
            return null;
        }

        $rawKm = self::pathDistanceKm(array_map(
            static fn(array $f) => [$f['lat'], $f['lng']],
            $thinned,
        ));

        if ($rawKm <= 0.0) {
            return null;
        }

        $ratio = self::pathDistanceKm($points) / $rawKm;

        if ($ratio < self::MIN_DISTANCE_RATIO || $ratio > self::MAX_DISTANCE_RATIO) {
            return null;
        }

        return ['points' => $points, 'confidence' => $confidence, 'ratio' => $ratio];
    }

    /**
     * OSRM `/match/v1/{profile}/{coords}`.
     *
     * `timestamps` is the signal that stops the matcher choosing a road the
     * rider could not have covered in fifteen seconds; OSRM demands them
     * strictly increasing, which a device clock does not guarantee, so
     * [self::monotonic] repairs the sequence rather than dropping fixes.
     *
     * `gaps=ignore` because a tracking trace legitimately has holes — tunnel,
     * dead battery, offline queue — and without it OSRM refuses the request
     * outright instead of returning several matchings.
     */
    private static function osrmMatch(array $chunk): ?array
    {
        $coords = [];
        $radiuses = [];
        foreach ($chunk as $fix) {
            $coords[] = self::num($fix['lng']) . ',' . self::num($fix['lat']);
            $radiuses[] = self::num(self::radius($fix['accuracy']));
        }

        $url = self::baseUrl() . '/match/v1/' . rawurlencode(self::profile()) . '/'
            . implode(';', $coords) . '?' . http_build_query([
                'geometries'  => 'polyline6',
                'overview'    => 'full',
                'radiuses'    => implode(';', $radiuses),
                'timestamps'  => implode(';', self::monotonic($chunk)),
                // Collapses the near-duplicate fixes a stationary phone emits,
                // which otherwise make the matcher spin in place.
                'tidy'        => 'true',
                'gaps'        => 'ignore',
                'annotations' => 'false',
            ]);

        $body = self::http('GET', $url);
        if (!is_array($body) || ($body['code'] ?? null) !== 'Ok') {
            return null;
        }

        $matchings = $body['matchings'] ?? null;
        if (!is_array($matchings) || $matchings === []) {
            return null;
        }

        // gaps=ignore can split one trace into several matchings. They come
        // back in trace order, so concatenating them is correct.
        $points = [];
        $confidence = 1.0;

        foreach ($matchings as $matching) {
            if (!is_array($matching) || !is_string($matching['geometry'] ?? null)) {
                continue;
            }
            self::append($points, Polyline::decode($matching['geometry']));
            $confidence = min($confidence, (float) ($matching['confidence'] ?? 0.0));
        }

        return $points === [] ? null : ['points' => $points, 'confidence' => $confidence];
    }

    /**
     * Valhalla `/trace_attributes` with `shape_match=map_snap`.
     *
     * `trace_attributes` rather than `trace_route` because only the former
     * returns `confidence_score`, and a matched road with no confidence is a
     * line this code has no way to distrust.
     */
    private static function valhallaMatch(array $chunk): ?array
    {
        $shape = [];
        $timestamps = self::monotonic($chunk);

        foreach ($chunk as $i => $fix) {
            $shape[] = [
                'lat'    => round($fix['lat'], 6),
                'lon'    => round($fix['lng'], 6),
                'time'   => (int) $timestamps[$i],
                'radius' => round(self::radius($fix['accuracy']), 1),
            ];
        }

        $body = self::http('POST', self::baseUrl() . '/trace_attributes', [
            'shape'       => $shape,
            'costing'     => self::profile(),
            'shape_match' => 'map_snap',
            'filters'     => [
                'attributes' => ['shape', 'confidence_score'],
                'action'     => 'include',
            ],
        ]);

        if (!is_array($body) || !is_string($body['shape'] ?? null)) {
            return null;
        }

        $points = Polyline::decode($body['shape']);

        return count($points) < 2
            ? null
            : ['points' => $points, 'confidence' => (float) ($body['confidence_score'] ?? 0.0)];
    }

    // ------------------------------------------------------------- internals

    /**
     * Collapses each parked run to its arrival and departure fix.
     *
     * The endpoints are both kept rather than one averaged point: the matcher
     * uses the time between consecutive coordinates, and folding a
     * twenty-minute stop into a single fix would make the following hop look
     * like a twenty-minute crawl down whatever road it landed on.
     */
    private static function collapseStationary(array $fixes): array
    {
        $total = count($fixes);
        if ($total < self::STATIONARY_MIN_FIXES) {
            return $fixes;
        }

        $out = [];
        $i = 0;

        while ($i < $total) {
            $anchor = $fixes[$i];
            $end = $i;

            // Measured against the anchor, not the previous fix, so a slow
            // drift out of the car park is not mistaken for standing still.
            while ($end + 1 < $total) {
                $next = $fixes[$end + 1];
                $metres = Geo::distanceM($anchor['lat'], $anchor['lng'], $next['lat'], $next['lng']);

                if ($metres > self::STATIONARY_RADIUS_M) {
                    break;
                }
                $end++;
            }

            if ($end - $i + 1 >= self::STATIONARY_MIN_FIXES) {
                $out[] = $fixes[$i];
                $out[] = $fixes[$end];
            } else {
                for ($k = $i; $k <= $end; $k++) {
                    $out[] = $fixes[$k];
                }
            }

            $i = $end + 1;
        }

        return $out;
    }

    /**
     * Drops fixes closer than [self::MIN_POINT_SPACING_M] to the last one kept.
     * First and last always survive so the segment keeps its endpoints.
     */
    private static function thin(array $fixes): array
    {
        $out = [$fixes[0]];
        $last = count($fixes) - 1;

        for ($i = 1; $i < $last; $i++) {
            $previous = $out[count($out) - 1];
            $metres = Geo::distanceM(
                $previous['lat'], $previous['lng'],
                $fixes[$i]['lat'], $fixes[$i]['lng'],
            );

            if ($metres >= self::MIN_POINT_SPACING_M) {
                $out[] = $fixes[$i];
            }
        }

        $out[] = $fixes[$last];

        return $out;
    }

    /**
     * Splits into requests the engine will accept, overlapping by one
     * coordinate so consecutive chunks meet on the same road instead of at two
     * independently guessed points.
     *
     * The hops are spread evenly rather than packed greedily. Packing leaves a
     * runt at the end — 83 points at a limit of 10 ends on a chunk of two —
     * and a two-point chunk is a directions query with no trace to match, which
     * fails often enough that it alone would sink whole days.
     */
    private static function chunk(array $fixes): array
    {
        $limit = self::maxCoordinates();
        $total = count($fixes);

        if ($total <= $limit) {
            return [$fixes];
        }

        // A chunk of L points covers L-1 hops, and the shared point means the
        // hops, not the points, are what has to be divided up.
        $hops = $total - 1;
        $count = (int) ceil($hops / ($limit - 1));

        $out = [];
        $start = 0;

        for ($i = 0; $i < $count; $i++) {
            $take = (int) ceil(($hops - $start) / ($count - $i));
            $end = $start + $take;
            $out[] = array_slice($fixes, $start, $end - $start + 1);
            $start = $end;   // the overlap
        }

        return $out;
    }

    /**
     * Epoch seconds, forced strictly increasing. A device clock can stall or
     * step back between fixes; OSRM rejects the whole request when it does.
     *
     * @return array<int, int>
     */
    private static function monotonic(array $fixes): array
    {
        $out = [];
        $previous = null;

        foreach ($fixes as $fix) {
            $ts = (int) $fix['ts'];
            if ($previous !== null && $ts <= $previous) {
                $ts = $previous + 1;
            }
            $out[] = $ts;
            $previous = $ts;
        }

        return $out;
    }

    private static function radius(float $accuracy): float
    {
        return max(self::MIN_RADIUS_M, min(self::MAX_RADIUS_M, $accuracy));
    }

    /**
     * Appends, dropping a repeated join point so a chunk seam does not leave a
     * zero-length dot in the polyline.
     */
    private static function append(array &$target, array $addition): void
    {
        if ($addition === []) {
            return;
        }

        if ($target !== []) {
            $last = $target[count($target) - 1];
            if (Geo::distanceM($last[0], $last[1], $addition[0][0], $addition[0][1]) < 1.0) {
                array_shift($addition);
            }
        }

        foreach ($addition as $point) {
            $target[] = $point;
        }
    }

    private static function pathDistanceKm(array $points): float
    {
        $total = 0.0;

        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $total += Geo::distanceKm(
                $points[$i - 1][0], $points[$i - 1][1],
                $points[$i][0], $points[$i][1],
            );
        }

        return $total;
    }

    /** Six decimals is ~11 cm — finer than GPS resolves, and it keeps the URL short. */
    private static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * One request. Every transport failure — DNS, TLS, timeout, 5xx, malformed
     * JSON — is the same answer to the caller: null, keep the raw trace.
     */
    private static function http(string $method, string $url, ?array $json = null): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::timeout(),
            CURLOPT_CONNECTTIMEOUT => min(5, self::timeout()),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'hazra-ev/1.0 (+route matching)',
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($json ?? []);
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($response) || $status !== 200) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
