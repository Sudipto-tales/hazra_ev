import 'package:flutter/material.dart';

import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/states.dart';
import '../../../widgets/status_badge.dart';
import 'admin_report_detail_page.dart';

/// Review queue across the whole team.
class AdminReportsPage extends StatefulWidget {
  const AdminReportsPage({super.key});

  @override
  State<AdminReportsPage> createState() => _AdminReportsPageState();
}

class _AdminReportsPageState extends State<AdminReportsPage> {
  final TextEditingController _search = TextEditingController();
  bool _loading = true;
  Object? _error;
  List<ReportInboxItem> _items = <ReportInboxItem>[];
  ReviewDecision? _decision = ReviewDecision.pending;

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
      final List<ReportInboxItem> rows =
          await AppScope.of(context).adminRepository.inbox(
                decision: _decision,
                query: _search.text,
              );
      if (!mounted) return;
      setState(() {
        _items = rows;
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
        title: const Text('Report inbox'),
        actions: <Widget>[
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              0,
              Insets.lg,
              Insets.sm,
            ),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _load(),
              decoration: const InputDecoration(
                hintText: 'Search title, company or employee…',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
          ),
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: Insets.lg),
              children: <Widget>[
                _chip('All', null),
                _chip('Pending', ReviewDecision.pending),
                _chip('Approved', ReviewDecision.approved),
                _chip('Sent back', ReviewDecision.rejected),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  Insets.md,
                  Insets.lg,
                  Insets.xxxl,
                ),
                children: <Widget>[
                  if (_loading)
                    const LoadingCards(count: 4)
                  else if (_error != null)
                    ErrorState(onRetry: _load)
                  else if (_items.isEmpty)
                    const EmptyState(
                      icon: Icons.inbox_outlined,
                      title: 'Inbox clear',
                      message: 'No reports match this filter.',
                    )
                  else
                    ..._items.map(_tile),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, ReviewDecision? value) {
    return Padding(
      padding: const EdgeInsets.only(right: Insets.sm),
      child: ChoiceChip(
        label: Text(label),
        selected: _decision == value,
        onSelected: (_) {
          setState(() => _decision = value);
          _load();
        },
      ),
    );
  }

  Widget _tile(ReportInboxItem item) {
    final ThemeData theme = Theme.of(context);
    final VisitReport r = item.report;

    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: AppCard(
        onTap: () async {
          await Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => AdminReportDetailPage(reportId: r.id),
            ),
          );
          if (mounted) _load();
        },
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                ProfileAvatar(
                  initials: item.employee.initials,
                  imageUrl: item.employee.avatarUrl,
                  size: Sizes.avatarSm,
                ),
                const SizedBox(width: Insets.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        item.employee.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontSize: 14,
                        ),
                      ),
                      Text(
                        '${item.companyName}'
                        '${item.branchName == null ? '' : ' · ${item.branchName}'}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                StatusBadge.review(item.decision, dense: true),
              ],
            ),
            const SizedBox(height: Insets.md),
            Text(
              r.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: Insets.xs),
            Text(
              r.preview,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: Insets.md),
            Row(
              children: <Widget>[
                Icon(
                  Icons.schedule_rounded,
                  size: 14,
                  color: theme.textTheme.bodySmall?.color,
                ),
                const SizedBox(width: Insets.xs),
                Text(
                  '${Fmt.mediumDate(r.submittedAt)} · ${Fmt.time(r.submittedAt)}',
                  style: theme.textTheme.labelSmall,
                ),
                const Spacer(),
                if (r.imageCount > 0) ...<Widget>[
                  Icon(
                    Icons.image_outlined,
                    size: 14,
                    color: theme.textTheme.bodySmall?.color,
                  ),
                  const SizedBox(width: Insets.xs),
                  Text('${r.imageCount}', style: theme.textTheme.labelSmall),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
