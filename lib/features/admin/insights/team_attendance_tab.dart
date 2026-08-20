import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/attendance_swatch.dart';
import '../../../widgets/states.dart';
import '../employees/employee_detail_page.dart';

/// Employee rows × days of the month.
///
/// The name column is pinned and the day strip scrolls horizontally. Both live
/// inside a single horizontal scroll view so every row scrolls together —
/// per-row scroll views would drift out of alignment.
class TeamAttendanceTab extends StatefulWidget {
  const TeamAttendanceTab({super.key});

  @override
  State<TeamAttendanceTab> createState() => _TeamAttendanceTabState();
}

class _TeamAttendanceTabState extends State<TeamAttendanceTab> {
  static const double _cell = 30;
  static const double _nameWidth = 132;

  bool _loading = true;
  Object? _error;
  TeamAttendanceGrid? _grid;
  late DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);

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
      final TeamAttendanceGrid grid = await AppScope.of(context)
          .adminRepository
          .teamAttendance(month: _month);
      if (!mounted) return;
      setState(() {
        _grid = grid;
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

  void _shiftMonth(int delta) {
    setState(() => _month = DateTime(_month.year, _month.month + delta));
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final DateTime now = DateTime.now();
    final bool canGoForward =
        _month.isBefore(DateTime(now.year, now.month));

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        Insets.lg,
        Insets.lg,
        Insets.lg,
        Insets.xxxl,
      ),
      children: <Widget>[
        AppCard(
          padding: const EdgeInsets.symmetric(
            horizontal: Insets.sm,
            vertical: Insets.xs,
          ),
          child: Row(
            children: <Widget>[
              IconButton(
                onPressed: () => _shiftMonth(-1),
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              Expanded(
                child: Text(
                  Fmt.monthYear(_month),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              IconButton(
                onPressed: canGoForward ? () => _shiftMonth(1) : null,
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
        ),
        const SizedBox(height: Insets.lg),
        if (_loading)
          const LoadingCards(count: 3)
        else if (_error != null)
          ErrorState(onRetry: _load)
        else if (_grid!.rows.isEmpty)
          const EmptyState(
            icon: Icons.grid_on_outlined,
            title: 'No employees',
            message: 'Add a field employee to see the attendance matrix.',
          )
        else
          ..._matrix(_grid!),
      ],
    );
  }

  List<Widget> _matrix(TeamAttendanceGrid grid) {
    final DateTime today = Fmt.dayOnly(DateTime.now());

    return <Widget>[
      AppCard(
        padding: const EdgeInsets.all(Insets.sm),
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              // Header: day numbers.
              Row(
                children: <Widget>[
                  const SizedBox(width: _nameWidth),
                  ...grid.days.map(
                    (DateTime d) => SizedBox(
                      width: _cell,
                      child: Column(
                        children: <Widget>[
                          Text(
                            Fmt.weekdayShort(d).substring(0, 1),
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                          Text(
                            '${d.day}',
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(width: Insets.md),
                  const SizedBox(width: 44, child: Text('%')),
                ],
              ),
              const SizedBox(height: Insets.sm),
              ...grid.rows.map((TeamAttendanceRow row) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(
                    children: <Widget>[
                      SizedBox(
                        width: _nameWidth,
                        child: InkWell(
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => EmployeeDetailPage(
                                employeeId: row.employee.id,
                              ),
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.only(right: Insets.sm),
                            child: Text(
                              row.employee.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        ),
                      ),
                      ...grid.days.map((DateTime d) {
                        final AttendanceStatus? status =
                            row.byDayKey[TeamAttendanceRow.dayKey(d)];
                        final (Color bg, Color fg) = AttendanceSwatch.colors(
                          context,
                          status,
                          isFuture: d.isAfter(today),
                        );
                        return Padding(
                          padding: const EdgeInsets.all(2),
                          child: Tooltip(
                            message: '${Fmt.mediumDate(d)} · '
                                '${status?.label ?? 'No data'}',
                            child: Container(
                              width: _cell - 4,
                              height: _cell - 4,
                              decoration: BoxDecoration(
                                color: bg,
                                borderRadius:
                                    BorderRadius.circular(Radii.sm - 2),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                _glyph(status),
                                style: TextStyle(
                                  color: fg,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                      const SizedBox(width: Insets.md),
                      SizedBox(
                        width: 44,
                        child: Text(
                          Fmt.percent(row.attendancePercent),
                          style: Theme.of(context)
                              .textTheme
                              .labelSmall
                              ?.copyWith(
                                color: row.attendancePercent >= 90
                                    ? AppColors.success
                                    : row.attendancePercent >= 75
                                        ? AppColors.warning
                                        : AppColors.danger,
                              ),
                        ),
                      ),
                    ],
                  ),
                );
              }),
            ],
          ),
        ),
      ),
      const SizedBox(height: Insets.md),
      const _Legend(),
    ];
  }

  static String _glyph(AttendanceStatus? status) => switch (status) {
        AttendanceStatus.present => 'P',
        AttendanceStatus.partial => '½',
        AttendanceStatus.absent => 'A',
        AttendanceStatus.holiday => 'H',
        AttendanceStatus.weekend => '·',
        _ => '',
      };
}

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    const List<(String, AttendanceStatus)> items =
        <(String, AttendanceStatus)>[
      ('Present', AttendanceStatus.present),
      ('Partial', AttendanceStatus.partial),
      ('Absent', AttendanceStatus.absent),
      ('Holiday', AttendanceStatus.holiday),
      ('Weekend', AttendanceStatus.weekend),
    ];

    return AppCard(
      padding: const EdgeInsets.all(Insets.md),
      child: Wrap(
        spacing: Insets.lg,
        runSpacing: Insets.sm,
        children: items.map(((String, AttendanceStatus) item) {
          final (Color bg, Color fg) =
              AttendanceSwatch.colors(context, item.$2);
          return Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: bg,
                  border: Border.all(color: fg.withValues(alpha: 0.5)),
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
              const SizedBox(width: Insets.xs),
              Text(item.$1, style: Theme.of(context).textTheme.bodySmall),
            ],
          );
        }).toList(),
      ),
    );
  }
}
