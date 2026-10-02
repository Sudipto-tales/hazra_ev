# Environment Variables

Vayu uses [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) to load environment variables from a `.env` file.

## Setup

Copy the template:

```bash
cp .env.example .env
```

## Accessing Variables

Use the `env()` helper anywhere in your code:

```php
$appName = env('APP_NAME', 'Vayu');    // second param is the default
$debug   = env('APP_DEBUG', false);
$dbHost  = env('DB_HOST', 'localhost');
```

## Available Keys

### Application

| Key | Default | Purpose |
|-----|---------|---------|
| `APP_NAME` | `Vayu` | Application name |
| `APP_ENV` | `production` | Environment (`development`, `production`) |
| `APP_DEBUG` | `false` | Show debug errors |
| `APP_URL` | `http://localhost` | Base URL for `base_url()` helper |

### Database

| Key | Default | Purpose |
|-----|---------|---------|
| `DB_TYPE` | `sqlite` | Database driver (`sqlite`, `mysql`, `mongo`) |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | — | Database name |
| `DB_USERNAME` | — | Database username |
| `DB_PASSWORD` | — | Database password |

### JWT

| Key | Default | Purpose |
|-----|---------|---------|
| `JWT_SECRET` | `change-me-in-production` | Secret key for HS256 signing |
| `JWT_TTL` | `3600` | Token lifetime in seconds |

### CORS

| Key | Default | Purpose |
|-----|---------|---------|
| `CORS_ORIGINS` | `*` | Allowed origins (comma-separated or `*` for all) |
