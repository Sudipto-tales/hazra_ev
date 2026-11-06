<?php

require_once __DIR__ . '/messaging/Registry.php';

/**
 * Records SMS metadata only; OTP plaintext exists only for the outgoing request.
 *
 * Credentials come from MessagingCredentialStore (database → environment), so
 * every SMS_* / MSG91_* / TWILIO_* environment variable keeps working until the
 * same field is saved in the admin panel.
 */
class SmsDeliveryService
{
    public const PROVIDERS = ['msg91', 'twilio', 'fast2sms', 'log'];

    /** One field of one SMS provider, by its registry key. */
    private static function credential(string $provider, string $field): string
    {
        return MessagingRegistry::value('sms.' . $provider, $field);
    }

    public static function callbackUrl(string $id): string
    {
        $url = self::credential('twilio', 'callback_url');
        if (!str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, '#')) return '';
        return $url . (str_contains($url, '?') ? '&' : '?') . 'message_id=' . rawurlencode($id);
    }

    /** @param array $context chain bookkeeping from MessagingRouter; empty for direct callers. */
    public static function sendOtp(string $provider, string $mobile, string $code, string $purpose, string $reference, array $context = []): array
    {
        $id = Uuid::v4(); $now = gmdate('Y-m-d\TH:i:s\Z');
        $provider = in_array($provider, self::PROVIDERS, true) ? $provider : 'unsupported';
        db_execute('INSERT INTO sms_messages (id, reference_id, event_name, mobile, provider, status, attempt_group, chain_position, is_test, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $reference, $purpose, '91' . $mobile, $provider, 'sending',
                $context['attempt_group'] ?? null, (int) ($context['chain_position'] ?? 0), !empty($context['is_test']) ? 1 : 0, $now, $now]);
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
        if ($provider === 'fast2sms') {
            return static::dispatchFast2Sms($mobile, $code);
        }
        if ($provider === 'msg91') {
            $key = self::credential('msg91', 'auth_key'); $template = self::credential('msg91', 'template_id');
            if (!$key || !$template) return ['success' => false, 'error_code' => 'configuration_missing', 'error_detail' => 'MSG91 authentication key or template ID is missing.'];
            $url = 'https://control.msg91.com/api/v5/otp?' . http_build_query(['template_id' => $template, 'mobile' => '91' . $mobile, 'otp' => $code, 'CRQID' => $id]);
            [$response, $http] = static::request($url, ['CRQID' => $id], ['authkey: ' . $key, 'Content-Type: application/json']);
            $ok = $http >= 200 && $http < 300 && ($response['type'] ?? '') === 'success';
            return ['success' => $ok, 'provider_id' => $ok ? (string) ($response['request_id'] ?? $response['requestId'] ?? $response['message'] ?? '') : null,
                'error_code' => $ok ? null : 'msg91_rejected', 'error_detail' => $ok ? null : "MSG91 rejected the request or could not be reached (HTTP {$http})."];
        }
        if ($provider === 'twilio') {
            $sid = self::credential('twilio', 'account_sid'); $token = self::credential('twilio', 'auth_token'); $from = self::credential('twilio', 'from');
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

    /**
     * Fast2SMS. Two routes, both OTP-shaped:
     *   otp — Fast2SMS supplies the wording ("Your OTP: ###"), no DLT template
     *         and no sender ID needed, which is why it is the default.
     *   dlt — your own approved template, requiring a registered sender ID and
     *         the message ID Fast2SMS assigned to it.
     *
     * Send-only: Fast2SMS does not publish the payload or headers of its
     * delivery-report webhook, and signs nothing, so there is no honest way to
     * authenticate one here yet. These sends stop at `accepted`.
     */
    protected static function dispatchFast2Sms(string $mobile, string $code): array
    {
        $key = self::credential('fast2sms', 'api_key');
        $route = self::credential('fast2sms', 'route') ?: 'otp';
        if ($key === '' || !in_array($route, ['otp', 'dlt'], true)) {
            return ['success' => false, 'error_code' => 'configuration_missing', 'error_detail' => 'The Fast2SMS API key or route is missing or invalid.'];
        }

        $data = ['route' => $route, 'numbers' => $mobile, 'variables_values' => $code];
        if ($route === 'dlt') {
            $sender = self::credential('fast2sms', 'sender_id');
            $messageId = self::credential('fast2sms', 'message_id');
            if ($sender === '' || $messageId === '') {
                return ['success' => false, 'error_code' => 'configuration_missing', 'error_detail' => 'The Fast2SMS DLT route needs both a registered sender ID and an approved message ID.'];
            }
            $data['sender_id'] = $sender;
            $data['message'] = $messageId;
            $data['flash'] = '0';
        }

        [$response, $http] = static::request('https://www.fast2sms.com/dev/bulkV2', $data, ['Authorization: ' . $key]);
        $ok = $http >= 200 && $http < 300 && in_array($response['return'] ?? null, [true, 'true', 1, '1'], true);

        return [
            'success' => $ok,
            'provider_id' => $ok ? (string) ($response['request_id'] ?? $response['requestId'] ?? '') : null,
            'error_code' => $ok ? null : 'fast2sms_rejected',
            'error_detail' => $ok ? null : trim('Fast2SMS rejected the request or could not be reached (HTTP ' . $http . '). ' . self::firstMessage($response)),
        ];
    }

    /** Fast2SMS returns its reason inside a `message` array; surface the first line. */
    private static function firstMessage(array $response): string
    {
        $message = $response['message'] ?? '';
        if (is_array($message)) {
            $message = (string) reset($message);
        }

        return substr(trim((string) $message), 0, 200);
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
        $token = self::credential('twilio', 'auth_token'); $url = self::callbackUrl($id);
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
        $expected = self::credential('msg91', 'webhook_token');
        return $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * Applies one provider callback. Returns the ids it moved so the webhook log
     * can record whether an authenticated call actually matched anything — an
     * unmatched callback is the usual cause of "the webhook is set up but
     * nothing updates", and it is invisible unless counted.
     *
     * @return list<string>
     */
    public static function updateStatus(string $provider, string $id, string $providerId, string $status, ?string $error = null, ?string $detail = null, string $mobile = ''): array
    {
        $rank = ['sending' => 0, 'accepted' => 1, 'sent' => 2, 'failed' => 3, 'delivered' => 4];
        if (!isset($rank[$status]) || $providerId === '') return [];
        $row = $id !== '' ? db_fetch_one('SELECT * FROM sms_messages WHERE id = ? AND provider = ?', [$id, $provider])
            : db_fetch_one('SELECT * FROM sms_messages WHERE provider = ? AND provider_id = ?', [$provider, $providerId]);
        if (!$row || ($row['provider_id'] && $row['provider_id'] !== $providerId) || ($mobile !== '' && $mobile !== $row['mobile'])
            || !isset($rank[$row['status']]) || $rank[$row['status']] > $rank[$status]) return [];
        $moved = db_execute('UPDATE sms_messages SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
            [$providerId, $status, $status === 'failed' ? substr((string) $error, 0, 80) : null,
                $status === 'failed' ? substr((string) $detail, 0, 512) : null, gmdate('Y-m-d\TH:i:s\Z'), $row['id'], $row['status']]);

        return $moved ? [(string) $row['id']] : [];
    }
}
