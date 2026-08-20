import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../core/theme/theme_ext.dart';
import 'app_card.dart';

/// Expand/collapse block used to break a long form into sections.
///
/// The header stays readable while collapsed — [summary] carries whatever the
/// section already holds ("3 products · 6 units") so the seller can check the
/// form without opening every card. [complete] flips the leading icon to a tick
/// and [errorText] turns the border red, which is how a `Form` validation
/// failure inside a collapsed section stays visible.
class CollapsibleCard extends StatelessWidget {
  const CollapsibleCard({
    super.key,
    required this.icon,
    required this.title,
    required this.expanded,
    required this.onToggle,
    required this.child,
    this.subtitle,
    this.summary,
    this.tone = AppColors.primary,
    this.complete = false,
    this.errorText,
  });

  final IconData icon;
  final String title;
  final String? subtitle;

  /// Shown instead of [subtitle] while collapsed, when there is something to
  /// report.
  final String? summary;

  final bool expanded;
  final VoidCallback onToggle;
  final Widget child;
  final Color tone;
  final bool complete;
  final String? errorText;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool hasError = errorText != null;
    final String? line = expanded ? subtitle : (summary ?? subtitle);

    return AppCard(
      padding: EdgeInsets.zero,
      borderColor: hasError ? AppColors.danger : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          InkWell(
            onTap: onToggle,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: Insets.lg,
                vertical: Insets.md,
              ),
              child: Row(
                children: <Widget>[
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: tone.withValues(alpha: context.isDark ? 0.18 : 0.1),
                      borderRadius: BorderRadius.circular(Radii.sm),
                    ),
                    child: Icon(
                      complete ? Icons.check_rounded : icon,
                      size: 18,
                      color: complete ? AppColors.success : tone,
                    ),
                  ),
                  const SizedBox(width: Insets.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(title, style: theme.textTheme.titleMedium),
                        if (line != null) ...<Widget>[
                          const SizedBox(height: 2),
                          Text(
                            line,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall,
                          ),
                        ],
                      ],
                    ),
                  ),
                  AnimatedRotation(
                    turns: expanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: const Icon(Icons.expand_more_rounded, size: 22),
                  ),
                ],
              ),
            ),
          ),
          if (hasError && !expanded)
            Padding(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                0,
                Insets.lg,
                Insets.md,
              ),
              child: Text(
                errorText!,
                style: theme.textTheme.bodySmall
                    ?.copyWith(color: AppColors.danger),
              ),
            ),
          if (expanded) ...<Widget>[
            Divider(height: 1, color: context.lineColor),
            Padding(
              padding: const EdgeInsets.all(Insets.lg),
              child: child,
            ),
          ],
        ],
      ),
    );
  }
}
