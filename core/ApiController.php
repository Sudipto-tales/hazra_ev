<?php

abstract class ApiController
{
    protected array $routeParams = [];

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    protected function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return ApiRequest::get($key, $default);
    }

    protected function body(): array
    {
        return ApiRequest::body();
    }

    protected function only(string ...$keys): array
    {
        return ApiRequest::only(...$keys);
    }

    protected function validate(array $rules): array
    {
        $validator = Validator::make(ApiRequest::body(), $rules);

        if ($validator->fails()) {
            ApiResponse::validationError($validator->errors());
        }

        return $validator->validated();
    }

    protected function user(): ?array
    {
        return JwtAuth::user();
    }

    protected function json(mixed $data, int $status = 200): never
    {
        ApiResponse::json($data, $status);
    }

    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        ApiResponse::success($data, $message, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): never
    {
        ApiResponse::error($message, $status, $errors);
    }
}
