/// Where the admin route map gets its basemap and its road snapping.
///
/// Two independent services, both swappable with `--dart-define` because
/// neither default is something you want pointed at a production fleet:
///
/// | Service | Default | Why it is only a default |
/// | --- | --- | --- |
/// | Tiles | CARTO Positron / Dark Matter | free + no key, but attribution is required |
/// | Snapping | `router.project-osrm.org` | demo server, no SLA, rate limited |
///
/// ```sh
/// flutter run --dart-define=MAP_TILE_URL=https://tiles.internal/{z}/{x}/{y}.png
/// flutter run --dart-define=OSRM_BASE_URL=https://osrm.internal
/// flutter run --dart-define=SNAP_TO_ROADS=false   # raw GPS trace only
/// flutter run --dart-define=OFFLINE_MAP=true      # force the painted fallback
/// ```
///
/// ## Why not `tile.openstreetmap.org` (changed 2026-11-04)
///
/// It was the default until the live team map landed, and it was the wrong
/// default twice over. OSM's tile usage policy explicitly rules out
/// "heavy use" and bulk/commercial consumption, and a fleet map that re-polls
/// every 15 s across a team is exactly that — using it would have been
/// borrowing someone's donated bandwidth for a commercial product. Second, the
/// standard OSM style is *designed to be read*: saturated road casings, green
/// parks, brown buildings. On a tracking map that colour competes with the
/// markers and the trails, which are the only thing anyone is looking at.
///
/// CARTO Positron (light) and Dark Matter (dark) fix both: free, **no API
/// key**, attribution only, and near-grey by design so a green "moving" disc
/// and a red long-stop ring are the loudest things on screen. The painted
/// canvas fallback (`RoutePainter`) is unchanged and still the answer when
/// tiles cannot be reached at all.
class MapConfig {
  const MapConfig._();

  /// `--dart-define=MAP_TILE_URL=…`. Empty means "use the CARTO defaults";
  /// set, it wins for both themes, because a customer who points this at their
  /// own tile host means it.
  static const String tileUrlOverride = String.fromEnvironment('MAP_TILE_URL');

  /// CARTO Positron. `{r}` is flutter_map's retina placeholder and resolves to
  /// `''` unless `retinaMode` is on. `{s}` subdomains are deliberately absent
  /// — `basemaps.cartocdn.com` serves every tile without them.
  static const String lightTileUrl =
      'https://basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

  /// CARTO Dark Matter — the same geometry on a dark plate, so switching the
  /// app theme does not switch map styles underneath the admin.
  static const String darkTileUrl =
      'https://basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';

  /// The template to hand [TileLayer], honouring the `--dart-define` override.
  static String tileUrlFor({required bool dark}) => tileUrlOverride.isNotEmpty
      ? tileUrlOverride
      : (dark ? darkTileUrl : lightTileUrl);

  /// Light-theme template. Kept as a constant for callers that have no theme
  /// to hand; prefer [tileUrlFor].
  static String get tileUrlTemplate => tileUrlFor(dark: false);

  /// Sent as `User-Agent: flutter_map (…)`. Tile hosts block anonymous bulk
  /// clients, so this has to identify the app, not the library.
  static const String userAgentPackageName = 'com.hazra.employeetracking';

  /// Both parties, because both are required: CARTO's basemaps are built from
  /// OSM data, so crediting one without the other is wrong.
  static const String attributionText = '© OpenStreetMap contributors © CARTO';
  static const String attributionUrl = 'https://openstreetmap.org/copyright';
  static const String tileProviderUrl = 'https://carto.com/attributions';

  /// 19 is the deepest zoom these raster styles publish. Asking for 20 returns
  /// 404s, so the map over-zooms the z19 tile instead.
  static const int maxNativeZoom = 19;
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
