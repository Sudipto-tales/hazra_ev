import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/states.dart';
import 'create_report_page.dart';
import 'report_detail_page.dart';
import 'widgets/report_card.dart';

/// Today's reports by default; older ones live in collapsible date groups so
/// today stays prominent.
class ReportsPage extends StatefulWidget {
  const ReportsPage({super.key});

  @override
  State<ReportsPage> createState() => _ReportsPageState();
}

class _ReportsPageState extends State<ReportsPage> {
  bool _loading = true;
  Object? _error;
  List<VisitReport> _all = const <VisitReport>[];
  String _query = '';
  String? _companyFilter;
  final Set<DateTime> _expandedDays = <DateTime>{};

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
      final List<VisitReport> rows =
          await AppScope.of(context).repository.reports();
      if (!mounted) return;
      setState(() {
        _all = rows;
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

  /// The day the "today / previous" split is drawn against. Read from the
  /// clock rather than captured in [initState] so a phone left on this tab
  /// overnight rolls over instead of filing new reports under "previous".
  DateTime get _todayDate => Fmt.dayOnly(DateTime.now());

  /// Company is free text now, so the filter matches on the name the seller
  /// typed rather than on an id.
  bool _matches(VisitReport r) {
    if (_companyFilter != null && r.companyName != _companyFilter) return false;
    if (_query.isEmpty) return true;
    final String q = _query.toLowerCase();
    return r.title.toLowerCase().contains(q) ||
        r.body.toLowerCase().contains(q) ||
        r.companyName.toLowerCase().contains(q) ||
        (r.branchName ?? '').toLowerCase().contains(q) ||
        r.sales.any((ProductSaleLine l) =>
            l.productName.toLowerCase().contains(q));
  }

  /// Chips are built from the names that actually appear in the data — with an
  /// open text field there is no master list to enumerate.
  List<String> get _companyNames {
    final Set<String> names =
        _all.map((VisitReport r) => r.companyName).toSet();
    final List<String> sorted = names.toList()
      ..sort((String a, String b) => a.toLowerCase().compareTo(b.toLowerCase()));
    return sorted;
  }

  List<VisitReport> get _today => _all
      .where((VisitReport r) =>
          Fmt.isSameDay(r.submittedAt, _todayDate) && _matches(r))
      .toList(growable: false);

  Map<DateTime, List<VisitReport>> get _previous {
    final Map<DateTime, List<VisitReport>> grouped =
        <DateTime, List<VisitReport>>{};
    for (final VisitReport r in _all) {
      if (Fmt.isSameDay(r.submittedAt, _todayDate)) continue;
      if (!_matches(r)) continue;
      final DateTime key = Fmt.dayOnly(r.submittedAt);
      grouped.putIfAbsent(key, () => <VisitReport>[]).add(r);
    }
    return grouped;
  }

  /// "Report #n" numbering, oldest-first within a company on a given day.
  Map<String, String> _indexLabels(List<VisitReport> reports) {
    final Map<String, List<VisitReport>> byCompany =
        <String, List<VisitReport>>{};
    for (final VisitReport r in reports) {
      byCompany
          .putIfAbsent(r.companyName.toLowerCase(), () => <VisitReport>[])
          .add(r);
    }
    final Map<String, String> labels = <String, String>{};
    byCompany.forEach((_, List<VisitReport> list) {
      if (list.length < 2) return;
      final List<VisitReport> ordered = list.toList()
        ..sort((VisitReport a, VisitReport b) =>
            a.submittedAt.compareTo(b.submittedAt));
      for (int i = 0; i < ordered.length; i++) {
        labels[ordered[i].id] = 'Report #${i + 1}';
      }
    });
    return labels;
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reports'),
        actions: <Widget>[
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          await Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => const CreateReportPage()),
          );
          if (mounted) _load();
        },
        icon: const Icon(Icons.add_rounded),
        label: const Text('New report'),
      ),
      body: SafeArea(
        top: false,
        child: Builder(
          builder: (BuildContext context) {
            if (_loading) {
              return const Padding(
                padding: EdgeInsets.all(Insets.lg),
                child: LoadingCards(count: 4),
              );
            }
            if (_error != null) {
              return ErrorState(onRetry: _load);
            }

            final List<VisitReport> today = _today;
            final Map<DateTime, List<VisitReport>> previous = _previous;
            final List<DateTime> days = previous.keys.toList()
              ..sort((DateTime a, DateTime b) => b.compareTo(a));
            final Map<String, String> labels = _indexLabels(today);

            return RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  Insets.sm,
                  Insets.lg,
                  96,
                ),
                children: <Widget>[
                  TextField(
                    onChanged: (String v) => setState(() => _query = v),
                    decoration: const InputDecoration(
                      hintText: 'Search company, title or text',
                      prefixIcon: Icon(Icons.search_rounded),
                    ),
                  ),
                  const SizedBox(height: Insets.md),
                  _CompanyFilterRow(
                    names: _companyNames,
                    selected: _companyFilter,
                    onSelect: (String? name) =>
                        setState(() => _companyFilter = name),
                  ),
                  const SizedBox(height: Insets.xl),
                  Row(
                    children: <Widget>[
                      Text("Today's reports", style: theme.textTheme.titleLarge),
                      const SizedBox(width: Insets.sm),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primarySoft,
                          borderRadius: BorderRadius.circular(Radii.pill),
                        ),
                        child: Text(
                          '${today.length}',
                          style: const TextStyle(
                            color: AppColors.primaryDark,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    Fmt.longDate(_todayDate),
                    style: theme.textTheme.bodySmall,
                  ),
                  const SizedBox(height: Insets.md),
                  if (today.isEmpty)
                    AppCard(
                      child: EmptyState(
                        icon: Icons.note_add_outlined,
                        title: 'No reports yet today',
                        message:
                            'Submit a report after each company visit. One company '
                            'can have as many reports as you need.',
                        actionLabel: 'Create report',
                        onAction: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const CreateReportPage(),
                            ),
                          );
                          if (mounted) _load();
                        },
                      ),
                    )
                  else
                    ...today.map(
                      (VisitReport r) => Padding(
                        padding: const EdgeInsets.only(bottom: Insets.md),
                        child: ReportCard(
                          report: r,
                          indexLabel: labels[r.id],
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => ReportDetailPage(reportId: r.id),
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: Insets.xl),
                  Text('Previous reports', style: theme.textTheme.titleLarge),
                  const SizedBox(height: Insets.md),
                  if (days.isEmpty)
                    AppCard(
                      child: Text(
                        'No earlier reports match this filter.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    )
                  else
                    ...days.map(
                      (DateTime day) => Padding(
                        padding: const EdgeInsets.only(bottom: Insets.sm),
                        child: _DayGroup(
                          day: day,
                          reports: previous[day]!,
                          expanded: _expandedDays.contains(day),
                          onToggle: () => setState(() {
                            if (!_expandedDays.remove(day)) {
                              _expandedDays.add(day);
                            }
                          }),
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _CompanyFilterRow extends StatelessWidget {
  const _CompanyFilterRow({
    required this.names,
    required this.selected,
    required this.onSelect,
  });

  final List<String> names;
  final String? selected;
  final ValueChanged<String?> onSelect;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.only(right: Insets.sm),
            child: ChoiceChip(
              label: const Text('All companies'),
              selected: selected == null,
              onSelected: (_) => onSelect(null),
            ),
          ),
          ...names.map(
            (String name) => Padding(
              padding: const EdgeInsets.only(right: Insets.sm),
              child: ChoiceChip(
                label: Text(name),
                selected: selected == name,
                onSelected: (_) => onSelect(name),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Collapsible date group — collapsed by default, lazily rendered on expand.
class _DayGroup extends StatelessWidget {
  const _DayGroup({
    required this.day,
    required this.reports,
    required this.expanded,
    required this.onToggle,
  });

  final DateTime day;
  final List<VisitReport> reports;
  final bool expanded;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: <Widget>[
          InkWell(
            onTap: onToggle,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: Insets.lg,
                vertical: Insets.md,
              ),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: theme.brightness == Brightness.dark
                          ? AppColors.surfaceAltDark
                          : AppColors.surfaceAlt,
                      borderRadius: BorderRadius.circular(Radii.md),
                    ),
                    alignment: Alignment.center,
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        Text(
                          '${day.day}',
                          style: theme.textTheme.titleMedium
                              ?.copyWith(fontSize: 14, height: 1),
                        ),
                        Text(
                          Fmt.monthShort(day.month),
                          style: theme.textTheme.labelSmall
                              ?.copyWith(fontSize: 9, height: 1.3),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: Insets.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          Fmt.monthDay(day),
                          style: theme.textTheme.titleMedium,
                        ),
                        Text(
                          '${reports.length} report'
                          '${reports.length == 1 ? '' : 's'} · '
                          '${Fmt.weekdayLong(day)}',
                          style: theme.textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                  AnimatedRotation(
                    turns: expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: const Icon(Icons.expand_more_rounded),
                  ),
                ],
              ),
            ),
          ),
          if (expanded) ...<Widget>[
            Divider(height: 1, color: theme.dividerColor),
            Padding(
              padding: const EdgeInsets.all(Insets.md),
              child: Column(
                children: reports
                    .map(
                      (VisitReport r) => Padding(
                        padding: const EdgeInsets.only(bottom: Insets.sm),
                        child: ReportCard(
                          report: r,
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => ReportDetailPage(reportId: r.id),
                            ),
                          ),
                        ),
                      ),
                    )
                    .toList(growable: false),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
