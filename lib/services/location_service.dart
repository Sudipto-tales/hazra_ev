import 'dart:async';
import 'dart:collection';
import 'dart:math';

import 'package:flutter/foundation.dart';
// `ActivityType` is also a model in this app (activity_event.dart); the
// geolocator/iOS one is not used here, so it is hidden to keep the import
// unambiguous.
import 'package:geolocator/geolocator.dart' hide ActivityType;

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

/// Uploads one batch of queued fixes for one session.
///
/// [sessionId] is the device-side session id every fix in [batch] carries; the
/// uploader is responsible for translating it to whatever id the server knows
/// the session by, because this class only ever sees the device's own.
///
/// Returns **the ids the server has settled** — accepted or permanently
/// rejected. Those, and only those, leave the queue; anything unmentioned was
/// not received and is sent again. Returning an empty set is a legitimate
/// answer meaning "nothing could be settled yet" (typically the session is not
/// open server-side), and leaves the queue untouched without claiming the
/// device is offline.
///
/// Throwing is how a network failure is reported, and is the only evidence
/// this class has that the device is offline.
///
/// The real implementation is `POST /tracking/locations` via
/// `TrackingRepository` — it belongs in the repository layer, not here,
/// because nothing under `lib/services/` is allowed to talk HTTP.
typedef LocationUploader = Future<Set<String>> Function(
  String sessionId,
  List<LocationLog> batch,
);

/// Real device GPS, backed by the `geolocator` plugin.
///
/// This is the implementation used whenever the app runs against the live API;
/// [MockLocationService] stays behind `USE_MOCKS=true` and the tests.
///
/// Scope, stated plainly so nothing here reads as more than it is:
///
/// * Tracking is **foreground / while-in-use**. On Android the position stream
///   runs as a foreground service (persistent notification) so a session keeps
///   recording with the screen off, but the app does not request
///   `ACCESS_BACKGROUND_LOCATION` and does not resurrect itself after the
///   process is killed. [requestPermission] therefore treats "while in use" as
///   sufficient and never reports [LocationHealth.backgroundDenied].
/// * The offline queue below is real — it holds the actual fixes captured on
///   this device. It drains through the [LocationUploader] attached with
///   [attachUploader] (`main.dart` wires it to `POST /tracking/locations` via
///   `TrackingRepository`). With no uploader attached — the `USE_MOCKS` build,
///   or a test — [flushQueue] reports the queue as it truly is instead of
///   pretending it drained.
/// * The queue is in memory only. A dropped network loses nothing; the process
///   being killed does. Persisting it is the next step, and it belongs here
///   rather than in the controller.
///
/// Platform declarations: Android is done (see
/// `android/app/src/main/AndroidManifest.xml`). This repo has no `ios/` folder
/// yet — when one is generated, `ios/Runner/Info.plist` needs:
///
/// * `NSLocationWhenInUseUsageDescription` — "Your location is recorded while
///   a work session is open, so your route, stops and customer visits appear
///   on your day's record."
/// * `NSLocationAlwaysAndWhenInUseUsageDescription` — "Allow location while a
///   work session is open so your route keeps recording when the app is in the
///   background or the screen is off. Nothing is recorded once you end the
///   session."
class GeoLocationService implements LocationService {
  GeoLocationService({
    this.config = TrackingConfig.defaults,
    LocationUploader? uploader,
    this.queueCapacity = 5000,
  }) : _uploader = uploader;

  final TrackingConfig config;

  /// Hard ceiling on the in-memory queue so a long offline stretch cannot grow
  /// without bound. At the default 30 s interval this is ~41 hours of fixes.
  /// Overflow drops the oldest fix and is reported as `failed`, because a fix
  /// dropped here is a fix that will never reach the server.
  final int queueCapacity;

  LocationUploader? _uploader;

  final Queue<LocationLog> _queue = Queue<LocationLog>();
  final StreamController<LocationLog> _fixes =
      StreamController<LocationLog>.broadcast();
  final StreamController<SyncSnapshot> _sync =
      StreamController<SyncSnapshot>.broadcast();

  StreamSubscription<Position>? _positions;
  Timer? _retry;
  Timer? _drain;

  String? _sessionId;
  int _seq = 0;
  int _failed = 0;
  bool _online = true;
  bool _flushing = false;
  DateTime? _lastSyncedAt;

  LocationLog? _lastAccepted;

  /// When a fix was last thrown away for being less accurate than
  /// [TrackingConfig.minAccuracyMetres]. Drives [LocationHealth.poorAccuracy],
  /// so that state is only ever reported off real rejected fixes.
  DateTime? _lastAccuracyReject;

  /// Point the queue at `POST /tracking/locations`. Feature code keeps talking
  /// to [LocationService]; only the composition root knows this exists.
  void attachUploader(LocationUploader uploader) {
    _uploader = uploader;
    // Anything captured before the wiring landed is still owed to the server.
    if (_queue.isNotEmpty) unawaited(flushQueue());
  }

  /// Real backlog size, for callers that want it without listening to
  /// [syncState].
  int get queuedCount => _queue.length;

  // ------------------------------------------------------------------ health

  @override
  Future<LocationHealth> health() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return LocationHealth.serviceDisabled;
      }
      final LocationHealth? denied =
          _denialFor(await Geolocator.checkPermission());
      if (denied != null) return denied;
      // Only reported when an upload actually failed — this class has no
      // connectivity plugin and will not guess at the network.
      if (!_online) return LocationHealth.noInternet;
      if (_accuracyIsPoor) return LocationHealth.poorAccuracy;
      return LocationHealth.ok;
    } catch (e) {
      // A dead platform channel is indistinguishable, from up here, from a
      // device with location switched off. Never let it escape as an
      // unhandled error — the UI has a dialog for serviceDisabled.
      debugPrint('[location] health check failed: $e');
      return LocationHealth.serviceDisabled;
    }
  }

  @override
  Future<LocationHealth> requestPermission({bool background = false}) async {
    // [background] is accepted for interface compatibility. Always-on tracking
    // is not implemented (no ACCESS_BACKGROUND_LOCATION is declared), so the
    // flag cannot change what is asked for; foreground permission is what the
    // foreground-service stream needs.
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.unableToDetermine) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.deniedForever) {
        // The OS will not show the prompt again, so the settings page is the
        // only route left — "Enable" has to lead there or it does nothing.
        await Geolocator.openAppSettings();
        return LocationHealth.permissionDeniedForever;
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.unableToDetermine) {
        return LocationHealth.permissionDenied;
      }

      if (!await Geolocator.isLocationServiceEnabled()) {
        await Geolocator.openLocationSettings();
        return LocationHealth.serviceDisabled;
      }

      return health();
    } catch (e) {
      debugPrint('[location] permission request failed: $e');
      return LocationHealth.permissionDenied;
    }
  }

  LocationHealth? _denialFor(LocationPermission permission) =>
      switch (permission) {
        LocationPermission.denied ||
        LocationPermission.unableToDetermine =>
          LocationHealth.permissionDenied,
        LocationPermission.deniedForever =>
          LocationHealth.permissionDeniedForever,
        // While-in-use is enough for foreground-service tracking, so it is not
        // reported as backgroundDenied — that would block Start Day over a
        // capability this build does not use.
        LocationPermission.whileInUse || LocationPermission.always => null,
      };

  bool get _accuracyIsPoor {
    final DateTime? rejected = _lastAccuracyReject;
    if (rejected == null) return false;
    // One bad fix is weather; bad fixes inside the last few intervals are a
    // signal worth showing.
    return DateTime.now().difference(rejected) <=
        Duration(seconds: config.locationIntervalSeconds * 3);
  }

  // ------------------------------------------------------------------- fixes

  @override
  Future<LocationLog?> currentFix({required String sessionId}) async {
    if ((await health()).blocksStart) return null;
    try {
      final Position position = await Geolocator.getCurrentPosition(
        locationSettings: _settings(
          timeLimit: const Duration(seconds: 25),
          stream: false,
        ),
      );
      // Deliberately not accuracy-filtered: a weak start fix is still where
      // the employee is, and health() already reports poorAccuracy (which does
      // not block the day) rather than refusing to open a session at all.
      if (position.accuracy > config.minAccuracyMetres) {
        _lastAccuracyReject = DateTime.now();
      }
      return _toLog(position, sessionId);
    } catch (e) {
      debugPrint('[location] currentFix failed: $e');
      // One fallback, to the cached fix, and only while it is fresh enough to
      // still describe where the phone is. Its own timestamp is kept, so the
      // session is never stamped with a time that did not happen.
      try {
        final Position? last = await Geolocator.getLastKnownPosition();
        if (last == null) return null;
        if (DateTime.now().difference(last.timestamp) >
            const Duration(minutes: 2)) {
          return null;
        }
        return _toLog(last, sessionId);
      } catch (_) {
        return null;
      }
    }
  }

  /// Fixes for [sessionId], off the single underlying position stream that
  /// [startTracking] opens. Yields nothing until tracking has been started.
  @override
  Stream<LocationLog> track({required String sessionId}) =>
      _fixes.stream.where((LocationLog fix) => fix.sessionId == sessionId);

  @override
  Future<void> startTracking({required String sessionId}) async {
    await stopTracking();
    _sessionId = sessionId;
    _emitSync();
    _subscribe();

    // A steady drain matters more than a big one: the admin's live map is only
    // as current as the last batch that landed, and a session that uploads
    // every half minute survives a mid-day crash with almost nothing lost.
    // Batches also go up on their own once the queue reaches syncBatchSize.
    _drain = Timer.periodic(
      Duration(seconds: max(30, config.locationIntervalSeconds)),
      (_) => unawaited(flushQueue()),
    );
  }

  /// Stops collecting. The queue is deliberately **not** cleared: fixes from a
  /// closed session still have to reach the server, and the session close is
  /// what tells the server they were the last of it.
  @override
  Future<void> stopTracking() async {
    _retry?.cancel();
    _retry = null;
    _drain?.cancel();
    _drain = null;
    await _positions?.cancel();
    _positions = null;
    _sessionId = null;
    unawaited(flushQueue());
  }

  void _subscribe() {
    if (_sessionId == null) return;
    try {
      _positions = Geolocator.getPositionStream(
        locationSettings: _settings(stream: true),
      ).listen(
        _onPosition,
        onError: _onStreamError,
        cancelOnError: false,
      );
    } catch (e) {
      _onStreamError(e);
    }
  }

  void _onStreamError(Object error) {
    // Permission revoked mid-session, location switched off, provider crash.
    // None of these may surface as an unhandled error: the controller polls
    // health() every 15 s and reads the real cause straight from the OS.
    debugPrint('[location] position stream error: $error');
    _positions?.cancel();
    _positions = null;
    _retry?.cancel();
    // Re-arm instead of giving up — the user may switch location back on
    // without ever returning to the app.
    _retry = Timer(
      Duration(seconds: config.locationIntervalSeconds.clamp(5, 60)),
      () {
        if (_sessionId != null && _positions == null) _subscribe();
      },
    );
  }

  void _onPosition(Position position) {
    final String? sessionId = _sessionId;
    if (sessionId == null) return;

    if (position.accuracy > config.minAccuracyMetres) {
      // TrackingConfig says fixes worse than this are dropped. Dropped means
      // dropped: not queued, not emitted, not counted as a location point.
      _lastAccuracyReject = DateTime.now();
      return;
    }

    final LocationLog fix = _toLog(position, sessionId);

    final LocationLog? previous = _lastAccepted;
    if (previous != null && _isImplausibleJump(previous, fix)) {
      debugPrint('[location] dropped implausible jump');
      return;
    }

    _lastAccuracyReject = null;
    _lastAccepted = fix;
    _enqueue(fix);
    if (!_fixes.isClosed) _fixes.add(fix);
    _emitSync();

    if (_uploader != null && _queue.length >= config.syncBatchSize) {
      unawaited(flushQueue());
    }
  }

  bool _isImplausibleJump(LocationLog from, LocationLog to) {
    final double seconds =
        to.recordedAt.difference(from.recordedAt).inMilliseconds / 1000;
    if (seconds <= 0) return false;
    final double metres = Geolocator.distanceBetween(
      from.latitude,
      from.longitude,
      to.latitude,
      to.longitude,
    );
    return metres / seconds * 3.6 > config.maxJumpKmh;
  }

  LocationLog _toLog(Position position, String sessionId) {
    return LocationLog(
      // Device-local id. The server assigns the real one when the fix syncs.
      id: 'loc_local_${position.timestamp.millisecondsSinceEpoch}_${_seq++}',
      sessionId: sessionId,
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      speedKmh: _speedKmh(position),
      recordedAt: position.timestamp,
      // Nothing is synced until an uploader confirms it, so this is the only
      // truthful state for a fresh fix.
      syncState: SyncState.queued,
    );
  }

  /// Some devices report a negative or absent speed. Falling back to the
  /// distance between the last two real fixes keeps the value measured rather
  /// than invented; with nothing to measure against it stays 0.
  double _speedKmh(Position position) {
    if (position.speed.isFinite && position.speed >= 0) {
      return position.speed * 3.6;
    }
    final LocationLog? previous = _lastAccepted;
    if (previous == null) return 0;
    final double seconds =
        position.timestamp.difference(previous.recordedAt).inMilliseconds /
            1000;
    if (seconds <= 0) return 0;
    final double metres = Geolocator.distanceBetween(
      previous.latitude,
      previous.longitude,
      position.latitude,
      position.longitude,
    );
    return metres / seconds * 3.6;
  }

  /// Accuracy and cadence come from [TrackingConfig], the same source the mock
  /// reads, so changing the rule changes both implementations.
  LocationSettings _settings({Duration? timeLimit, required bool stream}) {
    const LocationAccuracy accuracy = LocationAccuracy.high;
    if (defaultTargetPlatform == TargetPlatform.android) {
      return AndroidSettings(
        accuracy: accuracy,
        intervalDuration: Duration(seconds: config.locationIntervalSeconds),
        timeLimit: timeLimit,
        // Only the session stream runs as a foreground service — a one-shot
        // fix has no business posting a persistent notification.
        foregroundNotificationConfig: stream
            ? const ForegroundNotificationConfig(
                notificationTitle: 'Work session running',
                notificationText:
                    'Your route is being recorded until you end the session.',
                notificationChannelName: 'Work session tracking',
                enableWakeLock: true,
                setOngoing: true,
              )
            : null,
      );
    }
    if (defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS) {
      return AppleSettings(
        accuracy: accuracy,
        timeLimit: timeLimit,
        pauseLocationUpdatesAutomatically: false,
        // No always-on permission is requested, so this stays false: it would
        // advertise a capability the app has not declared.
        showBackgroundLocationIndicator: false,
      );
    }
    return LocationSettings(accuracy: accuracy, timeLimit: timeLimit);
  }

  // ----------------------------------------------------------- offline queue

  void _enqueue(LocationLog fix) {
    if (_queue.length >= queueCapacity) {
      _queue.removeFirst();
      // Counted as failed because it is: that fix will never reach the server.
      _failed++;
    }
    _queue.add(fix);
  }

  @override
  Stream<SyncSnapshot> syncState() async* {
    // New listeners get the queue as it stands before any further change, so
    // the status sheet is never blank waiting for the next fix.
    yield _snapshot();
    yield* _sync.stream;
  }

  /// Drains the queue, oldest first, one session at a time.
  ///
  /// The split by session is not cosmetic: `POST /tracking/locations` takes a
  /// single `sessionId` for the whole batch, and a day has several sessions —
  /// a pause that happens before the queue drains leaves fixes from two of
  /// them side by side.
  @override
  Future<void> flushQueue() async {
    final LocationUploader? uploader = _uploader;
    if (uploader == null) {
      // No uploader attached: the mock build, or a test. Re-emitting the queue
      // unchanged is the honest answer — clearing it here would claim a sync
      // of data that never left the phone.
      _emitSync();
      return;
    }
    if (_flushing || _queue.isEmpty) {
      _emitSync();
      return;
    }

    _flushing = true;
    try {
      // Sessions that answered "nothing settled" are parked for this round.
      // Without this the loop would re-send the same undeliverable batch until
      // the network finally failed for a different reason.
      final Set<String> parked = <String>{};

      while (true) {
        final List<LocationLog> batch = _nextBatch(parked);
        if (batch.isEmpty) break;

        final String sessionId = batch.first.sessionId;
        final Set<String> settled;

        try {
          settled = await uploader(sessionId, batch);
        } catch (e) {
          // The one thing a failed upload proves: this device cannot reach the
          // server right now. Everything stays queued.
          debugPrint('[location] flush failed: $e');
          _online = false;
          _emitSync();
          return;
        }

        // Reaching the server at all is what "online" means here, whether or
        // not it had anything to settle.
        _online = true;

        if (settled.isEmpty) {
          parked.add(sessionId);
          _emitSync();
          continue;
        }

        _queue.removeWhere((LocationLog fix) => settled.contains(fix.id));
        _lastSyncedAt = DateTime.now();
        _emitSync();
      }
    } finally {
      _flushing = false;
    }
  }

  /// Oldest fix that is not parked, plus every later fix of the same session,
  /// up to `syncBatchSize` — the server refuses a bigger batch outright.
  List<LocationLog> _nextBatch(Set<String> parked) {
    String? sessionId;
    final List<LocationLog> batch = <LocationLog>[];

    for (final LocationLog fix in _queue) {
      if (parked.contains(fix.sessionId)) continue;
      sessionId ??= fix.sessionId;
      if (fix.sessionId != sessionId) continue;
      batch.add(fix);
      if (batch.length >= config.syncBatchSize) break;
    }

    return batch;
  }

  SyncSnapshot _snapshot() => SyncSnapshot(
        queued: _queue.length,
        failed: _failed,
        lastSyncedAt: _lastSyncedAt,
        isOnline: _online,
      );

  void _emitSync() {
    if (_sync.isClosed) return;
    _sync.add(_snapshot());
  }

  Future<void> dispose() async {
    await stopTracking();
    await _fixes.close();
    await _sync.close();
  }
}
