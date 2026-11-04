import 'dart:async';

import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/services/location_queue_store.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:geolocator/geolocator.dart';

/// Live mode, device side: `liveUntil` out of a batch response changes **one**
/// thing, and the test is as much about what it does not change.
///
/// API plan §3.11: while `now < liveUntil` the drain timer runs at
/// `liveSyncIntervalSeconds` instead of `syncIntervalSeconds`; when the
/// deadline passes, or comes back null, or is absent entirely, it goes back.
/// GPS capture cadence is untouched throughout — a watch costs radio time, not
/// GPS time — so the position stream must not be recycled by any of it.

class _FakeQueueStore implements LocationQueueStore {
  LocationQueueData data = const LocationQueueData();

  @override
  Future<LocationQueueData> load() async => data;

  @override
  Future<void> save(LocationQueueData d) async => data = d;

  @override
  Future<void> clear() async => data = const LocationQueueData();
}

class _FakeGeolocator extends GeolocatorPlatform {
  final StreamController<Position> controller =
      StreamController<Position>.broadcast();

  /// Every (re)subscription to the position stream. The whole point of the
  /// live-mode change is that this number does not move when the drain cadence
  /// does.
  int streamOpens = 0;

  @override
  Future<LocationPermission> checkPermission() async =>
      LocationPermission.whileInUse;

  @override
  Future<bool> isLocationServiceEnabled() async => true;

  @override
  Stream<Position> getPositionStream({LocationSettings? locationSettings}) {
    streamOpens++;
    return controller.stream;
  }
}

Position _pos({DateTime? at}) => Position(
      latitude: 23.24,
      longitude: 87.86,
      timestamp: at ?? DateTime.now(),
      accuracy: 8,
      altitude: 0,
      altitudeAccuracy: 0,
      heading: 0,
      headingAccuracy: 0,
      speed: 0,
      speedAccuracy: 0,
    );

void main() {
  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 40));

  const TrackingConfig config = TrackingConfig(
    syncIntervalSeconds: 300,
    liveSyncIntervalSeconds: 15,
  );

  late _FakeGeolocator platform;
  late GeoLocationService service;

  /// What the next upload reports back as `liveUntil`.
  DateTime? served;
  int uploads = 0;

  setUp(() async {
    platform = _FakeGeolocator();
    GeolocatorPlatform.instance = platform;
    served = null;
    uploads = 0;

    service = GeoLocationService(
      queueStore: _FakeQueueStore(),
      config: config,
      persistDebounce: const Duration(milliseconds: 5),
      uploader: (String sessionId, List<LocationLog> batch) async {
        uploads++;
        return UploadOutcome(
          batch.map((LocationLog f) => f.id).toSet(),
          liveUntil: served,
        );
      },
    );
    await settle();
    await service.startTracking(sessionId: 'ses_1');
  });

  tearDown(() async {
    await service.dispose();
    await platform.controller.close();
  });

  /// One fix, then a drain — which is exactly how the device finds out it is
  /// being watched: in the reply to a batch it was going to send anyway.
  Future<void> postBatch() async {
    platform.controller.add(_pos());
    await settle();
    await service.flushQueue();
    await settle();
  }

  test('the drain starts on syncIntervalSeconds and nothing else', () {
    expect(service.drainInterval, const Duration(seconds: 300));
    expect(service.isLive, isFalse);
  });

  test('a liveUntil in the future drops the drain to liveSyncIntervalSeconds',
      () async {
    served = DateTime.now().add(const Duration(minutes: 2));
    final int armedBefore = service.drainArmCount;

    await postBatch();

    expect(uploads, 1, reason: 'the deadline rides a batch, not a new call');
    expect(service.isLive, isTrue);
    expect(service.drainInterval, const Duration(seconds: 15));
    expect(service.drainArmCount, greaterThan(armedBefore),
        reason: 'the timer is re-armed, not merely recorded');
  });

  test('a watch costs radio time, not GPS time', () async {
    final int opensBefore = platform.streamOpens;
    served = DateTime.now().add(const Duration(minutes: 2));
    await postBatch();

    expect(service.drainInterval, const Duration(seconds: 15));
    expect(platform.streamOpens, opensBefore,
        reason: 'entering live mode must not resubscribe the position stream');
    expect(service.config.locationIntervalSeconds,
        config.locationIntervalSeconds);
    expect(service.config.distanceFilterMetres, config.distanceFilterMetres);
  });

  test('liveUntil coming back null restores the slow cadence', () async {
    served = DateTime.now().add(const Duration(minutes: 2));
    await postBatch();
    expect(service.drainInterval, const Duration(seconds: 15));

    // Nobody is watching any more. No ramp, no hysteresis — the TTL was the
    // hysteresis.
    served = null;
    await postBatch();

    expect(service.isLive, isFalse);
    expect(service.drainInterval, const Duration(seconds: 300));
  });

  test('an absent liveUntil is treated exactly like a null one', () async {
    served = DateTime.now().add(const Duration(minutes: 2));
    await postBatch();
    expect(service.drainInterval, const Duration(seconds: 15));

    // A v7 server does not send the key at all. `UploadOutcome` with no
    // liveUntil argument is that case, and it must land on the same branch.
    service.applyLiveWindow(const UploadOutcome(<String>{}).liveUntil);

    expect(service.isLive, isFalse);
    expect(service.drainInterval, const Duration(seconds: 300));
  });

  test('a deadline that has already lapsed is not live', () async {
    served = DateTime.now().subtract(const Duration(seconds: 1));
    await postBatch();

    expect(service.isLive, isFalse);
    expect(service.drainInterval, const Duration(seconds: 300));
  });

  test('live mode lapsing restores the cadence without a new response',
      () async {
    // Granted for a moment, so it expires while the map is still open and the
    // server never mentions it again.
    served = DateTime.now().add(const Duration(milliseconds: 300));
    await postBatch();
    expect(service.drainInterval, const Duration(seconds: 15));

    await Future<void>.delayed(const Duration(milliseconds: 400));
    // The drain tick reconciles before flushing; force one rather than waiting
    // 15 real seconds for the timer.
    service.applyLiveWindow(served);

    expect(service.isLive, isFalse);
    expect(service.drainInterval, const Duration(seconds: 300));
  });

  test('liveSyncIntervalSeconds can never exceed the normal interval', () async {
    // A server that clamps badly, or an admin config read before the clamp
    // landed. The timer defends itself: live mode must be faster or it is not
    // live, and a 900 s "live" drain would be a silent regression.
    service.updateConfig(
      const TrackingConfig(
        syncIntervalSeconds: 300,
        liveSyncIntervalSeconds: 900,
      ),
    );
    served = DateTime.now().add(const Duration(minutes: 2));
    await postBatch();

    expect(service.drainInterval, const Duration(seconds: 300));
  });
}
