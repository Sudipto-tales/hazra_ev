<?php

/**
 * Universal GPS distance engine — storage half, cycling / road / train.
 *
 * Measured distance stays haversine over consecutive GPS fixes and nothing
 * else: no routing service, no road-network length, no matched geometry ever
 * reaches a number an employee is paid against. This migration adds the
 * columns that let `Engine::chainDistance` say *how well* it knows a number,
 * which is the half the system was missing.
 *
 * Five things land together:
 *
 *   tracking_configs
 *     min_step_metres            server-side measurement floor (SPLIT, below)
 *     accuracy_good_metres       0 .. good .. 20, moderate up to
 *                                min_accuracy_metres, poor above it
 *     accuracy_step_factor       scales the step by the fix's own error
 *     gps_gap_warning_seconds    60
 *     gps_gap_incomplete_seconds 180
 *   tracking_config_history      the same five, mirrored (nullable)
 *   location_fixes.source        STREAM | HEARTBEAT
 *   work_sessions / day_routes   distance_algo_version + the three reporting columns
 *   attendance_days              the three reporting columns
 *   session_gps_gaps             one row per non-normal stretch
 *   session_transport_modes      mode intervals, not a session column
 *
 * **Why `min_step_metres` exists at all.** `distance_filter_metres` was doing
 * two unrelated jobs: the OS capture filter on the device and the server's
 * minimum measurement step. Those want opposite values. Capture wants to go
 * *down* (10 m) so a winding track is sampled finely enough not to cut its
 * corners; measurement wants to stay *up* (15 m) so a parked phone's ~13 m
 * drift at 15 m accuracy is never booked as travel. One column cannot be both,
 * which is why lowering the filter alone would have inflated every parked
 * session. The filter keeps its meaning and its column; measurement moves here.
 *
 * **Why the three reporting columns are nullable with no default.** A row
 * written before this migration has no coverage figure, and 0 and 100 are both
 * lies about it — the same reason `location_fixes.heading_deg` is nullable in
 * 031 and NULL there is not 0. NULL means "this row predates the universal
 * engine", and `distance_km` remains its only distance. Nothing is backfilled.
 *
 * **Why `distance_algo_version` defaults to 1.** Acceptance criterion: a
 * historical distance is never silently recalculated. That is not free here —
 * `Engine::chainDistanceKm`'s whole design is that nothing is stored about the
 * decision, so retuning a threshold re-derives on the next refresh, and
 * `recompute()` re-derives an entire day on any late-arriving fix. So the
 * algorithm itself has to be pinned per row. `addColumn` stamps every existing
 * session and route with 1, and `Engine` keeps the v1 code path verbatim and
 * forever; only rows created after this migration carry
 * `Engine::DISTANCE_ALGO_VERSION` and get the new measurement.
 *
 * Config defaults note. `location_interval_seconds` (30 -> 10) and
 * `distance_filter_metres` (20 -> 10) are *existing* columns, and neither
 * driver can change an existing column's DEFAULT without rewriting the table —
 * SQLite cannot at all. They are also only ever read through
 * `Ctx::config()`, which materialises a missing row from column defaults, so
 * the new values are written there instead (one documented place, the pattern
 * `ConfigController::DEFAULTS` already set). Existing org rows keep whatever
 * their admin set, which is the requirement.
 *
 * Dialect note, inherited from 031: SQLite has no ADD CONSTRAINT but an ADD
 * COLUMN may carry inline CHECKs referencing other columns, so each
 * cross-column rule rides on the column it constrains and the columns are
 * added in dependency order. MySQL column-level CHECK may reference only its
 * own column, so every rule is a named table constraint added after.
 */
class UniversalDistanceEngine extends Migration
{
    /**
     * Good ends before poor begins.
     *
     * There is deliberately no separate moderate/poor column. That edge is
     * `min_accuracy_metres`, which already means exactly that and is already
     * what the admin route screen reads to decide a fix is rough. A second
     * column for the same boundary would be two sources of truth for one fact,
     * free to drift apart until the engine's "poor" and the map's "rough"
     * disagreed about the same fix — the overload this migration exists to
     * undo, reintroduced one column over. So the bands are
     * good `0 .. accuracy_good_metres`, moderate up to `min_accuracy_metres`,
     * and poor above it.
     */
    private const BANDS_CHECK = 'accuracy_good_metres <= min_accuracy_metres';

    /** Incomplete is strictly worse than a warning, or it is the same signal. */
    private const GAP_ORDER_CHECK = 'gps_gap_incomplete_seconds > gps_gap_warning_seconds';

    /** 0 disables the scaling and restores a flat step; negative is meaningless. */
    private const FACTOR_CHECK = 'accuracy_step_factor >= 0';

    /** A negative floor would subtract distance. 0 disables the gate. */
    private const MIN_STEP_CHECK = 'min_step_metres >= 0';

    public function up()
    {
        // --- tracking_configs: the five new knobs ----------------------------
        if (Dialect::isMysql()) {
            $this->addColumn('tracking_configs', 'min_step_metres', '{float} NOT NULL DEFAULT 15');
            $this->addColumn('tracking_configs', 'accuracy_good_metres', '{float} NOT NULL DEFAULT 20');
            $this->addColumn('tracking_configs', 'accuracy_step_factor', '{float} NOT NULL DEFAULT 1');
            $this->addColumn('tracking_configs', 'gps_gap_warning_seconds', '{int} NOT NULL DEFAULT 60');
            $this->addColumn('tracking_configs', 'gps_gap_incomplete_seconds', '{int} NOT NULL DEFAULT 180');

            $this->addCheck('tracking_configs', 'chk_accuracy_bands', self::BANDS_CHECK);
            $this->addCheck('tracking_configs', 'chk_gap_order', self::GAP_ORDER_CHECK);
            $this->addCheck('tracking_configs', 'chk_accuracy_step_factor', self::FACTOR_CHECK);
            $this->addCheck('tracking_configs', 'chk_min_step_metres', self::MIN_STEP_CHECK);
        } else {
            // Dependency order: the gap rule references
            // gps_gap_warning_seconds, so the referenced column is added
            // before the column that constrains it. The band rule references
            // min_accuracy_metres, which 002 created, so that one already holds.
            $this->addColumn(
                'tracking_configs',
                'min_step_metres',
                '{float} NOT NULL DEFAULT 15 CHECK (' . self::MIN_STEP_CHECK . ')',
            );
            $this->addColumn(
                'tracking_configs',
                'accuracy_good_metres',
                '{float} NOT NULL DEFAULT 20 CHECK (' . self::BANDS_CHECK . ')',
            );
            $this->addColumn(
                'tracking_configs',
                'accuracy_step_factor',
                '{float} NOT NULL DEFAULT 1 CHECK (' . self::FACTOR_CHECK . ')',
            );
            $this->addColumn('tracking_configs', 'gps_gap_warning_seconds', '{int} NOT NULL DEFAULT 60');
            $this->addColumn(
                'tracking_configs',
                'gps_gap_incomplete_seconds',
                '{int} NOT NULL DEFAULT 180 CHECK (' . self::GAP_ORDER_CHECK . ')',
            );
        }

        // Append-only, so the mirror is nullable: a row written before this
        // migration legitimately has no value for any of the five.
        $this->addColumn('tracking_config_history', 'min_step_metres', '{float}');
        $this->addColumn('tracking_config_history', 'accuracy_good_metres', '{float}');
        $this->addColumn('tracking_config_history', 'accuracy_step_factor', '{float}');
        $this->addColumn('tracking_config_history', 'gps_gap_warning_seconds', '{int}');
        $this->addColumn('tracking_config_history', 'gps_gap_incomplete_seconds', '{int}');

        // --- location_fixes.source -------------------------------------------
        //
        // The device's heartbeat fix is built through the same _toLog() as a
        // movement sample, so until now it was indistinguishable from one. That
        // is what made parked time unclassifiable: a parked vehicle emits
        // nothing until the heartbeat fires at 300 s, which is past the 180 s
        // incomplete threshold, so every lunch break would have been reported
        // as broken GPS coverage. Existing rows become STREAM — v1 days never
        // run gap classification, so the value is never read for them.
        $this->addColumn('location_fixes', 'source', "{str} NOT NULL DEFAULT 'STREAM'");

        // --- work_sessions / day_routes / attendance_days --------------------
        foreach (['work_sessions', 'day_routes'] as $table) {
            $this->addColumn($table, 'distance_algo_version', '{int} NOT NULL DEFAULT 1');
        }

        foreach (['work_sessions', 'day_routes', 'attendance_days'] as $table) {
            $this->addColumn($table, 'verified_distance_km', '{float}');
            $this->addColumn($table, 'incomplete_distance_km', '{float}');
            $this->addColumn($table, 'gps_coverage_percent', '{float}');
        }

        // --- session_gps_gaps -------------------------------------------------
        //
        // One row per NON-NORMAL stretch; a normal stretch is the absence of a
        // row, which is why the classification column has only two values.
        // `displacement_m` is the column that makes the whole thing work:
        // elapsed time alone cannot tell a parked vehicle from a tunnel, so a
        // quiet stretch is only a GPS gap when something actually moved across
        // it. employee_id is denormalised exactly as location_fixes does it —
        // every admin read filters on it.
        $this->exec("
            CREATE TABLE IF NOT EXISTS session_gps_gaps (
                id             {autoid},
                session_id     {uuid} NOT NULL,
                employee_id    {uuid} NOT NULL,
                work_date      {date} NOT NULL,
                gap_start      {ts} NOT NULL,
                gap_end        {ts} NOT NULL,
                seconds        {int} NOT NULL,
                displacement_m {float} NOT NULL,
                classification {str} NOT NULL,
                FOREIGN KEY (session_id) REFERENCES work_sessions(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_gaps_session ON session_gps_gaps (session_id);
            CREATE INDEX IF NOT EXISTS idx_gaps_employee_date ON session_gps_gaps (employee_id, work_date);
        ");

        // --- session_transport_modes -----------------------------------------
        //
        // An interval table rather than a work_sessions column, because the
        // mode is changeable mid-session and the history has to survive the
        // change. effective_to NULL means "still in force". Mode is metadata
        // for analytics and for whether the drawn line may be snapped — it
        // never selects a different distance algorithm, and it never varies
        // capture config, which is uniform for every employee.
        $this->exec("
            CREATE TABLE IF NOT EXISTS session_transport_modes (
                id             {autoid},
                session_id     {uuid} NOT NULL,
                employee_id    {uuid} NOT NULL,
                work_date      {date} NOT NULL,
                mode           {str} NOT NULL,
                effective_from {ts} NOT NULL,
                effective_to   {ts},
                FOREIGN KEY (session_id) REFERENCES work_sessions(id) ON DELETE CASCADE
            ) {opts};
            CREATE INDEX IF NOT EXISTS idx_modes_session ON session_transport_modes (session_id, effective_from);
            CREATE INDEX IF NOT EXISTS idx_modes_employee_date ON session_transport_modes (employee_id, work_date);
        ");

        // --- uniform device collection ---------------------------------------
        //
        // The three per-employee tracking flags are retired: collection is
        // organisation config, identical for every employee, and an employee
        // may not make their own tracking less aggressive than the org's rule.
        // This resets the stored values; the write path is removed with the
        // contract work (Phase 2), and `Present::preferences` will report these
        // fixed values rather than the columns. Config only — it touches no
        // distance record, so no historical number moves.
        $this->pdo->exec(
            'UPDATE user_preferences
                SET high_accuracy_mode = 1, sync_on_mobile_data = 1, battery_saver = 0'
        );

        // --- version floor ----------------------------------------------------
        //
        // Floor to 10 so every held ETag misses and a device re-pulls a config
        // carrying the five new fields. Bump instead if an org is already past
        // 10, so the version only ever moves forward. Last, because the columns
        // the re-pulled config describes have to exist before anything re-pulls.
        $this->pdo->exec(
            'UPDATE tracking_configs
                SET version = CASE WHEN version < 10 THEN 10 ELSE version + 1 END'
        );
    }

    /** See 031 — named table CHECK, once, MySQL/MariaDB only. */
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
        // Columns are deliberately left in place: dropping one rewrites the
        // whole table on both drivers and SQLite cannot drop an inline CHECK at
        // all, so a rollback leaving unused columns is cheaper and safer than a
        // rebuild — the same call 031 and 010 make. The two new tables hold
        // only derived data and are safe to drop.
        $this->drop(['session_gps_gaps', 'session_transport_modes']);
    }
}
