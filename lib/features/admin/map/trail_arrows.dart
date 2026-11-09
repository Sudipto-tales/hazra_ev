import 'dart:math' as math;
import 'dart:ui' show Offset;

import 'package:latlong2/latlong.dart';

/// Direction arrows along a trail — the thing that turns a there-and-back on
/// the same road into two visibly separate passes instead of one ambiguous
/// line.
///
/// The placement rule is **distance, not point count**. A route is sampled
/// unevenly: a phone parked at a customer emits dozens of fixes in one spot and
/// a handful across a fast open road. Spacing arrows every N points would pile
/// them up at every stop and leave the open stretches bare — exactly backwards.
/// Spacing by distance along the line puts one arrow every N units regardless
/// of how thick the fixes are, so the arrows read as a steady flow.
///
/// Two spaces, one walk: the canvas painter works in projected pixels
/// ([Offset]) and the tile/live maps work in geographic coordinates ([LatLng]).
/// Both share [_walk]; only the "how far apart are two points" and "what
/// direction" primitives differ.

/// One arrow on the canvas: where to draw it and which way it points.
class ArrowPlacement {
  const ArrowPlacement(this.position, this.angleRad);

  /// Projected position of the arrow tip-centre.
  final Offset position;

  /// Direction of travel in radians, `atan2(dy, dx)` — 0 points along +x.
  final double angleRad;
}

/// One arrow on a basemap: a coordinate and a compass bearing.
class TrailArrow {
  const TrailArrow(this.position, this.bearingDeg);

  final LatLng position;

  /// Degrees clockwise from north, the convention a north-pointing chevron is
  /// rotated by.
  final double bearingDeg;
}

/// Arrows along a projected polyline, one every [spacing] pixels.
///
/// The first arrow sits half a spacing in, so it never lands on top of the
/// start cap, and [maxArrows] caps a pathologically long line so a whole day
/// at tile scale cannot spawn thousands of them.
List<ArrowPlacement> arrowsAlongOffsets(
  List<Offset> points, {
  required double spacing,
  int maxArrows = 400,
}) {
  final List<ArrowPlacement> out = <ArrowPlacement>[];
  _walk(
    count: points.length,
    spacing: spacing,
    maxArrows: maxArrows,
    segmentLength: (int i) => (points[i + 1] - points[i]).distance,
    place: (int i, double frac) {
      final Offset a = points[i];
      final Offset b = points[i + 1];
      final Offset pos = a + (b - a) * frac;
      out.add(ArrowPlacement(pos, math.atan2(b.dy - a.dy, b.dx - a.dx)));
    },
  );
  return out;
}

const Distance _distance = Distance();

/// Arrows along a geographic polyline, one every [spacingMetres] of ground
/// distance. Same contract as [arrowsAlongOffsets]; the caller derives
/// [spacingMetres] from the current zoom so the on-screen spacing stays roughly
/// constant and the arrows thin out — rather than smear — when zoomed far out.
List<TrailArrow> arrowsAlongLatLng(
  List<LatLng> points, {
  required double spacingMetres,
  int maxArrows = 200,
}) {
  final List<TrailArrow> out = <TrailArrow>[];
  _walk(
    count: points.length,
    spacing: spacingMetres,
    maxArrows: maxArrows,
    segmentLength: (int i) => _distance.as(
      LengthUnit.Meter,
      points[i],
      points[i + 1],
    ),
    place: (int i, double frac) {
      final LatLng a = points[i];
      final LatLng b = points[i + 1];
      final LatLng pos = LatLng(
        a.latitude + (b.latitude - a.latitude) * frac,
        a.longitude + (b.longitude - a.longitude) * frac,
      );
      out.add(TrailArrow(pos, _distance.bearing(a, b)));
    },
  );
  return out;
}

/// The shared placement walk. Accumulates length across segments and fires
/// [place] every [spacing] units with the fraction along the current segment,
/// so the two coordinate spaces only have to supply a length metric and an
/// interpolator. Stops once [maxArrows] have been placed.
void _walk({
  required int count,
  required double spacing,
  required int maxArrows,
  required double Function(int segment) segmentLength,
  required void Function(int segment, double frac) place,
}) {
  if (count < 2 || !(spacing > 0) || !spacing.isFinite) return;

  int placed = 0;
  double next = spacing / 2; // distance from the current segment start
  for (int i = 0; i < count - 1; i++) {
    final double len = segmentLength(i);
    if (!len.isFinite || len <= 0) continue;
    while (next <= len) {
      place(i, next / len);
      if (maxArrows > 0 && ++placed >= maxArrows) return;
      next += spacing;
    }
    next -= len;
  }
}
