import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/mini_bar_chart.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../employees/employee_detail_page.dart';

/// Team totals over a range, plus a per-employee ranking.
class TeamAnalyticsTab extends StatefulWidget {
  const TeamAnalyticsTab({super.key});

  @override
  State<TeamAnalyticsTab> createState() => _TeamAnalyticsTabState();
}

class _TeamAnalyticsTabState extends State<TeamAnalyticsTab> {
  bool _loading = true;
  Object? _error;
  TeamStatistics? _stats;
  StatsRange _range = StatsRange.thisMonth;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final TeamStatistics stats = await AppScope.of(context)
          .adminRepository
          .teamStatistics(range: _range);
      if (!mounted) return;
      setState(() {
        _stats = stats;
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

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(
        Insets.lg,
        Insets.lg,
        Insets.lg,
        Insets.xxxl,
      ),
      children: <Widget>[
        SizedBox(
          height: 40,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: <StatsRange>[
              StatsRange.thisWeek,
              StatsRange.thisMonth,
              StatsRange.lastMonth,
            ]
                .map(
                  (StatsRange r) => Padding(
                    padding: const EdgeInsets.only(right: Insets.sm),
                    child: ChoiceChip(
                      label: Text(r.label),
                      selected: _range == r,
                      onSelected: (_) {
                        setState(() => _range = r);
                        _load();
                      },
                    ),
                  ),
                )
                .toList(),
          ),
        ),
        const SizedBox(height: Insets.lg),
        if (_loading)
          const LoadingCards(count: 3)
        else if (_error != null)
          ErrorState(onRetry: _load)
        else
          ..._content(_stats!),
      ],
    );
  }

  List<Widget> _content(TeamStatistics s) {
    return <Widget>[
      StatGrid(
        children: <Widget>[
          StatTile(
            icon: Icons.groups_rounded,
            value: '${s.headcount}',
            label: 'Field staff',
          ),
          StatTile(
            icon: Icons.percent_rounded,
            value: Fmt.percent(s.attendancePercent),
            label: 'Attendance',
            tone: s.attendancePercent >= 90
                ? AppColors.success
                : AppColors.warning,
          ),
          StatTile(
            icon: Icons.route_outlined,
            value: Fmt.km(s.totalDistanceKm),
            label: 'Total distance',
          ),
          StatTile(
            icon: Icons.storefront_outlined,
            value: '${s.totalVisits}',
            label: 'Visits',
            tone: AppColors.info,
          ),
          StatTile(
            icon: Icons.description_outlined,
            value: '${s.totalReports}',
            label: 'Reports',
            tone: AppColors.success,
          ),
          StatTile(
            icon: Icons.timelapse_rounded,
            value: Fmt.duration(s.averageWorkedDuration),
            label: 'Avg. worked / day',
          ),
        ],
      ),

      SectionHeader(
        title: 'Team distance',
        subtitle: s.rangeLabel,
        padding: const EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      AppCard(
        child: s.dailyDistance.isEmpty
            ? const EmptyState(
                icon: Icons.bar_chart_rounded,
                title: 'No data in range',
                message: 'Pick a different range to see the daily totals.',
              )
            : MiniBarChart(data: s.dailyDistance),
      ),

      const SectionHeader(
        title: 'By employee',
        subtitle: 'Ranked by distance covered',
        padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      ...s.perEmployee.map(_rankTile),
    ];
  }

  Widget _rankTile(EmployeeMetric m) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: AppCard(
        padding: const EdgeInsets.all(Insets.md),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => EmployeeDetailPage(employeeId: m.employeeId),
          ),
        ),
        child: Row(
          children: <Widget>[
            ProfileAvatar(initials: m.initials, size: Sizes.avatarSm),
            const SizedBox(width: Insets.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    m.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleMedium?.copyWith(fontSize: 14),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${m.visits} visits · ${m.reports} reports · '
                    '${Fmt.percent(m.attendancePercent)} attendance',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const SizedBox(width: Insets.sm),
            Text(
              Fmt.km(m.distanceKm),
              style: theme.textTheme.titleMedium?.copyWith(fontSize: 14),
            ),
          ],
        ),
      ),
    );
  }
}
