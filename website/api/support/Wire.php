<?php

/**
 * The serialisation seam.
 *
 * The database stores enum values in snake_case (schema doc §2); the wire value
 * is the Dart enum `name`, which is camelCase. That mapping happens here, once,
 * in both directions — nowhere else in the API converts a case.
 *
 * Timestamps are ISO-8601 UTC with a trailing Z. Durations are integer seconds.
 * Distances are kilometres as a double. Money is never a number: `dealValue`
 * and `paymentReceived` stay the free text the seller typed.
 */
final class Wire
{
    public const TS = 'Y-m-d\TH:i:s\Z';

    /** snake_case database value -> camelCase wire value. */
    public static function enum(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    /** camelCase wire value -> snake_case database value. */
    public static function unenum(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    /** Accepts a wire enum, returns it as a database value only if allowed. */
    public static function enumIn(?string $value, array $allowed): ?string
    {
        $db = self::unenum($value);
        return $db !== null && in_array($db, $allowed, true) ? $db : null;
    }

    public static function now(): string
    {
        return gmdate(self::TS);
    }

    /** Normalises anything date-ish to the wire timestamp format. */
    public static function ts(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = strtotime($value);
        return $time === false ? null : gmdate(self::TS, $time);
    }

    /** Normalises anything date-ish to a bare yyyy-mm-dd. */
    public static function date(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = strtotime($value);
        return $time === false ? null : gmdate('Y-m-d', $time);
    }

    public static function seconds(?string $from, ?string $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return max(0, strtotime($to) - strtotime($from));
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? round((float) $value, 4) : $default;
    }

    public static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    /** Free text stays free text — never coerced, only trimmed of nothing. */
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public static function json(?string $value, mixed $default = []): mixed
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return $decoded === null ? $default : $decoded;
    }

    /** Initials, matching Employee.initials in the Dart model exactly. */
    public static function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name))));

        if (!$parts) {
            return '?';
        }
        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 1));
        }

        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
}
