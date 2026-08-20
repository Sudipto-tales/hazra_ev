import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../../../widgets/status_badge.dart';
import '../../home/widgets/activity_timeline.dart';
import '../../home/widgets/visit_tile.dart';
import '../manage/employee_form_page.dart';
import '../map/route_map_page.dart';
import '../reports/admin_report_detail_page.dart';
import '../widgets/date_scrubber.dart';

/// One employee's day, as the admin sees it: status, numbers, route entry
/// point, visits and the full activity timeline.
class EmployeeDetailPage extends StatefulWidget {
  const EmployeeDetailPage({super.key, required this.employeeId});

  final String employeeId;

  @override
  State<EmployeeDetailPage> createState() => _EmployeeDetailPageState();
}

class _EmployeeDetailPageState extends State<EmployeeDetailPage> {
  bool _loading = true;
  Object? _error;
  EmployeeDay? _day;
  TeamMember? _member;
  TrackingConfig _config = TrackingConfig.defaults;
  DateTime _date = DateTime.now();

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
      final EmployeeDay day = await scope.adminRepository.employeeDay(
        employeeId: widget.employeeId,
        date: _date,
      );
      final TeamMember member =
          await scope.adminRepository.memberById(widget.employeeId);
      final TrackingConfig config = await scope.adminRepository.config();
      if (!mounted) return;
      setState(() {
        _day = day;
        _member = member;
        _config = config;
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

  void _setDate(DateTime d) {
    setState(() => _date = d);
    _load();
  }

  Future<void> _openReport(String reportId) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AdminReportDetailPage(reportId: reportId),
      ),
    );
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    final Employee? employee = _day?.employee ?? _member?.employee;

    return Scaffold(
      appBar: AppBar(
        title: Text(employee?.firstName ?? 'Employee'),
        actions: <Widget>[
          if (employee != null)
            PopupMenuButton<String>(
              onSelected: (String value) async {
                switch (value) {
                  case 'edit':
                    final bool? saved = await Navigator.of(context).push<bool>(
                      MaterialPageRoute<bool>(
                        builder: (_) => EmployeeFormPage(employee: employee),
                      ),
                    );
                    if (saved == true && mounted) _load();
                }
              },
              itemBuilder: (BuildContext context) =>
                  const <PopupMenuEntry<String>>[
                PopupMenuItem<String>(
                  value: 'edit',
                  child: Text('Edit employee'),
                ),
              ],
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          if (employee != null) _Header(employee: employee, member: _member),
          const SizedBox(height: Insets.lg),
          DateScrubber(
            date: _date,
            lastDate: DateTime.now(),
            firstDate: DateTime.now().subtract(const Duration(days: 120)),
            onChanged: _setDate,
          ),
          const SizedBox(height: Insets.lg),
          if (_loading)
            const LoadingCards(count: 3)
          else if (_error != null)
            ErrorState(onRetry: _load)
          else
            ..._content(_day!),
        ],
      ),
    );
  }

  List<Widget> _content(EmployeeDay day) {
    if (day.isEmpty) {
      return <Widget>[
        AppCard(
          child: EmptyState(
            icon: Icons.event_busy_outlined,
            title: day.attendance?.status.label ?? 'No data',
            message: 'No session was opened on '
                '${Fmt.mediumDate(_date)}.',
          ),
        ),
      ];
    }

    final DaySummary s = day.summary;
    final DateTime now = DateTime.now();

    return <Widget>[
      StatGrid(
        children: <Widget>[
          StatTile(
            icon: Icons.login_rounded,
            value: Fmt.time(s.joiningTime),
            label: 'Joining time',
          ),
          StatTile(
            icon: Icons.timelapse_rounded,
            value: Fmt.duration(s.workedDuration),
            label: 'Worked',
            tone: AppColors.info,
          ),
          StatTile(
            icon: Icons.route_outlined,
            value: Fmt.km(s.distanceKm),
            label: 'Distance',
          ),
          StatTile(
            icon: Icons.storefront_outlined,
            value: '${s.companiesVisited}',
            label: 'Companies',
            tone: AppColors.success,
          ),
          StatTile(
            icon: Icons.description_outlined,
            value: '${s.reportsSubmitted}',
            label: 'Reports',
            tone: AppColors.success,
          ),
          StatTile(
            icon: Icons.pause_circle_outline_rounded,
            value: Fmt.duration(s.longestStop),
            label: 'Longest stop',
            tone: s.longestStop.inMinutes >= _config.longStopThresholdMinutes
                ? AppColors.danger
                : AppColors.warning,
          ),
        ],
      ),

      const SizedBox(height: Insets.xl),
      SizedBox(
        height: Sizes.primaryActionHeight,
        child: FilledButton.icon(
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => RouteMapPage(
                employee: day.employee,
                date: _date,
              ),
            ),
          ),
          icon: const Icon(Icons.map_outlined),
          label: const Text('View route on map'),
        ),
      ),

      SectionHeader(
        title: 'Visits',
        subtitle: '${day.visits.length} on this day',
        padding: const EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      if (day.visits.isEmpty)
        const EmptyState(
          icon: Icons.storefront_outlined,
          title: 'No visits',
          message: 'No company visit was recorded on this day.',
        )
      else
        ...day.visits.map(
          (CompanyVisit v) => Padding(
            padding: const EdgeInsets.only(bottom: Insets.md),
            child: VisitTile(
              visit: v,
              now: now,
              onReportTap: _openReport,
            ),
          ),
        ),

      const SectionHeader(
        title: 'Activity timeline',
        padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      ActivityTimeline(events: day.activity, onReportTap: _openReport),
    ];
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.employee, required this.member});

  final Employee employee;
  final TeamMember? member;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              ProfileAvatar(
                initials: employee.initials,
                imageUrl: employee.avatarUrl,
                size: Sizes.avatarLg * 0.72,
              ),
              const SizedBox(width: Insets.lg),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(employee.name, style: theme.textTheme.titleLarge),
                    const SizedBox(height: 2),
                    Text(
                      employee.designation,
                      style: theme.textTheme.bodySmall,
                    ),
                    const SizedBox(height: Insets.sm),
                    if (member != null) StatusBadge.work(member!.status),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.md),
          const Divider(height: Insets.xl),
          KeyValueRow(
            label: 'Employee ID',
            value: employee.employeeCode,
            dense: true,
          ),
          KeyValueRow(
            label: 'Department',
            value: Fmt.orDash(employee.department),
            dense: true,
          ),
          KeyValueRow(
            label: 'Region',
            value: Fmt.orDash(employee.region),
            dense: true,
          ),
          KeyValueRow(label: 'Phone', value: employee.phone, dense: true),
          if (member?.lastFix != null)
            KeyValueRow(
              label: 'Last fix',
              value: Fmt.relative(member!.lastFix!.recordedAt),
              dense: true,
            ),
        ],
      ),
    );
  }
}
