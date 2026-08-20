# CORS (Cross-Origin Resource Sharing)

The `Cors` class (`core/Cors.php`) handles CORS headers for API requests. It runs automatically for all `api/` routes.

## Configuration

Set allowed origins in `.env`:

```env
# Allow all origins
CORS_ORIGINS=*

# Allow specific origins (comma-separated)
CORS_ORIGINS=https://myapp.com,https://admin.myapp.com
```

## What It Does

For every API request, `Cors::handle()` sets:

| Header | Value |
|--------|-------|
| `Access-Control-Allow-Origin` | Matched origin or first allowed origin |
| `Access-Control-Allow-Methods` | `GET, POST, PUT, PATCH, DELETE, OPTIONS` |
| `Access-Control-Allow-Headers` | `Content-Type, Authorization, X-Requested-With` |
| `Access-Control-Max-Age` | `86400` (24 hours) |

## Pre-flight Requests

`OPTIONS` requests receive a `204 No Content` response with the CORS headers and exit immediately.

## How Origin Matching Works

1. If `CORS_ORIGINS=*`, all origins are allowed
2. If specific origins are listed, the request's `Origin` header is checked against the list
3. If the origin matches, it's reflected in the response
4. If it doesn't match, the first allowed origin is used (browser will block the request)
