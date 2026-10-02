<?php

/** Server-side Meta Cloud API adapter. Tokens are exclusively environment variables. */
class WhatsAppService
{
    public static function setting(string $key, string $default = ''): string
    {
        $row = db_fetch_one("SELECT setting_value FROM website_settings WHERE setting_key = ? AND group_name = 'general'", [$key]);
        return (string) ((($row['setting_value'] ?? '') !== '' ? $row['setting_value'] : env(strtoupper($key), $default)));
    }

    public static function enabled(): bool
    {
        return self::setting('wa_enabled', '0') === '1';
    }

    public static function normalizeMobile(string $mobile): ?string
    {
        $mobile = preg_replace('/[\s()+-]+/', '', $mobile);
        if (preg_match('/^[5-9]\d{9}$/', $mobile)) $mobile = '91' . $mobile;
        return preg_match('/^[1-9]\d{9,14}$/', $mobile) ? $mobile : null;
    }

    public static function accepted(array $response, int $status): bool
    {
        return $status >= 200 && $status < 300 && !empty($response['messages'][0]['id']) && empty($response['error']);
    }

    public static function send(string $mobile, string $event, array $parameters, string $reference): array
    {
        $number = self::normalizeMobile($mobile);
        $template = self::setting('wa_template_' . $event);
        $token = (string) env('WHATSAPP_ACCESS_TOKEN', '');
        $phoneId = (string) env('WHATSAPP_PHONE_NUMBER_ID', '');
        $version = (string) env('WHATSAPP_GRAPH_VERSION', '');
        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        // Reserve before contacting Meta, including attempts blocked by missing configuration.
        db_execute('INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $reference, $event, $number ?? substr($mobile, 0, 20), $template, 'sending', $now, $now]);
        if (!self::enabled() || !$number || !preg_match('/^[a-z0-9_]+$/', $template)
            || !$token || !ctype_digit($phoneId) || !preg_match('/^v\d+\.\d+$/', $version)) {
            db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ? WHERE id = ?',
                ['failed', 'configuration_or_recipient_invalid', 'WhatsApp is disabled or its template, credentials, or recipient is invalid.', $id]);
            return ['success' => false, 'message' => 'WhatsApp is disabled or its configuration/recipient is incomplete.', 'id' => $id];
        }
        $components = [['type' => 'body', 'parameters' => array_map(static fn($value) => ['type' => 'text', 'text' => (string) $value], $parameters)]];
        if ($event === 'otp') $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string) $parameters[0]]]];
        $payload = ['messaging_product' => 'whatsapp', 'to' => $number, 'type' => 'template', 'template' => [
            'name' => $template, 'language' => ['code' => self::setting('wa_language', 'en_US')], 'components' => $components,
        ]];
        try {
            [$response, $status] = static::request($version, $phoneId, $token, $payload);
        } catch (Throwable $e) {
            $response = []; $status = 0;
            error_log('[WhatsApp] Transport failed for message ' . $id);
        }
        $ok = self::accepted($response, $status);
        $error = $ok ? null : (string) ($response['error']['code'] ?? 'transport_or_response');
        db_execute('UPDATE whatsapp_messages SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ?',
            [$response['messages'][0]['id'] ?? null, $ok ? 'accepted' : 'failed', $error, $ok ? null : 'Meta rejected the request or could not be reached (HTTP ' . $status . ').', gmdate('Y-m-d\TH:i:s\Z'), $id]);
        return ['success' => $ok, 'message' => $ok ? 'WhatsApp message accepted for delivery.' : 'WhatsApp sending failed.', 'id' => $id];
    }

    protected static function request(string $version, string $phoneId, string $token, array $payload): array
    {
        $response = []; $status = 0;
        if (function_exists('curl_init')) {
            $curl = curl_init("https://graph.facebook.com/{$version}/{$phoneId}/messages");
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload)]);
            $raw = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $response = json_decode(is_string($raw) ? $raw : '', true) ?: [];
            curl_close($curl);
        }
        return [$response, $status];
    }

    public static function notifyLead(array $lead, string $event): array
    {
        $details = $lead['details'] ?? json_decode($lead['details_json'] ?? '{}', true) ?? [];
        if (!in_array($details['whatsapp_consent'] ?? false, [true, 1, '1'], true)) {
            return ['success' => false, 'message' => 'This customer has not opted in to WhatsApp updates.'];
        }
        if (!in_array($event, ['lead_received', 'lead_approved', 'lead_rejected'], true)) {
            return ['success' => false, 'message' => 'Unsupported notification.'];
        }
        // Atomic cooldown reservation prevents simultaneous admin/automatic sends.
        $lock = 'wa:' . $lead['id'] . ':' . $event;
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'whatsapp_locks', ?, '', ?) ON CONFLICT(group_name, setting_key) DO NOTHING", [Uuid::v4(), $lock, '1970-01-01T00:00:00Z']);
        if (!db_execute("UPDATE website_settings SET updated_at = ? WHERE group_name = 'whatsapp_locks' AND setting_key = ? AND updated_at < ?", [$now, $lock, $cutoff])) {
            return ['success' => false, 'message' => 'Please wait 60 seconds before retrying this notification.'];
        }
        $params = [$lead['name'] ?: 'Customer', ucwords(str_replace('_', ' ', $lead['type']))];
        if ($event === 'lead_approved') $params[] = ($lead['scheduled_at'] ?? '') ?: 'As arranged';
        return static::send((string) $lead['phone'], $event, $params, $lead['id']);
    }

    public static function validSignature(string $raw, string $signature): bool
    {
        $secret = (string) env('WHATSAPP_APP_SECRET', '');
        return $secret !== '' && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $signature);
    }

    public static function updateStatuses(array $payload): void
    {
        $rank = ['accepted' => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4];
        foreach ($payload['entry'] ?? [] as $entry) foreach ($entry['changes'] ?? [] as $change) foreach ($change['value']['statuses'] ?? [] as $status) {
            $next = $status['status'] ?? '';
            if (!in_array($next, ['sent', 'delivered', 'read', 'failed'], true)) continue;
            $row = db_fetch_one('SELECT id, status FROM whatsapp_messages WHERE provider_id = ?', [$status['id'] ?? '']);
            if (!$row || ($rank[$row['status']] ?? 0) > $rank[$next]) continue;
            db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
                [$next, isset($status['errors'][0]['code']) ? (string) $status['errors'][0]['code'] : null, $next === 'failed' ? 'Meta reported WhatsApp delivery failure.' : null, gmdate('Y-m-d\TH:i:s\Z'), $row['id'], $row['status']]);
        }
    }
}
