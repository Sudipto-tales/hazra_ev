import 'dart:math';
import 'dart:ui' show Offset, Size;

import '../../../data/models/models.dart';

/// Maps latitude/longitude onto canvas pixels for the route map.
///
/// Equirectangular with a cosine correction on longitude: at Bardhaman's latitude
/// a degree of longitude is only ~0.92 of a degree of latitude, so without the
/// correction every route would look stretched east-west.
///
/// The scale is **uniform on both axes** — deliberately. Fitting width and
/// height independently would squash the shape of the route, which is the one
/// thing this screen exists to show. The fitted box is letterboxed inside the
/// canvas instead.
///
/// Pure Dart on purpose: no widgets, so it can be unit-tested.
class RouteProjection {
  RouteProjection._({
    required this.centerLat,
    required this.centerLng,
    required this.scale,
    required this.kx,
    required this.size,
    required this.padding,
  });

  factory RouteProjection.fit({
    required LatLngBounds bounds,
    required Size size,
    double padding = 28,
  }) {
    final double centerLat = bounds.midLat;
    final double centerLng = bounds.midLng;

    // Longitude compression at this latitude.
    final double kx = cos(centerLat * pi / 180).abs().clamp(0.05, 1.0);

    // Degenerate input (a single fix, or none) still has to project.
    final double rawW = max(bounds.spanLng * kx, 1e-6);
    final double rawH = max(bounds.spanLat, 1e-6);

    final double availW = max(size.width - padding * 2, 1);
    final double availH = max(size.height - padding * 2, 1);

    final double scale = min(availW / rawW, availH / rawH);

    return RouteProjection._(
      centerLat: centerLat,
      centerLng: centerLng,
      scale: scale,
      kx: kx,
      size: size,
      padding: padding,
    );
  }

  final double centerLat;
  final double centerLng;

  /// Pixels per degree of latitude.
  final double scale;

  /// Longitude compression factor.
  final double kx;
  final Size size;
  final double padding;

  Offset project(double lat, double lng) => Offset(
        size.width / 2 + (lng - centerLng) * kx * scale,
        // North is up, so latitude is negated.
        size.height / 2 - (lat - centerLat) * scale,
      );

  Offset projectPoint(LocationLog p) => project(p.latitude, p.longitude);

  (double lat, double lng) unproject(Offset p) => (
        centerLat - (p.dy - size.height / 2) / scale,
        centerLng + (p.dx - size.width / 2) / (kx * scale),
      );

  /// Metres covered by one logical pixel — drives the scale bar and converts
  /// the stop radius (metres) into a drawable circle.
  double get metresPerPixel => 111320 / scale;

  double metresToPixels(double metres) => metres / metresPerPixel;
}
