import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/settings_tile.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../../auth/role_select_page.dart';
import '../employees/employees_page.dart';
import '../manage/tracking_rules_page.dart';
import '../products/products_page.dart';
import 'admin_settings_page.dart';

/// Admin identity plus the entry points to the management screens. These are
/// deliberately pushed routes rather than nav destinations — infrequent,
/// destination-style, exactly how the employee Profile tab treats Settings.
class AdminProfilePage extends StatefulWidget {
  const AdminProfilePage({super.key});

  @override
  State<AdminProfilePage> createState() => _AdminProfilePageState();
}

class _AdminProfilePageState extends State<AdminProfilePage> {
  bool _loading = true;
  AdminUser? _admin;
  int _headcount = 0;
  int _pending = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final AppScope scope = AppScope.of(context);
    final AdminUser admin = await scope.adminRepository.profile();
    final List<TeamMember> team = await scope.adminRepository.team();
    final int pending = await scope.adminRepository.pendingReviewCount();
    if (!mounted) return;
    setState(() {
      _admin = admin;
      _headcount = team.length;
      _pending = pending;
      _loading = false;
    });
  }

  Future<void> _logout() async {
    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Sign out of the admin console?'),
        content: const Text(
          'You will need your admin ID and password to get back in.',
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
            child: const Text('Sign out'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const RoleSelectPage()),
      (Route<dynamic> route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Admin')),
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
                AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          ProfileAvatar(
                            initials: _admin!.initials,
                            imageUrl: _admin!.avatarUrl,
                            size: Sizes.avatarLg * 0.72,
                          ),
                          const SizedBox(width: Insets.lg),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  _admin!.name,
                                  style: theme.textTheme.titleLarge,
                                ),
                                Text(
                                  _admin!.role,
                                  style: theme.textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      const Divider(height: Insets.xl),
                      KeyValueRow(
                        label: 'Admin ID',
                        value: _admin!.adminCode,
                        dense: true,
                      ),
                      KeyValueRow(
                        label: 'Scope',
                        value: _admin!.region,
                        dense: true,
                      ),
                      KeyValueRow(
                        label: 'Email',
                        value: _admin!.email,
                        dense: true,
                      ),
                      KeyValueRow(
                        label: 'Phone',
                        value: _admin!.phone,
                        dense: true,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: Insets.lg),
                StatGrid(
                  children: <Widget>[
                    StatTile(
                      icon: Icons.groups_rounded,
                      value: '$_headcount',
                      label: 'Field staff',
                    ),
                    StatTile(
                      icon: Icons.rate_review_outlined,
                      value: '$_pending',
                      label: 'Awaiting review',
                      tone: AppColors.warning,
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                SettingsGroup(
                  title: 'Manage',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.badge_outlined,
                      title: 'Employees',
                      subtitle: 'Add and edit field employees',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const EmployeesPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.inventory_2_outlined,
                      title: 'Products',
                      subtitle: 'The catalogue the field team can log',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const ProductsPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.my_location_rounded,
                      title: 'Tracking rules',
                      subtitle: 'Stop radius, dwell time, long-stop alert',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const TrackingRulesPage(),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Insets.lg),
                SettingsGroup(
                  title: 'Console',
                  tiles: <Widget>[
                    SettingsTile(
                      icon: Icons.settings_outlined,
                      title: 'Settings',
                      subtitle: 'Theme, alerts, notifications',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => const AdminSettingsPage(),
                        ),
                      ),
                    ),
                    SettingsTile(
                      icon: Icons.logout_rounded,
                      title: 'Sign out',
                      destructive: true,
                      onTap: _logout,
                    ),
                  ],
                ),
                const SizedBox(height: Insets.xl),
                Center(
                  child: Text(
                    'Admin console 0.1.0 · Static demo data',
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ],
            ),
    );
  }
}
