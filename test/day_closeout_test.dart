import 'package:employeetracking_mobile_app/data/mock/day_lock_store.dart';
import 'package:employeetracking_mobile_app/data/mock/mock_data.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:flutter_test/flutter_test.dart';

/// The day lock, end to end across both sides of the mock backend.
///
/// The rule under test: a session ending is not a day ending. Sessions close
/// all the time; the day closes once, when the employee submits a declaration,
/// and only an admin can undo that.
void main() {
  late DayLockStore lock;
  late MockEmployeeRepository employee;
  late MockAdminRepository admin;

  setUp(() {
    lock = DayLockStore();
    employee = MockEmployeeRepository(latency: Duration.zero, dayLock: lock);
    admin = MockAdminRepository(latency: Duration.zero, dayLock: lock);
  });

  DayCloseoutDraft draft({
    String clientId = 'cid-1',
    double km = 40,
    int visits = 4,
    int rating = 4,
  }) =>
      DayCloseoutDraft(
        clientId: clientId,
        endedAt: DateTime.now(),
        declaredDistanceKm: km,
        declaredVisits: visits,
        rating: rating,
        tags: const <DayFeedbackTag>[DayFeedbackTag.traffic],
        feedback: 'Long day.',
      );

  group('closing the day', () {
    test('the day starts open', () async {
      final HomeSnapshot snap = await employee.home();
      expect(snap.dayState, DayState.open);
      expect(snap.dayState.isLocked, isFalse);
      expect(snap.closeout, isNull);
    });

    test('submitting a declaration locks the day', () async {
      await employee.submitDayCloseout(draft());

      final HomeSnapshot snap = await employee.home();
      expect(snap.dayState, DayState.closedByEmployee);
      expect(snap.dayState.isLocked, isTrue);
      expect(snap.status, WorkStatus.ended);
      expect(snap.closeout, isNotNull);
    });

    test('the declaration keeps the GPS figures beside the claimed ones',
        () async {
      final DayCloseout stored =
          await employee.submitDayCloseout(draft(km: 40, visits: 4));

      expect(stored.declaredDistanceKm, 40);
      expect(stored.declaredVisits, 4);
      expect(stored.measuredDistanceKm, MockData.todaySummary.distanceKm);
      expect(
        stored.measuredDistanceKm,
        isNot(equals(stored.declaredDistanceKm)),
        reason: 'the claim must not overwrite the measurement',
      );
    });

    test('a retry with the same clientId does not close the day twice',
        () async {
      final DayCloseout first = await employee.submitDayCloseout(draft());
      final DayCloseout again =
          await employee.submitDayCloseout(draft(km: 999, visits: 99));

      expect(again.id, first.id);
      expect(again.declaredDistanceKm, first.declaredDistanceKm);
    });

    test('deviation is signed against the measurement', () async {
      final DayCloseout stored = await employee.submitDayCloseout(
        draft(km: MockData.todaySummary.distanceKm * 2),
      );

      expect(stored.distanceDeviationPercent, closeTo(100, 0.001));
      expect(stored.distanceLooksOff, isTrue);
    });
  });

  group('admin reopen', () {
    test('reopening puts the employee back to an open day', () async {
      await employee.submitDayCloseout(draft());
      expect((await employee.home()).dayState.isLocked, isTrue);

      await admin.reopenDay(
        employeeId: MockData.employee.id,
        date: MockData.today,
        reason: 'Employee closed by mistake',
      );

      final HomeSnapshot snap = await employee.home();
      expect(snap.dayState, DayState.open);
      expect(snap.status, WorkStatus.working);
    });

    test('the original declaration survives the reopen', () async {
      final DayCloseout stored = await employee.submitDayCloseout(draft());
      await admin.reopenDay(
        employeeId: MockData.employee.id,
        date: MockData.today,
        reason: 'Extra site visit approved',
      );

      expect(lock.reopens, hasLength(1));
      expect(lock.reopens.single.closeout.id, stored.id);
      expect(lock.reopens.single.reason, 'Extra site visit approved');
    });

    test('the admin day view shows the lock and the declaration', () async {
      await employee.submitDayCloseout(draft(rating: 2));

      final EmployeeDay day = await admin.employeeDay(
        employeeId: MockData.employee.id,
        date: MockData.today,
      );

      expect(day.dayState, DayState.closedByEmployee);
      expect(day.closeout?.rating, 2);
      expect(day.closeout?.tags, contains(DayFeedbackTag.traffic));
    });

    test('reopening an already-open day is a no-op', () async {
      await admin.reopenDay(
        employeeId: MockData.employee.id,
        date: MockData.today,
        reason: 'nothing to undo',
      );

      expect(lock.reopens, isEmpty);
      expect((await employee.home()).dayState, DayState.open);
    });
  });
}
