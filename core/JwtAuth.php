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

    public static function user(): ?array
    {
        $payload = self::authenticate();

        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        return db_fetch_one(
            "SELECT id, name, email, role FROM users_tbl WHERE id = ?",
            [$payload['sub']],
        ) ?: null;
    }
}
