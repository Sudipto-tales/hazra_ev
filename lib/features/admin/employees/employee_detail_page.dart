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

  /// Reopening shifts numbers a manager will later be asked about, so the
  /// reason is mandatory and stored with it.
  Future<void> _reopenDay(EmployeeDay day) async {
    final String? reason = await _askReopenReason(context);
    if (reason == null || !mounted) return;

    try {
      await AppScope.of(context).adminRepository.reopenDay(
            employeeId: day.employee.id,
            date: day.date,
            reason: reason,
          );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: AppColors.danger,
          content: Text('Could not reopen the day. Try again.'),
        ),
      );
      return;
    }

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${day.employee.firstName} can work this day again'),
      ),
    );
    _load();
  }

  List<Widget> _content(EmployeeDay day) {
    final List<Widget> lock = <Widget>[
      if (day.dayState.isLocked || day.closeout != null) ...<Widget>[
        _CloseoutCard(day: day, onReopen: () => _reopenDay(day)),
        const SizedBox(height: Insets.lg),
      ],
    ];

    if (day.isEmpty) {
      return <Widget>[
        ...lock,
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
      ...lock,
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

/// Asks for the reason a day is being reopened. Returns null if the admin
/// backed out; never returns an empty string.
Future<String?> _askReopenReason(BuildContext context) {
  final TextEditingController controller = TextEditingController();
  final GlobalKey<FormState> form = GlobalKey<FormState>();

  return showDialog<String>(
    context: context,
    builder: (BuildContext context) {
      return AlertDialog(
        title: const Text('Reopen this day?'),
        content: Form(
          key: form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'The employee will be able to start a new session on this day. '
                'What they already submitted is kept on record.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              const SizedBox(height: Insets.lg),
              TextFormField(
                controller: controller,
                autofocus: true,
                textCapitalization: TextCapitalization.sentences,
                maxLength: 160,
                decoration: const InputDecoration(
                  labelText: 'Reason',
                  hintText: 'Why is this day being reopened?',
                ),
                validator: (String? v) => (v ?? '').trim().isEmpty
                    ? 'A reason is required'
                    : null,
              ),
            ],
          ),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              if (!(form.currentState?.validate() ?? false)) return;
              Navigator.pop(context, controller.text.trim());
            },
            child: const Text('Reopen'),
          ),
        ],
      );
    },
  );
}

/// The employee's declaration, and the admin's one lever over it.
///
/// Declared and tracked figures are shown as two separate rows on purpose. The
/// gap between them is the reason the declaration is collected at all, and
/// averaging or reconciling them here would hide exactly what an admin opened
/// this screen to see.
class _CloseoutCard extends StatelessWidget {
  const _CloseoutCard({required this.day, required this.onReopen});

  final EmployeeDay day;
  final VoidCallback onReopen;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final DayCloseout? c = day.closeout;
    final bool locked = day.dayState.isLocked;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(
                locked ? Icons.lock_outline_rounded : Icons.lock_open_rounded,
                size: 18,
                color: locked ? AppColors.info : AppColors.success,
              ),
              const SizedBox(width: Insets.sm),
              Expanded(
                child: Text(
                  locked ? 'Day closed by employee' : 'Day reopened',
                  style: theme.textTheme.titleMedium?.copyWith(fontSize: 15),
                ),
              ),
              if (c != null)
                Row(
                  children: <Widget>[
                    for (int i = 1; i <= 5; i++)
                      Icon(
                        i <= c.rating
                            ? Icons.star_rounded
                            : Icons.star_outline_rounded,
                        size: 16,
                        color: i <= c.rating
                            ? AppColors.warning
                            : AppColors.textTertiary,
                      ),
                  ],
                ),
            ],
          ),
          if (c != null) ...<Widget>[
            const Divider(height: Insets.xl),
            KeyValueRow(
              label: 'Employee reported',
              value: '${Fmt.km(c.declaredDistanceKm)} · '
                  '${c.declaredVisits} visits',
              dense: true,
            ),
            KeyValueRow(
              label: 'GPS tracked',
              value: '${Fmt.km(c.measuredDistanceKm)} · '
                  '${c.measuredVisits} visits',
              dense: true,
            ),
            if (c.distanceDeviationPercent != null)
              KeyValueRow(
                label: 'Difference',
                value: '${c.distanceDeviationPercent! > 0 ? '+' : ''}'
                    '${c.distanceDeviationPercent!.toStringAsFixed(0)}%',
                dense: true,
                valueColor:
                    c.distanceLooksOff ? AppColors.danger : AppColors.success,
              ),
            KeyValueRow(
              label: 'Submitted',
              value: Fmt.time(c.submittedAt),
              dense: true,
            ),
            if (c.tags.isNotEmpty) ...<Widget>[
              const SizedBox(height: Insets.md),
              Wrap(
                spacing: Insets.sm,
                runSpacing: Insets.xs,
                children: <Widget>[
                  for (final DayFeedbackTag tag in c.tags)
                    StatusBadge(
                      label: tag.label,
                      tone: BadgeTone.neutral,
                      dense: true,
                    ),
                ],
              ),
            ],
            if (c.feedback != null) ...<Widget>[
              const SizedBox(height: Insets.md),
              Text(c.feedback!, style: theme.textTheme.bodySmall),
            ],
          ] else ...<Widget>[
            const SizedBox(height: Insets.sm),
            Text(
              'No declaration — this day was closed without the employee '
              'filling in the end-of-day form.',
              style: theme.textTheme.bodySmall,
            ),
          ],
          if (locked) ...<Widget>[
            const SizedBox(height: Insets.lg),
            SizedBox(
              height: Sizes.touchTarget,
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onReopen,
                icon: const Icon(Icons.lock_open_rounded, size: 19),
                label: const Text('Reopen day'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
