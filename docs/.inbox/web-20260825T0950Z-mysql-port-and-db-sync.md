---
agent: web-agent
date: 2026-08-25T09:50Z
status: done
breaking: no
---

# MySQL port, one seed ledger, `php vayu db:sync`

The server runs MySQL. Everything under `website/` was SQLite-only — the schema,
the migration runner, and eight upserts written in SQLite's `ON CONFLICT`
dialect — so `DB_TYPE=mysql` failed on the first statement. The API contract is
unchanged; this is storage only.

Two things landed:

1. **One codebase, two drivers.** SQLite stays the zero-setup development
   default, MySQL is the server, and the same migrations and seeders run on
   both.
2. **A sync path.** New tables or new seed rows arriving in a checkout are
   applied to any database by one additive command that is safe to re-run.

## What changed

| File | One line |
| --- | --- |
| `config/dialect.php` | **New.** The only file that knows a driver name. Type tokens for DDL, upsert rewriting for DML, statement splitting, index emulation. |
| `config/db.php` | MySQL DSN gains port + `charset=utf8mb4` + real prepares; `db_query()` now routes every query through `Dialect::dml()`. |
| `config/migration.php` | `Migration` base gains `exec()` (token expansion → split → guard) and `drop()`. |
| `config/migrate.php` | Migrations ledger made portable; seeders get their own `seeders` ledger; `--demo` split out from `--seed`. |
| `database/migrations/001…008` | Retyped to Dialect tokens. Same tables, same constraints, same comments. |
| `database/migrations/UsersTable.php` | **Deleted.** Framework-scaffold `users_tbl`, unrouted, and its seeder created `admin@example.com`. |
| `database/seeds/001_bootstrap_admin.php` | **New.** The only seed that ships: one org, its tracking config, one admin. |
| `database/demo/seed.php`, `database/demo/seed_road_days.php` | **Moved** from `database/`. Development only, behind `--demo`, refused when `APP_ENV=production`. |
| `core/Console/DbSyncCommand.php` | **New.** `php vayu db:sync`. |
| `core/Console/DbExportCommand.php` | **New.** `php vayu db:export` — the schema as a .sql file, for hosts where nothing can reach MySQL over the network. |
| `api/support/Ctx.php` | `audit_log.before` quoted in the INSERT — see below. |
| `core/Console/MigrateCommand.php` | `--demo`, `--create-database`, prints the driver. |
| `vayu`, `.env.example` | Command registered; MySQL keys documented. |

## The parts worth knowing

**Type tokens.** Migrations no longer name column types. `{uuid}` is `TEXT` on
SQLite and `CHAR(36)` on MySQL, `{ts}` is the ISO-8601 string both drivers sort
correctly, `{json}` widens to `LONGTEXT` because `day_routes.polyline` and
`route_geometry.segments` outgrow MySQL's 64 KB `TEXT`. `{default '[]'}` exists
because MySQL forbade defaults on TEXT columns until 8.0.13 and then allowed
only the parenthesised form, while MariaDB takes the plain one — `Dialect`
reads the server version and picks.

**Foreign keys are now table-level.** MySQL parses an inline `REFERENCES` clause
and silently ignores it, so every inline FK — including the `ON DELETE CASCADE`
that `DELETE FROM users` depends on in `EmployeesController` — would have
quietly stopped cascading. They are declared at the end of each table instead.

**Upserts are rewritten at one choke point.** No code outside `config/db.php`
touches `$pdo`, so `db_query()` translating `ON CONFLICT … DO UPDATE SET x =
excluded.x` into `ON DUPLICATE KEY UPDATE x = VALUES(x)` covers all six sites
plus `INSERT OR IGNORE` and `INSERT OR REPLACE` without editing a controller.
Every `ON CONFLICT` target here is its table's only unique key, so MySQL's
"any unique key" semantics match one for one. `INSERT OR REPLACE` becomes an
upsert rather than `REPLACE INTO`, which would delete the row first and fire
cascades.

**One column had to be quoted.** `audit_log.before` — `BEFORE` is reserved in
MySQL, and the CREATE failed on it. Both `before` and `after` are backticked now,
in the migration and in `Ctx::audit()`; SQLite takes backticks too. An audit of
every table and column name in the schema against the MySQL 8.0 reserved word
list found no others.

**Partial indexes flatten.** MySQL has none, so `WHERE ended_at IS NULL` and the
three like it are dropped and the index created in full — correct, less
selective. `CREATE INDEX IF NOT EXISTS` is emulated against
`information_schema.statistics`.

## Seeding is now a ledger, not a file

`database/seeds/*.php` are numbered, idempotent, and recorded in a `seeders`
table the same way migrations are. Adding seed data later means dropping
`002_*.php` in the folder; `db:sync` applies it once, everywhere, and never
again.

What ships is one admin — `admin@hazraev.com` — and nothing else. No employees,
no companies, no products: the roster is built through the admin UI against the
real business.

```
php vayu db:sync            apply pending migrations, then pending seeders
php vayu db:sync --status   what is applied, what is pending
php vayu db:sync --dry      list without applying
```

It never drops and never rewrites. `php vayu migrate --fresh --demo` keeps the
destructive development shortcuts.

## Verified

Against the production MariaDB 11.8.8 (Hostinger), from an empty database:

- `db:sync` built 34 tables, 50 foreign keys (21 cascading), 96 indexes, all
  InnoDB / utf8mb4_unicode_ci; table set is identical to SQLite's, name for name
- one user exists — `admin@hazraev.com`, role admin — and its hash verifies
- re-running `db:sync` reports nothing to migrate, nothing to seed
- all three upsert rewrites and an 88 KB JSON round trip confirmed against a
  scratch table, created and dropped, no app table touched

Against MySQL 8.0.46 in a container, through the running API, so nothing was
written into production:

- admin login, bootstrap, employee create, employee login
- `device_health` upserted twice → one row, second value winning
- `POST /tracking/locations` replayed → both fixes `rejected: DUPLICATE`
- `POST /reports` replayed with the same Idempotency-Key → `Idempotent-Replay:
  true`, same report id, one row
- `attendance_days` and `day_routes` rolled up from the ingested fixes

Interrupted mid-run once, on purpose: migration 007 failed on the reserved word
and the ledger held 001–006, so the next `db:sync` resumed at 007 and applied
only what was missing. That is the incremental path working.

## Needs doing next

- [ ] `docs-agent` — `docs/03-DATABASE-SCHEMA.md` is written for PostgreSQL and
      now has a second port to describe. The SQLite port note in it is stale:
      types are tokens, FKs moved table-level, `{json}` columns are `LONGTEXT`
      on MySQL, and partial indexes do not exist there.
- [ ] `web-agent` — the scaffold's dead auth (`core/Auth.php`,
      `api/controllers/UserController.php`, `JwtAuth::user()`) still queries
      `users_tbl`, which no longer exists in any database. Nothing routes to it;
      it should be deleted rather than left to fail if someone wires it up.

## Blocked on

Nothing. Development on SQLite is unaffected.
