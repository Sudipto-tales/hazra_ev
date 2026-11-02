---
agent: web-agent
date: 2026-11-03T19:35Z
status: done
breaking: true
---

## What changed
- `website/database/migrations/031_tracking_config_v8.php` — NEW. Adds `sync_interval_seconds` (int, NOT NULL DEFAULT 300) and `distance_filter_metres` (int, NOT NULL DEFAULT 20) to `tracking_configs`; adds the same two columns (nullable) to the `tracking_config_history` mirror; floors the stored `version` to 8 (or bumps it if already higher) so a device holding a v7 ETag re-pulls.
- `website/api/support/Present.php` — `config()` now emits `syncIntervalSeconds` + `distanceFilterMetres` (camelCase, int) mapped from the snake_case columns, on the existing `TrackingConfig` object before `version`.
- `website/api/controllers/ConfigController.php` — added both fields to the editable `FIELDS` map (admin PUT path); added the cross-field 422 guard `syncIntervalSeconds ≤ offlineThresholdMinutes × 60 / 2`, validating the pair together when either side moves on the same write; history INSERT now also writes the two mirror columns; widened the PDOException fallback message.
- `website/api/support/Ctx.php` — the lazy `tracking_configs` insert now seeds `version = 8`.
- `website/database/seeds/001_bootstrap_admin.php` + `website/database/demo/seed.php` — config-row inserts now seed `version = 8`.
- `website/tests/config-v8.php` — NEW. End-to-end coverage (migration apply, version floor, presenter shape, DB CHECK backstop, GET 200/304, 422 rejection + pair rule, valid write + history).

## API contract impact
- `GET /api/v1/config` — **BREAKING (version 7 → 8)**. Adds `syncIntervalSeconds` (default 300) and `distanceFilterMetres` (default 20) to the `TrackingConfig` object; served at `version: 8` through the same ETag/`If-None-Match`/304 path (`config-<orgId>-<version>`), no new endpoint/envelope. Fields themselves are additive scalars; the breaking part is the `version` bump and the shape growing, exactly as `02-API-PLAN.md` §3.12 specifies.
- `PUT /api/v1/config` — both fields now writable (admin only). Out-of-range `syncIntervalSeconds` returns the standard `422 VALIDATION_FAILED` with `field: "syncIntervalSeconds"`.
- **Shipping note / consumer tolerance:** this is a shape change on a two-consumer contract. The Flutter client already defaults `distanceFilterMetres=20` locally and must tolerate a server still on v7 by falling back to local defaults (per the board's v8 task and `tracking_config.dart`); `syncIntervalSeconds` drives the mobile `_drain` timer that is still hard-coded to 5 min — mobile-agent's gated task now unblocks.

## Resolution of the three §4 open questions
- **CHECK vs app clamp:** did both, as instructed — reject with `422` up front AND keep a DB CHECK backstop. The CHECK could not be expressed identically across drivers through one ALTER, so it lands per-dialect: SQLite carries it as an inline `CHECK` on the `ADD COLUMN` (SQLite allows a column CHECK to reference other columns; verified it fires), MySQL/MariaDB as a table-level `ADD CONSTRAINT chk_sync_interval_window` after the columns (MySQL column-level CHECK may reference only its own column). Both enforce `sync_interval_seconds <= offline_threshold_minutes * 30`. This mirrors the existing `long_stop >= stop` discipline the update path already relies on.
- **History mirror:** added the two columns to `tracking_config_history` and populate them in the update's history INSERT. Note the asymmetry: the shipped history table stores a JSON `payload` snapshot (not a column per field), so the JSON already auto-captured the new fields; I added the two explicit columns anyway per the doc/task so the two tunable knobs stay queryable without parsing the snapshot. The other 10 config fields remain JSON-only.
- **Seed version → 8:** done at all three insert sites (bootstrap seed, demo seed, `Ctx::config` lazy insert) plus a migration-time floor/bump for already-deployed rows.

## Verification
- `php vayu migrate --fresh --seed` on SQLite: clean, exit 0; seeded config row reads `{"version":8,"sync_interval_seconds":300,"distance_filter_metres":20}`.
- `php tests/config-v8.php`: all checks passed (GET /config → 200, version 8, both fields; matching ETag → 304 empty; out-of-range `syncIntervalSeconds` → 422 on the field; pair-rule → 422; valid write → 200 + persisted + history rows written; DB CHECK rejects a direct out-of-range UPDATE).
- `php tests/tracking-ingest.php`: still passes. `php -l` clean on all changed files.
- Rollback: `down()` is intentionally empty (same call migration 010 makes) — dropping a column rewrites the whole table on both drivers and SQLite cannot drop the inline CHECK; a roll-back leaves two unused columns and a harmless constraint. The project convention is additive, ledger-tracked, re-runnable migrations (`db:sync`), not reversible down paths.

## Needs doing next
- [ ] mobile-agent — parse `syncIntervalSeconds`/`distanceFilterMetres` from `GET /config`; drive the `_drain` timer from `syncIntervalSeconds` (replace the hard-coded `Duration(minutes: 5)` at `location_service.dart:512`); keep the v7 fallback. (gated task now unblocked)
- [ ] web-agent (ops) — deploy migration 031 to prod via `php vayu db:sync` (MariaDB 11.8.8); the ADD CONSTRAINT CHECK path is MariaDB-exercised only after deploy — no MySQL instance available here to run it, though the branch is dialect-guarded and syntactically verified.

## Blocked on
- none
