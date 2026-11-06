import 'dart:async';

import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/services/location_queue_store.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';

/// In-memory stand-in for the durable queue, so these tests never bind the
/// `shared_preferences` plugin. It is the whole persistence contract the
/// service depends on.
class _FakeQueueStore implements LocationQueueStore {
  _FakeQueueStore([this.data = const LocationQueueData()]);

  LocationQueueData data;
  int saves = 0;
  int clears = 0;

  @override
  Future<LocationQueueData> load() async => data;

  @override
  Future<void> save(LocationQueueData d) async {
    data = d;
    saves++;
  }

  @override
  Future<void> clear() async {
    data = const LocationQueueData();
    clears++;
  }
}

/// A fake platform that feeds positions into the one stream the service opens,
/// so `_onPosition` can be driven without a device.
class _FakeGeolocator extends GeolocatorPlatform {
  final StreamController<Position> controller =
      StreamController<Position>.broadcast();

  @override
  Future<LocationPermission> checkPermission() async =>
      LocationPermission.whileInUse;

  @override
  Future<bool> isLocationServiceEnabled() async => true;

  @override
  Stream<Position> getPositionStream({LocationSettings? locationSettings}) =>
      controller.stream;
}

Position _pos({required double accuracy, DateTime? at, double lat = 23.24, double lng = 87.86}) =>
    Position(
      latitude: lat,
      longitude: lng,
      timestamp: at ?? DateTime.now(),
      accuracy: accuracy,
      altitude: 0,
      altitudeAccuracy: 0,
      heading: 0,
      headingAccuracy: 0,
      speed: 0,
      speedAccuracy: 0,
    );

LocationLog _fix(
  String id, {
  String session = 'ses_1',
  double accuracy = 8,
  bool lowAccuracy = false,
}) =>
    LocationLog(
      id: id,
      sessionId: session,
      latitude: 23.24,
      longitude: 87.86,
      accuracy: accuracy,
      speedKmh: 0,
      recordedAt: DateTime.utc(2026, 1, 1, 10),
      syncState: SyncState.queued,
      lowAccuracy: lowAccuracy,
    );

void main() {
  // Let microtasks + the short persist debounce run.
  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 40));

  const Duration debounce = Duration(milliseconds: 10);

  group('on-disk shape round-trips', () {
    test('a LocationLog survives JSON, low-accuracy flag and all', () {
      final LocationLog original = _fix('loc_1', accuracy: 120, lowAccuracy: true);
      final LocationLog back = LocationLog.fromJson(original.toJson());

      expect(back.id, original.id);
      expect(back.sessionId, original.sessionId);
      expect(back.latitude, original.latitude);
      expect(back.longitude, original.longitude);
      expect(back.accuracy, original.accuracy);
      expect(back.speedKmh, original.speedKmh);
      // The *instant* round-trips exactly; the UTC flag deliberately does not.
      // `toJson` writes `.toUtc()`, and `fromJson` now brings the value back
      // with `.toLocal()` so the UI cannot render a UTC wall clock by accident
      // (an 08:35 IST session start displayed as "03:05"). `isAtSameMomentAs`
      // is the right comparison here because `DateTime.==` also compares
      // `isUtc`, so it would fail on two values that denote the same moment.
      expect(back.recordedAt.isAtSameMomentAs(original.recordedAt), isTrue);
      expect(back.recordedAt.isUtc, isFalse);
      expect(back.syncState, original.syncState);
      expect(back.lowAccuracy, isTrue);
    });

    test('LocationQueueData carries fixes, failed count and lastSyncedAt', () {
      final DateTime synced = DateTime.utc(2026, 1, 1, 9);
      final LocationQueueData data = LocationQueueData(
        fixes: <LocationLog>[_fix('a'), _fix('b', lowAccuracy: true)],
        failed: 3,
        lastSyncedAt: synced,
      );
      final LocationQueueData back = LocationQueueData.fromJson(data.toJson());

      expect(back.fixes.map((LocationLog f) => f.id), <String>['a', 'b']);
      expect(back.fixes[1].lowAccuracy, isTrue);
      expect(back.failed, 3);
      expect(back.lastSyncedAt, synced);
    });
  });

  group('offline queue persistence', () {
    test('a restored queue resumes draining after a cold start', () async {
      // The store already holds what a previous, killed process had queued.
      final _FakeQueueStore store = _FakeQueueStore(
        LocationQueueData(
          fixes: <LocationLog>[
            _fix('loc_a'),
            _fix('loc_b', accuracy: 140, lowAccuracy: true),
            _fix('loc_c'),
          ],
          failed: 2,
        ),
      );

      final List<LocationLog> uploaded = <LocationLog>[];
      Future<UploadOutcome> uploader(
        String sessionId,
        List<LocationLog> batch,
      ) async {
        uploaded.addAll(batch);
        return UploadOutcome(batch.map((LocationLog f) => f.id).toSet());
      }

      final GeoLocationService service = GeoLocationService(
        queueStore: store,
        uploader: uploader,
        persistDebounce: debounce,
      );

      // Construction kicks off restore, which drains because an uploader is set.
      await settle();

      expect(
        uploaded.map((LocationLog f) => f.id),
        <String>['loc_a', 'loc_b', 'loc_c'],
        reason: 'every persisted fix reaches the server after a cold start',
      );
      expect(
        uploaded.firstWhere((LocationLog f) => f.id == 'loc_b').lowAccuracy,
        isTrue,
        reason: 'the low-accuracy flag survives the disk round-trip',
      );
      expect(service.queuedCount, 0);
      expect(store.data.fixes, isEmpty,
          reason: 'the drained queue is mirrored back to disk');

      await service.dispose();
    });

    test('fixes captured live are written to disk, then survive a restart',
        () async {
      final _FakeGeolocator platform = _FakeGeolocator();
      GeolocatorPlatform.instance = platform;
      final _FakeQueueStore store = _FakeQueueStore();

      // First run: capture a few fixes with no uploader, so they stay queued.
      final GeoLocationService run1 = GeoLocationService(
        queueStore: store,
        persistDebounce: debounce,
      );
      await settle(); // let restore (empty) finish and arm writes.
      await run1.startTracking(sessionId: 'ses_1');

      platform.controller.add(_pos(accuracy: 10));
      platform.controller.add(_pos(accuracy: 140)); // low-accuracy
      await settle();

      expect(store.data.fixes, hasLength(2),
          reason: 'both fixes were debounced to disk');
      expect(store.data.fixes[1].lowAccuracy, isTrue);

      await run1.dispose();
      await platform.controller.close();

      // Second run (a fresh process) against the same store, now with a wire.
      final List<LocationLog> uploaded = <LocationLog>[];
      final GeoLocationService run2 = GeoLocationService(
        queueStore: store,
        uploader: (String s, List<LocationLog> b) async {
          uploaded.addAll(b);
          return UploadOutcome(b.map((LocationLog f) => f.id).toSet());
        },
        persistDebounce: debounce,
      );
      await settle();

      expect(uploaded, hasLength(2),
          reason: 'the cold start reloads and drains what was persisted');
      expect(run2.queuedCount, 0);

      await run2.dispose();
    });

    test('a deleted account wipes the durable store', () async {
      final _FakeQueueStore store = _FakeQueueStore(
        LocationQueueData(fixes: <LocationLog>[_fix('loc_a'), _fix('loc_b')]),
      );
      final GeoLocationService service = GeoLocationService(
        queueStore: store,
        persistDebounce: debounce,
      );
      await settle();
      expect(service.queuedCount, 2);

      service.discardDeletedAccountQueue();
      await settle();

      expect(service.queuedCount, 0);
      expect(store.clears, greaterThanOrEqualTo(1));
      expect(store.data.fixes, isEmpty);
    });
  });

  group('low-accuracy fixes are kept, not discarded', () {
    late _FakeGeolocator platform;
    late _FakeQueueStore store;
    late GeoLocationService service;

    setUp(() async {
      platform = _FakeGeolocator();
      GeolocatorPlatform.instance = platform;
      store = _FakeQueueStore();
      service = GeoLocationService(
        queueStore: store,
        config: TrackingConfig.defaults, // minAccuracyMetres = 50
        persistDebounce: debounce,
      );
      await settle();
      await service.startTracking(sessionId: 'ses_1');
    });

    tearDown(() async {
      await service.dispose();
      await platform.controller.close();
    });

    test('a weak fix is queued and flagged instead of dropped', () async {
      final List<LocationLog> seen = <LocationLog>[];
      final StreamSubscription<LocationLog> sub =
          service.track(sessionId: 'ses_1').listen(seen.add);

      platform.controller.add(_pos(accuracy: 180)); // worse than 50 m
      await settle();

      expect(service.queuedCount, 1,
          reason: 'the weak fix is no longer thrown away');
      expect(seen, hasLength(1));
      expect(seen.single.lowAccuracy, isTrue);

      // And it stays a property of the fix. Weak GPS is not a health state
      // any more: the session is working, so health reads ok and the employee
      // is told nothing.
      expect(await service.health(), LocationHealth.ok,
          reason: 'the flag on the fix is the whole record of weak GPS');

      await sub.cancel();
    });

    test('accuracy never becomes a health state, weak or good', () async {
      platform.controller.add(_pos(accuracy: 160)); // worse than 50 m
      await settle();
      expect(await service.health(), LocationHealth.ok,
          reason: 'a weak fix is a working session, not an outage');

      platform.controller.add(_pos(accuracy: 9)); // confident fix
      await settle();

      expect(service.queuedCount, 2);
      expect(await service.health(), LocationHealth.ok);
      expect(store.data.fixes.last.lowAccuracy, isFalse);
    });

    test('a run of weak fixes still reports ok', () async {
      // The old behaviour flipped to poorAccuracy once bad fixes landed inside
      // three intervals. Nothing may resurrect that: a stretch of rough GPS is
      // an admin-side map detail, never an employee-facing status.
      for (int i = 0; i < 5; i++) {
        platform.controller.add(_pos(accuracy: 200));
        await settle();
      }

      expect(await service.health(), LocationHealth.ok);
      expect(service.queuedCount, 5, reason: 'every weak fix is still kept');
    });
  });
}
