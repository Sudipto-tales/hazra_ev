import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/mock/mock_data.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/mini_bar_chart.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';
import '../../widgets/status_badge.dart';
import 'widgets/month_grid.dart';

class CalendarPage extends StatefulWidget {
  const CalendarPage({super.key});

  @override
  State<CalendarPage> createState() => _CalendarPageState();
}

class _CalendarPageState extends State<CalendarPage> {
  DateTime _month = DateTime(MockData.today.year, MockData.today.month);
  DateTime? _selected = MockData.today;

  bool _loadingMonth = true;
  bool _loadingStats = true;
  Object? _error;

  Map<int, Attendance> _records = <int, Attendance>{};
  Attendance? _selectedRecord;
  PeriodStatistics? _stats;
  StatsRange _range = StatsRange.thisMonth;
  DateTimeRange? _customRange;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadMonth();
      _loadStats();
    });
  }

  Future<void> _loadMonth() async {
    setState(() {
      _loadingMonth = true;
      _error = null;
    });
    try {
      final List<Attendance> rows =
          await AppScope.of(context).repository.attendance(month: _month);
      if (!mounted) return;
      setState(() {
        _records = <int, Attendance>{
          for (final Attendance a in rows) a.date.day: a,
        };
        _selectedRecord = _selected == null
            ? null
            : _records[_selected!.day] != null &&
                    _selected!.month == _month.month &&
                    _selected!.year == _month.year
                ? _records[_selected!.day]
                : null;
        _loadingMonth = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loadingMonth = false;
      });
    }
  }

  Future<void> _loadStats() async {
    setState(() => _loadingStats = true);
    final PeriodStatistics s =
        await AppScope.of(context).repository.statistics(
              range: _range,
              from: _customRange?.start,
              to: _customRange?.end,
            );
    if (!mounted) return;
    setState(() {
      _stats = s;
      _loadingStats = false;
    });
  }

  void _shiftMonth(int delta) {
    setState(() {
      _month = DateTime(_month.year, _month.month + delta);
    });
    _loadMonth();
  }

  Future<void> _selectDay(DateTime date) async {
    setState(() => _selected = date);
    final Attendance? record =
        await AppScope.of(context).repository.attendanceForDate(date);
    if (!mounted) return;
    setState(() => _selectedRecord = record);
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Calendar'),
          bottom: const TabBar(
            tabs: <Widget>[
              Tab(text: 'Attendance'),
              Tab(text: 'Statistics'),
            ],
          ),
        ),
        body: TabBarView(
          children: <Widget>[
            _AttendanceTab(
              month: _month,
              records: _records,
              selected: _selected,
              selectedRecord: _selectedRecord,
              loading: _loadingMonth,
              error: _error,
              onRetry: _loadMonth,
              onShiftMonth: _shiftMonth,
              onSelectDay: _selectDay,
            ),
            _StatisticsTab(
              stats: _stats,
              loading: _loadingStats,
              range: _range,
              onRangeChanged: (StatsRange r) async {
                if (r == StatsRange.custom) {
                  final DateTimeRange? picked = await showDateRangePicker(
                    context: context,
                    firstDate: MockData.today.subtract(const Duration(days: 180)),
                    lastDate: MockData.today,
                    initialDateRange: _customRange,
                  );
                  if (picked == null) return;
                  _customRange = picked;
                }
                setState(() => _range = r);
                _loadStats();
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _AttendanceTab extends StatelessWidget {
  const _AttendanceTab({
    required this.month,
    required this.records,
    required this.selected,
    required this.selectedRecord,
    required this.loading,
    required this.error,
    required this.onRetry,
    required this.onShiftMonth,
    required this.onSelectDay,
  });

  final DateTime month;
  final Map<int, Attendance> records;
  final DateTime? selected;
  final Attendance? selectedRecord;
  final bool loading;
  final Object? error;
  final VoidCallback onRetry;
  final void Function(int delta) onShiftMonth;
  final void Function(DateTime date) onSelectDay;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    if (error != null) return ErrorState(onRetry: onRetry);

    final bool canGoForward = month.isBefore(
      DateTime(MockData.today.year, MockData.today.month),
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        Insets.lg,
        Insets.lg,
        Insets.lg,
        Insets.xxxl,
      ),
      children: <Widget>[
        AppCard(
          child: Column(
            children: <Widget>[
              Row(
                children: <Widget>[
                  IconButton(
                    onPressed: () => onShiftMonth(-1),
                    icon: const Icon(Icons.chevron_left_rounded),
                  ),
                  Expanded(
                    child: Center(
                      child: Text(
                        Fmt.monthYear(month),
                        style: theme.textTheme.titleLarge,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: canGoForward ? () => onShiftMonth(1) : null,
                    icon: const Icon(Icons.chevron_right_rounded),
                  ),
                ],
              ),
              const SizedBox(height: Insets.sm),
              if (loading)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: Insets.xxxl),
                  child: Center(child: CircularProgressIndicator()),
                )
              else
                MonthGrid(
                  month: month,
                  records: records,
                  selected: selected,
                  onSelect: onSelectDay,
                ),
              const SizedBox(height: Insets.lg),
              const Divider(height: 1),
              const SizedBox(height: Insets.md),
              const MonthLegend(),
            ],
          ),
        ),
        const SizedBox(height: Insets.xl),
        if (selected != null) ...<Widget>[
          Text(Fmt.longDate(selected!), style: theme.textTheme.titleLarge),
          const SizedBox(height: Insets.md),
          _DayDetail(record: selectedRecord),
        ],
      ],
    );
  }
}

class _DayDetail extends StatelessWidget {
  const _DayDetail({required this.record});

  final Attendance? record;

  @override
  Widget build(BuildContext context) {
    if (record == null) {
      return AppCard(
        child: EmptyState(
          icon: Icons.event_busy_outlined,
          title: 'No record',
          message: 'Nothing was tracked on this day.',
        ),
      );
    }

    final Attendance a = record!;
    final bool worked = a.status == AttendanceStatus.present ||
        a.status == AttendanceStatus.partial;

    return Column(
      children: <Widget>[
        AppCard(
          child: Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      a.status.label,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      worked
                          ? '${Fmt.time(a.joiningTime)} – ${Fmt.time(a.endTime)}'
                          : 'No sessions recorded',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              StatusBadge.attendance(a.status),
            ],
          ),
        ),
        if (worked) ...<Widget>[
          const SizedBox(height: Insets.md),
          StatGrid(
            children: <Widget>[
              StatTile(
                icon: Icons.login_rounded,
                value: Fmt.time(a.joiningTime),
                label: 'Joining time',
                tone: AppColors.success,
              ),
              StatTile(
                icon: Icons.logout_rounded,
                value: Fmt.time(a.endTime),
                label: 'Final end time',
                tone: AppColors.textSecondary,
              ),
              StatTile(
                icon: Icons.timer_outlined,
                value: Fmt.duration(a.workedDuration),
                label: 'Working time',
                tone: AppColors.primary,
              ),
              StatTile(
                icon: Icons.layers_outlined,
                value: '${a.sessionCount}',
                label: 'Sessions',
                tone: AppColors.info,
              ),
              StatTile(
                icon: Icons.route_rounded,
                value: Fmt.km(a.distanceKm),
                label: 'Distance',
                tone: AppColors.primary,
              ),
              StatTile(
                icon: Icons.business_rounded,
                value: '${a.companiesVisited}',
                label: 'Companies visited',
                tone: AppColors.warning,
              ),
              StatTile(
                icon: Icons.description_outlined,
                value: '${a.reportsSubmitted}',
                label: 'Reports',
                tone: AppColors.primary,
              ),
              StatTile(
                icon: Icons.pause_circle_outline_rounded,
                value: Fmt.duration(a.stopDuration),
                label: 'Total stop time',
                tone: AppColors.warning,
                caption: 'Longest ${Fmt.duration(a.longestStop)}',
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _StatisticsTab extends StatelessWidget {
  const _StatisticsTab({
    required this.stats,
    required this.loading,
    required this.range,
    required this.onRangeChanged,
  });

  final PeriodStatistics? stats;
  final bool loading;
  final StatsRange range;
  final ValueChanged<StatsRange> onRangeChanged;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        Insets.lg,
        Insets.lg,
        Insets.lg,
        Insets.xxxl,
      ),
      children: <Widget>[
        SizedBox(
          height: 36,
          child: ListView(
            scrollDirection: Axis.horizontal,
            children: StatsRange.values
                .map(
                  (StatsRange r) => Padding(
                    padding: const EdgeInsets.only(right: Insets.sm),
                    child: ChoiceChip(
                      label: Text(r.label),
                      selected: range == r,
                      onSelected: (_) => onRangeChanged(r),
                    ),
                  ),
                )
                .toList(growable: false),
          ),
        ),
        const SizedBox(height: Insets.lg),
        if (loading || stats == null)
          const LoadingCards(count: 3)
        else ...<Widget>[
          Text(stats!.rangeLabel, style: theme.textTheme.titleLarge),
          const SizedBox(height: Insets.md),
          AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    Text(
                      Fmt.percent(stats!.attendancePercent),
                      style: theme.textTheme.displaySmall,
                    ),
                    const SizedBox(width: Insets.sm),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Text(
                        'attendance',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.lg),
                ProportionBar(
                  segments: <ProportionSegment>[
                    ProportionSegment(
                      label: 'Present',
                      value: stats!.presentDays.toDouble(),
                      color: AppColors.success,
                    ),
                    ProportionSegment(
                      label: 'Absent',
                      value: stats!.absentDays.toDouble(),
                      color: AppColors.danger,
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'Averages',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          StatGrid(
            children: <Widget>[
              StatTile(
                icon: Icons.login_rounded,
                value: Fmt.time(stats!.averageJoiningTime),
                label: 'Average joining time',
                tone: AppColors.success,
              ),
              StatTile(
                icon: Icons.timer_outlined,
                value: Fmt.duration(stats!.averageWorkedDuration),
                label: 'Average working hours',
                tone: AppColors.primary,
              ),
              StatTile(
                icon: Icons.route_rounded,
                value: Fmt.km(stats!.averageDistanceKm),
                label: 'Average daily distance',
                tone: AppColors.primary,
              ),
              StatTile(
                icon: Icons.business_rounded,
                value: stats!.averageVisitsPerDay.toStringAsFixed(1),
                label: 'Average visits / day',
                tone: AppColors.warning,
              ),
              StatTile(
                icon: Icons.description_outlined,
                value: stats!.averageReportsPerDay.toStringAsFixed(1),
                label: 'Average reports / day',
                tone: AppColors.info,
              ),
              StatTile(
                icon: Icons.event_available_outlined,
                value: '${stats!.presentDays}',
                label: 'Working days',
                tone: AppColors.success,
                caption: 'of ${stats!.workingDays}',
              ),
            ],
          ),
          const SectionHeader(
            title: 'Totals',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                KeyValueRow(
                  label: 'Total distance',
                  value: Fmt.km(stats!.totalDistanceKm),
                  icon: Icons.route_rounded,
                ),
                KeyValueRow(
                  label: 'Total company visits',
                  value: '${stats!.totalVisits}',
                  icon: Icons.business_rounded,
                ),
                KeyValueRow(
                  label: 'Total reports',
                  value: '${stats!.totalReports}',
                  icon: Icons.description_outlined,
                ),
                KeyValueRow(
                  label: 'Present days',
                  value: '${stats!.presentDays}',
                  icon: Icons.event_available_outlined,
                ),
                KeyValueRow(
                  label: 'Absent days',
                  value: '${stats!.absentDays}',
                  icon: Icons.event_busy_outlined,
                  valueColor:
                      stats!.absentDays > 0 ? AppColors.danger : null,
                ),
              ],
            ),
          ),
          const SectionHeader(
            title: 'Distance per day',
            padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: MiniBarChart(data: stats!.dailyDistance),
          ),
        ],
      ],
    );
  }
}
