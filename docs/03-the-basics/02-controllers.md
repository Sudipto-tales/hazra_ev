# Controllers

Vayu has two controller base classes, each designed for its context.

## Frontend Controllers (`BaseController`)

Located in `app/controllers/`. Extend `BaseController` for page rendering.

```php
// app/controllers/About.php
class About extends BaseController
{
    public function index()
    {
        $data = $this->viewData(['title' => 'About Us']);
        return $this->respond('/app/page/about.php', $data);
    }
}
```

### BaseController Methods

| Method | Purpose |
|--------|---------|
| `$this->respond($view, $data)` | Render a view file with extracted variables |
| `$this->viewData($data, $extras)` | Merge multiple data arrays for views |
| `$this->apiGet($url, $query, $headers)` | Make a GET request to an external API |
| `$this->apiPost($url, $payload, $headers)` | Make a POST request to an external API |
| `$this->defaultAppName()` | Get the app name from `APP_NAME` env var |

## API Controllers (`ApiController`)

Located in `api/controllers/`. Extend `ApiController` for JSON APIs.

```php
// api/controllers/UserController.php
class UserController extends ApiController
{
    public function index()
    {
        $users = db_fetch_all("SELECT id, name, email FROM users_tbl");
        $this->success($users);
    }

    public function show()
    {
        $id = $this->param('id');
        $user = db_fetch_one("SELECT * FROM users_tbl WHERE id = ?", [$id]);

        if (!$user) {
            $this->error('User not found', 404);
        }

        $this->success($user);
    }

    public function update()
    {
        $id = $this->param('id');
        $data = $this->validate([
            'name'  => 'required|string|min:2|max:100',
            'email' => 'required|email',
        ]);

        db_execute("UPDATE users_tbl SET name = ?, email = ? WHERE id = ?",
            [$data['name'], $data['email'], $id]);

        $this->success(null, 'User updated');
    }
}
```

### ApiController Methods

| Method | Purpose |
|--------|---------|
| `$this->param($key)` | Access route parameters (`{id}`, `{slug}`, etc.) |
| `$this->input($key)` | Get a single field from request body |
| `$this->body()` | Get the full request body as an array |
| `$this->only(...$keys)` | Extract specific fields from request body |
| `$this->validate($rules)` | Validate request body — auto-responds 422 on failure |
| `$this->user()` | Get authenticated user (from JWT token) |
| `$this->json($data, $status)` | Send raw JSON response |
| `$this->success($data, $message, $status)` | Send success JSON response |
| `$this->error($message, $status, $errors)` | Send error JSON response |

### API Response Format

All `success()` and `error()` responses follow a consistent format:

```json
// Success
{
    "success": true,
    "message": "OK",
    "data": { ... }
}

// Error
{
    "success": false,
    "message": "Validation failed",
    "errors": { "email": ["email is required"] }
}
```
