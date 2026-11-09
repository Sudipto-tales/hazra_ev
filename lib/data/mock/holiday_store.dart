import 'package:flutter/foundation.dart';

import '../../core/utils/formatters.dart';
import '../models/models.dart';
import 'mock_data.dart';

/// The org's non-working calendar, in memory.
///
/// **One instance is shared by both mock repositories**, the same arrangement
/// as [ProductStore] and [DayLockStore]: the admin writes holidays and weekend
/// days through `MockAdminRepository`, and the employee side reads the same set
/// when it decides whether today is workable. In the live app this lives on the
/// server (the `holidays` table + `organizations.weekend_days`).
///
/// It mirrors the server's two halves exactly:
///  - dated public holidays, each `one_time` or `yearly`;
///  - weekly rest days as ISO weekday numbers (1=Mon..7=Sun).
class HolidayStore extends ChangeNotifier {
  HolidayStore({List<int>? weekendDays, bool seed = true})
      : _weekendDays = <int>{
          ...(weekendDays ?? const <int>[DateTime.friday]),
        } {
    if (!seed) return;
    for (final _Seed s in _defaultSeed) {
      _holidays[s.anchorKey] = Holiday(
        date: s.date,
        name: s.name,
        recurrence: s.recurrence,
      );
    }
  }

  /// Anchor day-key (`yyyymmdd` of the stored date) → entry. The PK is the date,
  /// so an upsert on the same day replaces it — exactly like the server.
  final Map<int, Holiday> _holidays = <int, Holiday>{};
  final Set<int> _weekendDays;

  static int _key(DateTime d) => d.year * 10000 + d.month * 100 + d.day;

  List<int> get weekendDays {
    final List<int> out = _weekendDays.toList()..sort();
    return List<int>.unmodifiable(out);
  }

  /// The calendar for one [year]: `one_time` entries whose year matches, plus
  /// every `yearly` entry projected onto that year, sorted by date — the same
  /// shape `HolidaysController::index` returns.
  List<Holiday> holidaysForYear(int year) {
    final List<Holiday> out = <Holiday>[];
    for (final Holiday h in _holidays.values) {
      if (h.recurrence == HolidayRecurrence.yearly) {
        final int lastDay = DateTime(year, h.date.month + 1, 0).day;
        if (h.date.day > lastDay) continue; // Feb 29 in a non-leap year.
        out.add(Holiday(
          date: DateTime(year, h.date.month, h.date.day),
          name: h.name,
          recurrence: HolidayRecurrence.yearly,
        ));
      } else if (h.date.year == year) {
        out.add(h);
      }
    }
    out.sort((Holiday a, Holiday b) => a.date.compareTo(b.date));
    return out;
  }

  /// Upsert on the date, like the server's `ON CONFLICT (org_id, date)`.
  Holiday upsertHoliday({
    required DateTime date,
    required String name,
    required HolidayRecurrence recurrence,
  }) {
    final DateTime day = Fmt.dayOnly(date);
    final Holiday h = Holiday(date: day, name: name, recurrence: recurrence);
    _holidays[_key(day)] = h;
    notifyListeners();
    return h;
  }

  /// Delete by date. Forgiving on a `yearly` entry: a projected occurrence still
  /// resolves to the stored anchor by month-day. Returns whether a row went.
  bool deleteHoliday(DateTime date) {
    final DateTime day = Fmt.dayOnly(date);
    final int exact = _key(day);
    if (_holidays.containsKey(exact)) {
      _holidays.remove(exact);
      notifyListeners();
      return true;
    }
    for (final int k in _holidays.keys.toList()) {
      final Holiday h = _holidays[k]!;
      if (h.recurrence == HolidayRecurrence.yearly &&
          h.date.month == day.month &&
          h.date.day == day.day) {
        _holidays.remove(k);
        notifyListeners();
        return true;
      }
    }
    return false;
  }

  /// Replace the weekend set. Deduped, sorted and range-checked (1..7), exactly
  /// like `HolidaysController::updateWeekendDays`.
  List<int> setWeekendDays(Iterable<int> days) {
    _weekendDays
      ..clear()
      ..addAll(days.where((int d) => d >= 1 && d <= 7));
    notifyListeners();
    return weekendDays;
  }

  /// Whether [date] is a holiday or a weekly rest day — the mock twin of
  /// `Engine::isNonWorkingDay`.
  bool isNonWorkingDay(DateTime date) {
    final DateTime day = Fmt.dayOnly(date);
    if (_weekendDays.contains(day.weekday)) return true;
    return holidaysForYear(day.year)
        .any((Holiday h) => Fmt.isSameDay(h.date, day));
  }

  static List<_Seed> get _defaultSeed {
    final int year = MockData.today.year;
    return <_Seed>[
      _Seed(DateTime(year, 1, 26), 'Republic Day', HolidayRecurrence.yearly),
      _Seed(DateTime(year, 8, 15), 'Independence Day', HolidayRecurrence.yearly),
      _Seed(DateTime(year, 10, 2), 'Gandhi Jayanti', HolidayRecurrence.yearly),
      _Seed(DateTime(year, MockData.today.month, 21), 'Local festival',
          HolidayRecurrence.oneTime),
    ];
  }
}

class _Seed {
  const _Seed(this.date, this.name, this.recurrence);
  final DateTime date;
  final String name;
  final HolidayRecurrence recurrence;
  int get anchorKey => date.year * 10000 + date.month * 100 + date.day;
}
