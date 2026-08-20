# Async & Parallel Processing

The `Async` class (`core/Async.php`) provides three patterns for non-blocking work in PHP.

## Parallel HTTP Requests

Run multiple HTTP requests simultaneously instead of sequentially:

```php
// Sequential: ~600ms (200ms + 200ms + 200ms)
// Parallel:   ~200ms (all at once)

$results = Async::parallel([
    'users'    => 'https://api.example.com/users',
    'posts'    => 'https://api.example.com/posts',
    'comments' => 'https://api.example.com/comments',
]);

$users = $results['users']['data'];
$posts = $results['posts']['data'];
```

### With Full Options

```php
$results = Async::parallel([
    'create_user' => [
        'url'     => 'https://api.example.com/users',
        'method'  => 'POST',
        'payload' => ['name' => 'John', 'email' => 'john@test.com'],
        'headers' => ['Authorization: Bearer token123'],
        'timeout' => 10,
    ],
    'get_roles' => [
        'url'   => 'https://api.example.com/roles',
        'query' => ['active' => 1],
    ],
]);

// Each result:
// ['success' => true, 'status' => 200, 'data' => [...]]
// or ['success' => false, 'error' => 'Connection timeout']
```

Requires the cURL extension. Falls back to sequential requests if cURL is unavailable.

## Deferred Tasks (Fire-and-Forget)

Run code AFTER the response is sent to the client. The user doesn't wait.

```php
class ContactController extends BaseController
{
    public function submit()
    {
        db_execute("INSERT INTO messages (email, body) VALUES (?, ?)",
            [$_POST['email'], $_POST['message']]);

        // These run AFTER user gets their response
        Async::defer(function() {
            Mailer::send('admin@site.com', 'New Contact', 'Someone reached out');
        });

        Async::defer(function() {
            api_post('https://hooks.slack.com/webhook', ['text' => 'New contact form']);
        });

        // User sees this immediately
        return $this->respond('/app/page/thank-you.php');
    }
}
```

Uses `fastcgi_finish_request()` when available (PHP-FPM), otherwise flushes output and closes the connection before running deferred tasks.

## Batch Tasks

Run multiple PHP functions with unified error handling:

```php
$results = Async::all([
    'db_users'  => fn() => db_fetch_all("SELECT * FROM users_tbl LIMIT 10"),
    'api_stats' => fn() => api_get('https://api.example.com/stats'),
    'file_size' => fn() => filesize('/path/to/file.pdf'),
]);

// Each: ['success' => true, 'data' => ...] or ['success' => false, 'error' => '...']
```

Note: `Async::all()` runs tasks sequentially in PHP — it provides unified error handling, not true parallelism.

## When to Use What

| Method | Use When |
|--------|----------|
| `Async::parallel()` | Multiple external HTTP calls that don't depend on each other |
| `Async::defer()` | Emails, webhooks, logging — user shouldn't wait for these |
| `Async::all()` | Multiple local tasks where you want unified error handling |
