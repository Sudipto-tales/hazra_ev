import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../state/app_scope.dart';
import '../../../state/settings_controller.dart';
import '../../../widgets/settings_tile.dart';

/// Console preferences. Theme and notifications reuse the shared
/// [SettingsController] so the two shells agree about the app's appearance.
class AdminSettingsPage extends StatefulWidget {
  const AdminSettingsPage({super.key});

  @override
  State<AdminSettingsPage> createState() => _AdminSettingsPageState();
}

class _AdminSettingsPageState extends State<AdminSettingsPage> {
  bool _longStopAlerts = true;
  bool _offlineAlerts = true;
  bool _dailyDigest = false;

  Widget _themeTile(
    SettingsController settings,
    ThemeMode mode,
    IconData icon,
    String title,
  ) {
    final bool selected = settings.themeMode == mode;
    return SettingsTile(
      icon: icon,
      title: title,
      trailing: Icon(
        selected
            ? Icons.radio_button_checked_rounded
            : Icons.radio_button_unchecked_rounded,
        color: selected ? AppColors.primary : AppColors.textTertiary,
      ),
      onTap: () => settings.setThemeMode(mode),
    );
  }

  @override
  Widget build(BuildContext context) {
    final SettingsController settings = AppScope.of(context).settings;

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListenableBuilder(
        listenable: settings,
        builder: (BuildContext context, _) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              Insets.lg,
              Insets.lg,
              Insets.xxxl,
            ),
            children: <Widget>[
              SettingsGroup(
                title: 'Appearance',
                tiles: <Widget>[
                  _themeTile(
                    settings,
                    ThemeMode.light,
                    Icons.light_mode_outlined,
                    'Light',
                  ),
                  _themeTile(
                    settings,
                    ThemeMode.dark,
                    Icons.dark_mode_outlined,
                    'Dark',
                  ),
                  _themeTile(
                    settings,
                    ThemeMode.system,
                    Icons.brightness_auto_outlined,
                    'Follow system',
                  ),
                ],
              ),
              const SizedBox(height: Insets.lg),
              SettingsGroup(
                title: 'Alerts',
                tiles: <Widget>[
                  SettingsSwitchTile(
                    icon: Icons.timer_off_outlined,
                    title: 'Long stop alerts',
                    subtitle: 'Notify when someone exceeds the threshold',
                    value: _longStopAlerts,
                    onChanged: (bool v) => setState(() => _longStopAlerts = v),
                  ),
                  SettingsSwitchTile(
                    icon: Icons.cloud_off_rounded,
                    title: 'Offline / GPS lost',
                    subtitle: 'Notify when tracking degrades',
                    value: _offlineAlerts,
                    onChanged: (bool v) => setState(() => _offlineAlerts = v),
                  ),
                  SettingsSwitchTile(
                    icon: Icons.summarize_outlined,
                    title: 'Daily digest',
                    subtitle: 'End-of-day team summary',
                    value: _dailyDigest,
                    onChanged: (bool v) => setState(() => _dailyDigest = v),
                  ),
                ],
              ),
              const SizedBox(height: Insets.lg),
              SettingsGroup(
                title: 'Notifications',
                tiles: <Widget>[
                  SettingsSwitchTile(
                    icon: Icons.notifications_outlined,
                    title: 'Push notifications',
                    value: settings.notificationsEnabled,
                    onChanged: settings.setNotificationsEnabled,
                  ),
                  SettingsSwitchTile(
                    icon: Icons.description_outlined,
                    title: 'New report submitted',
                    value: settings.reportReminders,
                    onChanged: settings.setReportReminders,
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}
