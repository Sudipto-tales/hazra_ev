<?php

require_once __DIR__ . '/../AttemptLog.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../EmailService.php';
// For OTP_TTL_SECONDS, which the code email quotes to the customer. Not a
// cycle: OtpService reaches the router and the registry, neither of which
// loads a transport — transports are required lazily, on first use.
require_once __DIR__ . '/../../OtpService.php';

/**
 * Email as a first-class delivery channel.
 *
 * The lead events delegate to EmailService, which already owns the team/customer
 * split and the per-event notify toggles in Admin → Settings. Routing them
 * through here rather than reimplementing the mail means this change cannot
 * alter which lead emails go out or what they say — it only records them and
 * lets the chain decide whether WhatsApp follows.
 *
 * "Success" is deliberately whether the CUSTOMER was reached, not whether the
 * team copy sent. That is the same boolean the lead flow has always used to
 * decide WhatsApp fallback, so the fallback behaviour is unchanged.
 */
final class EmailSmtpTransport implements MessagingTransport
{
    public function __construct(private string $provider = 'email.smtp')
    {
    }

    public function send(array $message): array
    {
        $event = (string) ($message['event'] ?? '');
        $recipient = trim((string) ($message['recipient'] ?? ''));

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            // No address on file. The router skips the step before reaching
            // here in normal flow; this guards the test-send path.
            return ['success' => false, 'error_code' => 'recipient_invalid', 'error_detail' => 'No valid email address for this recipient.'];
        }

        $id = MessagingAttemptLog::open('email_messages', $message, 'smtp');

        try {
            $result = $this->dispatch($event, $recipient, $message);
        } catch (Throwable $e) {
            error_log('[Email] Dispatch failed for message ' . $id);
            $result = ['success' => false, 'error_code' => 'transport_error', 'error_detail' => 'The mail server could not be reached.'];
        }

        MessagingAttemptLog::close('email_messages', $id, $result);

        return $result + ['id' => $id, 'message' => $result['success'] ? 'Email accepted by the mail server.' : 'Email sending failed.'];
    }

    private function dispatch(string $event, string $recipient, array $message): array
    {
        $siteName = MessagingRegistry::value($this->provider, 'from_name') ?: 'Hazra EV';
        $params = $message['params'] ?? [];

        if ($event === 'otp') {
            $code = (string) ($message['code'] ?? ($params[0] ?? ''));
            if (!preg_match('/^\d{4,8}$/', $code)) {
                return ['success' => false, 'error_code' => 'code_missing', 'error_detail' => 'No verification code was supplied for this email.'];
            }

            $sent = Mailer::send($recipient, "Your {$siteName} verification code", $this->otpBody($code, $siteName), true);

            return $sent
                ? ['success' => true, 'status' => 'accepted']
                : ['success' => false, 'error_code' => 'smtp_rejected', 'error_detail' => 'The mail server rejected the verification email.'];
        }

        if ($event === 'warranty_received') {
            $sent = Mailer::send(
                $recipient,
                "Warranty registration received — {$siteName}",
                $this->warrantyBody((string) ($params[0] ?? 'Customer'), (string) ($params[1] ?? ''), $siteName),
                true
            );

            return $sent
                ? ['success' => true, 'status' => 'accepted']
                : ['success' => false, 'error_code' => 'smtp_rejected', 'error_detail' => 'The mail server rejected the registration email.'];
        }

        $lead = $message['payload']['lead'] ?? null;
        if (!is_array($lead)) {
            return ['success' => false, 'error_code' => 'lead_missing', 'error_detail' => 'This email needs the enquiry record and none was supplied.'];
        }

        $sent = match ($event) {
            'lead_received' => EmailService::notifyNewLead($lead),
            'lead_approved' => EmailService::notifyLeadApproved($lead),
            'lead_rejected' => EmailService::notifyLeadRejected($lead),
            default => false,
        };

        return $sent
            ? ['success' => true, 'status' => 'accepted']
            : ['success' => false, 'error_code' => 'not_sent', 'error_detail' => 'The customer email was not sent — the notification may be switched off in Settings, or the address was rejected.'];
    }

    private function otpBody(string $code, string $siteName): string
    {
        $minutes = (int) round(OtpService::OTP_TTL_SECONDS / 60);

        return "<div style='font-family:Arial,sans-serif;color:#333;line-height:1.6;max-width:600px;margin:0 auto;border:1px solid #eee;border-radius:8px;overflow:hidden'>
            <div style='background:#111827;color:#fff;padding:20px;text-align:center'><h2 style='margin:0;font-size:20px'>{$siteName} verification code</h2></div>
            <div style='padding:24px;text-align:center'>
                <p style='font-size:15px;margin:0 0 16px'>Use this code to verify your mobile number:</p>
                <p style='font-size:34px;letter-spacing:8px;font-weight:bold;margin:0;color:#111827'>{$code}</p>
                <p style='font-size:13px;color:#666;margin:18px 0 0'>Valid for {$minutes} minutes. Do not share it with anyone — our staff will never ask you for it.</p>
            </div>
        </div>";
    }

    private function warrantyBody(string $name, string $reference, string $siteName): string
    {
        $referenceRow = $reference !== ''
            ? "<p style='margin:16px 0 0'><strong>Reference number:</strong> {$reference}</p>"
            : '';

        return "<div style='font-family:Arial,sans-serif;color:#333;line-height:1.6;max-width:600px;margin:0 auto;border:1px solid #eee;border-radius:8px;overflow:hidden'>
            <div style='background:#10b981;color:#fff;padding:20px;text-align:center'><h2 style='margin:0;font-size:20px'>Warranty registration received</h2></div>
            <div style='padding:24px'>
                <p>Dear <strong>{$name}</strong>,</p>
                <p>We have received your warranty registration at {$siteName} and our team is reviewing it.</p>
                {$referenceRow}
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0'>
                <p style='font-size:12px;color:#888'>Team {$siteName}</p>
            </div>
        </div>";
    }
}
