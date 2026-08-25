import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';

/// Five-star input. [value] of 0 means nothing picked yet.
///
/// Stars are laid out at full touch-target height rather than as icons scaled
/// to look pretty — this is tapped at the end of a long day, often one-handed.
class RatingBar extends StatelessWidget {
  const RatingBar({
    super.key,
    required this.value,
    required this.onChanged,
    this.showError = false,
  });

  final int value;
  final ValueChanged<int> onChanged;

  /// Draws the prompt in the error colour after a failed submit.
  final bool showError;

  static const List<String> _labels = <String>[
    'Poor',
    'Below par',
    'OK',
    'Good',
    'Great',
  ];

  static String labelFor(int rating) =>
      rating >= 1 && rating <= 5 ? _labels[rating - 1] : '';

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            for (int i = 1; i <= 5; i++)
              Expanded(
                child: Semantics(
                  button: true,
                  label: '$i out of 5 — ${_labels[i - 1]}',
                  selected: value == i,
                  child: InkWell(
                    onTap: () => onChanged(i),
                    borderRadius: BorderRadius.circular(Radii.md),
                    child: SizedBox(
                      height: Sizes.touchTarget,
                      child: Icon(
                        i <= value
                            ? Icons.star_rounded
                            : Icons.star_outline_rounded,
                        size: 34,
                        color: i <= value
                            ? AppColors.warning
                            : (showError
                                ? AppColors.danger.withValues(alpha: 0.55)
                                : AppColors.textTertiary),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          value == 0
              ? (showError ? 'Pick a rating to continue' : 'Tap to rate the day')
              : labelFor(value),
          style: theme.textTheme.bodySmall?.copyWith(
            color: value == 0 && showError ? AppColors.danger : null,
            fontWeight: value == 0 ? FontWeight.w400 : FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
