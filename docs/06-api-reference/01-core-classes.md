# Core Classes Reference

## App (`core/App.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `render` | `App::render(string $name, array $data = [])` | `void` |
| `component` | `App::component(string $name, array $data = [])` | `string` |
| `exists` | `App::exists(string $name)` | `bool` |

## ApiController (`core/ApiController.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `setRouteParams` | `setRouteParams(array $params)` | `void` |
| `param` | `param(string $key, mixed $default = null)` | `mixed` |
| `input` | `input(string $key, mixed $default = null)` | `mixed` |
| `body` | `body()` | `array` |
| `only` | `only(string ...$keys)` | `array` |
| `validate` | `validate(array $rules)` | `array` |
| `user` | `user()` | `?array` |
| `json` | `json(mixed $data, int $status = 200)` | `never` |
| `success` | `success(mixed $data = null, string $message = 'OK', int $status = 200)` | `never` |
| `error` | `error(string $message, int $status = 400, array $errors = [])` | `never` |

## ApiRequest (`core/ApiRequest.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `body` | `ApiRequest::body()` | `array` |
| `get` | `ApiRequest::get(string $key, mixed $default = null)` | `mixed` |
| `only` | `ApiRequest::only(string ...$keys)` | `array` |
| `has` | `ApiRequest::has(string $key)` | `bool` |
| `query` | `ApiRequest::query(string $key, mixed $default = null)` | `mixed` |
| `header` | `ApiRequest::header(string $name)` | `?string` |
| `bearerToken` | `ApiRequest::bearerToken()` | `?string` |
| `method` | `ApiRequest::method()` | `HttpMethod` |

Parses JSON, form-urlencoded, and multipart request bodies automatically.

## ApiResponse (`core/ApiResponse.php`)

| Method | Signature | Status Code |
|--------|-----------|-------------|
| `json` | `ApiResponse::json(mixed $data, int $status = 200, array $headers = [])` | Custom |
| `success` | `ApiResponse::success(mixed $data, string $message = 'OK', int $status = 200)` | 200 |
| `created` | `ApiResponse::created(mixed $data, string $message = 'Created')` | 201 |
| `error` | `ApiResponse::error(string $message, int $status = 400, array $errors = [])` | Custom |
| `notFound` | `ApiResponse::notFound(string $message = 'Resource not found')` | 404 |
| `unauthorized` | `ApiResponse::unauthorized(string $message = 'Unauthorized')` | 401 |
| `forbidden` | `ApiResponse::forbidden(string $message = 'Forbidden')` | 403 |
| `validationError` | `ApiResponse::validationError(array $errors)` | 422 |

All methods terminate execution (`never` return type).

## Auth (`core/Auth.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `login` | `Auth::login(string $email, string $password, bool $remember = false)` | `array` |
| `register` | `Auth::register(string $firstname, string $lastname, string $name, string $email, string $password, string $designation)` | `array` |
| `logout` | `Auth::logout(string $redirect = '')` | `void` |
| `isAuthenticated` | `Auth::isAuthenticated()` | `bool` |
| `isEmailVerified` | `Auth::isEmailVerified()` | `bool` |
| `verifyEmail` | `Auth::verifyEmail(int $userId, string $token)` | `bool` |
| `resendVerificationEmail` | `Auth::resendVerificationEmail(string $email)` | `array` |

## JwtAuth (`core/JwtAuth.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `generate` | `JwtAuth::generate(array $payload)` | `string` |
| `authenticate` | `JwtAuth::authenticate()` | `array\|false` |
| `user` | `JwtAuth::user()` | `?array` |

## Validator (`core/Validator.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `make` | `Validator::make(array $data, array $rules)` | `Validator` |
| `fails` | `$validator->fails()` | `bool` |
| `errors` | `$validator->errors()` | `array` |
| `validated` | `$validator->validated()` | `array` |

## Async (`core/Async.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `parallel` | `Async::parallel(array $requests, array $defaultHeaders = [])` | `array` |
| `defer` | `Async::defer(callable $callback)` | `void` |
| `all` | `Async::all(array $tasks)` | `array` |

## Cors (`core/Cors.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `handle` | `Cors::handle()` | `void` |

## Mailer (`core/Mailer.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `send` | `Mailer::send(string $to, string $subject, string $body, bool $isHtml = false)` | `bool` |

## HttpMethod (`core/HttpMethod.php`)

PHP 8.1 enum with cases: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.

| Method | Signature | Returns |
|--------|-----------|---------|
| `fromRequest` | `HttpMethod::fromRequest()` | `HttpMethod` |

## RouteManager (`core/RouteManager.php`)

| Method | Signature | Returns |
|--------|-----------|---------|
| `dispatch` | `RouteManager::dispatch(array $routes)` | `void` |
| `resolveRoute` | `RouteManager::resolveRoute()` | `string` |

## Helper Functions (`core/Helpers.php`, `config/env.php`, `config/db.php`)

| Function | Signature | Purpose |
|----------|-----------|---------|
| `env` | `env(string $key, mixed $default = null)` | Get environment variable |
| `base_url` | `base_url(string $path = '')` | Build full URL from APP_URL |
| `load_view` | `load_view(string $path, array $data = [])` | Render a view file |
| `view_data` | `view_data(array $data, array $extras = [])` | Merge view data arrays |
| `render_view` | `render_view(string $path, array $data = [])` | Alias for load_view |
| `api_request` | `api_request(string $url, string $method = 'GET', array $options = [])` | HTTP request |
| `api_get` | `api_get(string $url, array $query = [], array $headers = [])` | GET request |
| `api_post` | `api_post(string $url, array $payload = [], array $headers = [])` | POST request |
| `db_fetch_all` | `db_fetch_all(string $sql, array $params = [])` | Fetch multiple rows |
| `db_fetch_one` | `db_fetch_one(string $sql, array $params = [])` | Fetch single row |
| `db_execute` | `db_execute(string $sql, array $params = [])` | Run INSERT/UPDATE/DELETE |
| `db_last_insert_id` | `db_last_insert_id()` | Get last auto-increment ID |
| `mongo_find` | `mongo_find(string $collection, array $filter = [])` | MongoDB find |
| `mongo_insert` | `mongo_insert(string $collection, array $data)` | MongoDB insert |
| `mongo_update` | `mongo_update(string $collection, array $filter, array $data)` | MongoDB update |
| `mongo_delete` | `mongo_delete(string $collection, array $filter)` | MongoDB delete |
