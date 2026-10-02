import 'package:flutter/material.dart';

import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';

/// The geofence / tracking thresholds, editable.
///
/// These are the same values [TrackingConfig] already holds — stop radius,
/// dwell time, the long-stop line the dashboard alerts on — so changing one
/// here visibly changes what the map and the dashboard flag.
class TrackingRulesPage extends StatefulWidget {
  const TrackingRulesPage({super.key});

  @override
  State<TrackingRulesPage> createState() => _TrackingRulesPageState();
}

class _TrackingRulesPageState extends State<TrackingRulesPage> {
  bool _loading = true;
  bool _busy = false;
  TrackingConfig _config = TrackingConfig.defaults;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final TrackingConfig config =
        await AppScope.of(context).adminRepository.config();
    if (!mounted) return;
    setState(() {
      _config = config;
      _loading = false;
    });
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    await AppScope.of(context).adminRepository.saveConfig(_config);
    if (!mounted) return;
    setState(() => _busy = false);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Tracking rules saved')),
    );
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tracking rules'),
        actions: <Widget>[
          TextButton(
            onPressed: () =>
                setState(() => _config = TrackingConfig.defaults),
            child: const Text('Reset'),
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.lg),
          child: SizedBox(
            height: Sizes.primaryActionHeight,
            child: FilledButton(
              onPressed: _busy || _loading ? null : _save,
              child: const Text('Save rules'),
            ),
          ),
        ),
      ),
      body: _loading
          ? const Padding(
              padding: EdgeInsets.all(Insets.lg),
              child: LoadingCards(count: 3),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                Insets.lg,
                Insets.lg,
                Insets.xxxl,
              ),
              children: <Widget>[
                const AlertBanner(
                  icon: Icons.info_outline_rounded,
                  title: 'Applies to how data is read',
                  message: 'Thresholds change what counts as a stop and what '
                      'the dashboard flags as a long stop. Already-recorded '
                      'GPS fixes are not rewritten.',
                  tone: AppColors.info,
                ),
                const SizedBox(height: Insets.lg),

                _slider(
                  title: 'Stop radius',
                  help: 'Employee must stay inside this radius to count as '
                      'stopped.',
                  value: _config.stopRadiusMetres,
                  min: 25,
                  max: 200,
                  divisions: 35,
                  unit: 'm',
                  onChanged: (double v) => setState(
                    () => _config = _config.copyWith(stopRadiusMetres: v),
                  ),
                ),
                _slider(
                  title: 'Minimum dwell',
                  help: 'Shorter pauses are ignored — a traffic light is not '
                      'a visit.',
                  value: _config.stopThresholdMinutes.toDouble(),
                  min: 5,
                  max: 30,
                  divisions: 25,
                  unit: 'min',
                  onChanged: (double v) => setState(
                    () => _config =
                        _config.copyWith(stopThresholdMinutes: v.round()),
                  ),
                ),
                _slider(
                  title: 'Long stop',
                  help: 'Dwell time after which the dashboard raises a red '
                      'alert.',
                  value: _config.longStopThresholdMinutes.toDouble(),
                  min: 20,
                  max: 120,
                  divisions: 20,
                  unit: 'min',
                  tone: AppColors.danger,
                  onChanged: (double v) => setState(
                    () => _config =
                        _config.copyWith(longStopThresholdMinutes: v.round()),
                  ),
                ),
                _slider(
                  title: 'Accuracy floor',
                  help: 'Fixes worse than this are dropped rather than '
                      'plotted.',
                  value: _config.minAccuracyMetres,
                  min: 20,
                  max: 150,
                  divisions: 26,
                  unit: 'm',
                  onChanged: (double v) => setState(
                    () => _config = _config.copyWith(minAccuracyMetres: v),
                  ),
                ),
                _slider(
                  title: 'Fix interval',
                  help: 'How often a GPS fix is captured during a session.',
                  value: _config.locationIntervalSeconds.toDouble(),
                  min: 15,
                  max: 120,
                  divisions: 21,
                  unit: 's',
                  onChanged: (double v) => setState(
                    () => _config =
                        _config.copyWith(locationIntervalSeconds: v.round()),
                  ),
                ),
                _slider(
                  title: 'Offline window',
                  help: 'No upload for this long and the employee shows as '
                      'Offline.',
                  value: _config.offlineThresholdMinutes.toDouble(),
                  min: 5,
                  max: 60,
                  divisions: 11,
                  unit: 'min',
                  onChanged: (double v) => setState(
                    () => _config =
                        _config.copyWith(offlineThresholdMinutes: v.round()),
                  ),
                ),

                const SectionHeader(
                  title: 'Not editable here',
                  padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
                ),
                AppCard(
                  child: Column(
                    children: <Widget>[
                      KeyValueRow(
                        label: 'Invalid GPS jump above',
                        value: '${_config.maxJumpKmh.round()} km/h',
                        dense: true,
                      ),
                      KeyValueRow(
                        label: 'Stationary below',
                        value: '${_config.movementSpeedThresholdKmh} km/h',
                        dense: true,
                      ),
                      KeyValueRow(
                        label: 'Sync batch size',
                        value: '${_config.syncBatchSize} fixes',
                        dense: true,
                      ),
                    ],
                  ),
                ),
              ],
            ),
    );
  }

  Widget _slider({
    required String title,
    required String help,
    required double value,
    required double min,
    required double max,
    required int divisions,
    required String unit,
    required ValueChanged<double> onChanged,
    Color tone = AppColors.primary,
  }) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: AppCard(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.md,
          Insets.lg,
          Insets.sm,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(title, style: theme.textTheme.titleMedium),
                ),
                Text(
                  '${value.round()} $unit',
                  style: theme.textTheme.titleMedium?.copyWith(color: tone),
                ),
              ],
            ),
            Text(help, style: theme.textTheme.bodySmall),
            Slider(
              value: value.clamp(min, max),
              min: min,
              max: max,
              divisions: divisions,
              activeColor: tone,
              label: '${value.round()} $unit',
              onChanged: onChanged,
            ),
          ],
        ),
      ),
    );
  }
}
