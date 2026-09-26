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

        $sets = [];
        $params = [];

        foreach (self::FIELDS as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }

            if (!is_numeric($body[$wire])) {
                Envelope::invalid("{$wire} must be numeric", $wire);
            }

            $sets[] = "{$column} = ?";
            $params[] = $body[$wire] + 0;
        }

        if (!$sets) {
            Envelope::invalid('No writable configuration fields in the body');
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
            Envelope::invalid('longStopThresholdMinutes must be at least stopThresholdMinutes, and locationIntervalSeconds must be 5-600');
        }

        $after = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = ?", [Ctx::orgId()]);

        db_execute(
            "INSERT INTO tracking_config_history (id, org_id, version, payload, changed_at, changed_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), Ctx::orgId(), $after['version'], json_encode($after), Wire::now(), Ctx::id()],
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
