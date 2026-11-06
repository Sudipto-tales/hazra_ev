<?php

require_once __DIR__ . '/../../WhatsAppService.php';

/**
 * WhatsApp through MSG91 — the indirect route.
 *
 * Same templates, same delivery-log table and the same status vocabulary as the
 * direct Meta route; only the hop differs. MSG91 holds the Meta side of the
 * integration, which is the point of using it: no app secret, no verify token
 * and no phone number ID to manage, at the cost of a vendor in the middle.
 *
 * The request body follows MSG91's documented bulk template API. If an account's
 * panel shows a different payload shape, this method is the only place that has
 * to change — the chain, the log and the webhook handler are shape-independent.
 */
final class WhatsAppMsg91Transport implements MessagingTransport
{
    private const ENDPOINT = 'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

    public function __construct(private string $provider = 'whatsapp.msg91')
    {
    }

    public function send(array $message): array
    {
        $event = (string) ($message['event'] ?? '');
        $number = WhatsAppService::normalizeMobile((string) ($message['recipient'] ?? ''));
        $template = MessagingRegistry::value($this->provider, 'template_' . $event);
        $authKey = MessagingRegistry::value($this->provider, 'auth_key');
        $integrated = MessagingRegistry::value($this->provider, 'integrated_number');

        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');

        // Reserved before contacting MSG91, including attempts blocked by
        // configuration — the same ordering WhatsAppService uses, so a send that
        // dies mid-request is visible rather than absent.
        db_execute(
            'INSERT INTO whatsapp_messages (id, reference_id, event_name, mobile, template_name, provider, status, attempt_group, chain_position, is_test, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, (string) ($message['reference'] ?? ''), $event,
                $number ?? substr((string) ($message['recipient'] ?? ''), 0, 20),
                $template, 'msg91', 'sending',
                $message['attemptGroup'] ?? null, (int) ($message['position'] ?? 0),
                !empty($message['isTest']) ? 1 : 0, $now, $now,
            ]
        );

        if (!$number || !preg_match('/^[a-z0-9_]+$/', $template) || $authKey === '' || !ctype_digit($integrated)) {
            db_execute('UPDATE whatsapp_messages SET status = ?, error_code = ?, error_detail = ? WHERE id = ?',
                ['failed', 'configuration_or_recipient_invalid', 'MSG91 WhatsApp credentials, template, or recipient is invalid.', $id]);

            return ['success' => false, 'id' => $id, 'status' => 'failed',
                'error_code' => 'configuration_or_recipient_invalid',
                'message' => 'MSG91 WhatsApp is not fully configured for this event.'];
        }

        try {
            [$response, $status] = $this->request($authKey, $this->payload($integrated, $template, $number, $message));
        } catch (Throwable $e) {
            $response = [];
            $status = 0;
            error_log('[WhatsApp MSG91] Transport failed for message ' . $id);
        }

        // MSG91 answers 200 with {"type":"success"} and a request id; anything
        // else — including a 200 carrying type "error" — is a rejection.
        $ok = $status >= 200 && $status < 300 && ($response['type'] ?? '') === 'success';
        $providerId = $ok ? (string) ($response['request_id'] ?? $response['requestId'] ?? $response['data']['request_id'] ?? '') : null;

        db_execute('UPDATE whatsapp_messages SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
            [
                $providerId ?: null,
                $ok ? 'accepted' : 'failed',
                $ok ? null : 'msg91_rejected',
                $ok ? null : 'MSG91 rejected the WhatsApp request or could not be reached (HTTP ' . $status . ').',
                gmdate('Y-m-d\TH:i:s\Z'), $id, 'sending',
            ]
        );

        return [
            'success' => $ok, 'id' => $id, 'status' => $ok ? 'accepted' : 'failed',
            'provider_id' => $providerId,
            'error_code' => $ok ? null : 'msg91_rejected',
            'message' => $ok ? 'WhatsApp message accepted by MSG91.' : 'MSG91 rejected the WhatsApp message.',
        ];
    }

    private function payload(string $integrated, string $template, string $number, array $message): array
    {
        $components = [];
        foreach (array_values($message['params'] ?? []) as $index => $value) {
            $components['body_' . ($index + 1)] = ['type' => 'text', 'value' => (string) $value];
        }

        // An authentication template's copy-code button takes the same value as
        // the first body parameter, exactly as the direct Meta route sends it.
        if (($message['event'] ?? '') === 'otp' && isset($message['params'][0])) {
            $components['button_1'] = ['subtype' => 'url', 'type' => 'text', 'value' => (string) $message['params'][0]];
        }

        $language = ['code' => MessagingRegistry::value($this->provider, 'language') ?: 'en', 'policy' => 'deterministic'];
        $namespace = MessagingRegistry::value($this->provider, 'namespace');

        $templateBlock = ['name' => $template, 'language' => $language, 'to_and_components' => [['to' => [$number], 'components' => $components]]];
        if ($namespace !== '') {
            $templateBlock['namespace'] = $namespace;
        }

        return [
            'integrated_number' => $integrated,
            'content_type' => 'template',
            'payload' => ['messaging_product' => 'whatsapp', 'type' => 'template', 'template' => $templateBlock],
        ];
    }

    private function request(string $authKey, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return [[], 0];
        }

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['authkey: ' . $authKey, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return [json_decode(is_string($raw) ? $raw : '', true) ?: [], $status];
    }
}
