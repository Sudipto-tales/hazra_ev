---
agent: mobile-agent
date: 2026-08-21T09:10Z
status: done
breaking: no
---

# Admin map draws the server's road match; OSRM client becomes the fallback

Pairs with the web inbox note of the same date. `GET /routes` now sends
road-matched geometry as polyline6, so the map no longer has to match anything
itself for a closed day.

## What changed — `mobile_app/` only

| File | Status | Role |
| --- | --- | --- |
| `lib/core/utils/polyline.dart` | new | polyline6 decoder |
| `lib/data/models/admin.dart` | edited | `GeoPoint`, `MatchedSegment`, `MatchedRoute`, `RouteTrack.matched` |
| `lib/data/api/wire.dart` | edited | `Wire.matchedRoute`, wired into `routeTrack` |
| `lib/features/admin/map/route_tile_map.dart` | edited | prefers served geometry, partial-match notice |

`lib/services/osrm_service.dart` is **unchanged and still live**. It now covers
only what the server cannot: today's still-open route, and a server with
`MATCH_ENABLED=false`.

## Decoding boundary

`wire.dart` decodes the polyline; the models layer holds coordinates. Keeping
polyline6 out of `admin.dart` is what stops a wire format leaking into models
that no screen could read, and `GeoPoint` exists so the models layer still owns
no `flutter_map` or `latlong2` type. The map widget converts to `LatLng` once.

## Alignment

`_servedSegments()` lines the served segments up index-for-index with
`RouteTrack.segments` by session index, so a session keeps its palette colour in
both the faint raw line and the solid matched one. A session the server could
not match keeps its raw points in that slot rather than being dropped — a hole
would recolour every session after it.

## What the admin sees

Unchanged when everything matched: faint dashed raw trace under a solid cased
road line, one colour per session.

New: a **"Part of this day is raw GPS"** pill when the server reports
`status: partial`. Previously a session that failed to match was drawn as a
straight chord with nothing on screen saying so. The existing "Raw GPS trace"
pill still covers the nothing-matched case.

## Verified

- The polyline6 written by the PHP encoder decodes to identical coordinates in
  Dart, including negative and zero deltas, and a truncated string returns the
  vertices decoded so far instead of throwing.
- A server-shaped payload (one matched 217-vertex session, one fallback session)
  decodes to the right models; `pointsForSession` returns null for the fallback
  and for unknown indices; `null`, junk, and an empty `segments` list all decode
  to `null` rather than an empty route.
- `dart analyze lib` reports no new issues.

## Not done

Timestamps are still not sent by `OsrmService` on the fallback path — the server
sends them, the client does not. Worth closing, since it is the single biggest
lever on match quality at a 15 s interval, but it only affects today's route now.
