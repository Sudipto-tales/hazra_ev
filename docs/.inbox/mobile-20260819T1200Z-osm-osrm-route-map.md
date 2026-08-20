---
agent: mobile-agent
date: 2026-08-19T12:00Z
status: done
breaking: no
---

# Admin route map now renders on OpenStreetMap with OSRM road snapping

`RouteMapPage` used to draw the day's GPS trace on a hand-painted grid plate:
a custom equirectangular projection (`route_projection.dart`) feeding a
`CustomPainter` (`route_painter.dart`). Correct, but it reads as a chart, not
a map — no streets, no place names, and straight lines between fixes wherever
the handset skipped a beat.

It now renders on a real basemap, with the trace matched to the road network.

## What changed — `mobile_app/` only

| File | Status | Role |
| --- | --- | --- |
| `pubspec.yaml` | edited | `flutter_map: ^8.3.1`, `latlong2: ^0.10.1` |
| `lib/core/config/map_config.dart` | new | tile URL, OSRM host, snapping switches |
| `lib/services/osrm_service.dart` | new | OSRM `/match` client, thinning, chunking, cache |
| `lib/features/admin/map/route_tile_map.dart` | new | the `flutter_map` renderer |
| `lib/features/admin/map/route_map_page.dart` | edited | picks a renderer, both directions |

`route_painter.dart` and `route_projection.dart` are **unchanged and still
live** — see "Fallback" below.

### Basemap

`flutter_map` renders raster tiles itself rather than embedding a platform map
view, so there is no Google/Mapbox key, no API billing, and no per-platform
native setup. Android already declares `INTERNET`; nothing else was touched.

Layers, bottom to top: OSM tiles → stop/visit geofence circles (real metres,
from `TrackingConfig.stopRadiusMetres`) → unsynced-fix dots → raw trace →
snapped route → markers → scale bar → attribution.

Pan/zoom is deliberately **two-finger only**. The map sits inside a scrolling
`ListView`, so a one-finger drag has to keep belonging to the list.

### Road snapping — OSRM `/match`, not `/route`

`/route` answers "fastest way from A to B" and will invent a motorway nobody
drove. `/match` is handed the whole trace and returns the road path that best
explains it. That is the question this screen asks.

Per session segment, never across them — joining two sessions would ask OSRM
to route across a lunch break and draw a road that was never driven.

The service handles the demo server's real constraints:

- **100-coordinate cap** → chunked, overlapping by one coordinate so
  consecutive chunks meet on the same road.
- **Stationary noise** → fixes closer than 8 m are dropped before the request;
  `tidy=true` collapses the rest.
- **Trace gaps** (tunnel, dead battery, offline queue) → `gaps=ignore`, or
  OSRM refuses the whole request.
- **Per-point search radius** clamped from each fix's own `accuracy` to
  6–45 m. Too small and an off-road fix never matches; too large and the
  matcher jumps to a parallel street.

Every failure path returns `null`, never throws. An unreachable or
rate-limited OSRM host degrades to the raw polyline — with the demo server
that is the common case, not the exotic one. Results are cached per trace
signature so scrubbing the date back and forth does not re-hit it.

Both polylines are drawn when a match succeeds: the raw trace stays as a faint
dashed reference under the solid snapped line. That is how an admin tells
"drove down a side street" apart from "the matcher guessed".

### Fallback

The painted canvas is now the **offline renderer**, not dead code. Tiles need
the network and a reachable OSM; a blank grey box is not an answer.

- Starts on tiles. Five tile errors with nothing yet rendered → falls back.
- Both views carry a button to switch to the other, so the choice never sticks.
- `--dart-define=OFFLINE_MAP=true` starts on the canvas.

## Configuration

Neither default should point at a production fleet.

```sh
--dart-define=MAP_TILE_URL=https://tiles.internal/{z}/{x}/{y}.png
--dart-define=OSRM_BASE_URL=https://osrm.internal
--dart-define=OSRM_PROFILE=driving        # demo server carries driving only
--dart-define=SNAP_TO_ROADS=false         # raw trace, no OSRM calls
--dart-define=OFFLINE_MAP=true            # painted canvas, no tiles
```

## Demo data relocated to Bardhaman

The mock dataset was anchored on Dhaka. On a real basemap that put every demo
route in the wrong country, so `mobile_app/lib/data/mock/` and the simulated
`location_service.dart` fix now sit in Bardhaman (Purba Bardhaman, West
Bengal).

| Was | Now | Coordinates |
| --- | --- | --- |
| Gulshan Head Office | Khosbagan Head Office | 23.2432, 87.8567 |
| Banani Outlet | Curzon Gate Outlet | 23.2370, 87.8635 |
| Motijheel Branch | B.C. Road Branch | 23.2350, 87.8640 |
| Dhanmondi Store | Golapbag Store | 23.2262, 87.8482 |
| Uttara Sector 7 | Nababhat Showroom | 23.2565, 87.8772 |
| Mirpur Depot | Ullas Depot | 23.2273, 87.8935 |

Employee home anchors moved with them — Sripally, Nababhat, Kanchan Nagar,
Golapbag, Bara Bazar, Bajepratappur, Khosbagan. Branch ids changed to match
(`br_abc_banani` → `br_abc_curzon`, and so on); every reference was updated.
The runtime anchor for employees created in-session was a Dhaka-sized box
(~19 x 11 km) and is now town-sized (~6.5 x 7 km), so generated routes stay on
streets the basemap has.

These are landmark centroids, accurate to a few hundred metres — recognisable
on a map, not surveyed.

**Names and phone numbers were not touched.** The demo staff are still
`Rahul Ahmed`, `Nusrat Jahan` etc. on `+880` numbers, which now reads oddly
against an Indian town. Deliberate: the ask was coordinates, and renaming
people is a bigger call than relocating pins.

## Full-screen map

A map inside a scrolling `ListView` can only ever be a preview — one-finger
drag has to stay with the list. The inline map is therefore two-finger, and an
expand button opens the same widget full screen where drag, fling and all the
normal map gestures work. Selection carries back to the inline detail card.

## Marker and route styling

Restyled to the conventional OSM/flutter_map look rather than flat dots on a
line:

- **Teardrop pins**, drawn with a `CustomPainter` so the tip lands on an exact
  pixel and the white casing survives over busy tiles. `Marker.alignment` is
  `Alignment.topCenter`, which puts the tip *on* the coordinate — without it
  every pin sits half a pin north of where the employee stood.
- **One colour per work session** on the route line (blue → amber → red →
  green → cyan, wrapping after five). A day split by a lunch break now reads as
  two legs instead of one line that appears to teleport.
- **White casing** on every polyline. A coloured line over map tiles is
  competing with the tiles' own coloured lines; the casing is what keeps it
  readable.
- Pin colour still carries meaning — green start, blue visit, amber stop, red
  long stop, grey end — so the palette was not spent on decoration.

`latlong2` exports its own `Path`, so the canvas one is imported as `ui.Path`.

## Open items for the board

1. **`website/database/seed.php` is still on Dhaka coordinates.** Meghna
   Motors / Padma Auto House / Jamuna EV Traders / Turag Wheels sit at
   23.7–23.8 N, 90.3–90.4 E, and the org timezone is `Asia/Dhaka`. The app
   defaults to `USE_MOCKS=false`, so **the running app still shows Dhaka**
   unless launched with `--dart-define=USE_MOCKS=true`. Relocating the seeder
   is a `web-agent` change — flagged here, not attempted.
2. **The public defaults are dev-only.** `tile.openstreetmap.org` forbids heavy
   use under its tile policy; `router.project-osrm.org` has no SLA and is rate
   limited. Both need self-hosted or commercial replacements before this ships
   to a real fleet. `MAP_TILE_URL` / `OSRM_BASE_URL` are the seams.
3. **Snapping is client-side, so it is per-device and per-view.** Every admin
   opening the same day re-snaps the same trace. Moving it behind
   `website/api` (`GET /routes`, snapped geometry stored on `day_routes`)
   would snap once, serve both consumers, and let the website map reuse it.
   That is a `web-agent` change and a `docs/02-API-PLAN.md` contract addition
   — flagged here, not attempted.
4. **The website admin map gets nothing from this.** Same reason as (2).
5. **Tiles are not cached across launches.** `flutter_map`'s default provider
   uses the HTTP cache only. Offline-first would want a persistent tile store.

## Verification

- `flutter analyze` — 0 errors. The 16 remaining issues are pre-existing infos
  in untouched files.
- `flutter test` — `route_projection_test`, `smoke_test`,
  `admin_repository_test` and `report_sale_test` all pass after the
  relocation. `api_contract_test.dart` fails on `Connection refused` to
  `localhost:8000`; the PHP dev server is not running, and that is unrelated
  to this change.
- Not yet run against a live OSRM host or on a device — the snapping path is
  written to degrade rather than throw, but it has not been exercised in the
  wild.
