# Request Lifecycle

Every request to Vayu follows the same path through the framework.

## Flow Diagram

```
Browser Request
    │
    ▼
index.php
    │
    ▼
config/bootstrap.php
    ├── Loads Composer autoload (if exists)
    ├── Loads vlucas/phpdotenv (if available)
    ├── Loads env.php, config.php, framework.php, db.php
    └── Globs all core/*.php — auto-loads every framework class
    │
    ▼
config/config.php
    ├── Defines APP constants (name, env, debug, url)
    └── Merges frontend routes (app/view.php) + API routes (api/gateway.php)
    │
    ▼
config/route.php
    └── Calls RouteManager::dispatch($routes)
    │
    ▼
RouteManager::dispatch()
    ├── Resolves URL path from REQUEST_URI or $_GET['route']
    ├── Determines if route is frontend or API (api/ prefix)
    │
    ├─── [Frontend Route] ────────────────────────────┐
    │    Looks up route key in merged routes           │
    │    Instantiates controller (extends BaseController)
    │    Calls the mapped method                       │
    │    Controller renders view via load_view()       │
    │    Response sent to browser                      │
    │                                                  │
    ├─── [API Route] ─────────────────────────────────┐
    │    Cors::handle() — sets CORS headers            │
    │    Matches HTTP_METHOD:path against API routes   │
    │    Checks middleware (e.g., 'auth' → JwtAuth)    │
    │    Instantiates controller (extends ApiController)
    │    Injects route parameters                      │
    │    Calls the mapped method                       │
    │    Controller sends JSON via ApiResponse         │
    │                                                  │
    └─── [No Match] ──────────────────────────────────┐
         Frontend: 404 page                            │
         API: JSON 404 response                        │
```

## Route Resolution

The `RouteManager::resolveRoute()` method determines the current route:

1. Checks `$_GET['route']` (query-string routing: `?route=about`)
2. Falls back to parsing `REQUEST_URI`
3. Strips the base path (supports subdirectory installations)
4. Trims slashes
5. Empty path becomes `default` (maps to `/`)

## API Route Matching

API routes use the format `HTTP_METHOD:path`:

```
GET:api/v1/users          → exact match
GET:api/v1/users/{id}     → dynamic parameter match via regex
```

The dispatcher:
1. Tries exact match first
2. Falls back to regex matching for `{param}` placeholders
3. Extracts named parameters and passes them to the controller

## Middleware Processing

Currently, the only middleware is `auth`:

```php
'GET:api/v1/users' => ['UserController', 'index', 'auth'],
```

When `auth` is specified, `JwtAuth::authenticate()` runs before the controller. If the token is missing or invalid, a 401 response is returned immediately.

## Deferred Tasks

If any `Async::defer()` calls were made during the request, they execute after the response is sent to the client via a registered shutdown function.
