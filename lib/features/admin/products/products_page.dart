import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../data/repositories/admin_repository.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/states.dart';
import '../../reports/widgets/product_artwork.dart';
import '../../reports/widgets/product_detail_sheet.dart';
import '../../reports/widgets/product_widgets.dart';
import 'product_form_page.dart';

/// The catalogue the admin owns.
///
/// Whatever is listed here is what the field team can log on a report, so the
/// list shows delisted products too — greyed, with the switch off — rather than
/// hiding them.
class ProductsPage extends StatefulWidget {
  const ProductsPage({super.key});

  @override
  State<ProductsPage> createState() => _ProductsPageState();
}

class _ProductsPageState extends State<ProductsPage> {
  final TextEditingController _search = TextEditingController();

  bool _loading = true;
  Object? _error;
  List<Product> _products = const <Product>[];
  Set<String> _delisted = const <String>{};
  ProductCategory? _category;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final AdminRepository repo = AppScope.of(context).adminRepository;
      final List<Product> rows = await repo.products(
        category: _category,
        query: _search.text,
      );
      final Set<String> delisted = await repo.delistedProductIds();
      if (!mounted) return;
      setState(() {
        _products = rows;
        _delisted = delisted;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  Future<void> _openForm({Product? product}) async {
    final bool? saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => ProductFormPage(product: product),
      ),
    );
    if (saved == true && mounted) _load();
  }

  Future<void> _setActive(Product p, bool active) async {
    await AppScope.of(context).adminRepository.setProductActive(p.id, active);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          active
              ? '${p.displayName} is listed again'
              : '${p.displayName} delisted — sellers can no longer log it',
        ),
      ),
    );
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Products'),
        actions: <Widget>[
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add_rounded),
        label: const Text('List product'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Insets.lg,
              Insets.sm,
              Insets.lg,
              Insets.sm,
            ),
            child: Column(
              children: <Widget>[
                TextField(
                  controller: _search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    hintText: 'Search model or code',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: _search.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _search.clear();
                              _load();
                            },
                            icon: const Icon(Icons.close_rounded, size: 19),
                          ),
                  ),
                ),
                const SizedBox(height: Insets.md),
                SizedBox(
                  height: 36,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: <Widget>[
                      Padding(
                        padding: const EdgeInsets.only(right: Insets.sm),
                        child: ChoiceChip(
                          label: const Text('All'),
                          selected: _category == null,
                          onSelected: (_) {
                            setState(() => _category = null);
                            _load();
                          },
                        ),
                      ),
                      ...ProductCategory.values.map(
                        (ProductCategory c) => Padding(
                          padding: const EdgeInsets.only(right: Insets.sm),
                          child: ChoiceChip(
                            label: Text(c.label),
                            selected: _category == c,
                            onSelected: (_) {
                              setState(() => _category = c);
                              _load();
                            },
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  Insets.sm,
                  Insets.lg,
                  96,
                ),
                children: <Widget>[
                  if (_loading)
                    const LoadingCards(count: 3)
                  else if (_error != null)
                    ErrorState(onRetry: _load)
                  else if (_products.isEmpty)
                    EmptyState(
                      icon: Icons.inventory_2_outlined,
                      title: 'Nothing listed',
                      message: _search.text.isEmpty && _category == null
                          ? 'List a product and the field team can start logging '
                              'it on their reports.'
                          : 'No product matches this filter.',
                      actionLabel: 'List product',
                      onAction: () => _openForm(),
                    )
                  else ...<Widget>[
                    Text(
                      '${_products.length} product'
                      '${_products.length == 1 ? '' : 's'}'
                      '${_category == null ? '' : ' in ${_category!.label}'}',
                      style: theme.textTheme.bodySmall,
                    ),
                    const SizedBox(height: Insets.md),
                    ..._products.map(
                      (Product p) => Padding(
                        padding: const EdgeInsets.only(bottom: Insets.md),
                        child: _ProductRow(
                          product: p,
                          active: !_delisted.contains(p.id),
                          onEdit: () => _openForm(product: p),
                          onPreview: () => showProductDetailSheet(
                            context,
                            product: p,
                            colorName: p.colors.first.name,
                          ),
                          onActiveChanged: (bool v) => _setActive(p, v),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProductRow extends StatelessWidget {
  const _ProductRow({
    required this.product,
    required this.active,
    required this.onEdit,
    required this.onPreview,
    required this.onActiveChanged,
  });

  final Product product;

  /// Listing state. [Product] carries no `active` flag — that is admin-only, so
  /// it arrives separately from `delistedProductIds()` and is handed down here.
  final bool active;

  final VoidCallback onEdit;
  final VoidCallback onPreview;
  final ValueChanged<bool> onActiveChanged;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ProductColor first = product.colors.first;

    final Widget card = AppCard(
      onTap: onEdit,
      padding: const EdgeInsets.all(Insets.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SizedBox(
                width: 92,
                child: InkWell(
                  onTap: onPreview,
                  borderRadius: BorderRadius.circular(Radii.sm),
                  child: ProductArtwork(
                    category: product.category,
                    argb: first.argb,
                    height: 62,
                    padding: Insets.xs,
                    radius: Radii.sm,
                  ),
                ),
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Flexible(
                          child: Text(
                            product.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        if (product.isNew) ...<Widget>[
                          const SizedBox(width: Insets.sm),
                          const NewPill(),
                        ],
                      ],
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: <Widget>[
                        Icon(
                          categoryIcon(product.category),
                          size: 13,
                          color: theme.textTheme.bodySmall?.color,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            '${product.brand} · ${product.modelCode}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Insets.xs),
                    RatingStars(rating: product.rating, showLabel: false),
                  ],
                ),
              ),
              WarrantyBadge(years: product.warrantyYears, dense: true),
            ],
          ),
          const SizedBox(height: Insets.md),

          // The colour set is the thing the admin most often comes here to
          // check, so it is on the row rather than one tap deep.
          Row(
            children: <Widget>[
              Text(
                '${product.colors.length} colour'
                '${product.colors.length == 1 ? '' : 's'}',
                style: theme.textTheme.labelSmall,
              ),
              const SizedBox(width: Insets.sm),
              Expanded(
                child: Wrap(
                  spacing: Insets.xs,
                  runSpacing: Insets.xs,
                  children: product.colors
                      .map(
                        (ProductColor c) => Tooltip(
                          message: c.inStock ? c.name : '${c.name} — no stock',
                          child: Container(
                            width: 18,
                            height: 18,
                            decoration: BoxDecoration(
                              color: Color(c.argb),
                              shape: BoxShape.circle,
                              border: Border.all(color: context.lineColor),
                            ),
                            child: c.inStock
                                ? null
                                : const Icon(
                                    Icons.block_rounded,
                                    size: 11,
                                    color: AppColors.danger,
                                  ),
                          ),
                        ),
                      )
                      .toList(growable: false),
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),
          Divider(height: 1, color: context.lineColor),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  product.listedAt == null
                      ? 'Base catalogue'
                      : 'Listed ${Fmt.relative(product.listedAt!)}',
                  style: theme.textTheme.bodySmall,
                ),
              ),
              Text(
                active ? 'Listed' : 'Delisted',
                style: theme.textTheme.labelSmall,
              ),
              Switch(value: active, onChanged: onActiveChanged),
            ],
          ),
        ],
      ),
    );

    // Delisted products stay on the list — hiding them would leave no way to
    // put one back — but they read as switched off.
    return active ? card : Opacity(opacity: 0.55, child: card);
  }
}

/// "NEW" flag, shared by the admin list and the report form's model picker.
class NewPill extends StatelessWidget {
  const NewPill({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
      decoration: BoxDecoration(
        color: AppColors.success,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: const Text(
        'NEW',
        style: TextStyle(
          color: Colors.white,
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.5,
        ),
      ),
    );
  }
}
