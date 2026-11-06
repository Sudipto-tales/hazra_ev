<?php
require_once __DIR__ . "/WhatsAppService.php";
require_once __DIR__ . '/SmsDeliveryService.php';
require_once __DIR__ . '/Secrets.php';
require_once __DIR__ . '/messaging/Router.php';

/**
 * Shared OTP challenges, delivered down the chain configured for the `otp`
 * event in Admin → Messaging (email, WhatsApp and SMS providers, in the
 * operator's own order) rather than the old hardcoded WhatsApp-then-SMS rule.
 *
 * What did not move: the 60-second cooldown, the five-per-hour and
 * twenty-per-IP caps, one code per challenge however many providers are tried,
 * and the invalidation of earlier unverified codes. Those are request-level
 * rules, so a three-step fallback still costs the customer one request.
 */
class OtpService
{
    public const COOLDOWN_SECONDS = 60;
    public const MAX_PER_HOUR = 5;
    public const OTP_TTL_SECONDS = 600; // 10 minutes

    /**
     * Normalize a mobile number to 10 digits.
     */
    public static function normalizeMobile(string $mobile): ?string
    {
        $clean = preg_replace('/\D+/', '', $mobile);
        if (strlen($clean) === 12 && str_starts_with($clean, '91')) {
            $clean = substr($clean, 2);
        } elseif (strlen($clean) === 11 && str_starts_with($clean, '0')) {
            $clean = substr($clean, 1);
        }

        if (strlen($clean) === 10 && preg_match('/^[5-9]\d{9}$/', $clean)) {
            return $clean;
        }

        return null;
    }

    /**
     * Send an OTP code to a mobile number.
     *
     * @return array{success: bool, message: string, cooldown: int, debug_code?: string}
     */
    public static function sendOtp(string $mobile, string $purpose, string $channel = "auto", string $email = ''): array
    {
        $norm = self::normalizeMobile($mobile);
        if (!$norm) {
            return [
                'success' => false,
                'message' => 'Invalid 10-digit mobile number.',
                'cooldown' => 0,
            ];
        }

        if (!in_array($purpose, ['warranty_free', 'warranty_paid'], true) || !in_array($channel, ['auto', 'sms'], true)) {
            return ['success' => false, 'message' => 'Invalid OTP request.', 'cooldown' => 0];
        }
        // 1. Check cooldown (last 60s)
        $recent = db_fetch_one(
            "SELECT created_at FROM otp_challenges
              WHERE mobile = ? AND purpose = ?
              ORDER BY created_at DESC LIMIT 1",
            [$norm, $purpose]
        );

        if ($recent) {
            $tsStr = $recent['created_at'];
            $lastTime = strtotime(str_ends_with($tsStr, 'Z') ? $tsStr : $tsStr . ' UTC');
            $diff = time() - $lastTime;
            if ($diff < self::COOLDOWN_SECONDS && $diff >= 0) {
                $wait = self::COOLDOWN_SECONDS - $diff;
                return [
                    'success' => false,
                    'message' => "Please wait {$wait}s before requesting a new OTP.",
                    'cooldown' => $wait,
                ];
            }
        }

        // 2. Check hourly limit (max 5 per hour)
        $oneHourAgo = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
        $hourly = db_fetch_one(
            "SELECT COUNT(*) AS c FROM otp_challenges
              WHERE mobile = ? AND created_at >= ?",
            [$norm, $oneHourAgo]
        );

        if (($hourly['c'] ?? 0) >= self::MAX_PER_HOUR) {
            return [
                'success' => false,
                'message' => 'Maximum OTP attempts reached for this hour. Please try again later.',
                'cooldown' => 3600,
            ];
        }

        // Limit automated abuse across numbers from the same address.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $ipCount = db_fetch_one('SELECT COUNT(*) AS c FROM otp_challenges WHERE ip_address = ? AND created_at >= ?', [$ip, $oneHourAgo]);
        if (($ipCount['c'] ?? 0) >= 20) return ['success' => false, 'message' => 'Too many requests. Please try later.', 'cooldown' => 3600];
        if (!WhatsAppService::reserve('wa:otp:' . $norm)) {
            return ['success' => false, 'message' => 'Please wait 60 seconds before requesting another OTP.', 'cooldown' => 60];
        }
        // 3. Generate 6-digit OTP
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + self::OTP_TTL_SECONDS);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';

        db_execute(
            "INSERT INTO otp_challenges (id, mobile, purpose, code_hash, expires_at, attempts, created_at, ip_address, code_cipher, code_cipher_expires_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)",
            [$id, $norm, $purpose, $hash, $expiresAt, $now, $ip, self::cipher($code), $expiresAt]
        );

        // Invalidate earlier unverified codes; channel switching never leaves two valid codes.
        db_execute('UPDATE otp_challenges SET expires_at = ?, code_cipher = NULL, code_cipher_expires_at = NULL WHERE mobile = ? AND purpose = ? AND verified_at IS NULL AND id <> ?', [$now, $norm, $purpose, $id]);
        self::purgeCiphers();

        // 4. Send down the chain configured for the `otp` event. `sms` from the
        // form means "the customer says WhatsApp never arrived", so it pins the
        // chain to SMS steps rather than reordering anything.
        $result = MessagingRouter::dispatch('otp', [
            'reference' => $id,
            'mobile' => $norm,
            'email' => trim($email),
            'params' => [$code],
            'code' => $code,
            'payload' => ['purpose' => $purpose],
            'only' => $channel === 'sms' ? 'sms' : null,
        ]);

        if (!$result['success']) {
            db_execute('UPDATE otp_challenges SET expires_at = ?, code_cipher = NULL, code_cipher_expires_at = NULL WHERE id = ?', [$now, $id]);
            return ['success' => false, 'message' => 'Unable to send your OTP. Please try again after 60 seconds.', 'cooldown' => 60];
        }

        $res = [
            'success' => true,
            'channel' => $result['channel'],
            'message' => 'OTP sent via ' . self::channelLabel($result['channel']) . ' to ' . self::maskRecipient($result['channel'], $norm, $email),
            'cooldown' => self::COOLDOWN_SECONDS,
        ];

        // The development log sends nothing, so the code has to come back in the
        // response for a local test to be possible at all. Production never
        // reaches this: sms.log is refused outside local/development/testing.
        if (in_array(env('APP_ENV'), ['local', 'development', 'testing'], true) && self::usedDevelopmentLog($result['attempts'])) {
            $res['debug_code'] = $code;
        }

        return $res;
    }

    private static function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'email' => 'email',
            default => 'SMS',
        };
    }

    private static function maskRecipient(?string $channel, string $mobile, string $email): string
    {
        if ($channel === 'email' && str_contains($email, '@')) {
            [$user, $domain] = explode('@', trim($email), 2);
            return substr($user, 0, 1) . '***@' . $domain;
        }

        return substr($mobile, 0, 2) . '******' . substr($mobile, -2);
    }

    private static function usedDevelopmentLog(array $attempts): bool
    {
        foreach ($attempts as $attempt) {
            if (!empty($attempt['success']) && ($attempt['provider'] ?? '') === 'sms.log') {
                return true;
            }
        }

        return false;
    }

    /**
     * The code, encrypted for the admin reveal — null when APP_KEY is absent, in
     * which case the eye simply has nothing to show. Verification never depends
     * on this: password_verify against code_hash remains the only check.
     */
    private static function cipher(string $code): ?string
    {
        if (!Secrets::available() || MessagingRouter::setting('otp_reveal_enabled', '0') !== '1') {
            return null;
        }

        try {
            return Secrets::encrypt($code, Secrets::PURPOSE_OTP);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Drops ciphers that are past their window, on every send, so a revealable
     * code never outlives its challenge even if no cron runs. Cheap: the
     * column is indexed and the predicate is a single range scan.
     */
    public static function purgeCiphers(): int
    {
        return (int) db_execute(
            'UPDATE otp_challenges SET code_cipher = NULL, code_cipher_expires_at = NULL
              WHERE code_cipher IS NOT NULL
                AND (code_cipher_expires_at IS NULL OR code_cipher_expires_at <= ? OR verified_at IS NOT NULL OR consumed_at IS NOT NULL)',
            [gmdate('Y-m-d\TH:i:s\Z')]
        );
    }

    /**
     * Verify an OTP challenge.
     *
     * @return array{success: bool, message: string, session_token?: string, expires_at?: string}
     */
    public static function verifyOtp(string $mobile, string $purpose, string $code): array
    {
        if (!in_array($purpose, ['warranty_free', 'warranty_paid'], true) || !preg_match('/^\d{6}$/', $code)) {
            return ['success' => false, 'message' => 'Enter a valid six-digit OTP.'];
        }
        $norm = self::normalizeMobile($mobile);
        if (!$norm) {
            return ['success' => false, 'message' => 'Invalid mobile number.'];
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $challenge = db_fetch_one(
            "SELECT * FROM otp_challenges
              WHERE mobile = ? AND purpose = ? AND verified_at IS NULL AND expires_at > ?
              ORDER BY created_at DESC LIMIT 1",
            [$norm, $purpose, $now]
        );

        if (!$challenge) {
            return [
                'success' => false,
                'message' => 'OTP has expired or is invalid. Please request a new code.',
            ];
        }

        if ((int) $challenge['attempts'] >= 5) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. This OTP has expired. Please request a new code.',
            ];
        }

        if (!password_verify($code, $challenge['code_hash'])) {
            $attempts = (int) $challenge['attempts'] + 1;
            db_execute(
                "UPDATE otp_challenges SET attempts = attempts + 1 WHERE id = ? AND attempts < 5 AND verified_at IS NULL",
                [$challenge['id']]
            );
            $remaining = 5 - $attempts;
            $msg = $remaining > 0
                ? "Incorrect OTP. {$remaining} attempts remaining."
                : "Incorrect OTP. Maximum attempts reached. Please request a new code.";
            return ['success' => false, 'message' => $msg];
        }

        // OTP Verified successfully! Issue session token valid for 60 minutes
        $sessionToken = bin2hex(random_bytes(32));
        $verifiedAt = $now;
        $tokenExpiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

        $verified = db_execute(
            "UPDATE otp_challenges
                SET verified_at = ?, session_token = ?, code_cipher = NULL, code_cipher_expires_at = NULL
              WHERE id = ? AND verified_at IS NULL AND attempts < 5 AND expires_at > ?",
            [$verifiedAt, $sessionToken, $challenge['id'], $now]
        );

        if (!$verified) return ['success' => false, 'message' => 'OTP is no longer valid.'];
        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'session_token' => $sessionToken,
            'expires_at' => $tokenExpiresAt,
        ];
    }

    /**
     * Reveals a live code to an admin, or explains why it cannot.
     *
     * Deliberately narrow: the challenge must still be open, within its reveal
     * window, and have a cipher at all. A verified, consumed, expired or
     * superseded challenge has already had its cipher wiped, so this cannot
     * resurrect one — which is what keeps a stolen admin session from mining
     * historical codes.
     *
     * @return array{code: ?string, reason: string}
     */
    public static function revealCode(string $challengeId): array
    {
        if (MessagingRouter::setting('otp_reveal_enabled', '0') !== '1') {
            return ['code' => null, 'reason' => 'Revealing codes is switched off in Admin → Messaging.'];
        }

        $row = db_fetch_one('SELECT * FROM otp_challenges WHERE id = ?', [$challengeId]);
        if (!$row) {
            return ['code' => null, 'reason' => 'This challenge no longer exists.'];
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        if (!empty($row['verified_at']) || !empty($row['consumed_at'])) {
            return ['code' => null, 'reason' => 'This code has already been used, so it is no longer stored.'];
        }
        if (($row['expires_at'] ?? '') <= $now || ($row['code_cipher_expires_at'] ?? '') <= $now) {
            return ['code' => null, 'reason' => 'This code has expired. Only a live code can be shown.'];
        }
        if (empty($row['code_cipher'])) {
            return ['code' => null, 'reason' => 'This code was not stored for reveal — it was sent before the setting was switched on.'];
        }

        $code = Secrets::decrypt($row['code_cipher'], Secrets::PURPOSE_OTP);

        return $code === null
            ? ['code' => null, 'reason' => 'The stored code could not be read. APP_KEY may have changed since it was sent.']
            : ['code' => $code, 'reason' => ''];
    }
}
