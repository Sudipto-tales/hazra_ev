import 'dart:async';
import 'dart:math';

import '../core/config/tracking_config.dart';
import '../data/models/models.dart';

/// Device-side location collection, kept fully independent of the UI.
///
/// The production implementation wraps the existing geolocation plugin +
/// background service. Nothing above this interface should ever touch the
/// plugin directly, so the platform layer can be replaced in one file.
abstract class LocationService {
  /// Device location service + permission + accuracy health.
  Future<LocationHealth> health();

  /// Ask the OS for foreground (and, if [background], always-on) permission.
  Future<LocationHealth> requestPermission({bool background = false});

  /// Single fix used to stamp session start/end.
  Future<LocationLog?> currentFix({required String sessionId});

  /// Continuous fixes while a session is open.
  Stream<LocationLog> track({required String sessionId});

  Future<void> startTracking({required String sessionId});

  Future<void> stopTracking();

  /// Offline queue state for the tracking status sheet.
  Stream<SyncSnapshot> syncState();

  /// Force-flush the queued fixes.
  Future<void> flushQueue();
}

/// Simulated implementation for the static-data design build.
///
/// Emits a plausible fix every [TrackingConfig.locationIntervalSeconds]
/// (accelerated to a few seconds so the UI visibly updates) and drains a fake
/// offline queue.
class MockLocationService implements LocationService {
  MockLocationService({
    this.config = TrackingConfig.defaults,
    this.emitInterval = const Duration(seconds: 5),
  });

  final TrackingConfig config;
  final Duration emitInterval;

  /// Flip from the Settings screen to exercise the blocked-start dialogs.
  LocationHealth simulatedHealth = LocationHealth.ok;

  final Random _rnd = Random(99);
  final StreamController<SyncSnapshot> _sync =
      StreamController<SyncSnapshot>.broadcast();

  Timer? _timer;
  int _seq = 0;
  int _queued = 0;
  bool _online = true;
  DateTime? _lastSyncedAt = DateTime.now();

  double _lat = 23.2405;
  double _lng = 87.8600;

  @override
  Future<LocationHealth> health() async {
    await Future<void>.delayed(const Duration(milliseconds: 250));
    return simulatedHealth;
  }

  @override
  Future<LocationHealth> requestPermission({bool background = false}) async {
    await Future<void>.delayed(const Duration(milliseconds: 400));
    if (simulatedHealth == LocationHealth.permissionDenied) {
      simulatedHealth = LocationHealth.ok;
    }
    return simulatedHealth;
  }

  @override
  Future<LocationLog?> currentFix({required String sessionId}) async {
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (simulatedHealth.blocksStart) return null;
    return _makeFix(sessionId);
  }

  @override
  Stream<LocationLog> track({required String sessionId}) async* {
    while (true) {
      await Future<void>.delayed(emitInterval);
      yield _makeFix(sessionId);
    }
  }

  @override
  Future<void> startTracking({required String sessionId}) async {
    _timer?.cancel();
    _timer = Timer.periodic(emitInterval, (_) {
      // Simulate an occasional offline window so the queue is visible.
      _online = _rnd.nextInt(10) != 0;
      if (_online) {
        if (_queued > 0) _queued = (_queued - 3).clamp(0, 999).toInt();
        _lastSyncedAt = DateTime.now();
      } else {
        _queued += 1;
      }
      _sync.add(
        SyncSnapshot(
          queued: _queued,
          failed: 0,
          lastSyncedAt: _lastSyncedAt,
          isOnline: _online,
        ),
      );
    });
  }

  @override
  Future<void> stopTracking() async {
    _timer?.cancel();
    _timer = null;
  }

  @override
  Stream<SyncSnapshot> syncState() => _sync.stream;

  @override
  Future<void> flushQueue() async {
    await Future<void>.delayed(const Duration(milliseconds: 700));
    _queued = 0;
    _lastSyncedAt = DateTime.now();
    _sync.add(
      SyncSnapshot(
        queued: 0,
        failed: 0,
        lastSyncedAt: _lastSyncedAt,
        isOnline: true,
      ),
    );
  }

  LocationLog _makeFix(String sessionId) {
    _lat += (_rnd.nextDouble() - 0.5) * 0.004;
    _lng += (_rnd.nextDouble() - 0.5) * 0.004;
    return LocationLog(
      id: 'loc_${_seq++}',
      sessionId: sessionId,
      latitude: _lat,
      longitude: _lng,
      accuracy: 6 + _rnd.nextDouble() * 18,
      speedKmh: _rnd.nextDouble() * 42,
      recordedAt: DateTime.now(),
      syncState: _online ? SyncState.synced : SyncState.queued,
    );
  }

  void dispose() {
    _timer?.cancel();
    _sync.close();
  }
}
