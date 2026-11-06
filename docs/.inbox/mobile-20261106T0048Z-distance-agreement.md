# Device distance now follows the server's measurement rule

**Agent:** mobile-agent · **Date:** 2026-11-06

## What changed (mobile_app/ only)

The device was measuring daily distance with a *different* rule than the server
and then **ratcheting the result upward**, so Home showed more kilometres than
the dashboard, the statistics tab and the closeout sheet — every day, widening.

Three fixes in `lib/state/tracking_controller.dart` + `lib/core/config/tracking_config.dart`
+ `lib/data/api/wire.dart`:

1. **Measurement gate mirrors the server's universal engine (036).** A leg now
   counts only when it clears `max(minStepMetres, accuracyStepFactor * fix.accuracy)`,
   not the OS *capture* filter `distanceFilterMetres` (a smaller number doing a
   different job). `minStepMetres` and `accuracyStepFactor` are now parsed from
   `GET /config` (already served by `Present::config`), added to `TrackingConfig`
   with the documented defaults 15 / 1, and round-tripped in `configToJson`.
2. **The anchor holds on a rejected hop** instead of advancing on every fix —
   exactly what `Engine::chainDistanceV2` does, and for the same reason: advancing
   it let sub-threshold noise accumulate while holding it keeps slow travel
   measurable.
3. **Dropped the upward-only ratchet** (`max(displayed, measured)`). The displayed
   number is now the measured one (server baseline + un-synced legs), so when the
   server reconciles a day downward (a leg counted as jitter, a gap chord moved to
   `incompleteKm`) the screen follows. Paired fix: `_reconcileDistance` no longer
   clears legs when nothing has been uploaded (`syncedAt == null`) — its own comment
   already said "keep them all"; clearing them was what forced the ratchet.

## Contract

No wire-shape change. `minStepMetres`/`accuracyStepFactor` were already in the
`GET /config` / `PUT /config` payload; the client simply consumes them now. Same
29-path contract.

## Verification

`flutter test` → 268 pass / 23 skip / 0 fail. `flutter analyze` → 15 pre-existing
infos, none in touched files. Updated `test/live_values_test.dart` (jitter test now
models oscillation, not a 22 m ramp the server would itself count as travel; added a
test asserting the display follows the server downward) and `test/tracking_config_test.dart`
(parses the two new fields + v7 fallback).

## Note for docs-agent

`03-DATABASE-SCHEMA.md` §4/§5 and `02-API-PLAN.md` §3.11/§3.12 describe
`distanceFilterMetres` as device-only and the step gate as server-only. The device
now applies the server's step rule too; worth a line so the two engines are
documented as one.
