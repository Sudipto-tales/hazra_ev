<?php
require_once __DIR__ . "/WhatsAppService.php";
require_once __DIR__ . '/SmsDeliveryService.php';

/**
 * Shared OTP challenges with WhatsApp-first delivery and SMS fallback.
 * SMS providers: MSG91, Twilio, and development-only log.
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
    public static function sendOtp(string $mobile, string $purpose, string $channel = "auto"): array
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
        $lock = 'wa:otp:' . $norm;
        $lockNow = gmdate('Y-m-d\TH:i:s\Z');
        db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'whatsapp_locks', ?, '', ?) ON CONFLICT(group_name, setting_key) DO NOTHING", [Uuid::v4(), $lock, '1970-01-01T00:00:00Z']);
        if (!db_execute("UPDATE website_settings SET updated_at = ? WHERE group_name = 'whatsapp_locks' AND setting_key = ? AND updated_at <= ?", [$lockNow, $lock, gmdate('Y-m-d\TH:i:s\Z', time() - 60)])) {
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
            "INSERT INTO otp_challenges (id, mobile, purpose, code_hash, expires_at, attempts, created_at, ip_address)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)",
            [$id, $norm, $purpose, $hash, $expiresAt, $now, $ip]
        );

        // Invalidate earlier unverified codes; channel switching never leaves two valid codes.
        db_execute('UPDATE otp_challenges SET expires_at = ? WHERE mobile = ? AND purpose = ? AND verified_at IS NULL AND id <> ?', [$now, $norm, $purpose, $id]);
        // 4. Send via configured provider
        $provider = env('SMS_PROVIDER', 'log');
        $actualChannel = 'sms';
        $sent = false;
        if ($channel === 'auto' && WhatsAppService::enabled()) {
            try {
                $sent = static::sendWhatsAppOtp($norm, $code, $id)['success'];
                if ($sent) $actualChannel = 'whatsapp';
            } catch (Throwable $e) { error_log('[OTP] WhatsApp dispatch failed'); }
        }
        if (!$sent) $sent = static::sendSmsOtp($provider, $norm, $code, $purpose, $id);
        if (!$sent) {
            db_execute('UPDATE otp_challenges SET expires_at = ? WHERE id = ?', [$now, $id]);
            return ['success' => false, 'message' => 'Unable to send your OTP. Please try again after 60 seconds.', 'cooldown' => 60];
        }

        $res = [
            'success' => true,
            'channel' => $actualChannel,
            'message' => 'OTP sent via ' . ($actualChannel === 'whatsapp' ? 'WhatsApp' : 'SMS') . ' to ' . substr($norm, 0, 2) . '******' . substr($norm, -2),
            'cooldown' => self::COOLDOWN_SECONDS,
        ];

        // Include debug code if non-production or debug enabled
        if (in_array(env('APP_ENV'), ['local', 'development', 'testing'], true) && $provider === 'log' && $actualChannel === 'sms') {
            $res['debug_code'] = $code;
        }

        return $res;
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
                SET verified_at = ?, session_token = ?
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
     * Dispatch SMS through selected gateway provider.
     */
    protected static function sendWhatsAppOtp(string $mobile, string $code, string $id): array
    {
        return WhatsAppService::send($mobile, 'otp', [$code], $id);
    }

    protected static function sendSmsOtp(string $provider, string $mobile, string $code, string $purpose, string $reference): bool
    {
        return SmsDeliveryService::sendOtp($provider, $mobile, $code, $purpose, $reference)['success'];
    }
}
