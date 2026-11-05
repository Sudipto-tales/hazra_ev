import 'dart:async';

import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/core/utils/geo.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// The employee's own live values, which the server cannot supply in time.
///
/// `Engine::refreshSession` only recomputes on a fix batch, and batches go up
/// every `syncIntervalSeconds` (300 s by default) — so the server's idea of
/// where the employee is and how far they have gone is up to five minutes
/// behind the phone holding the fixes. These tests pin the two things that
/// follow from that: movement has exactly one source (the location service),
/// and distance is measured on the device with the server as a baseline rather
/// than as the only answer.

/// A location service the test drives by hand: no timers, no plugin, just the
/// three streams [TrackingController] subscribes to.
class _ScriptedLocationService implements LocationService {
  _ScriptedLocationService();

  TrackingConfig config = TrackingConfig.defaults;
  LocationHealth scriptedHealth = LocationHealth.ok;

  /// Handed back by [currentFix] — the session's opening and closing fix.
  LocationLog? nextFix;

  final StreamController<LocationLog> _fixes =
      StreamController<LocationLog>.broadcast();
  final StreamController<SyncSnapshot> _sync =
      StreamController<SyncSnapshot>.broadcast();
  final StreamController<MovementStatus> _movement =
      StreamController<MovementStatus>.broadcast();
  final StreamController<LocationHealth> _health =
      StreamController<LocationHealth>.broadcast();

  MovementStatus _movementValue = MovementStatus.unknown;
  SyncSnapshot _syncValue = const SyncSnapshot(
    queued: 0,
    failed: 0,
    lastSyncedAt: null,
    isOnline: true,
  );

  bool tracking = false;

  void emitFix(LocationLog fix) => _fixes.add(fix);

  void emitMovement(MovementStatus m) {
    _movementValue = m;
    _movement.add(m);
  }

  /// Push a health change the way the real service does off the OS location
  /// toggle, so the controller's subscription can be exercised without a plugin.
  void emitHealth(LocationHealth h) {
    scriptedHealth = h;
    _health.add(h);
  }

  void emitSync(SyncSnapshot s) {
    _syncValue = s;
    _sync.add(s);
  }

  @override
  void updateConfig(TrackingConfig next) => config = next;

  @override
  Future<LocationHealth> health() async => scriptedHealth;

  @override
  Future<LocationHealth> requestPermission({bool background = false}) async =>
      scriptedHealth;

  @override
  Future<LocationLog?> currentFix({required String sessionId}) async {
    final LocationLog? fix = nextFix;
    if (fix == null) return null;
    return LocationLog(
      id: fix.id,
      sessionId: sessionId,
      latitude: fix.latitude,
      longitude: fix.longitude,
      accuracy: fix.accuracy,
      speedKmh: fix.speedKmh,
      recordedAt: fix.recordedAt,
      syncState: fix.syncState,
    );
  }

  @override
  Stream<LocationLog> track({required String sessionId}) =>
      _fixes.stream.where((LocationLog f) => f.sessionId == sessionId);

  @override
  Future<void> startTracking({required String sessionId}) async {
    tracking = true;
    emitMovement(MovementStatus.unknown);
  }

  @override
  Future<void> stopTracking() async {
    tracking = false;
    emitMovement(MovementStatus.unknown);
  }

  @override
  Stream<SyncSnapshot> syncState() async* {
    yield _syncValue;
    yield* _sync.stream;
  }

  @override
  Future<void> flushQueue() async {}

  @override
  MovementStatus get movement => _movementValue;

  @override
  Stream<MovementStatus> movementState() async* {
    yield _movementValue;
    yield* _movement.stream;
  }

  @override
  Stream<LocationHealth> healthState() async* {
    yield scriptedHealth;
    yield* _health.stream;
  }

  Future<void> dispose() async {
    await _fixes.close();
    await _sync.close();
    await _movement.close();
    await _health.close();
  }
}

/// Serves a snapshot the test controls, so "what the server says" can be moved
/// around underneath the controller.
class _ScriptedRepository extends MockEmployeeRepository {
  _ScriptedRepository() : super(latency: Duration.zero);

  double serverDistanceKm = 0;
  WorkStatus status = WorkStatus.notStarted;
  List<WorkSession> sessions = const <WorkSession>[];

  @override
  Future<HomeSnapshot> home() async => HomeSnapshot(
        status: status,
        // Deliberately the opposite of what the device knows, so a test that
        // reads this value back has definitely not gone to the server for it.
        movement: MovementStatus.moving,
        locationHealth: LocationHealth.ok,
        sessions: sessions,
        summary: DaySummary.empty.copyWith(distanceKm: serverDistanceKm),
        activity: const <ActivityEvent>[],
        visits: const <CompanyVisit>[],
        stops: const <StopRecord>[],
        lastFix: null,
        sync: const SyncSnapshot(
          queued: 0,
          failed: 0,
          lastSyncedAt: null,
          isOnline: true,
        ),
      );

  @override
  Future<TrackingConfig> config() async => TrackingConfig.defaults;
}

LocationLog _fix(
  String id, {
  required double lat,
  required double lng,
  double speedKmh = 0,
  DateTime? at,
  String session = '',
}) =>
    LocationLog(
      id: id,
      sessionId: session,
      latitude: lat,
      longitude: lng,
      accuracy: 8,
      speedKmh: speedKmh,
      recordedAt: at ?? DateTime.now(),
      syncState: SyncState.queued,
    );

void main() {
  late _ScriptedLocationService location;
  late _ScriptedRepository repo;
  late TrackingController tracking;

  setUp(() {
    location = _ScriptedLocationService();
    repo = _ScriptedRepository();
    tracking = TrackingController(
      repository: repo,
      locationService: location,
    );
  });

  tearDown(() async {
    tracking.dispose();
    await location.dispose();
  });

  group('great-circle helper', () {
    test('measures a known short hop', () {
      // 0.01° of latitude is ~1.11 km anywhere on Earth.
      final double km = kmBetween(23.2400, 87.8600, 23.2500, 87.8600);
      expect(km, closeTo(1.111, 0.01));
    });

    test('is zero for a point against itself', () {
      expect(kmBetween(23.24, 87.86, 23.24, 87.86), 0);
    });
  });

  group('movement has one source', () {
    test('a session opens as unknown, not as a hard-coded "stationary"',
        () async {
      location.nextFix = _fix('open', lat: 23.24, lng: 87.86);

      final StartDayResult result = await tracking.startDay();

      expect(result.outcome, StartDayOutcome.started);
      expect(
        tracking.movement,
        MovementStatus.unknown,
        reason: 'no fix has argued either way yet; claiming "stationary" is '
            'the bug, not a safe default',
      );
    });

    test('the chip follows the location service, not fix.speedKmh', () async {
      location.nextFix = _fix('open', lat: 23.24, lng: 87.86);
      await tracking.startDay();

      location.emitMovement(MovementStatus.moving);
      await Future<void>.delayed(Duration.zero);
      expect(tracking.movement, MovementStatus.moving);

      // A single fast fix must NOT flip the chip on its own: the debounced
      // decision lives in LocationService, and the controller only mirrors it.
      location.emitFix(_fix(
        'f1',
        lat: 23.2401,
        lng: 87.8601,
        speedKmh: 0,
        session: tracking.activeSession!.id,
      ));
      await Future<void>.delayed(Duration.zero);
      expect(tracking.movement, MovementStatus.moving);

      location.emitMovement(MovementStatus.stationary);
      await Future<void>.delayed(Duration.zero);
      expect(tracking.movement, MovementStatus.stationary);
    });

    test('a closed session reports unknown, not stationary', () async {
      location.nextFix = _fix('open', lat: 23.24, lng: 87.86);
      await tracking.startDay();
      location.emitMovement(MovementStatus.moving);
      await Future<void>.delayed(Duration.zero);

      await tracking.pauseSession();

      expect(tracking.movement, MovementStatus.unknown);
      expect(tracking.activeSession, isNull);
    });
  });

  group('distance is measured on the device', () {
    test('accumulates across accepted fixes without waiting for the server',
        () async {
      location.nextFix = _fix('open', lat: 23.2400, lng: 87.8600);
      await tracking.startDay();
      final String sid = tracking.activeSession!.id;

      expect(tracking.summary.distanceKm, 0);

      // Two ~1.11 km hops north.
      location.emitFix(_fix('f1', lat: 23.2500, lng: 87.8600, session: sid));
      location.emitFix(_fix('f2', lat: 23.2600, lng: 87.8600, session: sid));
      await Future<void>.delayed(Duration.zero);

      expect(tracking.summary.distanceKm, closeTo(2.22, 0.05));
      expect(tracking.liveDistanceKm, tracking.summary.distanceKm);
      expect(
        tracking.activeSession!.distanceKm,
        closeTo(2.22, 0.05),
        reason: 'the open session carries what this device measured for it',
      );
    });

    test('ignores sub-filter jitter so a parked phone does not walk', () async {
      location.nextFix = _fix('open', lat: 23.2400, lng: 87.8600);
      await tracking.startDay();
      final String sid = tracking.activeSession!.id;

      // ~1 m apart, well under the 20 m distance filter.
      for (int i = 1; i <= 20; i++) {
        location.emitFix(_fix(
          'j$i',
          lat: 23.2400 + i * 0.00001,
          lng: 87.8600,
          session: sid,
        ));
      }
      await Future<void>.delayed(Duration.zero);

      expect(tracking.summary.distanceKm, 0);
    });

    test('a refresh takes the server as a baseline and never steps backwards',
        () async {
      location.nextFix = _fix('open', lat: 23.2400, lng: 87.8600);
      await tracking.startDay();
      final String sid = tracking.activeSession!.id;

      location.emitFix(_fix('f1', lat: 23.2500, lng: 87.8600, session: sid));
      await Future<void>.delayed(Duration.zero);
      final double local = tracking.summary.distanceKm;
      expect(local, greaterThan(1));

      // The server has not ingested a batch yet, so it still reports zero.
      // That must not wipe what the device measured.
      repo.serverDistanceKm = 0;
      await tracking.refresh();
      expect(
        tracking.summary.distanceKm,
        greaterThanOrEqualTo(local),
        reason: 'a travel figure that ticks backwards on a pull-to-refresh '
            'reads as a bug, not as precision',
      );

      // Now the server catches up and overtakes: it wins as the baseline.
      repo.serverDistanceKm = 9;
      await tracking.refresh();
      expect(tracking.summary.distanceKm, greaterThanOrEqualTo(9));
    });

    test('legs already uploaded are retired rather than double counted',
        () async {
      location.nextFix = _fix('open', lat: 23.2400, lng: 87.8600);
      await tracking.startDay();
      final String sid = tracking.activeSession!.id;

      final DateTime t0 = DateTime.now();
      location.emitFix(_fix(
        'f1',
        lat: 23.2500,
        lng: 87.8600,
        at: t0,
        session: sid,
      ));
      await Future<void>.delayed(Duration.zero);

      // The queue drained after that fix, so the server has seen it.
      location.emitSync(SyncSnapshot(
        queued: 0,
        failed: 0,
        lastSyncedAt: t0.add(const Duration(seconds: 1)),
        isOnline: true,
      ));
      await Future<void>.delayed(Duration.zero);

      // The server now reports exactly that leg. The device must not add its
      // own copy on top.
      repo.serverDistanceKm = 1.111;
      await tracking.refresh();

      expect(tracking.summary.distanceKm, closeTo(1.111, 0.05));
    });
  });
}
