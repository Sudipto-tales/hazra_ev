<?php

/**
 * The tables every read screen actually hits.
 *
 * `attendance_days` is Attendance AND DaySummary AND every cell of the team
 * attendance matrix AND the input to both statistics endpoints. Nothing on a
 * read path touches location_fixes except the live map for the current day.
 */
class RollupTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS attendance_days (
                employee_id          TEXT NOT NULL REFERENCES users(id),
                work_date            TEXT NOT NULL,
                status               TEXT NOT NULL DEFAULT 'no_data'
                                       CHECK (status IN ('present','absent','partial','holiday','weekend','no_data')),
                joining_time         TEXT,              -- first valid session start
                end_time             TEXT,
                session_count        INTEGER NOT NULL DEFAULT 0,
                worked_seconds       INTEGER NOT NULL DEFAULT 0,
                distance_km          REAL    NOT NULL DEFAULT 0,
                companies_visited    INTEGER NOT NULL DEFAULT 0,   -- DISTINCT companies, not visits
                reports_submitted    INTEGER NOT NULL DEFAULT 0,
                stop_seconds         INTEGER NOT NULL DEFAULT 0,
                longest_stop_seconds INTEGER NOT NULL DEFAULT 0,
                computed_at          TEXT NOT NULL,
                PRIMARY KEY (employee_id, work_date)
            );
            CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance_days (work_date);

            -- The drawn route, kept so the admin map reads one row instead of a
            -- thousand-row scan, and so fix retention stays an ops choice.
            CREATE TABLE IF NOT EXISTS day_routes (
                employee_id       TEXT NOT NULL REFERENCES users(id),
                work_date         TEXT NOT NULL,
                point_count       INTEGER NOT NULL DEFAULT 0,
                total_distance_km REAL NOT NULL DEFAULT 0,
                min_lat REAL, max_lat REAL, min_lng REAL, max_lng REAL,   -- box, precomputed
                polyline          TEXT NOT NULL DEFAULT '[]',   -- JSON [[lat,lng,t,acc,spd,sessionIdx], ...]
                first_fix_at      TEXT,
                last_fix_at       TEXT,
                PRIMARY KEY (employee_id, work_date)
            );

            -- Timeline, materialised from sessions + stops + visits + reports so
            -- the client never joins. Read by employee Home, the employee
            -- activity tab and the admin employee-day view alike.
            CREATE TABLE IF NOT EXISTS activity_events (
                id               TEXT PRIMARY KEY,
                employee_id      TEXT NOT NULL REFERENCES users(id),
                work_date        TEXT NOT NULL,
                type             TEXT NOT NULL CHECK (type IN (
                                   'day_started','session_started','travelling','arrived','stayed',
                                   'report_submitted','left','session_ended','day_ended','tracking_issue')),
                occurred_at      TEXT NOT NULL,
                end_at           TEXT,
                title            TEXT NOT NULL,
                subtitle         TEXT,
                company_name     TEXT,
                branch_name      TEXT,
                duration_seconds INTEGER,
                report_id        TEXT REFERENCES visit_reports(id) ON DELETE CASCADE,
                visit_id         TEXT REFERENCES company_visits(id) ON DELETE CASCADE,
                is_alert         INTEGER NOT NULL DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_activity_employee_date ON activity_events (employee_id, work_date, occurred_at);

            -- Device-reported location stack health. This is what turns a
            -- TeamMember red on the dashboard while the employee's GPS is off;
            -- LocationHealth is device-authored and never invented server-side.
            CREATE TABLE IF NOT EXISTS device_health (
                employee_id     TEXT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                location_health TEXT NOT NULL DEFAULT 'ok',
                permission      TEXT,
                is_online       INTEGER NOT NULL DEFAULT 1,
                reported_at     TEXT NOT NULL
            );
        ");
    }

    public function down()
    {
        foreach (['device_health', 'activity_events', 'day_routes', 'attendance_days'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
