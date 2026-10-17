<?php

/** Records SMS metadata only; OTP plaintext exists only for the outgoing request. */
class SmsDeliveryService
{
    public static function callbackUrl(string $id): string
    {
        $url = (string) env('SMS_TWILIO_CALLBACK_URL', '');
        if (!str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, '#')) return '';
        return $url . (str_contains($url, '?') ? '&' : '?') . 'message_id=' . rawurlencode($id);
    }

    public static function sendOtp(string $provider, string $mobile, string $code, string $purpose, string $reference): array
    {
        $id = Uuid::v4(); $now = gmdate('Y-m-d\TH:i:s\Z');
        $provider = in_array($provider, ['msg91', 'twilio', 'log'], true) ? $provider : 'unsupported';
        db_execute('INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $reference, $purpose, '91' . $mobile, $provider, 'sending', $now, $now]);
        try {
            $result = static::dispatch($provider, $mobile, $code, $id);
        } catch (Throwable $e) {
            $result = ['success' => false, 'error_code' => 'transport_error', 'error_detail' => 'The SMS provider could not be reached.'];
            error_log('[SMS] Dispatch failed for message ' . $id);
        }
        $status = $result['success'] ? ($result['status'] ?? 'accepted') : 'failed';
        // An early delivery callback must win over the initial HTTP response.
        db_execute("UPDATE sms_messages SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = 'sending'",
            [$result['provider_id'] ?? null, $status, $result['error_code'] ?? null, $result['error_detail'] ?? null, gmdate('Y-m-d\TH:i:s\Z'), $id]);
        return ['success' => (bool) $result['success'], 'id' => $id, 'status' => $status];
    }

    protected static function dispatch(string $provider, string $mobile, string $code, string $id): array
    {
        if ($provider === 'log') {
            if (!in_array(env('APP_ENV'), ['local', 'development', 'testing'], true)) {
                return ['success' => false, 'error_code' => 'production_log_disabled', 'error_detail' => 'Configure a real SMS provider for production.'];
            }
            error_log('[SMS DEV] OTP for ' . $mobile . ': ' . $code);
            return ['success' => true, 'status' => 'logged'];
        }
        if ($provider === 'msg91') {
            $key = (string) env('MSG91_AUTH_KEY', ''); $template = (string) env('MSG91_TEMPLATE_ID', '');
            if (!$key || !$template) return ['success' => false, 'error_code' => 'configuration_missing', 'error_detail' => 'MSG91 authentication key or template ID is missing.'];
            $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query(['template_id' => $template, 'mobile' => '91' . $mobile, 'otp' => $code, 'CRQID' => $id]);
            [$response, $http] = static::request($url, ['CRQID' => $id], ['authkey: ' . $key, 'Content-Type: application/json']);
            $ok = $http >= 200 && $http < 300 && ($response['type'] ?? '') === 'success';
            return ['success' => $ok, 'provider_id' => $ok ? (string) ($response['request_id'] ?? $response['requestId'] ?? $response['message'] ?? '') : null,
                'error_code' => $ok ? null : 'msg91_rejected', 'error_detail' => $ok ? null : "MSG91 rejected the request or could not be reached (HTTP {$http})."];
        }
        if ($provider === 'twilio') {
            $sid = (string) env('TWILIO_SID', ''); $token = (string) env('TWILIO_TOKEN', ''); $from = (string) env('TWILIO_FROM', '');
            if (!$sid || !$token || !$from || !preg_match('/^AC[a-fA-F0-9]{32}$/', $sid)) {
                return ['success' => false, 'error_code' => 'configuration_missing', 'error_detail' => 'Twilio account SID, token, or sender is missing or invalid.'];
            }
            $data = ['From' => $from, 'To' => '+91' . $mobile, 'Body' => "Your Hazra EV warranty verification code is {$code}. Valid for 10 minutes. Do not share this OTP."];
            if ($callback = self::callbackUrl($id)) $data['StatusCallback'] = $callback;
            [$response, $http] = static::request("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $data, [], $sid . ':' . $token);
            $ok = $http >= 200 && $http < 300 && !empty($response['sid']) && empty($response['error_code']) && !in_array($response['status'] ?? '', ['failed', 'undelivered'], true);
            return ['success' => $ok, 'provider_id' => $response['sid'] ?? null,
                'error_code' => $ok ? null : (string) ($response['code'] ?? $response['error_code'] ?? 'twilio_rejected'),
                'error_detail' => $ok ? null : "Twilio rejected the request or could not be reached (HTTP {$http})."];
        }
        return ['success' => false, 'error_code' => 'unsupported_provider', 'error_detail' => 'The selected SMS provider is unsupported.'];
    }

    protected static function request(string $url, array $data, array $headers, string $credentials = ''): array
    {
        if (!function_exists('curl_init')) return [[], 0];
        $curl = curl_init($url);
        $json = in_array('Content-Type: application/json', $headers, true);
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $json ? $headers : array_merge($headers, ['Content-Type: application/x-www-form-urlencoded']),
            CURLOPT_POSTFIELDS => $json ? json_encode($data) : http_build_query($data)]);
        if ($credentials !== '') curl_setopt($curl, CURLOPT_USERPWD, $credentials);
        $raw = curl_exec($curl); $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [json_decode(is_string($raw) ? $raw : '', true) ?: [], $http];
    }

    public static function validTwilioSignature(string $id, array $parameters, string $signature): bool
    {
        $token = (string) env('TWILIO_TOKEN', ''); $url = self::callbackUrl($id);
        if ($token === '' || $url === '') return false;
        ksort($parameters, SORT_STRING);
        foreach ($parameters as $key => $value) {
            if (!is_string($value)) return false;
            $url .= $key . $value;
        }
        return hash_equals(base64_encode(hash_hmac('sha1', $url, $token, true)), $signature);
    }

    public static function validMsg91Token(string $token): bool
    {
        $expected = (string) env('SMS_MSG91_WEBHOOK_TOKEN', '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    public static function updateStatus(string $provider, string $id, string $providerId, string $status, ?string $error = null, ?string $detail = null, string $mobile = ''): void
    {
        $rank = ['sending' => 0, 'accepted' => 1, 'sent' => 2, 'failed' => 3, 'delivered' => 4];
        if (!isset($rank[$status]) || $providerId === '') return;
        $row = $id !== '' ? db_fetch_one('SELECT * FROM sms_messages WHERE id = ? AND provider = ?', [$id, $provider])
            : db_fetch_one('SELECT * FROM sms_messages WHERE provider = ? AND provider_id = ?', [$provider, $providerId]);
        if (!$row || ($row['provider_id'] && $row['provider_id'] !== $providerId) || ($mobile !== '' && $mobile !== $row['mobile'])
            || !isset($rank[$row['status']]) || $rank[$row['status']] > $rank[$status]) return;
        db_execute('UPDATE sms_messages SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
            [$providerId, $status, $status === 'failed' ? substr((string) $error, 0, 80) : null,
                $status === 'failed' ? substr((string) $detail, 0, 512) : null, gmdate('Y-m-d\TH:i:s\Z'), $row['id'], $row['status']]);
    }
}
