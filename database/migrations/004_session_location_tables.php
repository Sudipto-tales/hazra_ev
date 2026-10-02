<?php

/**
 * Sessions and the fix table that is 99 % of the row count.
 *
 * Port note: the PostgreSQL design partitions `location_fixes` by day with a
 * BRIN index. Neither SQLite nor MySQL gives that for free here, so this is one
 * table with a covering (employee_id, recorded_at) index — correct at
 * development volume, and the one place the production schema genuinely
 * diverges. Retention there is a DETACH PARTITION; here it would be a DELETE.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class SessionLocationTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS work_sessions (
                id          {uuid} PRIMARY KEY,
                employee_id {uuid} NOT NULL,
                client_id   {str:128} NOT NULL,     -- device-generated, offline-safe
                work_date   {date} NOT NULL,        -- org-local calendar day
                seq         {int} NOT NULL,         -- 1-based 'Session 2'
                started_at  {ts} NOT NULL,          -- from the FIRST FIX, not the tap
                ended_at    {ts},
                start_lat   {float} NOT NULL,
                start_lng   {float} NOT NULL,
                distance_km {float} NOT NULL DEFAULT 0,
                point_count {int} NOT NULL DEFAULT 0,
                UNIQUE (employee_id, client_id),    -- idempotent offline replay
                UNIQUE (employee_id, work_date, seq),
                FOREIGN KEY (employee_id) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_sessions_employee_date ON work_sessions (employee_id, work_date DESC);
            -- 'Who is working right now' — the dashboard's hottest query.
            CREATE INDEX IF NOT EXISTS idx_open_sessions ON work_sessions (employee_id) WHERE ended_at IS NULL;

            CREATE TABLE IF NOT EXISTS location_fixes (
                id          {autoid},
                session_id  {uuid} NOT NULL,
                employee_id {uuid} NOT NULL,        -- denormalised: every admin read filters on it
                client_id   {str:128} NOT NULL,
                recorded_at {ts} NOT NULL,          -- device clock
                received_at {ts} NOT NULL,          -- server clock; skew is real
                lat         {float} NOT NULL,
                lng         {float} NOT NULL,
                accuracy_m  {float} NOT NULL,
                speed_kmh   {float} NOT NULL,
                FOREIGN KEY (session_id) REFERENCES work_sessions(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_fixes_employee_time ON location_fixes (employee_id, recorded_at);
            CREATE INDEX IF NOT EXISTS idx_fixes_session_time ON location_fixes (session_id, recorded_at);
            -- The idempotency guarantee for POST /tracking/locations: a replayed
            -- batch conflicts and comes back as rejected DUPLICATE.
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fixes_dedupe ON location_fixes (employee_id, client_id);
        ");
    }

    public function down()
    {
        $this->drop(['location_fixes', 'work_sessions']);
    }
}
