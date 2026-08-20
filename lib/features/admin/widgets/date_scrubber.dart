import 'package:flutter/material.dart';

import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../widgets/app_card.dart';

/// Prev / next day with a tap-to-pick middle. Used by the employee detail
/// screen and the route map, which both scrub a single day at a time.
class DateScrubber extends StatelessWidget {
  const DateScrubber({
    super.key,
    required this.date,
    required this.onChanged,
    required this.firstDate,
    required this.lastDate,
  });

  final DateTime date;
  final ValueChanged<DateTime> onChanged;
  final DateTime firstDate;
  final DateTime lastDate;

  bool get _canGoBack => Fmt.dayOnly(date).isAfter(Fmt.dayOnly(firstDate));

  bool get _canGoForward => Fmt.dayOnly(date).isBefore(Fmt.dayOnly(lastDate));

  Future<void> _pick(BuildContext context) async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: date,
      firstDate: firstDate,
      lastDate: lastDate,
    );
    if (picked != null) onChanged(Fmt.dayOnly(picked));
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool isToday = Fmt.isSameDay(date, DateTime.now());

    return AppCard(
      padding: const EdgeInsets.symmetric(
        horizontal: Insets.sm,
        vertical: Insets.xs,
      ),
      child: Row(
        children: <Widget>[
          IconButton(
            onPressed: _canGoBack
                ? () => onChanged(
                      Fmt.dayOnly(date.subtract(const Duration(days: 1))),
                    )
                : null,
            icon: const Icon(Icons.chevron_left_rounded),
            tooltip: 'Previous day',
          ),
          Expanded(
            child: InkWell(
              onTap: () => _pick(context),
              borderRadius: BorderRadius.circular(Radii.sm),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: Insets.sm),
                child: Column(
                  children: <Widget>[
                    Text(
                      isToday ? 'Today' : Fmt.weekdayLong(date),
                      style: theme.textTheme.titleMedium,
                    ),
                    Text(
                      Fmt.mediumDate(date),
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
            ),
          ),
          IconButton(
            onPressed: _canGoForward
                ? () => onChanged(
                      Fmt.dayOnly(date.add(const Duration(days: 1))),
                    )
                : null,
            icon: const Icon(Icons.chevron_right_rounded),
            tooltip: 'Next day',
          ),
        ],
      ),
    );
  }
}
