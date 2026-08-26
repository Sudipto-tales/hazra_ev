import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';

import '../core/config/tracking_config.dart';
import '../data/models/models.dart';
import '../data/repositories/employee_repository.dart';
import '../data/repositories/tracking_repository.dart';
import '../services/location_service.dart';

enum StartDayOutcome {
  started,
  blocked,
  offline,
  dayLocked,
  alreadyRunning,
}

class StartDayResult {
  const StartDayResult(this.outcome, this.health, [this.dayState = DayState.open]);
  final StartDayOutcome outcome;
  final LocationHealth health;
  final DayState dayState;
}

/// Why End Day cannot proceed. The day is only ever closed online with a live
/// GPS fix, because the lock that follows lives on the server — a day closed
/// offline would be a fact only this device believed.
enum EndDayBlock { none, locationOff, offline, alreadyClosed }

class EndDayGate {
  const EndDayGate(this.block, this.health, [this.fix]);

  final EndDayBlock block;
  final LocationHealth health;

  /// The closing fix, captured during the check so the form does not have to
  /// wait for GPS a second time.
  final LocationLog? fix;

  bool get ok => block == EndDayBlock.none;
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
    TrackingRepository? trackingRepository,
    this.config = TrackingConfig.defaults,
    this.outboxRetryInterval = const Duration(seconds: 30),
  })  : _repo = repository,
        _location = locationService,
        // The no-op is the mock/no-server path, and the safe default for a
        // caller that has not been updated: it keeps the day working locally
        // and never claims anything reached a server.
        _sync = trackingRepository ?? const NoopTrackingRepository();

  final EmployeeRepository _repo;
  final LocationService _location;

  /// The write path. Every call through it is retried until it lands, because
  /// a session the server never heard about is a day that did not happen.
  final TrackingRepository _sync;
  final TrackingConfig config;

  /// How often the outbox retries what the server has not accepted yet. Long
  /// enough not to hammer a dead network, short enough that a session opened
  /// in a basement is real within half a minute of walking outside.
  final Duration outboxRetryInterval;

  Timer? _ticker;
  int _tick = 0;

  /// When GPS and network both went away with a session open. Null whenever
  /// either one is back.
  DateTime? _degradedSince;

  /// Set when the watchdog closed a session on its own, so Home can say so
  /// once and then forget it.
  DateTime? _autoClosedAt;

  StreamSubscription<SyncSnapshot>? _syncSub;
  StreamSubscription<LocationLog>? _fixSub;

  /// Device session id → what the server still owes an answer on.
  ///
  /// This is the whole offline story for sessions. A session is opened locally
  /// the instant the fix arrives, so the button never waits for the network;
  /// the server call is queued here and retried by the ticker until it lands.
  /// The device id doubles as the idempotency key, so a retry after a timeout
  /// returns the session that was already created instead of a second one.
  ///
  /// In memory only: a dropped network loses nothing, but the process being
  /// killed with an unsynced session open does. Persisting this map is the
  /// next step and belongs beside the location queue, not in the UI layer.
  final Map<String, _SessionOutbox> _outbox = <String, _SessionOutbox>{};

  bool _draining = false;
  Timer? _outboxRetry;

  /// Last value sent to `POST /tracking/health`, so the ping only goes out on
  /// an actual change rather than every fifteen seconds.
  LocationHealth? _reportedHealth;

  final Random _rand = Random();

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

  /// Server-owned. The device mirrors it and never decides it locally.
  DayState get dayState => _snapshot?.dayState ?? DayState.open;
  DayCloseout? get closeout => _snapshot?.closeout;
  bool get isDayLocked => dayState.isLocked;

  /// Non-null until [acknowledgeAutoClose] — Home shows one notice for it.
  DateTime? get autoClosedAt => _autoClosedAt;

  void acknowledgeAutoClose() {
    if (_autoClosedAt == null) return;
    _autoClosedAt = null;
    notifyListeners();
  }

  bool get _isOnline => _snapshot?.sync.isOnline ?? true;

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
      // A locked day never resumes tracking, whatever the device thinks.
      if (status.isSessionOpen && !isDayLocked) {
        final WorkSession open = activeSession!;
        // Resumed from the server, so its id already *is* the server id — the
        // outbox entry is an identity mapping whose only job is to let fixes
        // captured from here on find the session they belong to.
        _outbox.putIfAbsent(
          open.id,
          () => _SessionOutbox(clientId: open.id)..serverId = open.id,
        );
        await _location.startTracking(sessionId: open.id);
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
      // Preserve locally-started/ended sessions across a pull-to-refresh —
      // unless the server says the day is closed, in which case the server
      // wins outright. The lock is the one piece of state the device does not
      // get an opinion on.
      final bool locked = fresh.dayState.isLocked;
      _snapshot = fresh.copyWith(
        sessions: locked ? null : _snapshot?.sessions,
        status: locked ? null : _snapshot?.status,
      );
      if (locked) {
        await _location.stopTracking();
        _stopTicker();
      }
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
      // Ask the server before anything else. This one call does double duty:
      // it proves there is a connection, and it settles whether today is still
      // workable. Local state can be stale — fresh install, cleared data, a
      // second device — and starting a day the server has closed is exactly
      // the divergence the lock exists to prevent.
      try {
        final HomeSnapshot fresh = await _repo.home();
        if (_disposed) {
          return const StartDayResult(
              StartDayOutcome.alreadyRunning, LocationHealth.ok);
        }
        _snapshot = (_snapshot ?? _emptySnapshot()).copyWith(
          dayState: fresh.dayState,
          closeout: fresh.closeout,
          summary: fresh.summary,
          sync: fresh.sync,
        );
      } catch (_) {
        _applyHealth(LocationHealth.noInternet);
        return const StartDayResult(
            StartDayOutcome.offline, LocationHealth.noInternet);
      }

      if (isDayLocked) {
        return StartDayResult(
            StartDayOutcome.dayLocked, LocationHealth.ok, dayState);
      }

      final LocationHealth health = await _location.health();
      if (health.blocksStart) {
        _applyHealth(health);
        return StartDayResult(StartDayOutcome.blocked, health);
      }

      final String sessionId = _newSessionId();
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

      // Open server-side, but do not make the button wait for it. The day is
      // already running as far as the device is concerned; the outbox owns
      // getting the server to agree, and retries until it does.
      _outbox[sessionId] = _SessionOutbox(clientId: sessionId, openFix: fix);
      unawaited(syncPendingWork());

      await _location.startTracking(sessionId: sessionId);
      _listenToLocation();
      _startTicker();
      unawaited(_pushHealth(health));
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
    _closeSession(open, WorkStatus.idle, reason: SessionEndReason.pause);
    _queueClose(open.id, reason: SessionEndReason.pause);
    await _location.stopTracking();
    _stopTicker();
    _degradedSince = null;
    notifyListeners();
  }

  /// Everything that must be true before the closeout form may open: the day
  /// still open, a live connection, and a real GPS fix. Captures that fix so
  /// [endDay] does not have to wait for one again.
  ///
  /// Called before the form rather than after it, so nobody fills in six fields
  /// only to be told they cannot submit.
  Future<EndDayGate> checkEndDay() async {
    if (isDayLocked) {
      return const EndDayGate(EndDayBlock.alreadyClosed, LocationHealth.ok);
    }
    if (!_isOnline) {
      return const EndDayGate(EndDayBlock.offline, LocationHealth.noInternet);
    }

    final LocationHealth health = await _location.health();
    if (_disposed) return const EndDayGate(EndDayBlock.offline, LocationHealth.ok);
    if (health.blocksStart) {
      _applyHealth(health);
      notifyListeners();
      return EndDayGate(EndDayBlock.locationOff, health);
    }

    final LocationLog? fix =
        await _location.currentFix(sessionId: activeSession?.id ?? 'ses_close');
    if (_disposed) return const EndDayGate(EndDayBlock.offline, LocationHealth.ok);
    if (fix == null) {
      _applyHealth(LocationHealth.serviceDisabled);
      notifyListeners();
      return const EndDayGate(
          EndDayBlock.locationOff, LocationHealth.serviceDisabled);
    }

    return EndDayGate(EndDayBlock.none, health, fix);
  }

  /// End Day — submits the declaration, then closes the day for good.
  ///
  /// Server first, device second, deliberately. If the POST fails the day stays
  /// open on both sides; if it succeeded and the device then crashed, the next
  /// `load()` picks the lock up from the server. The reverse order would let a
  /// device believe in a lock the server never wrote.
  ///
  /// Throws whatever the repository throws — the caller shows it.
  Future<DayCloseout> endDay(
    DayCloseoutDraft draft, {
    LocationLog? finalFix,
  }) async {
    _busy = true;
    notifyListeners();
    try {
      final DayCloseout stored = await _repo.submitDayCloseout(draft);
      if (_disposed) return stored;

      final WorkSession? open = activeSession;
      if (open != null) {
        _closeSession(
          open,
          WorkStatus.ended,
          reason: SessionEndReason.manual,
          finalFix: finalFix,
          endedAt: draft.endedAt,
        );
        // The closeout is the day's declaration; this is the session's own
        // end time and last fix. Queued rather than awaited: the day is
        // already closed on the server, and a flaky connection at this exact
        // moment must not leave the employee staring at a spinner.
        _queueClose(
          open.id,
          reason: SessionEndReason.manual,
          endedAt: draft.endedAt,
          fix: finalFix,
        );
      } else {
        _snapshot = _snapshot?.copyWith(status: WorkStatus.ended);
      }

      _snapshot = _snapshot?.copyWith(
        dayState: DayState.closedByEmployee,
        closeout: stored,
      );

      await _location.stopTracking();
      _stopTicker();
      _degradedSince = null;
      _autoClosedAt = null;
      return stored;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  /// The watchdog's close. GPS and network have both been gone long enough that
  /// nothing is being recorded and nothing can be asked — so the session ends
  /// and **no** closeout form is shown. The day stays open: an automatic close
  /// is not a declaration. The employee can start a new session when they are
  /// back, or close the day properly then.
  void _autoCloseSession() {
    final WorkSession? open = activeSession;
    if (open == null) return;

    _closeSession(open, WorkStatus.idle, reason: SessionEndReason.auto);
    // Reason `auto` goes up as `pause`: the session stops, the day does not.
    _queueClose(open.id, reason: SessionEndReason.auto, endedAt: _now);
    _autoClosedAt = _now;
    _degradedSince = null;
    unawaited(_location.stopTracking());
    _stopTicker();
    notifyListeners();
  }

  /// Demo helper: puts the day back to "Not Started" so the start flow can be
  /// walked through. Not part of the production surface.
  Future<void> resetDay() async {
    await _location.stopTracking();
    _stopTicker();
    _snapshot = _emptySnapshot();
    _degradedSince = null;
    _autoClosedAt = null;
    _outbox.clear();
    _outboxRetry?.cancel();
    _outboxRetry = null;
    _reportedHealth = null;
    notifyListeners();
  }

  // ---------------------------------------------------------------- internals

  void _closeSession(
    WorkSession open,
    WorkStatus next, {
    required SessionEndReason reason,
    LocationLog? finalFix,
    DateTime? endedAt,
  }) {
    final DateTime end = endedAt ?? finalFix?.recordedAt ?? DateTime.now();
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
                endReason: reason,
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

  // ------------------------------------------------------------ server sync

  /// Unique per session, and stable across retries because it is the
  /// idempotency key the server dedupes opens on.
  ///
  /// The old `ses_local_${n}` counter was neither: it restarted at 1 every
  /// day, so tomorrow's first session would have resolved to today's on the
  /// server, which looks `client_id` up per employee with no date scope.
  String _newSessionId() =>
      'ses_${DateTime.now().toUtc().millisecondsSinceEpoch}_${_rand.nextInt(1 << 30)}';

  /// Records that a session has ended and the server has to be told.
  ///
  /// The session is already closed on the device — this only owns delivery.
  /// The end time is captured now rather than at send time, so a close that
  /// takes three retries and twenty minutes still records the minute the
  /// employee actually stopped.
  void _queueClose(
    String sessionId, {
    required SessionEndReason reason,
    DateTime? endedAt,
    LocationLog? fix,
  }) {
    final _SessionOutbox entry = _outbox.putIfAbsent(
      sessionId,
      () => _SessionOutbox(clientId: sessionId),
    );

    entry.closeReason = reason;
    entry.closedAt = endedAt ?? DateTime.now();
    entry.closeFix = fix ?? entry.closeFix;

    unawaited(syncPendingWork());
  }

  /// One pass over everything the server has not been told: opens first, then
  /// closes. Whatever fails stays in the outbox and is retried — there is no
  /// attempt limit, because giving up would mean deciding a day's work never
  /// happened.
  ///
  /// Public because the retry cannot depend on a session being open: a close
  /// that failed leaves the ticker stopped, so something outside the session
  /// has to keep asking. [_outboxRetry] does that on its own, and the app may
  /// call this directly when it has reason to believe the network is back.
  Future<void> syncPendingWork() async {
    if (_draining) return;
    if (_outbox.isEmpty) {
      _armOutboxRetry();
      return;
    }
    _draining = true;

    try {
      final List<_SessionOutbox> pending =
          _outbox.values.toList(growable: false);

      for (final _SessionOutbox entry in pending) {
        if (_disposed) return;

        if (entry.needsOpen) {
          final LocationLog? fix = entry.openFix;

          if (fix == null) {
            // A close was queued for a session this controller never opened
            // and the server never named. There is nothing to open it with —
            // the server's own "no fixes received" rule is what closes it.
            debugPrint('[tracking] ${entry.clientId} has no opening fix');
            continue;
          }

          try {
            final WorkSession opened = await _sync.openSession(
              clientId: entry.clientId,
              fix: fix,
              startedAt: fix.recordedAt,
            );
            entry.serverId = opened.id;
          } catch (e) {
            // Offline, or the server is down. The clientId is what makes the
            // retry safe: a repeat returns the session already created.
            debugPrint('[tracking] session open still pending: $e');
            continue;
          }
        }

        // A close cannot go before the open it belongs to.
        if (entry.needsClose) {
          try {
            await _sync.closeSession(
              sessionId: entry.serverId!,
              reason: entry.closeReason!,
              endedAt: entry.closedAt,
              fix: entry.closeFix,
            );
            entry.closeSent = true;
          } catch (e) {
            debugPrint('[tracking] session close still pending: $e');
          }
        }
      }
    } finally {
      _draining = false;
      _armOutboxRetry();
    }

    // Entries outlive their close on purpose: fixes recorded during the
    // session may still be queued, and this map is the only thing that knows
    // which server session they belong to. A day holds a handful of them.
  }

  /// Keeps a retry running exactly as long as something is owed.
  ///
  /// The session ticker cannot be relied on for this: `pauseSession` stops it,
  /// and a close that failed is precisely the case where the app then sits
  /// idle with a session the server still believes is open.
  void _armOutboxRetry() {
    final bool pending = _outbox.values.any(
      (_SessionOutbox e) =>
          (e.needsOpen && e.openFix != null) || e.needsClose,
    );

    if (!pending || _disposed) {
      _outboxRetry?.cancel();
      _outboxRetry = null;
      return;
    }

    _outboxRetry ??= Timer.periodic(
      outboxRetryInterval,
      (_) => unawaited(syncPendingWork()),
    );
  }

  /// The offline queue's way out. `main.dart` hands this to
  /// `GeoLocationService.attachUploader`, which calls it with the fixes it has
  /// been holding.
  ///
  /// [sessionId] is the device-side id the fixes were recorded under; this is
  /// the only place that knows what the server calls that session. Returns the
  /// ids the server settled, so the queue can drop exactly those. Throws
  /// through on a network failure — the queue reads that as "offline" and
  /// keeps everything.
  Future<Set<String>> uploadQueuedFixes(
    String sessionId,
    List<LocationLog> batch,
  ) async {
    final _SessionOutbox? entry = _outbox[sessionId];

    if (entry == null) {
      // Fixes for a session this controller has no record of. Nothing can be
      // settled and nothing is thrown: the device is not offline, it just
      // cannot address these.
      debugPrint('[tracking] queued fixes for unknown session $sessionId');
      return const <String>{};
    }

    final String? serverId = entry.serverId;

    if (serverId == null) {
      // The session itself has not landed yet. Getting that done is what
      // unblocks the fixes; they stay queued until it does.
      unawaited(syncPendingWork());
      return const <String>{};
    }

    final LocationSyncResult result = await _sync.pushLocations(
      sessionId: serverId,
      fixes: batch,
    );

    return result.settled;
  }

  /// `POST /tracking/health` — on a change only, which is the point of it: the
  /// admin dashboard needs to know the moment GPS goes off, not a heartbeat
  /// every fifteen seconds.
  Future<void> _pushHealth(LocationHealth health) async {
    if (health == _reportedHealth) return;
    _reportedHealth = health;

    try {
      await _sync.reportHealth(health: health, isOnline: _isOnline);
    } catch (e) {
      // Fire-and-forget, but not forget-it-happened: clearing the record means
      // the next poll tries again instead of assuming the server knows.
      _reportedHealth = null;
      debugPrint('[tracking] health ping failed: $e');
    }
  }

  void _startTicker() {
    _ticker?.cancel();
    if (_disposed) return;
    _tick = 0;
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      _now = DateTime.now();
      _tick++;
      // GPS being switched off mid-session is silent — nothing pushes an event
      // for it — so it has to be looked for. The watchdog below is blind
      // without this.
      if (_tick % 15 == 0) unawaited(_pollHealth());
      // Anything the server has not been told yet. Cheap when there is
      // nothing owed — the drain returns on the first check — and it is what
      // turns a session opened on a dead network into a real one the moment
      // the network comes back.
      if (_tick % 30 == 0) unawaited(syncPendingWork());
      _evaluateWatchdog();
      notifyListeners();
    });
  }

  Future<void> _pollHealth() async {
    if (!status.isSessionOpen) return;
    final LocationHealth health = await _location.health();
    if (_disposed || _snapshot == null || health == locationHealth) return;
    _applyHealth(health);
    unawaited(_pushHealth(health));
    notifyListeners();
  }

  /// GPS unusable **and** no network, for [TrackingConfig.autoCloseAfterMinutes],
  /// with a session open → close it.
  ///
  /// Either condition alone is survivable and must not trigger this: offline
  /// still queues fixes on the device, and a live connection means the server
  /// sees the health ping and can decide for itself. Only losing both leaves a
  /// session that records nothing and can be told nothing — a fiction. The
  /// server runs the same rule from its side ("no fixes received"), which is
  /// what covers a device that is simply dead.
  void _evaluateWatchdog() {
    if (!status.isSessionOpen) {
      _degradedSince = null;
      return;
    }

    final bool blind = locationHealth.blocksStart && !_isOnline;
    if (!blind) {
      _degradedSince = null;
      return;
    }

    _degradedSince ??= _now;
    if (_now.difference(_degradedSince!) >=
        Duration(minutes: config.autoCloseAfterMinutes)) {
      _autoCloseSession();
    }
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
    _outboxRetry?.cancel();
    _syncSub?.cancel();
    _fixSub?.cancel();
    super.dispose();
  }
}

/// One session's unfinished business with the server.
///
/// It exists because the device is the source of truth for *when* a session
/// started and stopped, while the server is the source of truth for everything
/// derived from it — and on this app's network the two are frequently not
/// reconciled at the moment the employee taps the button.
class _SessionOutbox {
  _SessionOutbox({required this.clientId, this.openFix});

  /// The device-side session id. Doubles as the idempotency key for the open,
  /// which is why it must never be regenerated on a retry.
  final String clientId;

  /// The fix the session started at. The server takes the session's start time
  /// from it, so it is kept verbatim however long the open takes to land.
  final LocationLog? openFix;

  /// What the server calls this session. Null until the open lands — and while
  /// it is null the session's fixes cannot go up either, because
  /// `POST /tracking/locations` addresses a server session id.
  String? serverId;

  SessionEndReason? closeReason;
  DateTime? closedAt;
  LocationLog? closeFix;
  bool closeSent = false;

  bool get needsOpen => serverId == null;

  bool get needsClose => closeReason != null && !closeSent && serverId != null;
}
