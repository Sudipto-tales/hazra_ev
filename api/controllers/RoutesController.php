<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * One route; a single track is the n = 1 case. Always returns RouteTrack[].
 *
 * `subject=team` returns one element per employee, empty tracks included, so
 * the map legend can still list who did not work.
 *
 * Points are a flat [lat, lng, t, acc, spd, sessionIdx] tuple array rather than
 * objects — roughly a 4x size reduction on the largest payload in the system.
 * `t` is epoch seconds. `simplify=` is metres of Douglas-Peucker tolerance:
 * send 8 for the overview and 0 when the user zooms in.
 */
final class RoutesController extends V1Controller
{
    public function index(): never
    {
        $date = $this->dateParam();
        $employeeIds = $this->targets();
        $simplify = $this->hasQuery('simplify') ? (float) $this->query('simplify') : 8.0;

        // Today is still being written, so it reads live; any other date reads
        // the drawn route that was built when the session closed.
        $live = $date >= Ctx::today();

        $tracks = [];
        foreach ($employeeIds as $employeeId) {
            $tracks[] = $this->track($employeeId, $date, $simplify, $live);
        }

        if ($live) {
            Envelope::noStore();
        } else {
            header('Cache-Control: ' . Envelope::IMMUTABLE);
        }

        Envelope::ok($tracks, ['date' => $date, 'total' => count($tracks), 'simplify' => $simplify]);
    }

    // ------------------------------------------------------------- internals

    /** `employeeIds=a,b,c` narrows a team request without a second route. */
    private function targets(): array
    {
        $subject = Ctx::subject($this->query('subject'));

        if (!$this->hasQuery('employeeIds')) {
            return $subject['ids'];
        }

        $requested = array_values(array_filter(array_map('trim', explode(',', (string) $this->query('employeeIds')))));
        $allowed = array_flip($subject['ids']);

        $ids = array_values(array_filter($requested, static fn(string $id) => isset($allowed[$id])));

        if (!$ids) {
            Envelope::invalid('employeeIds matched nothing this token may read', 'employeeIds');
        }

        return $ids;
    }

    private function track(string $employeeId, string $date, float $simplify, bool $live): array
    {
        $points = $live
            ? $this->livePoints($employeeId, $date, $simplify)
            : $this->storedPoints($employeeId, $date, $simplify);

        $sessions = db_fetch_all(
            "SELECT * FROM work_sessions WHERE employee_id = ? AND work_date = ? ORDER BY seq",
            [$employeeId, $date],
        );

        $track = [
            'employeeId'      => $employeeId,
            'date'            => $date,
            'points'          => $points['tuples'],
            'sessions'        => array_map([Present::class, 'session'], $sessions),
            'stops'           => [],
            'visits'          => [],
            'totalDistanceKm' => $points['distanceKm'],
        ];

        if ($this->wants('stops') || $this->includes() === []) {
            $track['stops'] = array_map(
                [Present::class, 'stop'],
                db_fetch_all(
                    "SELECT * FROM stop_records WHERE employee_id = ? AND work_date = ? ORDER BY arrival",
                    [$employeeId, $date],
                ),
            );
        }

        if ($this->wants('visits') || $this->includes() === []) {
            $track['visits'] = array_map(
                static fn(array $v) => Present::visit($v),
                db_fetch_all(
                    "SELECT * FROM company_visits WHERE employee_id = ? AND work_date = ? ORDER BY arrival",
                    [$employeeId, $date],
                ),
            );
        }

        return $track;
    }

    private function storedPoints(string $employeeId, string $date, float $simplify): array
    {
        $row = db_fetch_one(
            "SELECT * FROM day_routes WHERE employee_id = ? AND work_date = ?",
            [$employeeId, $date],
        );

        if (!$row) {
            return ['tuples' => [], 'distanceKm' => 0.0];
        }

        $tuples = Wire::json($row['polyline'], []);

        // The stored polyline is already simplified at ~8 m; a coarser request
        // thins it further, a finer one cannot recover points that are gone.
        if ($simplify > 8.0) {
            $tuples = Geo::simplify($tuples, $simplify);
        }

        return ['tuples' => $tuples, 'distanceKm' => Wire::float($row['total_distance_km'])];
    }

    private function livePoints(string $employeeId, string $date, float $simplify): array
    {
        $rows = db_fetch_all(
            "SELECT f.lat, f.lng, f.recorded_at, f.accuracy_m, f.speed_kmh, s.seq
               FROM location_fixes f
               JOIN work_sessions s ON s.id = f.session_id
              WHERE s.employee_id = ? AND s.work_date = ?
              ORDER BY f.recorded_at, f.id",
            [$employeeId, $date],
        );

        $tuples = [];
        $distance = 0.0;

        foreach ($rows as $i => $row) {
            if ($i > 0) {
                $distance += Geo::distanceKm(
                    (float) $rows[$i - 1]['lat'], (float) $rows[$i - 1]['lng'],
                    (float) $row['lat'], (float) $row['lng'],
                );
            }

            $tuples[] = [
                (float) $row['lat'],
                (float) $row['lng'],
                strtotime($row['recorded_at']),
                round((float) $row['accuracy_m'], 1),
                round((float) $row['speed_kmh'], 1),
                (int) $row['seq'],
            ];
        }

        return [
            'tuples'     => Geo::simplify($tuples, $simplify),
            'distanceKm' => Wire::float($distance),
        ];
    }
}
