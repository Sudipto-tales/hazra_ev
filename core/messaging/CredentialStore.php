<?php

require_once __DIR__ . '/../Secrets.php';

/**
 * Reads and writes provider configuration, secret and non-secret alike.
 *
 * Resolution order is always database → environment → empty. That ordering is
 * what makes this safe to deploy: every key the code used to read from env
 * (WHATSAPP_ACCESS_TOKEN, MSG91_AUTH_KEY, TWILIO_*, the webhook secrets) keeps
 * working untouched until somebody saves the same field in the admin panel, and
 * nothing has to be migrated out of PROD_ENV_FILE first.
 *
 * Plaintext of a secret field leaves this class in exactly one direction — into
 * the provider request. `state()` is what the admin API returns, and it carries
 * a masked hint, never the value.
 */
final class MessagingCredentialStore
{
    /** @var array<string,array<string,array>>|null provider => field => row */
    private static ?array $cache = null;

    /** @var array<string,string>|null website_settings, for pre-panel values */
    private static ?array $legacyCache = null;

    public static function flush(): void
    {
        self::$cache = null;
        self::$legacyCache = null;
    }

    /** @return array<string,array> field => row, for one provider */
    private static function rows(string $provider): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (db_fetch_all('SELECT * FROM messaging_credentials') ?: [] as $row) {
                    self::$cache[$row['provider_key']][$row['field_key']] = $row;
                }
            } catch (Throwable $e) {
                // Table absent (pre-migration) or unreadable: fall back to env.
                self::$cache = [];
            }
        }

        return self::$cache[$provider] ?? [];
    }

    /**
     * The usable value of one field. Never throws: an unreadable ciphertext
     * (wrong or missing APP_KEY) behaves like an unset field, so a key mishap
     * degrades to "provider not configured" instead of a fatal mid-send.
     */
    public static function get(string $provider, array $field): string
    {
        $row = self::rows($provider)[$field['key']] ?? null;

        if ($row) {
            $stored = ((int) ($row['is_secret'] ?? 1) === 1)
                ? Secrets::decrypt($row['value_cipher'] ?? null, Secrets::PURPOSE_CREDENTIAL)
                : (string) ($row['value_plain'] ?? '');

            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        }

        $legacy = self::legacy($field);
        if ($legacy !== '') {
            return $legacy;
        }

        $envKey = (string) ($field['env'] ?? '');
        $fallback = (string) ($field['default'] ?? '');

        return $envKey !== '' ? (string) env($envKey, $fallback) : $fallback;
    }

    /**
     * The value this field had before it moved here — a row in website_settings
     * written by the old Settings tabs (smtp_host, wa_language, wa_template_*).
     *
     * Without this, deploying would blank an operator's working SMTP and
     * WhatsApp template configuration until they retyped it. Read-only: saving
     * through the new panel writes to messaging_credentials, which wins.
     */
    private static function legacy(array $field): string
    {
        $key = (string) ($field['legacy'] ?? '');
        if ($key === '') {
            return '';
        }

        if (!isset(self::$legacyCache)) {
            self::$legacyCache = [];
            try {
                foreach (db_fetch_all('SELECT setting_key, setting_value FROM website_settings') ?: [] as $row) {
                    if (($row['setting_value'] ?? '') !== '') {
                        self::$legacyCache[$row['setting_key']] = (string) $row['setting_value'];
                    }
                }
            } catch (Throwable $e) {
                self::$legacyCache = [];
            }
        }

        return self::$legacyCache[$key] ?? '';
    }

    /** Where the value in play actually came from — shown in the panel so a surprise is diagnosable. */
    public static function source(string $provider, array $field): string
    {
        $row = self::rows($provider)[$field['key']] ?? null;
        if ($row) {
            $stored = ((int) ($row['is_secret'] ?? 1) === 1)
                ? Secrets::decrypt($row['value_cipher'] ?? null, Secrets::PURPOSE_CREDENTIAL)
                : (string) ($row['value_plain'] ?? '');
            if ($stored !== null && $stored !== '') {
                return 'database';
            }
            if ((int) ($row['is_secret'] ?? 1) === 1 && ($row['value_cipher'] ?? '') !== '') {
                return 'unreadable';
            }
        }

        if (self::legacy($field) !== '') {
            return 'settings';
        }

        $envKey = (string) ($field['env'] ?? '');
        if ($envKey !== '' && (string) env($envKey, '') !== '') {
            return 'environment';
        }

        return ($field['default'] ?? '') !== '' ? 'default' : 'none';
    }

    /**
     * What the admin API is allowed to say about a field. A non-secret carries
     * its value so the form can be edited; a secret carries a hint only.
     */
    public static function state(string $provider, array $field): array
    {
        $value = self::get($provider, $field);
        $source = self::source($provider, $field);
        $row = self::rows($provider)[$field['key']] ?? null;
        $secret = ($field['type'] ?? 'text') === 'secret' || ($field['type'] ?? '') === 'generated';

        return [
            'key' => $field['key'],
            'configured' => $value !== '',
            'source' => $source,
            'hint' => $secret ? ($row['hint'] ?? Secrets::hint($value)) : '',
            'value' => $secret ? '' : $value,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * Saves one field. A blank value is a no-op by design: the admin form sends
     * empty secret inputs on every save, and "leave the stored secret alone" is
     * the only sane reading of that. Clearing is `forget()`, an explicit action.
     *
     * @throws RuntimeException when a secret is saved without APP_KEY configured
     */
    public static function set(string $provider, array $field, string $value, ?string $adminId = null): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $secret = ($field['type'] ?? 'text') === 'secret' || ($field['type'] ?? '') === 'generated';
        if ($secret && !Secrets::available()) {
            throw new RuntimeException('APP_KEY is not configured on this server, so credentials cannot be stored. Generate one with `php vayu key:generate`, add it to the environment, and save again.');
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        db_execute(
            'INSERT INTO messaging_credentials (id, provider_key, field_key, is_secret, value_cipher, value_plain, hint, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(provider_key, field_key) DO UPDATE SET
                is_secret = excluded.is_secret, value_cipher = excluded.value_cipher, value_plain = excluded.value_plain,
                hint = excluded.hint, updated_by = excluded.updated_by, updated_at = excluded.updated_at',
            [
                Uuid::v4(), $provider, $field['key'], $secret ? 1 : 0,
                $secret ? Secrets::encrypt($value, Secrets::PURPOSE_CREDENTIAL) : null,
                $secret ? null : $value,
                $secret ? Secrets::hint($value) : '',
                $adminId, $now,
            ]
        );

        self::flush();
    }

    public static function forget(string $provider, string $fieldKey): void
    {
        db_execute('DELETE FROM messaging_credentials WHERE provider_key = ? AND field_key = ?', [$provider, $fieldKey]);
        self::flush();
    }

    /** Every field key this layer owns, for the guards in the generic settings endpoints. */
    public static function reservedSettingKeys(): array
    {
        $keys = [];
        foreach (MessagingRegistry::specs() as $spec) {
            foreach ($spec['fields'] as $field) {
                if (($field['type'] ?? 'text') === 'secret' || ($field['type'] ?? '') === 'generated') {
                    $keys[] = strtolower($spec['key'] . '.' . $field['key']);
                    if (!empty($field['env'])) {
                        $keys[] = strtolower($field['env']);
                    }
                    // The pre-panel website_settings key, so the generic
                    // settings endpoints can no longer write a credential
                    // there in plaintext (smtp_password being the one that
                    // mattered).
                    if (!empty($field['legacy'])) {
                        $keys[] = strtolower($field['legacy']);
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
