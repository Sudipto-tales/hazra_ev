import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import 'app_card.dart';

/// Shared empty / error / loading / offline states. Every list in the app is
/// expected to route through these instead of rendering a blank screen.

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Insets.xxl,
          vertical: Insets.xxxl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 30, color: AppColors.primary),
            ),
            const SizedBox(height: Insets.lg),
            Text(title, style: theme.textTheme.titleLarge),
            const SizedBox(height: Insets.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            if (actionLabel != null) ...<Widget>[
              const SizedBox(height: Insets.xl),
              FilledButton(
                onPressed: onAction,
                style: FilledButton.styleFrom(
                  minimumSize: const Size(180, Sizes.touchTarget),
                ),
                child: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.onRetry, this.message});

  final VoidCallback onRetry;
  final String? message;

  @override
  Widget build(BuildContext context) {
    return EmptyState(
      icon: Icons.cloud_off_rounded,
      title: 'Could not load',
      message: message ??
          'Check your connection and try again. Nothing you recorded has been lost.',
      actionLabel: 'Retry',
      onAction: onRetry,
    );
  }
}

/// Shimmer-free skeleton: cheap, calm, and works in both themes.
class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    super.key,
    this.width = double.infinity,
    this.height = 14,
    this.radius = Radii.sm,
  });

  final double width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final bool isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: isDark
            ? Colors.white.withValues(alpha: 0.06)
            : Colors.black.withValues(alpha: 0.05),
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

class LoadingCards extends StatelessWidget {
  const LoadingCards({super.key, this.count = 3, this.height = 96});

  final int count;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: List<Widget>.generate(
        count,
        (int i) => Padding(
          padding: const EdgeInsets.only(bottom: Insets.md),
          child: AppCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Row(
                  children: <Widget>[
                    SkeletonBox(width: 40, height: 40, radius: Radii.md),
                    SizedBox(width: Insets.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          SkeletonBox(width: 140, height: 13),
                          SizedBox(height: 8),
                          SkeletonBox(width: 90, height: 11),
                        ],
                      ),
                    ),
                  ],
                ),
                SizedBox(height: height * 0.18),
                const SkeletonBox(height: 11),
                const SizedBox(height: 8),
                const SkeletonBox(width: 200, height: 11),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Inline alert strip for degraded tracking / offline queue / permission loss.
class AlertBanner extends StatelessWidget {
  const AlertBanner({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.tone = AppColors.warning,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String message;
  final Color tone;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(Insets.md),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(Radii.md),
        border: Border.all(color: tone.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, size: 19, color: tone),
          const SizedBox(width: Insets.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(fontSize: 14),
                ),
                const SizedBox(height: 2),
                Text(message, style: theme.textTheme.bodySmall),
                if (actionLabel != null)
                  Padding(
                    padding: const EdgeInsets.only(top: Insets.sm),
                    child: SizedBox(
                      height: 34,
                      child: OutlinedButton(
                        onPressed: onAction,
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size(0, 34),
                          padding: const EdgeInsets.symmetric(
                            horizontal: Insets.md,
                          ),
                          foregroundColor: tone,
                          side: BorderSide(color: tone.withValues(alpha: 0.5)),
                        ),
                        child: Text(actionLabel!),
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
