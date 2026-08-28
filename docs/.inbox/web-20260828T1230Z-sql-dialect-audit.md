---
agent: web-agent
date: 2026-08-28T12:30Z
status: done
handoff-to: deploy (patched PHP is not on Hostinger yet), docs-agent (retract the "six broken upserts" claim)
---

# web-agent — SQL dialect audit: one real bug, one prior claim retracted

Folder touched: `website/` only. No response shape changed, so the API contract
in `docs/02-API-PLAN.md` is untouched.

## Retraction first

An earlier note in this session claimed six SQLite-dialect statements in
`website/api/` would 500 on MariaDB, and asked for an upsert helper. **That was
wrong, and no such helper is needed — one already exists.**

`config/dialect.php::dml()` is called by `db_query()` on every statement, and it
already rewrites `INSERT OR REPLACE`, `INSERT OR IGNORE`, `ON CONFLICT … DO
NOTHING` and `ON CONFLICT … DO UPDATE SET … excluded.x` into their MySQL forms.
The original check PREPAREd the *source* SQL, which no code path ever sends.

Re-verified against production MariaDB 11.8.8, PREPARE only:

| Site | Verdict |
| --- | --- |
| `V1Controller.php:178` idempotency_keys | translates, PREPAREs clean |
| `Engine.php:833` notification_recipients | clean |
| `TrackingController.php:183` device_health | clean |
| `Engine.php:410` attendance_days | clean |
| `Engine.php:513` day_routes | clean |
| `MapMatch.php:340` route_geometry | clean |
| `ReportsController.php:243` report_reviews | clean |

MySQL's `ON DUPLICATE KEY` fires on *any* unique key, so the rewrite is only
correct if the intended key exists. All seven were checked against
`information_schema.statistics`: every one has a PRIMARY on exactly the columns
its `ON CONFLICT` names. No silent-duplicate risk.

`AuthController::registerDevice()`'s `push_token IS ?` was a real 500 and was
already fixed — `IS ?` is not an upsert form, so `dml()` never saw it.

## The audit

Every literal SQL string in the PHP source (235 of them) was pushed through the
real `Dialect::dml()` and handed to production for PREPARE. Dynamic SQL, which
that method cannot reach, was covered by grepping all source for SQLite-only
tokens (`rowid`, `strftime`, `julianday`, `AUTOINCREMENT`, `GLOB`, `PRAGMA`,
`random()`). One live defect fell out.

## Fixed

**`api/controllers/DaysController.php:116`** — `ORDER BY occurred_at, rowid`.
`rowid` is SQLite's implicit key; MySQL answers 1054 and the request 500s. This
breaks `GET /days/{subject}?include=activity` — the employee Activity tab, and
the Home snapshot, which asks for `activity` too. Now `ORDER BY occurred_at, id`,
which is a uuid rather than an insertion counter, but it is only a tiebreak
between two events at the same instant and it is stable on both engines.

**`config/dialect.php`** — `dml()` now rewrites `col IS ?` to `col <=> ?` on
MySQL, so the class of bug that hit `registerDevice()` cannot ship again.
`IS NOT ?` is deliberately left to fail loudly: its equivalent is
`NOT (col <=> ?)`, and a rewrite that guessed the operand wrong would invert a
condition silently. `AuthController`'s explicit two-branch version was left as
written — it is correct on both engines and clearer than relying on a rewrite.

Verified: `dml()` is still identity on SQLite; `IS ?` still matches NULL there;
the translated device lookup executes on production against the real `devices`
table with both a string and NULL bound; `push_token` is `utf8mb4_unicode_ci`
and `<=>` raises no collation error. Re-running the sweep, `rowid` is gone.

**`core/JwtAuth.php:62`** — `JwtAuth::user()` selected from `users_tbl`, a
framework-demo table the product database has never had, so any caller took a
1054 instead of a null. It is reached through `ApiController::user()`, which
`V1Controller` inherits, so it was one call away from a 500 in the base class of
every v1 controller. Now selects `id, org_id, role, name, email` from `users`.

It also gained `AND active = 1`. `Ctx::principal()` — the path the v1 API
actually uses — refuses a deactivated account, and a second door that did not
would have let a token issued before the deactivation keep working. The `sub`
guard moved from `isset` to `empty` for the same reason: `generate()` writes a
null `sub` when the payload has no id.

Verified on production: resolves the real admin row, returns null for an unknown
id. Re-running the sweep, `core/JwtAuth.php` is clean.

## Not fixed, worth knowing

`core/Auth.php` and the deliberately unrouted `api/controllers/UserController.php`
still reference `users_tbl`. Both are framework demo code — `api/gateway.php:18`
documents the controller as intentionally unrouted — and neither is reachable
from a v1 route, so they were left alone rather than half-ported.

**The patched PHP is still only local.** Production keeps serving the old files,
so the Activity tab stays broken there until `website/` is uploaded.
