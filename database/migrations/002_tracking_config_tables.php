<?php

/**
 * The org-wide tracking rulebook, its version history, and the holiday
 * calendar the nightly rollup reads AttendanceStatus.holiday from.
 *
 * Types are Dialect tokens; see config/dialect.php and the port note in 001.
 */
class TrackingConfigTables extends Migration
{
    public function up()
    {
        $this->exec("
            CREATE TABLE IF NOT EXISTS tracking_configs (
                org_id                                 {uuid} PRIMARY KEY,
                version                                {int} NOT NULL DEFAULT 1,
                location_interval_seconds              {int} NOT NULL DEFAULT 30,
                min_accuracy_metres                    {float} NOT NULL DEFAULT 50,
                stop_radius_metres                     {float} NOT NULL DEFAULT 75,
                stop_threshold_minutes                 {int} NOT NULL DEFAULT 10,
                long_stop_threshold_minutes            {int} NOT NULL DEFAULT 45,
                movement_speed_threshold_kmh           {float} NOT NULL DEFAULT 2,
                max_jump_kmh                           {float} NOT NULL DEFAULT 180,
                offline_threshold_minutes              {int} NOT NULL DEFAULT 10,
                location_unavailable_threshold_minutes {int} NOT NULL DEFAULT 15,
                sync_batch_size                        {int} NOT NULL DEFAULT 50,
                updated_at                             {ts} NOT NULL,
                updated_by                             {uuid},
                CHECK (location_interval_seconds BETWEEN 5 AND 600),
                CHECK (long_stop_threshold_minutes >= stop_threshold_minutes),
                FOREIGN KEY (org_id) REFERENCES organizations(id),
                FOREIGN KEY (updated_by) REFERENCES users(id)
            ) {opts};

            -- 'What was the rule on the 3rd' needs an answer (schema doc §4).
            CREATE TABLE IF NOT EXISTS tracking_config_history (
                id         {uuid} PRIMARY KEY,
                org_id     {uuid} NOT NULL,
                version    {int} NOT NULL,
                payload    {json} NOT NULL,        -- JSON snapshot of the row as written
                changed_at {ts} NOT NULL,
                changed_by {uuid},
                FOREIGN KEY (org_id) REFERENCES organizations(id),
                FOREIGN KEY (changed_by) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_config_history ON tracking_config_history (org_id, version DESC);

            -- Closes the 'holiday calendar has no source' gap in data doc §13.
            -- AttendanceStatus.holiday is read from here by the nightly rollup.
            CREATE TABLE IF NOT EXISTS holidays (
                org_id {uuid} NOT NULL,
                date   {date} NOT NULL,            -- yyyy-mm-dd
                name   {str} NOT NULL,
                PRIMARY KEY (org_id, date),
                FOREIGN KEY (org_id) REFERENCES organizations(id)
            ) {opts};
        ");
    }

    public function down()
    {
        $this->drop(['holidays', 'tracking_config_history', 'tracking_configs']);
    }
}
