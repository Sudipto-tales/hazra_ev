import 'dart:math' as math;

/// Pure-Dart great-circle geometry.
///
/// Deliberately dependency-free: the controller and the route services both
/// need "how far apart are these two points", and the controller must not
/// import `geolocator` (nothing above `LocationService` may touch the plugin).
/// Keeping one implementation here is also what stops the device-side distance
/// from drifting against the map-side one.
///
/// The radius is the IUGG mean Earth radius, 6 371 008.8 m — the same value
/// `OsrmService` used before it adopted this helper, so no existing number
/// shifts.
const double kEarthRadiusMetres = 6371008.8;

double _rad(double degrees) => degrees * math.pi / 180;

/// Great-circle distance in metres between two WGS-84 points.
///
/// Haversine, not Vincenty: at the distances one person covers in a day the
/// difference is well under the GPS error already baked into the inputs, and
/// this costs a handful of trig calls per fix.
double metresBetween(
  double lat1,
  double lng1,
  double lat2,
  double lng2,
) {
  final double dLat = _rad(lat2 - lat1);
  final double dLng = _rad(lng2 - lng1);
  final double h = math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(_rad(lat1)) *
          math.cos(_rad(lat2)) *
          math.sin(dLng / 2) *
          math.sin(dLng / 2);
  return 2 *
      kEarthRadiusMetres *
      math.asin(math.min(1, math.sqrt(h)));
}

/// [metresBetween] in kilometres, which is the unit every distance the app
/// displays is carried in.
double kmBetween(
  double lat1,
  double lng1,
  double lat2,
  double lng2,
) =>
    metresBetween(lat1, lng1, lat2, lng2) / 1000;
