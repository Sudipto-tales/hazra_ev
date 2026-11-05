import 'package:flutter/material.dart';

import '../../core/config/brand.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../state/app_scope.dart';
import '../../state/settings_controller.dart';
import '../../widgets/settings_tile.dart';
import '../auth/role_select_page.dart';
import 'account_deletion_page.dart';
import 'change_password_page.dart';
import 'developer_options_page.dart';
import 'edit_profile_page.dart';
import 'info_pages.dart';
import 'permissions_page.dart';
import 'personal_info_page.dart';
import 'support_page.dart';
import 'version_page.dart';

enum SettingsSection { all, notifications }

/// Settings, in six groups plus a version card.
///
/// The previous layout mixed organisation tracking thresholds, three device
/// overrides and a release-visible location simulator in among Account and
/// Notifications. All of that now lives behind **Developer options**, so this
/// screen is only the things an employee changes about themselves. Nothing was
/// dropped — see [DeveloperOptionsPage].
class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key, this.initialSection = SettingsSection.all});

  final SettingsSection initialSection;

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);
    final SettingsController s = scope.settings;
    final bool full = initialSection == SettingsSection.all;

    return Scaffold(
      appBar: AppBar(title: Text(full ? 'Settings' : 'Notifications')),
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
              if (full) ...<Widget>[
                SettingsGroup(
                  title: 'Account',
                  tiles: <Widget>[
                    // `PATCH /me` accepts phone and avatarUrl and nothing
                    // else, so the page offers exactly those two and says who
                    // owns the rest. Anything wider would be refused.
                    SettingsTile(
                      icon: Icons.person_outline_rounded,
                      title: 'Edit profile',
                      subtitle: 'Phone and photo',
                      onTap: () => _push(context, const EditProfilePage()),
                    ),
                    SettingsTile(
                      icon: Icons.badge_outlined,
                      title: 'Account information',
                      subtitle: 'ID, designation and reporting line',
                      onTap: () => _push(context, const PersonalInfoPage()),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Permissions',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.shield_outlined,
                      title: 'Location access',
                      subtitle: 'Check status and fix it from here',
                      tone: AppColors.success,
                      onTap: () => _push(context, const PermissionsPage()),
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
              if (full) ...<Widget>[
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
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Security',
                  tiles: <Widget>[
                    // `POST /me/password` takes the current password as its
                    // authorisation and signs out every other device.
                    SettingsTile(
                      icon: Icons.lock_outline_rounded,
                      title: 'Change password',
                      subtitle: 'Signs out your other devices',
                      onTap: () => _push(context, const ChangePasswordPage()),
                    ),
                    SettingsTile(
                      icon: Icons.person_off_outlined,
                      title: 'Delete account',
                      subtitle: 'Submit a request or view its status',
                      destructive: true,
                      onTap: () => _push(context, const AccountDeletionPage()),
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
                  title: 'About & support',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.info_outline_rounded,
                      title: 'About ${Brand.product}',
                      subtitle: 'Credits, website and support',
                      onTap: () => _push(context, const AboutPage()),
                    ),
                    SettingsTile(
                      icon: Icons.help_outline_rounded,
                      title: 'Help',
                      onTap: () => _push(context, const HelpPage()),
                    ),
                    SettingsTile(
                      icon: Icons.support_agent_rounded,
                      title: 'Contact support',
                      subtitle: SupportContacts.email,
                      onTap: () => _push(context, const ContactSupportPage()),
                    ),
                    SettingsTile(
                      icon: Icons.bug_report_outlined,
                      title: 'Report a problem',
                      subtitle: 'Details support will ask for, ready to send',
                      onTap: () => _push(context, const ReportProblemPage()),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Advanced',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.science_outlined,
                      title: 'Developer options',
                      subtitle: 'Tracking rules and diagnostics',
                      tone: AppColors.warning,
                      onTap: () =>
                          _push(context, const DeveloperOptionsPage()),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                // Its own card, as requested — and the way into the version
                // screen (four taps).
                const VersionTapCard(),
              ],
            ],
          );
        },
      ),
    );
  }

  static void _push(BuildContext context, Widget page) =>
      Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => page),
      );

  static String _themeLabel(ThemeMode mode) => switch (mode) {
        ThemeMode.system => 'Follow system',
        ThemeMode.light => 'Light',
        ThemeMode.dark => 'Dark',
      };

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
                      ? const Icon(Icons.check_rounded,
                          color: AppColors.primary)
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
                      ? const Icon(Icons.check_rounded,
                          color: AppColors.primary)
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
}
