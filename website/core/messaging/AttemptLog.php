<?php

/**
 * Writes one delivery-log row per chain attempt.
 *
 * WhatsApp and SMS attempts are recorded by WhatsAppService::send() and
 * SmsDeliveryService::sendOtp(), which have always owned their own rows and now
 * take the chain context through their $context argument. Email had no log at
 * all before this, so this class is what gives it one — same column shape, so
 * MessageDeliveryLog's union treats all three identically.
 *
 * A row is inserted BEFORE the provider is contacted, exactly as the WhatsApp
 * path does, so an attempt that dies mid-request is visible as `sending` rather
 * than missing.
 */
final class MessagingAttemptLog
{
    /** @return string the row id */
    public static function open(string $table, array $message, string $provider): string
    {
        $id = Uuid::v4();
        $now = gmdate('Y-m-d\TH:i:s\Z');

        db_execute(
            "INSERT INTO {$table} (id, reference_id, event_name, mobile, provider, status, attempt_group, chain_position, is_test, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'sending', ?, ?, ?, ?, ?)",
            [
                $id,
                (string) ($message['reference'] ?? ''),
                (string) ($message['event'] ?? ''),
                substr((string) ($message['recipient'] ?? ''), 0, 190),
                $provider,
                $message['attemptGroup'] ?? null,
                (int) ($message['position'] ?? 0),
                !empty($message['isTest']) ? 1 : 0,
                $now,
                $now,
            ]
        );

        return $id;
    }

    /** Only moves a row still in `sending`, so an early callback cannot be overwritten. */
    public static function close(string $table, string $id, array $result): void
    {
        db_execute(
            "UPDATE {$table} SET provider_id = ?, status = ?, error_code = ?, error_detail = ?, updated_at = ?
              WHERE id = ? AND status = 'sending'",
            [
                $result['provider_id'] ?? null,
                $result['success'] ? ($result['status'] ?? 'accepted') : 'failed',
                isset($result['error_code']) ? substr((string) $result['error_code'], 0, 80) : null,
                isset($result['error_detail']) ? substr((string) $result['error_detail'], 0, 512) : null,
                gmdate('Y-m-d\TH:i:s\Z'),
                $id,
            ]
        );
    }
}
