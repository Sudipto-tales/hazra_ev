# Mobile performance action plan (2026-11-01)

Three issues reported by the product owner: app "heaviness", GPS fixes lost on weak connections, and device heating/battery drain during tracking. Below is the diagnosis and a prioritized plan. Effort key: S <½day, M 1–2 days, L 3+ days incl. device testing.

## P0 — Critical: stop losing GPS data
- **0.1 Persist the offline queue to disk.** `GeoLocationService` holds fixes in an in-memory `Queue` (`mobile_app/lib/services/location_service.dart:252`); comment at `:215-216` admits a process-kill loses them. On weak signal the OS often kills the backgrounded app → unsynced route points are gone. Persist the queue and reload on launch so draining resumes. Effort M, risk Low–Med. Owner: mobile_app/ (mobile-agent).
- **0.2 Stop silently discarding low-accuracy fixes.** Fixes >50 m accuracy are dropped outright (`location_service.dart:484-489`). Keep them flagged instead so a weak-GPS stretch still leaves a trail. Effort S, risk Low.
- Verify: session + airplane-mode/2G throttle + force-kill + relaunch → all fixes eventually land; no gap on admin route map.

## P1 — High: battery & heat during active sessions
- **1.1 Kill the 1-second rebuild loop.** `tracking_controller.dart:733,746` calls notifyListeners() every second for the whole session. Scope the elapsed-time UI to its own ticker; notify only on real state change. Effort S, risk Low.
- **1.2 Make tracking thresholds server-driven.** `tracking_config.dart` is already built for `GET /api/config`; wiring it lets interval/accuracy/batch be tuned without an app release. Effort M, risk Low. mobile_app/ consumes; website/api/ exposes (web-agent handoff).
- **1.3 Reconsider wake-lock + accuracy.** `enableWakeLock: true` (`location_service.dart:578`) and `LocationAccuracy.high` (`:564`) are the biggest power levers; test balanced/medium and longer interval against route quality. Effort M, risk Med (too low → missed fixes).
- Verify: battery stats over a full 8h session before/after; confirm route completeness unchanged.

## P2 — Medium: weak-network upload robustness
- **2.1 Adaptive batch size + longer upload timeout.** 50-fix batch (`tracking_config.dart:14`) over a 20 s timeout (`api_client.dart:63`) can't finish on 2G. Larger budget and/or shrink batch on repeated failure. Effort S–M, risk Low.
- **2.2 Add a connectivity signal.** App only learns it's offline after a failed upload (`location_service.dart:300`). A connectivity listener avoids burning failed round-trips and flushes the instant signal returns. Effort M, risk Low (new dep).

## P3 — Lower: install size / heaviness
- **3.1 Enable R8 + resource shrinking** in the release block (`android/app/build.gradle.kts:56`): isMinifyEnabled/isShrinkResources true, with keep-rules for geolocator & flutter_map. Effort S, risk Med (test release build).
- **3.2 Ship an app bundle / ABI-split** instead of a universal APK (~½–⅓ size). Effort S, risk Low.
- **3.3 Disk-cache map tiles** (`route_tile_map.dart:301`), admin-only, lowest priority. Effort S, risk Low.

## Suggested sequence
1) 0.1 + 0.2 first (real harm). 2) 1.1 as a same-day battery win. 3) 1.2 then 1.3 together (config-driven first so tuning needs no release). 4) 2.x then 3.x as polish.
