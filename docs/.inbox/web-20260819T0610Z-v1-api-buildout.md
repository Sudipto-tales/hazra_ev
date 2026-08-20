---
agent: web-agent
date: 2026-08-19T06:10Z
status: done
breaking: yes — see "Contract deviations" below
---

# `/api/v1` built out against `02-API-PLAN.md`, on SQLite

The route table in `docs/02-API-PLAN.md` is now executable. 37 routes across 15
controllers, a SQLite port of `docs/03-DATABASE-SCHEMA.md`, and a seeder that
produces five working days of derived tracking data.

## What changed

### Schema — `website/database/migrations/` (7 new migrations)

| File | Tables |
| --- | --- |
| `001_core_identity_tables.php` | `organizations`, `users`, `employee_profiles`, `admin_profiles`, `devices`, `user_preferences`, `refresh_tokens`, `idempotency_keys` |
| `002_tracking_config_tables.php` | `tracking_configs`, `tracking_config_history`, `holidays` |
| `003_catalogue_customer_tables.php` | `companies`, `branches`, `products`, `product_colors`, `product_color_images` |
| `004_session_location_tables.php` | `work_sessions`, `location_fixes` |
| `005_visit_report_tables.php` | `stop_records`, `company_visits`, `visit_reports`, `report_sales`, `report_images`, `report_reviews` |
| `006_rollup_tables.php` | `attendance_days`, `day_routes`, `activity_events`, `device_health` |
| `007_notification_audit_tables.php` | `notifications`, `notification_recipients`, `audit_log` |

`database/seed.php` — one org, 1 admin, 4 employees, 4 companies / 5 branches,
5 products, 5 working days of GPS tracks per employee. Tracks are pushed through
the same ingest path the API uses, so stops, visits, timeline, routes and rollups
are derived rather than fabricated.

### Support layer — `website/api/support/` (new)

| File | Role |
| --- | --- |
| `bootstrap.php` | loads the layer; required by `RouteManager` before an api/ route dispatches |
| `Envelope.php` | the `{data, meta, error}` contract shape, ETag/304, Cache-Control |
| `Ctx.php` | principal, org, tracking config, and `subject` authorisation |
| `Wire.php` | snake_case DB enum ↔ camelCase wire enum, timestamps, durations |
| `Present.php` | row → wire object, one method per model in `lib/data/models/` |
| `Engine.php` | fix ingest, stop clustering, visit promotion, timeline, rollups, route simplification, live status |
| `Geo.php` | haversine + Douglas–Peucker (what PostGIS would have done) |
| `Users.php`, `Uuid.php`, `Cursor.php`, `V1Controller.php` | identity read, uuid v4, keyset cursors, base controller |

### Controllers — `website/api/controllers/` (14 new)

`AuthController`, `BootstrapController`, `MeController`, `EmployeesController`,
`DaysController`, `AttendanceController`, `StatisticsController`,
`RoutesController`, `ReportsController`, `ProductsController`,
`CompaniesController`, `TrackingController`, `ConfigController`,
`NotificationsController`, `StreamController`.

`api/gateway.php` — rewritten as the full route table, annotated with the plan
section each group implements.

### Framework touches — `website/core/`, `website/config/`, `website/`

- `core/RouteManager.php` — requires `api/support/bootstrap.php` before dispatching
  an api/ route, so a 401 from the auth middleware uses the contract envelope.
- `core/Cors.php` — allows `Idempotency-Key` and `If-None-Match`, exposes `ETag`.
- `config/db.php` — `FETCH_ASSOC` (the default `FETCH_BOTH` emitted every column
  twice through `json_encode`), plus `foreign_keys`, WAL and busy_timeout for SQLite.
- `config/migrate.php` — was dead code that fataled on an undefined `$base_url`;
  now a function-only runner.
- `core/Console/MigrateCommand.php` + `vayu` — new `php vayu migrate [--fresh] [--seed]`.
- `server.php` — sets `$_GET['route']`, mirroring the `.htaccess` rewrite. Without
  it the built-in server resolved `/api/v1/me` as `me`, so **no API route worked
  under `php vayu run`**.
- `.env.example` — documents `JWT_SECRET` (HS256 needs ≥ 32 bytes or php-jwt
  refuses to sign), SQLite defaults, CORS.

## Contract deviations — `docs/02-API-PLAN.md` needs these reflected

1. **`POST /api/v1/auth/login` moved.** It was `UserController@login` against the
   scaffold's `users_tbl`. That path is the contract's, so `UserController` is now
   unrouted (file left on disk, nothing dispatches to it). The `/api/v1/users`
   demo CRUD is gone with it.
2. **§3.11 is internally inconsistent.** It says `POST /tracking/locations` carries
   "the same flat tuple array §3.7 uses", but the response names fixes by `id` and
   the §3.7 tuple has no id slot. Implemented as: objects (`{id, latitude, …}`) or
   tuples `[lat, lng, t, acc, spd, clientId]` — the id in slot 6. Plan should pick one.
3. **§3.7 `points` tuple `t`** is emitted as epoch seconds. Not stated in the plan.
4. **`Attendance` embeds `summary`** per §3.5, so the nine `DaySummary` fields are
   nested, not flat. The Dart `Attendance` model is still flat — mobile-agent needs
   to know.
5. **`Employee` gained `active` and `reportingToId`.** `reportingTo` is now rendered
   server-side from the manager id, as data doc §1.1 asks for.
6. **`GET /days/{subject}` always returns `date`**, and returns `employee` only for
   an admin subject.
7. **`sync` on `/days/me`** reports `queued: 0, failed: 0` — `SyncSnapshot` is device
   queue state and is never persisted server-side. The client overlays its own.
8. `POST /reports` returns **200**, not 201, so an idempotent replay can return the
   identical response.

## Deviations from `docs/03-DATABASE-SCHEMA.md` (SQLite port)

- No partitioning, no BRIN — `location_fixes` is one table with a
  `(employee_id, recorded_at)` index. This is the one place the production schema
  genuinely diverges.
- No PostGIS. Stop clustering and branch matching are haversine in PHP (`Geo.php`);
  `geog` columns and the GiST index are dropped.
- Enums are `TEXT` + `CHECK`; arrays and `polyline` are JSON `TEXT`; `box` is four
  `REAL` columns.
- `employee_code` `CHECK` is `LIKE 'EMP-%'`; the exact `^EMP-\d{3,5}$` is enforced
  in `EmployeesController`.
- `stop_records.visit_id` carries no FK — it is a cycle with `company_visits.stop_id`.
- **Added, not in the doc:** `refresh_tokens`, `idempotency_keys`, `device_health`,
  `holidays`. Each closes a gap data doc §13 lists as missing.
- **Undocumented rule:** a day with ≥ 4 h worked is `present`, below that `partial`.
  No source in the docs for the boundary — `Engine::FULL_DAY_SECONDS`.

## Verified

`php vayu migrate --fresh --seed` then all 37 routes exercised over HTTP:

- reads: bootstrap, me, preferences, days, attendance (list + grid), statistics
  (both scopes), routes (live + stored), reports (3 lists + badge), products,
  companies, notifications, config
- writes: session open/close, location batch, health, report submit + images +
  review, employee create/patch, product create/delist, config update
- ingest rejections: `ACCURACY`, `JUMP`, `DUPLICATE` all returned per-fix
- idempotency: repeated `clientId` and repeated `Idempotency-Key` both replay
- authorisation: employee `subject=team` → 403; employee writing config → 403
- caching: `If-None-Match` on `/config` → 304
- auth lifecycle: refresh rotates, a reused refresh token → 401
- SSE `/stream` emits `member.status`, `member.fix`, `alert.longStop`, `report.filed`
- the website's own root route still renders

## Needs doing next

- [ ] `docs-agent` — fold the eight contract deviations above into `02-API-PLAN.md`,
      and the SQLite port notes into `03-DATABASE-SCHEMA.md`
- [ ] `docs-agent` — record that `SyncSnapshot`, `StatsRange` and the derived
      predicates remain client-only, as the schema doc already says
- [ ] `mobile-agent` — replace `lib/data/mock/` with HTTP repositories; the route
      map in `02-API-PLAN.md` §4 holds. Note deviations 4, 5 and 7 above
- [ ] `mobile-agent` — `ReportDraft` needs `clientId`; sessions and fixes need one too
- [ ] `web-agent` — nightly job to fill `absent`/`holiday`/`weekend` rollup rows for
      days nobody worked (currently synthesised at read time in `AttendanceController`)
- [ ] `web-agent` — `/stream` closes after `ttl` seconds because the built-in PHP
      server is single-threaded; needs a real SAPI before production

## Blocked on

Nothing.

## Dev quickstart

```
cd website
php vayu migrate --fresh --seed
php vayu run --port 8000
# admin@hazra-ev.test / arif@hazra-ev.test — password123
```
