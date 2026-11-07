---
agent: web-agent
date: 2026-11-06T18:16Z
status: done
breaking: yes — new request field on two tracking endpoints + new session field; additive and backward-compatible, but it changes the SOURCE of distanceKm for v3 sessions. 02-API-PLAN.md and 03-DATABASE-SCHEMA.md both need updating.
---

# Client odometer becomes the headline distance (distance algo v3)

## Why

The v2 universal engine undercounts badly in the field: the phone captures one
fix ~every `location_interval_seconds` while moving, which lands on top of
`gps_gap_warning_seconds` (60 s), so ordinary driving is classified as gap
travel and routed into `incompleteKm`. The verified **headline** then collapses
(observed: a real 84.5 km day read 4.9 km after Day-End, while the phone's own
odometer showed the correct figure live). v3 makes the phone's odometer the
headline and keeps the GPS-derived numbers as diagnostics.

## What changed (website/ only)

- `database/migrations/037_client_odometer_distance.php` (new) → adds
  `work_sessions.client_distance_km` (nullable float). No config change.
- `api/support/Engine.php`:
  - `DISTANCE_ALGO_VERSION` 2 → **3** (new sessions stamp v3; v1/v2 frozen).
  - new `sessionHeadlineKm()` → headline = `client_distance_km` for a v3 session,
    falling back to the derived verified figure while it is NULL.
  - `refreshSession()` writes the headline to `distance_km`; the three reporting
    columns stay the FIX-DERIVED verified/incomplete/coverage.
  - `buildDayRoute()` → `total_distance_km` = summed odometer for v3 (verified
    column stays the derived sum); v1/v2 unchanged.
  - `rollupDay()` → `verified_distance_km` now sums the per-session *derived*
    verified column, not the headline (they diverge under v3).
  - new `liveDayDistanceKm()` → live route total reads the stored odometer sum
    for v3; v1/v2 keep `chainDistanceKm`.
- `api/controllers/TrackingController.php`:
  - new `applyClientOdometer()` → stores `max(stored, reported)` from the
    request's `measuredDistanceKm` (v3 sessions only; absent/garbage never fails
    the batch).
  - `POST /tracking/locations` and `PATCH /tracking/sessions/{id}` both accept
    and apply `measuredDistanceKm` before recompute.
  - `RoutesController::livePoints` → uses `Engine::liveDayDistanceKm`.
- `api/support/Present.php` → `session` gains additive nullable `clientDistanceKm`
  (transparency; `distanceKm` is still the headline consumers read).

## API contract delta (for 02-API-PLAN.md)

- `POST /tracking/locations` request: optional `measuredDistanceKm` (number, km,
  the open session's ABSOLUTE odometer).
- `PATCH /tracking/sessions/{id}` request: optional `measuredDistanceKm` (the
  session's final odometer).
- Session response object: additive nullable `clientDistanceKm`.
- `distanceKm` semantics for a **v3** session: the client odometer, not the
  fix-derived figure. `verifiedDistanceKm` / `incompleteDistanceKm` /
  `gpsCoveragePercent` keep their v2 meaning (diagnostics).
- Store is monotonic (`max`): replays/out-of-order/handoff can only hold or
  raise it.

## Schema delta (for 03-DATABASE-SCHEMA.md)

- `work_sessions.client_distance_km` FLOAT NULL — device odometer, source of
  `distance_km` for a v3 session; NULL = no report yet (Engine falls back).
- `distance_algo_version = 3` documented alongside 1 and 2.

## Verification

- All 27 PHP suites pass (`php tests/*.php`), `php -l` clean on every changed file.
- `tests/tracking-distance.php` gains section 9: reproduces the 4 km→gap collapse
  and proves the reported odometer restores it as the headline while the derived
  verified/incomplete/coverage and gap rows are untouched; day rollup, drawn
  route and `liveDayDistanceKm` all agree on the summed odometer.
- Existing v2 tests pinned to literal algo 2 (the constant moved to 3).

## Needs doing next

- [ ] docs-agent: update `02-API-PLAN.md` (fields above) and
      `03-DATABASE-SCHEMA.md` (column + algo v3), and the board.
- [ ] (deploy) migration 037 must run on production before the mobile build that
      sends `measuredDistanceKm` is released — see deploy workflow.

## Paired with

`mobile-20261106T1816Z-report-odometer.md` (the client that sends the field).
