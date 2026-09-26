import 'package:flutter/material.dart';

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
import '../../reports/widgets/image_tile.dart';

/// Full report with the approve / send-back decision.
class AdminReportDetailPage extends StatefulWidget {
  const AdminReportDetailPage({super.key, required this.reportId});

  final String reportId;

  @override
  State<AdminReportDetailPage> createState() => _AdminReportDetailPageState();
}

class _AdminReportDetailPageState extends State<AdminReportDetailPage> {
  bool _loading = true;
  bool _busy = false;
  Object? _error;
  ReportInboxItem? _item;

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
      final ReportInboxItem item =
          await AppScope.of(context).adminRepository.inboxItem(widget.reportId);
      if (!mounted) return;
      setState(() {
        _item = item;
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

  Future<void> _decide(ReviewDecision decision) async {
    String? note;

    if (decision == ReviewDecision.rejected) {
      note = await _askForNote();
      if (note == null) return;
    }

    setState(() => _busy = true);
    await AppScope.of(context).adminRepository.reviewReport(
          reportId: widget.reportId,
          decision: decision,
          note: note,
        );
    if (!mounted) return;
    setState(() => _busy = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          decision == ReviewDecision.approved
              ? 'Report approved'
              : 'Report sent back to the employee',
        ),
      ),
    );
    await _load();
  }

  Future<String?> _askForNote() async {
    final TextEditingController controller = TextEditingController();
    final String? result = await showDialog<String>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Send back'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Text(
              'Tell the employee what needs to change. They will see this '
              'note against the report.',
            ),
            const SizedBox(height: Insets.md),
            TextField(
              controller: controller,
              maxLines: 3,
              autofocus: true,
              decoration: const InputDecoration(
                hintText: 'e.g. missing photos of the damaged cartons',
              ),
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(
              context,
              controller.text.trim().isEmpty
                  ? 'Needs revision'
                  : controller.text.trim(),
            ),
            style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
            child: const Text('Send back'),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Review report')),
      body: _loading
          ? const Padding(
              padding: EdgeInsets.all(Insets.lg),
              child: LoadingCards(count: 3),
            )
          : _error != null
              ? ErrorState(onRetry: _load)
              : _body(_item!),
      bottomNavigationBar: _loading || _item == null
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(Insets.lg),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: SizedBox(
                        height: Sizes.touchTarget,
                        child: OutlinedButton.icon(
                          onPressed: _busy
                              ? null
                              : () => _decide(ReviewDecision.rejected),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.danger,
                            side: const BorderSide(color: AppColors.danger),
                          ),
                          icon: const Icon(Icons.undo_rounded),
                          label: const Text('Send back'),
                        ),
                      ),
                    ),
                    const SizedBox(width: Insets.md),
                    Expanded(
                      child: SizedBox(
                        height: Sizes.touchTarget,
                        child: FilledButton.icon(
                          onPressed: _busy
                              ? null
                              : () => _decide(ReviewDecision.approved),
                          icon: const Icon(Icons.check_rounded),
                          label: const Text('Approve'),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _body(ReportInboxItem item) {
    final ThemeData theme = Theme.of(context);
    final VisitReport r = item.report;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        Insets.lg,
        Insets.lg,
        Insets.lg,
        Insets.xxxl,
      ),
      children: <Widget>[
        AppCard(
          child: Row(
            children: <Widget>[
              ProfileAvatar(
                initials: item.employee.initials,
                imageUrl: item.employee.avatarUrl,
                size: Sizes.avatarMd,
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(item.employee.name, style: theme.textTheme.titleMedium),
                    Text(
                      '${item.employee.employeeCode} · '
                      '${item.employee.designation}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: Insets.md),

        if (item.review.decision.isDecided)
          Padding(
            padding: const EdgeInsets.only(bottom: Insets.md),
            child: AlertBanner(
              icon: item.review.decision == ReviewDecision.approved
                  ? Icons.verified_outlined
                  : Icons.undo_rounded,
              title: item.review.decision.label,
              message: <String>[
                if (item.review.reviewedBy != null)
                  'by ${item.review.reviewedBy}',
                if (item.review.reviewedAt != null)
                  Fmt.relative(item.review.reviewedAt!),
                if (item.review.note != null) '— ${item.review.note}',
              ].join(' '),
              tone: item.review.decision == ReviewDecision.approved
                  ? AppColors.success
                  : AppColors.danger,
            ),
          ),

        AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Wrap(
                spacing: Insets.sm,
                runSpacing: Insets.xs,
                children: <Widget>[
                  StatusBadge.review(item.decision, dense: true),
                  StatusBadge.report(r.status, dense: true),
                ],
              ),
              const SizedBox(height: Insets.md),
              Text(r.title, style: theme.textTheme.titleLarge),
              const SizedBox(height: Insets.xs),
              Text(
                '${item.companyName}'
                '${item.branchName == null ? '' : ' · ${item.branchName}'}',
                style: theme.textTheme.bodySmall,
              ),
              const Divider(height: Insets.xxl),
              Text(r.body, style: theme.textTheme.bodyLarge),
            ],
          ),
        ),

        if (r.imageCount > 0) ...<Widget>[
          const SectionHeader(
            title: 'Attachments',
            padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
          ),
          SizedBox(
            height: 88,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: r.imageCount,
              separatorBuilder: (_, __) => const SizedBox(width: Insets.sm),
              itemBuilder: (BuildContext context, int i) =>
                  ReportImageTile(index: i),
            ),
          ),
        ],

        if (r.hasSale) ...<Widget>[
          SectionHeader(
            title: 'Products sold',
            subtitle: '${r.unitsSold} unit${r.unitsSold == 1 ? '' : 's'} · '
                '${r.sales.length} model${r.sales.length == 1 ? '' : 's'}',
            padding: const EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
          ),
          AppCard(
            child: Column(
              children: <Widget>[
                ...r.sales.map(
                  (ProductSaleLine l) => KeyValueRow(
                    label: '${l.productName} · ${l.colorName}',
                    value: '× ${l.units}',
                    dense: true,
                  ),
                ),
                if (r.paymentReceived != null) ...<Widget>[
                  const Divider(height: Insets.lg),
                  KeyValueRow(
                    label: 'Payment received',
                    value: r.paymentReceived!,
                    dense: true,
                    valueColor: AppColors.success,
                  ),
                ],
              ],
            ),
          ),
        ],

        const SectionHeader(
          title: 'Details',
          padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
        ),
        AppCard(
          child: Column(
            children: <Widget>[
              KeyValueRow(
                label: 'Submitted',
                value: '${Fmt.mediumDate(r.submittedAt)} · '
                    '${Fmt.time(r.submittedAt)}',
                dense: true,
              ),
              KeyValueRow(
                label: 'Location',
                value: Fmt.latLng(r.latitude, r.longitude),
                dense: true,
              ),
              if (r.dealValue != null)
                KeyValueRow(
                  label: 'Deal value',
                  value: r.dealValue!,
                  dense: true,
                  valueColor: AppColors.success,
                ),
              if (r.followUpOn != null)
                KeyValueRow(
                  label: 'Follow-up',
                  value: Fmt.mediumDate(r.followUpOn!),
                  dense: true,
                ),
              KeyValueRow(
                label: 'Photos',
                value: '${r.imageCount}',
                dense: true,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
