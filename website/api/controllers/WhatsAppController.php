<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/WhatsAppService.php';
require_once __BASEDIR__ . '/core/messaging/WebhookEventLog.php';
require_once __BASEDIR__ . '/core/Csrf.php';

final class WhatsAppController extends V1Controller
{
    public function verifyWebhook(): never
    {
        $started = microtime(true);
        $token = WhatsAppService::credential('verify_token');
        $valid = ($_GET['hub_mode'] ?? '') === 'subscribe' && $token !== '' && hash_equals($token, (string) ($_GET['hub_verify_token'] ?? ''));

        // Meta's verification handshake is the step operators get wrong most
        // often, and it used to leave no trace at all. Recording both outcomes
        // turns "Verify and save fails" into something readable on the
        // Webhooks page.
        WebhookEventLog::record([
            'provider' => 'whatsapp.meta',
            'route' => 'api/v1/whatsapp/webhook',
            'signatureValid' => $valid,
            'httpStatus' => $valid ? 200 : 403,
            'eventType' => 'verification',
            'payload' => ['hub_mode' => $_GET['hub_mode'] ?? null, 'hub_verify_token' => '[redacted]'],
            'errorCode' => $valid ? null : 'verify_token_mismatch',
            'errorDetail' => $valid ? null : ($token === ''
                ? 'No verify token is configured in Admin → Settings → Messaging.'
                : 'The token Meta sent does not match the configured verify token.'),
            'startedAt' => $started,
        ]);

        if (!$valid) {
            http_response_code(403); exit;
        }

        header('Content-Type: text/plain'); echo (string) ($_GET['hub_challenge'] ?? ''); exit;
    }

    public function webhook(): never
    {
        $started = microtime(true);
        $raw = file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

        if (!WhatsAppService::validSignature($raw, $signature)) {
            WebhookEventLog::record([
                'provider' => 'whatsapp.meta',
                'route' => 'api/v1/whatsapp/webhook',
                'signatureValid' => false,
                'httpStatus' => 403,
                'payload' => $raw,
                'errorCode' => 'signature_invalid',
                'errorDetail' => WhatsAppService::credential('app_secret') === ''
                    ? 'No app secret is configured, so every inbound event is rejected.'
                    : 'The X-Hub-Signature-256 HMAC did not match the configured app secret.',
                'startedAt' => $started,
            ]);
            http_response_code(403); exit;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            WebhookEventLog::record([
                'provider' => 'whatsapp.meta', 'route' => 'api/v1/whatsapp/webhook',
                'signatureValid' => true, 'httpStatus' => 400, 'payload' => $raw,
                'errorCode' => 'payload_invalid', 'errorDetail' => 'The body was not JSON.', 'startedAt' => $started,
            ]);
            http_response_code(400); exit;
        }

        $matched = WhatsAppService::updateStatuses($payload);

        WebhookEventLog::record([
            'provider' => 'whatsapp.meta',
            'route' => 'api/v1/whatsapp/webhook',
            'signatureValid' => true,
            'httpStatus' => 200,
            'eventType' => $payload['entry'][0]['changes'][0]['value']['statuses'][0]['status'] ?? 'event',
            'recipient' => $payload['entry'][0]['changes'][0]['value']['statuses'][0]['recipient_id'] ?? null,
            'matched' => $matched,
            'payload' => $payload,
            'errorCode' => $matched ? null : 'no_match',
            'errorDetail' => $matched ? null : 'Authenticated, but no recorded send matches this provider message ID.',
            'startedAt' => $started,
        ]);

        http_response_code(200); exit;
    }

    /**
     * Delivery reports for WhatsApp sent through MSG91.
     *
     * A shared header token rather than an HMAC, because that is all MSG91
     * offers. MSG91 gives the endpoint five seconds and never retries, so this
     * does one indexed update and returns — the log row is written after the
     * status is applied for the same reason.
     */
    public function webhookMsg91(): never
    {
        $started = microtime(true);
        $expected = MessagingRegistry::value('whatsapp.msg91', 'webhook_token');
        $supplied = (string) ($_SERVER['HTTP_X_WA_WEBHOOK_TOKEN'] ?? '');

        if ($expected === '' || !hash_equals($expected, $supplied)) {
            WebhookEventLog::record([
                'provider' => 'whatsapp.msg91', 'route' => 'api/v1/whatsapp/webhook/msg91',
                'signatureValid' => false, 'httpStatus' => 403, 'payload' => ApiRequest::body(),
                'errorCode' => 'token_invalid',
                'errorDetail' => $expected === ''
                    ? 'No webhook token is configured for MSG91 WhatsApp in Admin → Settings → Messaging.'
                    : 'The X-WA-Webhook-Token header did not match the configured token.',
                'startedAt' => $started,
            ]);
            http_response_code(403); exit;
        }

        $body = ApiRequest::body();
        $reports = is_array($body) && array_is_list($body) ? $body : [$body];
        $matched = [];
        $observed = '';

        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }

            // MSG91 reports a textual status; anything outside this map is a
            // lifecycle event this page does not track.
            $raw = strtolower((string) ($report['status'] ?? $report['event'] ?? ''));
            $status = match ($raw) {
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed', 'undelivered' => 'failed',
                default => '',
            };
            if ($status === '') {
                continue;
            }
            $observed = $status;

            $matched = array_merge($matched, WhatsAppService::updateMsg91Status(
                (string) ($report['requestId'] ?? $report['request_id'] ?? ''),
                (string) ($report['number'] ?? $report['telNum'] ?? $report['recipient'] ?? ''),
                $status,
                (string) ($report['failureReason'] ?? $report['reason'] ?? '')
            ));
        }

        WebhookEventLog::record([
            'provider' => 'whatsapp.msg91',
            'route' => 'api/v1/whatsapp/webhook/msg91',
            'signatureValid' => true,
            'httpStatus' => 204,
            'eventType' => $observed ?: 'event',
            'recipient' => $reports[0]['number'] ?? $reports[0]['telNum'] ?? null,
            'matched' => $matched,
            // `_status` is this server's own reading of the provider's status,
            // stored so a replay does not have to re-derive it.
            'payload' => is_array($body) ? (is_array($reports[0] ?? null) ? $reports[0] + ['_status' => $observed] : $body) : [],
            'errorCode' => $matched ? null : 'no_match',
            'errorDetail' => $matched ? null : 'Authenticated, but no recorded MSG91 WhatsApp send matches this report.',
            'startedAt' => $started,
        ]);

        http_response_code(204); exit;
    }

    public function history(): never
    {
        $this->requireAdmin();
        Envelope::ok(db_fetch_all('SELECT id, event_name, template_name, provider, status, error_code, created_at, updated_at FROM whatsapp_messages WHERE reference_id = ? ORDER BY created_at DESC LIMIT 50', [(string) $this->param('id')]));
    }

    public function sendLead(): never
    {
        $this->requireAdmin();
        if (!ApiRequest::bearerToken() && !Csrf::validate()) Envelope::forbidden('Invalid request token. Reload the page and try again.');
        $lead = db_fetch_one('SELECT * FROM website_leads WHERE id = ?', [(string) $this->param('id')]);
        if (!$lead) Envelope::notFound('NOT_FOUND', 'Lead not found');
        $event = match ($lead['status']) { 'approved' => 'lead_approved', 'rejected' => 'lead_rejected', default => 'lead_received' };
        $result = WhatsAppService::notifyLead($lead, $event);
        if (!$result['success']) Envelope::fail('WHATSAPP_SEND_FAILED', $result['message'], 400);
        Envelope::ok($result);
    }
}
