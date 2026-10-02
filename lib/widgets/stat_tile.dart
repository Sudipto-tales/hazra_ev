import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import 'app_card.dart';

/// Compact metric block: icon, value, label. Used in Home summary, calendar
/// day detail and the statistics screen.
class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.icon,
    required this.value,
    required this.label,
    this.tone = AppColors.primary,
    this.caption,
    this.onTap,
  });

  final IconData icon;
  final String value;
  final String label;
  final Color tone;
  final String? caption;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.all(Insets.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: tone.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(Radii.sm),
            ),
            child: Icon(icon, size: 18, color: tone),
          ),
          const SizedBox(height: Insets.md),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleLarge?.copyWith(fontSize: 19),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall,
          ),
          if (caption != null) ...<Widget>[
            const SizedBox(height: 4),
            Text(
              caption!,
              style: theme.textTheme.labelSmall?.copyWith(color: tone),
            ),
          ],
        ],
      ),
    );
  }
}

/// Responsive grid that keeps tiles readable from small phones to tablets.
class StatGrid extends StatelessWidget {
  const StatGrid({
    super.key,
    required this.children,
    this.minTileWidth = 150,
    this.spacing = Insets.md,
  });

  final List<Widget> children;
  final double minTileWidth;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final int columns =
            (constraints.maxWidth / minTileWidth).floor().clamp(2, 4).toInt();
        final double width =
            (constraints.maxWidth - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: children
              .map((Widget child) => SizedBox(width: width, child: child))
              .toList(growable: false),
        );
      },
    );
  }
}

/// Label ↔ value row for detail sheets.
class KeyValueRow extends StatelessWidget {
  const KeyValueRow({
    super.key,
    required this.label,
    required this.value,
    this.icon,
    this.valueColor,
    this.dense = false,
  });

  final String label;
  final String value;
  final IconData? icon;
  final Color? valueColor;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.symmetric(vertical: dense ? 5 : 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (icon != null) ...<Widget>[
            Icon(icon, size: 16, color: theme.textTheme.bodySmall?.color),
            const SizedBox(width: Insets.sm),
          ],
          Expanded(child: Text(label, style: theme.textTheme.bodyMedium)),
          const SizedBox(width: Insets.md),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: theme.textTheme.titleMedium?.copyWith(
                fontSize: 14,
                color: valueColor,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
