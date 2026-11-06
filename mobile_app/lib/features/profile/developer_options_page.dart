import 'package:flutter/foundation.dart' show kDebugMode;
import 'package:flutter/material.dart';

import '../../core/config/tracking_config.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/models.dart';
import '../../services/location_service.dart';
import '../../state/app_scope.dart';
import '../../state/settings_controller.dart';
import '../../widgets/app_card.dart';
import '../../widgets/settings_tile.dart';
import '../../widgets/stat_tile.dart';
import '../home/widgets/tracking_sheet.dart';

/// Everything diagnostic, behind one door.
///
/// Settings used to carry the tracking thresholds, the three device overrides
/// and a release-visible "simulate location state" in among Account and
/// Notifications, which made the main screen read like a debug console. The
/// values are still here — an employee reporting "my distance looks wrong"
/// needs to be able to read them out — but they are one deliberate tap away
/// and grouped by what they govern.
///
/// The thresholds are **read-only** here by design. They are organisation
/// config owned by the admin (`PUT /config`, admin-only), applied server-side
/// at read time; a per-device copy that disagreed with the server would be
/// worse than no copy at all.
class DeveloperOptionsPage extends StatelessWidget {
  const DeveloperOptionsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);
    final SettingsController s = scope.settings;
    // The live config the tracking stack is actually running on — pulled from
    // `GET /config` at login/resume/session-start — not `TrackingConfig.defaults`.
    // Showing the defaults here was the old bug in miniature: it looked like an
    // answer and was only a guess.
    final TrackingConfig c = scope.tracking.config;

    return Scaffold(
      appBar: AppBar(title: const Text('Developer options')),
      body: ListenableBuilder(
        listenable: s,
        builder: (BuildContext context, _) => ListView(
          padding: const EdgeInsets.fromLTRB(
            Insets.lg,
            Insets.lg,
            Insets.lg,
            Insets.xxxl,
          ),
          children: <Widget>[
            AppCard(
              color: AppColors.warning.withValues(alpha: 0.10),
              borderColor: AppColors.warning.withValues(alpha: 0.35),
              child: Row(
                children: <Widget>[
                  const Icon(Icons.science_outlined,
                      size: 19, color: AppColors.warning),
                  const SizedBox(width: Insets.md),
                  Expanded(
                    child: Text(
                      'Diagnostics for support calls. Tracking rules are set by '
                      'your admin and shown here read-only.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.xl),

            SettingsGroup(
              title: 'Live diagnostics',
              tiles: <Widget>[
                SettingsTile(
                  icon: Icons.my_location_rounded,
                  title: 'Location status',
                  subtitle: 'Permission, accuracy and the upload queue',
                  tone: AppColors.success,
                  onTap: () => showTrackingSheet(context),
                ),
              ],
            ),
            const SizedBox(height: Insets.xl),

            // --- the thresholds, grouped by what each one governs -----------
            const SectionHeader(
              title: 'Capture',
              subtitle: 'How often a position is taken, and what counts',
              padding: EdgeInsets.fromLTRB(0, 0, 0, Insets.md),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  KeyValueRow(
                    label: 'Fix interval',
                    value: '${c.locationIntervalSeconds} s',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Distance filter',
                    value: '${c.distanceFilterMetres} m',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Accuracy floor',
                    value: '${c.minAccuracyMetres.toInt()} m',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Invalid jump above',
                    value: '${c.maxJumpKmh.toInt()} km/h',
                    dense: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),

            const SectionHeader(
              title: 'Movement',
              subtitle: 'The dead band that decides moving versus stationary',
              padding: EdgeInsets.fromLTRB(0, 0, 0, Insets.md),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  KeyValueRow(
                    label: 'Stationary below',
                    value: '${c.movementSpeedThresholdKmh} km/h',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Moving at or above',
                    value: '${c.movementExitSpeedKmh} km/h',
                    dense: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),

            const SectionHeader(
              title: 'Stops',
              padding: EdgeInsets.fromLTRB(0, 0, 0, Insets.md),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  KeyValueRow(
                    label: 'Stop radius',
                    value: '${c.stopRadiusMetres.toInt()} m',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Counts as a stop after',
                    value: '${c.stopThresholdMinutes} min',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Flagged as a long stop after',
                    value: '${c.longStopThresholdMinutes} min',
                    dense: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),

            const SectionHeader(
              title: 'Upload',
              padding: EdgeInsets.fromLTRB(0, 0, 0, Insets.md),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  KeyValueRow(
                    label: 'Upload cadence',
                    value: '${c.syncIntervalSeconds} s',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'While an admin is watching',
                    value: '${c.liveSyncIntervalSeconds} s',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Batch size',
                    value: '${c.syncBatchSize} fixes',
                    dense: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),

            const SectionHeader(
              title: 'Health',
              padding: EdgeInsets.fromLTRB(0, 0, 0, Insets.md),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  KeyValueRow(
                    label: 'Offline after',
                    value: '${c.offlineThresholdMinutes} min',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Location unavailable after',
                    value: '${c.locationUnavailableThresholdMinutes} min',
                    dense: true,
                  ),
                  KeyValueRow(
                    label: 'Session auto-closes after',
                    value: '${c.autoCloseAfterMinutes} min',
                    dense: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.xl),

            // --- the three knobs that really are this device's -------------
            SettingsGroup(
              title: 'This device',
              tiles: <Widget>[
                SettingsSwitchTile(
                  icon: Icons.gps_fixed_rounded,
                  title: 'High accuracy mode',
                  subtitle:
                      'Reject fixes worse than ±${c.minAccuracyMetres.toInt()} m',
                  value: s.highAccuracyMode,
                  onChanged: s.setHighAccuracyMode,
                ),
                SettingsSwitchTile(
                  icon: Icons.sync_rounded,
                  title: 'Sync on mobile data',
                  subtitle: 'Off means Wi-Fi only for image uploads',
                  value: s.syncOnMobileData,
                  onChanged: s.setSyncOnMobileData,
                ),
                SettingsSwitchTile(
                  icon: Icons.battery_saver_rounded,
                  title: 'Battery saver',
                  subtitle: 'Sample every ${c.locationIntervalSeconds * 2}s '
                      'instead of ${c.locationIntervalSeconds}s',
                  value: s.batterySaver,
                  onChanged: s.setBatterySaver,
                ),
              ],
            ),

            // Debug builds only. This used to sit in the main Tracking group
            // labelled "Demo only", visible in release — a tap away from
            // putting a real employee's day into a simulated location state.
            if (kDebugMode && scope.locationService is MockLocationService)
              ...<Widget>[
              const SizedBox(height: Insets.xl),
              SettingsGroup(
                title: 'Simulation (debug build)',
                tiles: <Widget>[
                  SettingsTile(
                    icon: Icons.tune_rounded,
                    title: 'Simulate location state',
                    subtitle: 'Exercise the blocked-start flows',
                    tone: AppColors.warning,
                    onTap: () => _simulateHealth(context, scope),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Debug-only switch so the "location off / permission denied" dialogs can
  /// be walked through without changing device settings.
  Future<void> _simulateHealth(BuildContext context, AppScope scope) async {
    final LocationService service = scope.locationService;
    if (service is! MockLocationService) return;

    final LocationHealth? picked = await showModalBottomSheet<LocationHealth>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: LocationHealth.values
                // Not simulable: it is a legacy wire value with no UI left.
                .where((LocationHealth h) => h != LocationHealth.poorAccuracy)
                .map(
                  (LocationHealth h) => ListTile(
                    title: Text(h.title),
                    subtitle: Text(
                      h.message,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: service.simulatedHealth == h
                        ? const Icon(Icons.check_rounded,
                            color: AppColors.primary)
                        : null,
                    onTap: () => Navigator.pop(context, h),
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ),
    );
    if (picked == null || !context.mounted) return;
    service.simulatedHealth = picked;

    final bool reset = await showDialog<bool>(
          context: context,
          builder: (BuildContext context) => AlertDialog(
            title: const Text('Reset today?'),
            content: const Text(
              'Put the day back to "Not started" so the Start Day flow can be '
              'tested with this location state.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Keep day'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Reset'),
              ),
            ],
          ),
        ) ??
        false;
    if (reset) await scope.tracking.resetDay();
  }
}
