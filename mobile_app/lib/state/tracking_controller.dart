import 'dart:async';

import 'package:flutter/foundation.dart';

import '../core/config/tracking_config.dart';
import '../data/models/models.dart';
import '../data/repositories/employee_repository.dart';
import '../services/location_service.dart';

enum StartDayOutcome { started, blocked, alreadyRunning }

class StartDayResult {
  const StartDayResult(this.outcome, this.health);
  final StartDayOutcome outcome;
  final LocationHealth health;
}

/// Owns the workday / session state machine and the live timer.
///
/// The UI never talks to [LocationService] directly — it asks this controller,
/// which is the only place the "must have a valid fix before starting" rule
/// lives.
class TrackingController extends ChangeNotifier {
  TrackingController({
    required EmployeeRepository repository,
    required LocationService locationService,
    this.config = TrackingConfig.defaults,
  })  : _repo = repository,
        _location = locationService;

  final EmployeeRepository _repo;
  final LocationService _location;
  final TrackingConfig config;

  Timer? _ticker;
  StreamSubscription<SyncSnapshot>? _syncSub;
  StreamSubscription<LocationLog>? _fixSub;

  /// A read can still be in flight when the shell is torn down — signing out
  /// mid-load, for one. Every await here re-checks this before touching state.
  bool _disposed = false;

  bool _loading = true;
  Object? _error;
  HomeSnapshot? _snapshot;
  DateTime _now = DateTime.now();
  bool _busy = false;

  bool get isLoading => _loading;
  Object? get error => _error;
  HomeSnapshot? get snapshot => _snapshot;
  bool get isBusy => _busy;
  DateTime get now => _now;

  WorkStatus get status => _snapshot?.status ?? WorkStatus.notStarted;
  MovementStatus get movement => _snapshot?.movement ?? MovementStatus.unknown;
  LocationHealth get locationHealth =>
      _snapshot?.locationHealth ?? LocationHealth.ok;
  List<WorkSession> get sessions => _snapshot?.sessions ?? const <WorkSession>[];
  WorkSession? get activeSession => _snapshot?.activeSession;
  DaySummary get summary => _snapshot?.summary ?? DaySummary.empty;
  SyncSnapshot? get sync => _snapshot?.sync;
  LocationLog? get lastFix => _snapshot?.lastFix;

  /// Live duration of the open session; zero when nothing is running.
  Duration get sessionElapsed {
    final WorkSession? s = activeSession;
    if (s == null) return Duration.zero;
    return _now.difference(s.startTime);
  }

  /// Worked time across the day, including the running session.
  Duration get workedToday {
    Duration total = Duration.zero;
    for (final WorkSession s in sessions) {
      total += s.durationAt(_now);
    }
    return total;
  }

  DateTime? get joiningTime =>
      sessions.isEmpty ? null : sessions.first.startTime;

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final HomeSnapshot fresh = await _repo.home();
      if (_disposed) return;
      _snapshot = fresh;
      _error = null;
      if (status.isSessionOpen) {
        await _location.startTracking(sessionId: activeSession!.id);
        _listenToLocation();
        _startTicker();
      }
    } catch (e) {
      _error = e;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() async {
    try {
      final HomeSnapshot fresh = await _repo.home();
      // Preserve locally-started/ended sessions across a pull-to-refresh.
      _snapshot = fresh.copyWith(
        sessions: _snapshot?.sessions,
        status: _snapshot?.status,
      );
      _error = null;
    } catch (e) {
      _error = e;
    }
    notifyListeners();
  }

  // ------------------------------------------------------------- day actions

  /// Start Day. Refuses to open a session without a usable GPS fix — that first
  /// fix is what becomes today's joining time.
  Future<StartDayResult> startDay() async {
    if (status.isSessionOpen) {
      return const StartDayResult(StartDayOutcome.alreadyRunning, LocationHealth.ok);
    }
    if (_busy) {
      return const StartDayResult(StartDayOutcome.alreadyRunning, LocationHealth.ok);
    }

    _busy = true;
    notifyListeners();
    try {
      final LocationHealth health = await _location.health();
      if (health.blocksStart) {
        _applyHealth(health);
        return StartDayResult(StartDayOutcome.blocked, health);
      }

      final String sessionId = 'ses_local_${sessions.length + 1}';
      final LocationLog? fix = await _location.currentFix(sessionId: sessionId);
      if (fix == null) {
        _applyHealth(LocationHealth.serviceDisabled);
        return const StartDayResult(
            StartDayOutcome.blocked, LocationHealth.serviceDisabled);
      }

      final WorkSession session = WorkSession(
        id: sessionId,
        index: sessions.length + 1,
        startTime: fix.recordedAt,
        endTime: null,
        distanceKm: 0,
        locationPoints: 1,
        startLatitude: fix.latitude,
        startLongitude: fix.longitude,
      );

      _snapshot = (_snapshot ?? _emptySnapshot()).copyWith(
        status: WorkStatus.working,
        movement: MovementStatus.stationary,
        locationHealth: health,
        sessions: <WorkSession>[...sessions, session],
        lastFix: fix,
      );

      await _location.startTracking(sessionId: sessionId);
      _listenToLocation();
      _startTicker();
      return StartDayResult(StartDayOutcome.started, health);
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Closes the open session but keeps the day open — this is how a workday
  /// ends up with several sessions.
  Future<void> pauseSession() async {
    final WorkSession? open = activeSession;
    if (open == null) return;
    _closeSession(open, WorkStatus.idle);
    await _location.stopTracking();
    _stopTicker();
    notifyListeners();
  }

  /// End Day — closes the session for good and freezes the day's numbers.
  Future<void> endDay() async {
    _busy = true;
    notifyListeners();
    try {
      final WorkSession? open = activeSession;
      if (open != null) {
        final LocationLog? fix = await _location.currentFix(sessionId: open.id);
        _closeSession(open, WorkStatus.ended, finalFix: fix);
      } else {
        _snapshot = _snapshot?.copyWith(status: WorkStatus.ended);
      }
      await _location.stopTracking();
      _stopTicker();
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// Demo helper: puts the day back to "Not Started" so the start flow can be
  /// walked through. Not part of the production surface.
  Future<void> resetDay() async {
    await _location.stopTracking();
    _stopTicker();
    _snapshot = _emptySnapshot();
    notifyListeners();
  }

  // ---------------------------------------------------------------- internals

  void _closeSession(WorkSession open, WorkStatus next, {LocationLog? finalFix}) {
    final DateTime end = finalFix?.recordedAt ?? DateTime.now();
    final List<WorkSession> updated = sessions
        .map((WorkSession s) => s.id == open.id
            ? WorkSession(
                id: s.id,
                index: s.index,
                startTime: s.startTime,
                endTime: end,
                distanceKm: s.distanceKm,
                locationPoints: s.locationPoints,
                startLatitude: s.startLatitude,
                startLongitude: s.startLongitude,
              )
            : s)
        .toList(growable: false);

    _snapshot = _snapshot?.copyWith(
      status: next,
      movement: MovementStatus.stationary,
      sessions: updated,
      lastFix: finalFix,
    );
  }

  void _applyHealth(LocationHealth health) {
    _snapshot = (_snapshot ?? _emptySnapshot()).copyWith(
      locationHealth: health,
      status: status.isSessionOpen
          ? (health == LocationHealth.noInternet
              ? WorkStatus.offline
              : WorkStatus.locationUnavailable)
          : status,
    );
  }

  void _listenToLocation() {
    _syncSub?.cancel();
    _syncSub = _location.syncState().listen((SyncSnapshot s) {
      _snapshot = _snapshot?.copyWith(
        sync: s,
        status: status.isSessionOpen && !s.isOnline
            ? WorkStatus.offline
            : (status == WorkStatus.offline ? WorkStatus.working : status),
      );
      notifyListeners();
    });

    final WorkSession? open = activeSession;
    if (open == null) return;
    _fixSub?.cancel();
    _fixSub = _location.track(sessionId: open.id).listen((LocationLog fix) {
      _snapshot = _snapshot?.copyWith(
        lastFix: fix,
        movement: fix.speedKmh >= config.movementSpeedThresholdKmh
            ? MovementStatus.moving
            : MovementStatus.stationary,
      );
      notifyListeners();
    });
  }

  void _startTicker() {
    _ticker?.cancel();
    if (_disposed) return;
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      _now = DateTime.now();
      notifyListeners();
    });
  }

  void _stopTicker() {
    _ticker?.cancel();
    _ticker = null;
    _fixSub?.cancel();
    _fixSub = null;
  }

  HomeSnapshot _emptySnapshot() => HomeSnapshot(
        status: WorkStatus.notStarted,
        movement: MovementStatus.unknown,
        locationHealth: LocationHealth.ok,
        sessions: const <WorkSession>[],
        summary: DaySummary.empty,
        activity: const <ActivityEvent>[],
        visits: const <CompanyVisit>[],
        stops: const <StopRecord>[],
        lastFix: null,
        sync: SyncSnapshot(
          queued: 0,
          failed: 0,
          lastSyncedAt: DateTime.now(),
          isOnline: true,
        ),
      );

  /// Dropped after [dispose] instead of asserting: a late repository answer is
  /// not a programming error, it is just too late to matter.
  @override
  void notifyListeners() {
    if (_disposed) return;
    super.notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _ticker?.cancel();
    _syncSub?.cancel();
    _fixSub?.cancel();
    super.dispose();
  }
}
