# Authentication

Vayu provides two authentication systems: session-based for frontend pages and JWT-based for APIs.

## Session-Based Authentication (`Auth`)

Used for frontend pages with login forms, sessions, and cookies.

### Login

```php
$result = Auth::login($email, $password, $remember = true);
// Returns: ['status' => true/false, 'message' => '...']

if ($result['status']) {
    header('Location: ' . base_url('dashboard'));
    exit;
}
```

### Register

```php
$result = Auth::register($firstname, $lastname, $name, $email, $password, $designation);
```

Registration automatically sends a verification email via the Mailer.

### Check Authentication

```php
if (Auth::isAuthenticated()) {
    // user is logged in
}
```

### Email Verification

```php
// Verify with token (from email link)
Auth::verifyEmail($userId, $token);

// Check if verified
if (Auth::isEmailVerified()) { ... }

// Resend verification
Auth::resendVerificationEmail($email);
```

### Logout

```php
Auth::logout('login'); // clears session + cookies, redirects to /login
```

### Remember Me

When `$remember = true` is passed to `login()`, a secure cookie is set with a 30-day lifetime. On subsequent visits, the user is automatically authenticated from the cookie.

### Protecting a Page

```php
class Dashboard extends BaseController
{
    public function index()
    {
        if (!Auth::isAuthenticated()) {
            header('Location: ' . base_url('login'));
            exit;
        }
        return $this->respond('/app/page/dashboard.php');
    }
}
```

## JWT Authentication (`JwtAuth`)

Used for stateless API authentication with bearer tokens.

### Generate a Token

```php
$token = JwtAuth::generate(['id' => $user['id'], 'email' => $user['email']]);
```

Returns an HS256-signed JWT with configurable TTL (`JWT_TTL` env var, default 3600s).

### Validate a Token

```php
$payload = JwtAuth::authenticate();
// Returns: array of claims on success, false on failure
```

Reads the `Authorization: Bearer <token>` header automatically.

### Get Authenticated User

```php
$user = JwtAuth::user();
// Returns: ['id', 'name', 'email', 'role'] or null
```

Fetches the user from the database using the token's `sub` claim.

### Protecting API Routes

Add `'auth'` as the third element in a route definition:

```php
'GET:api/v1/users' => ['UserController', 'index', 'auth'],
```

The dispatcher checks `JwtAuth::authenticate()` before calling the controller. Returns a 401 JSON response if the token is missing or invalid.

### Accessing the User in a Controller

```php
class UserController extends ApiController
{
    public function profile()
    {
        $user = $this->user(); // shortcut for JwtAuth::user()
        $this->success($user);
    }
}
```

## Configuration

```env
JWT_SECRET=your-secret-key-change-in-production
JWT_TTL=3600
```

**Important**: Change `JWT_SECRET` in production. The default value is not secure.
