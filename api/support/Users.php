<?php

/**
 * The one identity read. `users` carries what both roles share; the profile
 * tables carry what only one of them has, so neither a nullable mega-table nor
 * a duplicated one is needed.
 */
final class Users
{
    private const SELECT = "
        SELECT u.*,
               ep.employee_code, ep.designation, ep.department, ep.region AS emp_region,
               ep.banner_url, ep.joined_on, ep.reports_to_id, ep.blood_group, ep.address,
               ap.admin_code, ap.role_title, ap.region AS admin_region,
               m.name AS manager_name, mp.role_title AS manager_role
          FROM users u
          LEFT JOIN employee_profiles ep ON ep.user_id = u.id
          LEFT JOIN admin_profiles    ap ON ap.user_id = u.id
          LEFT JOIN users             m  ON m.id = ep.reports_to_id
          LEFT JOIN admin_profiles    mp ON mp.user_id = m.id
    ";

    public static function byId(string $id): ?array
    {
        $row = db_fetch_one(self::SELECT . " WHERE u.id = ?", [$id]);
        return $row ? self::hydrate($row) : null;
    }

    public static function byEmail(string $email): ?array
    {
        $row = db_fetch_one(self::SELECT . " WHERE u.email = ? LIMIT 1", [$email]);
        return $row ? self::hydrate($row) : null;
    }

    /** @return array<int, array> */
    public static function many(string $where, array $params): array
    {
        return array_map([self::class, 'hydrate'], db_fetch_all(self::SELECT . ' ' . $where, $params));
    }

    /**
     * `reportingTo` is rendered here rather than in the app, which is the
     * change data doc §1.1 asks for: store the manager id, render the label
     * server-side.
     */
    private static function hydrate(array $row): array
    {
        $row['reporting_to_label'] = '';

        if (!empty($row['manager_name'])) {
            $row['reporting_to_label'] = $row['manager_role']
                ? sprintf('%s (%s)', $row['manager_name'], $row['manager_role'])
                : $row['manager_name'];
        }

        return $row;
    }

    public static function preferences(string $userId): array
    {
        $row = db_fetch_one("SELECT * FROM user_preferences WHERE user_id = ?", [$userId]);

        if (!$row) {
            db_execute("INSERT INTO user_preferences (user_id) VALUES (?)", [$userId]);
            $row = db_fetch_one("SELECT * FROM user_preferences WHERE user_id = ?", [$userId]);
        }

        return $row;
    }
}
