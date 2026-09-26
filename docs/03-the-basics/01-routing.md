# Routing

Vayu has two routing systems: frontend routes for page rendering and API routes for JSON endpoints.

## Frontend Routes

Defined in `app/view.php` as a `ViewRouteProvider`:

```php
class ViewRouteProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            'default' => ['Welcome', 'index'],       // GET /
            'about'   => ['PageController', 'about'], // GET /about
            'contact' => ['PageController', 'contact'],
        ];
    }
}
```

- The route key is the URL path
- `default` maps to the root URL `/`
- Value is `['ControllerClass', 'method']`

## API Routes

Defined in `api/gateway.php` as an `ApiGatewayProvider`:

```php
class ApiGatewayProvider extends RouteProvider
{
    public static function routes(): array
    {
        return [
            // Public routes
            'POST:api/v1/auth/login'    => ['UserController', 'login'],
            'POST:api/v1/auth/register' => ['UserController', 'register'],

            // Protected routes (require JWT)
            'GET:api/v1/users'          => ['UserController', 'index',   'auth'],
            'GET:api/v1/users/{id}'     => ['UserController', 'show',    'auth'],
            'PUT:api/v1/users/{id}'     => ['UserController', 'update',  'auth'],
            'DELETE:api/v1/users/{id}'  => ['UserController', 'destroy', 'auth'],
        ];
    }
}
```

### API Route Format

```
HTTP_METHOD:path => ['Controller', 'method', 'middleware']
```

- **HTTP_METHOD**: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`
- **path**: URL path, supports `{param}` placeholders
- **middleware**: Optional — currently only `'auth'` (JWT authentication)

### Dynamic Parameters

Use `{param}` in the path to capture URL segments:

```php
'GET:api/v1/users/{id}' => ['UserController', 'show', 'auth'],
```

Access in the controller:

```php
$id = $this->param('id');
```

## Route Merging

Both route sources are merged in `config/config.php` and dispatched by `RouteManager::dispatch()` in `config/route.php`. The dispatcher auto-detects API routes by the `api/` prefix.

## How Route Resolution Works

1. Checks `$_GET['route']` (query-string mode: `index.php?route=about`)
2. Falls back to `REQUEST_URI` parsing
3. Strips base path for subdirectory installations
4. Empty path resolves to `default`
