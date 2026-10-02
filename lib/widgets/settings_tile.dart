import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/theme/theme_ext.dart';

/// Grouped settings block — a titled card holding [SettingsTile] rows.
class SettingsGroup extends StatelessWidget {
  const SettingsGroup({super.key, required this.title, required this.tiles});

  final String title;
  final List<Widget> tiles;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(Insets.xs, 0, 0, Insets.sm),
          child: Text(
            title.toUpperCase(),
            style: theme.textTheme.labelSmall,
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: context.cardColor,
            borderRadius: BorderRadius.circular(Radii.lg),
            border: Border.all(color: context.lineColor),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            children: <Widget>[
              for (int i = 0; i < tiles.length; i++) ...<Widget>[
                tiles[i],
                if (i != tiles.length - 1)
                  Divider(height: 1, indent: 56, color: theme.dividerColor),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class SettingsTile extends StatelessWidget {
  const SettingsTile({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.trailing,
    this.onTap,
    this.tone = AppColors.primary,
    this.destructive = false,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color tone;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color color = destructive ? AppColors.danger : tone;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Insets.lg,
          vertical: Insets.md,
        ),
        child: Row(
          children: <Widget>[
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(Radii.sm),
              ),
              child: Icon(icon, size: 17, color: color),
            ),
            const SizedBox(width: Insets.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontSize: 14.5,
                      color: destructive ? AppColors.danger : null,
                    ),
                  ),
                  if (subtitle != null) ...<Widget>[
                    const SizedBox(height: 2),
                    Text(subtitle!, style: theme.textTheme.bodySmall),
                  ],
                ],
              ),
            ),
            if (trailing != null)
              trailing!
            else if (onTap != null)
              Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: theme.textTheme.bodySmall?.color,
              ),
          ],
        ),
      ),
    );
  }
}

/// Switch row that keeps the 48dp touch target without a cramped layout.
class SettingsSwitchTile extends StatelessWidget {
  const SettingsSwitchTile({
    super.key,
    required this.icon,
    required this.title,
    required this.value,
    required this.onChanged,
    this.subtitle,
    this.tone = AppColors.primary,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return SettingsTile(
      icon: icon,
      title: title,
      subtitle: subtitle,
      tone: tone,
      onTap: () => onChanged(!value),
      trailing: Switch.adaptive(value: value, onChanged: onChanged),
    );
  }
}
