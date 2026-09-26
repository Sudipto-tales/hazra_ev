import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../../widgets/avatar.dart';
import '../../widgets/settings_tile.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';
import '../auth/role_select_page.dart';
import '../home/widgets/tracking_sheet.dart';
import 'change_password_page.dart';
import 'info_pages.dart';
import 'personal_info_page.dart';
import 'settings_page.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  bool _loading = true;
  Object? _error;
  Employee? _me;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  /// `GET /api/employee/profile` — `?subject=me`, resolved from the token, so
  /// the app never sends an employee id on this route.
  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final Employee me = await AppScope.of(context).repository.profile();
      if (!mounted) return;
      setState(() {
        _me = me;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    // The header *is* the identity, so there is nothing below it worth showing
    // until the profile has landed — the page waits rather than popping in
    // around an empty banner.
    if (_loading) {
      return const Scaffold(
        body: Padding(
          padding: EdgeInsets.all(Insets.lg),
          child: LoadingCards(count: 4),
        ),
      );
    }
    if (_error != null || _me == null) {
      return Scaffold(
        body: ErrorState(
          onRetry: _load,
          message: 'Your profile could not be loaded. Anything you recorded '
              'today is still safe on this device.',
        ),
      );
    }

    final AppScope scope = AppScope.of(context);

    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: <Widget>[
          _ProfileHeader(employee: _me!),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              Insets.xl,
              Insets.lg,
              Insets.xxxl,
            ),
            child: Column(
              children: <Widget>[
                // The account is still on a password the server generated —
                // nobody has chosen one. Advisory, not a gate: the app works
                // fine either way, and locking someone out of their own day
                // over a password prompt would be worse than the prompt.
                if (_me!.mustChangePassword) ...<Widget>[
                  AlertBanner(
                    icon: Icons.key_rounded,
                    title: 'Set your own password',
                    message: 'You are signed in with the password your admin '
                        'gave you. Pick one only you know.',
                    tone: AppColors.warning,
                    actionLabel: 'Change password',
                    onAction: () async {
                      final bool? changed =
                          await Navigator.of(context).push<bool>(
                        MaterialPageRoute<bool>(
                          builder: (_) => const ChangePasswordPage(),
                        ),
                      );
                      if (changed == true && mounted) _load();
                    },
                  ),
                  const SizedBox(height: Insets.lg),
                ],
                ListenableBuilder(
                  listenable: scope.tracking,
                  builder: (BuildContext context, _) {
                    return AppCard(
                      child: Column(
                        children: <Widget>[
                          KeyValueRow(
                            label: 'Joining time today',
                            value: Fmt.time(scope.tracking.joiningTime),
                            icon: Icons.login_rounded,
                          ),
                          KeyValueRow(
                            label: 'Worked today',
                            value: Fmt.duration(scope.tracking.workedToday),
                            icon: Icons.timer_outlined,
                          ),
                          KeyValueRow(
                            label: 'Distance today',
                            value: Fmt.km(scope.tracking.summary.distanceKm),
                            icon: Icons.route_rounded,
                          ),
                        ],
                      ),
                    );
                  },
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Account',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.person_outline_rounded,
                      title: 'Personal information',
                      subtitle: 'Contact details, department, reporting line',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const PersonalInfoPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.settings_outlined,
                      title: 'Settings',
                      subtitle: 'Tracking, notifications, appearance',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const SettingsPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.notifications_none_rounded,
                      title: 'Notifications',
                      subtitle: 'Reminders and alerts',
                      tone: AppColors.warning,
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const SettingsPage(
                            initialSection: SettingsSection.notifications,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Tracking',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.my_location_rounded,
                      title: 'Location & tracking',
                      subtitle: 'Permission, accuracy, offline queue',
                      tone: AppColors.success,
                      onTap: () => showTrackingSheet(context),
                    ),
                    SettingsTile(
                      icon: Icons.privacy_tip_outlined,
                      title: 'Privacy',
                      subtitle: 'What is collected and when',
                      tone: AppColors.info,
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const PrivacyPage(),
                        ),
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
                      title: 'Help & support',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const HelpPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.info_outline_rounded,
                      title: 'About',
                      subtitle: 'Version 0.1.0',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const AboutPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.logout_rounded,
                      title: 'Log out',
                      destructive: true,
                      onTap: () => _logout(context),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _logout(BuildContext context) async {
    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text(
          'Any queued locations and draft reports stay on this device and will '
          'upload the next time you sign in.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.danger,
              minimumSize: const Size(120, Sizes.touchTarget),
            ),
            child: const Text('Log out'),
          ),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const RoleSelectPage()),
      (Route<dynamic> route) => false,
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.employee});

  final Employee employee;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return SizedBox(
      height: Sizes.bannerHeight + 92,
      child: Stack(
        children: <Widget>[
          Container(
            height: Sizes.bannerHeight,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: <Color>[AppColors.primary, AppColors.primaryDark],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: SafeArea(
              child: Align(
                alignment: Alignment.topRight,
                child: Padding(
                  padding: const EdgeInsets.only(right: Insets.sm),
                  child: IconButton(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const SettingsPage(),
                      ),
                    ),
                    icon: const Icon(Icons.settings_outlined,
                        color: Colors.white),
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: Insets.lg,
            right: Insets.lg,
            top: Sizes.bannerHeight - 44,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                ProfileAvatar(
                  initials: employee.initials,
                  imageUrl: employee.avatarUrl,
                  size: Sizes.avatarLg,
                  borderColor: theme.scaffoldBackgroundColor,
                ),
                const SizedBox(width: Insets.md),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 50),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          employee.name,
                          style: theme.textTheme.headlineSmall,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${employee.employeeCode} · ${employee.designation}',
                          style: theme.textTheme.bodySmall,
                          maxLines: 2,
                        ),
                        // Department is optional — drop the line rather than
                        // leaving an orphan icon next to nothing.
                        if (employee.department.trim().isNotEmpty) ...<Widget>[
                          const SizedBox(height: 4),
                          Row(
                            children: <Widget>[
                              const Icon(Icons.apartment_rounded,
                                  size: 14, color: AppColors.textSecondary),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(
                                  employee.department,
                                  style: theme.textTheme.bodySmall,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
