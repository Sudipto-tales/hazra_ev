import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../widgets/attendance_swatch.dart';

/// Monthly attendance grid. Colour encodes the day's status; the ring marks the
/// selected day.
class MonthGrid extends StatelessWidget {
  const MonthGrid({
    super.key,
    required this.month,
    required this.records,
    required this.selected,
    required this.onSelect,
  });

  final DateTime month;
  final Map<int, Attendance> records;
  final DateTime? selected;
  final ValueChanged<DateTime> onSelect;

  static const List<String> _weekdayLabels = <String>[
    'M', 'T', 'W', 'T', 'F', 'S', 'S',
  ];

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    final int leadingBlanks =
        DateTime(month.year, month.month, 1).weekday - 1; // Monday-first
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
            final Attendance? record = records[day];
            final bool isSelected =
                selected != null && Fmt.isSameDay(selected!, date);
            final bool isToday = Fmt.isSameDay(date, today);
            final bool isFuture = date.isAfter(today);

            final (Color bg, Color fg) = _colors(
              record?.status,
              isFuture: isFuture,
              context: context,
            );

            return InkWell(
              onTap: isFuture ? null : () => onSelect(date),
              borderRadius: BorderRadius.circular(Radii.md),
              child: Container(
                decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(Radii.md),
                  border: isSelected
                      ? Border.all(color: AppColors.primary, width: 2)
                      : isToday
                          ? Border.all(
                              color: AppColors.primary.withValues(alpha: 0.45),
                            )
                          : null,
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
                    if (record != null &&
                        record.status != AttendanceStatus.noData &&
                        !isFuture) ...<Widget>[
                      const SizedBox(height: 3),
                      Container(
                        width: 4,
                        height: 4,
                        decoration: BoxDecoration(
                          color: fg.withValues(alpha: 0.75),
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

  // Shared with the admin attendance matrix — see AttendanceSwatch.
  (Color, Color) _colors(
    AttendanceStatus? status, {
    required bool isFuture,
    required BuildContext context,
  }) =>
      AttendanceSwatch.colors(context, status, isFuture: isFuture);
}

/// Legend under the grid.
class MonthLegend extends StatelessWidget {
  const MonthLegend({super.key});

  @override
  Widget build(BuildContext context) {
    const List<(String, Color)> items = <(String, Color)>[
      ('Present', AppColors.success),
      ('Partial', AppColors.warning),
      ('Absent', AppColors.danger),
      ('Holiday', AppColors.info),
      ('No data', AppColors.textTertiary),
    ];

    return Wrap(
      spacing: Insets.lg,
      runSpacing: Insets.sm,
      children: items
          .map(
            ((String, Color) e) => Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 8,
                  height: 8,
                  decoration:
                      BoxDecoration(color: e.$2, shape: BoxShape.circle),
                ),
                const SizedBox(width: 6),
                Text(e.$1, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          )
          .toList(growable: false),
    );
  }
}
