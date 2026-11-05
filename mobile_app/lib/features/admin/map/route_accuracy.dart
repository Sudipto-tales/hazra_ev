import 'dart:math' as math;

import '../../../core/config/tracking_config.dart';
import '../../../core/utils/geo.dart';
import '../../../data/models/models.dart';

/// One contiguous stretch of a session's trace, all of it either rough or
/// clean. Consecutive runs **share their boundary fix** so the drawn line stays
/// continuous — a solid run ends exactly where the dashed one starts, with no
/// one-edge hole between them.
class AccuracyRun {
  const AccuracyRun({required this.points, required this.rough});

  final List<LocationLog> points;

  /// Draw this stretch as a data-quality hint rather than a confident trail.
  final bool rough;
}

/// How much of a stop's (or visit's) dwell was recorded on a weak fix.
///
/// [fixCount] of zero is *unknown*, not clean: a simplified payload can leave a
/// server-derived stop with no surviving fixes of its own, and an unknown stop
/// must not be decorated as rough.
class StopAccuracy {
  const StopAccuracy({
    required this.fixCount,
    required this.roughCount,
    required this.averageMetres,
  });

  static const StopAccuracy unknown =
      StopAccuracy(fixCount: 0, roughCount: 0, averageMetres: null);

  final int fixCount;
  final int roughCount;

  /// Mean horizontal accuracy of the fixes that reported one, or null when none
  /// did (a pre-`accuracy` payload, or a device-side log with no value).
  final double? averageMetres;

  bool get isKnown => fixCount > 0;

  /// "Predominantly" is **more than half** — a single weak fix inside a
  /// twenty-fix dwell is normal GPS behaviour, not a jitter-derived stop.
  bool get isRough => fixCount > 0 && roughCount * 2 > fixCount;

  double get roughShare => fixCount == 0 ? 0 : roughCount / fixCount;
}

/// Read-time classification of GPS quality on a fetched route.
///
/// The device sets [LocationLog.lowAccuracy] when it captures a fix, but a
/// route pulled back from the server does **not** carry that boolean: per API
/// plan §3.7/§3.11 the route payload carries the stored `accuracy_m` per point
/// (tuple slot 3) and low-accuracy is classified *at read time* by comparing it
/// against the live `config.minAccuracyMetres`. That is deliberate — an admin
/// retuning the threshold in Tracking Rules reclassifies every historical fix
/// without a rewrite — so this class is where the comparison happens, and
/// nothing in the UI may hard-code a metre value of its own.
///
/// Pure Dart, no widgets: both renderers and the list rows read the same
/// verdict, and it is unit-testable.
class RouteAccuracy {
  RouteAccuracy({
    required this.track,
    required this.thresholdMetres,
    this.stopRadiusMetres = 75,
  });

  /// The threshold always comes from the config the screen loaded, never from
  /// [TrackingConfig.defaults], so a server-tuned value wins.
  factory RouteAccuracy.of({
    required RouteTrack track,
    required TrackingConfig config,
  }) =>
      RouteAccuracy(
        track: track,
        thresholdMetres: config.minAccuracyMetres,
        stopRadiusMetres: config.stopRadiusMetres,
      );

  final RouteTrack track;
  final double thresholdMetres;

  /// Only used by the geometric fallback in [_fixesFor], and by [visit], which
  /// carries no radius of its own.
  final double stopRadiusMetres;

  /// Whether this fix reads as rough right now.
  ///
  /// `accuracy` wins when the point has one: that is the read-time rule, and it
  /// is the only branch a server-fetched route takes. [LocationLog.lowAccuracy]
  /// is the fallback for fixes that never carried a metre value — a log rebuilt
  /// from the device's offline queue, where the device already did the
  /// comparison. Reading the flag *first* would freeze an old threshold into
  /// the UI, which is exactly what read-time classification exists to avoid.
  bool isRough(LocationLog fix) {
    if (fix.accuracy > 0) return fix.accuracy > thresholdMetres;
    return fix.lowAccuracy;
  }

  List<LocationLog> get roughFixes =>
      track.points.where(isRough).toList(growable: false);

  int get roughCount => track.points.where(isRough).length;

  bool get hasRough => track.points.any(isRough);

  /// Fraction of the day's fixes that read as rough, `0`–`1`.
  double get roughShare =>
      track.points.isEmpty ? 0 : roughCount / track.points.length;

  /// Human label for the threshold being applied, e.g. `±50 m`.
  String get thresholdLabel => '±${thresholdMetres.round()} m';

  /// Splits one session's trace into alternating clean and rough runs.
  ///
  /// Classification is per **edge**, not per point: the line between a good fix
  /// and a weak one is itself uncertain, so it is drawn rough. An edge is rough
  /// when either of its endpoints is.
  List<AccuracyRun> runs(List<LocationLog> segment) {
    if (segment.isEmpty) return const <AccuracyRun>[];
    if (segment.length == 1) {
      return <AccuracyRun>[
        AccuracyRun(points: segment, rough: isRough(segment.first)),
      ];
    }

    final List<AccuracyRun> out = <AccuracyRun>[];
    int start = 0;
    bool current = _edgeRough(segment[0], segment[1]);

    for (int i = 1; i < segment.length - 1; i++) {
      final bool next = _edgeRough(segment[i], segment[i + 1]);
      if (next == current) continue;
      // `i + 1` as the exclusive end keeps the boundary fix in this run, and
      // `start = i` repeats it in the next one — that overlap is what stops a
      // visible gap appearing where the treatment changes.
      out.add(AccuracyRun(points: segment.sublist(start, i + 1), rough: current));
      start = i;
      current = next;
    }

    out.add(AccuracyRun(points: segment.sublist(start), rough: current));
    return out;
  }

  bool _edgeRough(LocationLog a, LocationLog b) => isRough(a) || isRough(b);

  /// Quality of the fixes behind a derived stop.
  StopAccuracy stop(StopRecord record) => _quality(
        sessionId: record.sessionId,
        arrival: record.arrival,
        departure: record.departure,
        latitude: record.latitude,
        longitude: record.longitude,
        radiusMetres: record.radiusMetres,
      );

  /// Quality of the fixes behind a promoted visit. Visits carry no radius of
  /// their own, so the geometric fallback uses the configured stop radius.
  StopAccuracy visit(CompanyVisit record) => _quality(
        sessionId: record.sessionId,
        arrival: record.arrival,
        departure: record.departure,
        latitude: record.latitude,
        longitude: record.longitude,
        radiusMetres: stopRadiusMetres,
      );

  StopAccuracy _quality({
    required String sessionId,
    required DateTime arrival,
    required DateTime? departure,
    required double latitude,
    required double longitude,
    required double radiusMetres,
  }) {
    final List<LocationLog> fixes = _fixesFor(
      sessionId: sessionId,
      arrival: arrival,
      departure: departure,
      latitude: latitude,
      longitude: longitude,
      radiusMetres: radiusMetres,
    );
    if (fixes.isEmpty) return StopAccuracy.unknown;

    int rough = 0;
    double sum = 0;
    int measured = 0;
    for (final LocationLog f in fixes) {
      if (isRough(f)) rough++;
      if (f.accuracy > 0) {
        sum += f.accuracy;
        measured++;
      }
    }

    return StopAccuracy(
      fixCount: fixes.length,
      roughCount: rough,
      averageMetres: measured == 0 ? null : sum / measured,
    );
  }

  /// The fixes that produced a dwell.
  ///
  /// Time window first — that is what the server derived the stop from. The
  /// geometric fallback exists because the route payload may be simplified
  /// (`Geo::simplify`), which can thin a dwell cluster down to points just
  /// outside the window while leaving plenty inside the radius.
  List<LocationLog> _fixesFor({
    required String sessionId,
    required DateTime arrival,
    required DateTime? departure,
    required double latitude,
    required double longitude,
    required double radiusMetres,
  }) {
    final DateTime end = departure ?? arrival;
    final List<LocationLog> inWindow = track.points
        .where((LocationLog p) =>
            p.sessionId == sessionId &&
            !p.recordedAt.isBefore(arrival) &&
            !p.recordedAt.isAfter(end))
        .toList(growable: false);
    if (inWindow.isNotEmpty) return inWindow;

    final double reach = math.max(radiusMetres, 25) * 1.5;
    return track.points
        .where((LocationLog p) =>
            p.sessionId == sessionId &&
            metresBetween(p.latitude, p.longitude, latitude, longitude) <=
                reach)
        .toList(growable: false);
  }

  /// Value equality on the inputs, so `CustomPainter.shouldRepaint` can compare
  /// two of these without walking every fix.
  @override
  bool operator ==(Object other) =>
      other is RouteAccuracy &&
      identical(other.track, track) &&
      other.thresholdMetres == thresholdMetres;

  @override
  int get hashCode => Object.hash(identityHashCode(track), thresholdMetres);
}
