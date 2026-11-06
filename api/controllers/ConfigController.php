<?php

require_once __DIR__ . '/../support/V1Controller.php';

/**
 * Admin-owned tracking rules, one row per organisation.
 *
 * Every field is writable over the API including the six the UI marks
 * read-only — that restriction is a product decision, and tuning maxJumpKmh
 * must not need an app release.
 *
 * A change is read-time only: stored fixes are never rewritten. Stop and
 * long-stop classification is re-evaluated against the config in force at read
 * time, and same-day sessions are re-derived below.
 */
final class ConfigController extends V1Controller
{
    private const FIELDS = [
        'locationIntervalSeconds'             => 'location_interval_seconds',
        'minAccuracyMetres'                   => 'min_accuracy_metres',
        'stopRadiusMetres'                    => 'stop_radius_metres',
        'stopThresholdMinutes'                => 'stop_threshold_minutes',
        'longStopThresholdMinutes'            => 'long_stop_threshold_minutes',
        'movementSpeedThresholdKmh'           => 'movement_speed_threshold_kmh',
        'maxJumpKmh'                          => 'max_jump_kmh',
        'offlineThresholdMinutes'             => 'offline_threshold_minutes',
        'locationUnavailableThresholdMinutes' => 'location_unavailable_threshold_minutes',
        'syncBatchSize'                       => 'sync_batch_size',
        'syncIntervalSeconds'                 => 'sync_interval_seconds',
        'distanceFilterMetres'                => 'distance_filter_metres',
        'movementExitSpeedKmh'                => 'movement_exit_speed_kmh',
        'liveSyncIntervalSeconds'             => 'live_sync_interval_seconds',
        // --- the universal distance engine (036) -------------------------
        //
        // minStepMetres is the server's measurement floor and is NOT
        // distanceFilterMetres, which stayed behind as the device's OS capture
        // filter. One column was doing both jobs and they want opposite values:
        // capture goes down so a winding track keeps its corners, measurement
        // stays up so a parked phone's drift never books distance.
        //
        // There is no moderate/poor threshold here. That edge is
        // minAccuracyMetres, which already means it.
        'minStepMetres'                       => 'min_step_metres',
        'accuracyGoodMetres'                  => 'accuracy_good_metres',
        'accuracyStepFactor'                  => 'accuracy_step_factor',
        'gpsGapWarningSeconds'                => 'gps_gap_warning_seconds',
        'gpsGapIncompleteSeconds'             => 'gps_gap_incomplete_seconds',
    ];

    /** Documented defaults, used when a column predates the v9 migration. */
    private const DEFAULTS = [
        'syncIntervalSeconds'     => 300,
        'distanceFilterMetres'    => 20,
        'movementExitSpeedKmh'    => 3,
        'liveSyncIntervalSeconds' => 15,
        'minStepMetres'           => 15,
        'accuracyGoodMetres'      => 20,
        'accuracyStepFactor'      => 1,
        'gpsGapWarningSeconds'    => 60,
        'gpsGapIncompleteSeconds' => 180,
    ];

    /** `version` backs the ETag, so an unchanged config is a 304. */
    public function show(): never
    {
        $config = Ctx::config();

        Envelope::freshness('config-' . Ctx::orgId() . '-' . $config['version'], Envelope::IMMUTABLE);
        Envelope::ok(Present::config($config));
    }

    public function update(): never
    {
        $this->requireAdmin();

        $before = Ctx::config();
        $body = ApiRequest::body();

        // Non-numeric input on any field is a 422 before anything else happens:
        // clamping applies to numbers out of range, never to garbage (02 §3.12).
        $values = [];

        foreach (self::FIELDS as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }

            if (!is_numeric($body[$wire])) {
                Envelope::invalid("{$wire} must be numeric", $wire);
            }

            $values[$wire] = $body[$wire] + 0;
        }

        if (!$values) {
            Envelope::invalid('No writable configuration fields in the body');
        }

        // Every cross-field rule below is checked against POST-WRITE values:
        // the body wins where it sets a field, the stored row answers where it
        // does not, so a PUT that moves both sides of a pair is judged on what
        // it is about to become rather than on half of it (02 §3.12, 03 §4).
        // By reference: a clamp below rewrites $values, and the 422 rules that
        // follow must judge the clamped number, not the one that was typed.
        $effective = function (string $wire) use (&$values, $before): float|int {
            if (array_key_exists($wire, $values)) {
                return $values[$wire];
            }

            $column = self::FIELDS[$wire];

            return isset($before[$column])
                ? $before[$column] + 0
                : (self::DEFAULTS[$wire] ?? 0);
        };

        // --- the two clamped rules -------------------------------------------
        //
        // Out of range here is a typo or a slider, not an intent, and an admin's
        // save must not fail over it. The response is the acknowledgement: it
        // carries back the value that was actually stored.
        if (array_key_exists('movementExitSpeedKmh', $values)) {
            $values['movementExitSpeedKmh'] = max(0, min(50, $values['movementExitSpeedKmh']));
        }

        // A live interval at or above the normal one makes "live" meaningless
        // but breaks nothing, so it clamps to syncIntervalSeconds - 1. Clamped
        // even when only syncIntervalSeconds moved: lowering the normal interval
        // under the stored live one would otherwise fail the whole save on the
        // DB CHECK, which is the one thing these rules clamp to avoid.
        $liveSyncAsked = $effective('liveSyncIntervalSeconds');
        $syncInterval = $effective('syncIntervalSeconds');

        $liveSync = max(5, min(120, $liveSyncAsked));

        if ($liveSync >= $syncInterval) {
            $liveSync = $syncInterval - 1;
        }

        if ($liveSync != $liveSyncAsked) {
            $values['liveSyncIntervalSeconds'] = $liveSync;
        }

        // A step factor is a slider: out of range is a typo, not an intent. 0
        // disables the scaling and restores a flat min_step_metres floor, which
        // is the documented escape hatch; above 5 the step would exceed any
        // plausible fix error and measure nothing at all.
        if (array_key_exists('accuracyStepFactor', $values)) {
            $values['accuracyStepFactor'] = max(0, min(5, $values['accuracyStepFactor']));
        }

        // Same reasoning. 0 disables the floor (the raw chord sum); 500 m is
        // already far past anything a 10 s capture interval can produce.
        if (array_key_exists('minStepMetres', $values)) {
            $values['minStepMetres'] = max(0, min(500, $values['minStepMetres']));
        }

        // --- the two 422 rules -----------------------------------------------
        //
        // Carried over from v8: the device must post at least twice inside the
        // offline window, or it would be flagged offline between its own
        // uploads. A 422 rather than a clamp because an admin who typed a
        // number has to be told it cannot hold, not quietly given another one.
        $offlineMinutes = array_key_exists('offlineThresholdMinutes', $body) && is_numeric($body['offlineThresholdMinutes'])
            ? $body['offlineThresholdMinutes'] + 0
            : (int) $before['offline_threshold_minutes'];

        if ($syncInterval > $offlineMinutes * 30) {
            Envelope::invalid(
                'syncIntervalSeconds must be at most offlineThresholdMinutes × 60 / 2 '
                    . "(≤ {$offlineMinutes} × 30 = " . ($offlineMinutes * 30) . 's)',
                'syncIntervalSeconds',
            );
        }

        // An inverted dead band is not a band: both predicates would be true at
        // once and the "hold" case unreachable. Equality is legal and simply
        // means no dead band, i.e. the pre-v9 behaviour. Mirrors the existing
        // long_stop >= stop discipline, and the DB CHECK is the backstop.
        $exitSpeed = $effective('movementExitSpeedKmh');
        $enterSpeed = array_key_exists('movementSpeedThresholdKmh', $body) && is_numeric($body['movementSpeedThresholdKmh'])
            ? $body['movementSpeedThresholdKmh'] + 0
            : (float) $before['movement_speed_threshold_kmh'];

        if ($exitSpeed < $enterSpeed) {
            Envelope::invalid(
                "movementExitSpeedKmh must be at least movementSpeedThresholdKmh ({$enterSpeed}) — "
                    . 'the movement dead band cannot invert',
                'movementExitSpeedKmh',
            );
        }

        // Good cannot end after poor begins, or the moderate band inverts and
        // a fix lands in two of them. 422 rather than a clamp, for the same
        // reason the pair rules above are: an admin who typed a number has to
        // be told it cannot hold. The DB CHECK is the backstop.
        $goodMetres = $effective('accuracyGoodMetres');
        $poorMetres = array_key_exists('minAccuracyMetres', $body) && is_numeric($body['minAccuracyMetres'])
            ? $body['minAccuracyMetres'] + 0
            : (float) $before['min_accuracy_metres'];

        if ($goodMetres > $poorMetres) {
            Envelope::invalid(
                "accuracyGoodMetres must be at most minAccuracyMetres ({$poorMetres}) — "
                    . 'the moderate band cannot invert',
                'accuracyGoodMetres',
            );
        }

        // Incomplete is strictly worse than a warning. Equal thresholds would
        // make one of the two classifications unreachable.
        $gapWarning = $effective('gpsGapWarningSeconds');
        $gapIncomplete = $effective('gpsGapIncompleteSeconds');

        if ($gapIncomplete <= $gapWarning) {
            Envelope::invalid(
                "gpsGapIncompleteSeconds must be greater than gpsGapWarningSeconds ({$gapWarning}) — "
                    . 'otherwise no gap is ever only a warning',
                'gpsGapIncompleteSeconds',
            );
        }

        $sets = [];
        $params = [];

        foreach ($values as $wire => $value) {
            $sets[] = self::FIELDS[$wire] . ' = ?';
            $params[] = $value;
        }

        $params[] = Wire::now();
        $params[] = Ctx::id();
        $params[] = Ctx::orgId();

        try {
            db_execute(
                "UPDATE tracking_configs
                    SET " . implode(', ', $sets) . ", version = version + 1, updated_at = ?, updated_by = ?
                  WHERE org_id = ?",
                $params,
            );
        } catch (PDOException) {
            Envelope::invalid(
                'longStopThresholdMinutes must be at least stopThresholdMinutes, '
                    . 'locationIntervalSeconds must be 5-600, '
                    . 'syncIntervalSeconds must be at most offlineThresholdMinutes × 60 / 2 '
                    . 'and greater than liveSyncIntervalSeconds (which holds at 5-120s), '
                    . 'movementExitSpeedKmh must be at least movementSpeedThresholdKmh, '
                    . 'accuracyGoodMetres must be at most minAccuracyMetres, '
                    . 'gpsGapIncompleteSeconds must be greater than gpsGapWarningSeconds, '
                    . 'and minStepMetres and accuracyStepFactor must not be negative'
            );
        }

        $after = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = ?", [Ctx::orgId()]);

        db_execute(
            "INSERT INTO tracking_config_history
                 (id, org_id, version, payload, sync_interval_seconds, distance_filter_metres,
                  movement_exit_speed_kmh, live_sync_interval_seconds, changed_at, changed_by,
                  min_step_metres, accuracy_good_metres, accuracy_step_factor,
                  gps_gap_warning_seconds, gps_gap_incomplete_seconds)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(), Ctx::orgId(), $after['version'], json_encode($after),
                $after['sync_interval_seconds'] ?? null, $after['distance_filter_metres'] ?? null,
                $after['movement_exit_speed_kmh'] ?? null, $after['live_sync_interval_seconds'] ?? null,
                Wire::now(), Ctx::id(),
                // The payload JSON above already carries the whole row; these
                // keep the tunable knobs queryable without parsing it, which is
                // the same reason the v9 four are mirrored.
                $after['min_step_metres'] ?? null, $after['accuracy_good_metres'] ?? null,
                $after['accuracy_step_factor'] ?? null,
                $after['gps_gap_warning_seconds'] ?? null, $after['gps_gap_incomplete_seconds'] ?? null,
            ],
        );

        Ctx::audit('tracking_config', Ctx::orgId(), 'update', Present::config($before), Present::config($after));

        $this->rederiveOpenDays();

        Envelope::ok(Present::config($after));
    }

    /**
     * Stops are materialised, so a change to the radius or dwell threshold has
     * to re-derive open and same-day sessions. Closed historical days are left
     * alone on purpose: retroactively editing what counted as a visit three
     * weeks ago would make the calendar disagree with itself.
     */
    private function rederiveOpenDays(): void
    {
        $today = Ctx::today();

        $rows = db_fetch_all(
            "SELECT DISTINCT s.employee_id
               FROM work_sessions s
               JOIN users u ON u.id = s.employee_id
              WHERE u.org_id = ? AND (s.work_date = ? OR s.ended_at IS NULL)",
            [Ctx::orgId(), $today],
        );

        foreach ($rows as $row) {
            Engine::recompute($row['employee_id'], $today);
        }
    }
}
