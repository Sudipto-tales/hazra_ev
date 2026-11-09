import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';

import '../core/config/tracking_config.dart';
import '../core/utils/geo.dart';
import '../data/api/api_exception.dart';
import '../data/models/models.dart';
import '../data/repositories/employee_repository.dart';
import '../data/repositories/tracking_repository.dart';
import '../services/location_service.dart';
import '../services/session_notification_service.dart';

enum StartDayOutcome {
  started,
  blocked,
  offline,
  dayLocked,

  /// Today is a holiday or a weekly rest day — the server refuses a session on
  /// it (`NON_WORKING_DAY`). Reached proactively from the day snapshot, or from
  /// the 409 the session-open call returns in the race case.
  nonWorkingDay,
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
    SessionNotificationService? sessionNotifications,
    this.config = TrackingConfig.defaults,
    this.outboxRetryInterval = const Duration(seconds: 30),
  })  : _repo = repository,
        _location = locationService,
        _sessionNotif = sessionNotifications,
        // The no-op is the mock/no-server path, and the safe default for a
        // caller that has not been updated: it keeps the day working locally
        // and never claims anything reached a server.
        _sync = trackingRepository ?? const NoopTrackingRepository() {
    // Health is watched for the whole life of the controller, not just while a
    // session is open. Switching GPS off while idle or on break used to go
    // unnoticed until the next Start Day tap, because the only health check
    // lived on the session ticker — which `pauseSession` stops.
    _healthSub = _location.healthState().listen(
      _onHealthPushed,
      onError: (Object e) => debugPrint('[tracking] health stream failed: $e'),
    );
  }

  final EmployeeRepository _repo;
  final LocationService _location;

  /// Posts the ongoing session notification (live timer + Pause / End Day).
  /// Null on platforms/builds without it; every call site null-checks, so the
  /// whole feature is one optional dependency.
  final SessionNotificationService? _sessionNotif;

  /// Bumped when the employee taps **End Day** on the session notification.
  /// Home watches this and opens the closeout flow — ending a day needs the
  /// in-app form, so the button can only bring the app forward to it.
  final ValueNotifier<int> _endDayRequests = ValueNotifier<int>(0);

  /// Watch this to drive the End Day closeout from a notification tap.
  ValueListenable<int> get endDayRequests => _endDayRequests;

  /// Called from `main.dart` when the notification's End Day button is tapped.
  void requestEndDay() => _endDayRequests.value++;

  /// The write path. Every call through it is retried until it lands, because
  /// a session the server never heard about is a day that did not happen.
  final TrackingRepository _sync;

  /// Tracking thresholds. Starts at [TrackingConfig.defaults] and is replaced
  /// by the server's values once [_refreshConfig] lands — at login, at resume,
  /// and at each session start (API plan §3.12). Mutable because the controller
  /// is built in `main.dart` before sign-in, so the real config is not known
  /// yet. A fetch failure leaves this at the last good value, never clears it.
  TrackingConfig config;

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

  /// The app's one movement truth, straight off [LocationService]. The
  /// controller subscribes and republishes; it does not compute. See
  /// [LocationService.movementState].
  StreamSubscription<MovementStatus>? _movementSub;

  /// Device location health, pushed. Lives as long as the controller — see the
  /// note in the constructor.
  StreamSubscription<LocationHealth>? _healthSub;

  /// Health while **no** session is open, so Home can offer "turn location on"
  /// before the employee taps Start Day and is refused. Null until the first
  /// reading lands.
  ///
  /// Kept separate from the snapshot's `locationHealth`, which is a fact about
  /// a running session and is what the server is told about. Writing an idle
  /// reading into the snapshot would make `_statusForHealth` degrade a day
  /// that has not started.
  LocationHealth? get idleLocationHealth => _idleHealth;
  LocationHealth? _idleHealth;

  /// The health reading Home should actually render.
  ///
  /// With a session open that is the snapshot's value — session state, and
  /// what the server has been told. With no session open the snapshot's value
  /// is stale (or the `ok` an empty snapshot starts life with), so the idle
  /// reading is the honest one. Falls back to `ok` only before the first
  /// reading has landed, which is a fraction of a second at launch.
  LocationHealth get effectiveLocationHealth => status.isSessionOpen
      ? locationHealth
      : (_idleHealth ?? LocationHealth.ok);

  // ------------------------------------------------------------- distance
  //
  // The server owns the day's distance — `Engine::refreshSession` recomputes
  // it — but it can only recompute on a fix batch, which lands every
  // `syncIntervalSeconds` (300 s by default). So the server is up to five
  // minutes behind the phone, and polling it harder cannot fix that. The
  // device measures its own travel from the fixes it already has (zero
  // network, zero extra battery) and hands the server the baseline role.

  /// Day distance as the server last reported it.
  double _serverDistanceKm = 0;

  /// Locally measured legs, stamped with when they were measured, so a server
  /// snapshot retires exactly the ones it already accounts for and no more.
  final List<_DistanceLeg> _legs = <_DistanceLeg>[];

  /// Fix the next leg is measured from.
  LocationLog? _distanceAnchor;

  /// What has already been shown. Distance within a day only goes up, so a
  /// reconcile may raise this and never lower it — a travel figure that ticks
  /// backwards on a refresh reads as a bug, not as precision.
  double _displayedDistanceKm = 0;

  /// Distance the open session already had when this device picked it up: the
  /// server's value for a resumed session, zero for one started here.
  double _openSessionBaselineKm = 0;

  /// Distance measured on this device since that baseline.
  double _openSessionLocalKm = 0;

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

  /// Provisional, device-synthesized session-boundary rows awaiting server
  /// confirmation. See the [activity] getter. Pruned on every refresh/load and
  /// cleared on reset.
  final List<ActivityEvent> _localActivity = <ActivityEvent>[];
  DateTime _now = DateTime.now();
  bool _busy = false;

  /// Ticks once per second while a session is open, carrying the current wall
  /// clock. This is the ONLY per-second signal the UI gets: the live elapsed
  /// timer widgets subscribe to it through a [ValueListenableBuilder] so just
  /// they repaint each second, while the controller itself notifies its general
  /// listeners only when something observable actually changes. Before this the
  /// ticker called [notifyListeners] every second and rebuilt the whole Home
  /// subtree all day — a constant CPU/redraw drain and a real heat/battery cost.
  final ValueNotifier<DateTime> _clock = ValueNotifier<DateTime>(DateTime.now());

  /// Subscribe to this from elapsed-time widgets only. See [_clock].
  ValueListenable<DateTime> get clock => _clock;

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

  /// The day's activity timeline: the server's events, with the device's own
  /// provisional session-boundary rows merged in so the timeline is as
  /// immediate as the stat tiles beside it (Start Day already shows the session,
  /// joining time and worked clock the instant it is tapped; the timeline was
  /// the one panel that waited for the round trip).
  ///
  /// A provisional row is dropped as soon as the server sends its own row for
  /// the same boundary — matched on type and a near-identical instant, because
  /// the device stamps these with the fix time it also sends as `startedAt`, so
  /// the server's row lands on the same moment. Only boundaries the device
  /// already owns are synthesized; stops/visits/reports stay the server's.
  List<ActivityEvent> get activity {
    final List<ActivityEvent> server = _snapshot?.activity ?? const <ActivityEvent>[];

    // Once the day is closed the server has rebuilt and returned the whole
    // timeline — every boundary, stop and report — and it is authoritative and
    // final. The provisional device rows have nothing left to add, and any that
    // the supersede match missed (a fix time that landed more than the match
    // window from the tap, a device/server clock skew) would linger here as a
    // second copy of an event the server already sent. That is the "every
    // activity doubles after I end the day" the timeline showed. So after the
    // lock, the server's list stands alone.
    if (_localActivity.isEmpty || isDayLocked) return server;

    final List<ActivityEvent> merged = <ActivityEvent>[
      ...server,
      for (final ActivityEvent local in _localActivity)
        if (!_isSuperseded(local, server)) local,
    ]..sort((ActivityEvent a, ActivityEvent b) => a.time.compareTo(b.time));

    return merged;
  }

  /// A server row of the same type within ~2 min of the provisional one is the
  /// same boundary. Session boundaries sit minutes apart, so this cannot fold
  /// two distinct events together.
  static bool _isSuperseded(ActivityEvent local, List<ActivityEvent> server) =>
      server.any((ActivityEvent s) =>
          s.type == local.type &&
          (s.time.difference(local.time)).abs() < const Duration(seconds: 120));
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

  /// Today is a holiday or weekly rest day, as the server reported it on the
  /// day snapshot. Lets Home disable and annotate Start Work *before* the tap —
  /// the 409 from the session-open call is the backstop for the race.
  bool get isNonWorkingDay => _snapshot?.isNonWorkingDay ?? false;

  /// Set when a session the device opened locally was refused by the server
  /// with `NON_WORKING_DAY` / `DAY_CLOSED` after the fact — the race case.
  /// Home shows one notice and then clears it. Null the rest of the time.
  DateTime? _startRejectedAt;
  String? _startRejectedMessage;

  DateTime? get startRejectedAt => _startRejectedAt;
  String? get startRejectedMessage => _startRejectedMessage;

  void acknowledgeStartRejection() {
    if (_startRejectedAt == null) return;
    _startRejectedAt = null;
    _startRejectedMessage = null;
    notifyListeners();
  }

  bool get _isOnline => _snapshot?.sync.isOnline ?? true;

  /// Live duration of the open session as of the controller's last tick; zero
  /// when nothing is running.
  Duration get sessionElapsed => sessionElapsedAt(_now);

  /// Live duration of the open session as of [at]. Elapsed-time widgets pass
  /// the per-second value from [clock] so they tick without the whole subtree
  /// rebuilding.
  Duration sessionElapsedAt(DateTime at) {
    final WorkSession? s = activeSession;
    if (s == null) return Duration.zero;
    return at.difference(s.startTime);
  }

  /// Worked time across the day, including the running session, as of the
  /// controller's last tick.
  Duration get workedToday => workedTodayAt(_now);

  /// Worked time across the day as of [at]. See [sessionElapsedAt].
  Duration workedTodayAt(DateTime at) {
    Duration total = Duration.zero;
    for (final WorkSession s in sessions) {
      total += s.durationAt(at);
    }
    return total;
  }

  DateTime? get joiningTime =>
      sessions.isEmpty ? null : sessions.first.startTime;

  /// Pull the server's [TrackingConfig] and hand it to the live tracking
  /// stack. Best-effort by contract (§3.12): a failure (offline, first launch,
  /// a timeout) keeps whatever config is already in force — the last good one,
  /// or [TrackingConfig.defaults] — so tracking never waits on the network for
  /// its thresholds and Start Day is never blocked.
  Future<void> _refreshConfig() async {
    try {
      final TrackingConfig fetched = await _repo.config();
      if (_disposed) return;
      applyConfig(fetched);
    } catch (e) {
      debugPrint('[tracking] config fetch failed; keeping current config: $e');
    }
  }

  /// Adopt [next] for both this controller (movement/watchdog thresholds) and
  /// the location service (interval/accuracy/batch). Visible for the tests and
  /// for the composition root; day-to-day it is driven by [_refreshConfig].
  @visibleForTesting
  void applyConfig(TrackingConfig next) {
    config = next;
    _location.updateConfig(next);
  }

  /// Recover [WorkStatus.paused] from a server snapshot that cannot express it.
  ///
  /// `Engine::liveStatus` collapses "no open session" to `ended` as soon as the
  /// day has any sessions at all (`$status = $sessions ? 'ended' : 'not_started'`),
  /// whether the day is locked or not. Adopted verbatim that reads as a
  /// finished day, and `_Actions` renders a **disabled** "Day completed" — so
  /// an employee who tapped Break and then relaunched the app was locked out
  /// of their own open day until they cleared app data.
  ///
  /// `dayState` is the field that still carries the truth, and it is served
  /// unconditionally (`DaysController`: "Unconditional, not an include"). An
  /// unlocked day with sessions but none open is a break, not an ending.
  ///
  /// This is a decode-time repair of one lossy enum, not a local
  /// re-derivation of §3.14's `activity` — that stays the server's job. When
  /// the `activity` key collision is fixed server-side, `activity == paused`
  /// becomes the direct source and this can read it instead.
  HomeSnapshot _reconcileStatus(HomeSnapshot fresh) {
    if (fresh.status != WorkStatus.ended) return fresh;
    if (fresh.dayState.isLocked) return fresh;
    if (fresh.sessions.isEmpty) return fresh;
    if (fresh.sessions.any((WorkSession s) => s.endTime == null)) return fresh;
    return fresh.copyWith(status: WorkStatus.paused);
  }

  Future<void> load() async {
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final HomeSnapshot fresh = await _repo.home();
      if (_disposed) return;
      // A full load is a clean slate: whatever was measured locally belonged
      // to the state being replaced, and the server's number is the baseline
      // for everything measured from here.
      _resetDistance();
      _localActivity.clear();
      _snapshot = _reconcileStatus(fresh);
      _reconcileDistance(fresh.summary.distanceKm, syncedAt: null);
      _error = null;
      // Fresh thresholds at login/resume (§3.12). Best-effort — see
      // [_refreshConfig]. For a resumed session it is awaited below so the
      // position stream subscribes under the server's config; with no session
      // open it rides along unawaited so a later Start Day starts from current
      // values even before its own re-read lands.
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
        // Resumed, so the server's figure for this session is what the device
        // adds to; the first fix after resume opens a fresh measuring chain.
        _openSessionBaselineKm = open.distanceKm;
        _openSessionLocalKm = 0;
        _distanceAnchor = null;
        await _refreshConfig();
        if (_disposed) return;
        await _location.startTracking(sessionId: open.id);
        _listenToLocation();
        _startTicker();
      } else {
        unawaited(_refreshConfig());
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
      // The device's own last successful upload is the line between what the
      // server could already have counted and what is still ours alone.
      final DateTime? syncedAt = _snapshot?.sync.lastSyncedAt;
      _snapshot = fresh.copyWith(
        sessions: locked ? null : _snapshot?.sessions,
        status: locked ? null : _snapshot?.status,
        movement: locked ? null : _snapshot?.movement,
      );
      // Server wins as the baseline; legs measured after [syncedAt] ride on
      // top, and _applyDistance's floor keeps the figure from stepping back.
      _reconcileDistance(fresh.summary.distanceKm, syncedAt: syncedAt);
      _pruneLocalActivity(fresh.activity);
      if (locked) {
        // The server owns the closed day's timeline outright now, so drop any
        // provisional device rows rather than carry them as possible duplicates.
        _localActivity.clear();
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
    // A fresh attempt clears any stale rejection notice from a previous one.
    _startRejectedAt = null;
    _startRejectedMessage = null;
    notifyListeners();
    // Re-read the server's thresholds for this session (§3.12). Kicked off here
    // so it runs alongside the home() check below and adds no latency to the
    // tap; awaited only just before the position stream subscribes, where
    // _settings() reads interval/accuracy — so the fresh config governs this
    // session. Best-effort: _refreshConfig swallows failures and the stack
    // keeps its last good config, so a config fetch never blocks Start Day.
    final Future<void> configReady = _refreshConfig();
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
          isNonWorkingDay: fresh.isNonWorkingDay,
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

      // A holiday or weekly rest day the server has already told us about. The
      // session-open call would 409; refuse it here so nothing opens locally.
      if (isNonWorkingDay) {
        return StartDayResult(
            StartDayOutcome.nonWorkingDay, LocationHealth.ok, dayState);
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
        // Unknown, not "stationary". No fix has argued either way yet, and
        // claiming one is what made the chip wrong rather than merely late —
        // LocationService publishes the real value on the first fix.
        movement: _location.movement,
        locationHealth: health,
        sessions: <WorkSession>[...sessions, session],
        lastFix: fix,
      );

      // The timeline keeps pace with the tap: the first session of the day
      // also writes "Day started". These are provisional until the server's
      // rebuildActivity sends its own rows, stamped at the same fix time.
      if (session.index == 1) {
        _addProvisional(ActivityType.dayStarted, session.startTime, 'Day started');
      }
      _addProvisional(ActivityType.sessionStarted, session.startTime,
          'Session ${session.index} started');

      // This session starts at zero on this device, and the opening fix is the
      // first anchor every later leg is measured from.
      _openSessionBaselineKm = 0;
      _openSessionLocalKm = 0;
      _distanceAnchor = fix;

      // Open server-side, but do not make the button wait for it. The day is
      // already running as far as the device is concerned; the outbox owns
      // getting the server to agree, and retries until it does.
      _outbox[sessionId] = _SessionOutbox(clientId: sessionId, openFix: fix);
      unawaited(syncPendingWork());

      // Let the (concurrent) config re-read settle before the stream
      // subscribes, so this session records at the server's cadence. The
      // local session is already open above, so the tap has already returned
      // its result to the UI — this only gates the background stream.
      await configReady;
      if (_disposed) {
        return const StartDayResult(
            StartDayOutcome.alreadyRunning, LocationHealth.ok);
      }
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
    // `paused`, not `idle`. `idle` is the server's "open session, stationary"
    // and reports isSessionOpen == true, so it left the card showing a frozen
    // timer and a Break button, and made `startDay` refuse the resume.
    final double closedKm =
        _closeSession(open, WorkStatus.paused, reason: SessionEndReason.pause);
    _queueClose(open.id, reason: SessionEndReason.pause, measuredDistanceKm: closedKm);
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
        final double closedKm = _closeSession(
          open,
          WorkStatus.ended,
          reason: SessionEndReason.manual,
          finalFix: finalFix,
          endedAt: draft.endedAt,
        );
        // The closeout is the day's declaration; this is the session's own
        // end time, last fix and final odometer. Queued rather than awaited:
        // the day is already closed on the server, and a flaky connection at
        // this exact moment must not leave the employee staring at a spinner.
        _queueClose(
          open.id,
          reason: SessionEndReason.manual,
          endedAt: draft.endedAt,
          fix: finalFix,
          measuredDistanceKm: closedKm,
        );
      } else {
        _snapshot = _snapshot?.copyWith(status: WorkStatus.ended);
      }

      _snapshot = _snapshot?.copyWith(
        dayState: DayState.closedByEmployee,
        closeout: stored,
      );
      _addProvisional(ActivityType.dayEnded, draft.endedAt, 'Day ended');

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

    final double closedKm =
        _closeSession(open, WorkStatus.paused, reason: SessionEndReason.auto);
    // Reason `auto` goes up as `pause`: the session stops, the day does not.
    _queueClose(open.id, reason: SessionEndReason.auto, endedAt: _now, measuredDistanceKm: closedKm);
    _autoClosedAt = _now;
    _degradedSince = null;
    unawaited(_location.stopTracking());
    _stopTicker();
    notifyListeners();
  }

  /// Roll a locally-opened session back after the server refused to open it —
  /// `NON_WORKING_DAY` or `DAY_CLOSED` arrived after the device already started
  /// the day (the race case). Nothing crashed: the session simply never
  /// happened, so it is removed and a single notice is raised for Home.
  void _rejectStartedSession(_SessionOutbox entry, ApiException reason) {
    final String clientId = entry.clientId;
    _outbox.remove(clientId);

    WorkSession? removed;
    for (final WorkSession s in sessions) {
      if (s.id == clientId) {
        removed = s;
        break;
      }
    }

    final bool wasOpen = activeSession?.id == clientId;
    final List<WorkSession> remaining = sessions
        .where((WorkSession s) => s.id != clientId)
        .toList(growable: false);

    // Drop the provisional "Day started" / "Session N started" rows this
    // session put on the timeline, matched on its start instant.
    if (removed != null) {
      final DateTime at = removed.startTime;
      _localActivity.removeWhere((ActivityEvent e) =>
          e.provisional &&
          (e.type == ActivityType.sessionStarted ||
              e.type == ActivityType.dayStarted) &&
          (e.time.difference(at)).abs() < const Duration(seconds: 2));
    }

    final bool stillOpen =
        remaining.any((WorkSession s) => s.endTime == null);
    final WorkStatus nextStatus = stillOpen
        ? status
        : (remaining.isEmpty ? WorkStatus.notStarted : WorkStatus.paused);

    _snapshot = _snapshot?.copyWith(
      sessions: remaining,
      status: nextStatus,
      movement: MovementStatus.unknown,
    );

    if (wasOpen) {
      _openSessionBaselineKm = 0;
      _openSessionLocalKm = 0;
      _distanceAnchor = null;
      unawaited(_location.stopTracking());
      _stopTicker();
    }

    _startRejectedAt = DateTime.now();
    _startRejectedMessage = reason.message;
    notifyListeners();
  }

  /// Demo helper: puts the day back to "Not Started" so the start flow can be
  /// walked through. Not part of the production surface.
  Future<void> resetDay() async {
    await _location.stopTracking();
    _stopTicker();
    _snapshot = _emptySnapshot();
    _resetDistance();
    _localActivity.clear();
    _degradedSince = null;
    _autoClosedAt = null;
    _startRejectedAt = null;
    _startRejectedMessage = null;
    _outbox.clear();
    _outboxRetry?.cancel();
    _outboxRetry = null;
    _reportedHealth = null;
    notifyListeners();
  }

  Future<void> terminateDeletedAccount() async {
    // Clear first: stopTracking normally attempts a final queue flush.
    if (_location is GeoLocationService) {
      _location.discardDeletedAccountQueue();
    }
    await resetDay();
  }

  // ---------------------------------------------------------------- internals

  /// Returns the session's final odometer (`closedKm`) so the caller can hand
  /// it to [_queueClose] — it is the number the server stores as this session's
  /// headline distance (v3, migration 037).
  double _closeSession(
    WorkSession open,
    WorkStatus next, {
    required SessionEndReason reason,
    LocationLog? finalFix,
    DateTime? endedAt,
  }) {
    final DateTime end = endedAt ?? finalFix?.recordedAt ?? DateTime.now();

    // The closing fix is still travel; measure it before the chain ends with
    // the session.
    if (finalFix != null) _accumulateDistance(finalFix);
    _distanceAnchor = null;
    final double closedKm =
        max(_openSessionBaselineKm + _openSessionLocalKm, open.distanceKm);

    final List<WorkSession> updated = sessions
        .map((WorkSession s) => s.id == open.id
            ? WorkSession(
                id: s.id,
                index: s.index,
                startTime: s.startTime,
                endTime: end,
                distanceKm: closedKm,
                locationPoints: s.locationPoints,
                startLatitude: s.startLatitude,
                startLongitude: s.startLongitude,
                endReason: reason,
              )
            : s)
        .toList(growable: false);

    _snapshot = _snapshot?.copyWith(
      status: next,
      // Nothing is being tracked any more, so nothing is known about movement.
      // "Stationary" here was a guess dressed as a measurement.
      movement: MovementStatus.unknown,
      sessions: updated,
      lastFix: finalFix,
    );

    _addProvisional(
      ActivityType.sessionEnded,
      end,
      'Session ${open.index} ended',
      subtitle: '${closedKm.toStringAsFixed(1)} km tracked',
      duration: end.difference(open.startTime),
    );

    _openSessionBaselineKm = 0;
    _openSessionLocalKm = 0;
    _applyDistance();

    return closedKm;
  }

  /// Append a provisional timeline row (device-owned session boundary) unless an
  /// identical one is already pending. Server confirmation later supersedes it.
  void _addProvisional(
    ActivityType type,
    DateTime time,
    String title, {
    String? subtitle,
    Duration? duration,
  }) {
    final bool exists = _localActivity.any((ActivityEvent e) =>
        e.type == type &&
        (e.time.difference(time)).abs() < const Duration(seconds: 2));
    if (exists) return;

    _localActivity.add(ActivityEvent(
      id: 'local-${type.name}-${time.microsecondsSinceEpoch}',
      type: type,
      time: time,
      title: title,
      subtitle: subtitle,
      duration: duration,
      provisional: true,
    ));
  }

  /// Drop provisional rows the server has now confirmed, so the local list does
  /// not grow across a day of refreshes.
  void _pruneLocalActivity(List<ActivityEvent> server) {
    _localActivity.removeWhere(
        (ActivityEvent local) => _isSuperseded(local, server));
  }

  void _applyHealth(LocationHealth health) {
    _snapshot = (_snapshot ?? _emptySnapshot()).copyWith(
      locationHealth: health,
      status: status.isSessionOpen ? _statusForHealth(health) : status,
    );
  }

  /// Maps device-location health to the workday status shown on the hero card.
  ///
  /// A fix we actually have — even an approximate one worse than
  /// [TrackingConfig.minAccuracyMetres] — is still a live, working session,
  /// which is why accuracy has no say here and the legacy
  /// [LocationHealth.poorAccuracy] lands on the same arm as [LocationHealth.ok].
  /// "Location Unavailable" is reserved for the states where there is
  /// genuinely no position to record (service off, permission denied/revoked,
  /// background location refused). A dropped network is "Offline", not
  /// unavailable, because fixes keep queuing locally.
  WorkStatus _statusForHealth(LocationHealth health) => switch (health) {
        LocationHealth.ok || LocationHealth.poorAccuracy => WorkStatus.working,
        LocationHealth.noInternet => WorkStatus.offline,
        LocationHealth.serviceDisabled ||
        LocationHealth.permissionDenied ||
        LocationHealth.permissionDeniedForever ||
        LocationHealth.backgroundDenied =>
          WorkStatus.locationUnavailable,
      };

  // --------------------------------------------------------------- distance

  /// Day distance as it stands right now: the server's baseline plus every
  /// leg this device has measured since the server could have seen it, floored
  /// at whatever has already been displayed.
  double get liveDistanceKm => _displayedDistanceKm;

  double get _measuredDistanceKm {
    double tail = 0;
    for (final _DistanceLeg leg in _legs) {
      tail += leg.km;
    }
    return _serverDistanceKm + tail;
  }

  /// Measure the leg from the last counted fix to [fix], using the identical
  /// rule the server's universal engine (036) reconciles this number to.
  ///
  /// The hop must out-travel what the fix could be wrong by —
  /// `max(minStepMetres, accuracyStepFactor * fix.accuracy)` — not the OS
  /// *capture* filter (`distanceFilterMetres`), which is a different, smaller
  /// number whose job is keeping corners, not measuring. A weak 40 m fix that
  /// moved 200 m is travel and counts; one that moved 25 m cannot be told from
  /// noise and does not.
  ///
  /// Crucially the anchor **holds** on a rejected hop rather than advancing to
  /// [fix] — the server does exactly this, and for the same reason: advancing
  /// it is what lets noise accumulate in sub-threshold increments, while
  /// holding it keeps genuinely slow travel measurable because displacement
  /// from the held anchor keeps growing until it clears the step. Advancing
  /// the anchor on every fix is what made this device read higher than the
  /// server every day.
  void _accumulateDistance(LocationLog fix) {
    final LocationLog? previous = _distanceAnchor;
    if (previous == null) {
      _distanceAnchor = fix;
      return;
    }

    final double km = kmBetween(
      previous.latitude,
      previous.longitude,
      fix.latitude,
      fix.longitude,
    );

    final double stepMetres =
        max(config.minStepMetres, config.accuracyStepFactor * fix.accuracy);

    // Held, not advanced, when the hop is noise: the next fix is measured from
    // the same anchor so slow travel still clears the step eventually.
    if (km * 1000 < stepMetres) return;

    _distanceAnchor = fix;
    _legs.add(_DistanceLeg(fix.recordedAt, km));
    _openSessionLocalKm += km;
  }

  /// Fold [server] in as the new baseline and drop the legs it already covers.
  ///
  /// A leg is covered when it was recorded at or before the last upload this
  /// device completed — that is precisely the set of fixes the server could
  /// have run `refreshSession` over. Legs newer than that are still the
  /// device's alone and stay on top.
  void _reconcileDistance(double server, {required DateTime? syncedAt}) {
    _serverDistanceKm = server;
    if (syncedAt == null) {
      // Nothing has ever reached the server from this device, so nothing it
      // reports can include our legs: keep them all and show server + legs.
      // (Clearing them here is what used to force the upward-only ratchet —
      // without the legs the measured number collapsed to a bare server value
      // that a pre-upload refresh could read as 0.)
    } else {
      _legs.removeWhere((_DistanceLeg leg) => !leg.at.isAfter(syncedAt));
    }
    _applyDistance();
  }

  /// Push the reconciled number into the snapshot so every consumer — hero
  /// card, Home summary, Profile, the closeout sheet's "GPS recorded" line —
  /// reads one value.
  void _applyDistance() {
    final HomeSnapshot? snap = _snapshot;
    if (snap == null) return;

    // Monotonic within a day: the measured number — server baseline plus the
    // device's own un-synced legs — may raise the displayed figure and never
    // lower it. The server now STORES this device's odometer (v3, migration
    // 037) rather than a fix-derived number that could reconcile downward, so
    // `_serverDistanceKm` comes back equal to what this device reported and the
    // floor only ever guards a transient dip (a refresh that lands between a leg
    // and its sync). A travel figure that ticks backwards reads as a bug, not as
    // precision — which is exactly what the old derived-and-collapsing headline
    // did on Day-End, and what this whole change removes.
    _displayedDistanceKm = max(_displayedDistanceKm, _measuredDistanceKm);

    final WorkSession? open = snap.activeSession;
    final double openKm = _openSessionBaselineKm + _openSessionLocalKm;

    _snapshot = snap.copyWith(
      summary: snap.summary.copyWith(distanceKm: _displayedDistanceKm),
      sessions: open == null || openKm <= open.distanceKm
          ? null
          : snap.sessions
              .map((WorkSession s) => s.id == open.id
                  ? WorkSession(
                      id: s.id,
                      index: s.index,
                      startTime: s.startTime,
                      endTime: s.endTime,
                      distanceKm: openKm,
                      locationPoints: s.locationPoints,
                      startLatitude: s.startLatitude,
                      startLongitude: s.startLongitude,
                      endReason: s.endReason,
                    )
                  : s)
              .toList(growable: false),
    );
  }

  /// Forget everything measured locally — a new day, or a reset.
  void _resetDistance() {
    _legs.clear();
    _distanceAnchor = null;
    _serverDistanceKm = 0;
    _displayedDistanceKm = 0;
    _openSessionBaselineKm = 0;
    _openSessionLocalKm = 0;
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

    // One movement truth, computed in LocationService (debounced, and the same
    // decision that picks the GPS cadence) and only mirrored here. The
    // controller deliberately does not derive it from fix.speedKmh a second
    // time: a raw per-fix comparison flickers at the threshold and would
    // disagree with the cadence the radio is actually running at.
    _movementSub?.cancel();
    _movementSub = _location.movementState().listen((MovementStatus m) {
      if (movement == m) return;
      _snapshot = _snapshot?.copyWith(movement: m);
      notifyListeners();
    });

    final WorkSession? open = activeSession;
    if (open == null) return;
    _fixSub?.cancel();
    _fixSub = _location.track(sessionId: open.id).listen((LocationLog fix) {
      _accumulateDistance(fix);
      _snapshot = _snapshot?.copyWith(lastFix: fix);
      _applyDistance();
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
    double? measuredDistanceKm,
  }) {
    final _SessionOutbox entry = _outbox.putIfAbsent(
      sessionId,
      () => _SessionOutbox(clientId: sessionId),
    );

    entry.closeReason = reason;
    entry.closedAt = endedAt ?? DateTime.now();
    entry.closeFix = fix ?? entry.closeFix;
    // The session's final odometer, carried to the server on close as the
    // authoritative headline distance (v3, migration 037).
    entry.measuredDistanceKm = measuredDistanceKm ?? entry.measuredDistanceKm;

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
          } on ApiException catch (e) {
            // A terminal "today is not workable" verdict — the day closed, or
            // it is a holiday/weekend — between the device deciding to start and
            // this call landing. Retrying would loop forever, so roll the local
            // session back and surface one notice. NON_WORKING_DAY is handled
            // exactly like DAY_CLOSED: they share this one path.
            if (e.code == 'NON_WORKING_DAY' || e.code == 'DAY_CLOSED') {
              _rejectStartedSession(entry, e);
              continue;
            }
            // Anything else (offline, server down, a timeout) is transient. The
            // clientId makes the retry safe: a repeat returns the session the
            // server already created.
            debugPrint('[tracking] session open still pending: $e');
            continue;
          } catch (e) {
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
              measuredDistanceKm: entry.measuredDistanceKm,
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
  /// ids the server settled, so the queue can drop exactly those, plus the
  /// `liveUntil` deadline from the response **`meta`** (§3.11) — the one way
  /// this device ever finds out an admin is watching it. Throws through on a
  /// network failure — the queue reads that as "offline" and keeps everything.
  Future<UploadOutcome> uploadQueuedFixes(
    String sessionId,
    List<LocationLog> batch,
  ) async {
    final _SessionOutbox? entry = _outbox[sessionId];

    if (entry == null) {
      // Fixes for a session this controller has no record of. Nothing can be
      // settled and nothing is thrown: the device is not offline, it just
      // cannot address these.
      debugPrint('[tracking] queued fixes for unknown session $sessionId');
      return UploadOutcome.nothing;
    }

    final String? serverId = entry.serverId;

    if (serverId == null) {
      // The session itself has not landed yet. Getting that done is what
      // unblocks the fixes; they stay queued until it does.
      unawaited(syncPendingWork());
      return UploadOutcome.nothing;
    }

    // Ride the open session's live odometer on its own batches, so the admin
    // dashboard tracks the phone within one sync instead of waiting for close
    // (v3, migration 037). A draining batch for an already-closed session
    // carries nothing — that session's final odometer went up with its close,
    // and this device no longer measures it.
    final WorkSession? open = activeSession;
    final double? measuredDistanceKm = open != null && open.id == sessionId
        ? _openSessionBaselineKm + _openSessionLocalKm
        : null;

    final LocationSyncResult result = await _sync.pushLocations(
      sessionId: serverId,
      fixes: batch,
      measuredDistanceKm: measuredDistanceKm,
    );

    return UploadOutcome(result.settled, liveUntil: result.liveUntil);
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
    _now = DateTime.now();
    _clock.value = _now;
    // Raise the ongoing notification (and its location foreground service) for
    // the open session, seeded with the current elapsed time.
    unawaited(_sessionNotif?.start(sessionElapsedAt(_now)));
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      _now = DateTime.now();
      _tick++;
      // Keep the notification's timer in step with the in-app one.
      unawaited(_sessionNotif?.update(sessionElapsedAt(_now)));
      // GPS being switched off mid-session is silent — nothing pushes an event
      // for it — so it has to be looked for. The watchdog below is blind
      // without this. Notifies on a real change only, from inside _pollHealth.
      if (_tick % 15 == 0) unawaited(_pollHealth());
      // Anything the server has not been told yet. Cheap when there is
      // nothing owed — the drain returns on the first check — and it is what
      // turns a session opened on a dead network into a real one the moment
      // the network comes back.
      if (_tick % 30 == 0) unawaited(syncPendingWork());
      // Compares against _now; may auto-close (which notifies) on its own.
      _evaluateWatchdog();
      // Drive ONLY the live elapsed-time widgets. Deliberately no
      // notifyListeners() here: the general listeners rebuild on real state
      // change (health, status, snapshot, sync, session open/close), not every
      // second. This is the whole point of P1.1.
      _clock.value = _now;
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

  /// A health value pushed by the service (the OS location toggle moved).
  ///
  /// Routed by whether a session is open: an open session's health is session
  /// state the server is told about, an idle reading is only a prompt for
  /// Home. Both notify, because both change what the screen should say.
  void _onHealthPushed(LocationHealth health) {
    if (_disposed) return;

    // Which branch a reading took is the whole question when watching this on
    // a device: session health goes to the server, an idle reading only
    // prompts Home.
    debugPrint('[tracking] health ${health.name} '
        '(session ${status.isSessionOpen ? 'open' : 'closed'}, '
        'status ${status.name})');

    if (status.isSessionOpen) {
      if (_snapshot == null || health == locationHealth) return;
      _applyHealth(health);
      unawaited(_pushHealth(health));
      notifyListeners();
      return;
    }

    if (health == _idleHealth) return;
    _idleHealth = health;
    notifyListeners();
  }

  /// Force a health re-read. For the moment the employee returns from the
  /// system settings app, where nothing observable to this process happened.
  Future<void> refreshLocationHealth() async {
    final LocationHealth health = await _location.health();
    if (_disposed) return;
    _onHealthPushed(health);
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
    _movementSub?.cancel();
    _movementSub = null;
    _distanceAnchor = null;
    // The session is no longer open — take the ongoing notification and its
    // foreground service down with it.
    unawaited(_sessionNotif?.stop());
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
    _movementSub?.cancel();
    _healthSub?.cancel();
    unawaited(_sessionNotif?.stop());
    _endDayRequests.dispose();
    _clock.dispose();
    super.dispose();
  }
}

/// One session's unfinished business with the server.
///
/// It exists because the device is the source of truth for *when* a session
/// started and stopped, while the server is the source of truth for everything
/// derived from it — and on this app's network the two are frequently not
/// reconciled at the moment the employee taps the button.
/// One measured hop between two consecutive accepted fixes, stamped with when
/// it happened so a server reconcile can retire exactly the hops it covers.
class _DistanceLeg {
  const _DistanceLeg(this.at, this.km);

  final DateTime at;
  final double km;
}

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

  /// The session's final odometer, sent on close as its headline distance
  /// (v3, migration 037). Null for an older build or a close that measured
  /// nothing.
  double? measuredDistanceKm;

  bool get needsOpen => serverId == null;

  bool get needsClose => closeReason != null && !closeSent && serverId != null;
}
