import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/theme/theme_ext.dart';
import '../../state/app_scope.dart';
import '../../widgets/app_card.dart';
import '../profile/app_info.dart';
import 'admin_login_page.dart';
import 'login_page.dart';

/// First screen of the app. Splits the two audiences before any credential is
/// asked for, so the admin surface is never reachable from the employee form.
class RoleSelectPage extends StatelessWidget {
  const RoleSelectPage({super.key});

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (BuildContext context, BoxConstraints constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: Insets.xxl),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: IntrinsicHeight(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const SizedBox(height: 72),
                      Container(
                        width: 58,
                        height: 58,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: <Color>[
                              AppColors.primary,
                              AppColors.primaryDark,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(Radii.lg),
                        ),
                        child: const Icon(
                          Icons.route_rounded,
                          color: Colors.white,
                          size: 30,
                        ),
                      ),
                      const SizedBox(height: Insets.xxl),
                      Text(
                        'Field Tracker',
                        style: theme.textTheme.displaySmall,
                      ),
                      const SizedBox(height: Insets.sm),
                      Text(
                        'Choose how you want to sign in.',
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: theme.textTheme.bodyMedium?.color,
                        ),
                      ),
                      const SizedBox(height: Insets.xxxl),
                      _RoleCard(
                        icon: Icons.badge_outlined,
                        tone: AppColors.primary,
                        toneSoft: AppColors.primarySoft,
                        title: 'Employee Login',
                        subtitle:
                            'Start your day, log company visits and submit '
                            'reports.',
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const LoginPage(),
                          ),
                        ),
                      ),
                      const SizedBox(height: Insets.lg),
                      _RoleCard(
                        icon: Icons.shield_outlined,
                        tone: AppColors.danger,
                        toneSoft: AppColors.dangerSoft,
                        title: 'Restricted',
                        badge: 'Authorized Personnel Only',
                        subtitle:
                            'Team monitoring, route history and report review.',
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const AdminLoginPage(),
                          ),
                        ),
                      ),
                      const Spacer(),
                      Center(
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: Insets.xl),
                          child: Text(
                            AppInfo.labelFor(
                              live: AppScope.of(context).isLive,
                            ),
                            style: theme.textTheme.bodySmall,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// One of the two entry points. [badge] renders the restricted strip.
class _RoleCard extends StatelessWidget {
  const _RoleCard({
    required this.icon,
    required this.tone,
    required this.toneSoft,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badge,
  });

  final IconData icon;
  final Color tone;
  final Color toneSoft;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool dark = context.isDark;
    // The soft tints are light-mode swatches; in dark mode a low-alpha wash of
    // the tone itself keeps the same relationship without a second palette.
    final Color wash = dark ? tone.withValues(alpha: 0.16) : toneSoft;

    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.all(Insets.xl),
      borderColor:
          dark ? tone.withValues(alpha: 0.35) : tone.withValues(alpha: 0.22),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: Sizes.avatarMd,
            height: Sizes.avatarMd,
            decoration: BoxDecoration(
              color: wash,
              borderRadius: BorderRadius.circular(Radii.md),
            ),
            child: Icon(icon, color: tone, size: 24),
          ),
          const SizedBox(width: Insets.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(color: tone),
                ),
                if (badge != null) ...<Widget>[
                  const SizedBox(height: Insets.xs),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Insets.sm,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: wash,
                      borderRadius: BorderRadius.circular(Radii.pill),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(Icons.lock_rounded, size: 12, color: tone),
                        const SizedBox(width: Insets.xs),
                        Text(
                          badge!,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: tone,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: Insets.sm),
                Text(subtitle, style: theme.textTheme.bodyMedium),
              ],
            ),
          ),
          const SizedBox(width: Insets.sm),
          Icon(
            Icons.chevron_right_rounded,
            color: theme.textTheme.bodySmall?.color,
          ),
        ],
      ),
    );
  }
}
