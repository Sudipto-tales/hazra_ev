<?php

/**
 * Sessions and the fix table that is 99 % of the row count.
 *
 * Port note: the PostgreSQL design partitions `location_fixes` by day with a
 * BRIN index. SQLite has neither, so this is one table with a covering
 * (employee_id, recorded_at) index — correct at development volume, and the
 * one place the production schema genuinely diverges. Retention there is a
 * DETACH PARTITION; here it would be a DELETE.
 */
class SessionLocationTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS work_sessions (
                id          TEXT PRIMARY KEY,
                employee_id TEXT NOT NULL REFERENCES users(id),
                client_id   TEXT NOT NULL,          -- device-generated, offline-safe
                work_date   TEXT NOT NULL,          -- org-local calendar day
                seq         INTEGER NOT NULL,       -- 1-based 'Session 2'
                started_at  TEXT NOT NULL,          -- from the FIRST FIX, not the tap
                ended_at    TEXT,
                start_lat   REAL NOT NULL,
                start_lng   REAL NOT NULL,
                distance_km REAL NOT NULL DEFAULT 0,
                point_count INTEGER NOT NULL DEFAULT 0,
                UNIQUE (employee_id, client_id),    -- idempotent offline replay
                UNIQUE (employee_id, work_date, seq)
            );
            CREATE INDEX IF NOT EXISTS idx_sessions_employee_date ON work_sessions (employee_id, work_date DESC);
            -- 'Who is working right now' — the dashboard's hottest query.
            CREATE INDEX IF NOT EXISTS idx_open_sessions ON work_sessions (employee_id) WHERE ended_at IS NULL;

            CREATE TABLE IF NOT EXISTS location_fixes (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id  TEXT NOT NULL REFERENCES work_sessions(id) ON DELETE CASCADE,
                employee_id TEXT NOT NULL,          -- denormalised: every admin read filters on it
                client_id   TEXT NOT NULL,
                recorded_at TEXT NOT NULL,          -- device clock
                received_at TEXT NOT NULL,          -- server clock; skew is real
                lat         REAL NOT NULL,
                lng         REAL NOT NULL,
                accuracy_m  REAL NOT NULL,
                speed_kmh   REAL NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_fixes_employee_time ON location_fixes (employee_id, recorded_at);
            CREATE INDEX IF NOT EXISTS idx_fixes_session_time ON location_fixes (session_id, recorded_at);
            -- The idempotency guarantee for POST /tracking/locations: a replayed
            -- batch conflicts and comes back as rejected DUPLICATE.
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fixes_dedupe ON location_fixes (employee_id, client_id);
        ");
    }

    public function down()
    {
        foreach (['location_fixes', 'work_sessions'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
