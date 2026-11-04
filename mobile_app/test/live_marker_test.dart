import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/core/theme/app_colors.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/features/admin/map/live_marker.dart';
import 'package:employeetracking_mobile_app/features/admin/map/live_pin.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The live map's rules, tested where they live: in pure derivation, not in a
/// widget tree.
///
/// Four of these groups are the ones that would hurt most if they silently
/// broke — the v7 fallback (production serves 7 today), the null-heading
/// no-rotation rule, the stale-marker honesty rule, and the colour/glyph
/// separation.

const TrackingConfig config = TrackingConfig(
  offlineThresholdMinutes: 10,
  longStopThresholdMinutes: 45,
);

final DateTime now = DateTime(2026, 11, 4, 12, 0);

Employee _employee({String id = 'e1'}) => Employee(
      id: id,
      name: 'Asha Roy',
      employeeCode: 'EMP-001',
      designation: 'Sales Executive',
      department: 'Sales',
      email: 'asha@example.com',
      phone: '9000000000',
      region: 'Bankura',
      reportingTo: 'ADM-001',
      joinedOn: DateTime(2025, 1, 1),
      avatarUrl: '',
      bannerUrl: '',
      bloodGroup: '',
      address: '',
    );

LocationLog _fix({
  Duration age = Duration.zero,
  double? heading,
  double lat = 23.24,
  double lng = 87.86,
}) =>
    LocationLog(
      id: 'loc_1',
      sessionId: 'ses_1',
      latitude: lat,
      longitude: lng,
      accuracy: 8,
      speedKmh: 12,
      recordedAt: now.subtract(age),
      syncState: SyncState.synced,
      headingDeg: heading,
    );

TeamMember _member({
  ActivityState? activity,
  WorkStatus status = WorkStatus.working,
  LocationLog? fix,
  Duration? dwell,
  bool active = true,
}) =>
    TeamMember(
      employee: _employee(),
      status: status,
      movement: MovementStatus.moving,
      locationHealth: LocationHealth.ok,
      summary: DaySummary.empty,
      attendanceStatus: AttendanceStatus.present,
      active: active,
      activity: activity,
      lastFix: fix ?? _fix(),
      openStopDuration: dwell,
    );

LiveMarker _marker({
  ActivityState? activity,
  WorkStatus status = WorkStatus.working,
  LocationLog? fix,
  Duration? dwell,
}) =>
    LiveMarker.of(
      _member(activity: activity, status: status, fix: fix, dwell: dwell),
      config: config,
      now: now,
    )!;

void main() {
  group('v7 server — every new field degrades, nothing breaks', () {
    test('Wire.config resolves all four v9 knobs to the local default', () {
      // What a version-7 /config body looks like: the four fields simply are
      // not there. None of them may become 0 — a 0 s timer or a 0 km/h
      // threshold is a behaviour change dressed up as a parse.
      final TrackingConfig c = Wire.config(<String, dynamic>{
        'locationIntervalSeconds': 60,
        'minAccuracyMetres': 50,
        'movementSpeedThresholdKmh': 2,
        'offlineThresholdMinutes': 10,
        'syncBatchSize': 50,
      });

      expect(c.syncIntervalSeconds, 300);
      expect(c.distanceFilterMetres, 20);
      expect(c.movementExitSpeedKmh, 3);
      expect(c.liveSyncIntervalSeconds, 15);
    });

    test('an explicit null is the same as absent', () {
      final TrackingConfig c = Wire.config(<String, dynamic>{
        'syncIntervalSeconds': null,
        'liveSyncIntervalSeconds': null,
        'movementExitSpeedKmh': null,
        'distanceFilterMetres': null,
      });

      expect(c.syncIntervalSeconds, 300);
      expect(c.liveSyncIntervalSeconds, 15);
      expect(c.movementExitSpeedKmh, 3);
      expect(c.distanceFilterMetres, 20);
    });

    test('a v9 server is read as sent', () {
      final TrackingConfig c = Wire.config(<String, dynamic>{
        'syncIntervalSeconds': 120,
        'liveSyncIntervalSeconds': 10,
        'movementExitSpeedKmh': 4.5,
        'distanceFilterMetres': 35,
      });

      expect(c.syncIntervalSeconds, 120);
      expect(c.liveSyncIntervalSeconds, 10);
      expect(c.movementExitSpeedKmh, 4.5);
      expect(c.distanceFilterMetres, 35);
    });

    test('a teamMember with no activity parses, and the map still draws it',
        () {
      final TeamMember m = Wire.teamMember(<String, dynamic>{
        'employee': <String, dynamic>{'id': 'e1', 'name': 'Asha Roy'},
        'status': 'working',
        'movement': 'moving',
        'locationHealth': 'ok',
        'active': true,
        'lastFix': <String, dynamic>{
          'id': 'loc_1',
          'latitude': 23.24,
          'longitude': 87.86,
          'recordedAt': now.toUtc().toIso8601String(),
        },
      });

      expect(m.activity, isNull, reason: 'v7 has no activity to send');

      final LiveMarker? marker =
          LiveMarker.of(m, config: config, now: now.add(const Duration(seconds: 5)));

      expect(marker, isNotNull,
          reason: 'a v7 payload must still produce a marker');
      expect(marker!.glyph, Icons.circle,
          reason: 'neutral glyph, not a guess at what activity would say');
      expect(marker.tone, LiveMarkerTone.moving,
          reason: 'colour falls back to the one status enum the server does '
              'send — the same mapping the roster already uses');
      expect(marker.bearingDeg, isNull);
      expect(marker.stateLabel, 'Working');
    });

    test('a 6-slot route tuple is still a complete fix', () {
      final RouteTrack track = Wire.routeTrack(<String, dynamic>{
        'employeeId': 'e1',
        'date': '2026-11-04',
        'sessions': <dynamic>[],
        'points': <dynamic>[
          <dynamic>[23.24, 87.86, 1762257600, 8, 12, 1],
          <dynamic>[23.25, 87.87, 1762257660, 8, 14, 1, 91.5],
          // Garbage in slot 6 is unknown, not clamped: no heading is better
          // than a wrong one.
          <dynamic>[23.26, 87.88, 1762257720, 8, 14, 1, 400],
          <dynamic>[23.27, 87.89, 1762257780, 8, 14, 1, null],
        ],
      });

      expect(track.points, hasLength(4));
      expect(track.points[0].headingDeg, isNull);
      expect(track.points[1].headingDeg, 91.5);
      expect(track.points[2].headingDeg, isNull);
      expect(track.points[3].headingDeg, isNull);
    });

    test('activity parses whether the server spells it snake or camel', () {
      for (final String raw in <String>[
        'location_unavailable',
        'locationUnavailable',
      ]) {
        final TeamMember m = Wire.teamMember(<String, dynamic>{
          'employee': <String, dynamic>{'id': 'e1', 'name': 'Asha'},
          'status': 'locationUnavailable',
          'activity': raw,
        });
        expect(m.activity, ActivityState.locationUnavailable, reason: raw);
      }
    });
  });

  group('null heading is never drawn as north', () {
    test('bearingDeg stays null when the fix carries no heading', () {
      expect(_marker(activity: ActivityState.moving).bearingDeg, isNull);
    });

    test('a real heading of 0 is kept — 0 means due north', () {
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(heading: 0),
      );
      expect(m.bearingDeg, 0);
    });

    test('a stale fix drops its heading too', () {
      // A direction-of-travel notch on a 40-minute-old fix claims the employee
      // is still going that way.
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(age: const Duration(minutes: 40), heading: 90),
      );
      expect(m.stale, isTrue);
      expect(m.bearingDeg, isNull);
    });

    testWidgets('the pin draws no bearing notch on a null heading',
        (WidgetTester tester) async {
      final LiveMarker marker = _marker(activity: ActivityState.moving);

      await tester.pumpWidget(
        MaterialApp(
          home: Center(
            child: LivePin(
              marker: marker,
              bearingDeg: marker.bearingDeg,
              selected: false,
              width: 34,
            ),
          ),
        ),
      );

      expect(find.byType(BearingNotch), findsNothing,
          reason: 'unknown heading means no notch at all, not a notch at 0');
    });

    testWidgets('a known heading rotates the notch and only the notch',
        (WidgetTester tester) async {
      final LiveMarker marker = _marker(
        activity: ActivityState.moving,
        fix: _fix(heading: 143),
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Center(
            child: LivePin(
              marker: marker,
              bearingDeg: marker.bearingDeg,
              selected: false,
              width: 34,
            ),
          ),
        ),
      );

      final BearingNotch notch =
          tester.widget<BearingNotch>(find.byType(BearingNotch));
      expect(notch.bearingDeg, 143);

      // The glyph is inside the pin but outside the rotation: a vehicle icon
      // spun to 143 degrees reads as a broken asset.
      final Finder glyph = find.byIcon(Icons.two_wheeler_rounded);
      expect(glyph, findsOneWidget);
      expect(
        find.ancestor(of: glyph, matching: find.byType(BearingNotch)),
        findsNothing,
      );
    });

    test('bearing lerps the short way round the circle', () {
      const MarkerMotion motion = MarkerMotion(
        fromLat: 0,
        fromLng: 0,
        toLat: 0,
        toLng: 0,
        fromBearing: 350,
        toBearing: 10,
      );

      // 350 -> 10 is a 20-degree nudge clockwise, not a 340-degree spin back.
      expect(motion.bearingAt(0), 350);
      expect(motion.bearingAt(0.5), 0, reason: 'half way is due north');
      expect(motion.bearingAt(1), 10);
    });

    test('there is no interpolating to or from an unknown heading', () {
      const MarkerMotion toUnknown = MarkerMotion(
        fromLat: 0,
        fromLng: 0,
        toLat: 0,
        toLng: 0,
        fromBearing: 90,
      );
      expect(toUnknown.bearingAt(0.5), isNull);

      const MarkerMotion fromUnknown = MarkerMotion(
        fromLat: 0,
        fromLng: 0,
        toLat: 0,
        toLng: 0,
        toBearing: 90,
      );
      expect(fromUnknown.bearingAt(0.5), 90,
          reason: 'a first known heading appears, it does not swing in');
    });

    test('position lerps rather than teleporting', () {
      const MarkerMotion motion = MarkerMotion(
        fromLat: 10,
        fromLng: 20,
        toLat: 11,
        toLng: 22,
      );
      expect(motion.latAt(0.5), 10.5);
      expect(motion.lngAt(0.5), 21);
      expect(motion.isStill, isFalse);
    });
  });

  group('the honesty rule — a watch is a request for freshness', () {
    test('a fix inside the offline window is live and opaque', () {
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(age: const Duration(minutes: 9, seconds: 59)),
      );

      expect(m.stale, isFalse);
      expect(m.opacity, 1);
      expect(m.tone, LiveMarkerTone.moving);
    });

    test('a fix past offlineThresholdMinutes goes grey at 60 percent', () {
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(age: const Duration(minutes: 12)),
      );

      expect(m.stale, isTrue);
      expect(m.tone, LiveMarkerTone.stale);
      expect(m.tone.color, AppColors.statusOffline);
      expect(m.opacity, closeTo(0.6, 0.0001));
      expect(m.ageLabel, '12 min ago');
    });

    test('stale outranks every other tone, including an alert', () {
      final LiveMarker m = _marker(
        activity: ActivityState.locationUnavailable,
        fix: _fix(age: const Duration(hours: 2)),
      );

      expect(m.tone, LiveMarkerTone.stale,
          reason: 'how old the data is matters more than what it said');
      expect(m.ageLabel, '2 hr ago');
    });

    test('every marker carries a real age, including a fresh one', () {
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(age: const Duration(seconds: 7)),
      );
      // Not "just now": a label that rounds away the seconds is
      // indistinguishable from a screen that has stopped updating.
      expect(m.ageLabel, '7s ago');
      expect(m.fixAge, const Duration(seconds: 7));
    });

    test('a device clock running ahead reads as fresh, not as the future', () {
      final LiveMarker m = _marker(
        activity: ActivityState.moving,
        fix: _fix(age: const Duration(minutes: -3)),
      );
      expect(m.fixAge, Duration.zero);
      expect(m.stale, isFalse);
    });
  });

  group('colour is state, glyph is activity, and they never mix', () {
    test('each activity gets its own glyph, 1:1', () {
      final Set<IconData> glyphs = <IconData>{};
      for (final ActivityState a in ActivityState.values) {
        if (a == ActivityState.notStarted) continue;
        glyphs.add(_marker(activity: a).glyph);
      }
      expect(glyphs, hasLength(ActivityState.values.length - 1),
          reason: 'no two activities may share a glyph');
    });

    test('tones collapse to the five an admin can name', () {
      expect(_marker(activity: ActivityState.moving).tone,
          LiveMarkerTone.moving);
      expect(_marker(activity: ActivityState.idle).tone,
          LiveMarkerTone.stopped);
      expect(_marker(activity: ActivityState.stopped).tone,
          LiveMarkerTone.stopped);
      expect(_marker(activity: ActivityState.locationUnavailable).tone,
          LiveMarkerTone.alert);
      expect(_marker(activity: ActivityState.paused).tone,
          LiveMarkerTone.lastKnown);
      expect(_marker(activity: ActivityState.ended).tone,
          LiveMarkerTone.lastKnown);
    });

    test('a long stop is an alert, and the ring fills on the way there', () {
      final LiveMarker short = _marker(
        activity: ActivityState.stopped,
        dwell: const Duration(minutes: 15),
      );
      expect(short.tone, LiveMarkerTone.stopped);
      expect(short.dwellFraction, closeTo(15 / 45, 0.001));
      expect(short.longStop, isFalse);

      final LiveMarker long = _marker(
        activity: ActivityState.stopped,
        dwell: const Duration(minutes: 50),
      );
      expect(long.tone, LiveMarkerTone.alert);
      expect(long.dwellFraction, 1, reason: 'the ring tops out, it does not wrap');
      expect(long.longStop, isTrue);
    });

    test('not_started and a missing fix are both off the map', () {
      expect(
        LiveMarker.of(
          _member(activity: ActivityState.notStarted),
          config: config,
          now: now,
        ),
        isNull,
      );
      expect(
        LiveMarker.of(
          TeamMember(
            employee: _employee(),
            status: WorkStatus.working,
            movement: MovementStatus.unknown,
            locationHealth: LocationHealth.ok,
            summary: DaySummary.empty,
            attendanceStatus: AttendanceStatus.present,
            active: true,
            activity: ActivityState.moving,
          ),
          config: config,
          now: now,
        ),
        isNull,
        reason: 'no coordinate means no marker — never an invented position',
      );
      // v7: no activity, but `status` alone settles this one case.
      expect(
        LiveMarker.of(
          _member(status: WorkStatus.notStarted),
          config: config,
          now: now,
        ),
        isNull,
      );
    });
  });

  group('clustering — nine phones in a depot are not nine discs', () {
    List<LiveMarker> depot(int n) => List<LiveMarker>.generate(
          n,
          (int i) => LiveMarker.of(
            _member(
              activity: ActivityState.idle,
              fix: _fix(lat: 23.24 + i * 0.0001, lng: 87.86 + i * 0.0001),
            ),
            config: config,
            now: now,
          )!,
        );

    test('below the cluster zoom, co-located markers collapse to one badge',
        () {
      final List<MarkerCluster> clusters =
          MarkerCluster.group(depot(9), zoom: 10, maxClusterZoom: 12);

      expect(clusters, hasLength(1));
      expect(clusters.single.isSingle, isFalse);
      expect(clusters.single.markers, hasLength(9));
    });

    test('above the cluster zoom every marker stands alone', () {
      final List<MarkerCluster> clusters =
          MarkerCluster.group(depot(9), zoom: 14, maxClusterZoom: 12);

      expect(clusters, hasLength(9));
      expect(clusters.every((MarkerCluster c) => c.isSingle), isTrue);
    });

    test('one person needing attention is not averaged away by eight', () {
      final List<LiveMarker> markers = <LiveMarker>[
        ...depot(8),
        _marker(
          activity: ActivityState.locationUnavailable,
          fix: _fix(lat: 23.2401, lng: 87.8601),
        ),
      ];

      final List<MarkerCluster> clusters =
          MarkerCluster.group(markers, zoom: 9, maxClusterZoom: 12);

      expect(clusters, hasLength(1));
      expect(clusters.single.tone, LiveMarkerTone.alert);
    });

    test('markers far apart are never merged', () {
      final List<LiveMarker> markers = <LiveMarker>[
        _marker(activity: ActivityState.moving, fix: _fix(lat: 23.24, lng: 87.86)),
        _marker(activity: ActivityState.moving, fix: _fix(lat: 12.97, lng: 77.59)),
      ];

      final List<MarkerCluster> clusters =
          MarkerCluster.group(markers, zoom: 11, maxClusterZoom: 12);

      expect(clusters, hasLength(2));
    });
  });
}
