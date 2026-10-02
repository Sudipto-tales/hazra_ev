<?php

/**
 * Opaque cursor for the unbounded collections (API plan §1.3): reports,
 * notifications, locations, employees.
 *
 * Keyset, not offset — a report filed while the user is paging must not shift
 * every subsequent page by one.
 */
final class Cursor
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 200;

    public static function encode(array $keys): string
    {
        return rtrim(strtr(base64_encode(json_encode($keys)), '+/', '-_'), '=');
    }

    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $keys = json_decode($json, true);
        return is_array($keys) ? $keys : null;
    }

    /**
     * `limit=0` is not "no rows" — it is how a badge count is fetched: an empty
     * data array with meta.total populated.
     */
    public static function limit(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_LIMIT;
        }

        return max(0, min(self::MAX_LIMIT, (int) $raw));
    }

    public static function isCountOnly(mixed $raw): bool
    {
        return $raw !== null && $raw !== '' && (int) $raw === 0;
    }
}
