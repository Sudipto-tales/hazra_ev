import 'dart:async';

import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// Location health has to be watched, not polled on a session timer.
///
/// The old arrangement only checked health from the per-second session ticker
/// (`_tick % 15`), and `_pollHealth` returned early unless a session was open.
/// So GPS switched off while idle, or while on break, went unnoticed until the
/// next Start Day tap — and `pauseSession` stops that ticker outright, so a
/// break left nothing watching at all.

/// A service whose health the test pushes by hand, the way the real one does
/// off `Geolocator.getServiceStatusStream()`.
class _PushyLocationService implements LocationService {
  TrackingConfig config = TrackingConfig.defaults;
  LocationHealth current = LocationHealth.ok;

  final StreamController<LocationLog> _fixes =
      StreamController<LocationLog>.broadcast();
  final StreamController<SyncSnapshot> _sync =
      StreamController<SyncSnapshot>.broadcast();
  final StreamController<MovementStatus> _movement =
      StreamController<MovementStatus>.broadcast();
  final StreamController<LocationHealth> _health =
      StreamController<LocationHealth>.broadcast();

  /// How many times the controller asked by *pulling*, so a test can show the
  /// push path is what did the work.
  int healthPulls = 0;

  bool tracking = false;
  int _seq = 0;

  void push(LocationHealth next) {
    current = next;
    _health.add(next);
  }

  @override
  void updateConfig(TrackingConfig next) => config = next;

  @override
  Future<LocationHealth> health() async {
    healthPulls++;
    return current;
  }

  @override
  Future<LocationHealth> requestPermission({bool background = false}) async =>
      current;

  @override
  Future<LocationLog?> currentFix({required String sessionId}) async {
    if (current.blocksStart) return null;
    return LocationLog(
      id: 'loc_${_seq++}',
      sessionId: sessionId,
      latitude: 23.24,
      longitude: 87.86,
      accuracy: 8,
      speedKmh: 0,
      recordedAt: DateTime.now(),
      syncState: SyncState.queued,
    );
  }

  @override
  Stream<LocationLog> track({required String sessionId}) => _fixes.stream;

  @override
  Future<void> startTracking({required String sessionId}) async =>
      tracking = true;

  @override
  Future<void> stopTracking() async => tracking = false;

  @override
  Stream<SyncSnapshot> syncState() => _sync.stream;

  @override
  Stream<MovementStatus> movementState() async* {
    yield MovementStatus.unknown;
    yield* _movement.stream;
  }

  @override
  Stream<LocationHealth> healthState() async* {
    yield current;
    yield* _health.stream;
  }

  @override
  MovementStatus get movement => MovementStatus.unknown;

  @override
  Future<void> flushQueue() async {}

  Future<void> dispose() async {
    await _fixes.close();
    await _sync.close();
    await _movement.close();
    await _health.close();
  }
}

void main() {
  late _PushyLocationService location;
  late TrackingController tracking;

  setUp(() {
    location = _PushyLocationService();
    tracking = TrackingController(
      repository: MockEmployeeRepository(latency: Duration.zero),
      locationService: location,
    );
  });

  tearDown(() async {
    tracking.dispose();
    await location.dispose();
  });

  /// Let the controller's healthState subscription deliver.
  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 20));

  group('health is watched with no session open', () {
    test('a pushed change is seen before the day has started', () async {
      await settle();
      expect(tracking.status, WorkStatus.notStarted);
      expect(tracking.effectiveLocationHealth, LocationHealth.ok);

      location.push(LocationHealth.serviceDisabled);
      await settle();

      // This is the case that used to be completely silent: no session, so no
      // ticker, so no poll, so Home said nothing until Start Day was refused.
      expect(tracking.idleLocationHealth, LocationHealth.serviceDisabled);
      expect(tracking.effectiveLocationHealth, LocationHealth.serviceDisabled);
    });

    test('listeners are notified so the banner can appear', () async {
      await settle();
      int notifications = 0;
      tracking.addListener(() => notifications++);

      location.push(LocationHealth.permissionDenied);
      await settle();

      expect(notifications, greaterThan(0));
    });

    test('an unchanged value does not wake listeners', () async {
      await settle();
      int notifications = 0;
      tracking.addListener(() => notifications++);

      location.push(LocationHealth.ok);
      await settle();

      expect(notifications, 0);
    });

    test('still watched after Break stops the ticker', () async {
      await tracking.startDay();
      await tracking.pauseSession();
      expect(tracking.status, WorkStatus.paused);
      await settle();

      location.push(LocationHealth.serviceDisabled);
      await settle();

      // `_stopTicker` cancelled the 15 s poll. The subscription is owned by the
      // controller, not the ticker, so it survives.
      expect(tracking.effectiveLocationHealth, LocationHealth.serviceDisabled);
    });
  });

  group('health with a session open', () {
    test('a pushed change lands on the snapshot, not the idle slot', () async {
      await tracking.startDay();
      await settle();
      expect(tracking.status.isSessionOpen, isTrue);

      location.push(LocationHealth.serviceDisabled);
      await settle();

      // Session health is session state and is what the server is told about;
      // writing it into the idle slot instead would lose it.
      expect(tracking.locationHealth, LocationHealth.serviceDisabled);
      expect(tracking.effectiveLocationHealth, LocationHealth.serviceDisabled);
    });

    test('an idle reading never degrades a day that has not started',
        () async {
      await settle();
      location.push(LocationHealth.serviceDisabled);
      await settle();

      // The snapshot's own health must stay untouched, or `_statusForHealth`
      // would report a degraded status for a day with no session in it.
      expect(tracking.status, WorkStatus.notStarted);
      expect(tracking.status.isDegraded, isFalse);
    });
  });

  group('refreshLocationHealth', () {
    test('re-reads on demand, for the return from system settings', () async {
      await settle();
      final int before = location.healthPulls;

      // No push — this is the case the streams cannot see: a runtime
      // permission changed in the settings app, which Android gives no
      // equivalent of getServiceStatusStream for.
      location.current = LocationHealth.permissionDeniedForever;
      await tracking.refreshLocationHealth();

      expect(location.healthPulls, greaterThan(before));
      expect(tracking.effectiveLocationHealth,
          LocationHealth.permissionDeniedForever);
    });

    test('is safe after dispose', () async {
      // Its own controller: `TrackingController.dispose` is not idempotent, so
      // disposing inside the test and again in tearDown would trip the
      // ValueNotifier assert and mask what is being checked here.
      final _PushyLocationService svc = _PushyLocationService();
      final TrackingController t = TrackingController(
        repository: MockEmployeeRepository(latency: Duration.zero),
        locationService: svc,
      );
      addTearDown(svc.dispose);
      await settle();

      t.dispose();
      svc.current = LocationHealth.serviceDisabled;

      // Home calls this from an app-resume callback, which can land after the
      // page — and the controller — are gone.
      await expectLater(t.refreshLocationHealth(), completes);
    });
  });

  group('what Home renders', () {
    test('ok and the legacy weak-signal value raise no banner', () {
      // A weak fix is a working session: GPS is on and recording, so warning
      // about it would train the employee to ignore the banner. poorAccuracy
      // is no longer produced at all, but an older device can still send it,
      // so it has to stay silent rather than merely unused.
      for (final LocationHealth h in <LocationHealth>[
        LocationHealth.ok,
        LocationHealth.poorAccuracy,
      ]) {
        expect(h.actionLabel, isNull);
      }
    });

    test('every state Home banners for offers an action', () {
      for (final LocationHealth h in LocationHealth.values) {
        final bool banners =
            h != LocationHealth.ok && h != LocationHealth.poorAccuracy;
        if (!banners) continue;
        expect(h.actionLabel, isNotNull,
            reason: 'Home banners for $h, so it needs a button that fixes it');
      }
    });
  });

  group('the mock service publishes its simulated health', () {
    test('assigning simulatedHealth pushes on healthState', () async {
      final MockLocationService mock =
          MockLocationService(emitInterval: const Duration(minutes: 5));
      addTearDown(mock.dispose);

      final List<LocationHealth> seen = <LocationHealth>[];
      final StreamSubscription<LocationHealth> sub =
          mock.healthState().listen(seen.add);
      addTearDown(sub.cancel);
      await settle();

      mock.simulatedHealth = LocationHealth.serviceDisabled;
      await settle();

      expect(seen, <LocationHealth>[
        LocationHealth.ok,
        LocationHealth.serviceDisabled,
      ]);
    });
  });
}
