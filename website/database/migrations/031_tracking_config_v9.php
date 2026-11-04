<?php

/**
 * TrackingConfig version 9 — the live-tracking contract, in one migration.
 *
 * `02-API-PLAN.md` §3.11/§3.12/§3.14 and `03-DATABASE-SCHEMA.md` §4/§4a/§5/§14
 * are authoritative. This file is the whole storage half and it lands together
 * or not at all:
 *
 *   tracking_configs
 *     sync_interval_seconds        how often the device drains its queue
 *     distance_filter_metres       the OS distance filter
 *     movement_exit_speed_kmh      upper edge of the movement dead band
 *     live_sync_interval_seconds   drain cadence while an admin is watching
 *   tracking_config_history        the same four, mirrored (nullable)
 *   location_fixes.heading_deg     nullable bearing; NULL is NOT 0
 *   tracking_watches               one row per employee (§4a)
 *   version                        floored to 9 so every held ETag misses
 *
 * **This file was 031_tracking_config_v8.php and was never deployed.** v8 is
 * folded into v9 (`02` §3.12), so it is retargeted rather than shipped: the
 * ledger keys on the filename, so the rename is what makes this run even on a
 * database that did apply the v8 file by hand. Every step is idempotent —
 * addColumn introspects, addCheck swallows a duplicate name, CREATE TABLE is
 * IF NOT EXISTS — so running it after the v8 file is a no-op plus the two new
 * columns, and the version floor still moves (8 < 9).
 *
 * Ordering (§14): heading_deg is independent and goes first; the config columns
 * and the history mirror land before the version bump, because a device must
 * never re-pull a config whose columns do not exist yet; tracking_watches comes
 * after users, which 001 created.
 *
 * Dialect note. The CHECKs cannot be expressed identically on both drivers
 * through an ALTER, which is the precedent v8 already set:
 *   - SQLite has no ADD CONSTRAINT, but an ADD COLUMN may carry inline CHECKs
 *     that reference other columns, so each cross-column rule rides on the
 *     column it constrains. That is also why the columns are added in
 *     dependency order — live_sync_interval_seconds references
 *     sync_interval_seconds, so sync_interval_seconds must already exist.
 *   - MySQL/MariaDB column-level CHECK may reference only its own column, so
 *     every rule is added as a named table constraint after the columns.
 * MariaDB 11.8.8 enforces CHECKs, so these are real backstops on production and
 * not decoration. `{float}` is DOUBLE / REAL — the same token accuracy_m and
 * speed_kmh already use for §5's `real`.
 */
class TrackingConfigV9 extends Migration
{
    /** §3.12: a device must post at least twice inside the offline window. */
    private const WINDOW_CHECK = 'sync_interval_seconds <= offline_threshold_minutes * 30';

    /** §3.12: an inverted dead band is not a band. Equality = no dead band. */
    private const BAND_CHECK = 'movement_exit_speed_kmh >= movement_speed_threshold_kmh';

    /** §4: below 5 s is a battery hazard, above 120 s is not live. */
    private const LIVE_RANGE_CHECK = 'live_sync_interval_seconds BETWEEN 5 AND 120';

    /** §4: a live interval at or above the normal one is not a live interval. */
    private const LIVE_BELOW_CHECK = 'live_sync_interval_seconds < sync_interval_seconds';

    public function up()
    {
        // --- location_fixes.heading_deg (§5) ---------------------------------
        //
        // Nullable, and NULL is not 0 — 0 is due north. Platforms signal "no
        // bearing" as -1 or NaN and both normalise to NULL at ingest
        // (Engine::ingest), never to 0, because a marker rotated north on every
        // parked fix renders missing data as data. Every row written before this
        // migration is legitimately NULL, so there is nothing to backfill. The
        // 0 <= heading_deg < 360 range is enforced at ingest rather than as a
        // CHECK: on the highest-volume write path in the system a constraint
        // violation would surface as a PDOException the ingest loop can only
        // read as DUPLICATE, which would mislabel the rejection reason.
        $this->addColumn('location_fixes', 'heading_deg', '{float}');

        // --- tracking_configs: the four v9 columns (§4) ----------------------
        if (Dialect::isMysql()) {
            $this->addColumn('tracking_configs', 'sync_interval_seconds', '{int} NOT NULL DEFAULT 300');
            $this->addColumn('tracking_configs', 'distance_filter_metres', '{int} NOT NULL DEFAULT 20');
            $this->addColumn('tracking_configs', 'movement_exit_speed_kmh', '{float} NOT NULL DEFAULT 3');
            $this->addColumn('tracking_configs', 'live_sync_interval_seconds', '{int} NOT NULL DEFAULT 15');

            $this->addCheck('tracking_configs', 'chk_sync_interval_window', self::WINDOW_CHECK);
            $this->addCheck('tracking_configs', 'chk_movement_dead_band', self::BAND_CHECK);
            $this->addCheck('tracking_configs', 'chk_live_sync_range', self::LIVE_RANGE_CHECK);
            $this->addCheck('tracking_configs', 'chk_live_sync_below_sync', self::LIVE_BELOW_CHECK);
        } else {
            $this->addColumn(
                'tracking_configs',
                'sync_interval_seconds',
                '{int} NOT NULL DEFAULT 300 CHECK (' . self::WINDOW_CHECK . ')',
            );
            $this->addColumn('tracking_configs', 'distance_filter_metres', '{int} NOT NULL DEFAULT 20');
            $this->addColumn(
                'tracking_configs',
                'movement_exit_speed_kmh',
                '{float} NOT NULL DEFAULT 3 CHECK (' . self::BAND_CHECK . ')',
            );
            $this->addColumn(
                'tracking_configs',
                'live_sync_interval_seconds',
                '{int} NOT NULL DEFAULT 15'
                    . ' CHECK (' . self::LIVE_RANGE_CHECK . ')'
                    . ' CHECK (' . self::LIVE_BELOW_CHECK . ')',
            );
        }

        // History is append-only, so the mirror columns are nullable — rows
        // written before v9 legitimately have no value for any of the four. The
        // JSON payload captures them too; these columns keep the tunable knobs
        // queryable without parsing the snapshot.
        $this->addColumn('tracking_config_history', 'sync_interval_seconds', '{int}');
        $this->addColumn('tracking_config_history', 'distance_filter_metres', '{int}');
        $this->addColumn('tracking_config_history', 'movement_exit_speed_kmh', '{float}');
        $this->addColumn('tracking_config_history', 'live_sync_interval_seconds', '{int}');

        // --- tracking_watches (§4a) ------------------------------------------
        //
        // One row per EMPLOYEE, not per admin: two admins watching the same
        // person share one deadline that only ever moves forward, so neither
        // can cut the other off and the ingest path answers "is this device
        // being watched" with one primary-key lookup. Expiry is passive — a row
        // whose watching_until is in the past is simply never true again. The
        // watching_until index is there so an optional housekeeping DELETE
        // stays cheap, not because any read needs it. There is no unwatch.
        $this->exec("
            CREATE TABLE IF NOT EXISTS tracking_watches (
                employee_id    {uuid} PRIMARY KEY,
                watching_until {ts} NOT NULL,
                updated_at     {ts} NOT NULL,
                updated_by     {uuid},
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (updated_by) REFERENCES users(id)
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_tracking_watches_until ON tracking_watches (watching_until);
        ");

        // --- version floor (§3.12) -------------------------------------------
        //
        // Floor the stored version to 9 so the ETag changes and a device holding
        // a v7 (or a hand-applied v8) config re-pulls the new shape; if an org
        // already edited config past 9, bump instead so the ETag still moves
        // forward rather than going backwards. Last, because the columns the
        // re-pulled config describes have to exist before anything re-pulls it.
        $this->pdo->exec(
            'UPDATE tracking_configs
                SET version = CASE WHEN version < 9 THEN 9 ELSE version + 1 END'
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
        // Deliberately empty for the columns. Dropping one rewrites the whole
        // table on both drivers, and SQLite cannot drop an inline CHECK at all.
        // Rolling back leaves unused columns and harmless constraints, which is
        // cheaper and safer than a table rebuild — the same call 010 makes.
        // tracking_watches is new in this migration and holds nothing but
        // expiring deadlines, so it is safe to drop.
        $this->drop(['tracking_watches']);
    }
}
