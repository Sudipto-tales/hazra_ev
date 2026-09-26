<?php

require_once __DIR__ . '/../support/V1Controller.php';
require_once __DIR__ . '/../support/Users.php';

/**
 * One login route for both shells. The role comes back in `principal.type`;
 * the client does not pick it, and today's role-select screen is a UI
 * affordance only.
 */
final class AuthController extends V1Controller
{
    private const REFRESH_DAYS = 30;

    public function login(): never
    {
        $data = $this->check([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = Users::byEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            Envelope::fail('INVALID_CREDENTIALS', 'Email or password is incorrect', 401);
        }

        if (!(int) $user['active']) {
            Envelope::forbidden('This account is deactivated');
        }

        $device = $this->registerDevice($user['id'], $this->input('device', []));

        Envelope::ok($this->tokenPayload($user, $device));
    }

    public function refresh(): never
    {
        $token = (string) ($this->input('refreshToken') ?? '');

        if ($token === '') {
            Envelope::invalid('refreshToken is required', 'refreshToken');
        }

        $row = db_fetch_one(
            "SELECT * FROM refresh_tokens WHERE token_hash = ?",
            [hash('sha256', $token)],
        );

        if (!$row || $row['revoked_at'] !== null || $row['expires_at'] < Wire::now()) {
            Envelope::fail('REFRESH_TOKEN_INVALID', 'Sign in again', 401);
        }

        $user = Users::byId($row['user_id']);

        if (!$user || !(int) $user['active']) {
            Envelope::forbidden('This account is deactivated');
        }

        // Rotate: a refresh token is single-use.
        db_execute("UPDATE refresh_tokens SET revoked_at = ? WHERE id = ?", [Wire::now(), $row['id']]);

        Envelope::ok($this->tokenPayload($user, $row['device_id']));
    }

    /** Revokes the device's push token too, so a signed-out phone stops buzzing. */
    public function logout(): never
    {
        $principal = Ctx::principal();
        $token = (string) ($this->input('refreshToken') ?? '');

        if ($token !== '') {
            db_execute(
                "UPDATE refresh_tokens SET revoked_at = ? WHERE token_hash = ? AND user_id = ?",
                [Wire::now(), hash('sha256', $token), $principal['id']],
            );
        } else {
            db_execute(
                "UPDATE refresh_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL",
                [Wire::now(), $principal['id']],
            );
        }

        $deviceId = $this->input('deviceId');

        if ($deviceId) {
            db_execute(
                "UPDATE devices SET push_token = NULL WHERE id = ? AND user_id = ?",
                [$deviceId, $principal['id']],
            );
        }

        Envelope::ok(['signedOut' => true]);
    }

    /** PUT /me/device — re-register a rotated push token. */
    public function device(): never
    {
        $principal = Ctx::principal();
        $deviceId = $this->registerDevice($principal['id'], ApiRequest::body());

        if ($deviceId === null) {
            Envelope::invalid('device.platform is required', 'platform');
        }

        Envelope::ok(['deviceId' => $deviceId]);
    }

    // ------------------------------------------------------------- internals

    private function tokenPayload(array $user, ?string $deviceId): array
    {
        $accessToken = JwtAuth::generate([
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'org'   => $user['org_id'],
        ]);

        return [
            'accessToken'  => $accessToken,
            'refreshToken' => $this->issueRefreshToken($user['id'], $deviceId),
            'expiresIn'    => (int) env('JWT_TTL', 3600),
            'tokenType'    => 'Bearer',
            'principal'    => Present::principal($user),
        ];
    }

    private function issueRefreshToken(string $userId, ?string $deviceId): string
    {
        $token = bin2hex(random_bytes(32));

        db_execute(
            "INSERT INTO refresh_tokens (id, user_id, token_hash, device_id, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                Uuid::v4(), $userId, hash('sha256', $token), $deviceId,
                gmdate(Wire::TS, time() + self::REFRESH_DAYS * 86400), Wire::now(),
            ],
        );

        return $token;
    }

    private function registerDevice(string $userId, mixed $device): ?string
    {
        if (!is_array($device) || empty($device['platform'])) {
            return null;
        }

        $platform  = (string) $device['platform'];
        $pushToken = $device['pushToken'] ?? null;

        // `push_token IS ?` parses on SQLite but is a syntax error on MySQL —
        // `IS` there only takes NULL/TRUE/FALSE, so every login carrying a
        // device.platform 500ed against the server. Branch instead of relying
        // on one engine's tolerance.
        $existing = $pushToken === null
            ? db_fetch_one(
                "SELECT id FROM devices
                  WHERE user_id = ? AND platform = ? AND (id = ? OR push_token IS NULL)",
                [$userId, $platform, $device['id'] ?? ''],
            )
            : db_fetch_one(
                "SELECT id FROM devices
                  WHERE user_id = ? AND platform = ? AND (id = ? OR push_token = ?)",
                [$userId, $platform, $device['id'] ?? '', $pushToken],
            );

        $now = Wire::now();

        if ($existing) {
            db_execute(
                "UPDATE devices SET app_version = ?, push_token = ?, last_seen_at = ? WHERE id = ?",
                [(string) ($device['appVersion'] ?? ''), $pushToken, $now, $existing['id']],
            );

            return $existing['id'];
        }

        $id = Uuid::isValid($device['id'] ?? null) ? $device['id'] : Uuid::v4();

        db_execute(
            "INSERT INTO devices (id, user_id, platform, app_version, push_token, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $userId, $platform, (string) ($device['appVersion'] ?? ''), $pushToken, $now],
        );

        return $id;
    }
}
