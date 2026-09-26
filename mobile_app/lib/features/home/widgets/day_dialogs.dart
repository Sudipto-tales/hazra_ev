import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart';

/// Blocking dialog when location is not usable. Used by both Start Day and
/// End Day — the [note] is what differs between them.
Future<void> showLocationBlockedDialog(
  BuildContext context,
  LocationHealth health, {
  required Future<void> Function() onEnable,
  String note = 'Your day cannot start without a valid GPS fix.',
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
            _Note(text: note, tone: AppColors.danger),
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

/// The day cannot be closed without a connection.
///
/// The lock that follows a close lives on the server. Closing offline would
/// make this device the only thing that believed the day was over — so the form
/// is never shown offline, and the work already done is not at risk either way.
Future<void> showOfflineDialog(
  BuildContext context, {
  required bool forEndDay,
}) {
  return showDialog<void>(
    context: context,
    builder: (BuildContext context) {
      return AlertDialog(
        icon: const Icon(
          Icons.wifi_off_rounded,
          color: AppColors.warning,
          size: 34,
        ),
        title: const Text('No internet connection', textAlign: TextAlign.center),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              forEndDay
                  ? 'Your day is closed on the server, not on this phone, so you '
                      'need a connection to end it.'
                  : 'Starting a day needs a connection so your device and the '
                      'server agree on which day is open.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: Insets.lg),
            _Note(
              text: forEndDay
                  ? 'Nothing is lost — your tracking keeps running and you can '
                      'end the day as soon as you are back online.'
                  : 'Try again once you are back online.',
              tone: AppColors.info,
            ),
          ],
        ),
        actions: <Widget>[
          FilledButton(
            onPressed: () => Navigator.pop(context),
            style: FilledButton.styleFrom(
              minimumSize: const Size(120, Sizes.touchTarget),
            ),
            child: const Text('Got it'),
          ),
        ],
      );
    },
  );
}

/// The day is already closed and only an admin can reopen it.
Future<void> showDayLockedDialog(BuildContext context, DayState state) {
  return showDialog<void>(
    context: context,
    builder: (BuildContext context) {
      return AlertDialog(
        icon: const Icon(
          Icons.lock_outline_rounded,
          color: AppColors.info,
          size: 34,
        ),
        title: const Text('Today is closed', textAlign: TextAlign.center),
        content: Text(
          state.lockMessage,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        actions: <Widget>[
          FilledButton(
            onPressed: () => Navigator.pop(context),
            style: FilledButton.styleFrom(
              minimumSize: const Size(120, Sizes.touchTarget),
            ),
            child: const Text('Got it'),
          ),
        ],
      );
    },
  );
}

class _Note extends StatelessWidget {
  const _Note({required this.text, required this.tone});

  final String text;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Insets.md),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(Radii.md),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(Icons.info_outline_rounded, size: 17, color: tone),
          const SizedBox(width: Insets.sm),
          Expanded(
            child: Text(
              text,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: tone),
            ),
          ),
        ],
      ),
    );
  }
}
