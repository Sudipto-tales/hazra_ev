import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/status_badge.dart';

class ReportCard extends StatelessWidget {
  const ReportCard({
    super.key,
    required this.report,
    required this.onTap,
    this.indexLabel,
  });

  final VisitReport report;
  final VoidCallback onTap;

  /// e.g. "Report #2" when several reports share a company on the same day.
  final String? indexLabel;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    // Company/branch are the seller's own words — never looked up.
    final String company = report.companyName;
    final String? branch = report.branchName;

    return AppCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Flexible(
                          child: Text(
                            company,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        if (indexLabel != null) ...<Widget>[
                          const SizedBox(width: Insets.sm),
                          Text(
                            indexLabel!,
                            style: theme.textTheme.labelSmall
                                ?.copyWith(color: AppColors.primary),
                          ),
                        ],
                      ],
                    ),
                    if (branch != null)
                      Text(branch, style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
              const SizedBox(width: Insets.sm),
              StatusBadge.report(report.status, dense: true),
            ],
          ),
          const SizedBox(height: Insets.md),
          Text(
            report.title,
            style: theme.textTheme.bodyLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            report.preview,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodyMedium,
          ),
          const SizedBox(height: Insets.md),
          Row(
            children: <Widget>[
              _Meta(
                icon: Icons.schedule_rounded,
                label: Fmt.time(report.submittedAt),
              ),
              const SizedBox(width: Insets.lg),
              _Meta(
                icon: Icons.image_outlined,
                label: '${report.imageCount}',
              ),
              if (report.hasSale) ...<Widget>[
                const SizedBox(width: Insets.lg),
                _Meta(
                  icon: Icons.electric_moped_rounded,
                  label: '${report.unitsSold} unit'
                      '${report.unitsSold == 1 ? '' : 's'}',
                ),
              ] else if (report.visitId != null) ...<Widget>[
                const SizedBox(width: Insets.lg),
                const _Meta(icon: Icons.place_outlined, label: 'Visit linked'),
              ],
              const Spacer(),
              if (report.paymentReceived != null)
                Text(
                  '${report.paymentReceived!} received',
                  style: theme.textTheme.labelSmall
                      ?.copyWith(color: AppColors.success, fontSize: 12),
                )
              else if (report.dealValue != null)
                Text(
                  report.dealValue!,
                  style: theme.textTheme.labelSmall
                      ?.copyWith(color: AppColors.success, fontSize: 12),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 14, color: theme.textTheme.bodySmall?.color),
        const SizedBox(width: 4),
        Text(label, style: theme.textTheme.bodySmall),
      ],
    );
  }
}
