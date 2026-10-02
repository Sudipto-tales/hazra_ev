<?php

class Async
{
    private static array $deferred = [];
    private static bool $shutdownRegistered = false;

    public static function parallel(array $requests, array $defaultHeaders = []): array
    {
        if (!function_exists('curl_multi_init')) {
            return self::parallelFallback($requests, $defaultHeaders);
        }

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($requests as $key => $request) {
            if (is_string($request)) {
                $request = ['url' => $request, 'method' => 'GET'];
            }

            $url = $request['url'];
            $method = strtoupper($request['method'] ?? 'GET');
            $headers = array_merge(['Accept: application/json'], $defaultHeaders, $request['headers'] ?? []);
            $payload = $request['payload'] ?? null;
            $query = $request['query'] ?? [];

            if ($query) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, $request['timeout'] ?? 30);

            if ($payload !== null && $method !== 'GET') {
                if (is_array($payload)) {
                    $payload = json_encode($payload);
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            curl_multi_add_handle($multiHandle, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle);
            }
        } while ($running > 0);

        $results = [];
        foreach ($handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($error) {
                $results[$key] = ['success' => false, 'error' => $error, 'status' => 0];
            } else {
                $decoded = json_decode($response, true);
                $results[$key] = [
                    'success' => $httpCode >= 200 && $httpCode < 300,
                    'status' => $httpCode,
                    'data' => $decoded !== null ? $decoded : $response,
                ];
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        return $results;
    }

    public static function defer(callable $callback): void
    {
        self::$deferred[] = $callback;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'executeDeferredTasks']);
        }
    }

    public static function executeDeferredTasks(): void
    {
        if (empty(self::$deferred)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!headers_sent()) {
            header('Connection: close');
            header('Content-Length: ' . ob_get_length());
            ob_end_flush();
            flush();
        }

        foreach (self::$deferred as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                error_log("[Vayu Async] Deferred task failed: " . $e->getMessage());
            }
        }

        self::$deferred = [];
    }

    public static function all(array $tasks): array
    {
        $results = [];
        foreach ($tasks as $key => $task) {
            try {
                $results[$key] = ['success' => true, 'data' => $task()];
            } catch (\Throwable $e) {
                $results[$key] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        return $results;
    }

    private static function parallelFallback(array $requests, array $defaultHeaders): array
    {
        $results = [];
        foreach ($requests as $key => $request) {
            if (is_string($request)) {
                $request = ['url' => $request, 'method' => 'GET'];
            }
            $response = api_request(
                $request['url'],
                $request['method'] ?? 'GET',
                [
                    'headers' => array_merge($defaultHeaders, $request['headers'] ?? []),
                    'payload' => $request['payload'] ?? null,
                    'query' => $request['query'] ?? [],
                ]
            );
            $results[$key] = ['success' => true, 'data' => $response];
        }
        return $results;
    }
}
