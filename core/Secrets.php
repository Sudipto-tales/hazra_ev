<?php

/**
 * Authenticated symmetric encryption for values that must live in the database
 * but must never be readable from it — provider credentials and live OTP codes.
 *
 * The key is APP_KEY in the environment and nowhere else: a database dump on its
 * own decrypts to nothing. AES-256-GCM is used rather than CBC so a tampered
 * ciphertext fails on the tag instead of decrypting to garbage, and every call
 * carries a `purpose` as additional authenticated data, so a credential
 * ciphertext cannot be moved into an OTP column and still open.
 *
 * `available()` is deliberately checked by callers before writing. Without a key
 * the store refuses to save and every reader falls back to the environment, so an
 * un-keyed deployment behaves exactly as it did before this layer existed rather
 * than half-working.
 *
 * Losing APP_KEY is not recoverable. It belongs in the PROD_ENV_FILE secret and a
 * password manager before anything is saved through the admin panel.
 */
final class Secrets
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const PREFIX = 'v1.';

    /** Purposes double as GCM additional data; they are not interchangeable. */
    public const PURPOSE_CREDENTIAL = 'messaging.credential';
    public const PURPOSE_OTP = 'otp.code';

    private static ?string $cachedKey = null;
    private static bool $keyResolved = false;

    /** The 32-byte key, or null when APP_KEY is missing or malformed. */
    public static function key(): ?string
    {
        if (self::$keyResolved) {
            return self::$cachedKey;
        }

        self::$keyResolved = true;
        self::$cachedKey = null;

        $raw = (string) env('APP_KEY', '');
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }

        $decoded = base64_decode(strtr(trim($raw), '-_', '+/'), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            error_log('[Secrets] APP_KEY is set but is not 32 bytes of base64; encryption is unavailable.');
            return null;
        }

        self::$cachedKey = $decoded;
        return self::$cachedKey;
    }

    /**
     * Forgets the resolved key. Needed where APP_KEY changes inside one
     * process: the tests, which must exercise both the keyed and the keyless
     * path, and the console after writing a key.
     */
    public static function flushKey(): void
    {
        self::$keyResolved = false;
        self::$cachedKey = null;
    }

    public static function available(): bool
    {
        return self::key() !== null && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /** Generates a key in the exact form APP_KEY expects. */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /** A URL- and header-safe random secret, for tokens an operator pastes into a provider panel. */
    public static function token(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes(max(16, $bytes))), '+/', '-_'), '=');
    }

    /**
     * @return string the packed ciphertext
     * @throws RuntimeException when no usable key is configured — callers check available() first
     */
    public static function encrypt(string $plaintext, string $purpose): string
    {
        $key = self::key();
        if ($key === null) {
            throw new RuntimeException('APP_KEY is not configured, so secrets cannot be stored.');
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipherText = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose, self::TAG_BYTES);
        if ($cipherText === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipherText);
    }

    /** Null on a missing key, a foreign key, tampering, or a purpose mismatch — never an exception. */
    public static function decrypt(?string $packed, string $purpose): ?string
    {
        $key = self::key();
        if ($key === null || $packed === null || !str_starts_with($packed, self::PREFIX)) {
            return null;
        }

        $blob = base64_decode(substr($packed, strlen(self::PREFIX)), true);
        if ($blob === false || strlen($blob) <= self::IV_BYTES + self::TAG_BYTES) {
            return null;
        }

        $iv = substr($blob, 0, self::IV_BYTES);
        $tag = substr($blob, self::IV_BYTES, self::TAG_BYTES);
        $cipherText = substr($blob, self::IV_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt($cipherText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose);

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * A non-secret preview for the admin UI: enough to tell two keys apart,
     * never enough to use. Short values are masked completely rather than
     * half-revealed, and anything that looks like an address keeps its domain
     * because that is the part an operator needs to recognise.
     */
    public static function hint(string $plaintext): string
    {
        $plaintext = trim($plaintext);
        if ($plaintext === '') {
            return '';
        }

        if (str_contains($plaintext, '@') && filter_var($plaintext, FILTER_VALIDATE_EMAIL)) {
            [$user, $domain] = explode('@', $plaintext, 2);
            return substr($user, 0, 1) . str_repeat('•', max(3, min(6, strlen($user) - 1))) . '@' . $domain;
        }

        if (strlen($plaintext) <= 8) {
            return str_repeat('•', strlen($plaintext));
        }

        return '••••' . substr($plaintext, -4);
    }
}
