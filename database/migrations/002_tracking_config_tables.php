<?php

/**
 * Admin-owned tracking configuration — one row per organisation, never per
 * employee and never per device (data doc §10.1).
 *
 * `version` bumps on every write and backs the ETag, so a device that already
 * holds the current config gets a 304.
 */
class TrackingConfigTables
{
    protected $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tracking_configs (
                org_id                                 TEXT PRIMARY KEY REFERENCES organizations(id),
                version                                INTEGER NOT NULL DEFAULT 1,
                location_interval_seconds              INTEGER NOT NULL DEFAULT 30,
                min_accuracy_metres                    REAL    NOT NULL DEFAULT 50,
                stop_radius_metres                     REAL    NOT NULL DEFAULT 75,
                stop_threshold_minutes                 INTEGER NOT NULL DEFAULT 10,
                long_stop_threshold_minutes            INTEGER NOT NULL DEFAULT 45,
                movement_speed_threshold_kmh           REAL    NOT NULL DEFAULT 2,
                max_jump_kmh                           REAL    NOT NULL DEFAULT 180,
                offline_threshold_minutes              INTEGER NOT NULL DEFAULT 10,
                location_unavailable_threshold_minutes INTEGER NOT NULL DEFAULT 15,
                sync_batch_size                        INTEGER NOT NULL DEFAULT 50,
                updated_at                             TEXT NOT NULL,
                updated_by                             TEXT REFERENCES users(id),
                CHECK (location_interval_seconds BETWEEN 5 AND 600),
                CHECK (long_stop_threshold_minutes >= stop_threshold_minutes)
            );

            -- 'What was the rule on the 3rd' needs an answer (schema doc §4).
            CREATE TABLE IF NOT EXISTS tracking_config_history (
                id         TEXT PRIMARY KEY,
                org_id     TEXT NOT NULL REFERENCES organizations(id),
                version    INTEGER NOT NULL,
                payload    TEXT NOT NULL,        -- JSON snapshot of the row as written
                changed_at TEXT NOT NULL,
                changed_by TEXT REFERENCES users(id)
            );
            CREATE INDEX IF NOT EXISTS idx_config_history ON tracking_config_history (org_id, version DESC);

            -- Closes the 'holiday calendar has no source' gap in data doc §13.
            -- AttendanceStatus.holiday is read from here by the nightly rollup.
            CREATE TABLE IF NOT EXISTS holidays (
                org_id TEXT NOT NULL REFERENCES organizations(id),
                date   TEXT NOT NULL,            -- yyyy-mm-dd
                name   TEXT NOT NULL,
                PRIMARY KEY (org_id, date)
            );
        ");
    }

    public function down()
    {
        foreach (['holidays', 'tracking_config_history', 'tracking_configs'] as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
