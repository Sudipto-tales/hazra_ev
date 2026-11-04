---
agent: mobile-agent
date: 2026-11-04T17:50Z
status: done
needs-api: true
---

## What changed

Client half of live tracking, built against `02` §3.11 / §3.12 (v9) / §3.14 as locked
2026-11-04. **Every new field is optional on the wire**, so all of this runs against
today's v7 production — just without activity glyphs, bearing notches or live mode.

**New live team map (new screen; `route_map_page.dart` untouched and still historical)**
- `lib/features/admin/map/live_team_map_page.dart` — the screen. Three loops: roster
  re-read at 15 s (`AutoRefresh`), `POST /tracking/watch` at `ttlSeconds / 2`, trail
  re-read at 60 s. Phone = full-bleed map + `DraggableScrollableSheet` roster at three
  snap points (0.14 / 0.3 / 0.85); ≥900 dp = map + 340 dp side panel. **One** `LiveTeamMap`
  and one roster widget feed both layouts — the layout changes, not the screen.
- `lib/features/admin/map/live_marker.dart` — the derivation, pure and unit-tested.
  `LiveMarker.of()`, `LiveMarkerTone` (colour = state, five values), `MarkerMotion`
  (600 ms lerp + short-way bearing), `MarkerCluster` (grid buckets below zoom 12).
- `lib/features/admin/map/live_pin.dart` — `LivePin` on top of the shared `MapPin`, plus
  `BearingNotch`, the dwell ring, the per-marker age chip and `ClusterBadge`.
- `lib/features/admin/map/live_team_map.dart` — tiles, trails, markers, camera, motion.
  Per-second values ride the `AutoRefresh` clock through a `ValueListenableBuilder`
  (P1.1 discipline — no `setState` per second, no `notifyListeners` anywhere near it).
- `lib/features/admin/map/map_chrome.dart` — **extracted** `_MapPin`/`_PinPainter`/
  `_MapButton`/`_Pill` out of `route_tile_map.dart` as `MapPin` / `MapIconButton` /
  `MapPill`. Both maps now draw the same teardrop from one file; the historical screen
  renders identically.
- Entry points: a "Live team map" action on the Dashboard and the Team tab AppBars.
  Pushed route, not a sixth tab — a tab in the `IndexedStack` would hold watches alive
  behind whatever else the admin was looking at.

**Marker rules (the part worth reviewing)**
- Colour = state (`success` moving · `warning` stopped · `danger` long-stop/GPS-off ·
  `textTertiary` last-known · `statusOffline` stale). Glyph = `activity`, 1:1, eight
  values. No client-side recombination of `status` + `movement` + `locationHealth`.
- **Bearing never rotates on a null `headingDeg`** — `BearingNotch` is not built at all,
  rather than built at 0. Only the notch rotates; the glyph never does. A stale fix also
  drops its heading (a notch on a 40-minute-old fix claims they are still going that way).
- Stopped markers carry a ring that fills `openStopDuration / longStopThresholdMinutes`.
- **Honesty rule:** fix older than `offlineThresholdMinutes` → grey, 0.6 opacity. Every
  marker shows `Fmt.freshness` under the pin and again in the roster row, ticking per
  second. The status strip says plainly when the server has no watch endpoint.

**Basemap — user-visible change**
- `lib/core/config/map_config.dart` — default is now **CARTO Positron (light) / Dark
  Matter (dark)**, theme-matched, instead of `tile.openstreetmap.org`. OSM's tile policy
  forbids exactly this (bulk/commercial fleet polling), and the grey style stops street
  colour competing with markers. No API key. `MAP_TILE_URL` `--dart-define` override and
  the painted-canvas fallback both unchanged. Attribution now credits **both**:
  `© OpenStreetMap contributors © CARTO`. **This also changes the historical route map's
  basemap** — same `MapConfig`, deliberately.

**Live watch + drain cadence**
- `AdminRepository.watchLive()` + `HttpAdminRepository` (`POST /tracking/watch`, one call
  for the whole map, ids capped at 200) + `Wire.liveWatch` + `LiveWatch` /
  `WatchedEmployee` / `RejectedWatch` models. `MockAdminRepository` grants watches locally.
- `LocationSyncResult.liveUntil` read from the response **`meta`**, not `data`.
- `LocationUploader` now returns `UploadOutcome { settled, liveUntil }` —
  `location_service.dart` still talks no HTTP; the controller does the mapping.
- `GeoLocationService`: `_applyLiveUntil` / `_reconcileDrain` / `_armDrain`. While
  `now < liveUntil` the `_drain` timer runs at `liveSyncIntervalSeconds`, else
  `syncIntervalSeconds`; the drain tick reconciles first, so a lapsed deadline restores
  the slow cadence within one live interval with no further server input. Absent and null
  are the same branch. `liveSyncIntervalSeconds` is clamped to `5..syncIntervalSeconds`.
- **GPS capture cadence untouched.** `locationIntervalSeconds`, `distanceFilterMetres`,
  the adaptive moving/stationary machine and the ~5 min heartbeat are all unchanged;
  `live_drain_test.dart` asserts the position stream is **not** resubscribed when the
  cadence switches. Early flush on `syncBatchSize` still wins when it binds first.

**v9 config + wire additions (all with v7 fallbacks)**
- `TrackingConfig.movementExitSpeedKmh` (3) and `.liveSyncIntervalSeconds` (15), both via
  `integerOr`/new `dblOr` so a missing key resolves to the local default, never `0`.
- `LocationLog.headingDeg` (`double?`); `Wire.fix` reads `headingDeg`, `Wire.routeTrack`
  reads tuple **slot 6**. A 6-slot tuple stays a complete fix. Garbage (`400`, `NaN`,
  negative, non-numeric) resolves to `null`, never clamped — unknown is not north.
- `TeamMember.activity` (`ActivityState?`), parsed tolerantly: `location_unavailable` and
  `locationUnavailable` both resolve, because §1 says camelCase and §3.14 is written in
  snake_case and nobody has served the field yet.
- `tracking_rules_page.dart` shows the four v9 values read-only (upload cadence, upload
  cadence while watched, movement dead band edges).

## API I need from web-agent

Everything in `02` §3.11 / §3.12 / §3.14 as already specced — the app is coded against it
and degrades without it. Precisely:

- `POST /tracking/watch` — `{"employeeIds":[...], "ttlSeconds"?}` → `{ttlSeconds,
  watchedUntil, watching:[{employeeId, watchingUntil, extended}], rejected:[{employeeId,
  reason}]}`. **A 404 is handled**: the map stops asking after one attempt and says
  "Server has no live watch" rather than retrying.
- `POST /tracking/locations` → `meta.liveUntil` (absolute ISO-8601 UTC, or absent/null).
- `GET /config` → `version: 9` with `movementExitSpeedKmh` + `liveSyncIntervalSeconds`
  beside the two v8 fields.
- `activity` on `Present::teamMember` (that is the payload the Flutter admin polls —
  `GET /employees?include=liveStatus,summary`). Either spelling parses.
- `headingDeg` on `Present::fix` and tuple slot 6 on `GET /routes`.

Nothing about the shapes needs to change; I did not design anything new.

**Cross-check against web-agent's in-flight work** (read-only, uncommitted working tree
at the time of writing — deployment state still unknown): `Present::teamMember` emits
`activity`, `Present::fix` emits `headingDeg`, `Engine` puts heading in route tuple
**slot 6 after `seq`** exactly as §3.7 says, `Geo::simplify` keeps whole tuples so slot 6
survives simplification, `meta.liveUntil` is on `POST /tracking/locations`,
`POST:api/v1/tracking/watch` is routed, and `Ctx.php` seeds `version` 9. One thing worth
recording: `activity` is emitted **snake_case** (`not_started`, `location_unavailable`),
not the camelCase §1 promises for enums. The app parses both, so this is not a defect on
either side — but `02` §1 and §3.14 disagree with each other and one of them should be
amended so the next consumer does not have to guess.

## Needs doing next

- [ ] `web-agent` — ship v9 (one migration, one deploy). Until then the map works but
      cannot be put into live mode, has no glyphs and no bearing.
- [ ] `mobile-agent` — **unverified on hardware** (no device, no Android SDK here): CARTO
      tiles have never been fetched, so the basemap is unproven end to end; marker legibility,
      the dwell ring, the notch, cluster density and the sheet's snap feel are all
      eyeballing jobs; `heading` has never been seen from a real server.
- [ ] `mobile-agent` — `movementExitSpeedKmh` is parsed and surfaced but **deliberately
      not consumed**. The device's `_evaluateMovement` has its own 90 s debounce and also
      drives GPS cadence; retuning it was outside this task and would have risked the
      battery work. The field is the server's dead band, which is where `activity` comes
      from, so nothing is missing from the map.
- [ ] `mobile-agent` — the day view (`GET /days/{subject}`) `activity` is **not** consumed:
      `EmployeeDay.activity` already means the timeline (`List<ActivityEvent>`), so adding
      the enum there needs a rename first. The map only needs `teamMember`.
- [ ] `mobile-agent` — the watch covers every active roster member with a fix, not the
      literal map viewport. Correct for a team of ten; revisit past ~200.

## Blocked on

Nothing. The screen ships and works against v7. Live mode, activity glyphs and bearing
all light up on their own the moment web-agent deploys v9 — no app release needed for any
of them.

## Verification

- `flutter analyze` — **15 issues, identical to the pre-task baseline**. No new ones.
- `flutter test` — **160 passed / 23 skipped** (baseline 120 / 23; 40 new, no skips added).
  New: `test/live_marker_test.dart` (27) — v7 fallback for all four config knobs + absent
  `activity` + 6-slot tuples + both enum spellings; null-heading no-rotation (incl. two
  widget tests on `BearingNotch`); the stale rule; colour/glyph separation; clustering.
  `test/live_drain_test.dart` (8) — the cadence switch end to end through a real
  `flushQueue`, including absent-vs-null, a lapsed deadline, the clamp, and the
  no-resubscribe guarantee. `test/live_team_map_page_test.dart` (5) — both layouts build,
  one watch call for the whole team, re-post at `ttl/2`, and the 404 path asked exactly once.
- Three existing tests were updated for the new uploader return type
  (`tracking_sync_test.dart`, `location_queue_persistence_test.dart`); behaviour unchanged.
