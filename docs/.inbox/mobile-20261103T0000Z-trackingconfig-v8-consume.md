---
agent: mobile-agent
date: 2026-11-03T00:00Z
status: done
needs-api: false
---

## What changed
- lib/core/config/tracking_config.dart — added `syncIntervalSeconds` (int, default 300) field, constructor param, and `copyWith` arg; `distanceFilterMetres` (default 20) was already present.
- lib/data/api/wire.dart — added default-aware `integerOr(raw, fallback)` helper; `Wire.config` now parses BOTH `syncIntervalSeconds` and `distanceFilterMetres` from `GET /config`, each falling back to the local `TrackingConfig.defaults` value when absent. `distanceFilterMetres` was previously NOT parsed at all (local default only); it is now server-driven. Added both fields to `configToJson` so the `PUT /config` round-trip carries them.
- lib/services/location_service.dart — `_drain` timer interval changed from hard-coded `Duration(minutes: 5)` to `Duration(seconds: config.syncIntervalSeconds)`. Early-flush (`_queue.length >= config.syncBatchSize`), `stopTracking()` final flush, and the ~5-min `_heartbeatInterval` are UNCHANGED.
- test/tracking_config_test.dart — added a `Wire.config version 8` group: one test asserting a v8 payload yields the server values (120 / 50), one asserting a v7 payload (both fields omitted) falls back to defaults (300 / 20).

## Parse change / v7 fallback
The existing `integer()` helper falls back to `0`, which for `syncIntervalSeconds` would mean a 0-second drain timer — unsafe. New fields therefore use `integerOr(j[...], TrackingConfig.defaults....)`, so a v7 server that omits the fields resolves them to 300 / 20 rather than zero or null. No crash, no null-out. This matches the "config omits a field → local default stands" discipline already relied on for `autoCloseAfterMinutes`.

## distanceFilter confirmation
`_settings()` already applies `config.distanceFilterMetres` on Android / Apple / generic `LocationSettings`. The value now flows from the parsed server response (it was previously only the local default because `Wire.config` never read the key).

## Guardrails honoured
No change to `syncBatchSize`, `offlineThresholdMinutes`, `locationIntervalSeconds`, or the heartbeat. No client-side clamping of `syncIntervalSeconds` — the server enforces the ceiling (`≤ offlineThresholdMinutes × 60 / 2`), and early-flush + heartbeat protect correctness if it were ever large.

## Test results
- `flutter test test/tracking_config_test.dart test/tracking_sync_test.dart` → all pass (15 tests across both files; config file has 6 including the 2 new v7/v8 cases).
- `flutter analyze` on the 3 changed lib files → 1 pre-existing warning only: `unawaited_return_in_try_block` at location_service.dart:421 (`requestPermission`, untouched by this task). No new issues.

## API I need from web-agent
- `GET /config` — `TrackingConfig` with `version: 8` and camelCase scalars `syncIntervalSeconds` (int, default 300) and `distanceFilterMetres` (int, default 20). Coding to §3.12 as locked. App already tolerates a v7 server that omits both.

## Needs doing next
- [ ] (optional, mobile-agent) surface `syncIntervalSeconds` / `distanceFilterMetres` in the admin tracking-rules UI if product wants them editable in-app — contract allows PUT of all fields.

## Blocked on
- Nothing.
</content>
</invoke>
