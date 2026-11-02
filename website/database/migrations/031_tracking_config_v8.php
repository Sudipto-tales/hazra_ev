<?php

/**
 * TrackingConfig version 8 — two server-tunable drain knobs.
 *
 * `02-API-PLAN.md` §3.12 grows TrackingConfig by two scalar fields so the
 * drain cadence and the GPS distance filter can change without an app release:
 *
 *   sync_interval_seconds   how often the device drains its queued fixes to
 *                           POST /tracking/locations (was a hard-coded 5 min)
 *   distance_filter_metres  the OS distance filter — suppress fixes until the
 *                           device has moved this far
 *
 * `tracking_configs` and `users` both predate their later columns, so like 010
 * this is an ALTER, not a rewrite of 002. Migration::addColumn introspects and
 * returns early when a column already exists, so the file is re-runnable the
 * same way CREATE TABLE IF NOT EXISTS is.
 *
 * The cross-field rule `sync_interval_seconds <= offline_threshold_minutes * 30`
 * (i.e. ≤ offlineThreshold × 60 / 2 — the device always posts at least twice
 * inside the offline window) is enforced two ways, mirroring the existing
 * `long_stop >= stop` discipline: the API rejects a violating PUT with a 422
 * (ConfigController::update), and the database carries it as a CHECK backstop.
 *
 * The CHECK cannot be expressed identically on both drivers through one ALTER:
 *   - SQLite has no ADD CONSTRAINT, but an ADD COLUMN may carry an inline CHECK
 *     that references other columns, so the rule rides on sync_interval_seconds.
 *   - MySQL/MariaDB column-level CHECK may reference only its own column, so the
 *     cross-column rule is added as a table constraint after the columns.
 * Both land the same guarantee; the SQL differs per the port note in 001/§4.
 *
 * Finally the stored `version` is floored to 8 (or bumped if already higher) so
 * a device still holding a v7 ETag sees it change and re-pulls the new shape.
 */
class TrackingConfigV8 extends Migration
{
    private const WINDOW_CHECK = 'sync_interval_seconds <= offline_threshold_minutes * 30';

    public function up()
    {
        if (Dialect::isMysql()) {
            $this->addColumn('tracking_configs', 'sync_interval_seconds', '{int} NOT NULL DEFAULT 300');
            $this->addColumn('tracking_configs', 'distance_filter_metres', '{int} NOT NULL DEFAULT 20');
            $this->addCheck('tracking_configs', 'chk_sync_interval_window', self::WINDOW_CHECK);
        } else {
            $this->addColumn(
                'tracking_configs',
                'sync_interval_seconds',
                '{int} NOT NULL DEFAULT 300 CHECK (' . self::WINDOW_CHECK . ')',
            );
            $this->addColumn('tracking_configs', 'distance_filter_metres', '{int} NOT NULL DEFAULT 20');
        }

        // History is append-only, so the mirror columns are nullable — rows
        // written before v8 legitimately have no value for either knob. The
        // JSON payload captures them too; these columns keep the two tunable
        // knobs queryable without parsing the snapshot.
        $this->addColumn('tracking_config_history', 'sync_interval_seconds', '{int}');
        $this->addColumn('tracking_config_history', 'distance_filter_metres', '{int}');

        // Floor the stored version to 8 so the ETag changes and a v7 device
        // re-pulls; if an org already edited config past 8, bump it instead so
        // the ETag still moves rather than going backwards.
        $this->pdo->exec(
            'UPDATE tracking_configs
                SET version = CASE WHEN version < 8 THEN 8 ELSE version + 1 END'
        );
    }

    /**
     * Adds a table-level CHECK once, on MySQL/MariaDB only. The migration
     * ledger already prevents a second run; the try/catch is the belt for a
     * hand-run so a pre-existing constraint name is a no-op, not a failure.
     */
    private function addCheck(string $table, string $name, string $expression): void
    {
        try {
            $this->pdo->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        } catch (PDOException) {
            // Constraint already present — nothing to do.
        }
    }

    public function down()
    {
        // Deliberately empty. Dropping a column rewrites the whole table on both
        // drivers, and SQLite cannot drop the inline CHECK at all. Rolling back
        // leaves two unused columns and a harmless constraint, which is cheaper
        // and safer than a table rebuild — the same call 010 makes.
    }
}
