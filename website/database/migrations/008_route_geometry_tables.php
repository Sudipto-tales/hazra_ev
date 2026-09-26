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
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class RouteGeometryTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS route_geometry (
                employee_id        {uuid} NOT NULL,
                work_date          {date} NOT NULL,
                engine             {str:32} NOT NULL,        -- 'osrm' | 'valhalla'
                profile            {str:32} NOT NULL,        -- 'motorcycle', 'motor_scooter', 'driving'
                status             {str:16} NOT NULL DEFAULT 'ok'
                                     CHECK (status IN ('ok','partial','failed','skipped')),
                -- JSON [{seq, polyline, confidence, pointCount, distanceKm, matched}, ...]
                -- polyline is encoded at precision 6; `matched` false means the
                -- entry fell back to the raw trace for that session.
                segments           {json} NOT NULL {default '[]'},
                -- Worst segment confidence, not the mean: one bad session is
                -- what an admin needs to see, and a mean hides it.
                confidence         {float} NOT NULL DEFAULT 0,
                matched_distance_km {float} NOT NULL DEFAULT 0,
                -- Invalidation key. A late offline batch changes day_routes'
                -- point_count, which is how a stale match is detected without
                -- hashing a thousand fixes.
                source_point_count {int} NOT NULL DEFAULT 0,
                matched_at         {ts} NOT NULL,
                PRIMARY KEY (employee_id, work_date),
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_route_geometry_date ON route_geometry (work_date);
        ");
    }

    public function down()
    {
        $this->drop(['route_geometry']);
    }
}
