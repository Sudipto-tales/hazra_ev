<?php

/**
 * RFC 4122 v4 identifiers. The schema doc specifies uuid primary keys; SQLite
 * has no gen_random_uuid(), so ids are generated here instead.
 */
final class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);   // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);   // variant 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }
}
