import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/status_badge.dart';

/// One roster row: who, what state, and the two numbers that matter today.
/// Shared by the dashboard and the Team tab.
class TeamStatusTile extends StatelessWidget {
  const TeamStatusTile({
    super.key,
    required this.member,
    required this.longStopThresholdMinutes,
    this.onTap,
  });

  final TeamMember member;
  final int longStopThresholdMinutes;
  final VoidCallback? onTap;

  Color get _dot => switch (member.status) {
        WorkStatus.working => AppColors.statusMoving,
        WorkStatus.idle => AppColors.statusIdle,
        WorkStatus.locationUnavailable => AppColors.statusLongStop,
        WorkStatus.offline => AppColors.statusOffline,
        WorkStatus.ended => AppColors.info,
        WorkStatus.notStarted => AppColors.textTertiary,
      };

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Employee e = member.employee;
    final bool longStop = member.isLongStop(longStopThresholdMinutes);

    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: AppCard(
        onTap: onTap,
        padding: const EdgeInsets.all(Insets.md),
        borderColor: longStop ? AppColors.danger.withValues(alpha: 0.4) : null,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                ProfileAvatar(
                  initials: e.initials,
                  imageUrl: e.avatarUrl,
                  size: Sizes.avatarMd,
                  statusColor: _dot,
                ),
                const SizedBox(width: Insets.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              e.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleMedium,
                            ),
                          ),
                          if (!member.active)
                            const Padding(
                              padding: EdgeInsets.only(left: Insets.sm),
                              child: StatusBadge(
                                label: 'Inactive',
                                dense: true,
                                icon: Icons.person_off_outlined,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        e.region.trim().isEmpty
                            ? e.employeeCode
                            : '${e.employeeCode} · ${e.region}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                      const SizedBox(height: Insets.sm),
                      Wrap(
                        spacing: Insets.sm,
                        runSpacing: Insets.xs,
                        children: <Widget>[
                          StatusBadge.work(member.status, dense: true),
                          if (member.isDegraded)
                            StatusBadge(
                              label: member.locationHealth.title,
                              tone: BadgeTone.danger,
                              icon: Icons.gps_off_rounded,
                              dense: true,
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: Insets.md),
            Row(
              children: <Widget>[
                _Metric(
                  icon: Icons.route_outlined,
                  value: Fmt.km(member.summary.distanceKm),
                ),
                _Metric(
                  icon: Icons.storefront_outlined,
                  value: '${member.summary.companiesVisited} visits',
                ),
                _Metric(
                  icon: Icons.description_outlined,
                  value: '${member.summary.reportsSubmitted} reports',
                ),
              ],
            ),
            if (longStop) ...<Widget>[
              const SizedBox(height: Insets.sm),
              Row(
                children: <Widget>[
                  const Icon(
                    Icons.warning_amber_rounded,
                    size: 15,
                    color: AppColors.danger,
                  ),
                  const SizedBox(width: Insets.xs),
                  Expanded(
                    child: Text(
                      'Stopped ${Fmt.duration(member.openStopDuration!)} — '
                      'over the ${longStopThresholdMinutes}-minute threshold',
                      style: theme.textTheme.bodySmall
                          ?.copyWith(color: AppColors.danger),
                    ),
                  ),
                ],
              ),
            ],
            if (member.lastFix != null) ...<Widget>[
              const SizedBox(height: Insets.xs),
              Text(
                'Last fix ${Fmt.relative(member.lastFix!.recordedAt)}',
                style: theme.textTheme.labelSmall,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Expanded(
      child: Row(
        children: <Widget>[
          Icon(icon, size: 15, color: theme.textTheme.bodySmall?.color),
          const SizedBox(width: Insets.xs),
          Flexible(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }
}
