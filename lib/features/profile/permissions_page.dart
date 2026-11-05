import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';

/// What the app has been allowed to do, and the one button that fixes each gap.
///
/// Why this exists as a page: location can be revoked or switched off from
/// outside the app at any moment, and until now the only place that said so
/// was a red session card mid-session with no action on it. The dialogs that
/// *did* offer an action only appeared on a Start Day / End Day tap.
///
/// Re-polled on every open and on app resume, because returning from the
/// system settings app is exactly the moment the answer changes and there is
/// no event for it.
class PermissionsPage extends StatefulWidget {
  const PermissionsPage({super.key});

  @override
  State<PermissionsPage> createState() => _PermissionsPageState();
}

class _PermissionsPageState extends State<PermissionsPage>
    with WidgetsBindingObserver {
  LocationHealth? _health;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _poll());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // The employee has just come back from Android settings, where they may
    // have granted exactly what we asked for. Nothing pushes an event for
    // that, so resume is the signal.
    if (state == AppLifecycleState.resumed) _poll();
  }

  Future<void> _poll() async {
    final AppScope scope = AppScope.of(context);
    final LocationHealth next = await scope.locationService.health();
    if (!mounted) return;
    setState(() => _health = next);
    // Keep the controller's reading in step, so Home's banner clears at the
    // same moment this page does rather than on its next poll.
    await scope.tracking.refreshLocationHealth();
  }

  Future<void> _act(LocationHealth health) async {
    setState(() => _busy = true);
    try {
      // `requestPermission` owns the branching: it re-requests in-app where
      // that can still work, and opens the location settings or the app's own
      // settings page where it cannot.
      await AppScope.of(context).locationService.requestPermission(
            background: health == LocationHealth.backgroundDenied,
          );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
    await _poll();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final LocationHealth? health = _health;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Permissions'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Re-check',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _busy ? null : _poll,
          ),
        ],
      ),
      body: health == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                Insets.lg,
                Insets.lg,
                Insets.xxxl,
              ),
              children: <Widget>[
                _HealthCard(
                  health: health,
                  busy: _busy,
                  onAct: () => _act(health),
                ),
                const SizedBox(height: Insets.lg),
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Why this is needed',
                          style: theme.textTheme.titleMedium),
                      const SizedBox(height: Insets.sm),
                      Text(
                        'Your route is recorded only while a session is open. '
                        'Location access set to "Allow all the time" is what '
                        'keeps that recording going when the screen is off — '
                        'without it, Android stops the updates and your day '
                        'shows gaps you did not take.',
                        style: theme.textTheme.bodyMedium,
                      ),
                      const SizedBox(height: Insets.md),
                      Text(
                        'No location is recorded before you tap Start Day, or '
                        'after you end it.',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}

class _HealthCard extends StatelessWidget {
  const _HealthCard({
    required this.health,
    required this.busy,
    required this.onAct,
  });

  final LocationHealth health;
  final bool busy;
  final VoidCallback onAct;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool ok = health == LocationHealth.ok;
    final Color tone = ok
        ? AppColors.success
        : health.blocksStart
            ? AppColors.danger
            : AppColors.warning;
    final String? action = health.actionLabel;

    return AppCard(
      color: tone.withValues(alpha: 0.10),
      borderColor: tone.withValues(alpha: 0.35),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(
                ok
                    ? Icons.check_circle_rounded
                    : Icons.location_off_rounded,
                color: tone,
                size: 22,
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                child: Text(
                  health.title,
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),
          Text(health.message, style: theme.textTheme.bodyMedium),
          if (action != null) ...<Widget>[
            const SizedBox(height: Insets.lg),
            SizedBox(
              width: double.infinity,
              height: Sizes.touchTarget,
              child: FilledButton.icon(
                onPressed: busy ? null : onAct,
                style: FilledButton.styleFrom(backgroundColor: tone),
                icon: busy
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : Icon(
                        health.needsSystemSettings
                            ? Icons.open_in_new_rounded
                            : Icons.my_location_rounded,
                        size: 18,
                      ),
                label: Text(action),
              ),
            ),
            if (health.needsSystemSettings) ...<Widget>[
              const SizedBox(height: Insets.sm),
              Text(
                'This opens Android settings. Come back when you are done and '
                'this page will re-check on its own.',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ],
        ],
      ),
    );
  }
}
