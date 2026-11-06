<?php

/**
 * Everything the server derives rather than accepts.
 *
 * Stops and visits are derived here from the fix chain; the client never posts
 * them, because letting two devices disagree about what a stop is would make
 * the dashboard argue with itself. The day rollup, the drawn route and the
 * activity timeline are all materialised from the same pass, so no read path
 * has to join four tables or scan location_fixes.
 *
 * Config is read-time semantics: a threshold change never rewrites a stored
 * fix. It re-derives stops for open and same-day sessions and leaves closed
 * historical days exactly as they were classified.
 */
final class Engine
{
    /**
     * The measurement algorithm new rows are stamped with.
     *
     * 1 — the original gated chord sum (migration 004 .. 035). Frozen.
     * 2 — the universal engine: accuracy-scaled step, identified gaps,
     *     `min_step_metres` (migration 036 onward).
     *
     * Bumping this must always come with a new branch in `chainDistance()` and
     * must never alter an existing one, or a historical distance moves.
     */
    public const DISTANCE_ALGO_VERSION = 2;

    /** Worked seconds at or above this count as a full day; below it, partial. */
    private const FULL_DAY_SECONDS = 4 * 3600;

    /** How close a stop must be to a branch before it is promoted to a visit. */
    private const VISIT_MATCH_METRES = 150.0;

    // ------------------------------------------------------------------ ingest

    /**
     * Accepts a batch of fixes for one session.
     *
     * Accuracy is never a reason to reject. A fix worse than
     * minAccuracyMetres is still stored — a weak-GPS stretch has to leave a
     * trail, not a gap — and its low-accuracy status is derived at read time
     * by comparing the stored accuracy_m against config.min_accuracy_metres,
     * which keeps it honest when an admin later retunes the threshold (config
     * is read-time semantics; a stored flag would go stale). The only reasons
     * a fix is turned away are MALFORMED, JUMP (maxJumpKmh) and DUPLICATE.
     *
     * @return array{accepted: string[], rejected: array<int, array{id: string, reason: string}>}
     */
    public static function ingest(array $session, array $fixes): array
    {
        $config      = Ctx::config();
        $maxJump     = (float) $config['max_jump_kmh'];

        usort($fixes, static fn(array $a, array $b) => strcmp((string) $a['recordedAt'], (string) $b['recordedAt']));

        $previous = db_fetch_one(
            "SELECT lat, lng, recorded_at FROM location_fixes
              WHERE session_id = ? ORDER BY recorded_at DESC LIMIT 1",
            [$session['id']],
        ) ?: null;

        $accepted = [];
        $rejected = [];
        $now = Wire::now();

        foreach ($fixes as $fix) {
            $clientId = (string) ($fix['id'] ?? $fix['clientId'] ?? '');

            if ($clientId === '' || !isset($fix['latitude'], $fix['longitude'])) {
                $rejected[] = ['id' => $clientId, 'reason' => 'MALFORMED'];
                continue;
            }

            $lat        = (float) $fix['latitude'];
            $lng        = (float) $fix['longitude'];
            $accuracy   = (float) ($fix['accuracy'] ?? 0);
            $speed      = (float) ($fix['speedKmh'] ?? 0);
            $heading    = self::heading($fix['headingDeg'] ?? $fix['heading'] ?? null);
            $recordedAt = Wire::ts($fix['recordedAt'] ?? null) ?? $now;

            // Accuracy is no longer a rejection reason — a low-accuracy fix is
            // stored like any other and flagged at read time (see docblock).
            if ($previous !== null) {
                $km      = Geo::distanceKm((float) $previous['lat'], (float) $previous['lng'], $lat, $lng);
                $seconds = Wire::seconds($previous['recorded_at'], $recordedAt);

                if ($seconds > 0 && Geo::impliedKmh($km, $seconds) > $maxJump) {
                    $rejected[] = ['id' => $clientId, 'reason' => 'JUMP'];
                    continue;
                }
            }

            try {
                db_execute(
                    "INSERT INTO location_fixes
                        (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh, heading_deg)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$session['id'], $session['employee_id'], $clientId, $recordedAt, $now, $lat, $lng, $accuracy, $speed, $heading],
                );
            } catch (PDOException) {
                // The unique (employee_id, client_id) index is the idempotency
                // guarantee: a replayed batch is reported, never duplicated.
                $rejected[] = ['id' => $clientId, 'reason' => 'DUPLICATE'];
                continue;
            }

            $accepted[] = $clientId;
            $previous = ['lat' => $lat, 'lng' => $lng, 'recorded_at' => $recordedAt];
        }

        if ($accepted) {
            // recompute() refreshes every session in the day, this one included,
            // so there is no separate refreshSession() call to make here.
            self::recompute($session['employee_id'], $session['work_date']);
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * Normalises a device bearing to `0 <= heading < 360` or NULL.
     *
     * **NULL is not 0** — 0 is due north (`03` §5, `02` §3.11). A stationary
     * device has no bearing and platforms say so differently: Android reports
     * `-1`, iOS can report `NaN`, a synthetic heartbeat fix carries nothing at
     * all, and a JSON encoder that cannot express NaN sends the string "NaN".
     * Every one of those is *unknown* and every one of them stores NULL, so the
     * map draws an unrotated marker instead of swinging it north on every
     * parked fix. Nothing here ever substitutes a default.
     */
    private static function heading(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
            return null;           // absent, null, "NaN", or anything non-numeric
        }

        $heading = (float) $value;

        if (is_nan($heading) || is_infinite($heading) || $heading < 0 || $heading >= 360) {
            return null;           // -1, NaN, out of range — all unknown
        }

        return $heading;
    }

    /**
     * Recomputes a session's distance and point count from its fix chain.
     *
     * `point_count` is deliberately the **raw** stored fix count: it answers
     * "how many fixes does this session have" — which is what the route and the
     * trail draw — not "how many of them moved the odometer". The two gates
     * below shape `distance_km` only.
     */
    public static function refreshSession(string $sessionId): void
    {
        $session = db_fetch_one("SELECT * FROM work_sessions WHERE id = ?", [$sessionId]);

        if (!$session) {
            return;
        }

        $algo = (int) ($session['distance_algo_version'] ?? 1);

        // recorded_at and source are selected for v2's gap classification;
        // v1 ignores both, which is why it can stay byte-identical.
        $fixes = db_fetch_all(
            "SELECT lat, lng, accuracy_m, recorded_at, source FROM location_fixes
              WHERE session_id = ? ORDER BY recorded_at, id",
            [$sessionId],
        );

        $measured = self::chainDistance($fixes, $algo);

        // distance_km stays the headline figure and keeps its meaning: it is
        // the verified number, never verified + incomplete. A v1 row leaves the
        // three reporting columns NULL, because v1 has no coverage concept and
        // writing 0 or 100 there would be a claim it never made.
        db_execute(
            "UPDATE work_sessions
                SET distance_km = ?, point_count = ?,
                    verified_distance_km = ?, incomplete_distance_km = ?, gps_coverage_percent = ?
              WHERE id = ?",
            [
                round($measured['verifiedKm'], 4),
                count($fixes),
                $algo < 2 ? null : round($measured['verifiedKm'], 4),
                $algo < 2 ? null : round($measured['incompleteKm'], 4),
                $algo < 2 ? null : $measured['coveragePercent'],
                $sessionId,
            ],
        );

        self::storeGaps($session, $measured['gaps']);
    }

    /**
     * Replaces one session's recorded GPS gaps.
     *
     * Delete-then-insert rather than a merge: gaps are wholly derived from the
     * fix chain and the thresholds in force, so a re-derive owns the set
     * outright and partial reconciliation could only leave a stale row behind.
     * A v1 session measures no gaps, so this clears the table for it and writes
     * nothing — the same answer a day with continuous GPS gives.
     */
    private static function storeGaps(array $session, array $gaps): void
    {
        db_execute("DELETE FROM session_gps_gaps WHERE session_id = ?", [$session['id']]);

        foreach ($gaps as $gap) {
            db_execute(
                "INSERT INTO session_gps_gaps
                    (session_id, employee_id, work_date, gap_start, gap_end, seconds,
                     displacement_m, classification)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $session['id'], $session['employee_id'], $session['work_date'],
                    $gap['start'], $gap['end'], $gap['seconds'],
                    $gap['displacementM'], $gap['class'],
                ],
            );
        }
    }

    /**
     * Travelled distance over an ordered fix chain, in kilometres.
     *
     * Two gates stand between the stored fixes and the number, because a parked
     * device still produces fixes and summing every consecutive pair turns GPS
     * jitter into kilometres nobody walked:
     *
     *   - **accuracy** — a fix worse than `min_accuracy_metres` does not get to
     *     measure a leg. It stays stored and it stays on the drawn route
     *     (accuracy is read-time semantics, never a reason to drop data — see
     *     ingest() above), it is only skipped here. Skipping carries the last
     *     *accepted* fix forward as the anchor, so a weak-GPS stretch measures
     *     one honest leg across it instead of a false detour out to the bad fix
     *     and back.
     *   - **minimum step** — a hop shorter than `distance_filter_metres` is
     *     noise rather than travel: the OS was asked to suppress fixes under
     *     that distance in the first place, so anything closer is jitter or the
     *     client's 5-minute heartbeat. It contributes 0 **and leaves the anchor
     *     where it is** — moving the anchor on a rejected hop is exactly what
     *     lets jitter accumulate in sub-threshold increments. Because the anchor
     *     holds, a genuinely slow walk is still measured in full: displacement
     *     from the anchor keeps growing until it clears the gate.
     *
     * Both thresholds are read from the org's tracking_config — the same row
     * deriveStops() and liveStatus() read, resolved through Ctx so a per-tenant
     * value always wins over the documented default. Nothing is stored about
     * the decision, so retuning either threshold re-derives distance on the next
     * refresh instead of rewriting a fix. A non-positive threshold disables its
     * own gate rather than swallowing the whole session.
     *
     * Public because RoutesController measures today's not-yet-cached route off
     * the raw fixes and has to arrive at the same kilometres the cached
     * day_routes row carries — one rule, not two implementations of it.
     *
     * @param array<int, array{lat: mixed, lng: mixed, accuracy_m?: mixed}> $fixes ordered oldest first
     */
    private static function chainDistanceV1(array $fixes): float
    {
        $minAccuracyM = (float) Ctx::configValue('min_accuracy_metres', 50);
        $minStepM     = (float) Ctx::configValue('distance_filter_metres', 20);

        $distance = 0.0;
        $anchor   = null;

        foreach ($fixes as $fix) {
            if ($minAccuracyM > 0 && (float) ($fix['accuracy_m'] ?? 0) > $minAccuracyM) {
                continue;                       // stored and drawn, just not measured
            }

            $lat = (float) $fix['lat'];
            $lng = (float) $fix['lng'];

            if ($anchor === null) {
                $anchor = [$lat, $lng];
                continue;
            }

            $km = Geo::distanceKm($anchor[0], $anchor[1], $lat, $lng);

            if ($km * 1000 < $minStepM) {
                continue;                       // jitter: no distance, anchor unmoved
            }

            $distance += $km;
            $anchor = [$lat, $lng];
        }

        return $distance;
    }

    /**
     * Verified kilometres over an ordered fix chain — the one number every
     * distance surface in the system reads.
     *
     * Kept as a float accessor because that is what the live route and the
     * cached day row compare against each other; `chainDistance()` below
     * carries the rest of what v2 knows.
     */
    public static function chainDistanceKm(array $fixes, int $algoVersion = self::DISTANCE_ALGO_VERSION): float
    {
        return self::chainDistance($fixes, $algoVersion)['verifiedKm'];
    }

    /**
     * Full measurement over an ordered fix chain, pinned to an algorithm
     * version.
     *
     * **Why a version at all.** The contract is that a historical distance is
     * never silently recalculated, and that is not free in this design: nothing
     * is stored about a measurement decision (so retuning a threshold
     * re-derives on the next refresh), and `recompute()` re-derives a whole day
     * on any late-arriving fix. Either property would quietly move an old
     * number the first time an old day was touched. So the algorithm is pinned
     * on the row — `work_sessions.distance_algo_version`, `day_routes` likewise
     * — v1 stays here verbatim and forever, and only rows created after
     * migration 036 are measured by v2.
     *
     * v1 reports no coverage, because it has no concept of one: `null` is the
     * honest answer there, not 100.
     *
     * @param array<int, array{lat: mixed, lng: mixed, accuracy_m?: mixed,
     *                         recorded_at?: mixed, source?: mixed}> $fixes oldest first
     * @return array{verifiedKm: float, incompleteKm: float,
     *               coveragePercent: float|null, gaps: array<int, array>}
     */
    public static function chainDistance(array $fixes, int $algoVersion = self::DISTANCE_ALGO_VERSION): array
    {
        if ($algoVersion < 2) {
            return [
                'verifiedKm'      => self::chainDistanceV1($fixes),
                'incompleteKm'    => 0.0,
                'coveragePercent' => null,
                'gaps'            => [],
            ];
        }

        return self::chainDistanceV2($fixes);
    }

    /**
     * v2 — the universal engine: cycling, road travel and train on one rule.
     *
     * Still haversine over consecutive GPS fixes and nothing else. No routing
     * service, no road-network length and no matched geometry reaches this
     * number; map matching is a drawing, never a measurement. Transport mode is
     * metadata and does not appear here at all, which is what makes the engine
     * universal — a train and a bicycle are measured by the identical code.
     *
     * Three things differ from v1:
     *
     *   - **The step scales with the fix's own error.** v1 refused to measure a
     *     leg from any fix worse than `min_accuracy_metres` and carried the
     *     anchor forward, so a weak-GPS stretch collapsed to one straight chord
     *     and real travel was thrown away — which is the under-count this whole
     *     change exists to fix. v2 instead asks a weak fix to move *further
     *     than it could be wrong by*: the hop must clear
     *     `max(min_step_metres, accuracy_step_factor * accuracy_m)`. A 40 m fix
     *     that moved 200 m is travel and now counts; one that moved 25 m cannot
     *     be told from noise and still does not. `accuracy_step_factor = 0`
     *     restores a flat floor.
     *   - **`min_step_metres`, not `distance_filter_metres`.** That column was
     *     doing two opposing jobs; see migration 036. Capture went down to 10 m
     *     so corners survive, measurement stayed at 15 m so a parked phone's
     *     drift never books distance.
     *   - **Gaps are identified, not bridged.** See below.
     *
     * The anchor still holds on a rejected hop — moving it is exactly what lets
     * noise accumulate in sub-threshold increments, and holding it is what
     * keeps genuinely slow travel measurable, since displacement from the held
     * anchor keeps growing until it clears the step.
     *
     * **A quiet stretch is only a gap when something moved across it.** Elapsed
     * time alone cannot separate a parked vehicle from a tunnel: the capture
     * distance filter means a parked device emits nothing until its five-minute
     * heartbeat, which is well past the incomplete threshold, so classifying on
     * time alone would report every lunch break and every long customer visit
     * as broken GPS coverage and make the coverage figure meaningless.
     * Displacement is what decides, because it is what says whether any
     * distance is unaccounted for. The closing fix's `source` is recorded on
     * the gap for the UI and for debugging — a `HEARTBEAT` close is what
     * explains most non-gaps — but it is deliberately not part of the
     * arithmetic.
     *
     * Distance across a real gap is **never** verified: we do not know what was
     * driven between the two ends, so the straight line goes to
     * `incompleteKm` and is reported under its own label, never summed into the
     * headline number.
     */
    private static function chainDistanceV2(array $fixes): array
    {
        $config = Ctx::config();

        $minStepM  = (float) ($config['min_step_metres'] ?? 15);
        $factor    = (float) ($config['accuracy_step_factor'] ?? 1);
        $gapWarnS  = (int) ($config['gps_gap_warning_seconds'] ?? 60);
        $gapBreakS = (int) ($config['gps_gap_incomplete_seconds'] ?? 180);

        $verified   = 0.0;
        $incomplete = 0.0;
        $gaps       = [];

        $anchor  = null;   // last point distance was measured FROM
        $prev    = null;   // immediately preceding fix; gap timing only
        $spanS   = 0;      // total tracked seconds across the chain
        $gapS    = 0;      // of which unaccounted for

        foreach ($fixes as $fix) {
            $lat = (float) $fix['lat'];
            $lng = (float) $fix['lng'];
            $acc = (float) ($fix['accuracy_m'] ?? 0);
            $at  = (string) ($fix['recorded_at'] ?? '');

            if ($prev === null) {
                $anchor = [$lat, $lng];
                $prev   = [$lat, $lng, $acc, $at];
                continue;
            }

            $elapsed = max(0, Wire::seconds($prev[3], $at));
            $spanS  += $elapsed;

            $hopKm = Geo::distanceKm($prev[0], $prev[1], $lat, $lng);

            // Worse of the two ends decides what counts as movement across a
            // quiet stretch: a gap bounded by one weak fix is not evidence.
            $gapStepM = max($minStepM, $factor * max($acc, $prev[2]));

            if ($elapsed >= $gapWarnS && $hopKm * 1000 > $gapStepM) {
                $gaps[] = [
                    'start'         => $prev[3],
                    'end'           => $at,
                    'seconds'       => $elapsed,
                    'displacementM' => round($hopKm * 1000, 1),
                    'class'         => $elapsed >= $gapBreakS ? 'INCOMPLETE' : 'WARNING',
                    'source'        => (string) ($fix['source'] ?? 'STREAM'),
                ];

                $incomplete += $hopKm;
                $gapS       += $elapsed;

                // Both restart on the far side: measuring from an anchor on the
                // near side would bridge the gap, which is the one thing a gap
                // must never be.
                $anchor = [$lat, $lng];
                $prev   = [$lat, $lng, $acc, $at];
                continue;
            }

            $prev  = [$lat, $lng, $acc, $at];
            $stepM = max($minStepM, $factor * $acc);
            $km    = Geo::distanceKm($anchor[0], $anchor[1], $lat, $lng);

            if ($km * 1000 < $stepM) {
                continue;      // noise: no distance, and the anchor HOLDS
            }

            $verified += $km;
            $anchor = [$lat, $lng];
        }

        return [
            'verifiedKm'      => $verified,
            'incompleteKm'    => $incomplete,
            'coveragePercent' => $spanS <= 0
                ? 100.0
                : round(max(0.0, ($spanS - $gapS) / $spanS) * 100, 2),
            'gaps'            => $gaps,
        ];
    }

    /**
     * One ordered fix chain per session of a day, oldest session first.
     *
     * v2 measures a day session by session rather than as one chain; see
     * `buildDayRoute`. Sessions with no fixes are skipped — they contribute
     * neither distance nor tracked seconds.
     *
     * @return array<int, array<int, array>>
     */
    private static function daySessionFixes(string $employeeId, string $workDate): array
    {
        $sessions = db_fetch_all(
            "SELECT id FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
            [$employeeId, $workDate],
        );

        $chains = [];

        foreach ($sessions as $session) {
            $chain = db_fetch_all(
                "SELECT lat, lng, accuracy_m, recorded_at, source FROM location_fixes
                  WHERE session_id = ? ORDER BY recorded_at, id",
                [$session['id']],
            );

            if ($chain) {
                $chains[] = $chain;
            }
        }

        return $chains;
    }

    /**
     * The algorithm version in force for one employee-day.
     *
     * The **minimum** across the day's sessions, so a day that straddles the
     * migration is measured by the older rule rather than having half of it
     * silently restated. A day with no sessions has nothing to freeze and gets
     * the current version.
     */
    public static function dayAlgoVersion(string $employeeId, string $workDate): int
    {
        $row = db_fetch_one(
            "SELECT MIN(distance_algo_version) AS v FROM work_sessions
              WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        return $row === null || $row === false || $row['v'] === null
            ? self::DISTANCE_ALGO_VERSION
            : (int) $row['v'];
    }

    // -------------------------------------------------------------- derivation

    /**
     * Re-derives everything downstream of the fixes for one employee-day, then
     * rebuilds the rollup, the route and the timeline.
     *
     * Session distance leads, because rollupDay() sums work_sessions.distance_km
     * and the drawn route is measured with the same gates: if the odometer were
     * not re-derived here, a threshold change would regate the route while
     * leaving the day summary on the old number, and ConfigController's promise
     * that same-day sessions are re-derived would only be half true. It also
     * makes recompute() the one entry point a distance backfill needs.
     */
    public static function recompute(string $employeeId, string $workDate): void
    {
        self::refreshSessions($employeeId, $workDate);
        self::deriveStops($employeeId, $workDate);
        self::promoteVisits($employeeId, $workDate);
        self::rebuildActivity($employeeId, $workDate);
        self::buildDayRoute($employeeId, $workDate);
        self::rollupDay($employeeId, $workDate);
    }

    /** Re-derives distance_km and point_count for every session in one day. */
    private static function refreshSessions(string $employeeId, string $workDate): void
    {
        $sessions = db_fetch_all(
            "SELECT id FROM work_sessions WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        foreach ($sessions as $session) {
            self::refreshSession($session['id']);
        }
    }

    /**
     * A stop is a run of fixes staying inside stopRadiusMetres for at least
     * stopThresholdMinutes. No geofence involved.
     */
    public static function deriveStops(string $employeeId, string $workDate): void
    {
        $config    = Ctx::config();
        $radius    = (float) $config['stop_radius_metres'];
        $threshold = (int) $config['stop_threshold_minutes'] * 60;
        $version   = (int) $config['version'];

        $sessions = db_fetch_all(
            "SELECT id FROM work_sessions WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        foreach ($sessions as $session) {
            $fixes = db_fetch_all(
                "SELECT lat, lng, recorded_at FROM location_fixes
                  WHERE session_id = ? ORDER BY recorded_at, id",
                [$session['id']],
            );

            $derived = self::clusterStops($fixes, $radius, $threshold);
            self::reconcileStops($employeeId, $workDate, $session['id'], $derived, $version);
        }
    }

    /** @return array<int, array{arrival: string, departure: string, lat: float, lng: float, radius: float}> */
    private static function clusterStops(array $fixes, float $radiusM, int $thresholdSeconds): array
    {
        $stops = [];
        $count = count($fixes);
        $i = 0;

        while ($i < $count) {
            $anchorLat = (float) $fixes[$i]['lat'];
            $anchorLng = (float) $fixes[$i]['lng'];

            $j = $i + 1;
            while ($j < $count) {
                $d = Geo::distanceM($anchorLat, $anchorLng, (float) $fixes[$j]['lat'], (float) $fixes[$j]['lng']);
                if ($d > $radiusM) {
                    break;
                }
                $j++;
            }

            $span = Wire::seconds($fixes[$i]['recorded_at'], $fixes[$j - 1]['recorded_at']);

            if ($j - $i >= 2 && $span >= $thresholdSeconds) {
                $cluster = array_slice($fixes, $i, $j - $i);
                $points  = array_map(
                    static fn(array $f) => [(float) $f['lat'], (float) $f['lng']],
                    $cluster,
                );

                [$cLat, $cLng] = Geo::centroid($points);

                $observed = 0.0;
                foreach ($points as $p) {
                    $observed = max($observed, Geo::distanceM($cLat, $cLng, $p[0], $p[1]));
                }

                $stops[] = [
                    'arrival'   => Wire::ts($cluster[0]['recorded_at']),
                    'departure' => Wire::ts($cluster[count($cluster) - 1]['recorded_at']),
                    'lat'       => $cLat,
                    'lng'       => $cLng,
                    'radius'    => round($observed, 2),
                ];

                $i = $j;
                continue;
            }

            $i++;
        }

        return $stops;
    }

    /**
     * Writes the derived stop set without disturbing stops that already carry a
     * visit — a re-derivation must never orphan a report.
     */
    private static function reconcileStops(
        string $employeeId,
        string $workDate,
        string $sessionId,
        array $derived,
        int $version,
    ): void {
        $existing = db_fetch_all(
            "SELECT * FROM stop_records WHERE session_id = ? ORDER BY arrival",
            [$sessionId],
        );

        $byArrival = [];
        foreach ($existing as $row) {
            $byArrival[substr($row['arrival'], 0, 16)] = $row;
        }

        $seen = [];

        foreach ($derived as $stop) {
            $key = substr($stop['arrival'], 0, 16);
            $seen[$key] = true;

            if (isset($byArrival[$key])) {
                db_execute(
                    "UPDATE stop_records
                        SET departure = ?, lat = ?, lng = ?, radius_m = ?, derived_from_config_version = ?
                      WHERE id = ?",
                    [$stop['departure'], $stop['lat'], $stop['lng'], $stop['radius'], $version, $byArrival[$key]['id']],
                );
                continue;
            }

            db_execute(
                "INSERT INTO stop_records
                    (id, session_id, employee_id, work_date, arrival, departure, lat, lng, radius_m, derived_from_config_version)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Uuid::v4(), $sessionId, $employeeId, $workDate,
                    $stop['arrival'], $stop['departure'], $stop['lat'], $stop['lng'], $stop['radius'], $version,
                ],
            );
        }

        foreach ($byArrival as $key => $row) {
            if (!isset($seen[$key]) && $row['visit_id'] === null) {
                db_execute("DELETE FROM stop_records WHERE id = ?", [$row['id']]);
            }
        }
    }

    /**
     * Promotes a stop to a company visit when it sits on a known branch.
     * Walk-ins stay StopRecords — the visit *is* a known-company event.
     */
    public static function promoteVisits(string $employeeId, string $workDate): void
    {
        $stops = db_fetch_all(
            "SELECT * FROM stop_records WHERE employee_id = ? AND work_date = ? ORDER BY arrival",
            [$employeeId, $workDate],
        );

        if (!$stops) {
            return;
        }

        $branches = db_fetch_all(
            "SELECT b.*, c.id AS company_id FROM branches b
               JOIN companies c ON c.id = b.company_id
              WHERE c.org_id = ?",
            [Ctx::orgId()],
        );

        foreach ($stops as $stop) {
            $match = null;
            $best  = self::VISIT_MATCH_METRES;

            foreach ($branches as $branch) {
                $d = Geo::distanceM((float) $stop['lat'], (float) $stop['lng'], (float) $branch['lat'], (float) $branch['lng']);
                if ($d <= $best) {
                    $best  = $d;
                    $match = $branch;
                }
            }

            if ($match === null) {
                continue;
            }

            $status = $stop['departure'] === null ? 'in_progress' : 'completed';

            if ($stop['visit_id'] !== null) {
                db_execute(
                    "UPDATE company_visits SET departure = ?, status = ? WHERE id = ?",
                    [$stop['departure'], $status, $stop['visit_id']],
                );
                continue;
            }

            $visitId = Uuid::v4();

            db_execute(
                "INSERT INTO company_visits
                    (id, session_id, employee_id, work_date, company_id, branch_id, stop_id,
                     arrival, departure, lat, lng, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $visitId, $stop['session_id'], $employeeId, $workDate,
                    $match['company_id'], $match['id'], $stop['id'],
                    $stop['arrival'], $stop['departure'], $stop['lat'], $stop['lng'], $status,
                ],
            );

            db_execute("UPDATE stop_records SET visit_id = ? WHERE id = ?", [$visitId, $stop['id']]);
        }
    }

    // ---------------------------------------------------------------- rollups

    /**
     * The newest stored fix per session for one employee-day.
     *
     * "Evidence" means a row in location_fixes and nothing else. Not a status,
     * not device_health: `offline` (a stale received_at) and
     * `location_unavailable` (a stale recorded_at, or a device reporting itself
     * blind) are deliberately different statuses for DISPLAY and must stay
     * that way — but for worked-time arithmetic they are the same thing, dark,
     * so the arithmetic reads fix recency instead of either status. A device
     * that was dark and then backfilled its offline queue has evidence for the
     * whole stretch and must not be penalised for having been late with it.
     *
     * Public because CloseDaysCommand stamps its midnight close against the
     * same horizon; two definitions of "last evidence" would let a session's
     * stamped end disagree with the worked time derived from it.
     *
     * Lexicographic MAX over recorded_at, which is how the rest of the Engine
     * already orders fixes (`ORDER BY recorded_at, id` everywhere) — correct
     * for the single canonical UTC timestamp format every writer stores.
     *
     * @return array<string, string> session id -> last recorded_at
     */
    public static function sessionLastFixes(string $employeeId, string $workDate): array
    {
        $rows = db_fetch_all(
            "SELECT f.session_id AS session_id, MAX(f.recorded_at) AS last_fix
               FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              GROUP BY f.session_id",
            [$employeeId, $workDate],
        );

        $last = [];

        foreach ($rows as $row) {
            if ($row['last_fix'] !== null && $row['last_fix'] !== '') {
                $last[(string) $row['session_id']] = (string) $row['last_fix'];
            }
        }

        return $last;
    }

    /**
     * Worked seconds for one session, with its trailing dark tail removed.
     *
     * THE RULE: a session's worked time runs from its start to the last moment
     * we have evidence for. Dark time counts as worked ONLY when it sits
     * between two known-good fixes.
     *
     * That asymmetry is the whole point, so it is encoded directly rather than
     * approximated by summing gaps:
     *
     *   - An INTERIOR gap (a fix on each side) COUNTS, in full, by
     *     construction. It lies inside [started_at, lastFix], and this function
     *     never subdivides that span — so no gap-walk, no per-gap threshold and
     *     no way for an interior hole to be scored twice or dropped. The
     *     employee was working; there was simply no signal, and the device
     *     backfills the stretch from its offline queue anyway.
     *   - A TRAILING dark tail (no fix ever follows) does NOT count. There is
     *     no evidence of work after the last fix, so worked time stops there.
     *
     * Closed session: ended_at is the employee's own declaration and stands as
     * it always has, unless the span from the last fix to that declaration
     * EXCEEDS offlineThresholdMinutes — a strict >, so a tail exactly at the
     * threshold is not trimmed and nothing moves by an off-by-one second. Short
     * tails are normal (a session ends between two sync ticks) and trimming
     * them would make every honest day read a minute or two short.
     *
     * Open session: capped at the last fix, never at the clock. This is what
     * stops a dead phone in a drawer accruing hours against a roster that
     * already reads `offline` — the roster and the timesheet now disagree about
     * nothing. An open session is capped unconditionally, with no threshold:
     * `$now` is not evidence of anything, whereas a closed session's ended_at
     * is a declaration that someone made. min() with $now only guards a fix
     * timestamped in the future by a skewed device clock.
     *
     * No fixes at all: 0, not the wall-clock span. A session with no evidence
     * whatsoever is not a session anyone can be paid for.
     */
    private static function sessionWorkedSeconds(
        array $session,
        ?string $lastFixAt,
        string $now,
        int $darkSeconds,
    ): int {
        if ($lastFixAt === null) {
            return 0;
        }

        $startedAt = $session['started_at'] ?? null;
        $endedAt   = $session['ended_at'] ?? null;

        if ($endedAt === null) {
            return min(Wire::seconds($startedAt, $lastFixAt), Wire::seconds($startedAt, $now));
        }

        // Wire::seconds floors at 0, so a fix recorded after ended_at reads as
        // no tail at all and the declaration stands.
        return Wire::seconds($lastFixAt, $endedAt) > $darkSeconds
            ? Wire::seconds($startedAt, $lastFixAt)
            : Wire::seconds($startedAt, $endedAt);
    }

    /**
     * The one table Attendance, DaySummary, the attendance matrix and both
     * statistics endpoints all read.
     *
     * worked_seconds is evidence-bounded — see sessionWorkedSeconds(). Same
     * column, same wire shape, corrected value: a day whose device went dark
     * and never came back reports the hours it has fixes for, not the hours
     * between its first fix and midnight.
     */
    public static function rollupDay(string $employeeId, string $workDate): void
    {
        $sessions = db_fetch_all(
            "SELECT * FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
            [$employeeId, $workDate],
        );

        $now = Wire::now();
        $worked = 0;
        $distance = 0.0;
        $joining = null;
        $end = null;
        $anyOpen = false;

        $incomplete = 0.0;

        // Read-time, from config, per rollup — never stored. An admin who
        // retunes offlineThresholdMinutes re-derives worked time on the next
        // recompute instead of leaving a frozen decision behind.
        $darkSeconds = (int) Ctx::config()['offline_threshold_minutes'] * 60;
        $lastFixes   = self::sessionLastFixes($employeeId, $workDate);

        foreach ($sessions as $session) {
            $worked   += self::sessionWorkedSeconds(
                $session,
                $lastFixes[(string) $session['id']] ?? null,
                $now,
                $darkSeconds,
            );
            $distance += (float) $session['distance_km'];
            // NULL on a v1 session and coalesced to 0 here: a day's incomplete
            // total is a sum over the sessions that measured one, and the
            // day-level column is itself left NULL for a v1 day below.
            $incomplete += (float) ($session['incomplete_distance_km'] ?? 0);
            $joining   = $joining === null ? $session['started_at'] : min($joining, $session['started_at']);

            if ($session['ended_at'] === null) {
                $anyOpen = true;
            } else {
                $end = $end === null ? $session['ended_at'] : max($end, $session['ended_at']);
            }
        }

        $companies = (int) (db_fetch_one(
            "SELECT COUNT(DISTINCT company_id) AS n FROM company_visits WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        )['n'] ?? 0);

        $reports = (int) (db_fetch_one(
            "SELECT COUNT(*) AS n FROM visit_reports WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        )['n'] ?? 0);

        $stops = db_fetch_all(
            "SELECT arrival, departure FROM stop_records WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        $stopSeconds = 0;
        $longestStop = 0;
        foreach ($stops as $stop) {
            $seconds = Wire::seconds($stop['arrival'], $stop['departure'] ?? $now);
            $stopSeconds += $seconds;
            $longestStop = max($longestStop, $seconds);
        }

        $status = self::classifyDay($workDate, count($sessions), $worked);

        // distance_km keeps its meaning — the verified figure, never verified
        // plus incomplete. The three reporting columns stay NULL for a v1 day,
        // which is what marks it as predating the universal engine rather than
        // as a day that happened to have perfect coverage.
        $algo = self::dayAlgoVersion($employeeId, $workDate);

        // Coverage is the duration-weighted day figure buildDayRoute already
        // computed; recompute() runs it immediately before this, so reading it
        // back beats recomputing the weighting in two places.
        $coverage = $algo < 2 ? null : (db_fetch_one(
            "SELECT gps_coverage_percent FROM day_routes WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        )['gps_coverage_percent'] ?? null);

        db_execute(
            "INSERT INTO attendance_days
                (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
                 distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at,
                 verified_distance_km, incomplete_distance_km, gps_coverage_percent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (employee_id, work_date) DO UPDATE SET
                status = excluded.status,
                joining_time = excluded.joining_time,
                end_time = excluded.end_time,
                session_count = excluded.session_count,
                worked_seconds = excluded.worked_seconds,
                distance_km = excluded.distance_km,
                companies_visited = excluded.companies_visited,
                reports_submitted = excluded.reports_submitted,
                stop_seconds = excluded.stop_seconds,
                longest_stop_seconds = excluded.longest_stop_seconds,
                computed_at = excluded.computed_at,
                verified_distance_km = excluded.verified_distance_km,
                incomplete_distance_km = excluded.incomplete_distance_km,
                gps_coverage_percent = excluded.gps_coverage_percent",
            [
                $employeeId, $workDate, $status, $joining, $anyOpen ? null : $end,
                count($sessions), $worked, round($distance, 4), $companies, $reports,
                $stopSeconds, $longestStop, $now,
                $algo < 2 ? null : round($distance, 4),
                $algo < 2 ? null : round($incomplete, 4),
                $coverage,
            ],
        );
    }

    /**
     * Calendar rules. Holiday and weekend win over presence so a day worked on
     * a public holiday still reads as a holiday on the calendar.
     */
    public static function classifyDay(string $workDate, int $sessionCount, int $workedSeconds): string
    {
        $holiday = db_fetch_one(
            "SELECT date FROM holidays WHERE org_id = ? AND date = ?",
            [Ctx::orgId(), $workDate],
        );

        if ($holiday) {
            return 'holiday';
        }

        $isoDay = (int) (new DateTimeImmutable($workDate))->format('N');
        if (in_array($isoDay, Ctx::weekendDays(), true)) {
            return 'weekend';
        }

        if ($sessionCount === 0) {
            return $workDate > Ctx::today() ? 'no_data' : 'absent';
        }

        return $workedSeconds >= self::FULL_DAY_SECONDS ? 'present' : 'partial';
    }

    /**
     * The drawn route, simplified once and kept. The admin map reads one row
     * instead of a thousand-row scan, and raw-fix retention becomes an
     * operational choice rather than a product one.
     */
    public static function buildDayRoute(string $employeeId, string $workDate): void
    {
        $rows = db_fetch_all(
            "SELECT f.lat, f.lng, f.recorded_at, f.accuracy_m, f.speed_kmh, f.heading_deg, s.seq
               FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY f.recorded_at, f.id",
            [$employeeId, $workDate],
        );

        if (!$rows) {
            db_execute("DELETE FROM day_routes WHERE employee_id = ? AND work_date = ?", [$employeeId, $workDate]);
            MapMatch::invalidate($employeeId, $workDate);
            return;
        }

        $tuples = [];
        $minLat = $maxLat = (float) $rows[0]['lat'];
        $minLng = $maxLng = (float) $rows[0]['lng'];

        // Same measurement as refreshSession(), so the route card and the day
        // summary cannot disagree about how far the employee went. The polyline
        // below still draws every fix — the gates shape the number, not the line.
        //
        // v2 measures **per session and sums**, where v1 measured the whole
        // day's chain in one pass. Two reasons, and both are why v1 is left
        // alone rather than corrected: a pause between sessions is not tracked
        // time, so classifying it as a GPS gap would report every lunch break
        // as broken coverage; and measuring across the boundary books the leg
        // from where one session ended to where the next began, which is travel
        // that happened while nothing was tracking it.
        $algo = self::dayAlgoVersion($employeeId, $workDate);

        if ($algo < 2) {
            $distance   = self::chainDistanceKm($rows, $algo);
            $incomplete = null;
            $coverage   = null;
        } else {
            $distance   = 0.0;
            $incomplete = 0.0;
            $spanS      = 0;
            $goodS      = 0.0;

            foreach (self::daySessionFixes($employeeId, $workDate) as $chain) {
                $measured    = self::chainDistance($chain, $algo);
                $distance   += $measured['verifiedKm'];
                $incomplete += $measured['incompleteKm'];

                // Coverage over a day is duration-weighted across its sessions,
                // not the mean of their percentages: a four-hour session with
                // one gap and a five-minute session with none are not equal
                // halves of the day's answer.
                $seconds = count($chain) < 2
                    ? 0
                    : max(0, Wire::seconds(
                        (string) $chain[0]['recorded_at'],
                        (string) $chain[count($chain) - 1]['recorded_at'],
                    ));

                $spanS += $seconds;
                $goodS += $seconds * ((float) ($measured['coveragePercent'] ?? 100.0) / 100);
            }

            $coverage = $spanS <= 0 ? 100.0 : round($goodS / $spanS * 100, 2);
        }

        foreach ($rows as $row) {
            $lat = (float) $row['lat'];
            $lng = (float) $row['lng'];

            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);

            $tuples[] = [
                $lat, $lng,
                strtotime($row['recorded_at']),
                round((float) $row['accuracy_m'], 1),
                round((float) $row['speed_kmh'], 1),
                (int) $row['seq'],
                // Slot 6 — heading (02 §3.7), additive and nullable. A route
                // cached before v9 simply has no slot 6, which a consumer must
                // read as the same thing as a present null.
                ($row['heading_deg'] ?? null) === null ? null : round((float) $row['heading_deg'], 1),
            ];
        }

        $simplified = Geo::simplify($tuples, 8.0);

        db_execute(
            "INSERT INTO day_routes
                (employee_id, work_date, point_count, total_distance_km, min_lat, max_lat, min_lng, max_lng,
                 polyline, first_fix_at, last_fix_at,
                 distance_algo_version, verified_distance_km, incomplete_distance_km, gps_coverage_percent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (employee_id, work_date) DO UPDATE SET
                point_count = excluded.point_count,
                total_distance_km = excluded.total_distance_km,
                min_lat = excluded.min_lat, max_lat = excluded.max_lat,
                min_lng = excluded.min_lng, max_lng = excluded.max_lng,
                polyline = excluded.polyline,
                first_fix_at = excluded.first_fix_at,
                last_fix_at = excluded.last_fix_at,
                distance_algo_version = excluded.distance_algo_version,
                verified_distance_km = excluded.verified_distance_km,
                incomplete_distance_km = excluded.incomplete_distance_km,
                gps_coverage_percent = excluded.gps_coverage_percent",
            [
                $employeeId, $workDate, count($simplified), round($distance, 4),
                $minLat, $maxLat, $minLng, $maxLng,
                json_encode($simplified),
                Wire::ts($rows[0]['recorded_at']),
                Wire::ts($rows[count($rows) - 1]['recorded_at']),
                $algo,
                $algo < 2 ? null : round($distance, 4),
                $incomplete === null ? null : round($incomplete, 4),
                $coverage,
            ],
        );

        // The matched geometry describes the trace that just changed, so it is
        // now a stale answer. Dropped, not rebuilt: ingest is a write path and
        // the matcher is a network hop, so re-matching happens on the next read.
        MapMatch::invalidate($employeeId, $workDate);
    }

    // --------------------------------------------------------------- timeline

    /**
     * Rebuilds the day's timeline from sessions + stops + visits + reports so
     * the client never joins them. `tracking_issue` rows — the Offline alert
     * and the Back online row that closes it — are written by the health
     * endpoint as they happen and are preserved across a rebuild, because
     * nothing else in the schema records that the device went dark.
     */
    public static function rebuildActivity(string $employeeId, string $workDate): void
    {
        db_execute(
            "DELETE FROM activity_events
              WHERE employee_id = ? AND work_date = ? AND type <> 'tracking_issue'",
            [$employeeId, $workDate],
        );

        $sessions = db_fetch_all(
            "SELECT * FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
            [$employeeId, $workDate],
        );

        if (!$sessions) {
            return;
        }

        $events = [];

        $events[] = self::event($employeeId, $workDate, 'day_started', $sessions[0]['started_at'], 'Day started');

        foreach ($sessions as $session) {
            $events[] = self::event(
                $employeeId, $workDate, 'session_started', $session['started_at'],
                'Session ' . $session['seq'] . ' started',
            );

            if ($session['ended_at'] !== null) {
                $events[] = self::event(
                    $employeeId, $workDate, 'session_ended', $session['ended_at'],
                    'Session ' . $session['seq'] . ' ended',
                    subtitle: sprintf('%.1f km tracked', (float) $session['distance_km']),
                    duration: Wire::seconds($session['started_at'], $session['ended_at']),
                );
            }
        }

        $stops = db_fetch_all(
            "SELECT s.*, c.name AS company_name, b.name AS branch_name, s.visit_id
               FROM stop_records s
               LEFT JOIN company_visits v ON v.id = s.visit_id
               LEFT JOIN companies c ON c.id = v.company_id
               LEFT JOIN branches  b ON b.id = v.branch_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY s.arrival",
            [$employeeId, $workDate],
        );

        $previousDeparture = null;

        foreach ($stops as $stop) {
            $label = $stop['company_name'] ?? 'Unassigned stop';

            if ($previousDeparture !== null) {
                $events[] = self::event(
                    $employeeId, $workDate, 'travelling', $previousDeparture, 'Travelling',
                    endAt: $stop['arrival'],
                    duration: Wire::seconds($previousDeparture, $stop['arrival']),
                );
            }

            $events[] = self::event(
                $employeeId, $workDate, 'arrived', $stop['arrival'], 'Arrived at ' . $label,
                company: $stop['company_name'], branch: $stop['branch_name'], visitId: $stop['visit_id'],
            );

            $duration = Wire::seconds($stop['arrival'], $stop['departure'] ?? Wire::now());

            $events[] = self::event(
                $employeeId, $workDate, 'stayed', $stop['arrival'], 'Stayed at ' . $label,
                endAt: $stop['departure'], duration: $duration,
                company: $stop['company_name'], branch: $stop['branch_name'], visitId: $stop['visit_id'],
            );

            if ($stop['departure'] !== null) {
                $events[] = self::event(
                    $employeeId, $workDate, 'left', $stop['departure'], 'Left ' . $label,
                    company: $stop['company_name'], branch: $stop['branch_name'], visitId: $stop['visit_id'],
                );
                $previousDeparture = $stop['departure'];
            }
        }

        $reports = db_fetch_all(
            "SELECT id, title, company_name, branch_name, visit_id, submitted_at
               FROM visit_reports WHERE employee_id = ? AND work_date = ? ORDER BY submitted_at",
            [$employeeId, $workDate],
        );

        foreach ($reports as $report) {
            $events[] = self::event(
                $employeeId, $workDate, 'report_submitted', $report['submitted_at'],
                'Report submitted', subtitle: $report['title'],
                company: $report['company_name'], branch: $report['branch_name'],
                reportId: $report['id'], visitId: $report['visit_id'],
            );
        }

        $lastSession = $sessions[count($sessions) - 1];
        if ($lastSession['ended_at'] !== null) {
            $events[] = self::event($employeeId, $workDate, 'day_ended', $lastSession['ended_at'], 'Day ended');
        }

        foreach ($events as $event) {
            db_execute(
                "INSERT INTO activity_events
                    (id, employee_id, work_date, type, occurred_at, end_at, title, subtitle,
                     company_name, branch_name, duration_seconds, report_id, visit_id, is_alert)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array_values($event),
            );
        }
    }

    private static function event(
        string $employeeId,
        string $workDate,
        string $type,
        ?string $occurredAt,
        string $title,
        ?string $endAt = null,
        ?string $subtitle = null,
        ?string $company = null,
        ?string $branch = null,
        ?int $duration = null,
        ?string $reportId = null,
        ?string $visitId = null,
        bool $isAlert = false,
    ): array {
        return [
            'id'          => Uuid::v4(),
            'employee_id' => $employeeId,
            'work_date'   => $workDate,
            'type'        => $type,
            'occurred_at' => Wire::ts($occurredAt) ?? Wire::now(),
            'end_at'      => Wire::ts($endAt),
            'title'       => $title,
            'subtitle'    => $subtitle,
            'company'     => $company,
            'branch'      => $branch,
            'duration'    => $duration,
            'report_id'   => $reportId,
            'visit_id'    => $visitId,
            'is_alert'    => $isAlert ? 1 : 0,
        ];
    }

    /** Something went wrong and the employee should see it in red. */
    public static function logAlert(string $employeeId, string $workDate, string $title, string $subtitle): void
    {
        self::logHealthRow($employeeId, $workDate, $title, $subtitle, true);
    }

    /**
     * The row that CLOSES an alert — tracking recovered.
     *
     * Same row family as [logAlert] (same type, so the rebuild preserves it
     * too) but not an alert, because "you are back online" is good news and a
     * red row for it would be noise. `is_alert` is the only thing telling the
     * two apart, which is what the timeline reads to pick its icon and colour.
     */
    public static function logRecovery(string $employeeId, string $workDate, string $title, string $subtitle): void
    {
        self::logHealthRow($employeeId, $workDate, $title, $subtitle, false);
    }

    /**
     * The one write behind both. Deliberately `tracking_issue` for either
     * sense: it is the only type `rebuildActivity` steps around, and these
     * rows cannot be rebuilt from sessions and stops because nothing else
     * records them. A new enum value would need a migration and a client that
     * understands it, and would buy nothing `is_alert` does not already say.
     */
    private static function logHealthRow(
        string $employeeId,
        string $workDate,
        string $title,
        string $subtitle,
        bool $isAlert,
    ): void {
        $event = self::event($employeeId, $workDate, 'tracking_issue', Wire::now(), $title, subtitle: $subtitle, isAlert: $isAlert);

        db_execute(
            "INSERT INTO activity_events
                (id, employee_id, work_date, type, occurred_at, end_at, title, subtitle,
                 company_name, branch_name, duration_seconds, report_id, visit_id, is_alert)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array_values($event),
        );
    }

    // ------------------------------------------------------------- day lock

    /**
     * The day's lock, and the declaration behind it.
     *
     * `row` is the latest closeout whether or not it is still the active lock,
     * because the admin's declared-versus-tracked card outlives the lock.
     * `state` is `open` unless that latest row has never been reopened.
     *
     * Lives here rather than in DaysController because the tracking write path
     * has to honour the same lock — one a client alone respects is not a lock.
     */
    public static function dayLock(string $employeeId, string $workDate): array
    {
        $row = db_fetch_one(
            "SELECT * FROM day_closeouts
              WHERE employee_id = ? AND work_date = ?
              ORDER BY submitted_at DESC LIMIT 1",
            [$employeeId, $workDate],
        );

        if (!$row || $row['reopened_at'] !== null) {
            return ['state' => 'open', 'row' => $row ?: null];
        }

        return ['state' => Wire::enum($row['state']), 'row' => $row];
    }

    // ----------------------------------------------------------- live status

    /** Health values that mean the device cannot produce a fix at all. */
    private const BLIND_HEALTH = [
        'service_disabled', 'permission_denied', 'permission_denied_forever', 'background_denied',
    ];

    /** How many fixes back the dead band may walk looking for a decisive one. */
    private const MOVEMENT_LOOKBACK = 20;

    /**
     * WorkStatus, MovementStatus, LocationHealth and `activity` for one
     * employee right now.
     *
     * Offline is measured on upload recency (received_at); Location Unavailable
     * on valid-fix recency (recorded_at). They are different failures and the
     * dashboard needs to tell them apart.
     *
     * `activity` (02 §3.14) is derived here and nowhere else: one enum the map
     * maps 1:1 to an icon, sitting beside the three existing ones rather than
     * replacing any of them. Read-time only — no column, nothing written.
     */
    public static function liveStatus(string $employeeId, string $workDate): array
    {
        $config = Ctx::config();

        $health = db_fetch_one("SELECT * FROM device_health WHERE employee_id = ?", [$employeeId]);
        $locationHealth = $health['location_health'] ?? 'ok';

        $sessions = db_fetch_all(
            "SELECT id, started_at, ended_at FROM work_sessions WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $workDate],
        );

        $open = null;
        foreach ($sessions as $session) {
            if ($session['ended_at'] === null) {
                $open = $session;
                break;
            }
        }

        // Newest first. This is the same query and the same index as the old
        // single-row read, widened from LIMIT 1 to MOVEMENT_LOOKBACK because
        // the dead band needs the *previous* movement state and nothing stores
        // one — see movement() for why walking the chain is the read-time
        // answer rather than a stored column.
        $recent = db_fetch_all(
            "SELECT f.* FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY f.recorded_at DESC LIMIT " . self::MOVEMENT_LOOKBACK,
            [$employeeId, $workDate],
        );

        $lastFix = $recent[0] ?? null;
        $movement = $lastFix === null ? 'unknown' : self::movement($recent, $config);

        $activity = self::activity($employeeId, $workDate, $config, $sessions, $open, $locationHealth, $lastFix, $movement);

        if ($open === null) {
            $status = $sessions ? 'ended' : 'not_started';

            return [
                'status'         => Wire::enum($status),
                'movement'       => Wire::enum($sessions ? $movement : 'unknown'),
                'locationHealth' => Wire::enum($locationHealth),
                'activity'       => Wire::enum($activity),
                'lastFix'        => $lastFix,
                'activeSince'    => null,
            ];
        }

        $now = Wire::now();
        $status = 'working';

        if (in_array($locationHealth, self::BLIND_HEALTH, true)) {
            $status = 'location_unavailable';
        } elseif ($lastFix === null) {
            $status = Wire::seconds($open['started_at'], $now) >= (int) $config['location_unavailable_threshold_minutes'] * 60
                ? 'location_unavailable'
                : 'working';
        } elseif (Wire::seconds($lastFix['received_at'], $now) >= (int) $config['offline_threshold_minutes'] * 60) {
            $status = 'offline';
        } elseif (Wire::seconds($lastFix['recorded_at'], $now) >= (int) $config['location_unavailable_threshold_minutes'] * 60) {
            $status = 'location_unavailable';
        } else {
            $status = $movement === 'moving' ? 'working' : 'idle';
        }

        return [
            'status'         => Wire::enum($status),
            'movement'       => Wire::enum($movement),
            'locationHealth' => Wire::enum($locationHealth),
            'activity'       => Wire::enum($activity),
            'lastFix'        => $lastFix,
            'activeSince'    => Wire::ts($open['started_at']),
        ];
    }

    /**
     * Movement under the §3.12 dead band, derived read-time from the fix chain.
     *
     *   speed >= movementExitSpeedKmh      (3) -> moving
     *   speed <  movementSpeedThresholdKmh (2) -> stationary
     *   in between                             -> hold the previous state
     *
     * The dead band is defined "over the previous state", but liveStatus is
     * computed fresh on every request and **nothing stores a movement state** —
     * deliberately: a stored column would be a second source of truth and would
     * break the read-time semantics board rule 2 and `03` §4 both insist on.
     *
     * So the previous state is *re-derived* instead of remembered: walk back
     * through the recent chain (newest first) until a fix is decisive — at or
     * above the exit threshold, or below the entry threshold — and take that as
     * the state being held. A fix inside the band carries no information about
     * which state it is in, which is exactly why it is skipped. When nothing in
     * the window is decisive the documented cold-start default applies:
     * `stationary`. Deterministic, bounded (MOVEMENT_LOOKBACK fixes), identical
     * for every caller, and it rewrites nothing.
     *
     * @param array<int, array> $recent fixes for the day, newest first
     */
    private static function movement(array $recent, array $config): string
    {
        $enter = (float) $config['movement_speed_threshold_kmh'];

        // max() so a pre-v9 row (or a hand-edited one) that lacks the upper
        // edge degrades to today's single-threshold behaviour instead of
        // inverting the band and making the hold case unreachable.
        $exit = max($enter, (float) ($config['movement_exit_speed_kmh'] ?? $enter));

        foreach ($recent as $fix) {
            $speed = (float) $fix['speed_kmh'];

            if ($speed >= $exit) {
                return 'moving';
            }

            if ($speed < $enter) {
                return 'stationary';
            }
        }

        return 'stationary';
    }

    /**
     * `activity` — the one derived enum the live map maps 1:1 to an icon.
     *
     * 02 §3.14's precedence table, in its order, which is the specification and
     * not a hint. First match wins:
     *
     *   1  no session today                                  -> not_started
     *   2  open + blind locationHealth                        -> location_unavailable
     *   3  open + last received_at older than offline         -> offline
     *   4  open + last recorded_at older than unavailable     -> location_unavailable
     *   5  open + an open stop_records row                    -> stopped
     *   6  open + moving under the dead band                  -> moving
     *   7  open + stationary, no open stop                    -> idle
     *   8  no open session, sessions exist, dayState open     -> paused
     *   8b no open session, sessions exist, dayState closed   -> ended
     *
     * Rule 5 reads the existing `open_stops` partial index, so it inherits stop
     * hysteresis for free — a traffic light never opens a stop row, a ten-minute
     * halt does. No parallel dwell tracking, no new timer, no new state.
     *
     * The two lookups it needs beyond liveStatus's own reads are both lazy and
     * both indexed: the open stop only when rules 1–4 did not match, and
     * dayLock only when there is no open session.
     */
    private static function activity(
        string $employeeId,
        string $workDate,
        array $config,
        array $sessions,
        ?array $open,
        string $locationHealth,
        ?array $lastFix,
        string $movement,
    ): string {
        if (!$sessions) {
            return 'not_started';                                           // 1
        }

        if ($open === null) {
            // 8 / 8b. The day is paused between sessions and ended once it has
            // been closed out; a reopened day reads paused again. dayState
            // already draws that line, so activity must not invent a third.
            return Engine::dayLock($employeeId, $workDate)['state'] === 'open' ? 'paused' : 'ended';
        }

        if (in_array($locationHealth, self::BLIND_HEALTH, true)) {
            return 'location_unavailable';                                  // 2
        }

        $now = Wire::now();

        if ($lastFix === null) {
            // Not a row in §3.14's table: an open session that has never
            // produced a fix. Mirrors the `status` branch directly above for
            // the same case rather than inventing a different reading — once
            // the session has been open longer than the unavailable threshold
            // with nothing recorded, the GPS is the thing that is missing.
            return Wire::seconds($open['started_at'], $now) >= (int) $config['location_unavailable_threshold_minutes'] * 60
                ? 'location_unavailable'
                : 'idle';
        }

        if (Wire::seconds($lastFix['received_at'], $now) >= (int) $config['offline_threshold_minutes'] * 60) {
            return 'offline';                                               // 3
        }

        if (Wire::seconds($lastFix['recorded_at'], $now) >= (int) $config['location_unavailable_threshold_minutes'] * 60) {
            return 'location_unavailable';                                  // 4
        }

        $openStop = db_fetch_one(
            "SELECT 1 FROM stop_records
              WHERE employee_id = ? AND work_date = ? AND departure IS NULL
              LIMIT 1",
            [$employeeId, $workDate],
        );

        if ($openStop) {
            return 'stopped';                                               // 5
        }

        return $movement === 'moving' ? 'moving' : 'idle';                  // 6 / 7
    }

    // ------------------------------------------------------------ fan-out

    /**
     * Creating or updating a product notifies the field team. That is the point
     * of the screen, so it is a server-side side effect, not a second call.
     */
    public static function notifyTeam(string $kind, string $title, string $message, array $links = []): void
    {
        $id = Uuid::v4();

        db_execute(
            "INSERT INTO notifications (id, org_id, kind, title, message, product_id, report_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$id, Ctx::orgId(), $kind, $title, $message, $links['productId'] ?? null, $links['reportId'] ?? null, Wire::now()],
        );

        $recipients = $links['userIds'] ?? Ctx::teamIds();

        foreach ($recipients as $userId) {
            db_execute(
                "INSERT OR IGNORE INTO notification_recipients (notification_id, user_id) VALUES (?, ?)",
                [$id, $userId],
            );
        }
    }
}
