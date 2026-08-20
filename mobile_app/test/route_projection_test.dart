import 'dart:ui' show Offset, Size;

import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_projection.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const LatLngBounds bardhaman = LatLngBounds(
    minLat: 23.2262,
    maxLat: 23.2565,
    minLng: 87.8482,
    maxLng: 87.8935,
  );
  const Size size = Size(360, 340);

  test('north is up', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size);
    final Offset north = p.project(bardhaman.maxLat, bardhaman.midLng);
    final Offset south = p.project(bardhaman.minLat, bardhaman.midLng);
    expect(north.dy, lessThan(south.dy));
  });

  test('east is right', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size);
    final Offset east = p.project(bardhaman.midLat, bardhaman.maxLng);
    final Offset west = p.project(bardhaman.midLat, bardhaman.minLng);
    expect(east.dx, greaterThan(west.dx));
  });

  test('the centre of the bounds lands at the centre of the canvas', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size);
    final Offset c = p.project(bardhaman.midLat, bardhaman.midLng);
    expect(c.dx, closeTo(size.width / 2, 0.001));
    expect(c.dy, closeTo(size.height / 2, 0.001));
  });

  test('unproject inverts project', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size);
    const double lat = 23.24;
    const double lng = 87.86;
    final (double lat2, double lng2) = p.unproject(p.project(lat, lng));
    expect(lat2, closeTo(lat, 1e-9));
    expect(lng2, closeTo(lng, 1e-9));
  });

  test('the route fits inside the canvas with its padding respected', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size, padding: 28);
    for (final (double lat, double lng) in <(double, double)>[
      (bardhaman.minLat, bardhaman.minLng),
      (bardhaman.minLat, bardhaman.maxLng),
      (bardhaman.maxLat, bardhaman.minLng),
      (bardhaman.maxLat, bardhaman.maxLng),
    ]) {
      final Offset o = p.project(lat, lng);
      expect(o.dx, greaterThanOrEqualTo(28 - 0.001));
      expect(o.dx, lessThanOrEqualTo(size.width - 28 + 0.001));
      expect(o.dy, greaterThanOrEqualTo(28 - 0.001));
      expect(o.dy, lessThanOrEqualTo(size.height - 28 + 0.001));
    }
  });

  test('a single point still projects instead of dividing by zero', () {
    final RouteProjection p = RouteProjection.fit(
      bounds: const LatLngBounds(
        minLat: 23.24,
        maxLat: 23.24,
        minLng: 87.86,
        maxLng: 87.86,
      ).padded(0.12),
      size: size,
    );
    final Offset o = p.project(23.24, 87.86);
    expect(o.dx.isFinite, isTrue);
    expect(o.dy.isFinite, isTrue);
  });

  test('scale is uniform, so the route shape is not squashed', () {
    final RouteProjection p =
        RouteProjection.fit(bounds: bardhaman, size: size);
    // One degree of latitude and one "corrected" degree of longitude must
    // travel the same number of pixels.
    final double dyPerDegLat =
        (p.project(24, 87.86).dy - p.project(23, 87.86).dy).abs();
    final double dxPerDegLng =
        (p.project(23.24, 88).dx - p.project(23.24, 87).dx).abs() / p.kx;
    expect(dxPerDegLng, closeTo(dyPerDegLat, 1e-6));
  });
}
