import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';
import '../../widgets/status_badge.dart';
import 'widgets/image_tile.dart';
import 'widgets/product_artwork.dart';
import 'widgets/product_widgets.dart';

class ReportDetailPage extends StatefulWidget {
  const ReportDetailPage({super.key, required this.reportId});

  final String reportId;

  @override
  State<ReportDetailPage> createState() => _ReportDetailPageState();
}

class _ReportDetailPageState extends State<ReportDetailPage> {
  VisitReport? _report;
  Object? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final VisitReport r =
          await AppScope.of(context).repository.reportById(widget.reportId);
      if (!mounted) return;
      setState(() => _report = r);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Report')),
      body: Builder(
        builder: (BuildContext context) {
          if (_error != null) return ErrorState(onRetry: _load);
          if (_report == null) {
            return const Padding(
              padding: EdgeInsets.all(Insets.lg),
              child: LoadingCards(count: 2),
            );
          }

          final VisitReport r = _report!;

          return ListView(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              Insets.sm,
              Insets.lg,
              Insets.xxxl,
            ),
            children: <Widget>[
              AppCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: AppColors.primarySoft,
                            borderRadius: BorderRadius.circular(Radii.md),
                          ),
                          child: const Icon(
                            Icons.business_rounded,
                            color: AppColors.primary,
                            size: 21,
                          ),
                        ),
                        const SizedBox(width: Insets.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(r.companyName,
                                  style: theme.textTheme.titleMedium),
                              if (r.branchName != null)
                                Text(r.branchName!,
                                    style: theme.textTheme.bodySmall),
                            ],
                          ),
                        ),
                        StatusBadge.report(r.status, dense: true),
                      ],
                    ),
                    const SizedBox(height: Insets.lg),
                    Text(r.title, style: theme.textTheme.headlineSmall),
                    const SizedBox(height: Insets.sm),
                    Row(
                      children: <Widget>[
                        Icon(Icons.schedule_rounded,
                            size: 14, color: theme.textTheme.bodySmall?.color),
                        const SizedBox(width: 5),
                        Text(
                          '${Fmt.time(r.submittedAt)} · '
                          '${Fmt.mediumDate(r.submittedAt)}',
                          style: theme.textTheme.bodySmall,
                        ),
                      ],
                    ),
                    const Divider(height: Insets.xxl),
                    Text(r.body, style: theme.textTheme.bodyLarge),
                  ],
                ),
              ),
              if (r.imageCount > 0) ...<Widget>[
                SectionHeader(
                  title: 'Attachments',
                  subtitle: '${r.imageCount} photo'
                      '${r.imageCount == 1 ? '' : 's'} uploaded with this '
                      'report',
                  padding: const EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
                ),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Wrap(
                        spacing: Insets.sm,
                        runSpacing: Insets.sm,
                        children: List<Widget>.generate(
                          r.imageCount,
                          (int i) => ReportImageTile(index: i, size: 92),
                        ),
                      ),
                      const SizedBox(height: Insets.md),
                      // The count is genuinely all the contract gives us:
                      // `GET /reports/{id}` returns `imageCount` and no urls,
                      // and there is no `include=images`. Showing empty slots
                      // plus this line is the most the app can honestly say —
                      // the alternative, tinted rectangles that read as
                      // photos, was worse than showing nothing.
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          const Icon(
                            Icons.info_outline_rounded,
                            size: 15,
                            color: AppColors.textSecondary,
                          ),
                          const SizedBox(width: Insets.xs),
                          Expanded(
                            child: Text(
                              'Photos are stored with the report on the '
                              'server. They cannot be previewed in the app '
                              'yet.',
                              style: theme.textTheme.bodySmall,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
              if (r.hasSale) ...<Widget>[
                SectionHeader(
                  title: 'Products sold',
                  subtitle: '${r.unitsSold} unit'
                      '${r.unitsSold == 1 ? '' : 's'} across '
                      '${r.sales.length} model${r.sales.length == 1 ? '' : 's'}',
                  padding: const EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
                ),
                AppCard(
                  child: Column(
                    children: <Widget>[
                      ...r.sales.map(
                        (ProductSaleLine l) => _SaleRow(line: l),
                      ),
                      if (r.paymentReceived != null) ...<Widget>[
                        const Divider(height: Insets.xl),
                        KeyValueRow(
                          label: 'Payment received',
                          value: r.paymentReceived!,
                          icon: Icons.payments_rounded,
                          valueColor: AppColors.success,
                        ),
                      ],
                    ],
                  ),
                ),
              ],
              const SectionHeader(
                title: 'Context',
                padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
              ),
              AppCard(
                child: Column(
                  children: <Widget>[
                    KeyValueRow(
                      label: 'Visit',
                      value: r.visitId ?? 'Not linked to a visit',
                      icon: Icons.place_outlined,
                    ),
                    KeyValueRow(
                      label: 'Session',
                      value: r.sessionId,
                      icon: Icons.layers_outlined,
                    ),
                    KeyValueRow(
                      label: 'Submitted from',
                      value: Fmt.latLng(r.latitude, r.longitude),
                      icon: Icons.my_location_rounded,
                    ),
                    KeyValueRow(
                      label: 'Images',
                      value: '${r.imageCount}',
                      icon: Icons.image_outlined,
                    ),
                    if (r.dealValue != null)
                      KeyValueRow(
                        label: 'Deal value',
                        value: r.dealValue!,
                        icon: Icons.payments_outlined,
                        valueColor: AppColors.success,
                      ),
                    if (r.followUpOn != null)
                      KeyValueRow(
                        label: 'Follow-up',
                        value: Fmt.mediumDate(r.followUpOn!),
                        icon: Icons.event_repeat_outlined,
                      ),
                  ],
                ),
              ),
              if (r.status == ReportStatus.queued ||
                  r.status == ReportStatus.failed) ...<Widget>[
                const SizedBox(height: Insets.lg),
                AlertBanner(
                  icon: Icons.cloud_upload_outlined,
                  tone: r.status == ReportStatus.failed
                      ? AppColors.danger
                      : AppColors.warning,
                  title: r.status == ReportStatus.failed
                      ? 'Upload failed'
                      : 'Waiting to upload',
                  message: r.status == ReportStatus.failed
                      ? 'This report is saved on your device. Retry when you have a connection.'
                      : 'Saved on your device. It will upload automatically once you are online.',
                  actionLabel: 'Retry now',
                  onAction: () => ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Retrying upload…')),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

/// One logged model: colour swatch, name, units. The thumbnail shows the colour
/// that was actually sold, which is the whole point of a per-colour image set.
class _SaleRow extends StatelessWidget {
  const _SaleRow({required this.line});

  final ProductSaleLine line;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: Row(
        children: <Widget>[
          SizedBox(
            width: 76,
            child: ProductArtwork(
              category: line.category,
              argb: line.colorArgb,
              height: 52,
              padding: Insets.xs,
              radius: Radii.sm,
            ),
          ),
          const SizedBox(width: Insets.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(line.productName, style: theme.textTheme.titleMedium),
                const SizedBox(height: 2),
                Row(
                  children: <Widget>[
                    Icon(categoryIcon(line.category),
                        size: 13, color: theme.textTheme.bodySmall?.color),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        '${line.category.label} · ${line.colorName}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: Insets.sm),
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: Insets.sm,
              vertical: 3,
            ),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(Radii.pill),
            ),
            child: Text(
              '× ${line.units}',
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: AppColors.primaryDark,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
