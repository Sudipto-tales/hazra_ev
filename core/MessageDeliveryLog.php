<?php
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/OtpService.php';

/** Combined, paginated metadata view. Never includes OTP hashes, codes, or tokens. */
class MessageDeliveryLog
{
    public const STATUSES = ['sending', 'accepted', 'sent', 'delivered', 'read', 'failed', 'logged'];

    private static function unionSql(): string
    {
        return "SELECT w.id, 'whatsapp' AS channel, 'meta' AS provider, w.reference_id, w.event_name,
            COALESCE(o.purpose, w.event_name) AS purpose, w.mobile, w.template_name, w.provider_id,
            w.status, w.error_code, w.error_detail, w.created_at, w.updated_at
            FROM whatsapp_messages w LEFT JOIN otp_challenges o ON o.id = w.reference_id AND w.event_name = 'otp'
            UNION ALL
            SELECT id, 'sms' AS channel, provider, reference_id, event_name, event_name AS purpose,
            mobile, '' AS template_name, provider_id, status, error_code, error_detail, created_at, updated_at FROM sms_messages";
    }

    public static function listing(array $filters): array
    {
        $where = []; $params = [];
        $channel = (string) ($filters['channel'] ?? ''); $status = (string) ($filters['status'] ?? '');
        if ($channel !== '') {
            if (!in_array($channel, ['whatsapp', 'sms'], true)) throw new InvalidArgumentException('Invalid channel.');
            $where[] = 'channel = ?'; $params[] = $channel;
        }
        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('Invalid status.');
            $where[] = 'status = ?'; $params[] = $status;
        }
        $query = trim((string) ($filters['q'] ?? ''));
        if (strlen($query) > 120) throw new InvalidArgumentException('Search is too long.');
        if ($query !== '') {
            $where[] = '(mobile LIKE ? OR purpose LIKE ? OR reference_id LIKE ? OR provider_id LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $query . '%'));
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            $date = (string) ($filters[$key] ?? '');
            if ($date === '') continue;
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Invalid date.');
            if ($key === 'to') $parsed = $parsed->modify('+1 day');
            $where[] = "created_at {$operator} ?"; $params[] = $parsed->format('Y-m-d\TH:i:s\Z');
        }
        if (!empty($filters['from']) && !empty($filters['to']) && $filters['from'] > $filters['to']) throw new InvalidArgumentException('Start date must precede end date.');
        $page = max(1, (int) ($filters['page'] ?? 1));
        if ($page > 1000000) throw new InvalidArgumentException('Invalid page.');
        $size = 25; $offset = ($page - 1) * $size;
        $sql = '(' . self::unionSql() . ') AS messages';
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $counts = db_fetch_all("SELECT status, COUNT(*) AS c FROM {$sql}{$clause} GROUP BY status", $params);
        $summary = array_fill_keys(self::STATUSES, 0); $total = 0;
        foreach ($counts as $count) { $summary[$count['status']] = (int) $count['c']; $total += (int) $count['c']; }
        $rows = db_fetch_all("SELECT * FROM {$sql}{$clause} ORDER BY created_at DESC, channel, id DESC LIMIT {$size} OFFSET {$offset}", $params);
        foreach ($rows as &$row) {
            $row['retryReason'] = self::retryReason($row);
            $row['canRetry'] = $row['retryReason'] === '';
            $row['retryLabel'] = in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) ? 'Send fresh OTP' : 'Retry message';
        }
        return ['items' => $rows, 'total' => $total, 'summary' => $summary, 'page' => $page, 'pageSize' => $size];
    }

    public static function find(string $channel, string $id): ?array
    {
        if (!in_array($channel, ['whatsapp', 'sms'], true)) return null;
        return db_fetch_one('SELECT * FROM (' . self::unionSql() . ') AS messages WHERE channel = ? AND id = ?', [$channel, $id]) ?: null;
    }

    public static function retryReason(array $row): string
    {
        if ($row['status'] !== 'failed') return 'Only failed messages can be retried.';
        if (in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) || $row['event_name'] === 'otp') {
            $challenge = db_fetch_one('SELECT id, mobile, purpose, created_at, attempts, verified_at, consumed_at FROM otp_challenges WHERE id = ?', [$row['reference_id']]);
            if (!$challenge || $challenge['verified_at'] || $challenge['consumed_at'] || (int) $challenge['attempts'] >= 5
                || strtotime($challenge['created_at']) < time() - OtpService::OTP_TTL_SECONDS) return 'The customer must request a new OTP from the warranty form.';
            $latest = db_fetch_one('SELECT id FROM otp_challenges WHERE mobile = ? AND purpose = ? ORDER BY created_at DESC LIMIT 1', [$challenge['mobile'], $challenge['purpose']]);
            if (($latest['id'] ?? '') !== $challenge['id']) return 'A newer OTP request already exists.';
            $sibling = db_fetch_one("SELECT id FROM (" . self::unionSql() . ") AS messages WHERE reference_id = ? AND status IN ('accepted', 'sent', 'delivered', 'read', 'logged') LIMIT 1", [$row['reference_id']]);
            if ($sibling) return 'This OTP was already accepted or delivered through another send. The customer can request a new code if needed.';
            return '';
        }
        $table = $row['channel'] === 'whatsapp' ? 'whatsapp_messages' : 'sms_messages';
        $newer = db_fetch_one("SELECT id FROM {$table} WHERE reference_id = ? AND event_name = ? AND id <> ? AND created_at >= ? LIMIT 1",
            [$row['reference_id'], $row['event_name'], $row['id'], $row['created_at']]);
        if ($newer) return 'A subsequent send already exists. Review its delivery status.';
        if (in_array($row['event_name'], ['lead_received', 'lead_approved', 'lead_rejected'], true)) {
            $lead = db_fetch_one('SELECT * FROM website_leads WHERE id = ?', [$row['reference_id']]);
            if (!$lead) return 'The enquiry no longer exists.';
            $details = json_decode($lead['details_json'] ?? '{}', true) ?: [];
            if (!in_array($details['whatsapp_consent'] ?? false, [true, 1, '1'], true)) return 'Customer WhatsApp consent is missing.';
            $currentEvent = match ($lead['status']) { 'approved' => 'lead_approved', 'rejected' => 'lead_rejected', default => 'lead_received' };
            if ($row['event_name'] !== $currentEvent) return 'The enquiry status has changed. Send its current update from the enquiry list.';
            return '';
        }
        if ($row['event_name'] === 'warranty_received') {
            $registration = db_fetch_one('SELECT whatsapp_consent_at FROM warranty_registrations WHERE id = ?', [$row['reference_id']]);
            return !empty($registration['whatsapp_consent_at']) ? '' : 'Warranty registration or WhatsApp consent is missing.';
        }
        return 'This message type cannot be retried.';
    }

    public static function retry(string $channel, string $id): array
    {
        $row = self::find($channel, $id);
        if (!$row) throw new OutOfBoundsException('Message not found.');
        if ($reason = self::retryReason($row)) throw new DomainException($reason);
        $lock = 'wa:retry:' . $channel . ':' . $id; $now = gmdate('Y-m-d\TH:i:s\Z');
        db_execute("INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at) VALUES (?, 'whatsapp_locks', ?, '', ?) ON CONFLICT(group_name, setting_key) DO NOTHING", [Uuid::v4(), $lock, '1970-01-01T00:00:00Z']);
        if (!db_execute("UPDATE website_settings SET updated_at = ? WHERE group_name = 'whatsapp_locks' AND setting_key = ? AND updated_at <= ?", [$now, $lock, gmdate('Y-m-d\TH:i:s\Z', time() - 60)])) throw new DomainException('Please wait 60 seconds before retrying.');
        if (in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) || $row['event_name'] === 'otp') {
            $challenge = db_fetch_one('SELECT mobile, purpose FROM otp_challenges WHERE id = ?', [$row['reference_id']]);
            $result = OtpService::sendOtp($challenge['mobile'], $challenge['purpose'], $channel === 'sms' ? 'sms' : 'auto');
            unset($result['debug_code']); // An admin delivery page must never disclose the customer's code.
            return $result;
        }
        if ($row['event_name'] === 'warranty_received') {
            $registration = db_fetch_one('SELECT * FROM warranty_registrations WHERE id = ?', [$row['reference_id']]);
            return WhatsAppService::send($registration['mobile'], 'warranty_received', [$registration['customer_name'], $registration['reference_no']], $registration['id']);
        }
        $lead = db_fetch_one('SELECT * FROM website_leads WHERE id = ?', [$row['reference_id']]);
        return WhatsAppService::notifyLead($lead, $row['event_name']);
    }
}
