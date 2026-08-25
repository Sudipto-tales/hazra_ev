<?php

/**
 * Request context: who is calling, which organisation they belong to, what the
 * tracking rules are, and whose data they are allowed to ask for.
 *
 * The `subject` parameter is authorised, not trusted (API plan §1.2). An
 * employee token may only ever resolve to `me`; asking for `team` with one is a
 * 403, not an empty list.
 */
final class Ctx
{
    private static ?array $principal = null;
    private static ?array $org = null;
    private static ?array $config = null;

    /** The authenticated user row joined to its role profile. */
    public static function principal(): array
    {
        if (self::$principal !== null) {
            return self::$principal;
        }

        $payload = JwtAuth::authenticate();

        if (!$payload || empty($payload['sub'])) {
            Envelope::unauthorized();
        }

        $user = db_fetch_one(
            "SELECT u.*,
                    ep.employee_code, ep.designation, ep.department, ep.region AS emp_region,
                    ep.banner_url, ep.joined_on, ep.reports_to_id, ep.blood_group, ep.address,
                    ap.admin_code, ap.role_title, ap.region AS admin_region
               FROM users u
               LEFT JOIN employee_profiles ep ON ep.user_id = u.id
               LEFT JOIN admin_profiles    ap ON ap.user_id = u.id
              WHERE u.id = ?",
            [$payload['sub']],
        );

        if (!$user) {
            Envelope::unauthorized('Token does not resolve to a user');
        }

        if (!(int) $user['active']) {
            Envelope::forbidden('This account is deactivated');
        }

        return self::$principal = $user;
    }

    /**
     * Primes the context outside an HTTP request — the seeder and any future
     * background job need an org and a config without a bearer token.
     */
    public static function impersonate(array $user): void
    {
        self::$principal = $user;
        self::$org = null;
        self::$config = null;
    }

    public static function id(): string
    {
        return self::principal()['id'];
    }

    public static function isAdmin(): bool
    {
        return self::principal()['role'] === 'admin';
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            Envelope::forbidden('Admin role required');
        }
    }

    public static function orgId(): string
    {
        return self::principal()['org_id'];
    }

    public static function org(): array
    {
        return self::$org ??= db_fetch_one(
            "SELECT * FROM organizations WHERE id = ?",
            [self::orgId()],
        ) ?: ['id' => self::orgId(), 'timezone' => 'Asia/Dhaka', 'weekend_days' => '[5,6]'];
    }

    public static function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(self::org()['timezone'] ?? 'UTC');
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /** Org-local calendar day. Work dates are local days, not UTC days. */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', self::timezone()))->format('Y-m-d');
    }

    /**
     * Tracking rules in force. One row per organisation — never per employee,
     * never per device. A missing row means the org was created outside the
     * seeder, so the documented defaults are materialised on first read.
     */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $row = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = ?", [self::orgId()]);

        if (!$row) {
            db_execute(
                "INSERT INTO tracking_configs (org_id, updated_at) VALUES (?, ?)",
                [self::orgId(), Wire::now()],
            );
            $row = db_fetch_one("SELECT * FROM tracking_configs WHERE org_id = ?", [self::orgId()]);
        }

        return self::$config = $row;
    }

    public static function configValue(string $key, float|int $default): float|int
    {
        $config = self::config();
        return isset($config[$key]) ? $config[$key] + 0 : $default;
    }

    /**
     * Resolves `?subject=` to the employee ids the caller may read.
     *
     * Returns ['scope' => 'self'|'employee'|'team', 'ids' => string[], 'id' => ?string].
     * `id` is null only for team scope.
     */
    public static function subject(?string $raw, bool $allowTeam = true): array
    {
        $raw = $raw === null || $raw === '' ? 'me' : $raw;

        if ($raw === 'me') {
            return ['scope' => 'self', 'ids' => [self::id()], 'id' => self::id()];
        }

        // Anything other than `me` is an admin question.
        if (!self::isAdmin()) {
            Envelope::forbidden('An employee token can only address subject=me');
        }

        if ($raw === 'team' || $raw === 'all') {
            if (!$allowTeam) {
                Envelope::invalid('This endpoint addresses one employee at a time', 'subject');
            }

            return ['scope' => 'team', 'ids' => self::teamIds(), 'id' => null];
        }

        $employee = db_fetch_one(
            "SELECT id FROM users WHERE id = ? AND org_id = ? AND role = 'employee'",
            [$raw, self::orgId()],
        );

        if (!$employee) {
            Envelope::notFound('EMPLOYEE_NOT_FOUND', 'No such employee in this organisation');
        }

        return ['scope' => 'employee', 'ids' => [$employee['id']], 'id' => $employee['id']];
    }

    /** Every employee in the org, deactivated ones included — the roster keeps them. */
    public static function teamIds(): array
    {
        return array_column(
            db_fetch_all(
                "SELECT id FROM users WHERE org_id = ? AND role = 'employee' ORDER BY name",
                [self::orgId()],
            ),
            'id',
        );
    }

    /** ISO weekday numbers the org treats as a weekend. */
    public static function weekendDays(): array
    {
        $days = Wire::json(self::org()['weekend_days'] ?? null, [5, 6]);
        return array_map('intval', is_array($days) ? $days : [5, 6]);
    }

    public static function audit(
        string $entity,
        string $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null,
    ): void {
        db_execute(
            "INSERT INTO audit_log (org_id, actor_id, entity, entity_id, action, `before`, `after`, occurred_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                self::orgId(), self::id(), $entity, $entityId, $action,
                $before === null ? null : json_encode($before),
                $after === null ? null : json_encode($after),
                Wire::now(),
            ],
        );
    }
}
