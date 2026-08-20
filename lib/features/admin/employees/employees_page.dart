import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/select_field.dart';
import '../../../widgets/states.dart';
import '../manage/employee_form_page.dart';
import '../widgets/team_status_tile.dart';
import 'employee_detail_page.dart';

/// Roster with search + live-status filter.
class EmployeesPage extends StatefulWidget {
  const EmployeesPage({super.key});

  @override
  State<EmployeesPage> createState() => _EmployeesPageState();
}

class _EmployeesPageState extends State<EmployeesPage> {
  final TextEditingController _search = TextEditingController();
  bool _loading = true;
  Object? _error;
  List<TeamMember> _members = <TeamMember>[];
  WorkStatus? _status;
  TrackingConfig _config = TrackingConfig.defaults;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final AppScope scope = AppScope.of(context);
      final List<TeamMember> rows = await scope.adminRepository.team(
        query: _search.text,
        status: _status,
      );
      final TrackingConfig config = await scope.adminRepository.config();
      if (!mounted) return;
      setState(() {
        _members = rows;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Team'),
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
          final bool? saved = await Navigator.of(context).push<bool>(
            MaterialPageRoute<bool>(builder: (_) => const EmployeeFormPage()),
          );
          if (saved == true && mounted) _load();
        },
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: const Text('Add employee'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              0,
              Insets.lg,
              Insets.md,
            ),
            child: Column(
              children: <Widget>[
                TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'Search name, code, region…',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: _search.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.close_rounded),
                            onPressed: () {
                              _search.clear();
                              _load();
                            },
                          ),
                  ),
                ),
                const SizedBox(height: Insets.md),
                SelectField<WorkStatus>(
                  label: 'Status',
                  hint: 'All statuses',
                  sheetTitle: 'Filter by status',
                  icon: Icons.filter_list_rounded,
                  clearable: true,
                  value: _status,
                  options: WorkStatus.values
                      .map(
                        (WorkStatus s) => SelectOption<WorkStatus>(
                          value: s,
                          label: s.label,
                        ),
                      )
                      .toList(),
                  onChanged: (WorkStatus? v) {
                    setState(() => _status = v);
                    _load();
                  },
                ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  0,
                  Insets.lg,
                  Insets.xxxl * 2,
                ),
                children: <Widget>[
                  if (_loading)
                    const LoadingCards(count: 4)
                  else if (_error != null)
                    ErrorState(onRetry: _load)
                  else if (_members.isEmpty)
                    EmptyState(
                      icon: Icons.person_search_outlined,
                      title: 'No one matches',
                      message: 'Try a different search term or clear the '
                          'status filter.',
                      actionLabel: 'Clear filters',
                      onAction: () {
                        _search.clear();
                        setState(() => _status = null);
                        _load();
                      },
                    )
                  else
                    ..._members.map(
                      (TeamMember m) => TeamStatusTile(
                        member: m,
                        longStopThresholdMinutes:
                            _config.longStopThresholdMinutes,
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => EmployeeDetailPage(
                                employeeId: m.employee.id,
                              ),
                            ),
                          );
                          if (mounted) _load();
                        },
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
