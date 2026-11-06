<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/SmsDeliveryService.php';
require_once __BASEDIR__ . '/core/messaging/WebhookEventLog.php';

final class SmsWebhookController extends V1Controller
{
    public function twilio(): never
    {
        $started = microtime(true);
        $id = (string) ($_GET['message_id'] ?? '');
        $signature = (string) ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');

        if (!SmsDeliveryService::validTwilioSignature($id, $_POST, $signature)) {
            WebhookEventLog::record([
                'provider' => 'sms.twilio', 'route' => 'api/v1/sms/webhook/twilio',
                'signatureValid' => false, 'httpStatus' => 403, 'payload' => $_POST,
                'errorCode' => 'signature_invalid',
                'errorDetail' => 'The X-Twilio-Signature did not validate. The usual cause is a status callback URL that differs from the configured one, even by a trailing slash or a subdirectory.',
                'startedAt' => $started,
            ]);
            http_response_code(403); exit;
        }

        $reported = (string) ($_POST['MessageStatus'] ?? '');
        $status = match ($reported) {
            'accepted', 'queued', 'sending' => 'accepted', 'sent' => 'sent', 'delivered', 'read' => 'delivered',
            'failed', 'undelivered', 'canceled' => 'failed', default => '',
        };

        $matched = SmsDeliveryService::updateStatus('twilio', $id, (string) ($_POST['MessageSid'] ?? ''), $status,
            $_POST['ErrorCode'] ?? null, 'Twilio reported SMS delivery failure.');

        WebhookEventLog::record([
            'provider' => 'sms.twilio',
            'route' => 'api/v1/sms/webhook/twilio',
            'signatureValid' => true,
            'httpStatus' => 204,
            'eventType' => $reported ?: 'event',
            'recipient' => $_POST['To'] ?? null,
            'matched' => $matched,
            // `_status` and `_messageId` are this server's own reading of the
            // callback, stored so a replay need not re-derive them.
            'payload' => $_POST + ['_status' => $status, '_messageId' => $id],
            'errorCode' => $matched ? null : ($status === '' ? 'status_ignored' : 'no_match'),
            'errorDetail' => $matched ? null : ($status === ''
                ? 'Twilio reported a status this page does not track.'
                : 'Authenticated, but no recorded send matches this message SID.'),
            'startedAt' => $started,
        ]);

        http_response_code(204); exit;
    }

    public function msg91(): never
    {
        $started = microtime(true);
        $token = (string) ($_SERVER['HTTP_X_SMS_WEBHOOK_TOKEN'] ?? '');

        if (!SmsDeliveryService::validMsg91Token($token)) {
            WebhookEventLog::record([
                'provider' => 'sms.msg91', 'route' => 'api/v1/sms/webhook/msg91',
                'signatureValid' => false, 'httpStatus' => 403, 'payload' => ApiRequest::body(),
                'errorCode' => 'token_invalid',
                'errorDetail' => 'The X-SMS-Webhook-Token header did not match the configured token. Check the custom header in MSG91 → OTP → Webhook.',
                'startedAt' => $started,
            ]);
            http_response_code(403); exit;
        }

        $payload = ApiRequest::body();
        $reports = array_is_list($payload) ? $payload : [$payload];
        $matched = [];
        $observed = '';
        $first = null;

        foreach ($reports as $report) {
            if (!is_array($report)) continue;
            $status = match ((string) ($report['status'] ?? '')) { '1' => 'delivered', '2' => 'failed', default => '' };
            if ($status !== '') {
                $observed = $status;
            }
            $first ??= $report;

            $matched = array_merge($matched, SmsDeliveryService::updateStatus('msg91', (string) ($report['CRQID'] ?? ''), (string) ($report['requestId'] ?? ''),
                $status, $status === 'failed' ? 'msg91_delivery_failed' : null,
                $status === 'failed' ? (string) ($report['failureReason'] ?? 'MSG91 reported SMS delivery failure.') : null,
                (string) ($report['telNum'] ?? '')));
        }

        WebhookEventLog::record([
            'provider' => 'sms.msg91',
            'route' => 'api/v1/sms/webhook/msg91',
            'signatureValid' => true,
            'httpStatus' => 204,
            'eventType' => $observed ?: 'event',
            'recipient' => $first['telNum'] ?? null,
            'matched' => $matched,
            'payload' => is_array($first) ? $first + ['_status' => $observed, '_errorCode' => $observed === 'failed' ? 'msg91_delivery_failed' : null] : [],
            'errorCode' => $matched ? null : ($observed === '' ? 'status_ignored' : 'no_match'),
            'errorDetail' => $matched ? null : ($observed === ''
                ? 'MSG91 reported a status other than 1 (delivered) or 2 (failed).'
                : 'Authenticated, but no recorded send matches this CRQID or request ID.'),
            'startedAt' => $started,
        ]);

        http_response_code(204); exit;
    }
}
