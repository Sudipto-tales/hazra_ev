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
     * Partial update.
     *
     * `highAccuracyMode`, `syncOnMobileData` and `batterySaver` are **no longer
     * writable**. They used to let a device make its own collection less
     * aggressive than the organisation's rule, and collection is organisation
     * config — identical for every employee, admin-owned, and not something an
     * employee may opt out of one phone at a time. Migration 036 reset the
     * stored columns and `Present::preferences` now reports the fixed uniform
     * values, so a build in the field reads the behaviour it should have.
     *
     * They are dropped from the map rather than 422'd on: an old app sending
     * them is not making an error, it is sending a field that stopped meaning
     * anything, and failing its whole preferences save over that would break
     * theme and notification settings too. Unknown keys are already ignored
     * here, so they take exactly that path.
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

    /**
     * POST /me/password — the account holder changes their own password.
     *
     * Knowing the current one is the whole authorisation: a stolen access token
     * must not be enough to lock the owner out of their own account.
     *
     * The optional `refreshToken` is the caller's own. Passing it keeps this
     * device signed in while every other one is cut loose — the same body field
     * `POST /auth/logout` already takes. Omit it and all sessions end, including
     * this one, which is the right default for "I think someone has my phone".
     */
    public function changePassword(): never
    {
        $body = ApiRequest::body();
        $user = Users::byId(Ctx::id());

        $current = (string) ($body['currentPassword'] ?? '');

        if ($current === '') {
            Envelope::invalid('currentPassword is required', 'currentPassword');
        }

        // 403, not 401. ApiClient refreshes and replays the request once on any
        // 401, so a 401 here would burn a refresh and fail again identically —
        // and the token is fine. It is the password that is wrong.
        if (!password_verify($current, $user['password_hash'])) {
            Envelope::forbidden('Current password is incorrect');
        }

        $next = Password::validate($body['newPassword'] ?? null, 'newPassword');

        if ($next === $current) {
            Envelope::invalid('newPassword must be different from the current one', 'newPassword');
        }

        Password::set(Ctx::id(), $next, mustChange: false);

        $keep = Wire::text($body['refreshToken'] ?? null);
        Password::revokeSessions(Ctx::id(), $keep ? hash('sha256', $keep) : null);

        Ctx::audit('user', Ctx::id(), 'password_change', null, null);

        Envelope::ok(['changed' => true, 'mustChangePassword' => false]);
    }
}
