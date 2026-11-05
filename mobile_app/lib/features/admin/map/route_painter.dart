import 'dart:math';

import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import 'route_accuracy.dart';
import 'route_projection.dart';

/// Colours resolved from the theme *outside* `paint()`. A painter must never
/// read `Theme.of` itself, and passing them in is what makes dark mode work.
class RouteMapColors {
  const RouteMapColors({
    required this.plate,
    required this.grid,
    required this.gridMajor,
    required this.stroke,
    required this.glow,
    required this.rough,
    required this.queued,
    required this.stop,
    required this.longStop,
    required this.visit,
    required this.start,
    required this.end,
    required this.label,
    required this.onMarker,
    required this.selection,
  });

  factory RouteMapColors.of(BuildContext context) {
    final bool dark = context.isDark;
    return RouteMapColors(
      plate: dark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
      grid: context.lineColor.withValues(alpha: dark ? 0.5 : 0.7),
      gridMajor: context.lineColor,
      stroke: AppColors.primary,
      glow: AppColors.primary.withValues(alpha: dark ? 0.26 : 0.18),
      // The route's own hue, held back. A rough stretch is still the route —
      // giving it a colour of its own would read as a different kind of event,
      // and a red or amber would read as a fault. Dashed-and-faint says
      // "approximate" without saying "wrong".
      rough: AppColors.primary.withValues(alpha: dark ? 0.62 : 0.5),
      queued: AppColors.textTertiary,
      stop: AppColors.warning,
      longStop: AppColors.danger,
      visit: AppColors.primary,
      start: AppColors.success,
      end: AppColors.statusOffline,
      label: dark ? AppColors.textSecondaryDark : AppColors.textSecondary,
      onMarker: Colors.white,
      selection: AppColors.primaryDark,
    );
  }

  final Color plate;
  final Color grid;
  final Color gridMajor;
  final Color stroke;
  final Color glow;

  /// Low-emphasis treatment for stretches the GPS was not confident about.
  final Color rough;
  final Color queued;
  final Color stop;
  final Color longStop;
  final Color visit;
  final Color start;
  final Color end;
  final Color label;
  final Color onMarker;
  final Color selection;
}

/// A tappable thing on the canvas.
class RouteMarker {
  const RouteMarker({
    required this.id,
    required this.kind,
    required this.center,
    required this.radius,
    this.visit,
    this.stop,
  });

  final String id;
  final RouteMarkerKind kind;
  final Offset center;
  final double radius;
  final CompanyVisit? visit;
  final StopRecord? stop;
}

enum RouteMarkerKind { start, end, visit, stop }

/// Cuts a polyline into `dash`-long pieces separated by `gap`-long holes,
/// measured along the line rather than per vertex — so the dashes stay even
/// through a dense cluster of fixes and a long straight leg alike.
///
/// Top-level and pure so the dash machinery is testable without a `Canvas`.
/// Returns an empty list for a degenerate pattern rather than looping forever.
List<(Offset, Offset)> dashSegments(
  List<Offset> points, {
  required double dash,
  required double gap,
}) {
  final List<(Offset, Offset)> out = <(Offset, Offset)>[];
  if (points.length < 2 || !(dash > 0) || !(gap > 0)) return out;

  bool on = true;
  double used = 0;

  for (int i = 1; i < points.length; i++) {
    Offset a = points[i - 1];
    final Offset b = points[i];
    double remaining = (b - a).distance;
    if (!remaining.isFinite || remaining <= 0) continue;
    final Offset unit = (b - a) / remaining;

    while (remaining > 1e-9) {
      final double span = on ? dash : gap;
      final double take = min(span - used, remaining);
      final Offset next = a + unit * take;
      if (on) out.add((a, next));
      a = next;
      remaining -= take;
      used += take;
      if (used >= span - 1e-9) {
        on = !on;
        used = 0;
      }
    }
  }
  return out;
}

/// Draws the day's route: plate, grid, scale bar, polyline per session,
/// direction chevrons, stop circles, numbered visit pins and start/end caps.
///
/// Zero third-party packages — this is `Canvas` and `dart:math` only, so it
/// works offline and pulls in no map tiles.
class RoutePainter extends CustomPainter {
  RoutePainter({
    required this.track,
    required this.projection,
    required this.colors,
    required this.markers,
    required this.accuracy,
    required this.longStopThresholdMinutes,
    required this.stopRadiusMetres,
    required this.zoom,
    this.selectedId,
  });

  final RouteTrack track;
  final RouteProjection projection;
  final RouteMapColors colors;
  final List<RouteMarker> markers;

  /// Which fixes, stretches and stops read as rough. Derived once by the
  /// screen and handed down — the painter never re-derives a threshold.
  final RouteAccuracy accuracy;
  final int longStopThresholdMinutes;
  final double stopRadiusMetres;

  /// Current InteractiveViewer scale. Every stroke width and marker radius is
  /// divided by it so pins stay the same size on screen as the map zooms.
  final double zoom;

  final String? selectedId;

  double _s(double v) => v / zoom;

  @override
  void paint(Canvas canvas, Size size) {
    _paintPlate(canvas, size);
    _paintGrid(canvas, size);
    _paintRoute(canvas);
    _paintChevrons(canvas);
    _paintStops(canvas);
    _paintVisits(canvas);
    _paintCaps(canvas);
    _paintSelection(canvas);
    _paintScaleBar(canvas, size);
  }

  void _paintPlate(Canvas canvas, Size size) {
    canvas.drawRect(
      Offset.zero & size,
      Paint()..color = colors.plate,
    );
  }

  void _paintGrid(Canvas canvas, Size size) {
    const double step = 40;
    final Paint minor = Paint()
      ..color = colors.grid
      ..strokeWidth = _s(0.7);
    final Paint major = Paint()
      ..color = colors.gridMajor
      ..strokeWidth = _s(1);

    int i = 0;
    for (double x = 0; x <= size.width; x += step, i++) {
      canvas.drawLine(
        Offset(x, 0),
        Offset(x, size.height),
        i % 5 == 0 ? major : minor,
      );
    }
    i = 0;
    for (double y = 0; y <= size.height; y += step, i++) {
      canvas.drawLine(
        Offset(0, y),
        Offset(size.width, y),
        i % 5 == 0 ? major : minor,
      );
    }
  }

  void _paintRoute(Canvas canvas) {
    // One path per session: a break must not draw a phantom straight line
    // from where the employee stopped to where they resumed.
    for (final List<LocationLog> segment in track.segments) {
      if (segment.length < 2) continue;

      // Confident stretches only. Rough ones are stroked separately below, so
      // they skip the glow and the full-weight line.
      final Path path = Path();
      final List<List<Offset>> rough = <List<Offset>>[];

      for (final AccuracyRun run in accuracy.runs(segment)) {
        final List<Offset> o = run.points
            .map(projection.projectPoint)
            .toList(growable: false);
        if (o.length < 2) continue;
        if (run.rough) {
          rough.add(o);
          continue;
        }
        path.moveTo(o.first.dx, o.first.dy);
        for (int k = 1; k < o.length; k++) {
          path.lineTo(o[k].dx, o[k].dy);
        }
      }

      final Path queued = Path();
      bool queuedStarted = false;

      for (int k = 0; k < segment.length; k++) {
        final Offset o = projection.projectPoint(segment[k]);

        if (segment[k].syncState != SyncState.synced) {
          if (!queuedStarted) {
            queued.moveTo(o.dx, o.dy);
            queuedStarted = true;
          } else {
            queued.lineTo(o.dx, o.dy);
          }
        } else {
          queuedStarted = false;
        }
      }

      canvas.drawPath(
        path,
        Paint()
          ..color = colors.glow
          ..style = PaintingStyle.stroke
          ..strokeWidth = _s(9)
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
      canvas.drawPath(
        path,
        Paint()
          ..color = colors.stroke
          ..style = PaintingStyle.stroke
          ..strokeWidth = _s(3.2)
          ..strokeCap = StrokeCap.round
          ..strokeJoin = StrokeJoin.round,
      );
      // Dashed, thin, no glow: the trail is still there, it just stops
      // claiming to be exact. Drawn after the solid line so the dashes are not
      // buried under a neighbouring run's round stroke cap.
      final Paint roughPaint = Paint()
        ..color = colors.rough
        ..style = PaintingStyle.stroke
        ..strokeWidth = _s(2.6)
        ..strokeCap = StrokeCap.round;
      for (final List<Offset> run in rough) {
        _dashedPolyline(canvas, run, roughPaint, dash: _s(5), gap: _s(4));
      }

      canvas.drawPath(
        queued,
        Paint()
          ..color = colors.queued
          ..style = PaintingStyle.stroke
          ..strokeWidth = _s(2.2)
          ..strokeCap = StrokeCap.round,
      );
    }
  }

  /// Strokes [points] as a dash pattern.
  ///
  /// `Canvas` has no dash support and the app takes no new dependency for one,
  /// so the walk is done here: [dashSegments] cuts the polyline into pieces and
  /// each piece is one `drawLine`.
  void _dashedPolyline(
    Canvas canvas,
    List<Offset> points,
    Paint paint, {
    required double dash,
    required double gap,
  }) {
    for (final (Offset a, Offset b) in dashSegments(
      points,
      dash: dash,
      gap: gap,
    )) {
      canvas.drawLine(a, b, paint);
    }
  }

  /// Draws a ring out of short arcs — the dashed equivalent of `drawCircle`.
  void _dashedCircle(
    Canvas canvas,
    Offset center,
    double radius,
    Paint paint, {
    int dashes = 16,
  }) {
    if (!(radius > 0) || !radius.isFinite) return;
    final Rect rect = Rect.fromCircle(center: center, radius: radius);
    final double step = 2 * pi / dashes;
    for (int i = 0; i < dashes; i++) {
      canvas.drawArc(rect, i * step, step * 0.55, false, paint);
    }
  }

  void _paintChevrons(Canvas canvas) {
    final List<LocationLog> pts = track.points;
    if (pts.length < 12) return;

    final int step = max(6, pts.length ~/ 12);
    final Paint paint = Paint()
      ..color = colors.stroke
      ..style = PaintingStyle.stroke
      ..strokeWidth = _s(2)
      ..strokeCap = StrokeCap.round;

    for (int i = step; i < pts.length - 1; i += step) {
      if (pts[i].sessionId != pts[i - 1].sessionId) continue;
      // Direction derived from two weak fixes is a guess. A missing chevron is
      // honest; a confident arrow pointing the wrong way is not.
      if (accuracy.isRough(pts[i]) || accuracy.isRough(pts[i - 1])) continue;
      final Offset a = projection.projectPoint(pts[i - 1]);
      final Offset b = projection.projectPoint(pts[i]);
      final double angle = atan2(b.dy - a.dy, b.dx - a.dx);
      if (!angle.isFinite) continue;

      // Two arms swept back from the direction of travel, forming a ">".
      const double spread = 0.55;
      final double len = _s(6);
      for (final double side in <double>[-1, 1]) {
        final double a2 = angle + pi + side * spread;
        canvas.drawLine(
          b,
          b + Offset(cos(a2) * len, sin(a2) * len),
          paint,
        );
      }
    }
  }

  void _paintStops(Canvas canvas) {
    // Geofence radius is a real-world distance, so it scales with the map
    // rather than with the zoom-compensated marker sizes.
    final double geofence = projection.metresToPixels(stopRadiusMetres);

    for (final StopRecord stop in track.stops) {
      final Offset o = projection.project(stop.latitude, stop.longitude);
      final Duration dwell =
          (stop.departure ?? stop.arrival).difference(stop.arrival);
      final bool long = dwell.inMinutes >= longStopThresholdMinutes;
      final Color tone = long ? colors.longStop : colors.stop;
      final bool rough = accuracy.stop(stop).isRough;

      canvas.drawCircle(
        o,
        geofence,
        Paint()..color = tone.withValues(alpha: rough ? 0.06 : 0.10),
      );

      final Paint ring = Paint()
        ..color = tone.withValues(alpha: rough ? 0.35 : 0.45)
        ..style = PaintingStyle.stroke
        ..strokeWidth = _s(1.2);
      // A dashed geofence says the edge of this dwell is not a hard line —
      // which is exactly what a stop derived from jittering fixes means.
      if (rough) {
        _dashedCircle(canvas, o, geofence, ring, dashes: 24);
      } else {
        canvas.drawCircle(o, geofence, ring);
      }

      // Unlinked stops get their own hollow pin; linked ones are covered by
      // the numbered visit marker drawn on top.
      if (!stop.isLinked) {
        canvas.drawCircle(o, _s(7), Paint()..color = colors.plate);
        canvas.drawCircle(
          o,
          _s(7),
          Paint()
            ..color = tone
            ..style = PaintingStyle.stroke
            ..strokeWidth = _s(2.4),
        );
      }

      if (rough) _roughHalo(canvas, o, _s(stop.isLinked ? 15 : 11), tone);
    }
  }

  /// A dashed ring just outside a marker: the same "approximate" grammar the
  /// rough stretches use, at marker scale.
  void _roughHalo(Canvas canvas, Offset center, double radius, Color tone) {
    _dashedCircle(
      canvas,
      center,
      radius,
      Paint()
        ..color = tone.withValues(alpha: 0.75)
        ..style = PaintingStyle.stroke
        ..strokeWidth = _s(1.4),
      dashes: 12,
    );
  }

  void _paintVisits(Canvas canvas) {
    for (int i = 0; i < track.visits.length; i++) {
      final CompanyVisit v = track.visits[i];
      final Offset o = projection.project(v.latitude, v.longitude);
      final double r = _s(11);

      canvas.drawCircle(
        o,
        r + _s(2),
        Paint()..color = colors.onMarker.withValues(alpha: 0.9),
      );
      canvas.drawCircle(o, r, Paint()..color = colors.visit);
      _text(canvas, '${i + 1}', o, _s(11), colors.onMarker, bold: true);
      if (accuracy.visit(v).isRough) {
        _roughHalo(canvas, o, r + _s(5), colors.visit);
      }
    }
  }

  void _paintCaps(Canvas canvas) {
    if (track.points.isEmpty) return;

    final Offset start = projection.projectPoint(track.points.first);
    final Offset end = projection.projectPoint(track.points.last);

    _cap(canvas, start, colors.start, 'S');
    // Neutral, not red — red has to keep meaning "problem" on this canvas.
    _cap(canvas, end, colors.end, 'E');
  }

  void _cap(Canvas canvas, Offset o, Color color, String glyph) {
    final double r = _s(10);
    canvas.drawCircle(
      o,
      r + _s(2.5),
      Paint()..color = colors.onMarker.withValues(alpha: 0.9),
    );
    canvas.drawCircle(o, r, Paint()..color = color);
    _text(canvas, glyph, o, _s(11), colors.onMarker, bold: true);
  }

  void _paintSelection(Canvas canvas) {
    if (selectedId == null) return;
    for (final RouteMarker m in markers) {
      if (m.id != selectedId) continue;
      canvas.drawCircle(
        m.center,
        m.radius + _s(7),
        Paint()
          ..color = colors.selection
          ..style = PaintingStyle.stroke
          ..strokeWidth = _s(3),
      );
    }
  }

  void _paintScaleBar(Canvas canvas, Size size) {
    const List<double> steps = <double>[50, 100, 200, 500, 1000, 2000, 5000];
    // The bar is drawn in scene units but must read as ~90 *screen* pixels,
    // so the budget shrinks as the viewer zooms in.
    final double mpp = projection.metresPerPixel;
    final double budget = _s(90);
    double chosen = steps.first;
    for (final double s in steps) {
      if (s / mpp <= budget) chosen = s;
    }

    final double px = chosen / mpp;
    final double y = size.height - _s(16);
    final double x0 = _s(14);
    final Paint paint = Paint()
      ..color = colors.label
      ..strokeWidth = _s(1.6)
      ..strokeCap = StrokeCap.square;

    canvas.drawLine(Offset(x0, y), Offset(x0 + px, y), paint);
    canvas.drawLine(Offset(x0, y - _s(4)), Offset(x0, y + _s(4)), paint);
    canvas.drawLine(
      Offset(x0 + px, y - _s(4)),
      Offset(x0 + px, y + _s(4)),
      paint,
    );

    final String label =
        chosen >= 1000 ? '${(chosen / 1000).toStringAsFixed(0)} km' : '${chosen.toStringAsFixed(0)} m';
    _text(
      canvas,
      label,
      Offset(x0 + px / 2, y - _s(12)),
      _s(10),
      colors.label,
    );
  }

  void _text(
    Canvas canvas,
    String value,
    Offset center,
    double fontSize,
    Color color, {
    bool bold = false,
  }) {
    final TextPainter tp = TextPainter(
      text: TextSpan(
        text: value,
        style: TextStyle(
          color: color,
          fontSize: fontSize,
          fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    tp.paint(canvas, center - Offset(tp.width / 2, tp.height / 2));
  }

  @override
  bool shouldRepaint(RoutePainter old) =>
      old.track != track ||
      old.projection != projection ||
      old.selectedId != selectedId ||
      old.zoom != zoom ||
      old.colors != colors ||
      old.accuracy != accuracy ||
      old.longStopThresholdMinutes != longStopThresholdMinutes;
}
