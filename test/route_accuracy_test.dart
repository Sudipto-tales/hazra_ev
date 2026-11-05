
import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_accuracy.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_painter.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_tile_map.dart';
import 'package:flutter/material.dart' show Colors, Offset;
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_test/flutter_test.dart';

/// `lowAccuracy` is derived, not delivered: the route payload carries a metre
/// value per fix (tuple slot 3) and the app compares it against the live
/// `minAccuracyMetres` at read time — API plan §3.7 and the accuracy note
/// under §3.11. These tests pin that derivation and the rendering decision it
/// drives, because neither is visible to a device-less build any other way.

final DateTime _day = DateTime(2026, 11, 5);

LocationLog _fix({
  required int minute,
  required double accuracy,
  String session = 's1',
  double lat = 23.24,
  double lng = 87.86,
  bool lowAccuracy = false,
}) =>
    LocationLog(
      id: 'f$minute',
      sessionId: session,
      latitude: lat,
      longitude: lng,
      accuracy: accuracy,
      speedKmh: 12,
      recordedAt: _day.add(Duration(hours: 9, minutes: minute)),
      syncState: SyncState.synced,
      lowAccuracy: lowAccuracy,
    );

RouteTrack _track(
  List<LocationLog> points, {
  List<StopRecord> stops = const <StopRecord>[],
  List<CompanyVisit> visits = const <CompanyVisit>[],
}) =>
    RouteTrack(
      employeeId: 'emp1',
      date: _day,
      points: points,
      sessions: <WorkSession>[
        WorkSession(
          id: 's1',
          index: 1,
          startTime: _day.add(const Duration(hours: 9)),
          endTime: _day.add(const Duration(hours: 18)),
          distanceKm: 10,
          locationPoints: points.length,
          startLatitude: 23.24,
          startLongitude: 87.86,
        ),
      ],
      stops: stops,
      visits: visits,
      totalDistanceKm: 10,
    );

RouteAccuracy _accuracy(RouteTrack track, {double threshold = 50}) =>
    RouteAccuracy(track: track, thresholdMetres: threshold);

void main() {
  group('read-time classification', () {
    test('a fix worse than the live threshold is rough, one at it is not', () {
      final RouteAccuracy a = _accuracy(_track(const <LocationLog>[]));
      expect(a.isRough(_fix(minute: 0, accuracy: 51)), isTrue);
      expect(a.isRough(_fix(minute: 0, accuracy: 50)), isFalse,
          reason: 'the threshold is the limit of acceptable, not the first '
              'rejected value — the server uses `>` too');
      expect(a.isRough(_fix(minute: 0, accuracy: 9)), isFalse);
    });

    test('raising the threshold reclassifies the same fixes', () {
      final LocationLog fix = _fix(minute: 0, accuracy: 80);
      expect(_accuracy(_track(const <LocationLog>[])).isRough(fix), isTrue);
      expect(
        _accuracy(_track(const <LocationLog>[]), threshold: 120).isRough(fix),
        isFalse,
        reason: 'an admin retuning Tracking Rules must reclassify history '
            'without any rewrite',
      );
    });

    test('a measured fix ignores a stale lowAccuracy flag', () {
      // Captured when the threshold was tighter, so the device flagged it.
      // Read time says otherwise, and read time wins.
      final LocationLog fix =
          _fix(minute: 0, accuracy: 20, lowAccuracy: true);
      expect(_accuracy(_track(const <LocationLog>[])).isRough(fix), isFalse);
    });

    test('a fix with no metre value falls back to the device flag', () {
      // A log rebuilt from the offline queue, or a payload older than the
      // accuracy slot: zero is "unknown", not "perfect".
      expect(
        _accuracy(_track(const <LocationLog>[]))
            .isRough(_fix(minute: 0, accuracy: 0, lowAccuracy: true)),
        isTrue,
      );
      expect(
        _accuracy(_track(const <LocationLog>[]))
            .isRough(_fix(minute: 0, accuracy: 0)),
        isFalse,
      );
    });

    test('the threshold is read off the config, never hard-coded', () {
      final RouteAccuracy a = RouteAccuracy.of(
        track: _track(const <LocationLog>[]),
        config: const TrackingConfig(minAccuracyMetres: 35),
      );
      expect(a.thresholdMetres, 35);
      expect(a.thresholdLabel, '±35 m');
    });

    test('counts and share describe the whole day', () {
      final RouteAccuracy a = _accuracy(_track(<LocationLog>[
        _fix(minute: 0, accuracy: 8),
        _fix(minute: 1, accuracy: 9),
        _fix(minute: 2, accuracy: 90),
        _fix(minute: 3, accuracy: 110),
      ]));
      expect(a.hasRough, isTrue);
      expect(a.roughCount, 2);
      expect(a.roughShare, closeTo(0.5, 1e-9));
      expect(a.roughFixes.map((LocationLog p) => p.id), <String>['f2', 'f3']);
    });

    test('a clean day has no rough anything', () {
      final RouteAccuracy a = _accuracy(_track(<LocationLog>[
        _fix(minute: 0, accuracy: 8),
        _fix(minute: 1, accuracy: 12),
      ]));
      expect(a.hasRough, isFalse);
      expect(a.roughCount, 0);
      expect(a.roughShare, 0);
    });
  });

  group('splitting a trace into runs', () {
    test('a clean segment is one solid run', () {
      final List<LocationLog> seg = <LocationLog>[
        for (int i = 0; i < 6; i++) _fix(minute: i, accuracy: 10),
      ];
      final List<AccuracyRun> runs = _accuracy(_track(seg)).runs(seg);
      expect(runs, hasLength(1));
      expect(runs.single.rough, isFalse);
      expect(runs.single.points, hasLength(6));
    });

    test('a rough stretch in the middle becomes its own run', () {
      final List<LocationLog> seg = <LocationLog>[
        _fix(minute: 0, accuracy: 10),
        _fix(minute: 1, accuracy: 10),
        _fix(minute: 2, accuracy: 120),
        _fix(minute: 3, accuracy: 140),
        _fix(minute: 4, accuracy: 10),
        _fix(minute: 5, accuracy: 10),
      ];
      final List<AccuracyRun> runs = _accuracy(_track(seg)).runs(seg);

      expect(runs.map((AccuracyRun r) => r.rough), <bool>[false, true, false]);
      // The edge *into* a weak fix is itself uncertain, so it is drawn rough:
      // the rough run starts at the last good fix and ends at the next one.
      expect(runs[0].points.map((LocationLog p) => p.id), <String>['f0', 'f1']);
      expect(
        runs[1].points.map((LocationLog p) => p.id),
        <String>['f1', 'f2', 'f3', 'f4'],
      );
      expect(runs[2].points.map((LocationLog p) => p.id), <String>['f4', 'f5']);
    });

    test('consecutive runs share a fix, so the drawn line has no hole', () {
      final List<LocationLog> seg = <LocationLog>[
        _fix(minute: 0, accuracy: 10),
        _fix(minute: 1, accuracy: 10),
        _fix(minute: 2, accuracy: 200),
        _fix(minute: 3, accuracy: 10),
        _fix(minute: 4, accuracy: 10),
      ];
      final List<AccuracyRun> runs = _accuracy(_track(seg)).runs(seg);
      for (int i = 1; i < runs.length; i++) {
        expect(
          runs[i].points.first.id,
          runs[i - 1].points.last.id,
          reason: 'run $i must start where run ${i - 1} ended',
        );
      }
    });

    test('an all-rough segment is one rough run, not many', () {
      final List<LocationLog> seg = <LocationLog>[
        for (int i = 0; i < 5; i++) _fix(minute: i, accuracy: 90),
      ];
      final List<AccuracyRun> runs = _accuracy(_track(seg)).runs(seg);
      expect(runs, hasLength(1));
      expect(runs.single.rough, isTrue);
    });

    test('degenerate segments do not throw', () {
      final RouteAccuracy a = _accuracy(_track(const <LocationLog>[]));
      expect(a.runs(const <LocationLog>[]), isEmpty);
      expect(a.runs(<LocationLog>[_fix(minute: 0, accuracy: 90)]), hasLength(1));
      expect(a.runs(<LocationLog>[_fix(minute: 0, accuracy: 90)]).single.rough,
          isTrue);
    });
  });

  group('stops and visits', () {
    StopRecord stop({
      required int fromMinute,
      required int toMinute,
      String id = 'stop1',
      String? visitId,
    }) =>
        StopRecord(
          id: id,
          sessionId: 's1',
          arrival: _day.add(Duration(hours: 9, minutes: fromMinute)),
          departure: _day.add(Duration(hours: 9, minutes: toMinute)),
          latitude: 23.24,
          longitude: 87.86,
          radiusMetres: 40,
          visitId: visitId,
        );

    test('a dwell that was mostly weak reads as rough', () {
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 10, accuracy: 90),
        _fix(minute: 12, accuracy: 110),
        _fix(minute: 14, accuracy: 95),
        _fix(minute: 16, accuracy: 12),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 16);
      final StopAccuracy q = _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s);

      expect(q.fixCount, 4);
      expect(q.roughCount, 3);
      expect(q.isRough, isTrue);
      expect(q.averageMetres, closeTo((90 + 110 + 95 + 12) / 4, 1e-9));
    });

    test('one weak fix inside a good dwell is not a rough stop', () {
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 10, accuracy: 9),
        _fix(minute: 12, accuracy: 11),
        _fix(minute: 14, accuracy: 140),
        _fix(minute: 16, accuracy: 8),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 16);
      final StopAccuracy q = _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s);

      expect(q.roughCount, 1);
      expect(q.isRough, isFalse,
          reason: 'GPS drops a fix now and then; that is not a jitter stop');
    });

    test('an exact half is not "predominantly"', () {
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 10, accuracy: 9),
        _fix(minute: 12, accuracy: 140),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 12);
      expect(
        _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s).isRough,
        isFalse,
      );
    });

    test('fixes from another session never count towards a stop', () {
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 10, accuracy: 200, session: 's2'),
        _fix(minute: 12, accuracy: 200, session: 's2'),
        _fix(minute: 14, accuracy: 9),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 14);
      final StopAccuracy q = _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s);
      expect(q.fixCount, 1);
      expect(q.isRough, isFalse);
    });

    test('a stop with no fixes of its own is unknown, not rough', () {
      // Nothing in the window and nothing within reach of the centre: a
      // heavily simplified payload. Unknown must not be decorated.
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 400, accuracy: 300, lat: 23.90, lng: 88.40),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 16);
      final StopAccuracy q = _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s);
      expect(q.isKnown, isFalse);
      expect(q.isRough, isFalse);
      expect(q.averageMetres, isNull);
    });

    test('an empty time window falls back to fixes near the stop', () {
      // Simplification thinned the dwell out of the window but left the
      // cluster sitting on top of the stop.
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 40, accuracy: 120),
        _fix(minute: 42, accuracy: 130),
      ];
      final StopRecord s = stop(fromMinute: 10, toMinute: 16);
      final StopAccuracy q = _accuracy(_track(pts, stops: <StopRecord>[s])).stop(s);
      expect(q.fixCount, 2);
      expect(q.isRough, isTrue);
    });

    test('a visit is judged the same way as a stop', () {
      final CompanyVisit v = CompanyVisit(
        id: 'v1',
        sessionId: 's1',
        companyId: 'c1',
        branchId: 'b1',
        stopId: 'stop1',
        arrival: _day.add(const Duration(hours: 9, minutes: 10)),
        departure: _day.add(const Duration(hours: 9, minutes: 14)),
        latitude: 23.24,
        longitude: 87.86,
        status: VisitStatus.completed,
        reportIds: const <String>[],
        dealReference: null,
      );
      final List<LocationLog> pts = <LocationLog>[
        _fix(minute: 10, accuracy: 120),
        _fix(minute: 12, accuracy: 130),
        _fix(minute: 14, accuracy: 10),
      ];
      expect(
        _accuracy(_track(pts, visits: <CompanyVisit>[v])).visit(v).isRough,
        isTrue,
      );
    });
  });

  group('dash geometry', () {
    test('a straight line is cut into evenly spaced pieces', () {
      final List<(Offset, Offset)> dashes = dashSegments(
        const <Offset>[Offset.zero, Offset(100, 0)],
        dash: 10,
        gap: 10,
      );
      expect(dashes, hasLength(5));
      expect(dashes.first.$1, Offset.zero);
      expect(dashes.first.$2, const Offset(10, 0));
      expect(dashes[1].$1, const Offset(20, 0));
      // Dashed means less ink than solid — that is the whole point.
      final double inked = dashes.fold<double>(
        0,
        (double sum, (Offset, Offset) d) => sum + (d.$2 - d.$1).distance,
      );
      expect(inked, closeTo(50, 1e-6));
    });

    test('the pattern carries across a vertex instead of restarting', () {
      final List<(Offset, Offset)> dashes = dashSegments(
        const <Offset>[Offset.zero, Offset(15, 0), Offset(30, 0)],
        dash: 10,
        gap: 10,
      );
      // 0–10 ink, 10–20 gap, 20–30 ink: the bend at x=15 lands mid-gap and
      // must not start a fresh dash.
      expect(dashes, hasLength(2));
      expect(dashes[1].$1.dx, closeTo(20, 1e-6));
    });

    test('a degenerate pattern returns nothing rather than hanging', () {
      expect(
        dashSegments(const <Offset>[Offset.zero, Offset(10, 0)],
            dash: 0, gap: 5),
        isEmpty,
      );
      expect(dashSegments(const <Offset>[Offset.zero], dash: 5, gap: 5),
          isEmpty);
    });
  });

  group('the rendering decision on the tile map', () {
    List<LocationLog> segment() => <LocationLog>[
          _fix(minute: 0, accuracy: 10, lat: 23.240, lng: 87.860),
          _fix(minute: 1, accuracy: 10, lat: 23.241, lng: 87.861),
          _fix(minute: 2, accuracy: 140, lat: 23.242, lng: 87.862),
          _fix(minute: 3, accuracy: 150, lat: 23.243, lng: 87.863),
          _fix(minute: 4, accuracy: 10, lat: 23.244, lng: 87.864),
          _fix(minute: 5, accuracy: 10, lat: 23.245, lng: 87.865),
        ];

    test('a rough stretch is dotted and uncased; a clean one is not', () {
      final List<LocationLog> seg = segment();
      final List<Polyline<Object>> lines = rawTracePolylines(
        segments: <List<LocationLog>>[seg],
        accuracy: _accuracy(_track(seg)),
        underSnap: false,
      );

      expect(lines, hasLength(3), reason: 'clean, rough, clean');

      final Polyline<Object> rough = lines[1];
      expect(rough.pattern.spacingFactor, isNotNull,
          reason: 'dotted, so it cannot be mistaken for the dashed raw trace');
      expect(rough.borderStrokeWidth, 0);
      expect(rough.borderColor, Colors.transparent);
      expect(rough.strokeWidth, lessThan(lines.first.strokeWidth));
      expect(rough.color.a, lessThan(lines.first.color.a));

      for (final Polyline<Object> clean in <Polyline<Object>>[
        lines.first,
        lines.last,
      ]) {
        expect(clean.pattern, const StrokePattern.solid());
        expect(clean.borderStrokeWidth, 2);
        expect(clean.color, lines.first.color);
      }
    });

    test('a clean segment draws exactly one solid, cased line', () {
      final List<LocationLog> seg = <LocationLog>[
        for (int i = 0; i < 5; i++)
          _fix(minute: i, accuracy: 10, lat: 23.24 + i * 0.001),
      ];
      final List<Polyline<Object>> lines = rawTracePolylines(
        segments: <List<LocationLog>>[seg],
        accuracy: _accuracy(_track(seg)),
        underSnap: false,
      );
      expect(lines, hasLength(1));
      expect(lines.single.pattern, const StrokePattern.solid());
      expect(lines.single.borderStrokeWidth, 2);
    });

    test('raising the threshold turns the rough stretch solid again', () {
      final List<LocationLog> seg = segment();
      final List<Polyline<Object>> lines = rawTracePolylines(
        segments: <List<LocationLog>>[seg],
        accuracy: _accuracy(_track(seg), threshold: 200),
        underSnap: false,
      );
      expect(lines, hasLength(1));
      expect(lines.single.pattern, const StrokePattern.solid());
    });

    test('under a road match the rough run is quieter still', () {
      final List<LocationLog> seg = segment();
      final List<Polyline<Object>> lines = rawTracePolylines(
        segments: <List<LocationLog>>[seg],
        accuracy: _accuracy(_track(seg)),
        underSnap: true,
      );
      expect(lines, hasLength(3));
      // The clean parts of a raw trace under a snapped line are dashed; the
      // rough parts stay dotted, and fainter than the clean ones.
      expect(lines.first.pattern.segments, isNotNull);
      expect(lines[1].pattern.spacingFactor, isNotNull);
      expect(lines[1].color.a, lessThan(lines.first.color.a));
    });

    test('each session keeps its own colour across the split', () {
      final List<LocationLog> a = segment();
      final List<LocationLog> b = segment()
          .map((LocationLog p) => LocationLog(
                id: 'b${p.id}',
                sessionId: 's2',
                latitude: p.latitude,
                longitude: p.longitude,
                accuracy: p.accuracy,
                speedKmh: p.speedKmh,
                recordedAt: p.recordedAt,
                syncState: p.syncState,
              ))
          .toList();
      final List<Polyline<Object>> lines = rawTracePolylines(
        segments: <List<LocationLog>>[a, b],
        accuracy: _accuracy(_track(<LocationLog>[...a, ...b])),
        underSnap: false,
      );
      expect(lines, hasLength(6));
      expect(lines[0].color, sessionColor(0));
      expect(lines[3].color, sessionColor(1));
    });
  });
}
