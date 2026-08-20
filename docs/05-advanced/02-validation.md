# Validation

The `Validator` class (`core/Validator.php`) provides rule-based input validation.

## Basic Usage

```php
$validator = Validator::make($data, [
    'name'  => 'required|string|min:2|max:100',
    'email' => 'required|email',
    'age'   => 'required|integer',
]);

if ($validator->fails()) {
    $errors = $validator->errors();
    // ['name' => ['name must be at least 2 characters'], ...]
}

$clean = $validator->validated();
// Returns only the fields that had rules defined
```

## In API Controllers

`ApiController` has a built-in `validate()` method that auto-responds with a 422 error on failure:

```php
class UserController extends ApiController
{
    public function store()
    {
        $data = $this->validate([
            'name'  => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'role'  => 'required|in:admin,editor,viewer',
        ]);

        // If validation fails, a 422 JSON response is sent automatically.
        // If it passes, $data contains only the validated fields.

        db_execute("INSERT INTO users_tbl (name, email, role) VALUES (?, ?, ?)",
            [$data['name'], $data['email'], $data['role']]);

        $this->success(null, 'User created', 201);
    }
}
```

### Auto 422 Response Format

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "name": ["name is required"],
        "email": ["email must be a valid email"]
    }
}
```

## Available Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must be present and non-empty | `'name' => 'required'` |
| `string` | Must be a string type | `'name' => 'string'` |
| `email` | Must be a valid email address | `'email' => 'email'` |
| `min:N` | String must be at least N characters | `'password' => 'min:8'` |
| `max:N` | String must be at most N characters | `'name' => 'max:100'` |
| `numeric` | Must be a numeric value | `'price' => 'numeric'` |
| `integer` | Must be an integer | `'age' => 'integer'` |
| `in:a,b,c` | Must be one of the listed values | `'role' => 'in:admin,user'` |

## Combining Rules

Chain rules with `|`:

```php
'password' => 'required|string|min:8|max:128',
'status'   => 'required|in:active,inactive,banned',
```

Validation stops at the first failing rule for each field.
