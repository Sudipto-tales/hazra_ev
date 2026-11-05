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
import 'location_queue_store.dart';

/// Device-side location collection, kept fully independent of the UI.
///
/// The production implementation wraps the existing geolocation plugin +
/// background service. Nothing above this interface should ever touch the
/// plugin directly, so the platform layer can be replaced in one file.
abstract class LocationService {
  /// Adopt a new [TrackingConfig] at runtime. The composition root builds the
  /// service before sign-in — and therefore before the server's config is
  /// known — so the controller pushes the fetched config down here once it has
  /// it. Interval and accuracy are re-read by the position stream each time it
  /// (re)subscribes, so a config applied just before [startTracking] governs
  /// that session.
  void updateConfig(TrackingConfig config);

  /// Device location service + permission + accuracy health.
  Future<LocationHealth> health();

  /// [health] pushed as it changes, rather than polled.
  ///
  /// The GPS master switch is the one input that can change at any moment,
  /// from outside the app, with no warning — and the controller's 15 s poll
  /// only runs while a session is open, so switching location off while idle
  /// or on break went unnoticed until the next Start Day tap.
  ///
  /// The production implementation drives this from
  /// `Geolocator.getServiceStatusStream()`, which fires the instant the toggle
  /// moves. A runtime permission revoked from the settings app has no
  /// equivalent stream on Android, so [TrackingController] still polls as the
  /// backstop; this makes the common case immediate rather than up to a poll
  /// interval late.
  ///
  /// Emits the current value on subscribe, then every change.
  Stream<LocationHealth> healthState();

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

  /// The device's **single** moving/stationary truth, pushed as it changes.
  ///
  /// This state is computed here and nowhere else. The implementation already
  /// has to decide moving-vs-stationary to pick a GPS cadence, and that
  /// decision is debounced against the speed threshold so a fix or two at the
  /// boundary cannot thrash it. Publishing the same value is what keeps the
  /// employee's activity chip and the radio's duty cycle from disagreeing — a
  /// second calculation in the controller would inevitably drift from this one.
  ///
  /// Emits the current value on subscribe (so a late listener is never blank),
  /// then every change. [MovementStatus.unknown] means no session is open, or
  /// no fix has arrived yet to decide with.
  ///
  /// Deliberately **not** derived from the server: the batch upload runs on
  /// `syncIntervalSeconds` (300 s by default), so the server's idea of what the
  /// employee is doing is up to five minutes old. The phone holds the newer
  /// truth about its own movement.
  Stream<MovementStatus> movementState();

  /// Latest value of [movementState], for callers that want it without
  /// listening.
  MovementStatus get movement;

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

  TrackingConfig config;
  final Duration emitInterval;

  @override
  void updateConfig(TrackingConfig next) => config = next;

  /// Flip from Developer options to exercise the blocked-start dialogs.
  ///
  /// Assigning it also publishes on [healthState], so the simulator exercises
  /// the same push path the real service uses and not only the pull path.
  LocationHealth get simulatedHealth => _simulatedHealth;
  set simulatedHealth(LocationHealth next) {
    if (next == _simulatedHealth) return;
    _simulatedHealth = next;
    if (!_healthState.isClosed) _healthState.add(next);
  }

  LocationHealth _simulatedHealth = LocationHealth.ok;

  final Random _rnd = Random(99);
  final StreamController<SyncSnapshot> _sync =
      StreamController<SyncSnapshot>.broadcast();
  final StreamController<MovementStatus> _movementState =
      StreamController<MovementStatus>.broadcast();
  final StreamController<LocationHealth> _healthState =
      StreamController<LocationHealth>.broadcast();

  MovementStatus _movement = MovementStatus.unknown;

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
    _setMovement(MovementStatus.unknown);
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
    _setMovement(MovementStatus.unknown);
  }

  @override
  Stream<SyncSnapshot> syncState() => _sync.stream;

  @override
  MovementStatus get movement => _movement;

  /// The mock has no cadence machinery to debounce, so it reports the plain
  /// threshold comparison off the fix it just invented. Same threshold, same
  /// vocabulary as [GeoLocationService] — the design build just gets a jumpier
  /// chip, which is what a simulated trace deserves.
  @override
  Stream<MovementStatus> movementState() async* {
    yield _movement;
    yield* _movementState.stream;
  }

  void _setMovement(MovementStatus next) {
    if (next == _movement) return;
    _movement = next;
    if (!_movementState.isClosed) _movementState.add(next);
  }

  @override
  Stream<LocationHealth> healthState() async* {
    yield _simulatedHealth;
    yield* _healthState.stream;
  }

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
    final double speedKmh = _rnd.nextDouble() * 42;
    _setMovement(speedKmh < config.movementSpeedThresholdKmh
        ? MovementStatus.stationary
        : MovementStatus.moving);
    return LocationLog(
      id: 'loc_${_seq++}',
      sessionId: sessionId,
      latitude: _lat,
      longitude: _lng,
      accuracy: 6 + _rnd.nextDouble() * 18,
      speedKmh: speedKmh,
      recordedAt: DateTime.now(),
      syncState: _online ? SyncState.synced : SyncState.queued,
    );
  }

  void dispose() {
    _timer?.cancel();
    _sync.close();
    _movementState.close();
    _healthState.close();
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
typedef LocationUploader = Future<UploadOutcome> Function(
  String sessionId,
  List<LocationLog> batch,
);

/// What one batch upload came back with.
///
/// Two separate statements that happen to share a response: [settled] is about
/// the *batch* (which fixes may leave the queue), [liveUntil] is about the
/// *device* (how often it should be talking). The wire keeps them apart for
/// the same reason — `data` carries the verdict, `meta` carries the deadline
/// (API plan §3.11) — and conflating them here would be the first step to a
/// drain cadence that depends on what was in the last batch.
class UploadOutcome {
  const UploadOutcome(this.settled, {this.liveUntil});

  /// Nothing could be settled, and nothing is claimed about watching. Not an
  /// error: typically the session has not landed server-side yet.
  static const UploadOutcome nothing = UploadOutcome(<String>{});

  /// Ids the server has ruled on — accepted or permanently rejected.
  final Set<String> settled;

  /// Absolute deadline for live mode, or null when nobody is watching **and**
  /// when the server is too old to say. Absent and null are the same thing:
  /// the device is not live. A stale deadline in the past is equally harmless
  /// — it is compared against the clock, never trusted as a flag.
  final DateTime? liveUntil;
}

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
/// * The queue is **durable**. It is mirrored to disk through a
///   [LocationQueueStore] (`shared_preferences` in production) and reloaded on
///   construction, so a dropped network loses nothing and a killed process
///   resumes draining after a cold start. Writes are debounced
///   ([_persistDebounce]) so a burst of fixes or a batch drain collapses into
///   one encode + one write rather than one per fix — the JSON encode of a full
///   5000-fix queue is the only part of this that could jank, and the debounce
///   keeps it off the hot path.
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
  /// Deleted accounts must not upload queued positions or collect new ones, and
  /// nothing they captured may be left behind on disk.
  void discardDeletedAccountQueue() {
    _queue.clear();
    _failed = 0;
    // Cancel any debounced write still owing the old (now-discarded) queue,
    // then wipe the durable copy. Order matters: if the timer fired after the
    // clear it would re-persist an empty queue, which is harmless, but clearing
    // the store outright is the honest erasure a deleted account requires.
    _persistTimer?.cancel();
    _persistTimer = null;
    unawaited(_store.clear());
    _emitSync();
  }

  GeoLocationService({
    this.config = TrackingConfig.defaults,
    LocationUploader? uploader,
    this.queueCapacity = 5000,
    LocationQueueStore? queueStore,
    this.persistDebounce = const Duration(seconds: 2),
  })  : _uploader = uploader,
        _store = queueStore ?? SharedPrefsLocationQueueStore() {
    unawaited(_restore());
  }

  /// Mutable so the controller can push the server's [TrackingConfig] down once
  /// it is fetched. A running stream keeps the config it subscribed under;
  /// [_settings] reads the new values the next time it (re)subscribes — which is
  /// what makes "re-read config at each session start" take effect.
  TrackingConfig config;

  @override
  void updateConfig(TrackingConfig next) => config = next;

  /// Durable backing for the offline queue. Reloaded on construction; written
  /// on a debounce as the queue changes.
  final LocationQueueStore _store;

  /// How long writes coalesce before hitting disk. See [_schedulePersist].
  final Duration persistDebounce;

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

  /// Publishes [_stationary] — the debounced cadence mode — as the app-wide
  /// movement truth. See [LocationService.movementState].
  final StreamController<MovementStatus> _movementState =
      StreamController<MovementStatus>.broadcast();

  /// Publishes [health] as it changes. See [LocationService.healthState].
  final StreamController<LocationHealth> _healthState =
      StreamController<LocationHealth>.broadcast();

  /// Last value published on [_healthState], so an unchanged recompute does
  /// not wake every listener.
  LocationHealth? _lastHealth;

  MovementStatus _movement = MovementStatus.unknown;

  /// The GPS master switch, watched rather than polled.
  StreamSubscription<ServiceStatus>? _serviceStatus;

  StreamSubscription<Position>? _positions;
  Timer? _retry;
  Timer? _drain;
  Timer? _persistTimer;

  /// Deadline an admin's watch is live until, as last reported in the `meta`
  /// of a `POST /tracking/locations` response (§3.11). Null means nobody is
  /// watching — which is also what a server too old to send the key means, so
  /// absent and null are handled by the same branch.
  DateTime? _liveUntil;

  /// Cadence the armed [_drain] timer is actually running at. Kept as a field
  /// because a `Timer` does not report its own period, and the whole point of
  /// live mode is being able to say which of the two intervals is in force.
  Duration _drainInterval = Duration.zero;

  /// How many times [_drain] has been armed this process. Only a test reads
  /// it; it is the cheapest proof that entering live mode **re-armed** the
  /// timer rather than just recording a number.
  int _drainArmCount = 0;

  /// Forced-fix guardrail. The distance filter and the stretched stationary
  /// interval can both leave a genuinely-present employee with no streamed fix
  /// for minutes, which the server would read as Offline (>10 min) or Location
  /// Unavailable (>15 min). The heartbeat tops the queue up with one current
  /// position if nothing has been emitted for [_heartbeatInterval]; it is reset
  /// every time a real fix lands, so it only ever fires during a quiet stretch.
  /// This is not a new server call — the existing 5-min batch carries it.
  Timer? _heartbeat;

  /// Debounces moving↔stationary so a fix or two at the speed boundary does not
  /// thrash the stream between accuracy modes. The candidate state must survive
  /// [_transitionHoldWindow] before [_resubscribe] swaps the settings.
  Timer? _transitionTimer;

  /// Current effective cadence mode. Moving: high accuracy, 60s. Stationary:
  /// balanced accuracy, ~3 min. Starts moving each session.
  bool _stationary = false;

  /// Target of the in-flight debounce, or null when no transition is pending.
  bool? _pendingStationary;

  /// Whether a fix has established [_stationary] for the current session yet.
  /// Until it has, the published movement state is
  /// [MovementStatus.unknown] — `_stationary = false` at session start is a
  /// cadence default (start on high accuracy), not a claim that the employee
  /// is moving, and reporting it as one is how the chip came to lie.
  bool _movementSeeded = false;

  static const Duration _heartbeatInterval = Duration(minutes: 5);
  static const Duration _transitionHoldWindow = Duration(seconds: 90);

  /// Stationary stretches the streamed interval by this factor (60s → ~3 min).
  static const int _stationaryIntervalFactor = 3;

  /// Until the on-disk queue has been read back, no write is allowed: a save
  /// off the still-empty in-memory queue would overwrite the very fixes
  /// [_restore] is about to load. Flipped true the moment restore finishes
  /// (success or failure).
  bool _restored = false;

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

  // ------------------------------------------------------------ live mode

  /// Whether an admin is watching this device right now — i.e. whether the
  /// drain timer is on the short interval. Compared against the clock on every
  /// read, so a deadline that has quietly passed reads as false without anyone
  /// having to clear it.
  bool get isLive {
    final DateTime? until = _liveUntil;
    return until != null && DateTime.now().isBefore(until);
  }

  /// Interval the armed drain timer is running at. [Duration.zero] when no
  /// session is open and the timer is therefore down.
  @visibleForTesting
  Duration get drainInterval => _drainInterval;

  /// Times the drain timer has been armed. Test seam — see [_drainArmCount].
  @visibleForTesting
  int get drainArmCount => _drainArmCount;

  /// The production path for a `liveUntil` off a batch response, exposed so a
  /// test can drive the cadence switch without a server and without waiting
  /// out a 300-second timer.
  @visibleForTesting
  void applyLiveWindow(DateTime? liveUntil) => _applyLiveUntil(liveUntil);

  /// The cadence a drain should be running at right now.
  ///
  /// `liveSyncIntervalSeconds` is floored at 5 s and never allowed to exceed
  /// the normal interval: the server already clamps both (§3.12), but this is
  /// the timer that would spin, so it defends itself.
  Duration get _targetDrainInterval {
    if (!isLive) return Duration(seconds: config.syncIntervalSeconds);
    final int live = config.liveSyncIntervalSeconds
        .clamp(5, config.syncIntervalSeconds);
    return Duration(seconds: live);
  }

  /// Adopt the deadline the server just reported, and move the timer if that
  /// changed which interval applies.
  ///
  /// No ramp and no hysteresis — the TTL *is* the hysteresis. Absent, null and
  /// already-expired all land on the same branch, which is why the only thing
  /// stored is the deadline itself.
  void _applyLiveUntil(DateTime? liveUntil) {
    _liveUntil = liveUntil;
    _reconcileDrain();
  }

  /// Re-arm the drain timer if the interval in force is no longer the right
  /// one. Called when a response moves the deadline and again on every drain
  /// tick, so live mode lapsing restores the slow cadence within one live
  /// interval even if the server never mentions it again.
  void _reconcileDrain() {
    if (_sessionId == null || _drain == null) return;
    if (_targetDrainInterval == _drainInterval) return;
    _armDrain();
  }

  void _armDrain() {
    _drain?.cancel();
    _drainInterval = _targetDrainInterval;
    _drainArmCount++;
    _drain = Timer.periodic(_drainInterval, (_) {
      // Order matters: a lapsed deadline must put the timer back on the slow
      // interval even when the queue is empty and flushQueue returns early.
      _reconcileDrain();
      unawaited(flushQueue());
    });
  }

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
  Stream<LocationHealth> healthState() async* {
    // Armed here rather than in the constructor, and deliberately so.
    // `getServiceStatusStream()` is an EventChannel: subscribing to it reaches
    // for `ServicesBinding.instance`, so arming it at construction made simply
    // *building* this service throw in any context without a platform binding
    // — a plain Dart test, an isolate. Lazy arming keeps construction
    // platform-free and only pays the cost when somebody actually listens.
    _watchServiceStatus();

    // Computed on subscribe rather than replaying `_lastHealth`: a listener
    // attaching after a long idle wants the answer now, not whatever was true
    // when the last event fired.
    final LocationHealth current = await health();
    _lastHealth = current;
    yield current;
    yield* _healthState.stream;
  }

  /// `getServiceStatusStream()` fires the moment the location toggle moves,
  /// which is the one health input that changes with no warning and has no
  /// other signal. A revoked runtime permission has no equivalent stream on
  /// Android, so [TrackingController] keeps polling as the backstop — this
  /// only makes the common case immediate.
  ///
  /// Idempotent: every [healthState] subscriber calls it, one subscription is
  /// enough. Failure is swallowed, because the poll is a complete fallback —
  /// losing the stream costs latency, not correctness.
  void _watchServiceStatus() {
    if (_serviceStatus != null) return;
    try {
      _serviceStatus = Geolocator.getServiceStatusStream().listen(
        (ServiceStatus status) {
          // Logged because this is the line that proves the stream is armed
          // and firing. On a device the alternative is inferring it from how
          // fast a banner appears, which cannot tell "the stream fired" from
          // "the 15 s poll caught it".
          debugPrint('[location] service status -> ${status.name}');
          unawaited(refreshHealth());
        },
        onError: (Object e) =>
            debugPrint('[location] service status stream failed: $e'),
        cancelOnError: false,
      );
      debugPrint('[location] service status stream armed');
    } catch (e) {
      debugPrint('[location] service status stream unavailable: $e');
    }
  }

  /// Recompute health and publish it if it actually moved.
  ///
  /// Public because the permissions screen and the session card both want to
  /// force a re-read after sending the employee to system settings, where the
  /// change happens outside anything this class can observe.
  Future<void> refreshHealth() async {
    final LocationHealth next = await health();
    if (_healthState.isClosed || next == _lastHealth) return;
    debugPrint('[location] health ${_lastHealth?.name ?? '-'} -> ${next.name}');
    _lastHealth = next;
    _healthState.add(next);
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
    _stationary = false;
    _pendingStationary = null;
    _movementSeeded = false;
    _setMovement(MovementStatus.unknown);
    _emitSync();
    _subscribe();
    // Arm the guardrail immediately: a session that opens somewhere with a weak
    // first fix should still be topped up within the offline window.
    _armHeartbeat();

    // Batching beats dribbling: waking the radio every half minute for a single
    // fix pays the cellular tail-energy cost over and over. The drain cadence is
    // server-tunable (config `syncIntervalSeconds`, default 300s) so an admin can
    // trade latency for battery without an app release; at the 300s default it
    // rolls roughly five fixes (at a 60s cadence) into one request, cutting
    // server calls ~10x with no loss of fidelity on the admin's live map.
    // Nothing is stranded: the queue still flushes early the moment it reaches
    // syncBatchSize, and stopTracking() force-flushes on session end. The server
    // clamps syncIntervalSeconds below the offline window, so no client-side
    // ceiling is needed — and the ~5-min heartbeat keeps the device inside that
    // window even on a quiet, distance-filtered stretch.
    //
    // While an admin is watching (`now < liveUntil`) the same timer runs at
    // `liveSyncIntervalSeconds` (15s) instead. That is the *only* thing a
    // watch changes: GPS capture cadence, the distance filter, the adaptive
    // moving/stationary state machine and the heartbeat above are all
    // untouched, because a watch costs radio time, not GPS time.
    _liveUntil = null;
    _armDrain();
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
    _drainInterval = Duration.zero;
    _liveUntil = null;
    _heartbeat?.cancel();
    _heartbeat = null;
    _transitionTimer?.cancel();
    _transitionTimer = null;
    _pendingStationary = null;
    _stationary = false;
    _movementSeeded = false;
    _setMovement(MovementStatus.unknown);
    await _positions?.cancel();
    _positions = null;
    _sessionId = null;
    unawaited(flushQueue());
  }

  void _subscribe() {
    if (_sessionId == null) return;
    try {
      _positions = Geolocator.getPositionStream(
        locationSettings: _settings(stream: true, stationary: _stationary),
      ).listen(
        _onPosition,
        onError: _onStreamError,
        cancelOnError: false,
      );
    } catch (e) {
      _onStreamError(e);
    }
  }

  /// Swap the live stream onto the current [_stationary] mode's settings. Only
  /// the subscription is recycled — the queue, drain, heartbeat and persistence
  /// timers are untouched, so a mode change never strands a fix or leaks a
  /// timer. A single old subscription is cancelled before the new one opens.
  void _resubscribe() {
    if (_sessionId == null) return;
    _positions?.cancel();
    _positions = null;
    _subscribe();
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

    // A fix worse than minAccuracyMetres used to be dropped outright, which
    // left weak-GPS stretches with no trail at all. It is now kept and queued,
    // flagged low-accuracy so the server (and the UI) can tell it apart from a
    // confident fix — a rough point is still roughly where the employee was,
    // and that is the whole record of the day. The poorAccuracy health signal
    // still fires off it, so the "weak GPS" warning is unchanged.
    final bool lowAccuracy = position.accuracy > config.minAccuracyMetres;
    if (lowAccuracy) _lastAccuracyReject = DateTime.now();

    final LocationLog fix = _toLog(position, sessionId, lowAccuracy: lowAccuracy);

    final LocationLog? previous = _lastAccepted;
    if (previous != null && _isImplausibleJump(previous, fix)) {
      debugPrint('[location] dropped implausible jump');
      return;
    }

    // A good fix clears the warning; a low-accuracy one leaves it standing.
    if (!lowAccuracy) _lastAccuracyReject = null;
    _lastAccepted = fix;
    _enqueue(fix);
    if (!_fixes.isClosed) _fixes.add(fix);
    _emitSync();

    // A real fix just reached the queue, so the offline window is fresh: push
    // the forced heartbeat back out to the full interval.
    _armHeartbeat();
    // Let this fix nudge the moving↔stationary cadence (debounced).
    _evaluateMovement(fix);

    if (_uploader != null && _queue.length >= config.syncBatchSize) {
      unawaited(flushQueue());
    }
  }

  /// Decide whether this fix argues for a cadence change and, if so, arm (or
  /// let ride) the debounce toward it. A fix that agrees with the current mode
  /// cancels any pending flip — that is the "state reverted before it held"
  /// case, and is what keeps the boundary from thrashing.
  void _evaluateMovement(LocationLog fix) {
    if (_sessionId == null) return;
    final bool candidate = fix.speedKmh < config.movementSpeedThresholdKmh;

    // The first fix of a session publishes its own verdict instead of waiting
    // out the hold window. That window exists to stop an *established* state
    // thrashing at the speed boundary; at session start there is no
    // established state, only `_stationary = false`, which is a cadence
    // default ("begin on high accuracy") and not a measurement. Reporting
    // that default as fact is what made the activity chip wrong rather than
    // merely late.
    //
    // Only the *published* state is seeded. `_stationary` — the GPS cadence —
    // is still left entirely to the debounce below: swapping the position
    // stream on the very first fix would cancel and re-open the subscription
    // at the moment fixes matter most, and a broadcast stream drops whatever
    // arrives in that gap. The two converge within one hold window, and every
    // publication after the seed is `_stationary` itself.
    if (!_movementSeeded) {
      _movementSeeded = true;
      _setMovement(
          candidate ? MovementStatus.stationary : MovementStatus.moving);
    }

    if (candidate == _stationary) {
      _pendingStationary = null;
      _transitionTimer?.cancel();
      _transitionTimer = null;
      // A fix that agrees with the current mode is also what settles a seed
      // that guessed the other way: published state re-joins the cadence.
      _setMovement(
          _stationary ? MovementStatus.stationary : MovementStatus.moving);
      return;
    }

    // Already waiting on exactly this transition — let the armed timer run so
    // the hold window is measured from the first qualifying fix.
    if (_pendingStationary == candidate &&
        (_transitionTimer?.isActive ?? false)) {
      return;
    }

    _pendingStationary = candidate;
    _transitionTimer?.cancel();
    _transitionTimer = Timer(_transitionHoldWindow, () {
      _transitionTimer = null;
      final bool? target = _pendingStationary;
      _pendingStationary = null;
      // The session may have closed, or the mode already matches, while the
      // window ran — either way there is nothing to swap.
      if (_sessionId == null || target == null || target == _stationary) return;
      _stationary = target;
      // One state change, published once: the cadence swap and the employee's
      // activity chip are the same decision.
      _setMovement(
          _stationary ? MovementStatus.stationary : MovementStatus.moving);
      _resubscribe();
    });
  }

  @override
  MovementStatus get movement => _movement;

  @override
  Stream<MovementStatus> movementState() async* {
    yield _movement;
    yield* _movementState.stream;
  }

  void _setMovement(MovementStatus next) {
    if (next == _movement) return;
    _movement = next;
    if (!_movementState.isClosed) _movementState.add(next);
  }

  /// (Re)start the forced-fix guardrail. Cancelling first means the timer only
  /// ever fires after a genuine [_heartbeatInterval] of silence.
  void _armHeartbeat() {
    if (_sessionId == null) return;
    _heartbeat?.cancel();
    _heartbeat = Timer(_heartbeatInterval, () => unawaited(_onHeartbeat()));
  }

  /// No fix has been emitted for [_heartbeatInterval]. Capture one current
  /// position — a one-shot read ignores the distance filter — and inject it
  /// into the queue so the next 5-min batch keeps the employee inside the
  /// server's offline window. Re-arms itself so a quiet stretch keeps ticking.
  Future<void> _onHeartbeat() async {
    final String? sessionId = _sessionId;
    if (sessionId == null) return;
    try {
      final Position position = await Geolocator.getCurrentPosition(
        locationSettings: _settings(
          timeLimit: const Duration(seconds: 25),
          stream: false,
        ),
      );
      if (_sessionId != sessionId) return; // session closed mid-read.
      final bool lowAccuracy = position.accuracy > config.minAccuracyMetres;
      if (lowAccuracy) _lastAccuracyReject = DateTime.now();
      final LocationLog fix =
          _toLog(position, sessionId, lowAccuracy: lowAccuracy);
      // Deliberately bypasses the jump filter: this is a forced top-up, not a
      // movement sample, and after minutes of silence the implied speed is low
      // anyway. It does not drive _evaluateMovement for the same reason.
      _lastAccepted = fix;
      _enqueue(fix);
      if (!_fixes.isClosed) _fixes.add(fix);
      _emitSync();
    } catch (e) {
      debugPrint('[location] heartbeat fix failed: $e');
    } finally {
      // Re-arm whether or not the read succeeded, so a transient failure just
      // retries at the next window instead of silently disarming the guard.
      _armHeartbeat();
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

  LocationLog _toLog(
    Position position,
    String sessionId, {
    bool? lowAccuracy,
  }) {
    return LocationLog(
      // Device-local id. The server assigns the real one when the fix syncs.
      id: 'loc_local_${position.timestamp.millisecondsSinceEpoch}_${_seq++}',
      sessionId: sessionId,
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      speedKmh: _speedKmh(position),
      // `.toLocal()` is load-bearing, not decoration. geolocator builds this
      // with `DateTime.fromMillisecondsSinceEpoch(..., isUtc: true)`, so the
      // value arrives UTC-flagged. `Fmt.time`/`Fmt.clock` read `.hour` and
      // `.minute` straight off the DateTime, so a UTC-flagged fix renders the
      // UTC wall clock: a session opened at 08:35 IST displayed as "03:05"
      // until the first server refresh replaced it (`Wire.dateOrNull` does
      // call `.toLocal()`, which is why the bug looked intermittent).
      // The instant is unchanged — only the flag — and uploads still go out as
      // `.toUtc().toIso8601String()`, so the wire contract is untouched.
      recordedAt: position.timestamp.toLocal(),
      // Nothing is synced until an uploader confirms it, so this is the only
      // truthful state for a fresh fix.
      syncState: SyncState.queued,
      // Derive from the threshold when the caller does not already know it (the
      // session start/end fix path), so the flag is always set off the same
      // rule the stream uses.
      lowAccuracy: lowAccuracy ?? position.accuracy > config.minAccuracyMetres,
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
  ///
  /// [stationary] selects the low-power variant: balanced-power accuracy and an
  /// interval stretched by [_stationaryIntervalFactor] (60s → ~3 min). Moving
  /// stays on high accuracy at the configured interval. The distance filter is
  /// applied in both modes so a parked employee's radio can idle; on iOS, which
  /// has no explicit interval, accuracy + distance filter are the only levers.
  ///
  /// `LocationAccuracy.medium` is geolocator's balanced-power tier — it maps to
  /// Android's `PRIORITY_BALANCED_POWER_ACCURACY` and the matching iOS accuracy.
  LocationSettings _settings({
    Duration? timeLimit,
    required bool stream,
    bool stationary = false,
  }) {
    final LocationAccuracy accuracy =
        stationary ? LocationAccuracy.medium : LocationAccuracy.high;
    final int intervalSeconds = stationary
        ? config.locationIntervalSeconds * _stationaryIntervalFactor
        : config.locationIntervalSeconds;
    if (defaultTargetPlatform == TargetPlatform.android) {
      return AndroidSettings(
        accuracy: accuracy,
        distanceFilter: config.distanceFilterMetres,
        intervalDuration: Duration(seconds: intervalSeconds),
        timeLimit: timeLimit,
        // No geolocator foreground notification: the location foreground
        // service is owned by SessionNotificationService (flutter_foreground_
        // task), which posts the one ongoing notification with the live timer
        // and the Pause / End Day buttons. Letting geolocator post its own too
        // would mean two services and two notifications. That foreground
        // service keeps this process alive, so the stream below keeps
        // delivering with the screen off exactly as before.
        foregroundNotificationConfig: null,
      );
    }
    if (defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS) {
      return AppleSettings(
        accuracy: accuracy,
        distanceFilter: config.distanceFilterMetres,
        timeLimit: timeLimit,
        pauseLocationUpdatesAutomatically: false,
        // No always-on permission is requested, so this stays false: it would
        // advertise a capability the app has not declared.
        showBackgroundLocationIndicator: false,
      );
    }
    return LocationSettings(
      accuracy: accuracy,
      distanceFilter: config.distanceFilterMetres,
      timeLimit: timeLimit,
    );
  }

  // ----------------------------------------------------------- offline queue

  void _enqueue(LocationLog fix) {
    if (_queue.length >= queueCapacity) {
      _queue.removeFirst();
      // Counted as failed because it is: that fix will never reach the server.
      _failed++;
    }
    _queue.add(fix);
    _schedulePersist();
  }

  // ------------------------------------------------------------ persistence

  /// Reload the durable queue, merge it ahead of anything captured since
  /// construction, and resume draining. Runs once, from the constructor.
  Future<void> _restore() async {
    try {
      final LocationQueueData data = await _store.load();
      if (data.fixes.isNotEmpty) {
        // Persisted fixes are strictly older than anything the live stream has
        // enqueued during the async gap, so they go to the front. Re-apply the
        // capacity cap against the merged total, dropping oldest-as-failed
        // exactly as [_enqueue] would have.
        final List<LocationLog> live = List<LocationLog>.of(_queue);
        _queue
          ..clear()
          ..addAll(data.fixes)
          ..addAll(live);
        while (_queue.length > queueCapacity) {
          _queue.removeFirst();
          _failed++;
        }
      }
      // Only adopt the persisted counters if this instance has not already
      // moved them on its own during the gap.
      if (_failed == 0) _failed += data.failed;
      _lastSyncedAt ??= data.lastSyncedAt;
    } catch (e) {
      debugPrint('[location] queue restore failed: $e');
    } finally {
      _restored = true;
      _emitSync();
      // Persist the merged state and resume draining if the wire is already up.
      _schedulePersist();
      if (_uploader != null && _queue.isNotEmpty) unawaited(flushQueue());
    }
  }

  /// Coalesce writes: the first change arms a short timer, later changes inside
  /// the window ride the same write. Cheap, and keeps the JSON encode of a big
  /// queue off every single fix.
  void _schedulePersist() {
    if (!_restored) return; // guarded: never write before the load lands.
    if (_persistTimer?.isActive ?? false) return;
    _persistTimer = Timer(persistDebounce, () => unawaited(_persistNow()));
  }

  Future<void> _persistNow() async {
    _persistTimer?.cancel();
    _persistTimer = null;
    if (!_restored) return;
    try {
      await _store.save(
        LocationQueueData(
          fixes: List<LocationLog>.of(_queue),
          failed: _failed,
          lastSyncedAt: _lastSyncedAt,
        ),
      );
    } catch (e) {
      debugPrint('[location] queue persist failed: $e');
    }
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
        final UploadOutcome outcome;

        try {
          outcome = await uploader(sessionId, batch);
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

        // The device learns it is being watched in the reply to a batch it was
        // going to send anyway — there is no push channel, by design. Applied
        // before the queue bookkeeping so a response that settles nothing
        // still moves the cadence.
        _applyLiveUntil(outcome.liveUntil);

        final Set<String> settled = outcome.settled;

        if (settled.isEmpty) {
          parked.add(sessionId);
          _emitSync();
          continue;
        }

        _queue.removeWhere((LocationLog fix) => settled.contains(fix.id));
        _lastSyncedAt = DateTime.now();
        // The queue shrank; mirror it so a cold start does not re-queue fixes
        // the server has already settled. A dropped write here only costs a
        // replay, which the server answers DUPLICATE, so the debounce is safe.
        _schedulePersist();
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
    await _serviceStatus?.cancel();
    _serviceStatus = null;
    // Flush any debounced write still owing before the instance goes away, so
    // a graceful teardown never loses the last few fixes.
    await _persistNow();
    await _fixes.close();
    await _sync.close();
    await _movementState.close();
    await _healthState.close();
  }
}
