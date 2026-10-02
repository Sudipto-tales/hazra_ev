<?php

/**
 * The tables every read screen actually hits.
 *
 * `attendance_days` is Attendance AND DaySummary AND every cell of the team
 * attendance matrix AND the input to both statistics endpoints. Nothing on a
 * read path touches location_fixes except the live map for the current day.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class RollupTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS attendance_days (
                employee_id          {uuid} NOT NULL,
                work_date            {date} NOT NULL,
                status               {str:16} NOT NULL DEFAULT 'no_data'
                                       CHECK (status IN ('present','absent','partial','holiday','weekend','no_data')),
                joining_time         {ts},              -- first valid session start
                end_time             {ts},
                session_count        {int} NOT NULL DEFAULT 0,
                worked_seconds       {int} NOT NULL DEFAULT 0,
                distance_km          {float} NOT NULL DEFAULT 0,
                companies_visited    {int} NOT NULL DEFAULT 0,   -- DISTINCT companies, not visits
                reports_submitted    {int} NOT NULL DEFAULT 0,
                stop_seconds         {int} NOT NULL DEFAULT 0,
                longest_stop_seconds {int} NOT NULL DEFAULT 0,
                computed_at          {ts} NOT NULL,
                PRIMARY KEY (employee_id, work_date),
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance_days (work_date);

            -- The drawn route, kept so the admin map reads one row instead of a
            -- thousand-row scan, and so fix retention stays an ops choice.
            CREATE TABLE IF NOT EXISTS day_routes (
                employee_id       {uuid} NOT NULL,
                work_date         {date} NOT NULL,
                point_count       {int} NOT NULL DEFAULT 0,
                total_distance_km {float} NOT NULL DEFAULT 0,
                min_lat {float}, max_lat {float}, min_lng {float}, max_lng {float},   -- box, precomputed
                polyline          {json} NOT NULL {default '[]'},   -- JSON [[lat,lng,t,acc,spd,sessionIdx], ...]
                first_fix_at      {ts},
                last_fix_at       {ts},
                PRIMARY KEY (employee_id, work_date),
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};

            -- Timeline, materialised from sessions + stops + visits + reports so
            -- the client never joins. Read by employee Home, the employee
            -- activity tab and the admin employee-day view alike.
            CREATE TABLE IF NOT EXISTS activity_events (
                id               {uuid} PRIMARY KEY,
                employee_id      {uuid} NOT NULL,
                work_date        {date} NOT NULL,
                type             {str:32} NOT NULL CHECK (type IN (
                                   'day_started','session_started','travelling','arrived','stayed',
                                   'report_submitted','left','session_ended','day_ended','tracking_issue')),
                occurred_at      {ts} NOT NULL,
                end_at           {ts},
                title            {str} NOT NULL,
                subtitle         {str},
                company_name     {str},
                branch_name      {str},
                duration_seconds {int},
                report_id        {uuid},
                visit_id         {uuid},
                is_alert         {bool} NOT NULL DEFAULT 0,
                FOREIGN KEY (employee_id) REFERENCES users(id),
                FOREIGN KEY (report_id) REFERENCES visit_reports(id) ON DELETE CASCADE,
                FOREIGN KEY (visit_id) REFERENCES company_visits(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_activity_employee_date ON activity_events (employee_id, work_date, occurred_at);

            -- Device-reported location stack health. This is what turns a
            -- TeamMember red on the dashboard while the employee's GPS is off;
            -- LocationHealth is device-authored and never invented server-side.
            CREATE TABLE IF NOT EXISTS device_health (
                employee_id     {uuid} PRIMARY KEY,
                location_health {str:32} NOT NULL DEFAULT 'ok',
                permission      {str:32},
                is_online       {bool} NOT NULL DEFAULT 1,
                reported_at     {ts} NOT NULL,
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop(['device_health', 'activity_events', 'day_routes', 'attendance_days']);
    }
}
