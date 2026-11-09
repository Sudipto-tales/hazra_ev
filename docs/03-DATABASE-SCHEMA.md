# Database schema

PostgreSQL 15+, PostGIS for the one thing that genuinely needs it (stop detection).
Designed around a single fact: **`location_fixes` is 99 % of the row count**. Every other
decision here is downstream of keeping that table cheap to write and rare to read.

Volume model — 12 employees × 8 h × 1 fix / 30 s ≈ **11 500 rows/day**, ~4.2 M/year.
At 200 employees that is 70 M/year. The schema below stays flat at that size; a naive one
does not.

> **Shipped port (2026-08-19 SQLite, 2026-08-25 MySQL).** `website/` runs one codebase
> over two drivers: SQLite is the dev default, **MySQL/MariaDB 11.8.8 is production**
> (deployed). `config/dialect.php` is the only driver-aware file — column types are tokens
> (`{uuid}` → `CHAR(36)`, `{json}` → `LONGTEXT`, `{ts}` → sortable ISO string), upserts are
> rewritten (`ON CONFLICT…`→`ON DUPLICATE KEY`, `IS ?`→`<=>`). Divergences from the
> PostgreSQL design below, both ports: no PostGIS (haversine + Douglas–Peucker in
> `Geo.php`), no partitioning/BRIN (`location_fixes` is one table with a
> `(employee_id, recorded_at)` index), enums are `TEXT`+`CHECK`, arrays/`polyline`/`box`
> are JSON or scalar columns, and partial indexes flatten on MySQL. FKs are declared
> table-level (MySQL silently ignores inline `REFERENCES`). The live DB has migrations
> 001–030 applied. This doc stays the design target; the port notes mark where reality
> differs.

---

## 1. The five ideas

1. **Partition the fix table by day.** Range partitions on `recorded_at`, BRIN index
   instead of B-tree. Writes are append-only into today's partition; retention is a
   `DETACH PARTITION`, not a `DELETE`.
2. **Roll the day up once, read it forever.** `attendance_days` stores the nine
   `DaySummary` numbers. The calendar, the statistics tab, the attendance matrix and the
   team analytics all read that table — none of them ever touches a fix.
3. **Store the drawn route, not just the points.** `day_routes` keeps a simplified
   polyline per employee-day. The admin map reads one row instead of a 1 000-row scan, and
   raw fixes past the retention window can be dropped without losing the map.
4. **One identity table, two profile tables.** `Employee` and `AdminUser` share six
   fields; splitting shared identity from role-specific profile avoids both a nullable
   mega-table and a duplicated one.
5. **Denormalise exactly where history must not move.** `report_sales` carries the product
   name and colour as they were; `visit_reports` carries the company name as typed. A
   catalogue edit or a delisting can never rewrite what a seller filed.

---

## 2. Enum types

```sql
CREATE TYPE user_role          AS ENUM ('employee','admin');
CREATE TYPE work_status        AS ENUM ('not_started','working','idle','location_unavailable','offline','ended');
CREATE TYPE movement_status    AS ENUM ('moving','stationary','unknown');
CREATE TYPE location_health    AS ENUM ('ok','service_disabled','permission_denied','permission_denied_forever','background_denied','poor_accuracy','no_internet');
CREATE TYPE attendance_status  AS ENUM ('present','absent','partial','holiday','weekend','no_data');  -- 'partial' RETIRED 2026-11-08: never written, never emitted (see §attendance_days)
CREATE TYPE visit_status       AS ENUM ('in_progress','completed','unassigned');
CREATE TYPE report_status      AS ENUM ('draft','queued','uploading','submitted','failed','reviewed');
CREATE TYPE review_decision    AS ENUM ('pending','approved','rejected');
CREATE TYPE product_category   AS ENUM ('scooty','bike','bicycle','others');
CREATE TYPE notification_kind  AS ENUM ('new_product','product_updated','report_reviewed','announcement');
CREATE TYPE activity_type      AS ENUM ('day_started','session_started','travelling','arrived','stayed',
                                        'report_submitted','left','session_ended','day_ended','tracking_issue');
```

**`activity` (`02-API-PLAN.md` §3.14) is deliberately NOT in this list and must never become
a column.** It is derived at read time from `work_status`, `movement_status`,
`location_health`, the open-stop row and the fix timestamps — a presentation collapse of
state this schema already holds, computed per request. Storing it would create a second
source of truth that goes stale the moment a threshold is re-tuned, which is exactly what
read-time semantics (§4) exist to prevent. `activity_type` above is an unrelated thing: the
timeline event log.

Dart enum → PostgreSQL type:

| Dart | PostgreSQL |
| --- | --- |
| `WorkStatus` | `work_status` |
| `MovementStatus` | `movement_status` |
| `LocationHealth` | `location_health` |
| `AttendanceStatus` | `attendance_status` |
| `VisitStatus` | `visit_status` |
| `ReportStatus` | `report_status` |
| `ReviewDecision` | `review_decision` |
| `ProductCategory` | `product_category` |
| `NotificationKind` | `notification_kind` |
| `ActivityType` | `activity_type` |
| `SyncState` | — device only |
| `StatsRange` | — request parameter |

Wire values stay the Dart enum `name` (`ProductCategory.code` already does this); the
database uses snake_case, so the mapping happens once in the serialisation layer.

`SyncState` is **not** an enum here — it is device-local queue state and never reaches the
database. `StatsRange` is a request parameter, not stored.

---

## 3. Identity

```sql
CREATE TABLE organizations (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name          text NOT NULL,
  timezone      text NOT NULL DEFAULT 'Asia/Dhaka',
  weekend_days  smallint[] NOT NULL DEFAULT '{5,6}',   -- ISO weekday numbers (1=Mon..7=Sun); admin write path GET/PUT /admin/weekend-days since 2026-11-08, was seed-time only. Stored as a JSON array under the MySQL dialect
  created_at    timestamptz NOT NULL DEFAULT now()
);

-- Shared identity. Both shells authenticate here.
CREATE TABLE users (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  org_id         uuid NOT NULL REFERENCES organizations(id),
  role           user_role NOT NULL,
  name           text NOT NULL,
  email          citext NOT NULL,
  phone          text NOT NULL,
  avatar_url     text NOT NULL DEFAULT '',
  password_hash  text NOT NULL,
  active         boolean NOT NULL DEFAULT true,
  created_at     timestamptz NOT NULL DEFAULT now(),
  updated_at     timestamptz NOT NULL DEFAULT now(),
  UNIQUE (org_id, email)
);

-- Employee-only fields.
CREATE TABLE employee_profiles (
  user_id        uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  employee_code  text NOT NULL,
  designation    text NOT NULL,
  department     text NOT NULL DEFAULT '',      -- optional, free text
  region         text NOT NULL DEFAULT '',      -- optional, free text
  banner_url     text NOT NULL DEFAULT '',
  joined_on      date NOT NULL,
  reports_to_id  uuid REFERENCES users(id),     -- replaces the rendered string
  blood_group    text NOT NULL DEFAULT '',
  address        text NOT NULL DEFAULT '',
  CONSTRAINT employee_code_format CHECK (employee_code ~ '^EMP-[0-9]{3,5}$')
);
CREATE UNIQUE INDEX ON employee_profiles (employee_code);

-- Admin-only fields.
CREATE TABLE admin_profiles (
  user_id     uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  admin_code  text NOT NULL UNIQUE,
  role_title  text NOT NULL,                     -- "Zonal Manager"
  region      text NOT NULL DEFAULT ''
);
```

`department` and `region` are `NOT NULL DEFAULT ''` rather than nullable: the app treats
"not set" as an empty string everywhere, and one representation beats two. Roster search
is a trigram index over the concatenation:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX employees_search_trgm ON employee_profiles
  USING gin ((employee_code || ' ' || designation || ' ' || department || ' ' || region) gin_trgm_ops);
CREATE INDEX users_name_trgm ON users USING gin (name gin_trgm_ops);
```

```sql
CREATE TABLE devices (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  platform    text NOT NULL,
  app_version text NOT NULL,
  push_token  text,
  last_seen_at timestamptz,
  UNIQUE (user_id, platform, push_token)
);

CREATE TABLE user_preferences (
  user_id              uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  theme_mode           text    NOT NULL DEFAULT 'system',
  language             text    NOT NULL DEFAULT 'English',
  notifications_enabled boolean NOT NULL DEFAULT true,
  report_reminders     boolean NOT NULL DEFAULT true,
  session_reminders    boolean NOT NULL DEFAULT true,
  system_notifications boolean NOT NULL DEFAULT true,
  high_accuracy_mode   boolean NOT NULL DEFAULT true,
  sync_on_mobile_data  boolean NOT NULL DEFAULT true,
  battery_saver        boolean NOT NULL DEFAULT false
);
```

---

## 4. Admin-owned tracking configuration

```sql
CREATE TABLE tracking_configs (
  org_id                                 uuid PRIMARY KEY REFERENCES organizations(id),
  version                                integer NOT NULL DEFAULT 1,
  location_interval_seconds              integer NOT NULL DEFAULT 30,
  min_accuracy_metres                    double precision NOT NULL DEFAULT 50,  -- read-time: low-accuracy flag AND the distance gate
  stop_radius_metres                     double precision NOT NULL DEFAULT 75,
  stop_threshold_minutes                 integer NOT NULL DEFAULT 10,
  long_stop_threshold_minutes            integer NOT NULL DEFAULT 45,
  movement_speed_threshold_kmh           double precision NOT NULL DEFAULT 2,
  max_jump_kmh                           double precision NOT NULL DEFAULT 180,
  offline_threshold_minutes              integer NOT NULL DEFAULT 10,
  location_unavailable_threshold_minutes integer NOT NULL DEFAULT 15,
  sync_batch_size                        integer NOT NULL DEFAULT 50,
  sync_interval_seconds                  integer NOT NULL DEFAULT 300,  -- config v9, SHIPPED (031)
  distance_filter_metres                 integer NOT NULL DEFAULT 20,   -- config v9, SHIPPED (031); also the server's minimum-step distance gate
  movement_exit_speed_kmh                double precision NOT NULL DEFAULT 3,  -- config v9, SHIPPED
  live_sync_interval_seconds             integer NOT NULL DEFAULT 15,   -- config v9, SHIPPED
  updated_at                             timestamptz NOT NULL DEFAULT now(),
  updated_by                             uuid REFERENCES users(id),
  CHECK (location_interval_seconds BETWEEN 5 AND 600),
  CHECK (long_stop_threshold_minutes >= stop_threshold_minutes),
  CHECK (sync_interval_seconds <= offline_threshold_minutes * 30),  -- v9: ≤ offlineThreshold×60/2
  CHECK (movement_exit_speed_kmh >= movement_speed_threshold_kmh),  -- v9: the dead band must not invert
  CHECK (live_sync_interval_seconds BETWEEN 5 AND 120),
  CHECK (live_sync_interval_seconds < sync_interval_seconds)
);
```

**Config version 9 — SHIPPED and APPLIED ON PRODUCTION 2026-11-04T18:20:03Z.** The four rows
flagged above back `TrackingConfig.syncIntervalSeconds`, `distanceFilterMetres`,
`movementExitSpeedKmh` and `liveSyncIntervalSeconds` (see `02-API-PLAN.md` §3.12, which is
authoritative for the wire shape and the validation rules). They exist in the deployed
MariaDB schema: `website/database/migrations/031_tracking_config_v9.php` reported
`migrated 031_tracking_config_v9` in deploy-server.yml run 41886059371, and every deploy
since has reported `nothing to migrate`.

**Why 9 and not 8.** The first two columns were specced for v8, migration 031 was written as
`031_tracking_config_v8.php` and the deploy history said it may already have been applied —
and the ledger keys on **filename**, so a retarget under the old name would have been
silently skipped and the new columns would never have landed. It therefore shipped under the
new filename `031_tracking_config_v9.php` with every step idempotent and a version floor
(`version < 9 → 9`, else `+1`) that handles 7 → 9 and 8 → 9 identically. One migration, one
deploy, all of it or none of it — which is how it went.

Decisions, no longer open:

- The `sync_interval_seconds` ceiling is **both**: a `422` in the update path (so an admin
  gets told, rather than silently getting a different number than they typed) **and** the
  `CHECK` above as the backstop. §3.12 of `02` is the authority on which rules `422` and
  which clamp — `movement_exit_speed_kmh >= movement_speed_threshold_kmh` is a `422`;
  `live_sync_interval_seconds` clamps on both of its rules. The `CHECK`s here mirror only
  the invariants that must never be violated by any path, including a hand-run `UPDATE`.
- Cross-field rules (`sync_interval_seconds` vs `offline_threshold_minutes`,
  `movement_exit_speed_kmh` vs `movement_speed_threshold_kmh`) must be validated against the
  **post-write** values when both sides move in one `PUT`.
- `tracking_config_history` (below) is "same columns + `changed_at`, `changed_by`", so the
  history migration must add all four there too.
- Bump the seeded/default `version` to **9** at all three insert sites that seed it today
  (`api/support/Ctx.php`, `database/seeds/001_bootstrap_admin.php`,
  `database/demo/seed.php`) plus the migration-time bump, so a device holding a 7 (or an 8)
  `ETag` re-pulls.

**MySQL port note.** `double precision` is `double` and `timestamptz` is `datetime(6)` under
`config/dialect.php` as elsewhere in this document; MariaDB 11.8.8 enforces `CHECK`
constraints, so the four above carry over rather than becoming app-only rules.

One row per organisation — **not** per employee, not per device. `version` bumps on every
write and backs the `ETag`, so a device that already has the current config gets a `304`.

Config is **read-time semantics**: stored fixes are never rewritten when a threshold
changes. Stop and long-stop classification is re-derived on read. The one exception worth
naming: `stop_records` are materialised (§6), so a change to `stop_radius_metres` or
`stop_threshold_minutes` must enqueue a **re-derivation job** for open and same-day
sessions. Closed historical days are left alone deliberately — retroactively editing what
counted as a visit three weeks ago would make the calendar disagree with itself.

Keep a `tracking_config_history` table (same columns + `changed_at`, `changed_by`) so a
dispute about "what the rule was on the 3rd" has an answer.

### Config v10 — five more columns (**BUILT in migration 036, UNTRACKED in git, NOT APPLIED anywhere**)

> `036_universal_distance_engine.php` exists only in the working tree — **untracked**, not
> committed, not on `main`, applied on SQLite by the suite and **never on production**,
> which still serves **v9**. It floors `tracking_configs.version` to 10. Reported
> 2026-11-05T18:31Z, verified and merged 2026-11-06.

```sql
ALTER TABLE tracking_configs ADD COLUMN
  min_step_metres            double precision NOT NULL DEFAULT 15,   -- v10: the SERVER's measurement floor
  accuracy_good_metres       double precision NOT NULL DEFAULT 20,   -- v10: upper edge of the "good" band
  accuracy_step_factor       double precision NOT NULL DEFAULT 1,    -- v10: scales the step by the fix's own error
  gps_gap_warning_seconds    integer          NOT NULL DEFAULT 60,   -- v10
  gps_gap_incomplete_seconds integer          NOT NULL DEFAULT 180,  -- v10
  CHECK (accuracy_good_metres <= min_accuracy_metres),   -- chk_accuracy_bands
  CHECK (gps_gap_incomplete_seconds > gps_gap_warning_seconds),  -- chk_gap_order
  CHECK (accuracy_step_factor >= 0),                     -- chk_accuracy_step_factor
  CHECK (min_step_metres >= 0);                          -- chk_min_step_metres
```

All five are mirrored **nullable** into `tracking_config_history`, the same way v9's four
are: a history row written before 036 has no value for them and `0` would be a claim it
never made.

**`min_step_metres` exists because `distance_filter_metres` was doing two unrelated jobs**
— the OS capture filter on the device and the server's minimum measurement step — and those
want **opposite** values. Capture wants to go down (10 m) so a winding track keeps its
corners; measurement wants to stay up (15 m) so a parked phone's ~13 m drift at 15 m
accuracy is never booked as travel. One column cannot be both, which is why lowering the
filter alone would have inflated every parked session. `distance_filter_metres` keeps its
meaning and its column; measurement moved here.

**There is deliberately no `accuracy_moderate_metres`.** It was in the first draft of 036
and was removed before the migration was cemented: it and `min_accuracy_metres` are the
same boundary, and two columns for one fact are free to drift until the engine's "poor" and
the admin map's "rough" disagree about the same fix — the exact overload this migration
exists to undo, reintroduced one column over. Bands are good `0..accuracy_good_metres`,
moderate up to `min_accuracy_metres`, poor above.

**The MySQL branch is unverified.** 036 was proven idempotent over three applications on
SQLite with all four `CHECK`s enforced; there is no MySQL in the dev environment, so the
MySQL path is mirrored from 031's `addCheck` pattern line for line and has never run. MySQL
column-level `CHECK` may reference only its own column, which is why every cross-column rule
is added as a named table constraint. **Confirm on the first production `db:sync`.**

**Three new defaults do not come from the column defaults.** `Ctx::config()`'s materialising
INSERT writes `location_interval_seconds = 10`, `distance_filter_metres = 10`,
`min_accuracy_metres = 30` and `version = 10` explicitly (`Ctx.php:169-172`). Neither driver
can change an existing column's `DEFAULT` without rewriting the table and SQLite cannot at
all, and that INSERT is the only path that materialises a missing row — so it is the single
documented source for the new values, while **every org row an admin has already tuned is
left exactly as it is.** Mirror `Ctx::config()`, not the DDL above, when you need to know
what a fresh org gets.

**Two of these columns gate derived distance (shipped 2026-11-05).** `Engine::chainDistanceKm`
reads both when it sums a fix chain into kilometres: `min_accuracy_metres` decides which
fixes are allowed to *measure* a leg, and `distance_filter_metres` is the **minimum step** —
a hop shorter than it contributes 0 km and leaves the anchor where it is. Two things follow
that are easy to miss from the column names alone:

- `distance_filter_metres` is **no longer device-side only.** It was documented as the GPS
  distance filter the OS is asked to honour; it is now also a server gate, so **widening it
  to save battery also coarsens the odometer** (`02` §3.12).
- `min_accuracy_metres` now has **two** read-time uses: the low-accuracy flag and this
  measurement gate. It is still **not** an ingest check — a weak fix is stored and drawn as
  before (§5), it just does not move the odometer.

Still read-time semantics, both of them: nothing is stored about the decision, so retuning
either threshold re-derives the distance on the next refresh instead of rewriting a fix.

---

## 4a. Live watch — who an admin is currently looking at

```sql
CREATE TABLE tracking_watches (
  employee_id    uuid PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  watching_until timestamptz NOT NULL,
  updated_at     timestamptz NOT NULL DEFAULT now(),
  updated_by     uuid REFERENCES users(id)
);
CREATE INDEX ON tracking_watches (watching_until);
```

Backs `POST /tracking/watch` and the `liveUntil` field on the `POST /tracking/locations`
response (`02-API-PLAN.md` §3.11, authoritative). **SHIPPED in migration 031 and live on
production since 2026-11-04T18:20Z.**

**One row per employee, not per admin — and that is a decision, not a simplification.** Two
admins watching the same person share one deadline; `POST /tracking/watch` upserts and the
deadline only ever moves **forward** inside an active window, so whoever is still watching
keeps the device live and neither admin can cut the other off. Keying by `(admin, employee)`
would mean reading the `MAX()` across rows on the hottest write path in the system to answer
one boolean, and would need a cleanup job to stop the table growing per admin per session.

**Why its own table rather than a column on `device_health`.** `device_health` is written by
the employee's device; this is written by an admin's. Separate writers, separate lifetimes,
and no contention between an ingest batch and a map that is polling. They are read together
and both are keyed by `employee_id`, so the join is trivial when it is wanted.

Read path — this is on the ingest hot path, so it has to be cheap: `POST /tracking/locations`
does exactly **one primary-key lookup** per batch (`watching_until > now()`), which is
index-only. Nothing else reads it; no fan-out, no scan.

Expiry is **passive**. A row is dead once `watching_until` is in the past; nothing has to
delete it for correctness, and a stale row is simply never true again until re-watched. The
`watching_until` index exists so an optional housekeeping `DELETE` stays cheap — not because
any read needs it. There is deliberately **no unwatch**: closing the map stops re-posting and
the TTL lapses, because an explicit unwatch would have to be delivered to the device, which
is precisely the capability this design does not have.

A watch changes **how often a device talks and nothing else** — not what is stored, not what
is derived, not what any endpoint returns about a fix. Board rule 2 is untouched by it.

---

## 5. Sessions and fixes

```sql
CREATE TABLE work_sessions (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  employee_id    uuid NOT NULL REFERENCES users(id),
  client_id      text NOT NULL,                    -- device-generated, offline-safe
  work_date      date NOT NULL,                    -- org-local calendar day
  seq            smallint NOT NULL,                -- 1-based "Session 2"
  started_at     timestamptz NOT NULL,             -- from the FIRST FIX, not the tap
  ended_at       timestamptz,
  start_lat      double precision NOT NULL,
  start_lng      double precision NOT NULL,
  distance_km    double precision NOT NULL DEFAULT 0,
  point_count    integer NOT NULL DEFAULT 0,
  -- migration 036, BUILT / UNTRACKED / NOT APPLIED — see the v10 note in §4
  distance_algo_version  integer NOT NULL DEFAULT 1,  -- 1 freezes the row to the pre-036 rule
  verified_distance_km   double precision,            -- NULL = row predates the engine. NOT 0
  incomplete_distance_km double precision,
  gps_coverage_percent   double precision,
  UNIQUE (employee_id, client_id),                 -- idempotent offline replay
  UNIQUE (employee_id, work_date, seq)
);
CREATE INDEX ON work_sessions (employee_id, work_date DESC);
CREATE INDEX open_sessions ON work_sessions (employee_id) WHERE ended_at IS NULL;
```

That partial index is what makes "who is working right now" — the dashboard's hottest
query — an index-only scan over a handful of rows.

`distance_km` and `point_count` are both **derived from the fix chain and rewritten whole**
by `Engine::refreshSession`, never incremented. They do not mean the same thing:
`point_count` is the **raw** stored fix count (what the route and the trail draw), while
`distance_km` is the **gated** travelled sum — weak fixes and sub-threshold hops are
excluded by the two config gates in §4. A session can legitimately carry 60 points and
0.0 km. Since 2026-11-05 `Engine::recompute` re-derives **every** session in the day (not
just the open one) before rebuilding stops, route and rollup, so the odometer, the route
total and `attendance_days.distance_km` cannot drift apart. The gates are **not
retroactive**: an open session self-heals on its next accepted batch, a closed historical
day keeps the number it was written with until something calls `recompute` for it — see
the board's open backfill decision.

**Migration 036 adds four more columns to this table and three to `day_routes` and
`attendance_days` — BUILT, UNTRACKED IN GIT, NEVER APPLIED TO PRODUCTION.** See the v10 note
in §4 for the status and the config side.

- **`distance_algo_version` (default `1`) is what freezes history.** A session created
  before 036 keeps `1` and is measured by `Engine::chainDistanceV1()` — the pre-036 body,
  preserved **byte-identical**, private and permanent — so **no stored historical distance
  moves and no backfill is needed** for the algorithm change. `TrackingController::startSession`
  stamps the current version at creation (`:89-95`) and `recompute()` never upgrades an
  existing v1 row. `RoutesController::livePoints` measures with `Engine::dayAlgoVersion()`
  (`:186`) rather than the current default, so a live read of an old day cannot disagree with
  its cached row. This is the one column that makes "historical records are not recalculated"
  true rather than aspirational.
- **The three reporting columns are nullable with no default, and `NULL` is not `0` or
  `100`.** A row written before 036 has no coverage figure and both of those would be a lie
  about it — the same reason `heading_deg` is nullable in 031. v1 rows leave all three
  **NULL** by design. `Present::nullableFloat()` carries that to the wire.
- `distance_km` **keeps its exact meaning** — the verified figure, never verified +
  incomplete. It is not dropped and must not be: two consumers read it.
- **`location_fixes.source` exists and nothing writes it.** The column is the server's only
  way to tell "parked, deliberately sampling slowly" from "GPS lost": the device's heartbeat
  fix is built through the same `_toLog()` as a movement sample, so the two are
  indistinguishable on the wire and in this table today. Every row therefore carries the
  `'STREAM'` default. `Engine` reads the column (`:169`, `:496`) and falls back to `'STREAM'`
  (`:440`). **The device half is not built**, which is why gap classification is
  displacement-gated rather than relying on it — see §4.
- Two new tables come with 036. **`session_gps_gaps`** (`session_id`, `employee_id`,
  `work_date`, `gap_start`, `gap_end`, `seconds`, `displacement_m`, `classification`)
  — one row per non-normal stretch, written by `Engine::storeGaps` and **replaced**, not
  appended, on re-derive. `displacement_m` is the column that makes it work: a 300 s gap
  with 5 m of displacement is a parked vehicle, a 180 s gap with 5 km is a train leaving a
  tunnel. **`session_transport_modes`** (`session_id`, `employee_id`, `work_date`, `mode`,
  `effective_from`, `effective_to`) — an interval table rather than a session column because
  the mode is changeable mid-session and the history has to survive the change;
  `effective_to` NULL means still in force. **Nothing writes to it on either side** — the
  table exists, the endpoint does not, and mode never selects a distance algorithm or varies
  capture config.

```sql
CREATE TABLE location_fixes (
  id           bigint GENERATED ALWAYS AS IDENTITY,
  session_id   uuid NOT NULL,
  employee_id  uuid NOT NULL,                      -- denormalised: every admin read filters on it
  client_id    text NOT NULL,
  recorded_at  timestamptz NOT NULL,               -- device clock
  received_at  timestamptz NOT NULL DEFAULT now(), -- server clock; skew is real
  lat          double precision NOT NULL,
  lng          double precision NOT NULL,
  geog         geography(Point,4326)
                 GENERATED ALWAYS AS (ST_MakePoint(lng, lat)::geography) STORED,
  accuracy_m   real NOT NULL,
  speed_kmh    real NOT NULL,
  heading_deg  real,                                 -- NULL = unknown. NOT 0: 0 is due north
  source       text NOT NULL DEFAULT 'STREAM',       -- migration 036: STREAM | HEARTBEAT. NOTHING WRITES IT YET
  PRIMARY KEY (recorded_at, id)
) PARTITION BY RANGE (recorded_at);

-- one partition per day, created a week ahead by a scheduled job
CREATE TABLE location_fixes_2026_08_18 PARTITION OF location_fixes
  FOR VALUES FROM ('2026-08-18') TO ('2026-08-19');

CREATE INDEX ON location_fixes USING brin (recorded_at) WITH (pages_per_range = 32);
CREATE INDEX ON location_fixes (employee_id, recorded_at);
CREATE INDEX ON location_fixes (session_id, recorded_at);
CREATE UNIQUE INDEX ON location_fixes (employee_id, client_id);   -- dedupe replayed batches
```

Notes that matter:

- `real` not `double precision` for accuracy, speed and heading — 4 bytes each, and none of
  the three is meaningful past one decimal. Over 70 M rows that is 560 MB saved for nothing
  given up.
- **`heading_deg` is nullable, and `NULL` is not `0`.** 0 means due north; "unknown" is a
  genuinely different thing and has to stay distinguishable, because the admin map rotates a
  vehicle marker by this value. A stationary device reports no bearing at all, and platforms
  signal that as `-1` or `NaN` — both normalise to `NULL` at ingest, never to `0`. A client
  that sees `NULL` must draw an unrotated marker, not one pointing north. Degrees clockwise
  from true north, `0 <= heading_deg < 360`; `heading_deg` is new in config v9's companion
  migration (`02` §3.11) and every row written before it is legitimately `NULL`.
- The generated `geog` column exists **only** for stop clustering (`ST_DWithin`). Route
  rendering reads `lat`/`lng` directly. Add the GiST index only if stop detection runs in
  SQL rather than in the ingest worker.
- `UNIQUE (employee_id, client_id)` is the idempotency guarantee for
  `POST /tracking/locations`. A replayed batch conflicts and is reported as
  `rejected: DUPLICATE` rather than duplicating the route.
- Fixes failing the `max_jump_kmh` check — or malformed/duplicate ones — are
  **rejected at ingest, not stored**. The rejection reason (`JUMP`/`MALFORMED`/`DUPLICATE`)
  goes back in the response so the device can drop them from its queue. Accuracy is **not**
  an ingest check: a fix with `accuracy_m > min_accuracy_metres` is stored like any other
  and flagged low-accuracy at read time (`accuracy_m` vs `min_accuracy_metres`), so a
  threshold change reclassifies stored rows without rewriting them. Since 2026-11-05 the
  same comparison also decides whether the fix **measures** a leg of derived distance
  (§4) — stored and drawn either way; only the kilometres skip it.

**Retention.** Keep raw fixes 90 days (configurable), then `DETACH` and archive the
partition to object storage. `day_routes` (§8) preserves the drawn route indefinitely, so
detaching costs nothing the UI can see.

---

## 6. Stops and visits (server-derived)

```sql
CREATE TABLE stop_records (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id    uuid NOT NULL REFERENCES work_sessions(id) ON DELETE CASCADE,
  employee_id   uuid NOT NULL REFERENCES users(id),
  work_date     date NOT NULL,
  arrival       timestamptz NOT NULL,
  departure     timestamptz,
  lat           double precision NOT NULL,
  lng           double precision NOT NULL,
  radius_m      double precision NOT NULL,
  visit_id      uuid REFERENCES company_visits(id) ON DELETE SET NULL,
  derived_from_config_version integer NOT NULL   -- which rules produced this row
);
CREATE INDEX ON stop_records (employee_id, work_date);
CREATE INDEX open_stops ON stop_records (employee_id) WHERE departure IS NULL;

CREATE TABLE company_visits (
  id             uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id     uuid NOT NULL REFERENCES work_sessions(id) ON DELETE CASCADE,
  employee_id    uuid NOT NULL REFERENCES users(id),
  work_date      date NOT NULL,
  company_id     uuid NOT NULL REFERENCES companies(id),
  branch_id      uuid REFERENCES branches(id),
  stop_id        uuid REFERENCES stop_records(id),
  arrival        timestamptz NOT NULL,
  departure      timestamptz,
  lat            double precision NOT NULL,
  lng            double precision NOT NULL,
  status         visit_status NOT NULL DEFAULT 'in_progress',
  deal_reference text
);
CREATE INDEX ON company_visits (employee_id, work_date);
CREATE INDEX ON company_visits (company_id, arrival DESC);
```

`derived_from_config_version` is what lets a re-derivation job find the stops produced
under an old rule set without re-scanning everything.

`CompanyVisit.reportIds` is **not** a column — it is the reverse of
`visit_reports.visit_id`. A visit can carry many reports; modelling it as an array would
invite exactly the collapse-to-one bug the model comment warns about.

---

## 7. Customers, reports, reviews

```sql
CREATE TABLE companies (
  id        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  org_id    uuid NOT NULL REFERENCES organizations(id),
  name      text NOT NULL,
  category  text NOT NULL,                 -- Distributor / Retail / Corporate
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE branches (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id uuid NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name       text NOT NULL,
  address    text NOT NULL,
  lat        double precision NOT NULL,
  lng        double precision NOT NULL,
  geog       geography(Point,4326) GENERATED ALWAYS AS (ST_MakePoint(lng, lat)::geography) STORED
);
CREATE INDEX ON branches USING gist (geog);     -- "which branch is this stop at"
```

```sql
CREATE TABLE visit_reports (
  id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  employee_id      uuid NOT NULL REFERENCES users(id),
  client_id        text NOT NULL,
  session_id       uuid NOT NULL REFERENCES work_sessions(id),
  visit_id         uuid REFERENCES company_visits(id),
  work_date        date NOT NULL,
  company_name     text NOT NULL,          -- as typed. never looked up
  branch_name      text,
  company_id       uuid REFERENCES companies(id),   -- optional link, map pin only
  branch_id        uuid REFERENCES branches(id),
  title            text NOT NULL,
  body             text NOT NULL,
  lat              double precision NOT NULL,
  lng              double precision NOT NULL,
  status           report_status NOT NULL DEFAULT 'submitted',
  deal_value       text,                   -- free text, as typed
  payment_received text,                   -- free text, as typed
  follow_up_on     date,
  submitted_at     timestamptz NOT NULL DEFAULT now(),
  UNIQUE (employee_id, client_id)
);
CREATE INDEX ON visit_reports (employee_id, submitted_at DESC);
CREATE INDEX ON visit_reports (work_date DESC, submitted_at DESC);
CREATE INDEX ON visit_reports (visit_id);
CREATE INDEX reports_company_trgm ON visit_reports USING gin (company_name gin_trgm_ops);
```

`deal_value` and `payment_received` stay `text` on purpose — they are what the seller
typed, and coercing them to `numeric` at write time would silently lose "≈2.4L" and
"paid 50%". Parse for analytics in a separate derived column if it is ever needed.

```sql
CREATE TABLE report_sales (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  report_id     uuid NOT NULL REFERENCES visit_reports(id) ON DELETE CASCADE,
  product_id    uuid NOT NULL REFERENCES products(id),
  product_name  text NOT NULL,             -- denormalised: history must not move
  category      product_category NOT NULL,
  color_name    text NOT NULL,
  color_argb    integer NOT NULL,
  units         integer NOT NULL CHECK (units > 0)
);
CREATE INDEX ON report_sales (report_id);
CREATE INDEX ON report_sales (product_id);

CREATE TABLE report_images (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  report_id     uuid NOT NULL REFERENCES visit_reports(id) ON DELETE CASCADE,
  url           text NOT NULL,
  thumbnail_url text,
  width         integer, height integer, bytes integer,
  captured_at   timestamptz,
  lat           double precision, lng double precision,
  position      smallint NOT NULL DEFAULT 0
);
```

`imageCount` in the model becomes `count(report_images)` — a derived value, not a stored
one, so it can never disagree with the actual attachments.

**Read path, shipped 2026-11-05 — no schema change was needed.** The rows were always here;
only the read was missing. `?include=images` (`02` §3.8) now returns the rows themselves,
ordered by `position`, in one batched `report_id IN (…)` query alongside the existing
`COUNT(*)`. `report_sales.imageUrl` is **not** a column and must not become one: it is
resolved at read time from `product_colors` / `product_color_images` / `products.hero_image`,
matched on `(product_id, color_name)` — which is exact, because `product_colors` carries
`UNIQUE (product_id, name)` and `report_sales.color_name` was copied from it at sale time.
The recorded consequence: re-shoot a colour's photo and older reports show the new photo
(`02` §3.8 states why that is intended).

```sql
CREATE TABLE report_reviews (
  report_id   uuid PRIMARY KEY REFERENCES visit_reports(id) ON DELETE CASCADE,
  decision    review_decision NOT NULL DEFAULT 'pending',
  note        text,
  reviewed_by uuid REFERENCES users(id),
  reviewed_at timestamptz
);
CREATE INDEX pending_reviews ON report_reviews (report_id) WHERE decision = 'pending';
```

One review per report, so it is a 1:1 side table keyed by the report — the badge count is
that partial index, and it stays fast no matter how large the archive grows. Review state
is deliberately **not** merged into `visit_reports.status`: that column is the *upload*
vocabulary and conflating the two would make the employee-side status lie.

---

## 8. Rollups — the tables every read screen actually hits

```sql
CREATE TABLE attendance_days (
  employee_id        uuid NOT NULL REFERENCES users(id),
  work_date          date NOT NULL,
  status             attendance_status NOT NULL,
  joining_time       timestamptz,          -- first valid session start
  end_time           timestamptz,
  session_count      smallint NOT NULL DEFAULT 0,
  worked_seconds     integer  NOT NULL DEFAULT 0,
  distance_km        double precision NOT NULL DEFAULT 0,
  companies_visited  smallint NOT NULL DEFAULT 0,   -- DISTINCT companies, not visits
  reports_submitted  smallint NOT NULL DEFAULT 0,
  stop_seconds       integer  NOT NULL DEFAULT 0,
  longest_stop_seconds integer NOT NULL DEFAULT 0,
  computed_at        timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (employee_id, work_date)
);
CREATE INDEX ON attendance_days (work_date);
```

This one table is `Attendance` **and** `DaySummary` **and** every cell of the team
attendance matrix **and** the input to both statistics endpoints. Written by the ingest
worker on session close — and **by nothing else.**

> **Corrected 2026-11-06. This paragraph used to say "and by a nightly job that also fills
> `absent` / `holiday` / `weekend` rows so the calendar has no gaps". No such job has ever
> existed, and the sentence caused a real bug.** `Engine::rollupDay` is reached only through
> `recompute()`, so **a day nobody worked has no row at all** — the table is sparse and
> always has been. `StatisticsController` had computed its denominator by counting rows
> (`present + partial + absent`), which with no absent rows equalled the numerator, so
> **every employee read 100 % attendance** and `absentDays` was permanently 0 on a figure
> both `02` and `03` call a headline number.
>
> The fix is **read-time, not a new table or job**: `StatisticsController::calendarWorkingDays(from, to, joinedOn)`
> counts weekdays in range that are neither a weekend (`Ctx::weekendDays()`) nor in
> `holidays`, never past `Ctx::today()` and never before the employee's `joined_on`. Same
> wire keys, corrected values, and **not retroactive by nature** — retuning the
> weekend/holiday calendar re-derives and rewrites nothing, which is standing rule 2 again.
>
> **The stored `status` enum therefore only ever holds the worked-day verdict the rollup
> writes** — which since the 2026-11-08 binary model is just `present` (or `holiday` when the
> worked day is a public holiday). `absent`, `weekend` and `noData` are read-time answers that
> no row ever carries — the opposite of what the enum's presence in the DDL suggests, so do not
> code against a stored `absent`.
>
> **Binary attendance, 2026-11-08 (migration 038, built/untracked/not deployed).**
> `Engine::classifyDay` dropped its 4-hour `FULL_DAY_SECONDS` threshold and the `partial`
> status is **retired**: the rule is now holiday → weekend → (no session: future `no_data` /
> past `absent`) → `present`. Migration 038 ran a **one-way** data fold
> `UPDATE attendance_days SET status='present' WHERE status='partial'`, so no stored `partial`
> survives and the enum value is legacy-only (SQLite/MySQL store `status` as plain text, so the
> enum is not a hard DB constraint here). `worked_seconds` is still computed and still feeds the
> `summary`/statistics tiles — it just no longer decides `status`. `02` §3.5.
>
> **One approximation is flagged rather than fudged:** `team()`'s single aggregate
> denominator is `calendarWorkingDays × headcount` and does **not** subtract per-employee
> joining dates (the per-employee rows do). A month with recent hires reads a little low at
> the team total. Exactness costs one calendar pass per head.

The `(employee_id, work_date)` primary key makes a 120-day calendar a single range scan,
and `(work_date)` makes the team matrix for a month one scan of ~360 rows.

### The holiday calendar

```sql
CREATE TABLE holidays (                              -- migration 002; recurrence added by 038
  org_id      uuid NOT NULL REFERENCES organizations(id),
  date        date NOT NULL,                         -- yyyy-mm-dd; the ANCHOR and the uniqueness key
  name        text NOT NULL,
  recurrence  text NOT NULL DEFAULT 'one_time',      -- 'one_time' | 'yearly'; added by migration 038
  PRIMARY KEY (org_id, date)
);
```

The source `Engine::holidayOn` / `classifyDay` / the statistics denominator / the
`NON_WORKING_DAY` session guard all read for a public holiday. The admin CRUD is
`GET/POST/DELETE /admin/holidays` (`02` §3.5a). **`recurrence` was added 2026-11-08 (migration
038, built/untracked/not deployed), defaulting to `one_time`** so every pre-existing holiday
keeps its exact meaning with no backfill. A `yearly` holiday stores a single anchor row and is
matched at read time by **month-day** via the portable `SUBSTR(date, 6, 5)` (not `strftime` —
prod is MariaDB), projected onto whichever year is read; a Feb-29 anchor simply has no
occurrence in a non-leap year. The PK stays `(org_id, date)`, so a `POST` upserts on the
anchor. Weekly rest days are **not** here — they are `organizations.weekend_days` (§3), applied
by the same engine helpers.

```sql
CREATE TABLE day_routes (
  employee_id     uuid NOT NULL REFERENCES users(id),
  work_date       date NOT NULL,
  point_count     integer NOT NULL,
  total_distance_km double precision NOT NULL,
  bounds          box NOT NULL,                    -- min/max lat-lng, precomputed
  polyline        jsonb NOT NULL,                  -- [[lat,lng,t,sessionIdx], ...] simplified
  first_fix_at    timestamptz, last_fix_at timestamptz,
  PRIMARY KEY (employee_id, work_date)
);
```

Built on session close by Douglas–Peucker at ~8 m tolerance. A 1 000-point day becomes
~150 points. This is what `GET /routes` reads for any date but today; today reads
`location_fixes` live. It also means raw-fix retention is an operational choice rather than
a product one.

```sql
-- route_geometry (migration 008) — road-matched geometry cache, one row per employee-day.
-- Backs the `matched` object on GET /routes. Filled on first single-employee read of a
-- closed day (subject=team serves cache-only); `php vayu match` backfills.
CREATE TABLE route_geometry (
  employee_id        uuid NOT NULL REFERENCES users(id),
  work_date          date NOT NULL,
  engine             text NOT NULL,            -- osrm | valhalla
  profile            text NOT NULL,
  status             text NOT NULL,            -- ok | partial
  confidence         double precision,
  distance_km        double precision NOT NULL,
  segments           {json} NOT NULL,          -- per session: {seq, polyline6, pointCount, matched, ratio}
  source_point_count integer NOT NULL,         -- invalidation vs day_routes.point_count
  PRIMARY KEY (employee_id, work_date)
);
```

Invalidated when `source_point_count` drifts from `day_routes.point_count`, or the engine /
profile names change. A day where nothing matched is deliberately not cached (usual cause
is a matcher that was down — retry, don't remember).

```sql
-- Timeline. Built from sessions + stops + visits + reports so the client never joins.
CREATE TABLE activity_events (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  employee_id  uuid NOT NULL REFERENCES users(id),
  work_date    date NOT NULL,
  type         activity_type NOT NULL,
  occurred_at  timestamptz NOT NULL,
  end_at       timestamptz,
  title        text NOT NULL,
  subtitle     text,
  company_name text, branch_name text,
  duration_seconds integer,
  report_id    uuid REFERENCES visit_reports(id) ON DELETE CASCADE,
  visit_id     uuid REFERENCES company_visits(id) ON DELETE CASCADE,
  is_alert     boolean NOT NULL DEFAULT false
);
CREATE INDEX ON activity_events (employee_id, work_date, occurred_at);
```

Materialised rather than computed per request: the same timeline is read by the employee's
Home, the employee's activity tab and the admin's employee-day view, and it is a four-way
join otherwise.

**`type = 'tracking_issue'` now carries TWO senses, and `is_alert` is the only thing that
separates them (changed 2026-11-05).** Weak GPS stopped being a status anybody is shown; an
outage is reported instead, as a **pair** of rows:

| `is_alert` | `title` | `subtitle` | Written by |
| --- | --- | --- | --- |
| `1` | `Offline` | the plain-language reason for that outage value | `Engine::logAlert`, on entering one of the five outage health values |
| `0` | `Back online` | how long the gap was | `Engine::logRecovery`, on the first non-outage value that follows |

Before this, an outage wrote `title = 'Tracking issue'` with the camelCase health enum as
the subtitle, and nothing ever closed it — so nobody reading a day could tell a two-minute
gap from a two-hour one. **No migration and no change to the `activity_type` enum**, which
is why the type name no longer tells the whole story: a reader that switches on `type` alone
will paint a recovery row in the alert colour with a crossed-out GPS glyph, the opposite of
its own text. Read `is_alert` **before** the type.

`poor_accuracy` writes **neither** row — it is accepted on `POST /tracking/health` for
builds already in the field and is otherwise inert — but it does **close** an open outage,
because any non-outage value does.

**Old rows are not backfilled.** Days before 2026-11-05 still read `Tracking issue` /
`poorAccuracy` and nothing rewrites them; whether that is acceptable (they age out of view)
or wants a one-off `UPDATE` is an open mobile-agent/web-agent item on the board.

**A dangling `Offline` row is a known hole.** The recovery row is written only when the
device comes back and says so, so a killed app, a dead phone, or location left off until the
next morning leaves the outage open and the day simply ends with it. Owned by Phase 3 of
`09-SESSION-DARK-TIME-PLAN.md` — and it must close at the **same last-evidence timestamp**
Phase 3 stamps the session with, or the timeline and the timesheet will disagree about when
the employee stopped.

### Day closeout and reopen (migration 009)

```sql
-- day_closeouts — APPEND-ONLY. A day may close, be reopened, and close again, and the
-- original declaration is kept each time, so there is deliberately NO UNIQUE on
-- (employee_id, work_date). The day is locked iff a row exists with reopened_at IS NULL.
CREATE TABLE day_closeouts (
  id                   uuid PRIMARY KEY,
  employee_id          uuid NOT NULL REFERENCES users(id),
  work_date            date NOT NULL,
  client_id            text NOT NULL,          -- idempotency guard
  state                text NOT NULL,          -- closed_by_employee | closed_by_system
  ended_at             timestamptz NOT NULL,
  declared_distance_km double precision, declared_visits smallint,
  rating               smallint NOT NULL,      -- 1..5; system close writes a fixed 5 placeholder
  tags                 {json} NOT NULL DEFAULT '[]',
  feedback             text,
  -- measured figures stored beside declared; declared never overwrites attendance_days
  reopened_at          timestamptz,
  UNIQUE (employee_id, client_id)
);

-- day_reopen_audit — its own table (not audit_log) so reopen count/reason stay queryable.
CREATE TABLE day_reopen_audit (
  closeout_id uuid NOT NULL REFERENCES day_closeouts(id),
  actor_id    uuid NOT NULL REFERENCES users(id),
  reason      text NOT NULL,
  occurred_at timestamptz NOT NULL
);
```

**`rating=5` on a `closed_by_system` row is a placeholder, not an opinion** — anything that
ever averages `rating` MUST exclude `state='closed_by_system'`.

`users.must_change_password` (migration 010, first ALTER in the project) is true exactly
while an account is on a server-generated password; advisory, nothing gates on it.

---

## 9. Catalogue

```sql
CREATE TABLE products (
  id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  org_id           uuid NOT NULL REFERENCES organizations(id),
  category         product_category NOT NULL,
  brand            text NOT NULL,
  name             text NOT NULL,
  model_code       text NOT NULL,
  rating           real NOT NULL CHECK (rating >= 0 AND rating <= 5),
  warranty_years   smallint NOT NULL,
  warranty_note    text NOT NULL,
  range_km         integer NOT NULL,
  top_speed_kmph   integer NOT NULL,
  charging_time    text NOT NULL,
  battery_capacity text NOT NULL,
  motor_power      text NOT NULL,
  load_capacity_kg integer NOT NULL,
  highlights       text[] NOT NULL DEFAULT '{}',
  active           boolean NOT NULL DEFAULT true,   -- delisted = false, never deleted
  listed_at        timestamptz,                     -- null for the seed catalogue
  created_by       uuid REFERENCES users(id),
  updated_at       timestamptz NOT NULL DEFAULT now(),
  UNIQUE (org_id, model_code)
);
CREATE INDEX ON products (org_id, category) WHERE active;
CREATE INDEX products_search_trgm ON products USING gin ((brand||' '||name||' '||model_code) gin_trgm_ops);

CREATE TABLE product_colors (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id uuid NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name       text NOT NULL,
  argb       integer NOT NULL,               -- 0xAARRGGBB
  in_stock   boolean NOT NULL DEFAULT true,
  position   smallint NOT NULL DEFAULT 0,
  UNIQUE (product_id, name)
);

-- Gallery is PER COLOUR, which is how the data arrives.
CREATE TABLE product_color_images (
  id        uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  color_id  uuid NOT NULL REFERENCES product_colors(id) ON DELETE CASCADE,
  url       text NOT NULL,
  position  smallint NOT NULL DEFAULT 0
);
```

**There is no price column, and there must not be one.** The field surface logs units and
the payment actually collected; a price column would leak into the spec sheet the first
time somebody writes a generic renderer. A test in the app already asserts the spec sheet
never shows one.

`active = false` is delisting: the product vanishes from the seller's picker while every
report that references it keeps reading correctly, because `report_sales` denormalises the
name and colour.

---

## 10. Notifications

```sql
CREATE TABLE notifications (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  org_id     uuid NOT NULL REFERENCES organizations(id),
  kind       notification_kind NOT NULL,
  title      text NOT NULL,
  message    text NOT NULL,
  product_id uuid REFERENCES products(id) ON DELETE CASCADE,
  report_id  uuid REFERENCES visit_reports(id) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE notification_recipients (
  notification_id uuid NOT NULL REFERENCES notifications(id) ON DELETE CASCADE,
  user_id         uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  read_at         timestamptz,
  PRIMARY KEY (notification_id, user_id)
);
CREATE INDEX unread_notifications ON notification_recipients (user_id) WHERE read_at IS NULL;
```

Fan-out table rather than a `read` flag on the notification: one product listing notifies
the whole field team, and each person's read state is their own. The badge is that partial
index.

---

## 11. Audit

```sql
CREATE TABLE audit_log (
  id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  org_id      uuid NOT NULL,
  actor_id    uuid REFERENCES users(id),
  entity      text NOT NULL,           -- 'tracking_config' | 'employee' | 'product' | 'report_review'
  entity_id   text NOT NULL,
  action      text NOT NULL,           -- 'create' | 'update' | 'delist' | 'deactivate'
  before      jsonb,
  after       jsonb,
  occurred_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON audit_log (entity, entity_id, occurred_at DESC);
```

Tracking-rule changes, roster edits and delistings are all decisions somebody will later
be asked to justify. Cheap to write, impossible to reconstruct after the fact.

---

## 12. Query → index map

| Query | Path |
| --- | --- |
| Dashboard live roster | `open_sessions` partial index + `attendance_days` today + last fix per employee |
| "Who is on a long stop" | `open_stops` partial index, `now() - arrival >= long_stop_threshold` |
| Employee Home | `work_sessions (employee_id, work_date)` + `activity_events (employee_id, work_date)` |
| Calendar month | `attendance_days` PK range scan |
| Team attendance matrix | `attendance_days (work_date)` — one scan per month |
| Statistics (either scope) | `attendance_days` aggregate; never touches fixes |
| Route, historical | `day_routes` PK — single row |
| Route, today | `location_fixes (session_id, recorded_at)` within today's partition |
| Review inbox | `pending_reviews` partial index |
| Badge counts | `pending_reviews` / `unread_notifications` partial indexes |
| Roster search | `pg_trgm` GIN over name + code + department + region |
| Catalogue by category | `products (org_id, category) WHERE active` |
| Fix ingest dedupe | `location_fixes (employee_id, client_id)` unique |
| Live-watch check, per ingest batch | `tracking_watches (employee_id)` primary key (§4a) |

Every screen in both apps resolves through an index above. No screen scans `location_fixes`
except the live map for the current day.

---

## 13. Model → table coverage

| Model | Table(s) |
| --- | --- |
| `Employee` | `users` + `employee_profiles` |
| `AdminUser` | `users` + `admin_profiles` |
| `EmployeeDraft` | write payload → `users` + `employee_profiles` |
| `Attendance` / `DaySummary` | `attendance_days` |
| `WorkSession` | `work_sessions` |
| `LocationLog` | `location_fixes` |
| `SyncSnapshot` | **device only** — not persisted |
| `StopRecord` | `stop_records` |
| `CompanyVisit` | `company_visits` (+ `visit_reports.visit_id` for `reportIds`) |
| `VisitReport` | `visit_reports` |
| `ProductSaleLine` | `report_sales` |
| report images (`imageCount`) | `report_images` |
| `ReportReview` | `report_reviews` |
| `ReportInboxItem` | join: `visit_reports` × `users` × `report_reviews` |
| `Company` / `Branch` | `companies` / `branches` |
| `Product` | `products` |
| `ProductColor` | `product_colors` + `product_color_images` |
| `ProductDraft` | write payload → `products` (+ colors) |
| `ActivityEvent` | `activity_events` |
| `AppNotification` | `notifications` + `notification_recipients` |
| `PeriodStatistics` / `TeamStatistics` / `EmployeeMetric` / `DailyMetric` | aggregates over `attendance_days` |
| `HomeSnapshot` / `EmployeeDay` | composed: sessions + stops + visits + reports + activity + rollup |
| `TeamMember` / `TeamOverview` | composed: `users` × `open_sessions` × `attendance_days` × `open_stops` |
| `RouteTrack` / `LatLngBounds` | `day_routes` (historical) · `location_fixes` (today) |
| `TeamAttendanceGrid` / `TeamAttendanceRow` | pivot over `attendance_days` |
| `TrackingConfig` | `tracking_configs` (+ `tracking_config_history`) |
| device preferences | `user_preferences` |
| auth / push | `users`, `devices` |
| enums | §2 |

Nothing in `lib/data/models/` is unaccounted for. The three model concepts with **no**
table are correct omissions: `SyncSnapshot` (device queue state), `StatsRange` (a request
parameter), and the derived predicates in §9 of the data doc (presentation rules).

---

## 14. Migration order

`organizations` → `users` → `employee_profiles` / `admin_profiles` → `devices`,
`user_preferences`, `tracking_configs` → `companies` → `branches` → `products` →
`product_colors` → `product_color_images` → `work_sessions` → `location_fixes` (+ partition
job) → `company_visits` → `stop_records` (circular FK with visits: create the FK after
both) → `visit_reports` → `report_sales`, `report_images`, `report_reviews` →
`attendance_days`, `day_routes`, `activity_events` → `notifications`,
`notification_recipients` → `audit_log`.

Shipped migration ledger (`website/database/migrations/`, 001–038 on disk; **001–031
applied on production, 032–038 not**): 001–007 core/tracking/
catalogue/session/report/rollup/notification tables, **008** `route_geometry`, **009**
`day_closeouts` + `day_reopen_audit`, **010** `users.must_change_password`, 011+ the website
admin / content / catalogue / warranty / app-release / dealer-locations / account-deletion /
messaging tables (website side, no inbox note — not described above).

> **Status correction, 2026-11-06: "on `main`" was wrong for 032–036.** All five of these
> migration files are **untracked in git** (`git status` → `??`) — they are in the working
> tree only, not committed, not on `main`, and not applied to production. The board said "on
> `main`, not deployed" for the messaging tranche and for v10; the first half was false in
> both cases. Production's applied ledger stops at **031**.

**032–035 — messaging, applied on SQLite 2026-11-05, NOT yet on production.** Website side,
so they are named rather than modelled above, and nothing the mobile app reads is touched:
**032** `messaging_credentials` (AES-256-GCM ciphertext in `value_cipher`, readable-back
non-secrets in `value_plain`, in one table so a provider's config is one query and one
atomic save) + `messaging_routes` (per-event delivery chains, seeded with a static default
order — see the board's Discrepancies for what that order actually is); **033**
`webhook_events`; **034** `email_messages` plus `attempt_group` / `chain_position` /
`is_test` on all three message tables and `provider` on `whatsapp_messages`, which
`channel` alone no longer identifies; **035** `otp_challenges.code_cipher` +
`code_cipher_expires_at` + `otp_reveal_audit` — the cipher sits **beside** the bcrypt hash,
never instead of it, so `password_verify` remains the only verification path and an
unreadable cipher cannot weaken the check. These four need `APP_KEY` in the environment
before the panel can store anything; without it the store is read-only and the app falls
back to `website_settings` then env.

**036 — universal distance engine, config v10. BUILT, UNTRACKED, applied on SQLite by the
suite, NEVER on production.** Five `tracking_configs` columns + four `CHECK`s + the nullable
history mirror (§4); `location_fixes.source`; `distance_algo_version` +
`verified_distance_km` / `incomplete_distance_km` / `gps_coverage_percent` on `work_sessions`
and `day_routes`, the three reporting columns on `attendance_days`; the new
`session_gps_gaps` and `session_transport_modes` tables (§5); a `user_preferences` reset
(`high_accuracy_mode = 1`, `sync_on_mobile_data = 1`, `battery_saver = 0` on all rows,
because those three became server-fixed — `02` §3.2); and the `version` floor to **10**.
Idempotent over three applications on SQLite with all four `CHECK`s enforced; **the MySQL
branch has never run** and mirrors 031's `addCheck` pattern. Ordering inside it follows the
same rule 031 set: columns before the `version` bump, so a device never re-pulls a config
whose columns do not exist yet.

**Two things gate this deploy and neither is in the migration.** (1) `location_fixes.source`
is useless until the device sends it, and gap classification was deliberately made
displacement-aware so 036 does not *depend* on it — but deploying ahead of the client half
means the one case `source` was added to disambiguate falls back to displacement alone.
(2) The mobile client's pre-fetch config fallbacks still read the **old** `60 / 20 / 50`,
so a cold start before the first `GET /config` captures at the pre-036 cadence.

**037 — client-reported odometer (algo v3). On disk, no inbox report here, so named not
modelled** (committed, unlike 038 below). **038 — holiday recurrence + binary attendance.
BUILT, UNTRACKED, applied on SQLite by `tests/attendance-binary-holidays.php`, NEVER on
production.** Two decisions land together: (a) `holidays.recurrence text NOT NULL DEFAULT
'one_time'` (`one_time`|`yearly`; PK stays `(org_id,date)`, `date` the anchor) — additive, no
backfill; (b) a one-way data fold `UPDATE attendance_days SET status='present' WHERE
status='partial'`, because `Engine::classifyDay` dropped its 4-hour threshold and `partial` is
retired. `down()` is deliberately a no-op: SQLite cannot drop a column and the `partial →
present` fold is not reversible regardless — the same stance 037/036/031/010 take for an added
column. `02` §3.5 / §3.5a.

**Config v9 — shipped as migration 031, applied on production 2026-11-04T18:20:03Z.** It was
written first as `031_tracking_config_v8.php` and retargeted under the **new filename**
`031_tracking_config_v9.php`, because the ledger keys on filename (§4). The v9 migration
carries, together and atomically: the four
`tracking_configs` columns + their `CHECK`s, the same four mirrored into
`tracking_config_history`, `location_fixes.heading_deg` (§5), the new `tracking_watches`
table (§4a), and the `version` bump to **9** so every device's `ETag` misses and re-pulls.
Ordering inside it: `tracking_configs` and `tracking_config_history` before the `version`
bump (a device must never re-pull a config whose columns do not exist yet);
`tracking_watches` after `users`. `location_fixes.heading_deg` is independent of the rest
and can go first. Adding a nullable column to the partitioned `location_fixes` is metadata
only on every partition — no rewrite, no backfill, and every pre-existing row is
legitimately `NULL`.
