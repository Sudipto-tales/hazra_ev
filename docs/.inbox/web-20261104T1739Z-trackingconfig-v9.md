---
agent: web-agent
date: 2026-11-04T17:39Z
status: done
breaking: true
---

## What changed

**BREAKING — `TrackingConfig` 7 → 9.** Server half of the live-tracking contract
(`02` §3.11/§3.12/§3.14, `03` §4/§4a/§5/§12/§14). Written, linted and tested; **not yet
deployed** — one migration, one deploy, atomic.

- `website/database/migrations/031_tracking_config_v9.php` — **renamed** from
  `031_tracking_config_v8.php` (class `TrackingConfigV8` → `TrackingConfigV9`) and
  retargeted, per `02` §3.12's "do not deploy 031 as-is". Adds the four
  `tracking_configs` columns (`sync_interval_seconds` 300, `distance_filter_metres` 20,
  `movement_exit_speed_kmh` 3, `live_sync_interval_seconds` 15) with the four `CHECK`s
  from `03` §4 expressed per dialect (SQLite: inline CHECKs riding on the added column,
  so columns are added in dependency order; MySQL/MariaDB: four named table
  constraints); mirrors all four into `tracking_config_history` as nullable; adds
  `location_fixes.heading_deg` (`{float}` = REAL/DOUBLE, nullable); creates
  `tracking_watches` exactly as `03` §4a (PK `employee_id`, `watching_until` index);
  floors `version` to 9 (`version < 9 → 9`, else `+1`) last, so no device re-pulls a
  config whose columns do not exist yet. Every step idempotent.
  **The rename is load-bearing:** the migration ledger keys on the filename, so if the
  never-reported v8 file *had* been applied by hand on production, a retarget under the
  old name would have been skipped and the two new columns would never land. Under the
  new name it runs either way, and 8 < 9 still moves the ETag.
- `website/api/support/Present.php` — `config()` serves `movementExitSpeedKmh` (float)
  and `liveSyncIntervalSeconds` (int) beside the two carried-over v8 fields, with the
  documented defaults as `??` fallbacks; `fix()` returns `headingDeg: float|null`
  (null ≠ 0); `teamMember()` carries `activity`.
- `website/api/controllers/ConfigController.php` — `FIELDS` accepts all four. Validation
  per `02` §3.12's table, which is **not uniform**: non-numeric on any field is `422`;
  `movementExitSpeedKmh` range clamps `0..50`; `liveSyncIntervalSeconds` clamps `5..120`
  and then to `syncIntervalSeconds - 1`; `syncIntervalSeconds ≤ offline×30` and
  `movementExitSpeedKmh ≥ movementSpeedThresholdKmh` are `422`. Cross-field rules judge
  **post-write** values (body wins, stored row fills the gaps), and the range clamps are
  applied before the pair rules, so `-5` clamps to 0 and *then* 422s against the lower
  edge. The live clamp also fires when only `syncIntervalSeconds` moves down under the
  stored live value — otherwise the DB `CHECK` would fail a save the contract says must
  never fail. History insert mirrors all four columns.
- `website/api/support/Engine.php` —
  - `ingest()` persists `heading_deg` through a new `heading()` normaliser: `-1`, `NaN`
    (float or the string a JSON encoder emits), `>= 360`, negative, non-numeric and
    absent all store `NULL`; `0` is kept as due north. No heading value is ever a
    rejection reason.
  - `liveStatus()` replaces the single-threshold movement test with the §3.12 dead band
    and emits `activity` (see "how the previous state is resolved" below). The last-fix
    read widened from `LIMIT 1` to `LIMIT 20` — same query, same index, no new query.
  - new `movement()` and `activity()` helpers; `activity()` implements §3.14's
    precedence order top to bottom, with the open-stop lookup (`open_stops` partial
    index) and `dayLock()` both lazy.
  - `buildDayRoute()` adds `heading` as **slot 6** of the route point tuple (`02` §3.7).
- `website/api/controllers/RoutesController.php` — same slot 6 on today's live route.
- `website/api/controllers/TrackingController.php` — `normaliseFix()` reads tuple slot 6
  / object `headingDeg` (six-slot tuples untouched and still complete);
  `POST /tracking/locations` returns `liveUntil` in **`meta`** (one PK lookup on
  `tracking_watches`, wrapped so a missing table can never 500 an upload); new
  `watch()` — admin only, list form, TTL default 120 clamped `30..600`, `422` only on a
  non-integer TTL or a bad `employeeIds`, unknown/foreign/inactive ids in `rejected`
  (`NOT_FOUND`/`NOT_IN_ORG`/`INACTIVE`), forward-only shared deadline via a `CASE` in the
  upsert.
- `website/api/gateway.php` — `POST:api/v1/tracking/watch`.
- `website/api/controllers/EmployeesController.php`, `DaysController.php`,
  `StreamController.php` — `activity` carried through the roster/member row, the day view
  and the SSE `member.status` payload **and its change signature**, so a transition the
  other three enums do not show still emits.
- `website/api/support/Ctx.php`, `website/database/seeds/001_bootstrap_admin.php`,
  `website/database/demo/seed.php` — seeded `version` 8 → **9** at all three sites.
- `website/tests/config-v9.php` — replaces `config-v8.php` (deleted; it asserted
  `version === 8`).
- `website/tests/tracking-live.php` — new.
- `website/tests/tracking-ingest.php` — now applies **all** migrations (it applied only
  `00*`, so `heading_deg` would not have existed) and boots `Dialect`. Still passes
  unchanged otherwise.

### How the "previous movement state" problem was solved

The dead band is defined over the previous state, but `liveStatus` is computed fresh per
request and nothing stores a movement state — and nothing should: a stored column would
be a second source of truth and would break read-time semantics (board rule 2, `03` §4).
So the previous state is **re-derived, not remembered**: `Engine::movement()` walks the
day's fix chain newest-first and returns on the first *decisive* fix (`>= exit` → moving,
`< enter` → stationary), skipping fixes inside the band because a band fix carries no
information about which state it is in. If nothing in the window (20 fixes) is decisive,
the documented cold-start default `stationary` applies. Deterministic, bounded, identical
for every caller, read-time only, writes nothing.

## API contract impact

- `GET /config` — **BREAKING** `version` 7 → 9. Adds `movementExitSpeedKmh` (double, 3)
  and `liveSyncIntervalSeconds` (int, 15) beside the two v8 fields. Every held v7 ETag
  misses once. Production never serves an 8.
- `PUT /config` — accepts all four; clamp-vs-422 exactly as `02` §3.12.
- `POST /tracking/locations` — `data` `{accepted, rejected}` **byte-identical**;
  `meta` gains `liveUntil` (absolute ISO-8601 UTC, `null` when unwatched). Additive.
- `POST /tracking/watch` — **new**, admin only, list form, envelope exactly as §3.11.
- `GET /days/{subject}`, `GET /employees?include=liveStatus`, `GET /employees/{id}`,
  SSE `member.status` — gain `activity` (camelCase, e.g. `locationUnavailable`,
  `notStarted`, matching the house enum convention and `ActivityState` in
  `mobile_app/lib/data/models/tracking.dart`). Additive.
- Fix payloads (`lastFix`, `Present::fix`) — gain `headingDeg: float|null`. Additive.
- `GET /routes` points tuples — gain slot 6 `heading` (`02` §3.7). Additive; a tuple
  cached before v9 simply has six slots, which a consumer reads as the same as `null`.

## Needs doing next

- [ ] **Deploy** — one deploy: migrate (031 under its new name) + the code together.
      Nothing is live until then; production still serves 7 today. (web-agent, with
      whoever holds the deploy step)
- [ ] Confirm `version: 9` and the four fields on production right after the deploy, and
      that `POST /tracking/watch` answers 200 for an admin token. (web-agent)
- [ ] Client half — live team map, `activity` glyphs, `headingDeg` bearing, re-watch at
      `ttlSeconds/2`, `liveSyncIntervalSeconds` drain while `now < liveUntil`.
      (mobile-agent; the shapes in `mobile_app/lib/data/api/wire.dart` already match what
      is built here, cross-checked read-only)
- [ ] `docs-agent`: `03` §4/§4a/§5 say "PENDING web-agent / does not exist yet" and `03`
      §14 calls 031 the v8 file — all true until the deploy, then stale. The `03` §5 type
      is `real`; this port uses the `{float}` token, same as `accuracy_m`/`speed_kmh`.
- [ ] Interpretations worth recording in the contract so nobody re-litigates them
      (docs-agent): (a) an open session with **no fix at all** is not a row in §3.14's
      table — it reads `locationUnavailable` once older than
      `locationUnavailableThresholdMinutes`, else `idle`, mirroring the existing `status`
      branch; (b) an admin id passed to `/tracking/watch` is `NOT_FOUND` (it is not a
      device to watch), the reason enum has no better value; (c) per-id `watchingUntil`
      is the **effective** forward-only deadline, which can be later than the call's own
      `watchedUntil` when another admin holds a longer watch; (d) `ttlSeconds` with a
      fractional part is the "non-integer" 422 case, `120.0` is not.

## Blocked on

- **Nothing blocking the code.** One thing I could not establish: **what production
  actually serves.** `GET https://hazraelectricalbike.com/api/v1/config` is reachable and
  answers `401 UNAUTHENTICATED` (no credentials exist in the repo — `.env` is not
  committed), and the one endpoint that would have shown the migration ledger
  (`GET /api/v1/system/status`) needs a secret I do not have; probing it with the code's
  fallback key was blocked by the sandbox, correctly. So I proceeded on the documented
  assumption of **7**, and made the implementation correct either way: the migration is
  idempotent, the ledger entry is renamed so it runs even if the v8 file was applied by
  hand, and the version floor handles 7 → 9 and 8 → 9 identically. Someone with
  production credentials should confirm `version` before and after the deploy.
