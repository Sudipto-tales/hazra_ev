import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/mock/holiday_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:flutter_test/flutter_test.dart';

/// The admin holiday-calendar repository surface, exercised on the mock backend
/// the app runs on without a server. The same method shapes back the real
/// `HttpAdminRepository`, so a drift here is a drift in the UI's contract too.
void main() {
  MockAdminRepository repo([HolidayStore? store]) => MockAdminRepository(
        latency: Duration.zero,
        holidays:
            store ?? HolidayStore(weekendDays: const <int>[], seed: false),
      );

  group('holidays', () {
    test('a one-time holiday shows up only in its own year', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
        date: DateTime(2026, 3, 14),
        name: 'Stocktake',
        recurrence: HolidayRecurrence.oneTime,
      );

      final List<Holiday> y2026 = await r.holidays(year: 2026);
      expect(
        y2026.where((Holiday h) => h.name == 'Stocktake'),
        hasLength(1),
      );

      final List<Holiday> y2027 = await r.holidays(year: 2027);
      expect(y2027.where((Holiday h) => h.name == 'Stocktake'), isEmpty);
    });

    test('a yearly holiday is projected onto whatever year is asked', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
        date: DateTime(2026, 1, 26),
        name: 'Republic Day',
        recurrence: HolidayRecurrence.yearly,
      );

      final List<Holiday> y2030 = await r.holidays(year: 2030);
      final Holiday projected =
          y2030.firstWhere((Holiday h) => h.name == 'Republic Day');
      expect(projected.date, DateTime(2030, 1, 26));
      expect(projected.recurrence, HolidayRecurrence.yearly);
    });

    test('the list comes back sorted by date', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
          date: DateTime(2026, 9, 2),
          name: 'B',
          recurrence: HolidayRecurrence.oneTime);
      await r.saveHoliday(
          date: DateTime(2026, 2, 2),
          name: 'A',
          recurrence: HolidayRecurrence.oneTime);

      final List<Holiday> all = await r.holidays(year: 2026);
      final List<DateTime> dates = all.map((Holiday h) => h.date).toList();
      final List<DateTime> sorted = <DateTime>[...dates]
        ..sort((DateTime a, DateTime b) => a.compareTo(b));
      expect(dates, sorted);
    });

    test('re-posting a date upserts rather than duplicating', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
          date: DateTime(2026, 5, 1),
          name: 'May Day',
          recurrence: HolidayRecurrence.oneTime);
      await r.saveHoliday(
          date: DateTime(2026, 5, 1),
          name: 'Labour Day',
          recurrence: HolidayRecurrence.yearly);

      final List<Holiday> may = (await r.holidays(year: 2026))
          .where((Holiday h) => h.date == DateTime(2026, 5, 1))
          .toList();
      expect(may, hasLength(1));
      expect(may.single.name, 'Labour Day');
      expect(may.single.recurrence, HolidayRecurrence.yearly);
    });

    test('a blank name is rejected the way the server 422s', () async {
      final MockAdminRepository r = repo();
      await expectLater(
        r.saveHoliday(
          date: DateTime(2026, 6, 1),
          name: '   ',
          recurrence: HolidayRecurrence.oneTime,
        ),
        throwsA(isA<ApiException>()
            .having((ApiException e) => e.field, 'field', 'name')),
      );
    });

    test('deleting a holiday removes it; deleting a bare date 404s', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
          date: DateTime(2026, 7, 4),
          name: 'Audit',
          recurrence: HolidayRecurrence.oneTime);

      await r.deleteHoliday(DateTime(2026, 7, 4));
      expect(
        (await r.holidays(year: 2026))
            .where((Holiday h) => h.date == DateTime(2026, 7, 4)),
        isEmpty,
      );

      await expectLater(
        r.deleteHoliday(DateTime(2026, 7, 4)),
        throwsA(isA<ApiException>()
            .having((ApiException e) => e.code, 'code', 'HOLIDAY_NOT_FOUND')),
      );
    });

    test('a projected yearly date still deletes its stored anchor', () async {
      final MockAdminRepository r = repo();
      await r.saveHoliday(
          date: DateTime(2020, 10, 2),
          name: 'Gandhi Jayanti',
          recurrence: HolidayRecurrence.yearly);

      // Delete using a different year's projection of the same month-day.
      await r.deleteHoliday(DateTime(2026, 10, 2));
      expect(await r.holidays(year: 2026), isEmpty);
    });
  });

  group('weekend days', () {
    test('saving dedupes, sorts and reads back', () async {
      final MockAdminRepository r = repo();
      final List<int> saved = await r.saveWeekendDays(<int>[7, 6, 6]);
      expect(saved, <int>[6, 7]);
      expect(await r.weekendDays(), <int>[6, 7]);
    });

    test('an out-of-range weekday 422s on the field', () async {
      final MockAdminRepository r = repo();
      await expectLater(
        r.saveWeekendDays(<int>[0, 8]),
        throwsA(isA<ApiException>()
            .having((ApiException e) => e.field, 'field', 'weekendDays')),
      );
    });
  });

  group('shared store', () {
    test('a holiday the admin adds is a non-working day for the employee side',
        () async {
      final HolidayStore store =
          HolidayStore(weekendDays: const <int>[], seed: false);
      final MockAdminRepository admin =
          MockAdminRepository(latency: Duration.zero, holidays: store);

      expect(store.isNonWorkingDay(DateTime(2026, 12, 25)), isFalse);
      await admin.saveHoliday(
        date: DateTime(2026, 12, 25),
        name: 'Christmas',
        recurrence: HolidayRecurrence.yearly,
      );
      expect(store.isNonWorkingDay(DateTime(2026, 12, 25)), isTrue);
      expect(store.isNonWorkingDay(DateTime(2027, 12, 25)), isTrue,
          reason: 'yearly projects onto future years');
    });
  });
}
