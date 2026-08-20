# Database schema

PostgreSQL 15+, PostGIS for the one thing that genuinely needs it (stop detection).
Designed around a single fact: **`location_fixes` is 99 % of the row count**. Every other
decision here is downstream of keeping that table cheap to write and rare to read.

Volume model — 12 employees × 8 h × 1 fix / 30 s ≈ **11 500 rows/day**, ~4.2 M/year.
At 200 employees that is 70 M/year. The schema below stays flat at that size; a naive one
does not.

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
CREATE TYPE attendance_status  AS ENUM ('present','absent','partial','holiday','weekend','no_data');
CREATE TYPE visit_status       AS ENUM ('in_progress','completed','unassigned');
CREATE TYPE report_status      AS ENUM ('draft','queued','uploading','submitted','failed','reviewed');
CREATE TYPE review_decision    AS ENUM ('pending','approved','rejected');
CREATE TYPE product_category   AS ENUM ('scooty','bike','bicycle','others');
CREATE TYPE notification_kind  AS ENUM ('new_product','product_updated','report_reviewed','announcement');
CREATE TYPE activity_type      AS ENUM ('day_started','session_started','travelling','arrived','stayed',
                                        'report_submitted','left','session_ended','day_ended','tracking_issue');
```

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
  weekend_days  smallint[] NOT NULL DEFAULT '{5,6}',   -- ISO weekday numbers
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
  min_accuracy_metres                    double precision NOT NULL DEFAULT 50,
  stop_radius_metres                     double precision NOT NULL DEFAULT 75,
  stop_threshold_minutes                 integer NOT NULL DEFAULT 10,
  long_stop_threshold_minutes            integer NOT NULL DEFAULT 45,
  movement_speed_threshold_kmh           double precision NOT NULL DEFAULT 2,
  max_jump_kmh                           double precision NOT NULL DEFAULT 180,
  offline_threshold_minutes              integer NOT NULL DEFAULT 10,
  location_unavailable_threshold_minutes integer NOT NULL DEFAULT 15,
  sync_batch_size                        integer NOT NULL DEFAULT 50,
  updated_at                             timestamptz NOT NULL DEFAULT now(),
  updated_by                             uuid REFERENCES users(id),
  CHECK (location_interval_seconds BETWEEN 5 AND 600),
  CHECK (long_stop_threshold_minutes >= stop_threshold_minutes)
);
```

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
  UNIQUE (employee_id, client_id),                 -- idempotent offline replay
  UNIQUE (employee_id, work_date, seq)
);
CREATE INDEX ON work_sessions (employee_id, work_date DESC);
CREATE INDEX open_sessions ON work_sessions (employee_id) WHERE ended_at IS NULL;
```

That partial index is what makes "who is working right now" — the dashboard's hottest
query — an index-only scan over a handful of rows.

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

- `real` not `double precision` for accuracy and speed — 4 bytes each, and neither value is
  meaningful past one decimal. Over 70 M rows that is 560 MB saved for nothing given up.
- The generated `geog` column exists **only** for stop clustering (`ST_DWithin`). Route
  rendering reads `lat`/`lng` directly. Add the GiST index only if stop detection runs in
  SQL rather than in the ingest worker.
- `UNIQUE (employee_id, client_id)` is the idempotency guarantee for
  `POST /tracking/locations`. A replayed batch conflicts and is reported as
  `rejected: DUPLICATE` rather than duplicating the route.
- Fixes failing `accuracy_m > min_accuracy_metres` or the `max_jump_kmh` check are
  **rejected at ingest, not stored**. The rejection reason goes back in the response so the
  device can drop them from its queue.

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
worker on session close and by a nightly job that also fills `absent` / `holiday` /
`weekend` rows so the calendar has no gaps.

The `(employee_id, work_date)` primary key makes a 120-day calendar a single range scan,
and `(work_date)` makes the team matrix for a month one scan of ~360 rows.

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
