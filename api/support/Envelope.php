<?php

/**
 * The one response shape (API plan §1.1).
 *
 *   {"data": …, "meta": {…}, "error": null}
 *   {"data": null, "meta": {…}, "error": {"code": …, "message": …, "field": …}}
 *
 * `code` is stable and machine-readable; `message` is never parsed by a client.
 * This is deliberately not core/ApiResponse.php — that class emits the
 * framework's {success, message, data} shape, which the contract does not use.
 */
final class Envelope
{
    /** Cache-Control values from API plan §1.4. */
    public const IMMUTABLE = 'private, max-age=86400';
    public const LIVE      = 'no-store';

    public static function ok(mixed $data, array $meta = [], array $headers = []): never
    {
        self::send(['data' => $data, 'meta' => self::meta($meta), 'error' => null], 200, $headers);
    }

    public static function created(mixed $data, array $meta = [], array $headers = []): never
    {
        self::send(['data' => $data, 'meta' => self::meta($meta), 'error' => null], 201, $headers);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function fail(
        string $code,
        string $message,
        int $status = 400,
        ?string $field = null,
    ): never {
        self::send([
            'data'  => null,
            'meta'  => self::meta([]),
            'error' => ['code' => $code, 'message' => $message, 'field' => $field],
        ], $status);
    }

    public static function notFound(string $code = 'NOT_FOUND', string $message = 'Resource not found'): never
    {
        self::fail($code, $message, 404);
    }

    public static function unauthorized(string $message = 'Authentication required'): never
    {
        self::fail('UNAUTHENTICATED', $message, 401);
    }

    /**
     * Passing subject=team with an employee token is a 403, not an empty list
     * (API plan §1.2). Silence would read as "the team has no members".
     */
    public static function forbidden(string $message = 'Not permitted for this role'): never
    {
        self::fail('FORBIDDEN', $message, 403);
    }

    public static function invalid(string $message, ?string $field = null): never
    {
        self::fail('VALIDATION_FAILED', $message, 422, $field);
    }

    /** Validator errors, flattened to the first failure and its field. */
    public static function validation(array $errors): never
    {
        $field = array_key_first($errors);
        $message = is_array($errors[$field]) ? ($errors[$field][0] ?? 'Invalid value') : (string) $errors[$field];

        self::fail('VALIDATION_FAILED', $message, 422, $field);
    }

    public static function conflict(string $code, string $message): never
    {
        self::fail($code, $message, 409);
    }

    /**
     * ETag short-circuit. Call before building an expensive payload when the
     * version is cheap to compute (config version, updated_at max), or right
     * before sending when it is not. Returns only when the client is stale.
     */
    public static function freshness(string $tag, string $cacheControl = self::IMMUTABLE): void
    {
        $etag = '"' . $tag . '"';
        $sent = trim((string) (ApiRequest::header('If-None-Match') ?? ''));

        header("ETag: {$etag}");
        header("Cache-Control: {$cacheControl}");

        if ($sent !== '' && ($sent === $etag || $sent === 'W/' . $etag)) {
            http_response_code(304);
            exit;
        }
    }

    public static function noStore(): void
    {
        header('Cache-Control: ' . self::LIVE);
    }

    private static function meta(array $meta): array
    {
        return $meta + ['serverTime' => Wire::now()];
    }

    private static function send(array $payload, int $status, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
        exit;
    }
}
