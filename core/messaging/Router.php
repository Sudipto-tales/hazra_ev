<?php

require_once __DIR__ . '/Registry.php';
require_once __DIR__ . '/Transport.php';
require_once __DIR__ . '/../WhatsAppService.php';

/**
 * Walks an event's configured provider chain.
 *
 * The chain replaces two hardcoded rules: "WhatsApp first, SMS if that fails"
 * for OTP, and "email, then WhatsApp if the mode says so" for enquiries. Both
 * are now rows in messaging_routes that an admin can reorder, and the seeded
 * defaults reproduce exactly what those rules did.
 *
 * Two modes. `first_success` stops at the first provider that ACCEPTS the
 * message — acceptance, not delivery, because a delivery report arrives later or
 * never. `all` runs every usable step, which is what "WhatsApp alongside email"
 * has always meant for enquiries.
 *
 * A step that cannot run is SKIPPED, not failed: no credentials, no template for
 * this event, no recipient of that kind, channel switched off. Skipping is what
 * makes an email-first default safe to ship — until SMTP credentials are
 * entered, the step is inert and the chain behaves as it did before.
 */
final class MessagingRouter
{
    public const MODE_FIRST = 'first_success';
    public const MODE_ALL = 'all';

    /** @return list<array> the event's steps, each annotated with whether it will run */
    public static function chain(string $event): array
    {
        $rows = [];
        try {
            $rows = db_fetch_all(
                'SELECT provider_key, position, enabled FROM messaging_routes WHERE event_name = ? ORDER BY position, provider_key',
                [$event]
            ) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }

        // Pre-migration, or an event somebody emptied: fall back to the order
        // the code used before chains existed, so nothing stops working.
        if (!$rows) {
            foreach (self::legacyChain($event) as $index => $provider) {
                $rows[] = ['provider_key' => $provider, 'position' => ($index + 1) * 10, 'enabled' => 1];
            }
        }

        $steps = [];
        foreach ($rows as $row) {
            $provider = (string) $row['provider_key'];
            $spec = MessagingRegistry::spec($provider);
            if (!$spec) {
                continue;
            }

            $enabled = (int) $row['enabled'] === 1;
            $reason = $enabled ? self::skipReason($provider, $event) : 'Switched off for this event.';

            $steps[] = [
                'provider' => $provider,
                'label' => $spec['label'],
                'channel' => $spec['channel'],
                'kind' => $spec['kind'],
                'position' => (int) $row['position'],
                'enabled' => $enabled,
                'willRun' => $reason === '',
                'skipReason' => $reason,
            ];
        }

        return $steps;
    }

    /** The pre-chain behaviour, used only when the table has nothing to say. */
    private static function legacyChain(string $event): array
    {
        if ($event === 'otp') {
            return ['whatsapp.meta', 'sms.' . (string) env('SMS_PROVIDER', 'log')];
        }

        return ['email.smtp', 'whatsapp.meta'];
    }

    /**
     * Registry reasons plus the two routing-level switches that are not a
     * provider's business: the WhatsApp channel toggle and, for enquiries, the
     * automatic-updates toggle. Both already gated these sends before chains.
     */
    public static function skipReason(string $provider, string $event): string
    {
        $spec = MessagingRegistry::spec($provider);

        if ($spec && $spec['channel'] === 'whatsapp') {
            if (!WhatsAppService::enabled()) {
                return 'WhatsApp is switched off in Settings.';
            }
            if (str_starts_with($event, 'lead_') && WhatsAppService::setting('wa_auto_leads', '0') !== '1') {
                return 'Automatic enquiry updates are switched off in Settings.';
            }
        }

        return MessagingRegistry::skipReason($provider, $event);
    }

    /** first_success unless the admin says otherwise; enquiries inherit wa_lead_mode. */
    public static function mode(string $event): string
    {
        $stored = self::setting('chain_mode_' . $event);
        if (in_array($stored, [self::MODE_FIRST, self::MODE_ALL], true)) {
            return $stored;
        }

        if (str_starts_with($event, 'lead_')) {
            return WhatsAppService::setting('wa_lead_mode', 'additional') === 'fallback' ? self::MODE_FIRST : self::MODE_ALL;
        }

        return self::MODE_FIRST;
    }

    /** A non-secret messaging setting, in website_settings under group `messaging`. */
    public static function setting(string $key, string $default = ''): string
    {
        try {
            $row = db_fetch_one("SELECT setting_value FROM website_settings WHERE group_name = 'messaging' AND setting_key = ?", [$key]);
        } catch (Throwable $e) {
            return $default;
        }

        return ($row['setting_value'] ?? '') !== '' ? (string) $row['setting_value'] : $default;
    }

    /** Writes one non-secret messaging setting. */
    public static function saveSetting(string $key, string $value): void
    {
        db_execute(
            "INSERT INTO website_settings (id, group_name, setting_key, setting_value, updated_at)
             VALUES (?, 'messaging', ?, ?, ?)
             ON CONFLICT(group_name, setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
            [Uuid::v4(), $key, $value, gmdate('Y-m-d\TH:i:s\Z')]
        );
    }

    /**
     * Replaces one event's chain. All-or-nothing per event: the rows are
     * rewritten, so a provider left out of the payload leaves the chain.
     *
     * @param list<array{provider: string, position: int, enabled: bool}> $steps
     */
    public static function saveChain(string $event, array $steps): void
    {
        db_execute('DELETE FROM messaging_routes WHERE event_name = ?', [$event]);
        $now = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($steps as $index => $step) {
            db_execute(
                'INSERT INTO messaging_routes (id, event_name, provider_key, position, enabled, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [Uuid::v4(), $event, $step['provider'], (int) ($step['position'] ?? ($index + 1) * 10), !empty($step['enabled']) ? 1 : 0, $now]
            );
        }
    }

    /**
     * Sends one event down its chain.
     *
     * @param array $context {
     *   reference: string (the lead / challenge / registration id these attempts belong to),
     *   mobile: string, email: string, params: list<string>, code: ?string,
     *   payload: array (lead row and OTP purpose, for the transports that need them),
     *   lock: ?string (60s duplicate guard, for admin-triggerable events),
     *   only: ?string (restrict to one channel — what OtpService's `sms` argument means),
     *   isTest: bool
     * }
     * @return array{success: bool, channel: ?string, attemptGroup: string, attempts: list<array>, message: string}
     */
    public static function dispatch(string $event, array $context): array
    {
        $group = $context['attemptGroup'] ?? Uuid::v4();
        $mode = self::mode($event);
        $only = $context['only'] ?? null;
        $attempts = [];
        $succeeded = null;

        // The 60-second duplicate guard covers WhatsApp only, and is taken
        // lazily just before the first WhatsApp attempt. Taking it up front
        // would let a held lock suppress the email as well, which is not what
        // it has ever meant: it exists so an automatic enquiry message and an
        // admin's manual one cannot both go out.
        $lock = (string) ($context['lock'] ?? '');
        $lockTaken = false;

        foreach (self::chain($event) as $step) {
            $spec = MessagingRegistry::spec($step['provider']);
            $recipient = ($spec['recipient'] ?? 'mobile') === 'email'
                ? trim((string) ($context['email'] ?? ''))
                : trim((string) ($context['mobile'] ?? ''));

            $reason = $step['skipReason'];
            if ($reason === '' && $only !== null && $spec['channel'] !== $only) {
                $reason = 'This request asked for ' . $only . ' only.';
            }
            if ($reason === '' && $recipient === '') {
                $reason = $spec['recipient'] === 'email'
                    ? 'No email address on file for this recipient.'
                    : 'No mobile number on file for this recipient.';
            }
            // Opt-in is required for every WhatsApp message except an OTP the
            // customer has just asked for themselves.
            if ($reason === '' && $spec['channel'] === 'whatsapp' && $event !== 'otp'
                && array_key_exists('whatsappConsent', $context['payload'] ?? [])
                && empty($context['payload']['whatsappConsent'])) {
                $reason = 'This customer has not opted in to WhatsApp updates.';
            }
            if ($reason === '' && $spec['channel'] === 'whatsapp' && $lock !== '' && !$lockTaken) {
                if (!WhatsAppService::reserve($lock)) {
                    $reason = 'A WhatsApp message for this event was sent in the last 60 seconds.';
                } else {
                    $lockTaken = true;
                }
            }

            if ($reason !== '') {
                $attempts[] = ['provider' => $step['provider'], 'channel' => $spec['channel'], 'skipped' => true, 'success' => false, 'reason' => $reason];
                continue;
            }

            $transport = MessagingRegistry::transport($step['provider']);
            if (!$transport) {
                $attempts[] = ['provider' => $step['provider'], 'channel' => $spec['channel'], 'skipped' => true, 'success' => false, 'reason' => 'No transport is implemented for this provider.'];
                continue;
            }

            try {
                $result = $transport->send([
                    'event' => $event,
                    'recipient' => $recipient,
                    'reference' => (string) ($context['reference'] ?? ''),
                    'attemptGroup' => $group,
                    'position' => $step['position'],
                    'isTest' => !empty($context['isTest']),
                    'params' => $context['params'] ?? [],
                    'code' => $context['code'] ?? null,
                    'payload' => $context['payload'] ?? [],
                ]);
            } catch (Throwable $e) {
                // A transport is not supposed to throw; if one does, the chain
                // continues rather than taking the caller's request down.
                error_log('[Messaging] ' . $step['provider'] . ' threw during ' . $event);
                $result = ['success' => false, 'error_code' => 'transport_exception', 'error_detail' => 'The provider adapter failed unexpectedly.'];
            }

            $attempts[] = [
                'provider' => $step['provider'],
                'channel' => $spec['channel'],
                'skipped' => false,
                'success' => (bool) $result['success'],
                'messageId' => $result['id'] ?? null,
                'errorCode' => $result['error_code'] ?? null,
                'reason' => $result['success'] ? '' : (string) ($result['error_detail'] ?? $result['message'] ?? 'The provider rejected the message.'),
            ];

            if ($result['success']) {
                $succeeded ??= $spec['channel'];
                if ($mode === self::MODE_FIRST) {
                    break;
                }
            }
        }

        $ran = array_filter($attempts, static fn(array $a): bool => !$a['skipped']);

        return [
            'success' => $succeeded !== null,
            'channel' => $succeeded,
            'attemptGroup' => $group,
            'attempts' => $attempts,
            'message' => $succeeded !== null
                ? 'Message accepted for delivery over ' . $succeeded . '.'
                : ($ran ? 'Every configured provider rejected the message.' : 'No provider is configured to carry this message.'),
        ];
    }
}
