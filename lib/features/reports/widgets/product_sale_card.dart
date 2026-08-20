import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import '../../../widgets/app_card.dart';
import 'product_artwork.dart';
import 'product_detail_sheet.dart';
import 'product_widgets.dart';

/// One selected product on the report form: image, warranty, rating, the colour
/// row that swaps the image, the headline specs and the units sold.
///
/// Tapping the image opens the full gallery + spec sheet
/// ([showProductDetailSheet]); whatever colour the seller leaves that sheet on
/// comes back here. Price is deliberately absent everywhere.
class ProductSaleCard extends StatelessWidget {
  const ProductSaleCard({
    super.key,
    required this.product,
    required this.line,
    required this.onChanged,
    required this.onRemove,
  });

  final Product product;
  final ProductSaleLine line;
  final ValueChanged<ProductSaleLine> onChanged;
  final VoidCallback onRemove;

  Future<void> _openGallery(BuildContext context) async {
    final String? picked = await showProductDetailSheet(
      context,
      product: product,
      colorName: line.colorName,
    );
    if (picked == null) return;
    final ProductColor c = product.colorByName(picked);
    onChanged(line.copyWith(colorName: c.name, colorArgb: c.argb));
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ProductColor color = product.colorByName(line.colorName);

    return AppCard(
      padding: const EdgeInsets.all(Insets.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              WarrantyBadge(years: product.warrantyYears, dense: true),
              const Spacer(),
              RatingStars(rating: product.rating),
              const SizedBox(width: Insets.xs),
              InkWell(
                onTap: onRemove,
                borderRadius: BorderRadius.circular(Radii.pill),
                child: const Padding(
                  padding: EdgeInsets.all(4),
                  child: Icon(Icons.close_rounded, size: 17),
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),

          // Tap target for the gallery. The hint matters — nothing else on the
          // form opens on an image tap.
          InkWell(
            onTap: () => _openGallery(context),
            borderRadius: BorderRadius.circular(Radii.md),
            child: Stack(
              children: <Widget>[
                ProductArtwork(
                  category: product.category,
                  argb: color.argb,
                  height: 148,
                ),
                Positioned(
                  right: Insets.sm,
                  bottom: Insets.sm,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Insets.sm,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.55),
                      borderRadius: BorderRadius.circular(Radii.pill),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        const Icon(Icons.photo_library_outlined,
                            size: 12, color: Colors.white),
                        const SizedBox(width: 4),
                        Text(
                          '${color.imageCount} photos · tap for details',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: Insets.md),

          ColorSwatchRow(
            colors: product.colors,
            selectedName: color.name,
            onSelect: (ProductColor c) =>
                onChanged(line.copyWith(colorName: c.name, colorArgb: c.argb)),
          ),
          const SizedBox(height: Insets.xs),
          Text(color.name, style: theme.textTheme.bodySmall),
          const SizedBox(height: Insets.md),

          Text(product.name, style: theme.textTheme.titleLarge),
          Text(
            '${product.brand} · ${product.category.label}',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: Insets.md),

          if (product.rangeKm > 0 || product.topSpeedKmph > 0)
            Wrap(
              spacing: Insets.sm,
              runSpacing: Insets.sm,
              children: <Widget>[
                if (product.rangeKm > 0)
                  SpecTile(
                    icon: Icons.route_rounded,
                    value: '${product.rangeKm} km',
                    label: 'RANGE',
                  ),
                if (product.topSpeedKmph > 0)
                  SpecTile(
                    icon: Icons.speed_rounded,
                    value: '${product.topSpeedKmph} km/h',
                    label: product.topSpeedKmph >= 60
                        ? 'HIGH SPEED'
                        : 'TOP SPEED',
                  ),
              ],
            ),
          const SizedBox(height: Insets.md),
          Divider(height: 1, color: context.lineColor),
          const SizedBox(height: Insets.sm),

          Row(
            children: <Widget>[
              const Icon(Icons.inventory_2_outlined,
                  size: 17, color: AppColors.primary),
              const SizedBox(width: Insets.sm),
              Expanded(
                child: Text('Units sold', style: theme.textTheme.bodyLarge),
              ),
              UnitStepper(
                value: line.units,
                onChanged: (int v) => onChanged(line.copyWith(units: v)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
