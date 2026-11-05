import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import 'image_tile.dart';
import 'product_artwork.dart';

/// One sold model on a report: photograph, name, colour swatch, unit count.
///
/// Replaces the `KeyValueRow` the admin screen used to render, which was
/// `"City Rider · Crimson" → "× 2"` as plain text. The photograph is the thing
/// an admin actually scans a sold-units list for, and until
/// `Present::saleLine` carried `imageUrl` there was nothing to draw — the
/// employee-side card could reach the live catalogue for it, the admin's could
/// not.
///
/// Falls back to the painted [ProductArtwork] when [ProductSaleLine.imageUrl]
/// is null, which covers both "the catalogue has no photo for this colour" and
/// "the server predates the field".
class SaleLineRow extends StatelessWidget {
  const SaleLineRow({super.key, required this.line, this.dense = false});

  final ProductSaleLine line;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final double side = dense ? 48 : 60;
    final String? url = ReportImageTile.resolve(line.imageUrl);

    return Padding(
      padding: EdgeInsets.symmetric(vertical: dense ? 5 : 7),
      child: Row(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(Radii.sm),
            child: SizedBox(
              width: side,
              height: side,
              child: url == null
                  ? ProductArtwork(
                      category: line.category,
                      argb: line.colorArgb,
                      height: side,
                      padding: 4,
                      radius: Radii.sm,
                    )
                  : Container(
                      color: context.isDark
                          ? AppColors.surfaceAltDark
                          : AppColors.surfaceAlt,
                      child: Image.network(
                        url,
                        fit: BoxFit.cover,
                        cacheWidth: (side *
                                MediaQuery.devicePixelRatioOf(context))
                            .round(),
                        // A catalogue photo that 404s must not leave a hole:
                        // drop back to the painted silhouette, which always
                        // renders and is still the right category and colour.
                        errorBuilder: (_, __, ___) => ProductArtwork(
                          category: line.category,
                          argb: line.colorArgb,
                          height: side,
                          padding: 4,
                          radius: Radii.sm,
                        ),
                      ),
                    ),
            ),
          ),
          const SizedBox(width: Insets.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  line.productName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyLarge
                      ?.copyWith(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 3),
                Row(
                  children: <Widget>[
                    // The swatch is the sold colour as an actual colour, which
                    // a name alone cannot convey across three shades of blue.
                    Container(
                      width: 11,
                      height: 11,
                      decoration: BoxDecoration(
                        color: Color(line.colorArgb),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.black.withValues(alpha: 0.15),
                        ),
                      ),
                    ),
                    const SizedBox(width: 5),
                    Flexible(
                      child: Text(
                        line.colorName.isEmpty ? '—' : line.colorName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: Insets.sm),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(Radii.pill),
            ),
            child: Text(
              '× ${line.units}',
              style: const TextStyle(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
