import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/auto_refresh.dart';
import '../../../widgets/mini_bar_chart.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../employees/employee_detail_page.dart';
import '../map/live_team_map_page.dart';
import '../shell/admin_shell.dart';
import '../widgets/team_status_tile.dart';

/// Live team status for today: alerts first, then the numbers, then the roster.
class AdminDashboardPage extends StatefulWidget {
  const AdminDashboardPage({super.key});

  @override
  State<AdminDashboardPage> createState() => _AdminDashboardPageState();
}

class _AdminDashboardPageState extends State<AdminDashboardPage>
    with WidgetsBindingObserver, AutoRefresh<AdminDashboardPage> {
  bool _loading = true;
  Object? _error;
  TeamOverview? _overview;
  TrackingConfig _config = TrackingConfig.defaults;
  AdminUser? _admin;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => runRefresh(manual: true));
  }

  /// The poll re-runs this same path — there is exactly one way this screen
  /// loads, whether the trigger was the timer, the toolbar button or a pull.
  @override
  Future<bool> onAutoRefresh() => _load();

  /// [silent] is what makes a 15 s poll tolerable: a background refresh leaves
  /// the current numbers on screen and swaps them when the new ones land,
  /// instead of blanking the dashboard into skeletons every quarter minute.
  /// Only the first load (and an explicit retry) shows the loading state.
  Future<bool> _load({bool silent = true}) async {
    if (!silent || _overview == null) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final AppScope scope = AppScope.of(context);
      final TeamOverview overview = await scope.adminRepository.overview();
      final TrackingConfig config = await scope.adminRepository.config();
      final AdminUser admin = await scope.adminRepository.profile();
      if (!mounted) return false;
      setState(() {
        _overview = overview;
        _config = config;
        _admin = admin;
        _error = null;
        _loading = false;
      });
      return true;
    } catch (e) {
      if (!mounted) return false;
      setState(() {
        // A failed poll must not throw away data that is merely a little old.
        // The error state is for a screen with nothing to show.
        if (_overview == null) _error = e;
        _loading = false;
      });
      return false;
    }
  }

  /// The live map is a pushed route rather than a sixth tab: it is the one
  /// admin screen that asks every watched device to drain 20x faster, so it
  /// should be somewhere an admin goes deliberately and leaves when done —
  /// an IndexedStack tab would sit there holding watches alive behind
  /// whatever else they were looking at.
  void _openLiveMap() {
    Navigator.of(context)
        .push(
          MaterialPageRoute<void>(builder: (_) => const LiveTeamMapPage()),
        )
        .then((_) {
      if (mounted) unawaited(runRefresh(manual: true));
    });
  }

  void _openEmployee(TeamMember member) {
    Navigator.of(context)
        .push(
          MaterialPageRoute<void>(
            builder: (_) => EmployeeDetailPage(employeeId: member.employee.id),
          ),
        )
        .then((_) {
          if (mounted) unawaited(runRefresh(manual: true));
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
            onPressed: _openLiveMap,
            icon: const Icon(Icons.map_rounded),
            tooltip: 'Live team map',
          ),
          IconButton(
            onPressed: () => unawaited(runRefresh(manual: true)),
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => runRefresh(manual: true),
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
            const SizedBox(height: Insets.sm),
            AutoRefreshStamp(
              clock: autoRefreshClock,
              lastRefreshedAt: () => lastRefreshedAt,
            ),
            const SizedBox(height: Insets.lg),
            if (_loading)
              const LoadingCards(count: 3)
            else if (_error != null)
              ErrorState(onRetry: () => unawaited(_load(silent: false)))
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
        .where((TeamMember m) =>
            m.active && m.status == WorkStatus.notStarted)
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
          title: '${degraded.length} offline',
          message: degraded
              .map((TeamMember m) =>
                  '${m.employee.firstName} · ${m.degradedReason ?? 'No signal'}')
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
            label: 'Companies visited',
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
                // Not "Absent": nobody is a confirmed absence while the day is
                // still open — they are people who have not clocked on yet, and
                // this is the bucket that made the old bar fall short of the
                // headcount and read 0% at 9am.
                ProportionSegment(
                  label: 'Not started',
                  value: notStarted.length.toDouble(),
                  color: AppColors.statusOffline.withValues(alpha: 0.4),
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
