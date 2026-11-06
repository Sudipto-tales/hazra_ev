<?php

require_once __DIR__ . '/Registry.php';
require_once __DIR__ . '/Router.php';

/**
 * Records and replays inbound provider callbacks.
 *
 * The reason this exists: a webhook that "isn't working" is almost always
 * arriving and being refused — wrong secret, wrong header name, a verify token
 * that was rotated on one side only — and before this table every one of those
 * returned 403 and left nothing behind. So a rejected call is recorded with the
 * same care as an accepted one, and an accepted call that matched no message is
 * recorded as matched_count 0 rather than looking like a success.
 *
 * Nothing secret is stored. Header values are dropped except for a safe
 * allow-list; an authentication header is reduced to "present, N characters",
 * which is what an operator actually needs to see. Payload keys that look like
 * credentials are replaced before the body is written.
 */
final class WebhookEventLog
{
    private const MAX_PAYLOAD = 4000;
    private const SAFE_HEADERS = ['CONTENT_TYPE', 'HTTP_CONTENT_TYPE', 'HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
    private const SENSITIVE = '/(token|secret|signature|authkey|auth_key|password|authorization|apikey|api_key)/i';

    /**
     * @param array $event {
     *   provider: string, route: string, signatureValid: bool, httpStatus: int,
     *   eventType: ?string, recipient: ?string, matched: list<string>,
     *   payload: mixed, errorCode: ?string, errorDetail: ?string, startedAt: ?float
     * }
     * @return string the row id — '' when the table is absent (pre-migration)
     */
    public static function record(array $event): string
    {
        $id = Uuid::v4();
        $matched = array_values(array_filter($event['matched'] ?? []));

        try {
            db_execute(
                'INSERT INTO webhook_events (id, provider_key, route, method, ip, signature_valid, http_status, event_type, recipient,
                    matched_message_id, matched_count, payload_redacted, headers_redacted, error_code, error_detail, processing_ms, received_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    (string) ($event['provider'] ?? 'unknown'),
                    substr((string) ($event['route'] ?? ''), 0, 128),
                    substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'), 0, 8),
                    substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
                    !empty($event['signatureValid']) ? 1 : 0,
                    (int) ($event['httpStatus'] ?? 0),
                    substr((string) ($event['eventType'] ?? ''), 0, 40) ?: null,
                    substr((string) ($event['recipient'] ?? ''), 0, 32) ?: null,
                    $matched[0] ?? null,
                    count($matched),
                    self::redactBody($event['payload'] ?? null),
                    self::redactHeaders(),
                    substr((string) ($event['errorCode'] ?? ''), 0, 80) ?: null,
                    substr((string) ($event['errorDetail'] ?? ''), 0, 512) ?: null,
                    isset($event['startedAt']) ? (int) round((microtime(true) - (float) $event['startedAt']) * 1000) : 0,
                    gmdate('Y-m-d\TH:i:s\Z'),
                ]
            );
        } catch (Throwable $e) {
            // A webhook must never fail because its audit row could not be
            // written — the provider would retry a call we already applied.
            error_log('[Webhook] Could not record an inbound event: ' . $e->getMessage());
            return '';
        }

        return $id;
    }

    private static function redactBody($payload): string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => $payload];
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        return substr((string) json_encode(self::scrub($payload), JSON_UNESCAPED_SLASHES), 0, self::MAX_PAYLOAD);
    }

    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::scrub($value);
                continue;
            }
            if (is_string($key) && preg_match(self::SENSITIVE, $key)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }

    /**
     * Safe headers verbatim; an authentication header reduced to its shape.
     * "present, 43 characters" is enough to tell a missing header from a
     * truncated paste, which is the actual diagnostic question.
     */
    private static function redactHeaders(): string
    {
        $kept = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value) || !str_starts_with($key, 'HTTP_') && !in_array($key, self::SAFE_HEADERS, true)) {
                continue;
            }
            $name = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/', '', $key)));
            $kept[$name] = in_array($key, self::SAFE_HEADERS, true)
                ? substr($value, 0, 200)
                : (preg_match(self::SENSITIVE, $key) ? 'present, ' . strlen($value) . ' characters' : substr($value, 0, 120));
        }

        return substr((string) json_encode($kept, JSON_UNESCAPED_SLASHES), 0, self::MAX_PAYLOAD);
    }

    /** @return array{items: list<array>, total: int, summary: array, page: int, pageSize: int} */
    public static function listing(array $filters): array
    {
        $where = [];
        $params = [];

        $provider = (string) ($filters['provider'] ?? '');
        if ($provider !== '') {
            if (!array_key_exists($provider, MessagingRegistry::specs())) {
                throw new InvalidArgumentException('Invalid provider.');
            }
            $where[] = 'provider_key = ?';
            $params[] = $provider;
        }

        $result = (string) ($filters['result'] ?? '');
        if ($result !== '') {
            $clause = match ($result) {
                'rejected' => 'signature_valid = 0',
                'unmatched' => 'signature_valid = 1 AND matched_count = 0',
                'applied' => 'signature_valid = 1 AND matched_count > 0',
                default => throw new InvalidArgumentException('Invalid result filter.'),
            };
            $where[] = $clause;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if (strlen($query) > 120) {
            throw new InvalidArgumentException('Search is too long.');
        }
        if ($query !== '') {
            $where[] = '(recipient LIKE ? OR matched_message_id LIKE ? OR error_code LIKE ? OR ip LIKE ? OR event_type LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%' . $query . '%'));
        }

        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if ($date === '') {
                continue;
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Invalid date.');
            }
            if ($key === 'to') {
                $parsed = $parsed->modify('+1 day');
            }
            $where[] = "received_at {$operator} ?";
            $params[] = $parsed->format('Y-m-d\TH:i:s\Z');
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        if ($page > 1000000) {
            throw new InvalidArgumentException('Invalid page.');
        }
        $size = 25;
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $totals = db_fetch_one("SELECT COUNT(*) AS total,
                SUM(CASE WHEN signature_valid = 0 THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN signature_valid = 1 AND matched_count = 0 THEN 1 ELSE 0 END) AS unmatched,
                SUM(CASE WHEN signature_valid = 1 AND matched_count > 0 THEN 1 ELSE 0 END) AS applied
              FROM webhook_events{$clause}", $params) ?: [];

        $rows = db_fetch_all(
            "SELECT * FROM webhook_events{$clause} ORDER BY received_at DESC, id DESC LIMIT {$size} OFFSET " . (($page - 1) * $size),
            $params
        );

        foreach ($rows as &$row) {
            $row['canReplay'] = self::replayReason($row) === '';
            $row['replayReason'] = self::replayReason($row);
        }

        return [
            'items' => $rows,
            'total' => (int) ($totals['total'] ?? 0),
            'summary' => [
                'rejected' => (int) ($totals['rejected'] ?? 0),
                'unmatched' => (int) ($totals['unmatched'] ?? 0),
                'applied' => (int) ($totals['applied'] ?? 0),
            ],
            'page' => $page,
            'pageSize' => $size,
        ];
    }

    public static function find(string $id): ?array
    {
        return db_fetch_one('SELECT * FROM webhook_events WHERE id = ?', [$id]) ?: null;
    }

    /** Empty when the event can be replayed; otherwise the reason it cannot. */
    public static function replayReason(array $row): string
    {
        if ((int) $row['signature_valid'] !== 1) {
            return 'This call failed authentication, so replaying it would apply an unverified payload.';
        }
        if ((int) $row['matched_count'] > 0) {
            return 'This call was already applied to a message.';
        }
        if (!in_array($row['provider_key'], ['whatsapp.meta', 'whatsapp.msg91', 'sms.msg91', 'sms.twilio'], true)) {
            return 'This provider has no replayable status payload.';
        }
        if (($row['payload_redacted'] ?? '') === '') {
            return 'The payload was not recorded.';
        }

        return '';
    }

    /**
     * Re-applies a verified call that matched nothing — typically one that
     * arrived before its own message row was committed.
     *
     * Safe to repeat: every handler it routes into keeps the status-rank guard,
     * so a replay can only ever move a message forward, never regress a
     * delivered or read one.
     *
     * @return array{applied: int, message: string}
     */
    public static function replay(string $id): array
    {
        $row = self::find($id);
        if (!$row) {
            throw new OutOfBoundsException('Webhook event not found.');
        }
        if ($reason = self::replayReason($row)) {
            throw new DomainException($reason);
        }

        $payload = json_decode((string) $row['payload_redacted'], true);
        if (!is_array($payload)) {
            throw new DomainException('The recorded payload could not be read.');
        }

        require_once __DIR__ . '/../WhatsAppService.php';
        require_once __DIR__ . '/../SmsDeliveryService.php';

        $matched = match ($row['provider_key']) {
            'whatsapp.meta' => WhatsAppService::updateStatuses($payload),
            'whatsapp.msg91' => WhatsAppService::updateMsg91Status(
                (string) ($payload['requestId'] ?? $payload['request_id'] ?? ''),
                (string) ($payload['number'] ?? $payload['telNum'] ?? ''),
                (string) ($payload['_status'] ?? ''),
                (string) ($payload['failureReason'] ?? '')
            ),
            'sms.msg91' => SmsDeliveryService::updateStatus('msg91', (string) ($payload['CRQID'] ?? ''), (string) ($payload['requestId'] ?? ''),
                (string) ($payload['_status'] ?? ''), $payload['_errorCode'] ?? null, $payload['failureReason'] ?? null, (string) ($payload['telNum'] ?? '')),
            'sms.twilio' => SmsDeliveryService::updateStatus('twilio', (string) ($payload['_messageId'] ?? ''), (string) ($payload['MessageSid'] ?? ''),
                (string) ($payload['_status'] ?? ''), $payload['ErrorCode'] ?? null, 'Twilio reported SMS delivery failure.'),
            default => [],
        };

        db_execute('UPDATE webhook_events SET replayed_at = ?, matched_message_id = ?, matched_count = ? WHERE id = ?',
            [gmdate('Y-m-d\TH:i:s\Z'), $matched[0] ?? null, count($matched), $id]);

        return [
            'applied' => count($matched),
            'message' => $matched
                ? 'Replayed: the delivery status was applied to the message.'
                : 'Replayed, but it still matched no message. The provider ID in this call does not belong to any send recorded here.',
        ];
    }

    /** @return int rows removed */
    public static function prune(?int $days = null): int
    {
        $days = $days ?? max(1, (int) MessagingRouter::setting('webhook_retention_days', '30'));
        $cutoff = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);

        return (int) db_execute('DELETE FROM webhook_events WHERE received_at < ?', [$cutoff]);
    }
}
