<?php

require_once __DIR__ . '/../../WhatsAppService.php';

/**
 * WhatsApp through Meta's Cloud API — the direct route.
 *
 * Thin on purpose: WhatsAppService already builds the template payload, writes
 * its own delivery-log row before contacting Meta, and interprets the response.
 * It is the code the webhook status updates and the retry path are written
 * against, so the chain delegates to it instead of re-implementing the send.
 */
final class WhatsAppMetaTransport implements MessagingTransport
{
    public function __construct(private string $provider = 'whatsapp.meta')
    {
    }

    public function send(array $message): array
    {
        $result = WhatsAppService::send(
            (string) ($message['recipient'] ?? ''),
            (string) ($message['event'] ?? ''),
            array_values($message['params'] ?? []),
            (string) ($message['reference'] ?? ''),
            [
                'provider' => 'meta',
                'attempt_group' => $message['attemptGroup'] ?? null,
                'chain_position' => (int) ($message['position'] ?? 0),
                'is_test' => !empty($message['isTest']),
            ]
        );

        return $result + ['status' => $result['success'] ? 'accepted' : 'failed'];
    }
}
