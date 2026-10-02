<?php

final class ApiRequest
{
    private static ?array $parsedBody = null;

    public static function body(): array
    {
        if (self::$parsedBody !== null) {
            return self::$parsedBody;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        self::$parsedBody = match (true) {
            str_contains($contentType, 'application/json')
                => json_decode(file_get_contents('php://input'), true) ?? [],
            str_contains($contentType, 'application/x-www-form-urlencoded'),
            str_contains($contentType, 'multipart/form-data')
                => $_POST,
            default => [],
        };

        return self::$parsedBody;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::body()[$key] ?? $default;
    }

    public static function only(string ...$keys): array
    {
        return array_intersect_key(self::body(), array_flip($keys));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::body());
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$serverKey] ?? null;
    }

    public static function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public static function method(): HttpMethod
    {
        return HttpMethod::fromRequest();
    }
}
