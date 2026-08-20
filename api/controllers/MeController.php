<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * The principal and its device preferences. `GET /me` returns an Employee or an
 * AdminUser discriminated by `type` — the same shape `/bootstrap` embeds.
 */
final class MeController extends V1Controller
{
    public function show(): never
    {
        Envelope::noStore();
        Envelope::ok(Present::principal(Users::byId(Ctx::id())));
    }

    /** Self-editable fields only. Roster fields belong to the admin. */
    public function update(): never
    {
        $body = ApiRequest::body();
        $sets = [];
        $params = [];

        foreach (['avatarUrl' => 'avatar_url', 'phone' => 'phone'] as $wire => $column) {
            if (array_key_exists($wire, $body)) {
                $sets[] = "{$column} = ?";
                $params[] = (string) $body[$wire];
            }
        }

        if (!$sets) {
            Envelope::invalid('Nothing to update — avatarUrl and phone are the self-editable fields');
        }

        $params[] = Wire::now();
        $params[] = Ctx::id();

        db_execute("UPDATE users SET " . implode(', ', $sets) . ", updated_at = ? WHERE id = ?", $params);

        Envelope::ok(Present::principal(Users::byId(Ctx::id())));
    }

    public function preferences(): never
    {
        Envelope::noStore();
        Envelope::ok(Present::preferences(Users::preferences(Ctx::id())));
    }

    /**
     * Partial update. `highAccuracyMode`, `syncOnMobileData` and `batterySaver`
     * interact with TrackingConfig: admin config sets the ceiling, a device
     * preference may only make collection less aggressive, never more.
     */
    public function updatePreferences(): never
    {
        Users::preferences(Ctx::id());

        $columns = [
            'themeMode'            => 'theme_mode',
            'language'             => 'language',
            'notificationsEnabled' => 'notifications_enabled',
            'reportReminders'      => 'report_reminders',
            'sessionReminders'     => 'session_reminders',
            'systemNotifications'  => 'system_notifications',
            'highAccuracyMode'     => 'high_accuracy_mode',
            'syncOnMobileData'     => 'sync_on_mobile_data',
            'batterySaver'         => 'battery_saver',
        ];

        $body = ApiRequest::body();
        $sets = [];
        $params = [];

        foreach ($columns as $wire => $column) {
            if (!array_key_exists($wire, $body)) {
                continue;
            }

            $sets[] = "{$column} = ?";
            $params[] = in_array($wire, ['themeMode', 'language'], true)
                ? (string) $body[$wire]
                : (Wire::bool($body[$wire]) ? 1 : 0);
        }

        if ($sets) {
            $params[] = Ctx::id();
            db_execute("UPDATE user_preferences SET " . implode(', ', $sets) . " WHERE user_id = ?", $params);
        }

        Envelope::ok(Present::preferences(Users::preferences(Ctx::id())));
    }
}
