import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/company_directory.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/status_badge.dart';

/// One company visit: arrival, departure, dwell time and attached reports.
///
/// A [CompanyVisit] travels as ids only, so the two labels this tile cannot
/// read off it are resolved elsewhere:
///
///  * company and branch names come from [AppScope.companies]. The tile only
///    reads; the screen that owns it must call `companies.ensureLoaded()` on
///    its load path. It still listens to the directory, so a load that lands
///    after the first frame fills the labels in instead of leaving a
///    placeholder frozen on screen.
///  * report titles come from [reportTitles], passed down by the parent that
///    already fetched the day's reports. Fetching them here would be one
///    request per visit on every build.
class VisitTile extends StatelessWidget {
  const VisitTile({
    super.key,
    required this.visit,
    required this.now,
    this.reportTitles = const <String, String>{},
    this.onReportTap,
    this.onAddReport,
  });

  final CompanyVisit visit;
  final DateTime now;

  /// Report id → label, for the ids in [CompanyVisit.reportIds]. Anything
  /// missing falls back to a neutral "Report n" — never to a guessed title.
  final Map<String, String> reportTitles;

  final void Function(String reportId)? onReportTap;
  final VoidCallback? onAddReport;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final CompanyDirectory directory = AppScope.of(context).companies;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: AppColors.primarySoft,
                  borderRadius: BorderRadius.circular(Radii.md),
                ),
                child: const Icon(
                  Icons.storefront_rounded,
                  size: 19,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                child: ListenableBuilder(
                  listenable: directory,
                  builder: (BuildContext context, _) {
                    // Null means "not in the directory" — shown as such. It
                    // must never fall through to another company's name.
                    final String? company =
                        directory.companyName(visit.companyId);
                    final String? branch =
                        directory.branchName(visit.companyId, visit.branchId);

                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          company ?? 'Unknown company',
                          style: company != null
                              ? theme.textTheme.titleMedium
                              : theme.textTheme.titleMedium
                                  ?.copyWith(color: AppColors.textSecondary),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (branch != null)
                          Text(branch, style: theme.textTheme.bodySmall),
                      ],
                    );
                  },
                ),
              ),
              StatusBadge(
                label: visit.status == VisitStatus.inProgress
                    ? 'On site'
                    : Fmt.duration(visit.durationAt(now)),
                tone: visit.status == VisitStatus.inProgress
                    ? BadgeTone.positive
                    : BadgeTone.neutral,
                dense: true,
                icon: Icons.timelapse_rounded,
              ),
            ],
          ),
          const SizedBox(height: Insets.md),
          Row(
            children: <Widget>[
              _Leg(
                icon: Icons.login_rounded,
                label: 'Arrived',
                value: Fmt.time(visit.arrival),
                color: AppColors.success,
              ),
              _Leg(
                icon: Icons.logout_rounded,
                label: 'Left',
                value: visit.departure == null
                    ? 'On site'
                    : Fmt.time(visit.departure),
                color: AppColors.textSecondary,
              ),
              _Leg(
                icon: Icons.timer_outlined,
                label: 'Stayed',
                value: Fmt.duration(visit.durationAt(now)),
                color: AppColors.warning,
              ),
            ],
          ),
          if (visit.dealReference != null) ...<Widget>[
            const SizedBox(height: Insets.md),
            Row(
              children: <Widget>[
                const Icon(Icons.receipt_long_outlined,
                    size: 15, color: AppColors.textSecondary),
                const SizedBox(width: 6),
                Text(
                  'Order ${visit.dealReference}',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ],
          const Divider(height: Insets.xxl),
          if (visit.reportIds.isEmpty)
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'No report submitted for this visit',
                    style: theme.textTheme.bodySmall,
                  ),
                ),
                TextButton.icon(
                  onPressed: onAddReport,
                  icon: const Icon(Icons.add_rounded, size: 17),
                  label: const Text('Add report'),
                ),
              ],
            )
          else
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Text(
                      '${visit.reportIds.length} report'
                      '${visit.reportIds.length == 1 ? '' : 's'}',
                      style: theme.textTheme.titleMedium?.copyWith(fontSize: 13.5),
                    ),
                    const Spacer(),
                    TextButton.icon(
                      onPressed: onAddReport,
                      icon: const Icon(Icons.add_rounded, size: 17),
                      label: const Text('Add another'),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.sm),
                for (int i = 0; i < visit.reportIds.length; i++)
                  InkWell(
                    onTap: onReportTap == null
                        ? null
                        : () => onReportTap!(visit.reportIds[i]),
                    borderRadius: BorderRadius.circular(Radii.sm),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 7),
                      child: Row(
                        children: <Widget>[
                          const Icon(Icons.description_outlined,
                              size: 16, color: AppColors.primary),
                          const SizedBox(width: Insets.sm),
                          Expanded(
                            child: Text(
                              _reportLabel(i),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyLarge
                                  ?.copyWith(fontSize: 13.5),
                            ),
                          ),
                          const Icon(Icons.chevron_right_rounded, size: 18),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }

  /// Label for the report at [index] of [CompanyVisit.reportIds].
  ///
  /// The parent supplies real titles when it has them. When it does not — the
  /// admin screens reuse this tile without loading report bodies — the row
  /// still has to read as something, so it is numbered off the ids the visit
  /// already holds rather than guessed at.
  String _reportLabel(int index) {
    final String? title = reportTitles[visit.reportIds[index]];
    if (title != null && title.isNotEmpty) return title;
    return visit.reportIds.length == 1 ? 'Report' : 'Report ${index + 1}';
  }
}

class _Leg extends StatelessWidget {
  const _Leg({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Expanded(
      child: Row(
        children: <Widget>[
          Icon(icon, size: 15, color: color),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(fontSize: 13),
                ),
                Text(label, style: theme.textTheme.labelSmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
