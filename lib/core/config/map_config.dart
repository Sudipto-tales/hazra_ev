/// Where the admin route map gets its basemap and its road snapping.
///
/// Two independent services, both swappable with `--dart-define` because
/// neither default is something you want pointed at a production fleet:
///
/// | Service | Default | Why it is only a default |
/// | --- | --- | --- |
/// | Tiles | `tile.openstreetmap.org` | OSM's tile policy forbids heavy use |
/// | Snapping | `router.project-osrm.org` | demo server, no SLA, rate limited |
///
/// ```sh
/// flutter run --dart-define=MAP_TILE_URL=https://tiles.internal/{z}/{x}/{y}.png
/// flutter run --dart-define=OSRM_BASE_URL=https://osrm.internal
/// flutter run --dart-define=SNAP_TO_ROADS=false   # raw GPS trace only
/// flutter run --dart-define=OFFLINE_MAP=true      # force the painted fallback
/// ```
class MapConfig {
  const MapConfig._();

  /// Raster tile template. `{s}` subdomains are deliberately absent — OSM
  /// deprecated them, and flutter_map warns when it sees one on an OSM URL.
  static const String tileUrlTemplate = String.fromEnvironment(
    'MAP_TILE_URL',
    defaultValue: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
  );

  /// Sent as `User-Agent: flutter_map (…)`. OSM blocks anonymous bulk clients,
  /// so this has to identify the app, not the library.
  static const String userAgentPackageName = 'com.hazra.employeetracking';

  static const String attributionText = 'OpenStreetMap contributors';
  static const String attributionUrl = 'https://openstreetmap.org/copyright';

  /// 19 is the deepest zoom the standard OSM raster style publishes. Asking
  /// for 20 returns 404s, so the map over-zooms the z19 tile instead.
  static const int maxNativeZoom = 19;
  static const double maxZoom = 20;
  static const double minZoom = 3;

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
