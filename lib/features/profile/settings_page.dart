import 'package:flutter/material.dart';

import '../../core/config/tracking_config.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../data/models/models.dart';
import '../../services/location_service.dart';
import '../../state/app_scope.dart';
import '../../state/settings_controller.dart';
import '../../widgets/settings_tile.dart';
import '../auth/role_select_page.dart';
import '../home/widgets/tracking_sheet.dart';
import 'info_pages.dart';

enum SettingsSection { all, notifications }

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key, this.initialSection = SettingsSection.all});

  final SettingsSection initialSection;

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);
    final SettingsController s = scope.settings;
    const TrackingConfig config = TrackingConfig.defaults;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          initialSection == SettingsSection.notifications
              ? 'Notifications'
              : 'Settings',
        ),
      ),
      body: ListenableBuilder(
        listenable: s,
        builder: (BuildContext context, _) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              Insets.lg,
              Insets.lg,
              Insets.xxxl,
            ),
            children: <Widget>[
              if (initialSection == SettingsSection.all) ...<Widget>[
                SettingsGroup(
                  title: 'Account',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.person_outline_rounded,
                      title: 'Edit profile',
                      subtitle: 'Photo, phone, address',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.password_rounded,
                      title: 'Change password',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.badge_outlined,
                      title: 'Account information',
                      subtitle: 'EMP-1042 · Field Sales — North Zone',
                      onTap: () => _todo(context),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Tracking',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.my_location_rounded,
                      title: 'Location status',
                      subtitle: 'Live permission, accuracy and queue',
                      tone: AppColors.success,
                      onTap: () => showTrackingSheet(context),
                    ),
                    SettingsTile(
                      icon: Icons.tune_rounded,
                      title: 'Simulate location state',
                      subtitle: 'Demo only — exercise the blocked-start flows',
                      tone: AppColors.warning,
                      onTap: () => _simulateHealth(context, scope),
                    ),
                    SettingsSwitchTile(
                      icon: Icons.gps_fixed_rounded,
                      title: 'High accuracy mode',
                      subtitle:
                          'Reject fixes worse than ±${config.minAccuracyMetres.toInt()} m',
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
                      subtitle:
                          'Sample every ${config.locationIntervalSeconds * 2}s instead of ${config.locationIntervalSeconds}s',
                      value: s.batterySaver,
                      onChanged: s.setBatterySaver,
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
              ],
              SettingsGroup(
                title: 'Notifications',
                tiles: <Widget>[
                  SettingsSwitchTile(
                    icon: Icons.notifications_active_outlined,
                    title: 'Enable notifications',
                    value: s.notificationsEnabled,
                    onChanged: s.setNotificationsEnabled,
                  ),
                  SettingsSwitchTile(
                    icon: Icons.description_outlined,
                    title: 'Report reminders',
                    subtitle: 'Nudge me if I leave a company without a report',
                    value: s.reportReminders,
                    onChanged: s.setReportReminders,
                  ),
                  SettingsSwitchTile(
                    icon: Icons.timer_outlined,
                    title: 'Work & session reminders',
                    subtitle: 'Start day, long stop, end day',
                    value: s.sessionReminders,
                    onChanged: s.setSessionReminders,
                  ),
                  SettingsSwitchTile(
                    icon: Icons.campaign_outlined,
                    title: 'System notifications',
                    value: s.systemNotifications,
                    onChanged: s.setSystemNotifications,
                  ),
                ],
              ),
              if (initialSection == SettingsSection.all) ...<Widget>[
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'App',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.dark_mode_outlined,
                      title: 'Theme',
                      subtitle: _themeLabel(s.themeMode),
                      onTap: () => _pickTheme(context, s),
                    ),
                    SettingsTile(
                      icon: Icons.language_rounded,
                      title: 'Language',
                      subtitle: s.language,
                      onTap: () => _pickLanguage(context, s),
                    ),
                    SettingsTile(
                      icon: Icons.storage_outlined,
                      title: 'Data preferences',
                      subtitle: 'Cache, offline queue, image quality',
                      onTap: () => _todo(context),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Security',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.lock_outline_rounded,
                      title: 'Change password',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.devices_rounded,
                      title: 'Device & sessions',
                      subtitle: 'This device · signed in today',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.logout_rounded,
                      title: 'Log out',
                      destructive: true,
                      onTap: () => Navigator.of(context, rootNavigator: true)
                          .pushAndRemoveUntil(
                        MaterialPageRoute<void>(
                          builder: (_) => const RoleSelectPage(),
                        ),
                        (Route<dynamic> route) => false,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Support',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.help_outline_rounded,
                      title: 'Help',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(builder: (_) => const HelpPage()),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.support_agent_rounded,
                      title: 'Contact support',
                      subtitle: 'support@company.com',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.bug_report_outlined,
                      title: 'Report a problem',
                      onTap: () => _todo(context),
                    ),
                    SettingsTile(
                      icon: Icons.info_outline_rounded,
                      title: 'App version',
                      subtitle: '0.1.0 (static demo data)',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(builder: (_) => const AboutPage()),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          );
        },
      ),
    );
  }

  static String _themeLabel(ThemeMode mode) => switch (mode) {
        ThemeMode.system => 'Follow system',
        ThemeMode.light => 'Light',
        ThemeMode.dark => 'Dark',
      };

  void _todo(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Not wired up in this design build')),
    );
  }

  Future<void> _pickTheme(BuildContext context, SettingsController s) async {
    final ThemeMode? mode = await showModalBottomSheet<ThemeMode>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: ThemeMode.values
              .map(
                (ThemeMode m) => ListTile(
                  title: Text(_themeLabel(m)),
                  trailing: s.themeMode == m
                      ? const Icon(Icons.check_rounded, color: AppColors.primary)
                      : null,
                  onTap: () => Navigator.pop(context, m),
                ),
              )
              .toList(growable: false),
        ),
      ),
    );
    if (mode != null) s.setThemeMode(mode);
  }

  Future<void> _pickLanguage(BuildContext context, SettingsController s) async {
    const List<String> languages = <String>['English', 'বাংলা', 'हिन्दी'];
    final String? picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: languages
              .map(
                (String l) => ListTile(
                  title: Text(l),
                  trailing: s.language == l
                      ? const Icon(Icons.check_rounded, color: AppColors.primary)
                      : null,
                  onTap: () => Navigator.pop(context, l),
                ),
              )
              .toList(growable: false),
        ),
      ),
    );
    if (picked != null) s.setLanguage(picked);
  }

  /// Demo-only switch so the "location off / permission denied" dialogs can be
  /// walked through without changing device settings.
  Future<void> _simulateHealth(BuildContext context, AppScope scope) async {
    final LocationService service = scope.locationService;
    if (service is! MockLocationService) return;

    final LocationHealth? picked = await showModalBottomSheet<LocationHealth>(
      context: context,
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: LocationHealth.values
              .map(
                (LocationHealth h) => ListTile(
                  title: Text(h.title),
                  subtitle: Text(
                    h.message,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: service.simulatedHealth == h
                      ? const Icon(Icons.check_rounded, color: AppColors.primary)
                      : null,
                  onTap: () => Navigator.pop(context, h),
                ),
              )
              .toList(growable: false),
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
