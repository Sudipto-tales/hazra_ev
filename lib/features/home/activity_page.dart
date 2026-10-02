import 'package:flutter/material.dart';

import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../widgets/app_card.dart';
import '../../widgets/states.dart';
import '../reports/report_detail_page.dart';
import 'widgets/activity_timeline.dart';

/// Full-day timeline, opened from Home → Activity → View all.
class ActivityPage extends StatelessWidget {
  const ActivityPage({super.key, required this.events});

  final List<ActivityEvent> events;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Activity'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(24),
          child: Padding(
            padding: const EdgeInsets.only(left: Insets.lg, bottom: Insets.md),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                Fmt.longDate(DateTime.now()),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          ),
        ),
      ),
      body: events.isEmpty
          ? const EmptyState(
              icon: Icons.timeline_rounded,
              title: 'No activity',
              message: 'Nothing has been recorded for today yet.',
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                Insets.sm,
                Insets.lg,
                Insets.xxxl,
              ),
              children: <Widget>[
                AppCard(
                  child: ActivityTimeline(
                    events: events,
                    onReportTap: (String id) => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => ReportDetailPage(reportId: id),
                      ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
