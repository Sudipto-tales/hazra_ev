import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/stat_tile.dart';
import 'product_artwork.dart';
import 'product_widgets.dart';

/// Opened by tapping a product image on the report form.
///
/// Shows the full image set for the chosen colour, the colour row that swaps
/// that set, and the spec sheet — warranty, mileage, top speed, charging,
/// battery, motor, load. **No price**: field sellers log units and the payment
/// they received, pricing is not part of this surface.
///
/// Returns the colour the seller left it on, so picking a colour in here is the
/// same action as picking it on the card.
Future<String?> showProductDetailSheet(
  BuildContext context, {
  required Product product,
  required String colorName,
}) {
  return showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (BuildContext ctx) => _ProductDetailSheet(
      product: product,
      initialColor: colorName,
    ),
  );
}

class _ProductDetailSheet extends StatefulWidget {
  const _ProductDetailSheet({required this.product, required this.initialColor});

  final Product product;
  final String initialColor;

  @override
  State<_ProductDetailSheet> createState() => _ProductDetailSheetState();
}

class _ProductDetailSheetState extends State<_ProductDetailSheet> {
  late ProductColor _color = widget.product.colorByName(widget.initialColor);
  final PageController _pages = PageController();
  int _frame = 0;

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _pickColor(ProductColor c) {
    setState(() {
      _color = c;
      _frame = 0;
    });
    // A different colour is a different image set, so start it from the top.
    if (_pages.hasClients) _pages.jumpToPage(0);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Product p = widget.product;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.92,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  0,
                  Insets.lg,
                  Insets.lg,
                ),
                children: <Widget>[
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      WarrantyBadge(years: p.warrantyYears),
                      const Spacer(),
                      RatingStars(rating: p.rating, size: 16),
                    ],
                  ),
                  const SizedBox(height: Insets.md),

                  // The image set for the selected colour.
                  SizedBox(
                    height: 220,
                    child: PageView.builder(
                      controller: _pages,
                      itemCount: _color.imageCount,
                      onPageChanged: (int i) => setState(() => _frame = i),
                      itemBuilder: (BuildContext context, int i) => Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: ProductArtwork(
                          category: p.category,
                          argb: _color.argb,
                          variant: i,
                          height: 220,
                          radius: Radii.lg,
                          padding: Insets.lg,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: Insets.sm),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List<Widget>.generate(
                      _color.imageCount,
                      (int i) => Container(
                        width: i == _frame ? 18 : 6,
                        height: 6,
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        decoration: BoxDecoration(
                          color: i == _frame
                              ? AppColors.primary
                              : context.lineColor,
                          borderRadius: BorderRadius.circular(Radii.pill),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: Insets.md),
                  Text(
                    '${_color.name} · image ${_frame + 1} of '
                    '${_color.imageCount}',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall,
                  ),
                  const SizedBox(height: Insets.lg),

                  ColorSwatchRow(
                    colors: p.colors,
                    selectedName: _color.name,
                    onSelect: _pickColor,
                    diameter: 30,
                  ),
                  const SizedBox(height: Insets.lg),

                  Text(p.name, style: theme.textTheme.headlineSmall),
                  const SizedBox(height: 2),
                  Row(
                    children: <Widget>[
                      Icon(categoryIcon(p.category),
                          size: 15, color: AppColors.textSecondary),
                      const SizedBox(width: Insets.xs),
                      Text(
                        '${p.brand} · ${p.category.label} · ${p.modelCode}',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                  const SizedBox(height: Insets.lg),

                  if (p.rangeKm > 0 || p.topSpeedKmph > 0)
                    Wrap(
                      spacing: Insets.sm,
                      runSpacing: Insets.sm,
                      children: <Widget>[
                        if (p.rangeKm > 0)
                          SpecTile(
                            icon: Icons.route_rounded,
                            value: '${p.rangeKm} km',
                            label: 'RANGE',
                          ),
                        if (p.topSpeedKmph > 0)
                          SpecTile(
                            icon: Icons.speed_rounded,
                            value: '${p.topSpeedKmph} km/h',
                            label: p.topSpeedKmph >= 60
                                ? 'HIGH SPEED'
                                : 'TOP SPEED',
                          ),
                        SpecTile(
                          icon: Icons.bolt_rounded,
                          value: p.chargingTime.split(',').first.trim(),
                          label: 'CHARGING',
                        ),
                      ],
                    ),

                  const SectionHeader(
                    title: 'Specifications',
                    padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
                  ),
                  AppCard(
                    child: Column(
                      children: p.specSheet
                          .map(
                            (({String label, String value}) s) => KeyValueRow(
                              label: s.label,
                              value: s.value,
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ),

                  if (p.highlights.isNotEmpty) ...<Widget>[
                    const SectionHeader(
                      title: 'Highlights',
                      padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
                    ),
                    AppCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: p.highlights
                            .map(
                              (String h) => Padding(
                                padding: const EdgeInsets.only(bottom: Insets.sm),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: <Widget>[
                                    const Icon(
                                      Icons.check_circle_outline_rounded,
                                      size: 16,
                                      color: AppColors.success,
                                    ),
                                    const SizedBox(width: Insets.sm),
                                    Expanded(
                                      child: Text(
                                        h,
                                        style: theme.textTheme.bodyMedium,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            )
                            .toList(growable: false),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                Insets.lg,
                Insets.sm,
                Insets.lg,
                Insets.lg,
              ),
              child: SizedBox(
                width: double.infinity,
                height: Sizes.primaryActionHeight,
                child: FilledButton.icon(
                  onPressed: () => Navigator.pop(context, _color.name),
                  icon: const Icon(Icons.palette_outlined, size: 18),
                  label: Text('Use ${_color.name}'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
