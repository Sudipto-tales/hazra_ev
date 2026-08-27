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
    /** Worked seconds at or above this count as a full day; below it, partial. */
    private const FULL_DAY_SECONDS = 4 * 3600;

    /** How close a stop must be to a branch before it is promoted to a visit. */
    private const VISIT_MATCH_METRES = 150.0;

    // ------------------------------------------------------------------ ingest

    /**
     * Accepts a batch of fixes for one session.
     *
     * The device filters on minAccuracyMetres before upload; the server
     * re-checks and also applies maxJumpKmh. Client-side filtering is an
     * optimisation, never the guarantee.
     *
     * @return array{accepted: string[], rejected: array<int, array{id: string, reason: string}>}
     */
    public static function ingest(array $session, array $fixes): array
    {
        $config      = Ctx::config();
        $minAccuracy = (float) $config['min_accuracy_metres'];
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
            $recordedAt = Wire::ts($fix['recordedAt'] ?? null) ?? $now;

            if ($accuracy > $minAccuracy) {
                $rejected[] = ['id' => $clientId, 'reason' => 'ACCURACY'];
                continue;
            }

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
                        (session_id, employee_id, client_id, recorded_at, received_at, lat, lng, accuracy_m, speed_kmh)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$session['id'], $session['employee_id'], $clientId, $recordedAt, $now, $lat, $lng, $accuracy, $speed],
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
            self::refreshSession($session['id']);
            self::recompute($session['employee_id'], $session['work_date']);
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /** Recomputes a session's distance and point count from its fix chain. */
    public static function refreshSession(string $sessionId): void
    {
        $fixes = db_fetch_all(
            "SELECT lat, lng FROM location_fixes WHERE session_id = ? ORDER BY recorded_at, id",
            [$sessionId],
        );

        $distance = 0.0;
        for ($i = 1, $n = count($fixes); $i < $n; $i++) {
            $distance += Geo::distanceKm(
                (float) $fixes[$i - 1]['lat'], (float) $fixes[$i - 1]['lng'],
                (float) $fixes[$i]['lat'],     (float) $fixes[$i]['lng'],
            );
        }

        db_execute(
            "UPDATE work_sessions SET distance_km = ?, point_count = ? WHERE id = ?",
            [round($distance, 4), count($fixes), $sessionId],
        );
    }

    // -------------------------------------------------------------- derivation

    /**
     * Re-derives everything downstream of the fixes for one employee-day, then
     * rebuilds the rollup, the route and the timeline.
     */
    public static function recompute(string $employeeId, string $workDate): void
    {
        self::deriveStops($employeeId, $workDate);
        self::promoteVisits($employeeId, $workDate);
        self::rebuildActivity($employeeId, $workDate);
        self::buildDayRoute($employeeId, $workDate);
        self::rollupDay($employeeId, $workDate);
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
     * The one table Attendance, DaySummary, the attendance matrix and both
     * statistics endpoints all read.
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

        foreach ($sessions as $session) {
            $worked   += Wire::seconds($session['started_at'], $session['ended_at'] ?? $now);
            $distance += (float) $session['distance_km'];
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

        db_execute(
            "INSERT INTO attendance_days
                (employee_id, work_date, status, joining_time, end_time, session_count, worked_seconds,
                 distance_km, companies_visited, reports_submitted, stop_seconds, longest_stop_seconds, computed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                computed_at = excluded.computed_at",
            [
                $employeeId, $workDate, $status, $joining, $anyOpen ? null : $end,
                count($sessions), $worked, round($distance, 4), $companies, $reports,
                $stopSeconds, $longestStop, $now,
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
            "SELECT f.lat, f.lng, f.recorded_at, f.accuracy_m, f.speed_kmh, s.seq
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
        $distance = 0.0;

        foreach ($rows as $i => $row) {
            $lat = (float) $row['lat'];
            $lng = (float) $row['lng'];

            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);

            if ($i > 0) {
                $distance += Geo::distanceKm((float) $rows[$i - 1]['lat'], (float) $rows[$i - 1]['lng'], $lat, $lng);
            }

            $tuples[] = [
                $lat, $lng,
                strtotime($row['recorded_at']),
                round((float) $row['accuracy_m'], 1),
                round((float) $row['speed_kmh'], 1),
                (int) $row['seq'],
            ];
        }

        $simplified = Geo::simplify($tuples, 8.0);

        db_execute(
            "INSERT INTO day_routes
                (employee_id, work_date, point_count, total_distance_km, min_lat, max_lat, min_lng, max_lng,
                 polyline, first_fix_at, last_fix_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (employee_id, work_date) DO UPDATE SET
                point_count = excluded.point_count,
                total_distance_km = excluded.total_distance_km,
                min_lat = excluded.min_lat, max_lat = excluded.max_lat,
                min_lng = excluded.min_lng, max_lng = excluded.max_lng,
                polyline = excluded.polyline,
                first_fix_at = excluded.first_fix_at,
                last_fix_at = excluded.last_fix_at",
            [
                $employeeId, $workDate, count($simplified), round($distance, 4),
                $minLat, $maxLat, $minLng, $maxLng,
                json_encode($simplified),
                Wire::ts($rows[0]['recorded_at']),
                Wire::ts($rows[count($rows) - 1]['recorded_at']),
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
     * the client never joins them. Alert rows (tracking_issue) are written by
     * the health endpoint as they happen and are preserved across a rebuild.
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

    public static function logAlert(string $employeeId, string $workDate, string $title, string $subtitle): void
    {
        $event = self::event($employeeId, $workDate, 'tracking_issue', Wire::now(), $title, subtitle: $subtitle, isAlert: true);

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

    /**
     * WorkStatus, MovementStatus and LocationHealth for one employee right now.
     *
     * Offline is measured on upload recency (received_at); Location Unavailable
     * on valid-fix recency (recorded_at). They are different failures and the
     * dashboard needs to tell them apart.
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

        $lastFix = db_fetch_one(
            "SELECT f.* FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY f.recorded_at DESC LIMIT 1",
            [$employeeId, $workDate],
        ) ?: null;

        $movement = 'unknown';
        if ($lastFix !== null) {
            $movement = (float) $lastFix['speed_kmh'] >= (float) $config['movement_speed_threshold_kmh']
                ? 'moving'
                : 'stationary';
        }

        if ($open === null) {
            $status = $sessions ? 'ended' : 'not_started';

            return [
                'status'         => Wire::enum($status),
                'movement'       => Wire::enum($sessions ? $movement : 'unknown'),
                'locationHealth' => Wire::enum($locationHealth),
                'lastFix'        => $lastFix,
                'activeSince'    => null,
            ];
        }

        $now = Wire::now();
        $status = 'working';

        if (in_array($locationHealth, ['service_disabled', 'permission_denied', 'permission_denied_forever', 'background_denied'], true)) {
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
            'lastFix'        => $lastFix,
            'activeSince'    => Wire::ts($open['started_at']),
        ];
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
