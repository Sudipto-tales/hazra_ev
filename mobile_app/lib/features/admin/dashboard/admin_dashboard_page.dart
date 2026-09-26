import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/mini_bar_chart.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../employees/employee_detail_page.dart';
import '../shell/admin_shell.dart';
import '../widgets/team_status_tile.dart';

/// Live team status for today: alerts first, then the numbers, then the roster.
class AdminDashboardPage extends StatefulWidget {
  const AdminDashboardPage({super.key});

  @override
  State<AdminDashboardPage> createState() => _AdminDashboardPageState();
}

class _AdminDashboardPageState extends State<AdminDashboardPage> {
  bool _loading = true;
  Object? _error;
  TeamOverview? _overview;
  TrackingConfig _config = TrackingConfig.defaults;
  AdminUser? _admin;

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
      final AppScope scope = AppScope.of(context);
      final TeamOverview overview = await scope.adminRepository.overview();
      final TrackingConfig config = await scope.adminRepository.config();
      final AdminUser admin = await scope.adminRepository.profile();
      if (!mounted) return;
      setState(() {
        _overview = overview;
        _config = config;
        _admin = admin;
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

  void _openEmployee(TeamMember member) {
    Navigator.of(context)
        .push(
          MaterialPageRoute<void>(
            builder: (_) => EmployeeDetailPage(employeeId: member.employee.id),
          ),
        )
        .then((_) {
          if (mounted) _load();
        });
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: <Widget>[
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            Insets.lg,
            Insets.lg,
            Insets.lg,
            Insets.xxxl,
          ),
          children: <Widget>[
            Text(
              _admin == null
                  ? 'Team overview'
                  : 'Good day, ${_admin!.name.split(' ').first}',
              style: theme.textTheme.headlineSmall,
            ),
            const SizedBox(height: 2),
            Text(Fmt.longDate(DateTime.now()), style: theme.textTheme.bodySmall),
            const SizedBox(height: Insets.xl),
            if (_loading)
              const LoadingCards(count: 3)
            else if (_error != null)
              ErrorState(onRetry: _load)
            else
              ..._content(_overview!),
          ],
        ),
      ),
    );
  }

  List<Widget> _content(TeamOverview o) {
    final List<TeamMember> longStops = o.members
        .where((TeamMember m) => m.isLongStop(_config.longStopThresholdMinutes))
        .toList();
    final List<TeamMember> degraded =
        o.members.where((TeamMember m) => m.isDegraded).toList();
    final List<TeamMember> notStarted = o.members
        .where((TeamMember m) => m.status == WorkStatus.notStarted)
        .toList();

    return <Widget>[
      if (longStops.isNotEmpty) ...<Widget>[
        AlertBanner(
          icon: Icons.timer_off_outlined,
          title: '${longStops.length} long stop'
              '${longStops.length == 1 ? '' : 's'}',
          message: longStops
              .map((TeamMember m) =>
                  '${m.employee.firstName} · ${Fmt.duration(m.openStopDuration!)}')
              .join('  ·  '),
          tone: AppColors.danger,
          actionLabel: 'View',
          onAction: () => _openEmployee(longStops.first),
        ),
        const SizedBox(height: Insets.md),
      ],
      if (degraded.isNotEmpty) ...<Widget>[
        AlertBanner(
          icon: Icons.gps_off_rounded,
          title: 'Tracking degraded for ${degraded.length}',
          message: degraded
              .map((TeamMember m) =>
                  '${m.employee.firstName} · ${m.locationHealth.title}')
              .join('  ·  '),
          actionLabel: 'View',
          onAction: () => _openEmployee(degraded.first),
        ),
        const SizedBox(height: Insets.md),
      ],
      if (notStarted.isNotEmpty) ...<Widget>[
        AlertBanner(
          icon: Icons.hourglass_empty_rounded,
          title: '${notStarted.length} not started',
          message: notStarted.map((TeamMember m) => m.employee.name).join(', '),
          tone: AppColors.info,
        ),
        const SizedBox(height: Insets.md),
      ],

      const SizedBox(height: Insets.sm),
      StatGrid(
        children: <Widget>[
          StatTile(
            icon: Icons.directions_walk_rounded,
            value: '${o.workingCount}',
            label: 'Working now',
            tone: AppColors.statusMoving,
          ),
          StatTile(
            icon: Icons.pause_circle_outline_rounded,
            value: '${o.idleCount}',
            label: 'Stopped / idle',
            tone: AppColors.statusIdle,
          ),
          StatTile(
            icon: Icons.cloud_off_rounded,
            value: '${o.offlineCount}',
            label: 'Offline / no GPS',
            tone: AppColors.statusOffline,
          ),
          StatTile(
            icon: Icons.person_off_outlined,
            value: '${o.absentCount}',
            label: 'Absent',
            tone: AppColors.danger,
          ),
          StatTile(
            icon: Icons.route_outlined,
            value: Fmt.km(o.totalDistanceKm),
            label: 'Team distance',
          ),
          StatTile(
            icon: Icons.storefront_outlined,
            value: '${o.totalVisits}',
            label: 'Visits today',
            tone: AppColors.info,
          ),
          StatTile(
            icon: Icons.description_outlined,
            value: '${o.totalReports}',
            label: 'Reports today',
            tone: AppColors.success,
          ),
          StatTile(
            icon: Icons.rate_review_outlined,
            value: '${o.pendingReviewCount}',
            label: 'Awaiting review',
            tone: AppColors.warning,
            onTap: () => AdminShellScope.maybeOf(context)?.goToTab(2),
          ),
        ],
      ),

      const SizedBox(height: Insets.xl),
      AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Attendance · ${Fmt.percent(o.attendancePercent)}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: Insets.md),
            ProportionBar(
              segments: <ProportionSegment>[
                ProportionSegment(
                  label: 'Working',
                  value: o.workingCount.toDouble(),
                  color: AppColors.statusMoving,
                ),
                ProportionSegment(
                  label: 'Idle',
                  value: o.idleCount.toDouble(),
                  color: AppColors.statusIdle,
                ),
                ProportionSegment(
                  label: 'Offline',
                  value: o.offlineCount.toDouble(),
                  color: AppColors.statusOffline,
                ),
                ProportionSegment(
                  label: 'Absent',
                  value: o.absentCount.toDouble(),
                  color: AppColors.danger,
                ),
              ],
            ),
          ],
        ),
      ),

      SectionHeader(
        title: 'Live team status',
        subtitle: '${o.headcount} field staff',
        actionLabel: 'All',
        onAction: () => AdminShellScope.maybeOf(context)?.goToTab(1),
        padding: const EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      if (o.members.isEmpty)
        const EmptyState(
          icon: Icons.groups_outlined,
          title: 'No employees yet',
          message: 'Add a field employee from the Admin tab to get started.',
        )
      else
        ...o.members.map(
          (TeamMember m) => TeamStatusTile(
            member: m,
            longStopThresholdMinutes: _config.longStopThresholdMinutes,
            onTap: () => _openEmployee(m),
          ),
        ),
    ];
  }
}
