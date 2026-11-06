<?php

require_once __DIR__ . '/messaging/Registry.php';

/**
 * Server-side Meta Cloud API adapter.
 *
 * Credentials and template names now come from MessagingCredentialStore, which
 * resolves database → website_settings (the old wa_* rows) → environment, so
 * nothing that worked from env or the old Settings tab stops working, and the
 * admin panel can take over a field by saving it.
 */
class WhatsAppService
{
    public static function setting(string $key, string $default = ''): string
    {
        $row = db_fetch_one("SELECT setting_value FROM website_settings WHERE setting_key = ? AND group_name = 'general'", [$key]);
        return (string) ((($row['setting_value'] ?? '') !== '' ? $row['setting_value'] : env(strtoupper($key), $default)));
    }

    /** One of this provider's configured fields, by registry key. */
    public static function credential(string $field): string
    {
        return MessagingRegistry::value('whatsapp.meta', $field);
    }

    /**
     * Atomically reserves a 60-second slot for one logical send.
     *
     * The same guard was written out three times — OTP requests, lead
     * notifications and admin retries — so it lives here once. The UPDATE's
     * WHERE clause is the lock: only one caller can move a row whose
     * updated_at is older than the cutoff, whoever gets there first.
     */
    public static function reserve(string $lock, int $seconds = 60): bool
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        db_execute(
            "INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at)
             VALUES (?, 'whatsapp_locks', ?, '', ?) ON CONFLICT(group_name, setting_key) DO NOTHING",
            [Uuid::v4(), $lock, '1970-01-01T00:00:00Z']
        );

        return (bool) db_execute(
            "UPDATE website_settings SET updated_at = ? WHERE group_name = 'whatsapp_locks' AND setting_key = ? AND updated_at <= ?",
            [$now, $lock, gmdate('Y-m-d\TH:i:s\Z', time() - $seconds)]
        );
    }

    /** Opt-in recorded with the enquiry; without it no enquiry message may be sent. */
    public static function leadConsent(array $lead): bool
    {
        $details = $lead['details'] ?? json_decode($lead['details_json'] ?? '{}', true) ?? [];

        return in_array($details['whatsapp_consent'] ?? false, [true, 1, '1'], true);
    }

    /** Template body parameters, in the order the approved templates declare them. */
    public static function leadParams(array $lead, string $event): array
    {
        $params = [$lead['name'] ?: 'Customer', ucwords(str_replace('_', ' ', $lead['type']))];
        if ($event === 'lead_approved') {
            $params[] = ($lead['scheduled_at'] ?? '') ?: 'As arranged';
        }

        return $params;
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

    /**
     * @param array $context chain bookkeeping from MessagingRouter — which
     *        attempt group and position this send is, and whether it is a panel
     *        test. Empty for the direct callers (admin send, retry), which is
     *        why every field is optional.
     */
    public static function send(string $mobile, string $event, array $parameters, string $reference, array $context = []): array
    {
        $number = self::normalizeMobile($mobile);
        $template = self::credential('template_' . $event);
        $token = self::credential('access_token');
        $phoneId = self::credential('phone_number_id');
        $version = self::credential('graph_version');
        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        // Reserve before contacting Meta, including attempts blocked by missing configuration.
        db_execute('INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, provider, status, attempt_group, chain_position, is_test, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $reference, $event, $number ?? substr($mobile, 0, 20), $template, $context['provider'] ?? 'meta', 'sending',
                $context['attempt_group'] ?? null, (int) ($context['chain_position'] ?? 0), !empty($context['is_test']) ? 1 : 0, $now, $now]);
        if (!self::enabled() || !$number || !preg_match('/^[a-z0-9_]+$/', $template)
            || !$token || !ctype_digit($phoneId) || !preg_match('/^v\d+\.\d+$/', $version)) {
            db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ? WHERE id = ?',
                ['failed', 'configuration_or_recipient_invalid', 'WhatsApp is disabled or its template, credentials, or recipient is invalid.', $id]);
            return ['success' => false, 'message' => 'WhatsApp is disabled or its configuration/recipient is incomplete.', 'id' => $id];
        }
        $components = [['type' => 'body', 'parameters' => array_map(static fn($value) => ['type' => 'text', 'text' => (string) $value], $parameters)]];
        if ($event === 'otp') $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string) $parameters[0]]]];
        $payload = ['messaging_product' => 'whatsapp', 'to' => $number, 'type' => 'template', 'template' => [
            'name' => $template, 'language' => ['code' => self::credential('language') ?: 'en_US'], 'components' => $components,
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

    /**
     * The admin "Send / Retry" path for an enquiry, unchanged: consent, the
     * 60-second duplicate guard, then a direct Meta send. The automatic flow
     * goes through MessagingRouter instead, which reuses the same three helpers
     * above so there is one definition of each rule.
     */
    public static function notifyLead(array $lead, string $event): array
    {
        if (!self::leadConsent($lead)) {
            return ['success' => false, 'message' => 'This customer has not opted in to WhatsApp updates.'];
        }
        if (!in_array($event, ['lead_received', 'lead_approved', 'lead_rejected'], true)) {
            return ['success' => false, 'message' => 'Unsupported notification.'];
        }
        // Atomic cooldown reservation prevents simultaneous admin/automatic sends.
        if (!self::reserve('wa:' . $lead['id'] . ':' . $event)) {
            return ['success' => false, 'message' => 'Please wait 60 seconds before retrying this notification.'];
        }

        return static::send((string) $lead['phone'], $event, self::leadParams($lead, $event), $lead['id']);
    }

    public static function validSignature(string $raw, string $signature): bool
    {
        $secret = self::credential('app_secret');
        return $secret !== '' && hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $signature);
    }

    /**
     * Applies Meta's delivery statuses. Returns the ids it actually moved, so
     * the webhook log can record what a call did — an authenticated callback
     * that matched nothing is the single most common "webhooks don't work"
     * symptom, and it is invisible unless counted.
     *
     * @return list<string>
     */
    public static function updateStatuses(array $payload): array
    {
        $rank = ['accepted' => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4];
        $matched = [];
        foreach ($payload['entry'] ?? [] as $entry) foreach ($entry['changes'] ?? [] as $change) foreach ($change['value']['statuses'] ?? [] as $status) {
            $next = $status['status'] ?? '';
            if (!in_array($next, ['sent', 'delivered', 'read', 'failed'], true)) continue;
            // Scoped to the direct route: the same table now also holds sends
            // made through MSG91, whose reports arrive on their own endpoint.
            $row = db_fetch_one("SELECT id, status FROM whatsapp_messages WHERE provider_id = ? AND (provider = 'meta' OR provider IS NULL OR provider = '')", [$status['id'] ?? '']);
            if (!$row || ($rank[$row['status']] ?? 0) > $rank[$next]) continue;
            if (db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
                [$next, isset($status['errors'][0]['code']) ? (string) $status['errors'][0]['code'] : null, $next === 'failed' ? 'Meta reported WhatsApp delivery failure.' : null, gmdate('Y-m-d\TH:i:s\Z'), $row['id'], $row['status']])) {
                $matched[] = (string) $row['id'];
            }
        }

        return $matched;
    }

    /**
     * MSG91's outbound WhatsApp report. Shares the rank guard with the direct
     * route so a duplicate or out-of-order report cannot regress a delivered or
     * read message, and is scoped to provider = 'msg91'.
     *
     * Correlation is the request id MSG91 returned when it accepted the send.
     * Where a report carries no request id, the most recent in-flight send to
     * that number is used — narrow enough to be right, and it never touches a
     * row that has already reached a stronger status.
     *
     * @return list<string>
     */
    public static function updateMsg91Status(string $requestId, string $recipient, string $next, ?string $errorDetail = null): array
    {
        $rank = ['sending' => -1, 'accepted' => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4];
        if (!isset($rank[$next]) || $next === 'sending') {
            return [];
        }

        $row = $requestId !== ''
            ? db_fetch_one("SELECT id, status FROM whatsapp_messages WHERE provider = 'msg91' AND provider_id = ?", [$requestId])
            : null;

        if (!$row && $recipient !== '') {
            $row = db_fetch_one(
                "SELECT id, status FROM whatsapp_messages WHERE provider = 'msg91' AND mobile = ? AND status IN ('sending', 'accepted', 'sent') ORDER BY created_at DESC LIMIT 1",
                [$recipient]
            );
        }

        if (!$row || ($rank[$row['status']] ?? 0) > $rank[$next]) {
            return [];
        }

        $moved = db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
            [$next, $next === 'failed' ? 'msg91_delivery_failed' : null,
                $next === 'failed' ? substr((string) ($errorDetail ?: 'MSG91 reported WhatsApp delivery failure.'), 0, 512) : null,
                gmdate('Y-m-d\TH:i:s\Z'), $row['id'], $row['status']]);

        return $moved ? [(string) $row['id']] : [];
    }
}
