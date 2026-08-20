import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/tracking_controller.dart';
import '../../../widgets/stat_tile.dart';

/// Blocking dialog when the day cannot start: location off, permission denied.
/// The session is NOT created until a valid fix exists.
Future<void> showLocationBlockedDialog(
  BuildContext context,
  LocationHealth health, {
  required Future<void> Function() onEnable,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (BuildContext context) {
      return AlertDialog(
        icon: const Icon(
          Icons.location_off_rounded,
          color: AppColors.danger,
          size: 34,
        ),
        title: Text(health.title, textAlign: TextAlign.center),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              health.message,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: Insets.lg),
            Container(
              padding: const EdgeInsets.all(Insets.md),
              decoration: BoxDecoration(
                color: AppColors.dangerSoft,
                borderRadius: BorderRadius.circular(Radii.md),
              ),
              child: Row(
                children: <Widget>[
                  const Icon(Icons.info_outline_rounded,
                      size: 17, color: AppColors.danger),
                  const SizedBox(width: Insets.sm),
                  Expanded(
                    child: Text(
                      'Your day cannot start without a valid GPS fix.',
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: AppColors.danger),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actionsAlignment: MainAxisAlignment.spaceBetween,
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Not now'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(context);
              await onEnable();
            },
            style: FilledButton.styleFrom(
              minimumSize: const Size(150, Sizes.touchTarget),
            ),
            child: const Text('Enable location'),
          ),
        ],
      );
    },
  );
}

/// End Day confirmation — shows what is about to be frozen.
Future<bool> confirmEndDay(BuildContext context, TrackingController c) async {
  final bool? ok = await showDialog<bool>(
    context: context,
    builder: (BuildContext context) {
      return AlertDialog(
        title: const Text('End your day?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Your final location and time will be saved, the open session '
              'closed and tracking stopped.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: Insets.md),
            const Divider(height: Insets.lg),
            KeyValueRow(
              label: 'Joining time',
              value: Fmt.time(c.joiningTime),
              dense: true,
            ),
            KeyValueRow(
              label: 'Worked today',
              value: Fmt.duration(c.workedToday),
              dense: true,
            ),
            KeyValueRow(
              label: 'Sessions',
              value: '${c.sessions.length}',
              dense: true,
            ),
            KeyValueRow(
              label: 'Distance',
              value: Fmt.km(c.summary.distanceKm),
              dense: true,
            ),
            KeyValueRow(
              label: 'Companies visited',
              value: '${c.summary.companiesVisited}',
              dense: true,
            ),
            KeyValueRow(
              label: 'Reports submitted',
              value: '${c.summary.reportsSubmitted}',
              dense: true,
            ),
          ],
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep working'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.danger,
              minimumSize: const Size(130, Sizes.touchTarget),
            ),
            child: const Text('End Day'),
          ),
        ],
      );
    },
  );
  return ok ?? false;
}
