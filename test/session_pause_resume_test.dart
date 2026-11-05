import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/core/utils/formatters.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// The Break button, and the timestamp rendering it sits next to.
///
/// Both bugs here were reported from the field as "the time is wrong" and
/// "Break does nothing". Neither was cosmetic: the first rendered a UTC wall
/// clock, and the second left the employee unable to resume their own open
/// day. The tests are grouped by the invariant each one pins, because each was
/// a place the old code was silently self-consistent.

/// A repository whose `home()` returns whatever snapshot the test hands it.
///
/// This is the only way to exercise the decode-time repair: the bug only
/// appears when the **server's** view of a paused day (`status: ended`,
/// `dayState: open`) is adopted, which is what happens on a cold start or a
/// re-login while on break.
class _SnapshotRepository implements EmployeeRepository {
  _SnapshotRepository(this.snapshot);

  HomeSnapshot snapshot;

  @override
  Future<HomeSnapshot> home() async => snapshot;

  @override
  Future<TrackingConfig> config() async => TrackingConfig.defaults;

  @override
  dynamic noSuchMethod(Invocation invocation) =>
      throw UnimplementedError('${invocation.memberName} is not used here');
}

HomeSnapshot _snapshot({
  required WorkStatus status,
  required DayState dayState,
  required List<WorkSession> sessions,
}) =>
    HomeSnapshot(
      status: status,
      movement: MovementStatus.unknown,
      locationHealth: LocationHealth.ok,
      sessions: sessions,
      summary: DaySummary.empty,
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
      dayState: dayState,
      closeout: null,
    );

WorkSession _closedSession(int index) => WorkSession(
      id: 'ses_$index',
      index: index,
      startTime: DateTime(2026, 1, 1, 9),
      endTime: DateTime(2026, 1, 1, 11),
      distanceKm: 4,
      locationPoints: 12,
      startLatitude: 23.24,
      startLongitude: 87.86,
      endReason: SessionEndReason.pause,
    );

void main() {
  group('WorkStatus vocabulary', () {
    test('paused is not a session-open state, idle still is', () {
      // This single pair is the whole Break fix. `idle` is the server's
      // "open session, stationary" (API plan §3.14 row 7) and must stay open;
      // `paused` is "between sessions, day still open" and must not. Collapsing
      // the two is what made the card render a frozen timer as live and made
      // `startDay` refuse to resume.
      expect(WorkStatus.paused.isSessionOpen, isFalse);
      expect(WorkStatus.idle.isSessionOpen, isTrue);
    });

    test('paused is not degraded — it is deliberate, not a fault', () {
      expect(WorkStatus.paused.isDegraded, isFalse);
    });

    test('paused reads as a break, not as a stop or an ending', () {
      expect(WorkStatus.paused.label, 'On Break');
      expect(WorkStatus.paused.label, isNot(WorkStatus.ended.label));
      expect(WorkStatus.paused.label, isNot(WorkStatus.idle.label));
    });
  });

  group('pause and resume', () {
    late MockLocationService location;
    late TrackingController tracking;

    setUp(() {
      location = MockLocationService(emitInterval: const Duration(minutes: 5));
      tracking = TrackingController(
        repository: MockEmployeeRepository(latency: Duration.zero),
        locationService: location,
      );
    });

    tearDown(() {
      tracking.dispose();
      location.dispose();
    });

    test('Break closes the session and leaves the day resumable', () async {
      await tracking.startDay();
      expect(tracking.status, WorkStatus.working);
      expect(tracking.activeSession, isNotNull);

      await tracking.pauseSession();

      // The status the UI switches on. `idle` here was the bug.
      expect(tracking.status, WorkStatus.paused);
      expect(tracking.status.isSessionOpen, isFalse,
          reason: 'the card must stop rendering a live timer');
      expect(tracking.activeSession, isNull);
      expect(tracking.isDayLocked, isFalse,
          reason: 'a break must not close the day');
    });

    test('Resume after Break opens a second session', () async {
      await tracking.startDay();
      final String? first = tracking.activeSession?.id;
      await tracking.pauseSession();

      // The exact call the "Resume session" button makes. Before the fix this
      // returned `alreadyRunning` and did nothing, because `startDay` opens
      // with `if (status.isSessionOpen) return` and `idle` satisfied it.
      final StartDayResult result = await tracking.startDay();

      expect(result.outcome, StartDayOutcome.started);
      expect(tracking.status, WorkStatus.working);
      expect(tracking.activeSession, isNotNull);
      expect(tracking.activeSession!.id, isNot(first));
      expect(tracking.sessions, hasLength(2));
    });

    test('worked time survives the break and keeps accruing', () async {
      await tracking.startDay();
      await tracking.pauseSession();
      final Duration afterPause = tracking.workedToday;

      await tracking.startDay();
      expect(tracking.workedToday, greaterThanOrEqualTo(afterPause),
          reason: 'the first session still counts toward the day');
      expect(tracking.sessions.first.endTime, isNotNull);
    });
  });

  group('server snapshot cannot express a break', () {
    late MockLocationService location;

    setUp(() {
      location = MockLocationService(emitInterval: const Duration(minutes: 5));
    });

    tearDown(() => location.dispose());

    /// `Engine::liveStatus` collapses "no open session" to `ended` as soon as
    /// the day has any sessions — locked or not. Adopted verbatim that renders
    /// a **disabled** "Day completed" button, which locked an employee out of
    /// their own open day after a relaunch while on break.
    test('ended + open day + only closed sessions is repaired to paused',
        () async {
      final TrackingController tracking = TrackingController(
        repository: _SnapshotRepository(_snapshot(
          status: WorkStatus.ended,
          dayState: DayState.open,
          sessions: <WorkSession>[_closedSession(1)],
        )),
        locationService: location,
      );
      addTearDown(tracking.dispose);

      await tracking.load();

      expect(tracking.status, WorkStatus.paused);
      expect(tracking.isDayLocked, isFalse);
    });

    test('a genuinely closed day stays ended', () async {
      final TrackingController tracking = TrackingController(
        repository: _SnapshotRepository(_snapshot(
          status: WorkStatus.ended,
          dayState: DayState.closedByEmployee,
          sessions: <WorkSession>[_closedSession(1)],
        )),
        locationService: location,
      );
      addTearDown(tracking.dispose);

      await tracking.load();

      expect(tracking.status, WorkStatus.ended,
          reason: 'a closed-out day must keep its dead primary action');
      expect(tracking.isDayLocked, isTrue);
    });

    test('an auto-closed day stays ended', () async {
      final TrackingController tracking = TrackingController(
        repository: _SnapshotRepository(_snapshot(
          status: WorkStatus.ended,
          dayState: DayState.closedBySystem,
          sessions: <WorkSession>[_closedSession(1)],
        )),
        locationService: location,
      );
      addTearDown(tracking.dispose);

      await tracking.load();

      expect(tracking.status, WorkStatus.ended);
    });

    test('a day with no sessions at all is not a break', () async {
      final TrackingController tracking = TrackingController(
        repository: _SnapshotRepository(_snapshot(
          status: WorkStatus.notStarted,
          dayState: DayState.open,
          sessions: const <WorkSession>[],
        )),
        locationService: location,
      );
      addTearDown(tracking.dispose);

      await tracking.load();

      expect(tracking.status, WorkStatus.notStarted);
    });

    test('an open session is never repaired to paused', () async {
      final WorkSession open = WorkSession(
        id: 'ses_open',
        index: 1,
        startTime: DateTime(2026, 1, 1, 9),
        endTime: null,
        distanceKm: 1,
        locationPoints: 3,
        startLatitude: 23.24,
        startLongitude: 87.86,
      );
      final TrackingController tracking = TrackingController(
        // A contradictory payload on purpose: `ended` alongside a session the
        // server says is still open. The repair must not fire on it.
        repository: _SnapshotRepository(_snapshot(
          status: WorkStatus.ended,
          dayState: DayState.open,
          sessions: <WorkSession>[open],
        )),
        locationService: location,
      );
      addTearDown(tracking.dispose);

      await tracking.load();

      expect(tracking.status, isNot(WorkStatus.paused));
    });
  });

  group('timestamps render in local time', () {
    test('Fmt.time renders a UTC-flagged value as local wall clock', () {
      // Built from a local wall clock, then flagged UTC — so the assertion is
      // a hard literal rather than a mirror of the thing under test. On
      // Asia/Kolkata (+05:30, the deployment zone) the pre-fix output was
      // "03:05", which is the reported symptom exactly. On a UTC CI box the
      // offset is zero and this can only pass, which is why the guard below
      // states the precondition instead of silently weakening.
      final DateTime local = DateTime(2026, 11, 5, 8, 35);
      expect(Fmt.time(local.toUtc()), '08:35 AM');

      if (DateTime.now().timeZoneOffset == Duration.zero) {
        markTestSkipped(
          'host is UTC: this assertion cannot distinguish the bug. Run in a '
          'zone with a non-zero offset (deployment is Asia/Kolkata).',
        );
      }
    });

    test('Fmt.time is unchanged for an already-local value', () {
      final DateTime local = DateTime(2026, 11, 5, 8, 35);
      expect(Fmt.time(local), '08:35 AM');
    });

    test('date formatters agree across both flags', () {
      final DateTime utc = DateTime.utc(2026, 11, 5, 3, 5);
      expect(Fmt.mediumDate(utc), Fmt.mediumDate(utc.toLocal()));
      expect(Fmt.longDate(utc), Fmt.longDate(utc.toLocal()));
      expect(Fmt.weekdayShort(utc), Fmt.weekdayShort(utc.toLocal()));
      expect(Fmt.monthDay(utc), Fmt.monthDay(utc.toLocal()));
      expect(Fmt.monthYear(utc), Fmt.monthYear(utc.toLocal()));
    });

    test('a fix restored from the durable queue comes back local', () {
      final LocationLog original = LocationLog(
        id: 'loc_1',
        sessionId: 'ses_1',
        latitude: 23.24,
        longitude: 87.86,
        accuracy: 8,
        speedKmh: 0,
        recordedAt: DateTime.utc(2026, 11, 5, 3, 5),
        syncState: SyncState.queued,
      );

      final LocationLog back = LocationLog.fromJson(original.toJson());

      expect(back.recordedAt.isUtc, isFalse);
      expect(back.recordedAt.isAtSameMomentAs(original.recordedAt), isTrue,
          reason: 'the instant must survive; only the flag is normalised');
      expect(Fmt.time(back.recordedAt), Fmt.time(original.recordedAt));
    });
  });
}
