import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';

/// Chronological "what happened today" list: arrivals, dwell time, reports,
/// departures and tracking issues on one rail.
class ActivityTimeline extends StatelessWidget {
  const ActivityTimeline({
    super.key,
    required this.events,
    this.onReportTap,
    this.maxItems,
  });

  final List<ActivityEvent> events;
  final void Function(String reportId)? onReportTap;
  final int? maxItems;

  @override
  Widget build(BuildContext context) {
    final List<ActivityEvent> items = maxItems != null && events.length > maxItems!
        ? events.sublist(0, maxItems!)
        : events;

    return Column(
      children: List<Widget>.generate(items.length, (int i) {
        return _TimelineRow(
          event: items[i],
          isFirst: i == 0,
          isLast: i == items.length - 1,
          onReportTap: onReportTap,
        );
      }),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({
    required this.event,
    required this.isFirst,
    required this.isLast,
    this.onReportTap,
  });

  final ActivityEvent event;
  final bool isFirst;
  final bool isLast;
  final void Function(String reportId)? onReportTap;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final (IconData icon, Color color) = _visual(event);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          // Rail
          SizedBox(
            width: 34,
            child: Column(
              children: <Widget>[
                Container(
                  width: 2,
                  height: 6,
                  color: isFirst ? Colors.transparent : theme.dividerColor,
                ),
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.13),
                    shape: BoxShape.circle,
                    border: Border.all(color: color.withValues(alpha: 0.35)),
                  ),
                  child: Icon(icon, size: 15, color: color),
                ),
                Expanded(
                  child: Container(
                    width: 2,
                    color: isLast ? Colors.transparent : theme.dividerColor,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: Insets.md),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(top: 4, bottom: isLast ? 0 : Insets.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Text(
                        event.endTime == null
                            ? Fmt.time(event.time)
                            : '${Fmt.time(event.time)} – ${Fmt.time(event.endTime)}',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: theme.textTheme.bodySmall?.color,
                          fontSize: 11.5,
                        ),
                      ),
                      if (event.duration != null) ...<Widget>[
                        const SizedBox(width: Insets.sm),
                        Container(
                          width: 3,
                          height: 3,
                          decoration: BoxDecoration(
                            color: theme.textTheme.bodySmall?.color,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: Insets.sm),
                        Text(
                          Fmt.duration(event.duration!),
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: color,
                            fontSize: 11.5,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    event.title,
                    style: theme.textTheme.titleMedium?.copyWith(fontSize: 14.5),
                  ),
                  if (event.branchName != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        event.branchName!,
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  if (event.subtitle != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        event.subtitle!,
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  if (event.reportId != null && onReportTap != null)
                    Padding(
                      padding: const EdgeInsets.only(top: Insets.sm),
                      child: InkWell(
                        onTap: () => onReportTap!(event.reportId!),
                        borderRadius: BorderRadius.circular(Radii.sm),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Text(
                                'Open report',
                                style: theme.textTheme.labelSmall?.copyWith(
                                  color: AppColors.primary,
                                  fontSize: 12,
                                ),
                              ),
                              const Icon(
                                Icons.chevron_right_rounded,
                                size: 16,
                                color: AppColors.primary,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  (IconData, Color) _visual(ActivityEvent e) {
    if (e.isAlert) return (Icons.gps_off_rounded, AppColors.danger);
    return switch (e.type) {
      ActivityType.dayStarted => (Icons.play_arrow_rounded, AppColors.success),
      ActivityType.sessionStarted => (Icons.restart_alt_rounded, AppColors.success),
      ActivityType.travelling => (Icons.directions_car_filled_outlined, AppColors.info),
      ActivityType.arrived => (Icons.place_rounded, AppColors.primary),
      ActivityType.stayed => (Icons.timelapse_rounded, AppColors.warning),
      ActivityType.reportSubmitted => (Icons.description_rounded, AppColors.primary),
      ActivityType.left => (Icons.logout_rounded, AppColors.textSecondary),
      ActivityType.sessionEnded => (Icons.pause_rounded, AppColors.warning),
      ActivityType.dayEnded => (Icons.stop_rounded, AppColors.textSecondary),
      ActivityType.trackingIssue => (Icons.gps_off_rounded, AppColors.danger),
    };
  }
}
