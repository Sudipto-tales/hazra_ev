<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/WhatsAppService.php';
require_once __BASEDIR__ . '/core/Csrf.php';

final class WhatsAppController extends V1Controller
{
    public function verifyWebhook(): never
    {
        $token = (string) env('WHATSAPP_VERIFY_TOKEN', '');
        if (($_GET['hub_mode'] ?? '') !== 'subscribe' || $token === '' || !hash_equals($token, (string) ($_GET['hub_verify_token'] ?? ''))) {
            http_response_code(403); exit;
        }
        header('Content-Type: text/plain'); echo (string) ($_GET['hub_challenge'] ?? ''); exit;
    }

    public function webhook(): never
    {
        $raw = file_get_contents('php://input');
        if (!WhatsAppService::validSignature($raw, (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''))) {
            http_response_code(403); exit;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) { http_response_code(400); exit; }
        WhatsAppService::updateStatuses($payload);
        http_response_code(200); exit;
    }

    public function history(): never
    {
        $this->requireAdmin();
        Envelope::ok(db_fetch_all('SELECT id, event_name, template_name, status, error_code, created_at, updated_at FROM whatsapp_messages WHERE reference_id = ? ORDER BY created_at DESC LIMIT 50', [(string) $this->param('id')]));
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
