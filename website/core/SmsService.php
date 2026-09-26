<?php

/**
 * Service to handle SMS dispatch and OTP challenges.
 * Pluggable provider architecture (MSG91, Twilio, Log/Dev).
 */
class SmsService
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
    public static function sendOtp(string $mobile, string $purpose): array
    {
        $norm = self::normalizeMobile($mobile);
        if (!$norm) {
            return [
                'success' => false,
                'message' => 'Invalid 10-digit mobile number.',
                'cooldown' => 0,
            ];
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

        // 3. Generate 6-digit OTP
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + self::OTP_TTL_SECONDS);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        db_execute(
            "INSERT INTO otp_challenges (id, mobile, purpose, code_hash, expires_at, attempts, created_at, ip_address)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)",
            [$id, $norm, $purpose, $hash, $expiresAt, $now, $ip]
        );

        // 4. Send via configured provider
        $provider = env('SMS_PROVIDER', 'log');
        $sent = self::dispatchSms($provider, $norm, $code, $purpose);

        $res = [
            'success' => true,
            'message' => 'OTP sent successfully to ' . substr($norm, 0, 2) . '******' . substr($norm, -2),
            'cooldown' => self::COOLDOWN_SECONDS,
        ];

        // Include debug code if non-production or debug enabled
        if (defined('APP_DEBUG') && APP_DEBUG || env('APP_ENV') === 'local' || env('APP_ENV') === 'development' || $provider === 'log') {
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
                "UPDATE otp_challenges SET attempts = ? WHERE id = ?",
                [$attempts, $challenge['id']]
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

        db_execute(
            "UPDATE otp_challenges
                SET verified_at = ?, session_token = ?
              WHERE id = ?",
            [$verifiedAt, $sessionToken, $challenge['id']]
        );

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
    private static function dispatchSms(string $provider, string $mobile, string $code, string $purpose): bool
    {
        $message = "Your Hazra EV warranty verification code is {$code}. Valid for 10 minutes. Do not share this OTP.";

        if ($provider === 'msg91') {
            $authKey = env('MSG91_AUTH_KEY', '');
            $templateId = env('MSG91_TEMPLATE_ID', '');
            if (!$authKey) {
                error_log("[SmsService] MSG91_AUTH_KEY not set. Falling back to log.");
                return self::logSms($mobile, $code, $message);
            }

            // MSG91 API call
            $payload = [
                'template_id' => $templateId,
                'mobile'      => '91' . $mobile,
                'otp'         => $code,
            ];
            $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query($payload);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["authkey: {$authKey}"]);
            $response = curl_exec($ch);
            curl_close($ch);
            return true;
        }

        if ($provider === 'twilio') {
            $sid = env('TWILIO_SID', '');
            $token = env('TWILIO_TOKEN', '');
            $from = env('TWILIO_FROM', '');
            if (!$sid || !$token || !$from) {
                error_log("[SmsService] Twilio credentials not set. Falling back to log.");
                return self::logSms($mobile, $code, $message);
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            $data = [
                'From' => $from,
                'To'   => '+91' . $mobile,
                'Body' => $message,
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $res = curl_exec($ch);
            curl_close($ch);
            return true;
        }

        // Default: Log provider
        return self::logSms($mobile, $code, $message);
    }

    private static function logSms(string $mobile, string $code, string $message): bool
    {
        error_log("[SmsService DEV/LOG] Sent SMS to {$mobile}: {$message}");
        return true;
    }
}
