import 'dart:math' as math;

import 'package:employeetracking_mobile_app/features/admin/map/trail_arrows.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:latlong2/latlong.dart';

/// The arrow placement math, tested where it lives — pure, no canvas, no map.
/// The one property that matters is that spacing follows *distance*, not the
/// number of fixes: a stop that dumped forty points in one spot must not sprout
/// forty arrows.
void main() {
  group('arrowsAlongOffsets', () {
    test('first arrow is half a spacing in, then every spacing', () {
      // A straight 100px line along +x, spacing 10 → arrows at x = 5, 15, …, 95.
      final List<ArrowPlacement> arrows = arrowsAlongOffsets(
        <Offset>[const Offset(0, 0), const Offset(100, 0)],
        spacing: 10,
      );
      expect(arrows.length, 10);
      expect(arrows.first.position.dx, closeTo(5, 1e-9));
      expect(arrows[1].position.dx, closeTo(15, 1e-9));
      expect(arrows.last.position.dx, closeTo(95, 1e-9));
      for (final ArrowPlacement a in arrows) {
        expect(a.angleRad, closeTo(0, 1e-9)); // pointing along +x
      }
    });

    test('spacing is by distance, not by point count', () {
      // Same geometry, wildly different sampling: a 2-point line and a
      // 101-point line both 100px long must give the same arrows.
      final List<ArrowPlacement> sparse = arrowsAlongOffsets(
        <Offset>[const Offset(0, 0), const Offset(100, 0)],
        spacing: 10,
      );
      final List<Offset> dense = <Offset>[
        for (int i = 0; i <= 100; i++) Offset(i.toDouble(), 0),
      ];
      final List<ArrowPlacement> denseArrows =
          arrowsAlongOffsets(dense, spacing: 10);

      expect(denseArrows.length, sparse.length);
      for (int i = 0; i < sparse.length; i++) {
        expect(denseArrows[i].position.dx, closeTo(sparse[i].position.dx, 1e-6));
      }
    });

    test('angle follows the segment direction', () {
      final List<ArrowPlacement> arrows = arrowsAlongOffsets(
        <Offset>[const Offset(0, 0), const Offset(10, 10)],
        spacing: 3,
      );
      expect(arrows, isNotEmpty);
      expect(arrows.first.angleRad, closeTo(math.pi / 4, 1e-9));
    });

    test('maxArrows caps a long line', () {
      final List<ArrowPlacement> arrows = arrowsAlongOffsets(
        <Offset>[const Offset(0, 0), const Offset(100000, 0)],
        spacing: 1,
        maxArrows: 50,
      );
      expect(arrows.length, 50);
    });

    test('degenerate inputs yield nothing', () {
      expect(arrowsAlongOffsets(<Offset>[const Offset(0, 0)], spacing: 10),
          isEmpty);
      expect(
        arrowsAlongOffsets(
          <Offset>[const Offset(0, 0), const Offset(1, 0)],
          spacing: 0,
        ),
        isEmpty,
      );
    });
  });

  group('arrowsAlongLatLng', () {
    test('points roughly east bear ~90°', () {
      final List<TrailArrow> arrows = arrowsAlongLatLng(
        <LatLng>[const LatLng(22.5, 88.3), const LatLng(22.5, 88.4)],
        spacingMetres: 200,
      );
      expect(arrows, isNotEmpty);
      expect(arrows.first.bearingDeg, closeTo(90, 1.0));
    });

    test('count is driven by ground distance, not sampling density', () {
      const LatLng a = LatLng(22.5, 88.30);
      const LatLng b = LatLng(22.5, 88.40);
      final List<TrailArrow> sparse =
          arrowsAlongLatLng(<LatLng>[a, b], spacingMetres: 500);

      // Same line, resampled into 50 collinear points.
      final List<LatLng> dense = <LatLng>[
        for (int i = 0; i <= 50; i++)
          LatLng(22.5, 88.30 + (88.40 - 88.30) * i / 50),
      ];
      final List<TrailArrow> denseArrows =
          arrowsAlongLatLng(dense, spacingMetres: 500);

      // Linear interpolation on a short east-west run barely differs from the
      // great-circle sampling, so the counts match (±1 at the tail).
      expect((denseArrows.length - sparse.length).abs(), lessThanOrEqualTo(1));
      expect(sparse.length, greaterThan(1));
    });
  });
}
