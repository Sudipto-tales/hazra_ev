import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/api/api_exception.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/attendance_swatch.dart';
import '../../../widgets/states.dart';

/// The admin holiday calendar.
///
/// A month grid mirroring the employee calendar's `MonthGrid` pattern, but
/// read/write: the admin taps any day — past or future — to mark it a holiday
/// or a weekly rest day. Dated holidays come from `GET /admin/holidays`; the
/// weekend set from `GET /admin/weekend-days`.
///
/// The three recurrence choices map to two endpoints:
///  - **One-time** / **Yearly** → `POST /admin/holidays {date, name, recurrence}`.
///  - **Weekly** → adds the day's ISO weekday to the weekend set and
///    `PUT /admin/weekend-days`, which blocks *every* such weekday.
class HolidayCalendarPage extends StatefulWidget {
  const HolidayCalendarPage({super.key});

  @override
  State<HolidayCalendarPage> createState() => _HolidayCalendarPageState();
}

class _HolidayCalendarPageState extends State<HolidayCalendarPage> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);

  bool _loading = true;
  bool _saving = false;
  Object? _error;

  /// Day-key (`yyyymmdd`) → holiday, for the currently loaded year.
  Map<int, Holiday> _holidays = <int, Holiday>{};
  List<int> _weekendDays = const <int>[];

  /// The year whose holidays are currently in [_holidays]. Reloaded only when
  /// the month navigation crosses a year boundary.
  int? _loadedYear;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  static int _dayKey(DateTime d) => d.year * 10000 + d.month * 100 + d.day;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final AppScope scope = AppScope.of(context);
      final List<Holiday> holidays =
          await scope.adminRepository.holidays(year: _month.year);
      final List<int> weekend = await scope.adminRepository.weekendDays();
      if (!mounted) return;
      setState(() {
        _holidays = <int, Holiday>{
          for (final Holiday h in holidays) _dayKey(h.date): h,
        };
        _weekendDays = weekend;
        _loadedYear = _month.year;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  void _shiftMonth(int delta) {
    final DateTime next = DateTime(_month.year, _month.month + delta);
    setState(() => _month = next);
    // Holidays are loaded a year at a time; only a year change needs a refetch.
    if (next.year != _loadedYear) _load();
  }

  Future<void> _onDayTap(DateTime date) async {
    final Holiday? existing = _holidays[_dayKey(date)];
    final bool isWeekend = _weekendDays.contains(date.weekday);

    final _DayEditResult? result = await showModalBottomSheet<_DayEditResult>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (BuildContext context) => _HolidayDaySheet(
        date: date,
        existing: existing,
        isWeekend: isWeekend,
      ),
    );
    if (result == null || !mounted) return;

    await _apply(date, existing: existing, wasWeekend: isWeekend, result: result);
  }

  /// Translates the sheet's intent into repository calls, then reloads.
  Future<void> _apply(
    DateTime date, {
    required Holiday? existing,
    required bool wasWeekend,
    required _DayEditResult result,
  }) async {
    setState(() => _saving = true);
    try {
      final AppScope scope = AppScope.of(context);

      if (result.markHoliday) {
        switch (result.recurrence) {
          case _RecurrenceChoice.weekly:
            // Weekly rest day: weekday goes into the weekend set. A dated
            // holiday that previously sat on this day is being converted, so
            // clear it too.
            if (existing != null) {
              await scope.adminRepository.deleteHoliday(existing.date);
            }
            if (!wasWeekend) {
              final List<int> next = <int>{..._weekendDays, date.weekday}.toList()
                ..sort();
              await scope.adminRepository.saveWeekendDays(next);
            }
          case _RecurrenceChoice.oneTime:
          case _RecurrenceChoice.yearly:
            await scope.adminRepository.saveHoliday(
              date: date,
              name: result.name,
              recurrence: result.recurrence == _RecurrenceChoice.yearly
                  ? HolidayRecurrence.yearly
                  : HolidayRecurrence.oneTime,
            );
        }
      } else {
        // Unchecked. A dated holiday is deleted; a weekly rest day is removed
        // from the weekend set (which clears that weekday across the calendar).
        if (existing != null) {
          await scope.adminRepository.deleteHoliday(existing.date);
        } else if (wasWeekend) {
          final List<int> next =
              _weekendDays.where((int d) => d != date.weekday).toList();
          await scope.adminRepository.saveWeekendDays(next);
        }
      }

      if (!mounted) return;
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      _showError(e.field == null ? e.message : '${e.field}: ${e.message}');
    } catch (_) {
      if (!mounted) return;
      _showError('Could not update the calendar. Try again.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(backgroundColor: AppColors.danger, content: Text(message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Holiday calendar'),
        bottom: _saving
            ? const PreferredSize(
                preferredSize: Size.fromHeight(2),
                child: LinearProgressIndicator(minHeight: 2),
              )
            : null,
      ),
      body: _error != null
          ? ErrorState(onRetry: _load)
          : ListView(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                Insets.lg,
                Insets.lg,
                Insets.xxxl,
              ),
              children: <Widget>[
                AppCard(
                  child: Column(
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          IconButton(
                            onPressed: _saving ? null : () => _shiftMonth(-1),
                            icon: const Icon(Icons.chevron_left_rounded),
                          ),
                          Expanded(
                            child: Center(
                              child: Text(
                                Fmt.monthYear(_month),
                                style: theme.textTheme.titleLarge,
                              ),
                            ),
                          ),
                          IconButton(
                            onPressed: _saving ? null : () => _shiftMonth(1),
                            icon: const Icon(Icons.chevron_right_rounded),
                          ),
                        ],
                      ),
                      const SizedBox(height: Insets.sm),
                      if (_loading)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: Insets.xxxl),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      else
                        _HolidayMonthGrid(
                          month: _month,
                          holidays: _holidays,
                          weekendDays: _weekendDays,
                          onSelect: _saving ? null : _onDayTap,
                        ),
                      const SizedBox(height: Insets.lg),
                      const Divider(height: 1),
                      const SizedBox(height: Insets.md),
                      const _CalendarLegend(),
                    ],
                  ),
                ),
                const SizedBox(height: Insets.lg),
                Text(
                  'Tap any day to mark it a holiday or a weekly rest day. '
                  'Weekly applies to every such weekday; employees cannot start '
                  'work on a non-working day.',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
    );
  }
}

/// Read/write month grid for the admin calendar. Unlike the employee
/// `MonthGrid` it never disables future days — the whole point is to schedule
/// them — and it colours holidays and weekend days distinctly.
class _HolidayMonthGrid extends StatelessWidget {
  const _HolidayMonthGrid({
    required this.month,
    required this.holidays,
    required this.weekendDays,
    required this.onSelect,
  });

  final DateTime month;
  final Map<int, Holiday> holidays;
  final List<int> weekendDays;
  final ValueChanged<DateTime>? onSelect;

  static const List<String> _weekdayLabels = <String>[
    'M', 'T', 'W', 'T', 'F', 'S', 'S', //
  ];

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    final int leadingBlanks = DateTime(month.year, month.month, 1).weekday - 1;
    final DateTime today = Fmt.dayOnly(DateTime.now());

    return Column(
      children: <Widget>[
        Row(
          children: _weekdayLabels
              .map(
                (String d) => Expanded(
                  child: Center(
                    child: Text(d, style: theme.textTheme.labelSmall),
                  ),
                ),
              )
              .toList(growable: false),
        ),
        const SizedBox(height: Insets.sm),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: leadingBlanks + daysInMonth,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 7,
            mainAxisSpacing: 4,
            crossAxisSpacing: 4,
            childAspectRatio: 0.92,
          ),
          itemBuilder: (BuildContext context, int i) {
            if (i < leadingBlanks) return const SizedBox.shrink();

            final int day = i - leadingBlanks + 1;
            final DateTime date = DateTime(month.year, month.month, day);
            final Holiday? holiday =
                holidays[date.year * 10000 + date.month * 100 + day];
            final bool isWeekend = weekendDays.contains(date.weekday);
            final bool isToday = Fmt.isSameDay(date, today);

            final (Color bg, Color fg) = _cellColors(
              context,
              holiday: holiday != null,
              isWeekend: isWeekend,
            );

            return InkWell(
              onTap: onSelect == null ? null : () => onSelect!(date),
              borderRadius: BorderRadius.circular(Radii.md),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(Radii.md),
                  border: isToday
                      ? Border.all(
                          color: AppColors.primary.withValues(alpha: 0.55),
                        )
                      : Border.all(color: context.lineColor),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Text(
                      '$day',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: isToday ? FontWeight.w800 : FontWeight.w600,
                        color: fg,
                      ),
                    ),
                    if (holiday != null) ...<Widget>[
                      const SizedBox(height: 2),
                      Text(
                        holiday.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 8,
                          height: 1.0,
                          color: fg.withValues(alpha: 0.85),
                        ),
                      ),
                    ] else if (isWeekend) ...<Widget>[
                      const SizedBox(height: 3),
                      Container(
                        width: 4,
                        height: 4,
                        decoration: BoxDecoration(
                          color: fg.withValues(alpha: 0.6),
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  (Color, Color) _cellColors(
    BuildContext context, {
    required bool holiday,
    required bool isWeekend,
  }) {
    if (holiday) {
      return AttendanceSwatch.colors(context, AttendanceStatus.holiday);
    }
    if (isWeekend) {
      return AttendanceSwatch.colors(context, AttendanceStatus.weekend);
    }
    // A plain working day: the card surface, not the muted weekend fill, so the
    // three states stay distinct.
    return (context.cardColor, AppColors.textPrimary);
  }
}

class _CalendarLegend extends StatelessWidget {
  const _CalendarLegend();

  @override
  Widget build(BuildContext context) {
    const List<(String, AttendanceStatus?)> items = <(String, AttendanceStatus?)>[
      ('Holiday', AttendanceStatus.holiday),
      ('Weekly rest day', AttendanceStatus.weekend),
      ('Working day', null),
    ];

    return Wrap(
      spacing: Insets.lg,
      runSpacing: Insets.sm,
      children: items.map(((String, AttendanceStatus?) e) {
        final (Color bg, Color fg) = e.$2 == null
            ? (context.cardColor, AppColors.textTertiary)
            : AttendanceSwatch.colors(context, e.$2);
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                color: bg,
                border: Border.all(color: fg.withValues(alpha: 0.5)),
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            const SizedBox(width: Insets.xs),
            Text(e.$1, style: Theme.of(context).textTheme.bodySmall),
          ],
        );
      }).toList(growable: false),
    );
  }
}

/// What the editor sheet hands back. Null is returned on cancel.
class _DayEditResult {
  const _DayEditResult({
    required this.markHoliday,
    required this.name,
    required this.recurrence,
  });

  final bool markHoliday;
  final String name;
  final _RecurrenceChoice recurrence;
}

enum _RecurrenceChoice { oneTime, yearly, weekly }

extension _RecurrenceChoiceX on _RecurrenceChoice {
  String get label => switch (this) {
        _RecurrenceChoice.oneTime => 'One-time',
        _RecurrenceChoice.yearly => 'Yearly',
        _RecurrenceChoice.weekly => 'Weekly',
      };
}

/// The day editor: a checkbox, a title field and the three recurrence choices.
class _HolidayDaySheet extends StatefulWidget {
  const _HolidayDaySheet({
    required this.date,
    required this.existing,
    required this.isWeekend,
  });

  final DateTime date;
  final Holiday? existing;
  final bool isWeekend;

  @override
  State<_HolidayDaySheet> createState() => _HolidayDaySheetState();
}

class _HolidayDaySheetState extends State<_HolidayDaySheet> {
  late bool _marked = widget.existing != null || widget.isWeekend;
  late final TextEditingController _name =
      TextEditingController(text: widget.existing?.name ?? '');
  late _RecurrenceChoice _recurrence = _initialRecurrence();

  _RecurrenceChoice _initialRecurrence() {
    if (widget.existing != null) {
      return widget.existing!.recurrence == HolidayRecurrence.yearly
          ? _RecurrenceChoice.yearly
          : _RecurrenceChoice.oneTime;
    }
    if (widget.isWeekend) return _RecurrenceChoice.weekly;
    return _RecurrenceChoice.oneTime;
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  bool get _needsName => _marked && _recurrence != _RecurrenceChoice.weekly;

  void _save() {
    final String name = _name.text.trim();
    if (_needsName && name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Give this holiday a name.')),
      );
      return;
    }
    Navigator.of(context).pop(
      _DayEditResult(
        markHoliday: _marked,
        name: name,
        recurrence: _recurrence,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool existed = widget.existing != null || widget.isWeekend;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        Insets.lg,
        0,
        Insets.lg,
        MediaQuery.of(context).viewInsets.bottom + Insets.lg,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(Fmt.longDate(widget.date), style: theme.textTheme.titleLarge),
          const SizedBox(height: Insets.md),
          CheckboxListTile(
            value: _marked,
            onChanged: (bool? v) => setState(() => _marked = v ?? false),
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            title: const Text('Mark as holiday'),
            subtitle: Text(
              _marked
                  ? 'This day will be a non-working day.'
                  : existed
                      ? 'Clears the holiday / rest day on this date.'
                      : 'A normal working day.',
            ),
          ),
          if (_marked) ...<Widget>[
            const SizedBox(height: Insets.sm),
            if (_recurrence != _RecurrenceChoice.weekly)
              TextField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'What day is this?',
                  hintText: 'e.g. Independence Day',
                ),
              ),
            const SizedBox(height: Insets.lg),
            Text('Repeats', style: theme.textTheme.labelLarge),
            const SizedBox(height: Insets.sm),
            Wrap(
              spacing: Insets.sm,
              children: _RecurrenceChoice.values
                  .map(
                    (_RecurrenceChoice c) => ChoiceChip(
                      label: Text(c.label),
                      selected: _recurrence == c,
                      onSelected: (_) => setState(() => _recurrence = c),
                    ),
                  )
                  .toList(growable: false),
            ),
            const SizedBox(height: Insets.sm),
            Text(
              switch (_recurrence) {
                _RecurrenceChoice.oneTime =>
                  'Blocks only this date.',
                _RecurrenceChoice.yearly =>
                  'Blocks this date every year.',
                _RecurrenceChoice.weekly =>
                  'Blocks every ${Fmt.weekdayShort(widget.date)} as a weekly '
                      'rest day.',
              },
              style: theme.textTheme.bodySmall,
            ),
          ],
          const SizedBox(height: Insets.lg),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: _save,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(Sizes.touchTarget),
              ),
              child: Text(_marked ? 'Save' : 'Remove'),
            ),
          ),
        ],
      ),
    );
  }
}
