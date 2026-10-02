<?php

final class Cors
{
    public static function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed = env('CORS_ORIGINS', '*');

        if ($allowed !== '*') {
            $origins = array_map('trim', explode(',', $allowed));
            if (!in_array($origin, $origins, true)) {
                $origin = $origins[0];
            }
        }

        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Idempotency-Key, If-None-Match');
        header('Access-Control-Expose-Headers: ETag, Idempotent-Replay');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
