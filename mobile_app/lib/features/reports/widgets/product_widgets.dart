import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';

/// "4 YEARS WARRANTY / WARRANTY POLICY" plate.
class WarrantyBadge extends StatelessWidget {
  const WarrantyBadge({super.key, required this.years, this.dense = false});

  final int years;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: dense ? Insets.sm : Insets.md,
        vertical: dense ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: AppColors.success,
        borderRadius: BorderRadius.circular(Radii.sm),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(
            Icons.verified_user_rounded,
            size: dense ? 13 : 16,
            color: Colors.white,
          ),
          const SizedBox(width: Insets.xs),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                '$years YEAR${years == 1 ? '' : 'S'} WARRANTY',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: dense ? 9.5 : 11,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                  letterSpacing: 0.3,
                ),
              ),
              if (!dense)
                const Text(
                  'WARRANTY POLICY',
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 8.5,
                    fontWeight: FontWeight.w600,
                    height: 1.3,
                    letterSpacing: 0.6,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Five-star row with a half-star step, labelled like the catalogue card.
class RatingStars extends StatelessWidget {
  const RatingStars({
    super.key,
    required this.rating,
    this.showLabel = true,
    this.size = 14,
  });

  final double rating;
  final bool showLabel;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: <Widget>[
        if (showLabel)
          Text(
            'Rating',
            style: Theme.of(context)
                .textTheme
                .labelSmall
                ?.copyWith(fontSize: 9.5, letterSpacing: 0.4),
          ),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: List<Widget>.generate(5, (int i) {
            final double fill = rating - i;
            return Icon(
              fill >= 1
                  ? Icons.star_rounded
                  : fill >= 0.5
                      ? Icons.star_half_rounded
                      : Icons.star_outline_rounded,
              size: size,
              color: AppColors.warning,
            );
          }),
        ),
      ],
    );
  }
}

/// One of the "100 km RANGE" / "HIGH SPEED" tiles from the catalogue card.
class SpecTile extends StatelessWidget {
  const SpecTile({
    super.key,
    required this.icon,
    required this.value,
    required this.label,
  });

  final IconData icon;
  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Insets.md,
        vertical: Insets.sm,
      ),
      decoration: BoxDecoration(
        color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(Radii.sm),
        border: Border.all(color: context.lineColor),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 17, color: AppColors.primary),
          const SizedBox(width: Insets.sm),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                value,
                style: theme.textTheme.titleMedium?.copyWith(fontSize: 13.5),
              ),
              Text(
                label,
                style: theme.textTheme.labelSmall?.copyWith(
                  fontSize: 8.5,
                  letterSpacing: 0.6,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// "Available Colors" swatch row. Out-of-stock colours are struck through and
/// cannot be picked — the seller should not log a colour the warehouse cannot
/// ship.
class ColorSwatchRow extends StatelessWidget {
  const ColorSwatchRow({
    super.key,
    required this.colors,
    required this.selectedName,
    required this.onSelect,
    this.title = 'Available Colors',
    this.diameter = 26,
  });

  final List<ProductColor> colors;
  final String selectedName;
  final ValueChanged<ProductColor> onSelect;
  final String title;
  final double diameter;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.4),
        ),
        const SizedBox(height: Insets.sm),
        Wrap(
          spacing: Insets.sm,
          runSpacing: Insets.sm,
          children: colors.map((ProductColor c) {
            final bool selected = c.name == selectedName;
            return Tooltip(
              message: c.inStock ? c.name : '${c.name} — out of stock',
              child: InkWell(
                onTap: c.inStock ? () => onSelect(c) : null,
                borderRadius: BorderRadius.circular(Radii.pill),
                child: Container(
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: selected ? AppColors.primary : Colors.transparent,
                      width: 2,
                    ),
                  ),
                  child: Container(
                    width: diameter,
                    height: diameter,
                    decoration: BoxDecoration(
                      color: Color(c.argb),
                      shape: BoxShape.circle,
                      border: Border.all(color: context.lineColor),
                    ),
                    child: c.inStock
                        ? null
                        : Icon(
                            Icons.block_rounded,
                            size: diameter * 0.6,
                            color: AppColors.danger.withValues(alpha: 0.8),
                          ),
                  ),
                ),
              ),
            );
          }).toList(growable: false),
        ),
      ],
    );
  }
}

/// Category glyph shared by the picker, the card and the sheet.
IconData categoryIcon(ProductCategory category) => switch (category) {
      ProductCategory.scooty => Icons.electric_moped_rounded,
      ProductCategory.bike => Icons.electric_bike_rounded,
      ProductCategory.bicycle => Icons.pedal_bike_rounded,
      ProductCategory.others => Icons.ev_station_rounded,
    };

/// Small ± stepper used for units sold.
class UnitStepper extends StatelessWidget {
  const UnitStepper({
    super.key,
    required this.value,
    required this.onChanged,
    this.min = 1,
    this.max = 99,
  });

  final int value;
  final ValueChanged<int> onChanged;
  final int min;
  final int max;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      decoration: BoxDecoration(
        color: context.isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        borderRadius: BorderRadius.circular(Radii.pill),
        border: Border.all(color: context.lineColor),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: value > min ? () => onChanged(value - 1) : null,
            icon: const Icon(Icons.remove_rounded, size: 18),
            tooltip: 'One less',
          ),
          SizedBox(
            width: 26,
            child: Text(
              '$value',
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium,
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: value < max ? () => onChanged(value + 1) : null,
            icon: const Icon(Icons.add_rounded, size: 18),
            tooltip: 'One more',
          ),
        ],
      ),
    );
  }
}
