# Vayu Framework — Roadmap & Progress Tracker

This document tracks what's been built, what's in progress, and what's planned. Use it to understand where you are in the framework's development.

## Legend

- [x] Done — implemented and working
- [ ] Planned — not yet started

---

## Phase 1: Core Foundation [COMPLETE]

- [x] **Entry Point & Bootstrap** — Single entry point (`index.php`), auto-loads all core files via glob
- [x] **Environment Variables** — `.env` support via vlucas/phpdotenv, `env()` helper
- [x] **Configuration System** — App constants, route merging, framework metadata
- [x] **Route Resolution** — URL parsing from `REQUEST_URI` or query string, base path stripping

## Phase 2: Routing & Controllers [COMPLETE]

- [x] **Frontend Routing** — `ViewRouteProvider` in `app/view.php`, key-value route definitions
- [x] **API Routing** — `ApiGatewayProvider` in `api/gateway.php`, `METHOD:path` format
- [x] **Dynamic Route Parameters** — `{id}`, `{slug}` etc. via regex matching
- [x] **HTTP Method Enum** — `HttpMethod` enum (GET, POST, PUT, PATCH, DELETE, OPTIONS)
- [x] **BaseController** — View rendering, data merging, external API helpers
- [x] **ApiController** — Request parsing, validation, auth user access, JSON responses
- [x] **Route Dispatcher** — `RouteManager` auto-detects frontend vs API routes

## Phase 3: Views & Components [COMPLETE]

- [x] **View System** — `load_view()`, `render_view()`, variable extraction
- [x] **Component System** — `App::render()`, `App::component()`, `App::exists()`
- [x] **Component Isolation** — Closure-based scope, output buffering, no variable leakage
- [x] **Subdirectory Support** — `App::render('ui/card')` loads from `app/components/ui/card.php`
- [x] **Built-in Components** — `head`, `footer`, `hero`

## Phase 4: Database [COMPLETE]

- [x] **Multi-DB Support** — SQLite, MySQL, MongoDB via `DB_TYPE` env var
- [x] **SQL Helpers** — `db_fetch_all()`, `db_fetch_one()`, `db_execute()`, `db_last_insert_id()`
- [x] **MongoDB Helpers** — `mongo_find()`, `mongo_insert()`, `mongo_update()`, `mongo_delete()`
- [x] **Prepared Statements** — PDO prepared statements by default (injection-safe)
- [x] **Migration Framework** — `Migration` base class with `up()`, `down()`, `seed()`
- [x] **Users Migration** — `UsersTable` with auth-ready schema

## Phase 5: Authentication [COMPLETE]

- [x] **Session Auth** — Login, logout, register with session management
- [x] **Remember Me** — 30-day persistent cookies with secure tokens
- [x] **Email Verification** — Token-based verification flow
- [x] **Password Hashing** — BCrypt via `password_hash()`
- [x] **JWT Auth** — HS256 tokens via `firebase/php-jwt`, configurable TTL
- [x] **Route Protection** — `'auth'` middleware on API routes, auto 401 response

## Phase 6: API Layer [COMPLETE]

- [x] **Request Parser** — `ApiRequest` handles JSON, form, multipart bodies
- [x] **Response Helpers** — `ApiResponse` with success, error, 404, 401, 403, 422 responses
- [x] **Input Validation** — `Validator` with rules: required, string, email, min, max, numeric, integer, in
- [x] **CORS** — Auto headers, configurable origins, pre-flight handling
- [x] **Bearer Token** — Auto-extraction from `Authorization` header

## Phase 7: Utilities [COMPLETE]

- [x] **Mailer** — PHPMailer wrapper, Gmail SMTP support, HTML emails
- [x] **HTTP Helpers** — `api_get()`, `api_post()`, `api_request()` with cURL + stream fallback
- [x] **Async Parallel** — `Async::parallel()` for concurrent HTTP requests via cURL multi
- [x] **Deferred Tasks** — `Async::defer()` for fire-and-forget post-response work
- [x] **Batch Tasks** — `Async::all()` for unified error handling across multiple operations

---

## Phase 8: Security & Forms [PLANNED]

- [ ] **CSRF Protection** — Token generation & validation for forms (`Csrf::token()`, `Csrf::field()`, `Csrf::validate()`)
- [ ] **Form Request Handling** — Input sanitization, advanced validation rules, `Request` class
- [ ] **File Upload** — Secure upload with type/size validation, unique filenames, configurable directory

## Phase 9: Middleware & Sessions [PLANNED]

- [ ] **Middleware System** — Before/after route hooks, middleware registration in route definitions
- [ ] **Session Flash Messages** — One-time messages that survive a single redirect (`Session::flash()`, `Session::get()`)
- [ ] **Rate Limiting** — Request throttling for API endpoints

## Phase 10: Developer Experience [PLANNED]

- [ ] **Error Pages** — Custom 404/500 pages with debug info in development
- [ ] **Logging** — Structured logging to file with log levels
- [ ] **CLI Commands** — Artisan-like CLI for migrations, route listing, etc.
- [ ] **Response Helpers** — Redirect helpers, download responses, streamed responses

---

## Current State Summary

| Area | Status | Files |
|------|--------|-------|
| Bootstrap & Config | Done | `config/bootstrap.php`, `config/config.php`, `config/env.php` |
| Routing (Frontend) | Done | `app/view.php`, `core/RouteManager.php`, `core/RouteProvider.php` |
| Routing (API) | Done | `api/gateway.php`, `core/RouteManager.php` |
| Controllers (Frontend) | Done | `core/BaseController.php` |
| Controllers (API) | Done | `core/ApiController.php` |
| Component System | Done | `core/App.php` |
| Database | Done | `config/db.php` |
| Migrations | Done | `config/migration.php`, `config/migrate.php` |
| Session Auth | Done | `core/Auth.php` |
| JWT Auth | Done | `core/JwtAuth.php` |
| API Request/Response | Done | `core/ApiRequest.php`, `core/ApiResponse.php` |
| Validation | Done | `core/Validator.php` |
| CORS | Done | `core/Cors.php` |
| Mailer | Done | `core/Mailer.php` |
| HTTP Helpers | Done | `core/Helpers.php` |
| Async/Parallel | Done | `core/Async.php` |
| CSRF Protection | Planned | — |
| File Upload | Planned | — |
| Middleware System | Planned | — |
| Session Flash | Planned | — |

**You are here: Phase 7 complete. Phase 8 (Security & Forms) is next.**
