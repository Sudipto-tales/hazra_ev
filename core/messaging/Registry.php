<?php

require_once __DIR__ . '/CredentialStore.php';

/**
 * The one description of every messaging provider: what it sends, which fields
 * it needs, and how its webhook is wired.
 *
 * The admin form is generated from this, the validation on save reads this, the
 * health panel reads this, and the router asks this whether a step is usable.
 * Adding a provider is therefore a transport class plus one entry here — there
 * is no second list to keep in step, and no UI edit.
 *
 * Field types: text, number, select (with options), secret (write-only, masked
 * in every response) and generated (a secret this server creates for the
 * operator to paste into the provider's panel).
 *
 * `required` fields decide `configured()`. `required_for` marks a field that is
 * only needed for a capability — a webhook, say — so a provider can be
 * send-ready while its delivery reports are not yet wired.
 */
final class MessagingRegistry
{
    public const EVENTS = ['otp', 'lead_received', 'lead_approved', 'lead_rejected', 'warranty_received'];
    public const CHANNELS = ['email', 'whatsapp', 'sms'];

    public const EVENT_LABELS = [
        'otp' => 'Warranty OTP',
        'lead_received' => 'Enquiry received',
        'lead_approved' => 'Enquiry approved',
        'lead_rejected' => 'Enquiry rejected',
        'warranty_received' => 'Warranty registered',
    ];

    private static ?array $specs = null;

    /** @return array<string,array> provider key => spec */
    public static function specs(): array
    {
        if (self::$specs !== null) {
            return self::$specs;
        }

        $templateFields = static function (string $prefix, bool $required): array {
            $labels = [
                'otp' => 'OTP authentication template',
                'lead_received' => 'Enquiry acknowledgement template',
                'lead_approved' => 'Enquiry approval template',
                'lead_rejected' => 'Enquiry rejection template',
                'warranty_received' => 'Warranty registration template',
            ];
            $fields = [];
            foreach ($labels as $event => $label) {
                $fields[] = [
                    'key' => 'template_' . $event,
                    'label' => $label,
                    'type' => 'text',
                    'required' => false,
                    'required_for' => $required ? 'event:' . $event : null,
                    'legacy' => $prefix === 'wa' ? 'wa_template_' . $event : null,
                    'help' => 'Exact approved template name. An event with no template name is skipped.',
                ];
            }

            return $fields;
        };

        self::$specs = [
            // ---------------------------------------------------------- email
            'email.smtp' => [
                'key' => 'email.smtp',
                'channel' => 'email',
                'label' => 'Email (SMTP)',
                'kind' => 'direct',
                'events' => self::EVENTS,
                'recipient' => 'email',
                'fields' => [
                    ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'required' => true, 'env' => 'SMTP_HOST', 'legacy' => 'smtp_host', 'placeholder' => 'smtp.gmail.com'],
                    ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'required' => true, 'env' => 'SMTP_PORT', 'legacy' => 'smtp_port', 'default' => '587'],
                    ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'required' => false, 'env' => 'SMTP_ENCRYPTION', 'legacy' => 'smtp_encryption', 'default' => 'tls',
                        'options' => [['tls', 'STARTTLS (587)'], ['ssl', 'SSL/TLS (465)'], ['none', 'None']]],
                    ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'env' => 'SMTP_USERNAME', 'legacy' => 'smtp_username'],
                    ['key' => 'password', 'label' => 'Password / app secret', 'type' => 'secret', 'required' => true, 'env' => 'SMTP_PASSWORD', 'legacy' => 'smtp_password',
                        'help' => 'Stored encrypted. For Gmail this is an app password, not the account password.'],
                    ['key' => 'from_email', 'label' => 'From address', 'type' => 'text', 'required' => true, 'env' => 'SMTP_FROM_EMAIL', 'legacy' => 'smtp_from_email'],
                    ['key' => 'from_name', 'label' => 'From name', 'type' => 'text', 'required' => false, 'env' => 'SMTP_FROM_NAME', 'legacy' => 'smtp_from_name', 'default' => 'Hazra EV'],
                ],
                'webhook' => null,
                'notes' => [
                    'SMTP has no delivery callback. An email send stops at "accepted" — a later bounce is invisible to this panel.',
                    'Needs nothing else: no webhook URL, no secret, no provider account beyond the mailbox.',
                ],
            ],

            // ------------------------------------------------------- whatsapp
            'whatsapp.meta' => [
                'key' => 'whatsapp.meta',
                'channel' => 'whatsapp',
                'label' => 'WhatsApp — Meta Cloud API (direct)',
                'kind' => 'direct',
                'events' => self::EVENTS,
                'recipient' => 'mobile',
                'fields' => array_merge([
                    ['key' => 'access_token', 'label' => 'Access token', 'type' => 'secret', 'required' => true, 'env' => 'WHATSAPP_ACCESS_TOKEN',
                        'help' => 'A System User permanent token from the Meta app. A 24-hour test token will stop working tomorrow.'],
                    ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'type' => 'text', 'required' => true, 'env' => 'WHATSAPP_PHONE_NUMBER_ID',
                        'pattern' => '^\d+$', 'help' => 'Digits only, from WhatsApp → API Setup. Not the phone number itself.'],
                    ['key' => 'graph_version', 'label' => 'Graph API version', 'type' => 'text', 'required' => true, 'env' => 'WHATSAPP_GRAPH_VERSION',
                        'default' => 'v21.0', 'pattern' => '^v\d+\.\d+$'],
                    ['key' => 'app_secret', 'label' => 'App secret', 'type' => 'secret', 'required' => false, 'required_for' => 'webhook', 'env' => 'WHATSAPP_APP_SECRET',
                        'help' => 'Validates the X-Hub-Signature-256 HMAC on inbound events. Without it every delivery report is rejected.'],
                    ['key' => 'verify_token', 'label' => 'Webhook verify token', 'type' => 'generated', 'required' => false, 'required_for' => 'webhook', 'env' => 'WHATSAPP_VERIFY_TOKEN',
                        'help' => 'Generate here, then paste the same value into Meta\'s webhook configuration.'],
                    ['key' => 'language', 'label' => 'Template language', 'type' => 'text', 'required' => false, 'legacy' => 'wa_language', 'default' => 'en_US'],
                ], $templateFields('wa', true)),
                'webhook' => [
                    'path' => 'api/v1/whatsapp/webhook',
                    'methods' => 'GET (verification) + POST (events)',
                    'auth' => 'HMAC X-Hub-Signature-256 with the app secret',
                    'secret_field' => 'verify_token',
                    'steps' => [
                        'Meta app → WhatsApp → Configuration → Webhook → Edit.',
                        'Paste the callback URL above and the generated verify token, then Verify and save.',
                        'Subscribe to the `messages` field — delivery reports arrive on it.',
                    ],
                ],
                'notes' => [
                    'Not required: WABA ID, App ID, business portfolio ID, template namespace, sender display name — none of those endpoints are called.',
                    'Templates must exist and be approved in Meta with body parameters in the order documented in website/docs/whatsapp.md.',
                ],
            ],

            'whatsapp.msg91' => [
                'key' => 'whatsapp.msg91',
                'channel' => 'whatsapp',
                'label' => 'WhatsApp — via MSG91 (indirect)',
                'kind' => 'indirect',
                'events' => self::EVENTS,
                'recipient' => 'mobile',
                'fields' => array_merge([
                    ['key' => 'auth_key', 'label' => 'MSG91 authkey', 'type' => 'secret', 'required' => true, 'env' => 'MSG91_AUTH_KEY',
                        'help' => 'The same authkey as MSG91 SMS if it is one account.'],
                    ['key' => 'integrated_number', 'label' => 'Integrated WhatsApp number', 'type' => 'text', 'required' => true,
                        'pattern' => '^\d{10,15}$', 'help' => 'Country code and number, digits only — the number registered with MSG91.'],
                    ['key' => 'namespace', 'label' => 'Template namespace', 'type' => 'text', 'required' => false,
                        'help' => 'Shown beside the template in the MSG91 panel. Leave blank if your account does not use one.'],
                    ['key' => 'language', 'label' => 'Template language', 'type' => 'text', 'required' => false, 'default' => 'en'],
                    ['key' => 'webhook_token', 'label' => 'Webhook header token', 'type' => 'generated', 'required' => false, 'required_for' => 'webhook',
                        'help' => 'Generate here, then set it as the X-WA-Webhook-Token custom header in MSG91.'],
                ], $templateFields('msg91', true)),
                'webhook' => [
                    'path' => 'api/v1/whatsapp/webhook/msg91',
                    'methods' => 'POST',
                    'auth' => 'Shared secret in the X-WA-Webhook-Token header',
                    'secret_field' => 'webhook_token',
                    'steps' => [
                        'MSG91 → Webhook (New) → Add webhook.',
                        'Event: Outbound Report Received. "Outbound Request Received" only logs Sent and carries no delivery result.',
                        'URL: the callback above. Add the custom header X-WA-Webhook-Token with the generated token.',
                        'Bulk sends produce one call per recipient, which is what this page expects.',
                    ],
                ],
                'notes' => [
                    'Not required: Meta app secret, verify token or phone number ID — MSG91 holds the Meta side of the integration.',
                    'MSG91\'s webhook times out after 5 seconds and is never retried, so this handler does one indexed update and returns.',
                ],
            ],

            // ------------------------------------------------------------ sms
            'sms.msg91' => [
                'key' => 'sms.msg91',
                'channel' => 'sms',
                'label' => 'SMS — MSG91',
                'kind' => 'direct',
                'events' => ['otp'],
                'recipient' => 'mobile',
                'fields' => [
                    ['key' => 'auth_key', 'label' => 'MSG91 authkey', 'type' => 'secret', 'required' => true, 'env' => 'MSG91_AUTH_KEY'],
                    ['key' => 'template_id', 'label' => 'OTP template ID', 'type' => 'text', 'required' => true, 'env' => 'MSG91_TEMPLATE_ID',
                        'help' => 'From MSG91 → OTP. The sender ID and DLT entity ID live on the template, not here.'],
                    ['key' => 'webhook_token', 'label' => 'Webhook header token', 'type' => 'generated', 'required' => false, 'required_for' => 'webhook',
                        'env' => 'SMS_MSG91_WEBHOOK_TOKEN', 'help' => 'Set as the X-SMS-Webhook-Token custom header in MSG91.'],
                ],
                'webhook' => [
                    'path' => 'api/v1/sms/webhook/msg91',
                    'methods' => 'POST',
                    'auth' => 'Shared secret in the X-SMS-Webhook-Token header',
                    'secret_field' => 'webhook_token',
                    'steps' => [
                        'MSG91 → OTP → Webhook → On Report Received.',
                        'URL: the callback above, with the X-SMS-Webhook-Token header set to the generated token.',
                        'Body: {"CRQID":"{{CRQID}}","requestId":"{{requestId}}","telNum":"{{telNum}}","status":"{{status}}","failureReason":"{{failureReason}}"}',
                        'Report status 1 means delivered, 2 means failed.',
                    ],
                ],
                'notes' => ['Not required: sender ID, DLT entity ID — both are attached to the approved template.'],
            ],

            'sms.fast2sms' => [
                'key' => 'sms.fast2sms',
                'channel' => 'sms',
                'label' => 'SMS — Fast2SMS',
                'kind' => 'direct',
                'events' => ['otp'],
                'recipient' => 'mobile',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'required' => true, 'env' => 'FAST2SMS_API_KEY',
                        'help' => 'Dev API key from the Fast2SMS dashboard; sent as the Authorization header.'],
                    ['key' => 'route', 'label' => 'Route', 'type' => 'select', 'required' => true, 'default' => 'otp', 'env' => 'FAST2SMS_ROUTE',
                        'options' => [['otp', 'OTP — fixed "Your OTP: ###" text, no DLT needed'], ['dlt', 'DLT — your approved template and sender ID']]],
                    ['key' => 'sender_id', 'label' => 'DLT sender ID', 'type' => 'text', 'required' => false, 'required_for' => 'route:dlt', 'env' => 'FAST2SMS_SENDER_ID',
                        'pattern' => '^[A-Za-z0-9]{3,11}$', 'help' => 'Six-character registered DLT sender ID. Only used on the DLT route.'],
                    ['key' => 'message_id', 'label' => 'DLT message / template ID', 'type' => 'text', 'required' => false, 'required_for' => 'route:dlt', 'env' => 'FAST2SMS_MESSAGE_ID',
                        'help' => 'The approved message ID Fast2SMS assigned to your DLT template. Only used on the DLT route.'],
                ],
                'webhook' => null,
                'notes' => [
                    'Send-only in this build: Fast2SMS does not publish its delivery-report payload or headers, so its sends stay at "accepted" and never reach "delivered".',
                    'Fast2SMS does not sign requests, so a delivery-report route cannot be authenticated the way MSG91 and Twilio are.',
                    'Not required: DLT entity ID — it stays in the Fast2SMS panel.',
                ],
            ],

            'sms.twilio' => [
                'key' => 'sms.twilio',
                'channel' => 'sms',
                'label' => 'SMS — Twilio',
                'kind' => 'direct',
                'events' => ['otp'],
                'recipient' => 'mobile',
                'fields' => [
                    ['key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text', 'required' => true, 'env' => 'TWILIO_SID', 'pattern' => '^AC[a-fA-F0-9]{32}$'],
                    ['key' => 'auth_token', 'label' => 'Auth token', 'type' => 'secret', 'required' => true, 'env' => 'TWILIO_TOKEN'],
                    ['key' => 'from', 'label' => 'From number', 'type' => 'text', 'required' => true, 'env' => 'TWILIO_FROM', 'placeholder' => '+1...'],
                    ['key' => 'callback_url', 'label' => 'Status callback URL', 'type' => 'text', 'required' => false, 'required_for' => 'webhook', 'env' => 'SMS_TWILIO_CALLBACK_URL',
                        'help' => 'Must match the externally visible URL character for character, subdirectory included, or every callback fails signature validation.'],
                ],
                'webhook' => [
                    'path' => 'api/v1/sms/webhook/twilio',
                    'methods' => 'POST',
                    'auth' => 'HMAC X-Twilio-Signature over the callback URL and all form fields',
                    'secret_field' => null,
                    'steps' => [
                        'Nothing to configure in Twilio: the callback URL is sent with each message.',
                        'Save the callback URL above into the field, exactly as shown — the sender appends its own message_id parameter.',
                    ],
                ],
                'notes' => ['Not required: messaging service SID, API key/secret — the account SID and auth token are enough.'],
            ],

            'sms.log' => [
                'key' => 'sms.log',
                'channel' => 'sms',
                'label' => 'SMS — development log',
                'kind' => 'direct',
                'events' => ['otp'],
                'recipient' => 'mobile',
                'fields' => [],
                'webhook' => null,
                'notes' => [
                    'Writes the code to the PHP error log and sends nothing. Refused outright unless APP_ENV is local, development or testing.',
                    'Useful as the last step of a chain while testing; it can never be a delivery confirmation.',
                ],
            ],
        ];

        return self::$specs;
    }

    public static function spec(string $provider): ?array
    {
        return self::specs()[$provider] ?? null;
    }

    public static function field(string $provider, string $fieldKey): ?array
    {
        foreach (self::spec($provider)['fields'] ?? [] as $field) {
            if ($field['key'] === $fieldKey) {
                return $field;
            }
        }

        return null;
    }

    public static function value(string $provider, string $fieldKey): string
    {
        $field = self::field($provider, $fieldKey);

        return $field ? MessagingCredentialStore::get($provider, $field) : '';
    }

    /** True when every unconditionally required field has a value. */
    public static function configured(string $provider): bool
    {
        $spec = self::spec($provider);
        if (!$spec) {
            return false;
        }

        foreach ($spec['fields'] as $field) {
            if (!empty($field['required']) && MessagingCredentialStore::get($provider, $field) === '') {
                return false;
            }
            // A conditionally required field — "sender_id when route is dlt".
            $condition = (string) ($field['required_for'] ?? '');
            if (str_contains($condition, ':') && !str_starts_with($condition, 'event:')) {
                [$otherKey, $expected] = explode(':', $condition, 2);
                if (self::value($provider, $otherKey) === $expected && MessagingCredentialStore::get($provider, $field) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /** Why a step will be skipped, in words the panel can show. Empty when it will run. */
    public static function skipReason(string $provider, string $event): string
    {
        $spec = self::spec($provider);
        if (!$spec) {
            return 'Unknown provider.';
        }
        if (!in_array($event, $spec['events'], true)) {
            return 'This provider does not carry ' . (self::EVENT_LABELS[$event] ?? $event) . ' messages.';
        }
        if (!self::configured($provider)) {
            return 'Credentials are incomplete.';
        }
        if ($provider === 'sms.log' && !in_array(env('APP_ENV'), ['local', 'development', 'testing'], true)) {
            return 'The development log is refused outside local, development and testing.';
        }
        if ($spec['channel'] === 'whatsapp' && self::value($provider, 'template_' . $event) === '') {
            return 'No approved template name is set for this event.';
        }

        return '';
    }

    /** True when the webhook side of a provider is fully wired. */
    public static function webhookReady(string $provider): bool
    {
        $spec = self::spec($provider);
        if (!$spec || empty($spec['webhook'])) {
            return false;
        }

        foreach ($spec['fields'] as $field) {
            if (($field['required_for'] ?? '') === 'webhook' && MessagingCredentialStore::get($provider, $field) === '') {
                return false;
            }
        }

        return true;
    }

    /** The externally visible callback URL, derived from APP_URL so it cannot drift. */
    public static function webhookUrl(string $provider): string
    {
        $spec = self::spec($provider);
        if (!$spec || empty($spec['webhook']['path'])) {
            return '';
        }

        return rtrim((string) env('APP_URL', ''), '/') . '/' . ltrim($spec['webhook']['path'], '/');
    }

    /** @return list<string> provider keys on one channel */
    public static function byChannel(string $channel): array
    {
        return array_keys(array_filter(self::specs(), static fn(array $spec): bool => $spec['channel'] === $channel));
    }

    /** @var array<string,MessagingTransport> */
    private static array $transports = [];

    /**
     * Substitutes a transport. The only supported use is a test standing in a
     * fake so a suite can exercise the chain without contacting a provider —
     * which is the house rule for these tests.
     */
    public static function override(string $provider, ?MessagingTransport $transport): void
    {
        if ($transport === null) {
            unset(self::$transports[$provider]);
            return;
        }

        self::$transports[$provider] = $transport;
    }

    /** Lazily loads and returns the transport for a provider. */
    public static function transport(string $provider): ?MessagingTransport
    {
        if (isset(self::$transports[$provider])) {
            return self::$transports[$provider];
        }

        $classes = [
            'email.smtp' => 'EmailSmtpTransport',
            'whatsapp.meta' => 'WhatsAppMetaTransport',
            'whatsapp.msg91' => 'WhatsAppMsg91Transport',
            'sms.msg91' => 'SmsProviderTransport',
            'sms.twilio' => 'SmsProviderTransport',
            'sms.fast2sms' => 'SmsProviderTransport',
            'sms.log' => 'SmsProviderTransport',
        ];

        if (!isset($classes[$provider])) {
            return null;
        }

        require_once __DIR__ . '/Transport.php';
        require_once __DIR__ . '/transports/' . $classes[$provider] . '.php';
        $class = $classes[$provider];

        return self::$transports[$provider] = new $class($provider);
    }
}
