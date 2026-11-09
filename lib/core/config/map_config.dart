/// Where the admin route map gets its basemap and its road snapping.
///
/// Two independent services, both swappable with `--dart-define` because
/// neither default is something you want pointed at a production fleet:
///
/// | Service | Default | Why it is only a default |
/// | --- | --- | --- |
/// | Tiles | Esri World Light/Dark Gray Base | free + no key, but native tiles stop at z16 |
/// | Snapping | `router.project-osrm.org` | demo server, no SLA, rate limited |
///
/// ```sh
/// flutter run --dart-define=MAP_TILE_URL=https://tiles.internal/{z}/{x}/{y}.png
/// flutter run --dart-define=CARTO_KEY=…             # Voyager / Dark Matter at z19
/// flutter run --dart-define=OSRM_BASE_URL=https://osrm.internal
/// flutter run --dart-define=SNAP_TO_ROADS=false   # raw GPS trace only
/// flutter run --dart-define=OFFLINE_MAP=true      # force the painted fallback
/// ```
///
/// ## Why not `tile.openstreetmap.org`
///
/// It was the default until the live team map landed, and it was the wrong
/// default twice over. OSM's tile usage policy explicitly rules out
/// "heavy use" and bulk/commercial consumption, and a fleet map that re-polls
/// every 15 s across a team is exactly that — using it would have been
/// borrowing someone's donated bandwidth for a commercial product. Second, the
/// standard OSM style is *designed to be read*: saturated road casings, green
/// parks, brown buildings. On a tracking map that colour competes with the
/// markers and the trails, which are the only thing anyone is looking at. That
/// reasoning has not changed, so raw OSM is still not the default.
///
/// ## Why not CARTO any more, unless you have a key (changed 2026-11-05)
///
/// CARTO Positron and Dark Matter were the default because they were free,
/// keyless and near-grey by design, so a green "moving" disc and a red
/// long-stop ring stayed the loudest things on screen. On 2026-09-25 CARTO
/// stopped serving their raster basemaps to keyless clients: the tile still
/// returns HTTP 200, but what comes back is a grey plate stamped
/// "API KEY REQUIRED", which is how the watermark got onto the admin maps.
///
/// So the keyless default is now Esri's World Light Gray Base (and World Dark
/// Gray Base in the dark theme), which renders the same intent — pale plate,
/// thin white roads, grey labels, markers loudest. Two things about it differ
/// from CARTO and are worth knowing before touching this file. Esri orders its
/// path `{z}/{y}/{x}`, y before x, and serves JPEG with no extension at all.
/// And its native tiles stop at z16: ask for z17 and you get a
/// "Map data not yet available" placeholder at HTTP 200, so it fails silently
/// rather than 404ing. [maxNativeZoom] is therefore 16 for Esri, and the map
/// over-zooms the z16 tile past that instead of going blank. Over-zooming a
/// z16 label-light tile is also exactly why streets read as a vague grey
/// blur once an admin pinches in past a neighbourhood — the keyless plate has
/// no z17–z19 detail to show.
///
/// A CARTO key is the fix for that blur, and it changes the *light* default as
/// well as lifting the zoom ceiling. The keyed light basemap is now CARTO
/// **Voyager** (`rastertiles/voyager`), not Positron: Voyager keeps the pale,
/// low-contrast plate Positron had but carries proper street labels and road
/// casings down to z19, so a zoomed-in map names the road an employee is
/// parked on instead of showing an unlabelled grey ribbon. Dark theme stays on
/// Dark Matter (`dark_all`) — there is no "voyager dark", and Dark Matter is
/// already the quiet dark plate the markers need. Both go to z19, which is the
/// whole point of holding a key.
///
/// Keys are free from <https://carto.com/basemaps/apikey> — no account, no
/// card, it arrives by email — and the commercial free tier is a million tile
/// requests a month, which a fleet of this size does not come close to. Pass
/// it as `--dart-define=CARTO_KEY=…`; the query parameter is `key=`, appended
/// after the file extension. A native app sends no `Referer`, so the key has
/// to be left unrestricted or restricted by Android package name + signing
/// cert.
class MapConfig {
  const MapConfig._();

  /// `--dart-define=MAP_TILE_URL=…`. Empty means "use the built-in defaults";
  /// set, it wins for both themes and over [cartoKey], because a customer who
  /// points this at their own tile host means it.
  static const String tileUrlOverride = String.fromEnvironment('MAP_TILE_URL');

  /// `--dart-define=CARTO_KEY=…`. Empty means "no key", which is the only
  /// reason the Esri basemap is the default rather than CARTO.
  static const String cartoKey = String.fromEnvironment('CARTO_KEY');

  /// Which of the three basemaps this build resolved to. Everything
  /// provider-dependent below — URL, native zoom, attribution — reads this, so
  /// the precedence is stated once.
  static _Basemap get _basemap => tileUrlOverride.isNotEmpty
      ? _Basemap.custom
      : (cartoKey.isNotEmpty ? _Basemap.carto : _Basemap.esri);

  /// Esri World Light Gray Base. Note `{z}/{y}/{x}` — Esri puts y before x —
  /// and no file extension: the service answers with JPEG regardless.
  static const String esriLightTileUrl =
      'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/'
      'World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}';

  /// Esri World Dark Gray Base — the same geometry on a dark plate, so
  /// switching the app theme does not switch map styles underneath the admin.
  static const String esriDarkTileUrl =
      'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/'
      'World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}';

  /// CARTO Voyager, the keyed light default since 2026-11-09 — the street-
  /// labelled plate that makes zoomed-in roads legible where Positron (and the
  /// Esri fallback) leave them an unlabelled blur. Key-only, like every CARTO
  /// raster since 2026-09-25. `{r}` is flutter_map's retina placeholder and
  /// resolves to `''` unless `retinaMode` is on, so the query string goes after
  /// the `.png`, not between the tile path and it. `{s}` subdomains are
  /// deliberately absent — `basemaps.cartocdn.com` serves every tile without
  /// them. Endpoint path verified against CARTO's raster CDN (a keyless HEAD
  /// still answers 200 image/png, so the path is right; the key is what stops
  /// the "API KEY REQUIRED" watermark coming back in the pixels).
  static const String cartoLightTileUrl =
      'https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?key=$cartoKey';

  /// CARTO Dark Matter, the dark counterpart. There is no Voyager dark variant,
  /// and Dark Matter is already the quiet near-black plate the markers want, so
  /// the dark theme stays on it.
  static const String cartoDarkTileUrl =
      'https://basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=$cartoKey';

  /// The template to hand [TileLayer], honouring the `--dart-define` override
  /// first and a CARTO key second.
  static String tileUrlFor({required bool dark}) {
    switch (_basemap) {
      case _Basemap.custom:
        return tileUrlOverride;
      case _Basemap.carto:
        return dark ? cartoDarkTileUrl : cartoLightTileUrl;
      case _Basemap.esri:
        return dark ? esriDarkTileUrl : esriLightTileUrl;
    }
  }

  /// Light-theme template. Kept for callers that have no theme to hand;
  /// prefer [tileUrlFor].
  static String get tileUrlTemplate => tileUrlFor(dark: false);

  /// Sent as `User-Agent: flutter_map (…)`. Tile hosts block anonymous bulk
  /// clients, so this has to identify the app, not the library.
  static const String userAgentPackageName = 'com.hazra.employeetracking';

  /// Both parties in both cases, because both are required: these basemaps are
  /// built from OSM data, so crediting the server without the data — or the
  /// other way round — is wrong.
  static const String esriAttribution = '© Esri, © OpenStreetMap contributors';
  static const String cartoAttribution = '© OpenStreetMap contributors © CARTO';

  /// Whatever the active provider requires. An operator-supplied tile host is
  /// almost always OSM-derived and we cannot know who serves it, so the custom
  /// case credits the data and leaves the host to them.
  static String get attributionText {
    switch (_basemap) {
      case _Basemap.custom:
        return '© OpenStreetMap contributors';
      case _Basemap.carto:
        return cartoAttribution;
      case _Basemap.esri:
        return esriAttribution;
    }
  }

  static const String attributionUrl = 'https://openstreetmap.org/copyright';

  /// Where the active provider's own attribution terms live.
  static String get tileProviderUrl => _basemap == _Basemap.carto
      ? 'https://carto.com/attributions'
      : 'https://www.esri.com/en-us/legal/terms/full-master-agreement';

  /// The deepest zoom the active style actually publishes. Esri's Light/Dark
  /// Gray Base stops at 16 and answers z17 with a placeholder image at HTTP
  /// 200; CARTO's raster styles go to 19. A custom host gets 19 because the
  /// operator who set it knows their own coverage. [maxZoom] stays above both
  /// so the map over-zooms the deepest real tile instead of going blank.
  static int get maxNativeZoom => _basemap == _Basemap.esri ? 16 : 19;

  static const double maxZoom = 20;
  static const double minZoom = 3;

  // ---------------------------------------------------- live team map only

  /// At or below this zoom the live map collapses overlapping markers into a
  /// count badge. A depot with nine phones parked in it is nine discs on top
  /// of each other — a number is both smaller and more truthful.
  static const double liveClusterMaxZoom = 12;

  /// How much of the recent trail the live map draws behind each marker. Half
  /// an hour is about one leg between customers; a whole day is what the
  /// historical route screen is for.
  static const Duration liveTrailWindow = Duration(minutes: 30);

  /// A new fix slides the marker over this long instead of teleporting it. A
  /// jump reads as a rendering glitch; a slide reads as movement, which is
  /// what it is.
  static const Duration liveMarkerMotion = Duration(milliseconds: 600);

  /// Base URL of an OSRM instance, no trailing slash.
  static const String osrmBaseUrl = String.fromEnvironment(
    'OSRM_BASE_URL',
    defaultValue: 'https://router.project-osrm.org',
  );

  /// `driving`, `walking` or `cycling`, depending on what the instance was
  /// built with. The demo server only carries `driving`.
  static const String osrmProfile = String.fromEnvironment(
    'OSRM_PROFILE',
    defaultValue: 'driving',
  );

  /// Turn snapping off and the map draws the raw GPS trace, straight lines and
  /// all. Useful when the OSRM host is down or the fleet is not on roads.
  static const bool snapToRoads =
      bool.fromEnvironment('SNAP_TO_ROADS', defaultValue: true);

  /// Skips tiles entirely and renders the hand-painted plate. Set it for demos
  /// on a machine with no network, or when a customer bans third-party hosts.
  static const bool forceOffline =
      bool.fromEnvironment('OFFLINE_MAP', defaultValue: false);

  /// OSRM rejects a `/match` request with more coordinates than this, so a
  /// long day is snapped in overlapping chunks.
  static const int osrmMaxCoordinates = 100;

  /// Fixes closer together than this add nothing to the match and eat into the
  /// coordinate budget, so they are dropped before the request is built.
  static const double minPointSpacingMetres = 8;

  /// Per-point search radius handed to OSRM, clamped from the fix's own
  /// accuracy. Too small and a fix off the road never matches; too large and
  /// the matcher happily jumps to a parallel street.
  static const double minMatchRadiusMetres = 6;
  static const double maxMatchRadiusMetres = 45;

  static const Duration osrmTimeout = Duration(seconds: 12);

  static String get osrmMatchBase =>
      '${osrmBaseUrl.replaceAll(RegExp(r'/+$'), '')}/match/v1/$osrmProfile';
}

/// Which basemap a build resolved to. Private because the choice is made here
/// from `--dart-define`s and nothing outside should be branching on it.
enum _Basemap { custom, carto, esri }
