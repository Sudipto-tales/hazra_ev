<?php

final class ApiResponse
{
    public static function json(
        mixed $data = null,
        int $status = 200,
        array $headers = [],
    ): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode(
            value: $data,
            flags: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit;
    }

    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): never {
        self::json(
            data: ['success' => true, 'message' => $message, 'data' => $data],
            status: $status,
        );
    }

    public static function created(mixed $data = null, string $message = 'Created'): never
    {
        self::success(data: $data, message: $message, status: 201);
    }

    public static function error(
        string $message = 'Error',
        int $status = 400,
        array $errors = [],
    ): never {
        self::json(
            data: [
                'success' => false,
                'message' => $message,
                ...($errors ? ['errors' => $errors] : []),
            ],
            status: $status,
        );
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error(message: $message, status: 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error(message: $message, status: 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error(message: $message, status: 403);
    }

    public static function validationError(array $errors): never
    {
        self::error(message: 'Validation failed', status: 422, errors: $errors);
    }
}
