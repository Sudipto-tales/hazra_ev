import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/mock/holiday_store.dart';
import 'package:employeetracking_mobile_app/data/mock/mock_data.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/tracking_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// The two halves of the non-working-day guard:
///  - the proactive refusal, when the day snapshot already says so;
///  - the race backstop, when `POST /tracking/sessions` 409s after the device
///    has already opened the session locally.
void main() {
  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 20));

  test('a holiday today refuses Start Day without opening anything', () async {
    // A weekend-day set that contains today makes the mock home() report the
    // day as non-working.
    final HolidayStore store =
        HolidayStore(weekendDays: <int>[MockData.today.weekday]);
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final _FakeTrackingRepository server = _FakeTrackingRepository();
    final TrackingController tracking = TrackingController(
      repository:
          MockEmployeeRepository(latency: Duration.zero, holidays: store),
      locationService: location,
      trackingRepository: server,
    );
    addTearDown(tracking.dispose);
    addTearDown(location.dispose);

    final StartDayResult result = await tracking.startDay();

    expect(result.outcome, StartDayOutcome.nonWorkingDay);
    expect(tracking.isNonWorkingDay, isTrue);
    expect(tracking.activeSession, isNull,
        reason: 'nothing may open on a non-working day');
    await settle();
    expect(server.openAttempts, 0,
        reason: 'the server is never asked to open a refused session');
  });

  test('a NON_WORKING_DAY 409 rolls the local session back, no crash',
      () async {
    // The snapshot said the day was workable, so the device opened a session.
    // The server then refuses it — the race case — and the controller must roll
    // back cleanly and raise one notice.
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final _FakeTrackingRepository server = _FakeTrackingRepository()
      ..rejectWith = 'NON_WORKING_DAY';
    final TrackingController tracking = TrackingController(
      repository: MockEmployeeRepository(
        latency: Duration.zero,
        holidays: HolidayStore(weekendDays: const <int>[], seed: false),
      ),
      locationService: location,
      trackingRepository: server,
    );
    addTearDown(tracking.dispose);
    addTearDown(location.dispose);

    // The day starts locally — the tap never waits on the network, so Start Day
    // itself succeeds. (In this zero-latency fake the outbox then rejects almost
    // immediately; against a real server the session lives until the 409.)
    final StartDayResult result = await tracking.startDay();
    expect(result.outcome, StartDayOutcome.started);

    // The outbox drains, the 409 comes back, and the session is rolled back.
    await settle();
    await tracking.syncPendingWork();
    await settle();

    expect(tracking.activeSession, isNull);
    expect(tracking.status, WorkStatus.notStarted);
    expect(tracking.startRejectedAt, isNotNull);
    expect(tracking.startRejectedMessage, isNotNull);

    // The notice clears, and it was a rejection — not a crash.
    tracking.acknowledgeStartRejection();
    expect(tracking.startRejectedAt, isNull);
  });

  test('DAY_CLOSED is handled on the same path as NON_WORKING_DAY', () async {
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final _FakeTrackingRepository server = _FakeTrackingRepository()
      ..rejectWith = 'DAY_CLOSED';
    final TrackingController tracking = TrackingController(
      repository: MockEmployeeRepository(
        latency: Duration.zero,
        holidays: HolidayStore(weekendDays: const <int>[], seed: false),
      ),
      locationService: location,
      trackingRepository: server,
    );
    addTearDown(tracking.dispose);
    addTearDown(location.dispose);

    await tracking.startDay();
    await settle();
    await tracking.syncPendingWork();
    await settle();

    expect(tracking.activeSession, isNull);
    expect(tracking.startRejectedAt, isNotNull);
  });
}

/// Opens throw a chosen contract code when [rejectWith] is set, succeed
/// otherwise. Everything else is a no-op — these tests only care about the open.
class _FakeTrackingRepository implements TrackingRepository {
  String? rejectWith;
  int openAttempts = 0;

  @override
  Future<WorkSession> openSession({
    required String clientId,
    required LocationLog fix,
    DateTime? startedAt,
  }) async {
    openAttempts++;
    final String? code = rejectWith;
    if (code != null) {
      throw ApiException(code: code, message: 'Today is not workable', status: 409);
    }
    return WorkSession(
      id: 'srv_$clientId',
      index: 1,
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
    double? measuredDistanceKm,
  }) async =>
      WorkSession(
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

  @override
  Future<LocationSyncResult> pushLocations({
    required String sessionId,
    required List<LocationLog> fixes,
    double? measuredDistanceKm,
  }) async =>
      LocationSyncResult.empty;

  @override
  Future<void> reportHealth({
    required LocationHealth health,
    bool isOnline = true,
    String? permission,
  }) async {}
}
