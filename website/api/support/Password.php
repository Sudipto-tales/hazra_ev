<?php

/**
 * Everything that touches a credential, in one place.
 *
 * Three routes write passwords — employee create, admin reset, self change —
 * and they must agree on the hash cost, the length bounds and what happens to
 * existing sessions. Spreading that across three controllers is how one of them
 * ends up quietly weaker than the others.
 *
 * A generated password is a first-login credential, not a secret: it is shown
 * once, read off a screen and passed along. It is never stored in plaintext,
 * never returned a second time and never written to audit_log.
 */
final class Password
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    /**
     * No O/0, l/1/I, or 5/S. This gets dictated over a phone or copied off a
     * screen by someone who did not choose it, and an ambiguous glyph turns a
     * working credential into a support call.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRTUVWXYZabcdefghijkmnpqrtuvwxyz2346789';

    private const GENERATED_LENGTH = 12;

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < self::GENERATED_LENGTH; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /** Fails into the envelope; returns the password unchanged when it passes. */
    public static function validate(mixed $plain, string $field = 'password'): string
    {
        if (!is_string($plain)) {
            Envelope::invalid("{$field} must be a string", $field);
        }

        $length = strlen($plain);

        if ($length < self::MIN_LENGTH) {
            Envelope::invalid("{$field} must be at least " . self::MIN_LENGTH . ' characters', $field);
        }

        if ($length > self::MAX_LENGTH) {
            Envelope::invalid("{$field} must be " . self::MAX_LENGTH . ' characters or fewer', $field);
        }

        return $plain;
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    /**
     * `mustChange` is true exactly when the password was generated rather than
     * chosen — the account is reachable but nobody has picked its credential.
     */
    public static function set(string $userId, string $plain, bool $mustChange): void
    {
        db_execute(
            "UPDATE users SET password_hash = ?, must_change_password = ?, updated_at = ? WHERE id = ?",
            [self::hash($plain), $mustChange ? 1 : 0, Wire::now(), $userId],
        );
    }

    /**
     * Revokes the account's refresh tokens.
     *
     * `$keepTokenHash` spares one — the caller's own — so changing your password
     * signs out your other devices without signing you out of the one you are
     * holding. An admin reset passes nothing and kicks everything.
     */
    public static function revokeSessions(string $userId, ?string $keepTokenHash = null): void
    {
        $now = Wire::now();

        if ($keepTokenHash === null || $keepTokenHash === '') {
            db_execute(
                "UPDATE refresh_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL",
                [$now, $userId],
            );

            return;
        }

        db_execute(
            "UPDATE refresh_tokens SET revoked_at = ?
              WHERE user_id = ? AND revoked_at IS NULL AND token_hash != ?",
            [$now, $userId, $keepTokenHash],
        );
    }
}
