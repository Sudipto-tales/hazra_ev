---
agent: mobile-agent
date: 2026-11-05T10:37Z
status: done
needs-api: false
---

## What changed

Board item "Surface `lowAccuracy` in route/stop UI so rough GPS stretches read as rough
(follow-up to P0.2)" — the flag existed end to end and nothing in `lib/features/` read it.
It does now, on both renderers and in the rows under the map.

**Where the flag is derived.** Client-side, at read time, in one place.
`lib/features/admin/map/route_accuracy.dart` (new) holds `RouteAccuracy`, built once per
build by `RouteMapPage` from the `TrackingConfig` that screen already loads. A fix is rough
when `accuracy > config.minAccuracyMetres`; `LocationLog.lowAccuracy` is only the fallback
for fixes that carry no metre value (a log rebuilt from the device's offline queue, or a
payload older than tuple slot 3). This follows `docs/02-API-PLAN.md:456-462` deliberately:
the route payload carries `accuracy_m` per point and **no** `lowAccuracy` boolean —
confirmed against `RoutesController::livePoints()` / `Engine.php` — and classifying at read
time is what makes an admin retuning the threshold in Tracking Rules reclassify history
without a rewrite. Reading a stored boolean first would freeze an old threshold into the UI.
**No server change wanted or needed.**

- `lib/features/admin/map/route_accuracy.dart` — new. `RouteAccuracy` (per-fix verdict,
  day counts, `runs()` splitting a session's trace into alternating clean/rough stretches)
  and `StopAccuracy` (per-dwell: fix count, rough count, mean accuracy, `isRough`).
  Edge-based: the line *into* a weak fix is itself uncertain, so it is drawn rough.
  Consecutive runs share their boundary fix, so the drawn line has no hole where the
  treatment changes. A stop is rough only when **more than half** its fixes are weak — one
  bad fix in a twenty-fix dwell is normal GPS, not a jitter stop. A stop with no surviving
  fixes is *unknown*, not rough, and gets no decoration.
- `lib/features/admin/map/route_painter.dart` — rough stretches draw dashed, thin and
  without the glow (new `RouteMapColors.rough`); rough stops get a dashed geofence ring and
  a dashed halo; rough visit pins get a dashed halo; direction chevrons are suppressed on
  rough edges (a confident arrow derived from two weak fixes is a lie). New public pure
  `dashSegments()` — `Canvas` has no dash support and no new dependency was taken.
- `lib/features/admin/map/route_tile_map.dart` — new public pure `rawTracePolylines()`
  splits the raw trace per run: rough runs are dotted, lighter, thinner and uncased; clean
  runs are unchanged. Rough fixes also get a translucent accuracy halo layer (disc sized to
  the metres the handset reported, subsampled to 60) under everything else — the honest
  "how wide is the doubt" read on a basemap, and the only signal that survives when the
  road-matched line is on top. Rough geofences get a soft outer wash instead of a crisp
  ring. A pill names the treatment and quotes the live threshold.
- `lib/features/admin/map/route_map_page.dart` — derives `RouteAccuracy` once and hands it
  to both renderers, the legend, the stats and the rows. Legend gains a dotted swatch plus a
  sentence quoting the configured threshold. New `Rough fixes` stat tile (shown at zero too
  — that is the reassurance that makes a non-zero count mean something). Detail card gains
  a `GPS accuracy · ±N m average` row, a `Rough GPS` badge and one explanatory sentence.
  **Unlinked stops now have a list row at all** (`_StopTile`): previously the only way to
  reach one was to tap its pin, which is exactly where a jitter-derived dwell could hide.
- `lib/features/home/widgets/visit_tile.dart` — additive optional `roughGps` (default
  false, every existing caller unchanged): renders one neutral line under the dwell
  numbers it qualifies.
- `lib/data/mock/admin_mock_data.dart` — one deterministic weak-signal window per mock day
  (back half of one approach leg plus that dwell). Only the reported `accuracy` changes; no
  extra draws from the `Random`, so every coordinate, timestamp and distance is byte-identical
  to before. This is what makes the treatment reviewable in a mock build with no handset.

**Treatment chosen, and why.** Rough stretches keep the route's own colour and lose
everything that made the line look authoritative — the casing, most of the weight, the solid
stroke. No new hue, no amber, no red. A weak fix is a property of the sky and the buildings,
not a fault of the employee, and colouring it as a warning puts a question mark over the
person. Dotted on tiles rather than dashed, so it can never be confused with the existing
dashed "raw trace under the road match" line, which is a different statement. Rows use
`StatusBadge(tone: neutral)` and one sentence in `bodySmall`. All colours come from theme
tokens / `RouteMapColors.of(context)`, which is dark-mode aware; a dark-mode widget test
covers it.

## API I need from web-agent

- none. `GET /api/admin/routes/{employee}/{date}` already serves `accuracy_m` in tuple slot 3
  and `GET /api/config` already serves `minAccuracyMetres`. That is the whole contract this
  needs, and a server-side `lowAccuracy` boolean would be a **regression** — it would bake
  one threshold into stored data and break the retune-reclassifies-history property the API
  plan explicitly chose.

## Needs doing next

- [ ] Device check on real weak-GPS data: dash density at pinch-zoom extremes, and whether
      60 accuracy halos is the right budget on a long weak stretch — mobile-agent, needs an
      Android build
- [ ] Employee-side route/history screens show no accuracy hint yet; only the admin route
      screen does — mobile-agent, if product wants it
- [ ] The live team map still renders every fix with equal confidence — mobile-agent,
      separate from this item

## Blocked on

- nothing

## Verification

`flutter analyze` → **14 issues, 0 in any file touched here**. All 14 are pre-existing
(dangling library doc comments in `data/models/`, `prefer_const` in `calendar_page` /
`home_page` / `admin_mock_data:64`, `use_build_context_synchronously` in
`admin_report_detail_page`, the `unawaited_return_in_try_block` warning in
`location_service:710`, `library_private_types_in_public_api` in `smoke_test`).

`flutter test` → **252 passed, 23 skipped, 0 failed**, including 32 new:

- `test/route_accuracy_test.dart` (27) — threshold comparison and that `>` not `>=` matches
  the server; retuning the threshold reclassifies the same fixes; a stale `lowAccuracy` flag
  loses to a measured value; zero-accuracy falls back to the flag; run splitting (clean =
  one solid run, rough middle = three runs, all-rough = one run, adjacent runs share a fix,
  degenerate input); stop predominance (majority rough, one bad fix is not, exact half is
  not, cross-session fixes excluded, no fixes = unknown, empty time window falls back to
  geometry); dash geometry; and the **rendering decision** — a rough run comes back dotted,
  uncased, thinner and fainter, a clean one solid and cased, and raising the threshold turns
  the same stretch solid again.
- `test/low_accuracy_route_ui_test.dart` (5) — the same day with only the accuracy numbers
  swapped: weak gets the badge, the sentence on both the stop row and the visit row, and the
  count; clean gets none of it while keeping the row itself. Plus the legend quoting the
  configured threshold, and a dark-mode pass.

**Device-only, not verified here** (no Android SDK on this machine): how the dashes and the
halos actually read over Esri/CARTO tiles at field zoom levels; the paint cost of the halo
layer on a weak-signal day with thousands of fixes; and the whole thing against a real
server payload rather than a constructed track.
