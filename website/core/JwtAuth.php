<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

final class JwtAuth
{
    private static function secret(): string
    {
        return env('JWT_SECRET', 'change-me-in-production');
    }

    private static function ttl(): int
    {
        return (int) env('JWT_TTL', 3600);
    }

    public static function generate(array $payload): string
    {
        $now = time();

        return JWT::encode(
            payload: [
                'iat' => $now,
                'exp' => $now + self::ttl(),
                'sub' => $payload['id'] ?? null,
                ...$payload,
            ],
            key: self::secret(),
            alg: 'HS256',
        );
    }

    public static function authenticate(): array|false
    {
        $token = ApiRequest::bearerToken();

        if (!$token) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!empty($_SESSION['logged_in']) || !empty($_SESSION['admin_logged_in'])) {
                return [
                    'sub' => $_SESSION['user_id'] ?? 'usr-admin-01',
                    'role' => $_SESSION['user_role'] ?? 'admin',
                    'email' => $_SESSION['user_email'] ?? '',
                    'name' => $_SESSION['user_name'] ?? '',
                ];
            }
            return false;
        }

        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The framework's own "who is calling" helper, behind ApiController::user().
     *
     * The v1 API does not come through here — it uses Ctx::principal(), which
     * also joins the role profiles and resolves the org. This stayed pointed at
     * users_tbl, a table from the framework demo schema that the product
     * database has never had, so any caller would have taken a 1054 rather than
     * a null. Same rule as Ctx: a deactivated account does not resolve, or a
     * token issued before the deactivation would still open this door.
     */
    public static function user(): ?array
    {
        $payload = self::authenticate();

        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        return db_fetch_one(
            "SELECT id, org_id, role, name, email FROM users WHERE id = ? AND active = 1",
            [$payload['sub']],
        ) ?: null;
    }
}
