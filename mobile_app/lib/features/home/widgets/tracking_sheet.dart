import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../state/tracking_controller.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../../../widgets/status_badge.dart';

/// "Why is my status what it is?" — one sheet showing permission, GPS quality
/// and the offline queue. Reachable from the hero card and from Settings.
Future<void> showTrackingSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (BuildContext context) => const _TrackingSheet(),
  );
}

class _TrackingSheet extends StatelessWidget {
  const _TrackingSheet();

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);
    final ThemeData theme = Theme.of(context);

    return ListenableBuilder(
      listenable: scope.tracking,
      builder: (BuildContext context, _) {
        final TrackingController c = scope.tracking;
        final LocationHealth health = c.locationHealth;
        final SyncSnapshot? sync = c.sync;
        final LocationLog? fix = c.lastFix;

        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              Insets.xl,
              0,
              Insets.xl,
              Insets.xl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        'Tracking status',
                        style: theme.textTheme.headlineSmall,
                      ),
                    ),
                    StatusBadge.work(c.status),
                  ],
                ),
                const SizedBox(height: Insets.lg),
                AlertBanner(
                  icon: health == LocationHealth.ok
                      ? Icons.check_circle_outline_rounded
                      : Icons.warning_amber_rounded,
                  tone: health == LocationHealth.ok
                      ? AppColors.success
                      : AppColors.warning,
                  title: health.title,
                  message: health.message,
                  actionLabel: health.blocksStart ? 'Fix now' : null,
                  onAction: health.blocksStart
                      ? () => ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Opening system location settings…'),
                            ),
                          )
                      : null,
                ),
                const SizedBox(height: Insets.lg),
                Text('Last fix', style: theme.textTheme.titleMedium),
                const SizedBox(height: Insets.sm),
                KeyValueRow(
                  label: 'Coordinates',
                  value: fix == null
                      ? '—'
                      : Fmt.latLng(fix.latitude, fix.longitude),
                  icon: Icons.place_outlined,
                ),
                KeyValueRow(
                  label: 'Accuracy',
                  value: fix == null ? '—' : '± ${Fmt.metres(fix.accuracy)}',
                  icon: Icons.gps_fixed_rounded,
                  valueColor: fix != null && fix.accuracy > 50
                      ? AppColors.danger
                      : null,
                ),
                KeyValueRow(
                  label: 'Speed',
                  value: fix == null
                      ? '—'
                      : '${fix.speedKmh.toStringAsFixed(1)} km/h',
                  icon: Icons.speed_rounded,
                ),
                KeyValueRow(
                  label: 'Recorded',
                  value: fix == null
                      ? '—'
                      : '${Fmt.time(fix.recordedAt)} · ${Fmt.relative(fix.recordedAt, now: c.now)}',
                  icon: Icons.schedule_rounded,
                ),
                const Divider(height: Insets.xxl),
                Text('Sync queue', style: theme.textTheme.titleMedium),
                const SizedBox(height: Insets.sm),
                KeyValueRow(
                  label: 'Connection',
                  value: (sync?.isOnline ?? true) ? 'Online' : 'Offline',
                  icon: Icons.wifi_rounded,
                  valueColor:
                      (sync?.isOnline ?? true) ? AppColors.success : AppColors.warning,
                ),
                KeyValueRow(
                  label: 'Queued locations',
                  value: '${sync?.queued ?? 0}',
                  icon: Icons.upload_file_rounded,
                ),
                KeyValueRow(
                  label: 'Failed uploads',
                  value: '${sync?.failed ?? 0}',
                  icon: Icons.error_outline_rounded,
                  valueColor:
                      (sync?.failed ?? 0) > 0 ? AppColors.danger : null,
                ),
                KeyValueRow(
                  label: 'Last synced',
                  value: sync?.lastSyncedAt == null
                      ? 'Never'
                      : Fmt.relative(sync!.lastSyncedAt!, now: c.now),
                  icon: Icons.cloud_done_outlined,
                ),
                const SizedBox(height: Insets.md),
                Text(
                  'Queued points stay on this device and upload automatically. '
                  'Nothing is lost while you are offline.',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: Insets.lg),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () async {
                          await scope.locationService.flushQueue();
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Queue flushed')),
                          );
                        },
                        icon: const Icon(Icons.sync_rounded, size: 18),
                        label: const Text('Sync now'),
                      ),
                    ),
                    const SizedBox(width: Insets.md),
                    Expanded(
                      child: FilledButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Close'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
