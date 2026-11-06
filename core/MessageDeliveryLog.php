<?php
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/OtpService.php';
require_once __DIR__ . '/messaging/Router.php';

/** Combined, paginated metadata view. Never includes OTP hashes, codes, or tokens. */
class MessageDeliveryLog
{
    public const STATUSES = ['sending', 'accepted', 'sent', 'delivered', 'read', 'failed', 'logged'];
    public const CHANNELS = ['whatsapp', 'sms', 'email'];

    /**
     * One shape over three tables. The email arm is new; the WhatsApp arm no
     * longer hardcodes 'meta' as the provider, because that table now also
     * holds sends made through MSG91 — a NULL there is a row written before the
     * indirect route existed, which was Meta by definition.
     */
    private static function unionSql(): string
    {
        return "SELECT w.id, 'whatsapp' AS channel, COALESCE(NULLIF(w.provider, ''), 'meta') AS provider, w.reference_id, w.event_name,
            COALESCE(o.purpose, w.event_name) AS purpose, w.mobile, w.template_name, w.provider_id,
            w.status, w.error_code, w.error_detail, w.attempt_group, w.chain_position, w.is_test, w.created_at, w.updated_at
            FROM whatsapp_messages w LEFT JOIN otp_challenges o ON o.id = w.reference_id AND w.event_name = 'otp'
            UNION ALL
            SELECT id, 'sms' AS channel, provider, reference_id, event_name, event_name AS purpose,
            mobile, '' AS template_name, provider_id, status, error_code, error_detail, attempt_group, chain_position, is_test, created_at, updated_at FROM sms_messages
            UNION ALL
            SELECT e.id, 'email' AS channel, e.provider, e.reference_id, e.event_name,
            COALESCE(o.purpose, e.event_name) AS purpose, e.mobile, '' AS template_name, e.provider_id,
            e.status, e.error_code, e.error_detail, e.attempt_group, e.chain_position, e.is_test, e.created_at, e.updated_at
            FROM email_messages e LEFT JOIN otp_challenges o ON o.id = e.reference_id AND e.event_name = 'otp'";
    }

    public static function listing(array $filters): array
    {
        $where = []; $params = [];
        $channel = (string) ($filters['channel'] ?? ''); $status = (string) ($filters['status'] ?? '');
        if ($channel !== '') {
            if (!in_array($channel, self::CHANNELS, true)) throw new InvalidArgumentException('Invalid channel.');
            $where[] = 'channel = ?'; $params[] = $channel;
        }
        $tests = (string) ($filters['tests'] ?? '');
        if ($tests !== '') {
            if (!in_array($tests, ['only', 'exclude'], true)) throw new InvalidArgumentException('Invalid test filter.');
            $where[] = $tests === 'only' ? 'is_test = 1' : '(is_test = 0 OR is_test IS NULL)';
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
            // Whether the eye should appear at all. The code itself is never in
            // this payload — it is fetched per click, from its own endpoint,
            // and audited.
            $row['canReveal'] = self::revealable($row);
        }
        return ['items' => $rows, 'total' => $total, 'summary' => $summary, 'page' => $page, 'pageSize' => $size];
    }

    public static function find(string $channel, string $id): ?array
    {
        if (!in_array($channel, self::CHANNELS, true)) return null;
        return db_fetch_one('SELECT * FROM (' . self::unionSql() . ') AS messages WHERE channel = ? AND id = ?', [$channel, $id]) ?: null;
    }

    /**
     * An OTP row whose challenge is still live and still holds a cipher.
     *
     * Cheap on purpose — one indexed lookup per OTP row — and it never
     * decrypts: this only decides whether to draw the eye. OtpService::reveal
     * re-checks every condition before handing a code over, so a stale `true`
     * here cannot leak anything.
     */
    private static function revealable(array $row): bool
    {
        if (!in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) && $row['event_name'] !== 'otp') {
            return false;
        }
        if (MessagingRouter::setting('otp_reveal_enabled', '0') !== '1') {
            return false;
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $challenge = db_fetch_one(
            'SELECT id FROM otp_challenges
              WHERE id = ? AND verified_at IS NULL AND consumed_at IS NULL
                AND expires_at > ? AND code_cipher IS NOT NULL AND code_cipher_expires_at > ?',
            [$row['reference_id'], $now, $now]
        );

        return (bool) $challenge;
    }

    /**
     * Hands one live code to an admin and records that it happened.
     *
     * The audit row is written before the code is returned and is not
     * conditional on anything: if an admin saw a customer's code, there is a row
     * saying who, when and from where.
     *
     * @return array{code: string, challengeId: string}
     * @throws OutOfBoundsException|DomainException
     */
    public static function reveal(string $channel, string $id, array $admin): array
    {
        $row = self::find($channel, $id);
        if (!$row) throw new OutOfBoundsException('Message not found.');
        if (!in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) && $row['event_name'] !== 'otp') {
            throw new DomainException('This message is not a verification code.');
        }

        // A reveal is a deliberate support action, not something to be done in
        // bulk. The cap is high enough never to be hit by a person working
        // through a queue and low enough to stop a script walking the log.
        $recent = db_fetch_one('SELECT COUNT(*) AS c FROM otp_reveal_audit WHERE admin_user_id = ? AND revealed_at >= ?',
            [$admin['id'] ?? '', gmdate('Y-m-d\TH:i:s\Z', time() - 3600)]);
        if ((int) ($recent['c'] ?? 0) >= 30) {
            throw new DomainException('Too many codes revealed in the last hour. Try again later.');
        }

        $result = OtpService::revealCode((string) $row['reference_id']);
        if ($result['code'] === null) throw new DomainException($result['reason']);

        db_execute(
            'INSERT INTO otp_reveal_audit (id, challenge_id, message_id, channel, admin_user_id, admin_email, ip, user_agent, revealed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Uuid::v4(), $row['reference_id'], $row['id'], $channel,
                $admin['id'] ?? null, $admin['email'] ?? null,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                gmdate('Y-m-d\TH:i:s\Z'),
            ]
        );

        return ['code' => $result['code'], 'challengeId' => (string) $row['reference_id']];
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
        $table = match ($row['channel']) { 'whatsapp' => 'whatsapp_messages', 'email' => 'email_messages', default => 'sms_messages' };
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
        if (!WhatsAppService::reserve('wa:retry:' . $channel . ':' . $id)) throw new DomainException('Please wait 60 seconds before retrying.');
        if (in_array($row['purpose'], ['warranty_free', 'warranty_paid'], true) || $row['event_name'] === 'otp') {
            $challenge = db_fetch_one('SELECT mobile, purpose FROM otp_challenges WHERE id = ?', [$row['reference_id']]);
            $result = OtpService::sendOtp($challenge['mobile'], $challenge['purpose'], $channel === 'sms' ? 'sms' : 'auto');
            unset($result['debug_code']); // An admin delivery page must never disclose the customer's code.
            return $result;
        }
        // Retry means "send this again the same way", so the provider comes
        // from the row rather than from the top of the chain. Without this, an
        // operator running WhatsApp through MSG91 could only retry through Meta.
        if ($row['event_name'] === 'warranty_received') {
            $registration = db_fetch_one('SELECT * FROM warranty_registrations WHERE id = ?', [$row['reference_id']]);
            return self::resend($row, (string) $registration['mobile'], (string) ($registration['email'] ?? ''),
                [$registration['customer_name'], $registration['reference_no']], (string) $registration['id']);
        }
        $lead = db_fetch_one('SELECT * FROM website_leads WHERE id = ?', [$row['reference_id']]);

        return self::resend($row, (string) $lead['phone'], (string) ($lead['email'] ?? ''),
            WhatsAppService::leadParams($lead, $row['event_name']), (string) $lead['id'], ['lead' => $lead]);
    }

    /** Re-sends one logged message through the provider that produced it. */
    private static function resend(array $row, string $mobile, string $email, array $params, string $reference, array $payload = []): array
    {
        $provider = match ($row['channel']) {
            'whatsapp' => 'whatsapp.' . (($row['provider'] ?? '') !== '' ? $row['provider'] : 'meta'),
            'email' => 'email.smtp',
            default => 'sms.' . (($row['provider'] ?? '') !== '' ? $row['provider'] : 'log'),
        };

        $spec = MessagingRegistry::spec($provider);
        $transport = MessagingRegistry::transport($provider);
        if (!$spec || !$transport) {
            throw new DomainException('No transport is available for the provider this message was sent with.');
        }

        $result = $transport->send([
            'event' => $row['event_name'],
            'recipient' => $spec['recipient'] === 'email' ? $email : $mobile,
            'reference' => $reference,
            'attemptGroup' => $row['attempt_group'] ?: Uuid::v4(),
            'position' => (int) ($row['chain_position'] ?? 0),
            'params' => $params,
            'payload' => $payload,
        ]);

        return $result + ['message' => $result['success'] ? 'Message accepted for delivery.' : 'The provider rejected the message.'];
    }
}
