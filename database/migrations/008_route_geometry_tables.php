<?php

/**
 * The road-matched route, cached per employee-day.
 *
 * `day_routes.polyline` is the raw GPS trace: straight lines between fixes
 * fifteen seconds apart, which at 40 km/h is a ~165 m chord that cuts corners
 * and crosses blocks. This table holds what a map-matcher made of that trace —
 * the road chain that best explains the fixes — so the admin map draws streets
 * instead of chords.
 *
 * One row per employee-day, not per session, because the read path is the day.
 * Segments stay separated inside `segments` (one entry per session seq): a gap
 * between two sessions is a lunch break, and matching across it would draw a
 * road nobody rode.
 *
 * The cache is derived, never authoritative. Dropping the whole table costs a
 * re-match, nothing else — which is also the recovery when the profile or the
 * matching engine changes.
 */
class RouteGeometryTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS route_geometry (
                employee_id        TEXT NOT NULL REFERENCES users(id),
                work_date          TEXT NOT NULL,
                engine             TEXT NOT NULL,              -- 'osrm' | 'valhalla'
                profile            TEXT NOT NULL,              -- 'motorcycle', 'motor_scooter', 'driving'
                status             TEXT NOT NULL DEFAULT 'ok'
                                     CHECK (status IN ('ok','partial','failed','skipped')),
                -- JSON [{seq, polyline, confidence, pointCount, distanceKm, matched}, ...]
                -- polyline is encoded at precision 6; `matched` false means the
                -- entry fell back to the raw trace for that session.
                segments           TEXT NOT NULL DEFAULT '[]',
                -- Worst segment confidence, not the mean: one bad session is
                -- what an admin needs to see, and a mean hides it.
                confidence         REAL NOT NULL DEFAULT 0,
                matched_distance_km REAL NOT NULL DEFAULT 0,
                -- Invalidation key. A late offline batch changes day_routes'
                -- point_count, which is how a stale match is detected without
                -- hashing a thousand fixes.
                source_point_count INTEGER NOT NULL DEFAULT 0,
                matched_at         TEXT NOT NULL,
                PRIMARY KEY (employee_id, work_date)
            );
            CREATE INDEX IF NOT EXISTS idx_route_geometry_date ON route_geometry (work_date);
        ");
    }

    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS route_geometry");
    }
}
