<?php
require_once __DIR__ . '/../support/V1Controller.php';
require_once __BASEDIR__ . '/core/messaging/Router.php';
require_once __BASEDIR__ . '/core/messaging/WebhookEventLog.php';
require_once __BASEDIR__ . '/core/Csrf.php';

/**
 * The admin panel's messaging configuration: providers, credentials, webhook
 * wiring and the per-event delivery chains.
 *
 * The response is generated from MessagingRegistry rather than hand-written, so
 * the form in the panel and the validation here can never disagree about which
 * fields a provider has. No secret value is ever in a response: a secret field
 * reports only whether it is configured, where the value came from, and a
 * masked hint. The one exception is a freshly generated token, returned once by
 * `rotate` because the operator has to paste it into the provider's panel.
 */
final class MessagingConfigController extends V1Controller
{
    public function show(): never
    {
        $this->requireAdmin();
        Envelope::noStore();

        Envelope::ok([
            'appKeyConfigured' => Secrets::available(),
            'appUrl' => rtrim((string) env('APP_URL', ''), '/'),
            'environment' => (string) env('APP_ENV', 'production'),
            'providers' => $this->providers(),
            'events' => $this->events(),
            'settings' => [
                'otp_reveal_enabled' => MessagingRouter::setting('otp_reveal_enabled', '0'),
                'webhook_retention_days' => MessagingRouter::setting('webhook_retention_days', '30'),
            ],
        ]);
    }

    /** @return list<array> every provider, its fields' state, and its webhook wiring */
    private function providers(): array
    {
        $providers = [];

        foreach (MessagingRegistry::specs() as $key => $spec) {
            $fields = [];
            foreach ($spec['fields'] as $field) {
                $fields[] = array_merge(
                    [
                        'key' => $field['key'],
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'required' => !empty($field['required']),
                        'requiredFor' => $field['required_for'] ?? null,
                        'help' => $field['help'] ?? '',
                        'placeholder' => $field['placeholder'] ?? '',
                        'options' => $field['options'] ?? null,
                        'envKey' => $field['env'] ?? null,
                    ],
                    MessagingCredentialStore::state($key, $field)
                );
            }

            $providers[] = [
                'key' => $key,
                'label' => $spec['label'],
                'channel' => $spec['channel'],
                'kind' => $spec['kind'],
                'events' => $spec['events'],
                'recipient' => $spec['recipient'],
                'configured' => MessagingRegistry::configured($key),
                'webhookReady' => MessagingRegistry::webhookReady($key),
                'webhookUrl' => MessagingRegistry::webhookUrl($key),
                'webhook' => $spec['webhook'],
                'notes' => $spec['notes'],
                'fields' => $fields,
                'health' => $this->health($key),
            ];
        }

        return $providers;
    }

    /**
     * What this provider has actually done lately. Three timestamps answer the
     * questions an operator asks when something looks wrong: did anything send,
     * did anything succeed, and has the provider ever called us back?
     */
    private function health(string $provider): array
    {
        $spec = MessagingRegistry::spec($provider);
        $table = match ($spec['channel']) {
            'whatsapp' => 'whatsapp_messages',
            'email' => 'email_messages',
            default => 'sms_messages',
        };
        $name = $spec['channel'] === 'whatsapp' ? explode('.', $provider)[1] : ($spec['channel'] === 'email' ? 'smtp' : explode('.', $provider)[1]);

        try {
            $sends = db_fetch_one(
                "SELECT MAX(created_at) AS last_send,
                        MAX(CASE WHEN status IN ('accepted', 'sent', 'delivered', 'read', 'logged') THEN created_at END) AS last_success,
                        SUM(CASE WHEN status = 'failed' AND created_at >= ? THEN 1 ELSE 0 END) AS failures
                   FROM {$table} WHERE provider = ?" . ($spec['channel'] === 'whatsapp' && $name === 'meta' ? " OR provider IS NULL OR provider = ''" : ''),
                [gmdate('Y-m-d\TH:i:s\Z', time() - 86400), $name]
            ) ?: [];
            $hook = db_fetch_one('SELECT MAX(received_at) AS last_event, MAX(CASE WHEN signature_valid = 1 THEN received_at END) AS last_valid FROM webhook_events WHERE provider_key = ?', [$provider]) ?: [];
        } catch (Throwable $e) {
            $sends = $hook = [];
        }

        return [
            'lastSendAt' => $sends['last_send'] ?? null,
            'lastSuccessAt' => $sends['last_success'] ?? null,
            'failures24h' => (int) ($sends['failures'] ?? 0),
            'lastWebhookAt' => $hook['last_event'] ?? null,
            'lastVerifiedWebhookAt' => $hook['last_valid'] ?? null,
        ];
    }

    /** @return list<array> each event, its mode, and its annotated chain */
    private function events(): array
    {
        $events = [];

        foreach (MessagingRegistry::EVENTS as $event) {
            $events[] = [
                'key' => $event,
                'label' => MessagingRegistry::EVENT_LABELS[$event],
                'mode' => MessagingRouter::mode($event),
                'steps' => MessagingRouter::chain($event),
            ];
        }

        return $events;
    }

    public function update(): never
    {
        $this->requireAdmin();
        $this->guardToken();

        $body = ApiRequest::body();
        if (!is_array($body)) {
            Envelope::invalid('The configuration payload must be a JSON object.');
        }

        $admin = Ctx::principal()['id'] ?? null;
        $saved = [];

        // ------------------------------------------------- credentials
        foreach ((array) ($body['providers'] ?? []) as $providerKey => $values) {
            $spec = MessagingRegistry::spec((string) $providerKey);
            if (!$spec) {
                Envelope::invalid('Unknown provider: ' . $providerKey);
            }
            if (!is_array($values)) {
                Envelope::invalid('Provider settings must be an object: ' . $providerKey);
            }

            foreach ($values as $fieldKey => $value) {
                $field = MessagingRegistry::field((string) $providerKey, (string) $fieldKey);
                if (!$field) {
                    Envelope::invalid('Unknown field ' . $fieldKey . ' for ' . $providerKey);
                }

                $value = is_scalar($value) ? trim((string) $value) : '';
                // A blank secret means "keep what is stored" — the form posts
                // every field on every save and cannot echo a stored secret.
                if ($value === '') {
                    continue;
                }

                $this->validateField($spec, $field, $value);

                try {
                    MessagingCredentialStore::set((string) $providerKey, $field, $value, $admin);
                } catch (RuntimeException $e) {
                    Envelope::fail('APP_KEY_MISSING', $e->getMessage(), 409);
                }
                $saved[] = $providerKey . '.' . $fieldKey;
            }
        }

        // ------------------------------------------------- clearing a field
        foreach ((array) ($body['clear'] ?? []) as $entry) {
            // A provider key contains a dot itself (sms.msg91), so the field is
            // whatever follows the LAST dot: "sms.msg91.auth_key".
            $providerKey = (string) substr((string) $entry, 0, (int) strrpos((string) $entry, '.'));
            $fieldKey = (string) substr((string) $entry, (int) strrpos((string) $entry, '.') + 1);
            if (!MessagingRegistry::field($providerKey, $fieldKey)) {
                Envelope::invalid('Unknown field to clear: ' . $entry);
            }
            MessagingCredentialStore::forget($providerKey, $fieldKey);
            $saved[] = 'cleared:' . $entry;
        }

        // ------------------------------------------------- chains
        foreach ((array) ($body['chains'] ?? []) as $event => $steps) {
            if (!in_array((string) $event, MessagingRegistry::EVENTS, true)) {
                Envelope::invalid('Unknown event: ' . $event);
            }
            if (!is_array($steps)) {
                Envelope::invalid('A chain must be a list of steps: ' . $event);
            }

            $clean = [];
            $seen = [];
            foreach ($steps as $index => $step) {
                $providerKey = (string) ($step['provider'] ?? '');
                if (!MessagingRegistry::spec($providerKey)) {
                    Envelope::invalid('Unknown provider in the ' . $event . ' chain: ' . $providerKey);
                }
                if (isset($seen[$providerKey])) {
                    Envelope::invalid('A provider can only appear once in a chain: ' . $providerKey);
                }
                $seen[$providerKey] = true;
                $clean[] = [
                    'provider' => $providerKey,
                    'position' => ($index + 1) * 10,
                    'enabled' => !empty($step['enabled']),
                ];
            }

            MessagingRouter::saveChain((string) $event, $clean);
            $saved[] = 'chain:' . $event;
        }

        // ------------------------------------------------- modes
        foreach ((array) ($body['modes'] ?? []) as $event => $mode) {
            if (!in_array((string) $event, MessagingRegistry::EVENTS, true)) {
                Envelope::invalid('Unknown event: ' . $event);
            }
            if (!in_array((string) $mode, [MessagingRouter::MODE_FIRST, MessagingRouter::MODE_ALL], true)) {
                Envelope::invalid('A chain mode must be first_success or all.');
            }
            MessagingRouter::saveSetting('chain_mode_' . $event, (string) $mode);
            $saved[] = 'mode:' . $event;
        }

        // ------------------------------------------------- settings
        foreach ((array) ($body['settings'] ?? []) as $key => $value) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($key === 'otp_reveal_enabled') {
                if (!in_array($value, ['0', '1'], true)) {
                    Envelope::invalid('otp_reveal_enabled must be 0 or 1.');
                }
                MessagingRouter::saveSetting('otp_reveal_enabled', $value);
            } elseif ($key === 'webhook_retention_days') {
                if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 365) {
                    Envelope::invalid('webhook_retention_days must be between 1 and 365.');
                }
                MessagingRouter::saveSetting('webhook_retention_days', $value);
            } else {
                Envelope::invalid('Unknown messaging setting: ' . $key);
            }
            $saved[] = 'setting:' . $key;
        }

        MessagingCredentialStore::flush();
        Envelope::ok(['message' => 'Messaging configuration saved.', 'saved' => $saved]);
    }

    /** Shape rules from the registry, applied before anything is stored. */
    private function validateField(array $spec, array $field, string $value): void
    {
        if (strlen($value) > 1024) {
            Envelope::invalid($field['label'] . ' is too long.');
        }

        if (($field['type'] ?? '') === 'select') {
            $allowed = array_map(static fn(array $option): string => (string) $option[0], $field['options'] ?? []);
            if (!in_array($value, $allowed, true)) {
                Envelope::invalid($field['label'] . ' must be one of: ' . implode(', ', $allowed));
            }
        }

        if (($field['type'] ?? '') === 'number' && !ctype_digit($value)) {
            Envelope::invalid($field['label'] . ' must be a number.');
        }

        if (!empty($field['pattern']) && !preg_match('/' . $field['pattern'] . '/', $value)) {
            Envelope::invalid($field['label'] . ' is not in the expected format.');
        }

        // Template names reach Meta and MSG91 verbatim; the providers only
        // accept lowercase, digits and underscores, so reject early with a
        // readable message instead of a provider rejection later.
        if (str_starts_with($field['key'], 'template_') && !preg_match('/^[a-z0-9_]+$/', $value)) {
            Envelope::invalid($field['label'] . ' must be lowercase letters, digits and underscores only — the exact approved template name.');
        }

        if ($field['key'] === 'callback_url' && !str_starts_with($value, 'https://')) {
            Envelope::invalid('The status callback URL must be an https URL.');
        }

        if (in_array($field['key'], ['from_email', 'username'], true) && $spec['channel'] === 'email'
            && $field['key'] === 'from_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            Envelope::invalid('The From address must be a valid email address.');
        }
    }

    /**
     * Generates a webhook secret server-side and returns it ONCE.
     *
     * This is the only response in the system that carries a secret in the
     * clear, and it has to: the operator needs the value to paste into Meta or
     * MSG91. It is never readable again — afterwards the panel shows only a
     * masked hint.
     */
    public function rotateSecret(): never
    {
        $this->requireAdmin();
        $this->guardToken();
        Envelope::noStore();

        $providerKey = (string) $this->param('provider');
        $spec = MessagingRegistry::spec($providerKey);
        if (!$spec) {
            Envelope::notFound('NOT_FOUND', 'Unknown provider.');
        }

        $body = ApiRequest::body();
        $fieldKey = (string) (is_array($body) ? ($body['field'] ?? '') : '');
        if ($fieldKey === '') {
            $fieldKey = (string) ($spec['webhook']['secret_field'] ?? '');
        }

        $field = MessagingRegistry::field($providerKey, $fieldKey);
        if (!$field || ($field['type'] ?? '') !== 'generated') {
            Envelope::invalid('This provider has no generated secret to rotate.');
        }

        $token = Secrets::token(24);
        try {
            MessagingCredentialStore::set($providerKey, $field, $token, Ctx::principal()['id'] ?? null);
        } catch (RuntimeException $e) {
            Envelope::fail('APP_KEY_MISSING', $e->getMessage(), 409);
        }

        Envelope::ok([
            'field' => $fieldKey,
            'secret' => $token,
            'notice' => 'Copy this now and paste it into the provider\'s panel. It cannot be shown again.',
            'webhookUrl' => MessagingRegistry::webhookUrl($providerKey),
        ]);
    }

    /**
     * Sends one real message through one provider, recorded as a test.
     *
     * Real on purpose: the only question worth answering here is whether the
     * credentials work end to end, and a simulated send answers nothing. The
     * row is flagged is_test so these never inflate delivery figures.
     */
    public function test(): never
    {
        $this->requireAdmin();
        $this->guardToken();

        $body = ApiRequest::body();
        $providerKey = (string) ($body['provider'] ?? '');
        $to = trim((string) ($body['to'] ?? ''));
        $event = (string) ($body['event'] ?? 'otp');

        $spec = MessagingRegistry::spec($providerKey);
        if (!$spec) {
            Envelope::invalid('Unknown provider: ' . $providerKey);
        }
        if (!in_array($event, $spec['events'], true)) {
            Envelope::invalid('This provider does not carry ' . (MessagingRegistry::EVENT_LABELS[$event] ?? $event) . ' messages.');
        }
        if ($to === '') {
            Envelope::invalid($spec['recipient'] === 'email' ? 'Enter an email address to send the test to.' : 'Enter a mobile number to send the test to.', 'to');
        }
        if ($reason = MessagingRegistry::skipReason($providerKey, $event)) {
            Envelope::fail('PROVIDER_NOT_READY', $reason, 409);
        }

        $transport = MessagingRegistry::transport($providerKey);
        if (!$transport) {
            Envelope::fail('PROVIDER_NOT_READY', 'No transport is implemented for this provider.', 409);
        }

        // A test code is a throwaway: it verifies nothing and matches no
        // challenge, so it can never be used to complete a real verification.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $result = $transport->send([
            'event' => $event,
            'recipient' => $to,
            'reference' => 'test:' . Uuid::v4(),
            'attemptGroup' => Uuid::v4(),
            'position' => 0,
            'isTest' => true,
            'code' => $code,
            'params' => $event === 'otp' ? [$code] : ['Test Customer', 'Test Drive', 'As arranged'],
            // Enquiry emails are rendered from a lead row, so a test of one
            // needs a stand-in. It is addressed to whoever asked for the test.
            'payload' => [
                'purpose' => 'warranty_free',
                'lead' => [
                    'id' => 'test', 'name' => 'Test Customer', 'type' => 'test_drive',
                    'phone' => $spec['recipient'] === 'mobile' ? $to : '', 'email' => $spec['recipient'] === 'email' ? $to : '',
                    'status' => 'new', 'admin_note' => 'This is a configuration test.', 'scheduled_at' => '',
                    'details_json' => '{"whatsapp_consent":true}',
                ],
            ],
        ]);

        if (empty($result['success'])) {
            Envelope::fail('TEST_SEND_FAILED', (string) ($result['error_detail'] ?? $result['message'] ?? 'The provider rejected the test message.'), 400);
        }

        Envelope::ok([
            'message' => 'Test message accepted by the provider. Watch Message Delivery for its delivery status.',
            'messageId' => $result['id'] ?? null,
        ]);
    }

    private function guardToken(): void
    {
        if (!ApiRequest::bearerToken() && !Csrf::validate()) {
            Envelope::forbidden('Invalid request token. Reload the page and try again.');
        }
    }
}
