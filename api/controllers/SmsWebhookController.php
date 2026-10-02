<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/SmsDeliveryService.php';

final class SmsWebhookController extends V1Controller
{
    public function twilio(): never
    {
        $id = (string) ($_GET['message_id'] ?? '');
        if (!SmsDeliveryService::validTwilioSignature($id, $_POST, (string) ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? ''))) {
            http_response_code(403); exit;
        }
        $status = match ($_POST['MessageStatus'] ?? '') {
            'accepted', 'queued', 'sending' => 'accepted', 'sent' => 'sent', 'delivered', 'read' => 'delivered',
            'failed', 'undelivered', 'canceled' => 'failed', default => '',
        };
        SmsDeliveryService::updateStatus('twilio', $id, (string) ($_POST['MessageSid'] ?? ''), $status,
            $_POST['ErrorCode'] ?? null, 'Twilio reported SMS delivery failure.');
        http_response_code(204); exit;
    }

    public function msg91(): never
    {
        if (!SmsDeliveryService::validMsg91Token((string) ($_SERVER['HTTP_X_SMS_WEBHOOK_TOKEN'] ?? ''))) {
            http_response_code(403); exit;
        }
        $payload = ApiRequest::body();
        $reports = array_is_list($payload) ? $payload : [$payload];
        foreach ($reports as $report) {
            if (!is_array($report)) continue;
            $status = match ((string) ($report['status'] ?? '')) { '1' => 'delivered', '2' => 'failed', default => '' };
            SmsDeliveryService::updateStatus('msg91', (string) ($report['CRQID'] ?? ''), (string) ($report['requestId'] ?? ''),
                $status, $status === 'failed' ? 'msg91_delivery_failed' : null,
                $status === 'failed' ? (string) ($report['failureReason'] ?? 'MSG91 reported SMS delivery failure.') : null,
                (string) ($report['telNum'] ?? ''));
        }
        http_response_code(204); exit;
    }
}
