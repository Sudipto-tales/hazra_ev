# HTTP Helpers

Vayu provides helper functions for making external HTTP requests from your application.

## Functions

### `api_get($url, $query, $headers)`

```php
$response = api_get('https://api.example.com/users', ['page' => 1]);
```

### `api_post($url, $payload, $headers)`

```php
$response = api_post('https://api.example.com/users', ['name' => 'John']);
```

### `api_request($url, $method, $options)`

Full control over the request:

```php
$response = api_request('https://api.example.com/resource', 'PUT', [
    'headers' => ['Authorization: Bearer token123'],
    'payload' => ['name' => 'Updated'],
    'query'   => ['include' => 'relations'],
]);
```

## How It Works

- Uses cURL when available
- Falls back to PHP stream context (`file_get_contents`) if cURL is not installed
- JSON payloads are automatically encoded
- Responses are JSON-decoded when the content type is JSON

## Using in Controllers

`BaseController` provides convenience wrappers:

```php
class WeatherController extends BaseController
{
    public function index()
    {
        $weather = $this->apiGet('https://api.weather.com/current', [
            'city' => 'kolkata',
            'key'  => env('WEATHER_API_KEY'),
        ]);

        return $this->respond('/app/page/weather.php', ['weather' => $weather]);
    }
}
```

## For Parallel Requests

Use `Async::parallel()` instead — see [Async & Parallel Processing](03-async.md).
