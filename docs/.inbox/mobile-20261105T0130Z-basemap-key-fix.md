---
agent: mobile-agent
date: 2026-11-05T01:30Z
status: done
needs-api: false
---

## What changed

The admin maps rendered a grey plate stamped **"API KEY REQUIRED"** instead of a basemap.
Not a regression in our code: **CARTO stopped serving raster tiles to keyless clients on
2026-09-25**. The failure is silent — the tile still returns HTTP 200, so `RoutePainter`
never kicks in and the watermark just draws.

**Keyless default is now Esri** (`lib/core/config/map_config.dart`)
- `World_Light_Gray_Base` / `World_Dark_Gray_Base`. Same design intent as Positron:
  pale plate, thin roads, grey labels, markers loudest.
- Two gotchas, both documented in the file: Esri orders its path `{z}/{y}/{x}` (y before
  x) and serves JPEG with **no extension**; and its native tiles **stop at z16** — z17+
  returns a "Map data not yet available" placeholder at HTTP 200, not a 404.
- `maxNativeZoom` is therefore a getter, not a constant: 16 for Esri, 19 for CARTO and
  for a `MAP_TILE_URL` override. `maxZoom` stays 20, so the map over-zooms z16 rather
  than going blank.

**CARTO is now opt-in via `--dart-define=CARTO_KEY=…`**, which restores Positron / Dark
Matter at full z19. Free key from <https://carto.com/basemaps/apikey> — no account, no
card. Parameter is `key=`, appended after the `.png`. Commercial free tier is **1M tile
requests/month** (5M is non-commercial only; a paid fleet product is commercial by their
definition). A native app sends no `Referer`, so the key must be unrestricted or
restricted by Android package + signing cert.

**Precedence, stated once** in a private `_Basemap` enum + `_basemap` getter:
`MAP_TILE_URL` → CARTO (if key) → Esri. URL, native zoom and attribution all read it, so
they cannot disagree.

**Attribution follows the provider.** `attributionText` became a getter, so
`const Text(MapConfig.attributionText)` no longer compiled in `live_team_map.dart` and
`route_tile_map.dart` — `const` dropped at both call sites. Esri → `© Esri, © OpenStreetMap
contributors`; CARTO → unchanged.

## Verified

`flutter analyze`: 15 issues, byte-identical to the pre-change tree (all pre-existing, none
in the touched files). `flutter test`: 160 passed, 23 skipped, 0 failed. Substituted tile
URLs fetched live for both themes at z15/z16 — real imagery, not placeholders.

## Not changed / worth a decision

`route_tile_map.dart` fits the camera at `maxZoom: 17` and `live_team_map.dart` zooms +2.5
capped at 20. Under Esri both land above native z16, so the default view is a slightly soft
over-zoomed tile. Dropping the fit cap to 16 would be crisper — left alone as a UX call.

OSRM/snapping config, `RoutePainter` fallback, marker and clustering logic all untouched.
`website/` untouched. Note `website/config/dealer-map.php` still uses OSM tiles directly
and is unaffected by the CARTO change.
