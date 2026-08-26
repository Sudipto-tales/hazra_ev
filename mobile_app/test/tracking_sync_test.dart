import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/tracking_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// The tracking write path, exercised without a server.
///
/// What is actually being checked here is the offline story: a day starts on
/// the device whether or not the network is there, and the server hears about
/// it eventually and exactly once. The fake below is not a simulator — it is a
/// switch for "the call lands" versus "the call throws", which is the only
/// distinction the controller's retry logic turns on.
class _FakeTrackingRepository implements TrackingRepository {
  bool failOpen = false;
  bool failClose = false;
  bool failPush = false;

  final List<String> opened = <String>[];
  final List<String> closed = <String>[];
  final List<SessionEndReason> closeReasons = <SessionEndReason>[];
  final List<String> pushedFixIds = <String>[];
  final List<String> pushedSessionIds = <String>[];
  final List<LocationHealth> healthPings = <LocationHealth>[];

  /// clientId → server id, so a repeated open returns the same session the way
  /// the real endpoint's `(employee_id, client_id)` lookup does.
  final Map<String, String> _serverIds = <String, String>{};
  int _seq = 0;

  @override
  Future<WorkSession> openSession({
    required String clientId,
    required LocationLog fix,
    DateTime? startedAt,
  }) async {
    if (failOpen) throw StateError('network down');

    opened.add(clientId);
    final String serverId =
        _serverIds.putIfAbsent(clientId, () => 'srv_${++_seq}');

    return WorkSession(
      id: serverId,
      index: _seq,
      startTime: startedAt ?? fix.recordedAt,
      endTime: null,
      distanceKm: 0,
      locationPoints: 1,
      startLatitude: fix.latitude,
      startLongitude: fix.longitude,
    );
  }

  @override
  Future<WorkSession> closeSession({
    required String sessionId,
    required SessionEndReason reason,
    LocationLog? fix,
    DateTime? endedAt,
  }) async {
    if (failClose) throw StateError('network down');

    closed.add(sessionId);
    closeReasons.add(reason);

    return WorkSession(
      id: sessionId,
      index: 1,
      startTime: endedAt ?? DateTime.now(),
      endTime: endedAt ?? DateTime.now(),
      distanceKm: 0,
      locationPoints: 0,
      startLatitude: 0,
      startLongitude: 0,
      endReason: reason,
    );
  }

  @override
  Future<LocationSyncResult> pushLocations({
    required String sessionId,
    required List<LocationLog> fixes,
  }) async {
    if (failPush) throw StateError('network down');

    pushedSessionIds.add(sessionId);
    pushedFixIds.addAll(fixes.map((LocationLog f) => f.id));

    return LocationSyncResult(
      accepted: fixes.map((LocationLog f) => f.id).toList(),
      rejected: const <RejectedFix>[],
    );
  }

  @override
  Future<void> reportHealth({
    required LocationHealth health,
    bool isOnline = true,
    String? permission,
  }) async {
    healthPings.add(health);
  }
}

/// A single fix belonging to [sessionId], the way the queue would hold it.
LocationLog _fix(String sessionId, String id) => LocationLog(
      id: id,
      sessionId: sessionId,
      latitude: 23.24,
      longitude: 87.86,
      accuracy: 8,
      speedKmh: 12,
      recordedAt: DateTime.now(),
      syncState: SyncState.queued,
    );

void main() {
  late _FakeTrackingRepository server;
  late MockLocationService location;
  late TrackingController tracking;

  setUp(() {
    server = _FakeTrackingRepository();
    // A long emit interval keeps the mock's own timers out of the way; these
    // tests drive the controller directly rather than waiting on fixes.
    location = MockLocationService(emitInterval: const Duration(minutes: 5));
    tracking = TrackingController(
      repository: MockEmployeeRepository(latency: Duration.zero),
      locationService: location,
      trackingRepository: server,
    );
  });

  tearDown(() {
    tracking.dispose();
    location.dispose();
  });

  /// The outbox drains off an unawaited future; give it the event-loop turns
  /// it needs rather than a wall-clock delay.
  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 20));

  test('start day opens the session server-side without blocking the tap',
      () async {
    final StartDayResult result = await tracking.startDay();
    expect(result.outcome, StartDayOutcome.started);

    // The local session exists the moment startDay returns.
    final WorkSession? open = tracking.activeSession;
    expect(open, isNotNull);

    await settle();
    expect(server.opened, hasLength(1));
    expect(server.opened.single, open!.id,
        reason: 'the device session id is the clientId the server dedupes on');

    // And the queue can now address the session by whatever the server calls
    // it, not by the device's own id.
    final Set<String> settled =
        await tracking.uploadQueuedFixes(open.id, <LocationLog>[
      _fix(open.id, 'loc_1'),
    ]);

    expect(settled, <String>{'loc_1'});
    expect(server.pushedSessionIds.single, startsWith('srv_'));
  });

  test('a day started offline still runs, and the fixes wait for the open',
      () async {
    server.failOpen = true;

    final StartDayResult result = await tracking.startDay();
    // The employee's day starts. It has to: the alternative is refusing to
    // work because a server did not answer.
    expect(result.outcome, StartDayOutcome.started);

    final String sessionId = tracking.activeSession!.id;
    await settle();
    expect(server.opened, isEmpty);

    // Nothing is settled and nothing is thrown — the fixes stay queued rather
    // than being reported as delivered.
    expect(
      await tracking.uploadQueuedFixes(sessionId, <LocationLog>[
        _fix(sessionId, 'loc_1'),
      ]),
      isEmpty,
    );
    expect(server.pushedFixIds, isEmpty);

    // Network back.
    server.failOpen = false;
    await tracking.syncPendingWork();

    expect(server.opened, <String>[sessionId]);
    expect(
      await tracking.uploadQueuedFixes(sessionId, <LocationLog>[
        _fix(sessionId, 'loc_1'),
      ]),
      <String>{'loc_1'},
    );
  });

  test('a retried open does not create a second session', () async {
    await tracking.startDay();
    await settle();

    await tracking.syncPendingWork();
    await tracking.syncPendingWork();

    expect(server.opened, hasLength(1),
        reason: 'the open is only attempted while it has not landed');
  });

  test('a close that fails is kept and retried, never dropped', () async {
    await tracking.startDay();
    await settle();
    final String sessionId = server.opened.single;

    server.failClose = true;
    await tracking.pauseSession();
    await settle();

    expect(server.closed, isEmpty);
    // The session is closed on the device — the employee is on a break — but
    // the server has not been told, and the outbox still owes it.
    expect(tracking.activeSession, isNull);

    server.failClose = false;
    await tracking.syncPendingWork();

    expect(server.closed, hasLength(1));
    expect(server.closeReasons.single, SessionEndReason.pause);
    expect(server.pushedSessionIds, isEmpty);
    expect(sessionId, isNotEmpty);
  });

  test('an upload failure throws through so the queue keeps the fixes',
      () async {
    await tracking.startDay();
    await settle();
    final String sessionId = tracking.activeSession!.id;

    server.failPush = true;

    await expectLater(
      tracking.uploadQueuedFixes(sessionId, <LocationLog>[
        _fix(sessionId, 'loc_1'),
      ]),
      throwsA(isA<StateError>()),
      reason: 'a swallowed failure would silently lose the fix',
    );
  });

  test('fixes for a session the controller never saw settle nothing',
      () async {
    expect(
      await tracking.uploadQueuedFixes('ses_from_a_previous_run',
          <LocationLog>[_fix('ses_from_a_previous_run', 'loc_1')]),
      isEmpty,
    );
  });

  test('device health is reported on a change, not on every poll', () async {
    await tracking.startDay();
    await settle();

    expect(server.healthPings, <LocationHealth>[LocationHealth.ok]);
  });

  test('the no-op repository hands the device id back unchanged', () async {
    const NoopTrackingRepository noop = NoopTrackingRepository();

    final WorkSession session = await noop.openSession(
      clientId: 'ses_local',
      fix: _fix('ses_local', 'loc_1'),
    );

    expect(session.id, 'ses_local');
    // And it never claims a fix reached anything.
    expect(
      (await noop.pushLocations(
        sessionId: 'ses_local',
        fixes: <LocationLog>[_fix('ses_local', 'loc_1')],
      ))
          .settled,
      isEmpty,
    );
  });
}
