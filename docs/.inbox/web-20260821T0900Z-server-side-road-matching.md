---
agent: web-agent
date: 2026-08-21T09:00Z
status: done
breaking: no
---

# Road matching moved server-side, cached per employee-day

`GET /routes` now carries a `matched` object beside the raw `points` tuples:
the day's GPS trace resolved onto the road network, encoded as polyline6.

Until now the only road matching in the system was `OsrmService` in the Flutter
app (mobile inbox, 2026-08-19). That worked, but it put a matcher round trip on
every admin who opened a day, cached only in device memory, against a demo host
with no SLA. A closed day's route is immutable, so it should be matched once and
kept.

## Why matching and not directions

A directions call answers "what is the fastest way from A to B" and will invent
a road nobody rode. A matcher is given the whole trace and returns the road
chain that best *explains* it — an HMM whose emissions are fix-to-road distances
and whose transitions weigh road distance against the straight-line hop.

At the configured 15 s interval a two-wheeler at 40 km/h moves ~165 m between
fixes, which is exactly where the wrong parallel street becomes plausible. Every
signal that disambiguates is therefore sent, not defaulted: per-point accuracy
as the search radius, and **timestamps**, which the device-side client was not
sending at all.

## What changed — `website/` only

| File | Status | Role |
| --- | --- | --- |
| `api/support/Polyline.php` | new | Google polyline codec, precision 6 |
| `api/support/MapMatch.php` | new | matcher, cache, OSRM + Valhalla drivers |
| `database/migrations/008_route_geometry_tables.php` | new | `route_geometry` cache table |
| `api/controllers/RoutesController.php` | edited | `matched` in the payload, `snap=` param |
| `api/support/Engine.php` | edited | `buildDayRoute` invalidates the cache |
| `api/support/bootstrap.php`, `api/support/V1Controller.php` | edited | load the two new files |
| `core/Console/MatchCommand.php`, `vayu` | new / edited | `php vayu match` backfill |
| `.env.example` | edited | `MATCH_*` settings |

## API — additive, not breaking

```
GET /routes?…&snap=0|1
```

Each `RouteTrack` gains:

```json
"matched": {
  "engine": "osrm", "profile": "driving", "status": "ok",
  "confidence": 0.606, "distanceKm": 4.9153,
  "segments": [
    {"seq": 1, "polyline": "sw}hk@i|upfD…", "pointCount": 241,
     "distanceKm": 4.9153, "matched": true, "confidence": 0.606, "ratio": 1.047}
  ]
}
```

`matched` is **null** for today (the trace is still being written, so nothing
about it is cacheable), when `snap=0`, when `MATCH_ENABLED=false`, and whenever
the matcher was unavailable. All four mean the same thing to a client: draw the
raw trace. `points` is unchanged and still authoritative — both are sent so the
admin can see the raw fixes faint under the snapped road, which is how "rode
down a side street" is told apart from "the matcher guessed".

One `segments` entry per work session, keyed by `seq`. Sessions are never joined
across: a gap between two is a lunch break, and matching through it would draw a
road nobody rode. A session that failed to match keeps its raw points with
`matched: false`, and the day's `status` becomes `partial`.

`docs/02-API-PLAN.md` §3.7 needs the new field and the `snap=` parameter.

## Caching

`route_geometry`, one row per employee-day. A miss matches on read and stores
the result, so the first admin to open a day pays for it and nobody after them
does — measured 2045 ms cold, 0.1 ms warm.

Invalidation is `source_point_count` against `day_routes.point_count`, plus the
engine and profile names. A late offline batch moves the count; an ops change
moves the names; either makes the stored geometry an answer to a different
question. `Engine::buildDayRoute` drops the row rather than re-matching, because
ingest is a write path and the matcher is a network hop.

`subject=team` serves the **cache only** and never fills a miss inline: matching
costs seconds per day, so a twenty-person team map that built its own misses
would be a forty-second request. Single-employee reads do build on a miss, and
`php vayu match` is what keeps the team case warm.

A day where **nothing** matched is deliberately not cached — the usual cause is
a matcher that was down, and that should be retried, not remembered.

## Two engines

| Engine | Two-wheeler support |
| --- | --- |
| OSRM `/match` | needs a graph built from a forked `motorcycle.lua`; stock profiles are car/bike/foot and `bike` is a *bicycle* |
| Valhalla `/trace_attributes` | `motorcycle` and `motor_scooter` costings ship with it, chosen per request |

**Valhalla is the recommendation for a two-wheeler fleet** — no Lua fork to
maintain, and `motor_scooter` already models "uses the car network, tolerates
rough and narrow, is not a highway vehicle". OSRM stays the default because the
mobile client already speaks it and a demo host exists.

`/trace_attributes` rather than Valhalla's `/trace_route` because only the
former returns `confidence_score`.

### Trap worth knowing: OSRM ignores the profile in the URL

`osrm-extract` compiles the profile into the graph. The URL segment is not a
selector — the public demo answers `/match/v1/banana/…` exactly as it answers
`/driving/…` (verified). So `MATCH_PROFILE=motorcycle` against a car graph gives
car routing while every payload claims otherwise. **On OSRM, two-wheeler routing
is an infrastructure decision, never a config one.** On Valhalla the same
setting is a real per-request selector.

## Quality gate

OSRM returns `confidence: 0` on well-matched traces in several builds, including
the public demo, so confidence alone cannot gate anything. The gate that works
on both engines is the ratio of matched road length to thinned raw length,
accepted in `[0.4, 2.5]`:

- above the ceiling the matcher detoured to reach fixes that are not on the
  network — a field, a factory yard — and the road it drew is fiction;
- below the floor it discarded most of the trace and matched a fragment.

Both numbers ship in the payload; `ratio` is the one the server trusted.

## Verified

- Polyline codec round-trips in PHP, and the PHP encoder decodes correctly in
  Dart — negatives, zero, and truncated input included.
- A simulated ride along a real 5.03 km Bardhaman road (27 fixes at 15 s /
  ~165 m, ±10 m jitter) matched to 241 vertices, **111 m from ground truth over
  5 km**, ratio 1.047, confidence 0.61. Encoded 907 bytes against 5247 as JSON
  floats.
- Chunking: never exceeds the limit, no runt tail chunk, contiguous coverage,
  checked at n = 11…250.
- Cache miss/hit, `point_count` drift, profile switch and `invalidate()` all
  behave, on a throwaway DB copy.

## Known gap — the seeder produces traces nothing can match

`database/seed.php` generates location fixes by interpolating a straight line
between two points, so they run across fields with no road under them. OSRM
answers `NoMatch`, and forcing a wider radius produces a 3.8× detour that the
ratio gate correctly rejects. **Every seeded day will therefore show the raw
trace**, and the feature cannot be demonstrated in dev until the seeder walks a
real road geometry. Not fixed here: it is the seeder's design call (whether to
hit a router at seed time or ship pre-baked shapes), and it is a separate task.

## Ops

```sh
php vayu match                    # last 7 days, lazily-missed days included
php vayu match --days=30 --force  # after an engine or profile switch
php vayu match --date=2026-08-19 --employee=<id>
```

Settings are in `.env.example`. One that bites: `MATCH_MAX_COORDINATES` defaults
to 100, which is right for self-hosted OSRM, but **router.project-osrm.org caps
`/match` at 10** and returns `TooBig` above it. Set it to 10 when pointed there.

Neither default host is safe for a fleet. OSM's tile policy and the OSRM demo's
rate limits both forbid it; production needs a self-hosted matcher built from an
India or West Bengal extract.
