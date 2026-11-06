<?php

require_once __DIR__ . '/../../SmsDeliveryService.php';

/**
 * Every SMS provider, because they differ only inside
 * SmsDeliveryService::dispatch() — which already owns the row, the provider
 * request, the signature helpers the webhooks call back into, and the status
 * vocabulary. One transport over all four keeps that single code path.
 *
 * SMS carries OTP only. The providers wired here expose OTP endpoints (MSG91's
 * OTP API, Fast2SMS's OTP/DLT routes) rather than a general transactional
 * template per event, and inventing lead-notification SMS would mean DLT
 * templates nobody has registered. MessagingRegistry says so in each spec's
 * `events`, so the router skips an SMS step for any other event instead of
 * failing it.
 */
final class SmsProviderTransport implements MessagingTransport
{
    public function __construct(private string $provider)
    {
    }

    public function send(array $message): array
    {
        $code = (string) ($message['code'] ?? '');
        if (!preg_match('/^\d{4,8}$/', $code)) {
            return ['success' => false, 'error_code' => 'code_missing', 'error_detail' => 'SMS delivery needs a verification code and none was supplied.'];
        }

        // The stored purpose stays warranty_free / warranty_paid so existing
        // retry eligibility and the delivery page read exactly as before.
        $purpose = (string) ($message['payload']['purpose'] ?? $message['event'] ?? 'otp');

        $result = SmsDeliveryService::sendOtp(
            substr($this->provider, strlen('sms.')),
            (string) ($message['recipient'] ?? ''),
            $code,
            $purpose,
            (string) ($message['reference'] ?? ''),
            [
                'attempt_group' => $message['attemptGroup'] ?? null,
                'chain_position' => (int) ($message['position'] ?? 0),
                'is_test' => !empty($message['isTest']),
            ]
        );

        return $result + ['message' => $result['success'] ? 'SMS accepted by the provider.' : 'SMS sending failed.'];
    }
}
