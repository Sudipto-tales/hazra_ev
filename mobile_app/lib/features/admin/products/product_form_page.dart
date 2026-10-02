import 'package:flutter/material.dart';

import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart';
import '../../../data/models/product_page_content.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/select_field.dart';
import 'product_color_editor.dart';
import 'product_editor_fields.dart';
import 'product_feature_editor.dart';

/// Product and website content are saved together using the shared catalogue API.
class ProductFormPage extends StatefulWidget {
  const ProductFormPage({super.key, this.product});
  final Product? product;
  @override
  State<ProductFormPage> createState() => _ProductFormPageState();
}

class _ProductFormPageState extends State<ProductFormPage> {
  final _form = GlobalKey<FormState>();
  late final _brand = TextEditingController(
    text: widget.product?.brand ?? 'Hazra EV',
  );
  late final _name = TextEditingController(text: widget.product?.name ?? '');
  late final _modelCode = TextEditingController(
    text: widget.product?.modelCode ?? '',
  );
  late final _slug = TextEditingController(text: widget.product?.slug ?? '');
  late final _range = TextEditingController(
    text: '${widget.product?.rangeKm ?? 100}',
  );
  late final _speed = TextEditingController(
    text: '${widget.product?.topSpeedKmph ?? 65}',
  );
  late final _battery = TextEditingController(
    text: widget.product?.batteryCapacity ?? '72V 30Ah',
  );
  late final _motor = TextEditingController(
    text: widget.product?.motorPower ?? '1200W',
  );
  late final _charging = TextEditingController(
    text: widget.product?.chargingTime ?? '',
  );
  late final _load = TextEditingController(
    text: widget.product == null ? '' : '${widget.product!.loadCapacityKg}',
  );
  late final _rating = TextEditingController(
    text: widget.product == null ? '' : '${widget.product!.rating}',
  );
  late final _warranty = TextEditingController(
    text: '${widget.product?.warrantyYears ?? 3}',
  );
  late final _warrantyNote = TextEditingController(
    text: widget.product?.warrantyNote ?? '3 Years Comprehensive Warranty',
  );
  late final _highlights = TextEditingController(
    text: (widget.product?.highlights ?? []).join('\n'),
  );
  late final _order = TextEditingController(
    text: '${widget.product?.featuredOrder ?? 0}',
  );
  late ProductCategory _category =
      widget.product?.category ?? ProductCategory.scooty;
  late bool _active = widget.product?.active ?? true;
  late bool _featured = widget.product?.isFeatured ?? false;
  late String _heroImage = widget.product?.heroImage ?? '';
  late final List<ProductColor> _colors = (widget.product?.colors ??
          [
            ProductColor(
              id: productEditorId(),
              name: 'Matte Black',
              argb: 0xff202020,
            ),
          ])
      .map(
        (c) => ProductColor(
          id: c.id ?? productEditorId(),
          position: c.position,
          name: c.name,
          argb: c.argb,
          inStock: c.inStock,
          imageUrls: List<String>.from(c.imageUrls),
        ),
      )
      .toList();
  late String? _defaultColorId = widget.product?.defaultColorId ??
      (_colors.isEmpty ? null : _colors.first.id);
  late List<ProductFeatureCard> _cards = [...?widget.product?.featureCards];
  late final Map<String, dynamic> _page = {
    ...productPageDefaults,
    ...?widget.product?.pageContent,
  };
  late final Map<String, TextEditingController> _pageText = {
    for (final key in productPageGroups.values.expand((keys) => keys))
      if (!key.startsWith('show_') && key != 'cinematic_image')
        key: TextEditingController(text: (_page[key] ?? '').toString()),
  };
  late final Set<String> _relatedIds = Set<String>.from(
    (_page['related_ids'] as List? ?? []).whereType<String>(),
  );
  List<Product> _relatedProducts = [];
  Object? _relatedError;
  bool _loadingRelated = false;
  bool _started = false;
  bool _busy = false;
  int _uploads = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _loadRelated();
    }
  }

  Future<void> _loadRelated() async {
    setState(() {
      _loadingRelated = true;
      _relatedError = null;
    });
    try {
      final products = await AppScope.of(context).adminRepository.products();
      if (mounted) {
        setState(
          () => _relatedProducts =
              products.where((p) => p.id != widget.product?.id).toList(),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _relatedError = error);
    } finally {
      if (mounted) setState(() => _loadingRelated = false);
    }
  }

  @override
  void dispose() {
    for (final controller in [
      _brand,
      _name,
      _modelCode,
      _slug,
      _range,
      _speed,
      _battery,
      _motor,
      _charging,
      _load,
      _rating,
      _warranty,
      _warrantyNote,
      _highlights,
      _order,
      ..._pageText.values,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _message(String message) => ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));

  void _uploading(bool uploading) {
    if (mounted) setState(() => _uploads += uploading ? 1 : -1);
  }

  Future<void> _editColor({int? index}) async {
    final result = await Navigator.of(context).push<ProductColor>(
      MaterialPageRoute(
        builder: (_) =>
            ProductColorEditor(color: index == null ? null : _colors[index]),
      ),
    );
    if (result == null || !mounted) return;
    if (_colors.asMap().entries.any(
          (e) =>
              e.key != index &&
              e.value.name.toLowerCase() == result.name.toLowerCase(),
        )) {
      _message('${result.name} is already on this product');
      return;
    }
    setState(() {
      if (index == null) {
        _colors.add(result);
      } else {
        _colors[index] = result;
      }
      _defaultColorId ??= result.id;
    });
  }

  Future<void> _removeColor(int index) async {
    final color = _colors[index];
    final affected = _cards.where((c) => c.colorId == color.id).length;
    final remove = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Remove ${color.name}?'),
        content: Text(
          affected == 0
              ? 'This removes the colour and its gallery from the product.'
              : '$affected feature card(s) will become shared across all colours.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (remove != true || !mounted) return;
    setState(() {
      _colors.removeAt(index);
      _cards =
          _cards.map((c) => c.colorId == color.id ? c.shared() : c).toList();
      if (_defaultColorId == color.id) {
        _defaultColorId = _colors.isEmpty ? null : _colors.first.id;
      }
    });
  }

  Future<void> _editCard({int? index}) async {
    final result = await Navigator.of(context).push<ProductFeatureCard>(
      MaterialPageRoute(
        builder: (_) => ProductFeatureEditor(
          card: index == null ? null : _cards[index],
          colors: _colors,
        ),
      ),
    );
    if (result == null || !mounted) return;
    setState(() {
      if (index == null) {
        _cards.add(result);
      } else {
        _cards[index] = result;
      }
    });
  }

  void _move<T>(List<T> items, int index, int direction) => setState(() {
        final item = items.removeAt(index);
        items.insert(index + direction, item);
      });

  Future<void> _save() async {
    if (_busy || _uploads > 0) return;
    if (!_form.currentState!.validate()) {
      _message(
        'Check the highlighted fields, including the page content sections.',
      );
      return;
    }
    if (_colors.isEmpty) {
      _message('Add at least one colour');
      return;
    }
    if (_colors.length > 40 || _cards.length > 40) {
      _message('Use up to 40 colours and 40 feature cards');
      return;
    }
    final highlights = _highlights.text
        .split('\n')
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();
    if (highlights.length > 100 ||
        highlights.any((s) => productTextLimit(s, 500) != null)) {
      _message('Use up to 100 highlights, with at most 500 bytes each');
      return;
    }
    final page = <String, dynamic>{
      ..._page,
      for (final entry in _pageText.entries) entry.key: entry.value.text,
      'related_ids': _relatedIds.toList(),
    };
    final fallback = _heroImage.trim().isNotEmpty
        ? _heroImage.trim()
        : (_colors.first.imageUrls.isEmpty
            ? ''
            : _colors.first.imageUrls.first);
    setState(() => _busy = true);
    try {
      await AppScope.of(context).adminRepository.saveProduct(
            ProductDraft(
              id: widget.product?.id,
              category: _category,
              brand: _brand.text.trim(),
              name: _name.text.trim(),
              modelCode: _modelCode.text.trim(),
              slug: _slug.text.trim(),
              heroImage: fallback,
              isFeatured: _featured,
              featuredOrder: productWholeNumberValue(_order.text),
              active: _active,
              defaultColorId: _defaultColorId ?? _colors.first.id,
              rating: double.tryParse(_rating.text.trim()) ?? 0,
              warrantyYears: productWholeNumberValue(_warranty.text),
              warrantyNote: _warrantyNote.text.trim(),
              rangeKm: productWholeNumberValue(_range.text),
              topSpeedKmph: productWholeNumberValue(_speed.text),
              chargingTime: _charging.text.trim(),
              batteryCapacity: _battery.text.trim(),
              motorPower: _motor.text.trim(),
              loadCapacityKg: productWholeNumberValue(_load.text),
              colors: List<ProductColor>.from(_colors),
              highlights: highlights,
              featureCards: List<ProductFeatureCard>.from(_cards),
              pageContent: page,
            ),
          );
      if (!mounted) return;
      _message(widget.product == null ? 'Product created' : 'Product updated');
      Navigator.pop(context, true);
    } catch (error) {
      if (mounted) _message(productEditorError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _section(String title, List<Widget> children) => Padding(
        padding: const EdgeInsets.only(bottom: 20),
        child: AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              ...children,
            ],
          ),
        ),
      );

  Widget _number(String label, TextEditingController controller) =>
      ProductEditorText(
        label: label,
        controller: controller,
        number: true,
        validator: productWholeNumber,
      );

  Widget _ordering<T>(
    List<T> items,
    int index,
    VoidCallback remove, {
    required String label,
  }) =>
      Row(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          IconButton(
            tooltip: 'Move $label up',
            icon: const Icon(Icons.arrow_upward),
            onPressed: index == 0 ? null : () => _move(items, index, -1),
          ),
          IconButton(
            tooltip: 'Move $label down',
            icon: const Icon(Icons.arrow_downward),
            onPressed:
                index == items.length - 1 ? null : () => _move(items, index, 1),
          ),
          IconButton(
            tooltip: 'Remove $label',
            icon: const Icon(Icons.delete_outline),
            onPressed: remove,
          ),
        ],
      );

  Widget _pageGroup(MapEntry<String, List<String>> group) => ExpansionTile(
        key: ValueKey(group.key),
        title: Text(group.key),
        maintainState: true,
        children: group.value.map((key) {
          final words =
              key.replaceAll('_', ' ').replaceAll('cta', 'button label');
          final label = '${words[0].toUpperCase()}${words.substring(1)}';
          if (key.startsWith('show_')) {
            return SwitchListTile(
              title: Text(label),
              value: _page[key] == true,
              onChanged: (v) => setState(() => _page[key] = v),
            );
          }
          if (key == 'cinematic_image') {
            return ProductImageInput(
              label: 'Banner image',
              value: (_page[key] ?? '').toString(),
              onChanged: (v) => setState(() => _page[key] = v),
              onUploading: _uploading,
            );
          }
          return ProductEditorText(
            label: label,
            controller: _pageText[key],
            lines: RegExp('title|description|note').hasMatch(key) ? 3 : 1,
            validator: (v) => key == 'cinematic_link'
                ? productUrl(v, allowAnchor: true)
                : productTextLimit(v, 5000),
          );
        }).toList(),
      );

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: !_busy && _uploads == 0,
        child: Scaffold(
          appBar: AppBar(
            title:
                Text(widget.product == null ? 'Add product' : 'Edit product'),
          ),
          bottomNavigationBar: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(Insets.lg),
              child: FilledButton(
                onPressed: _busy || _uploads > 0 ? null : _save,
                child: _busy
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(_uploads > 0 ? 'Uploading photos…' : 'Save product'),
              ),
            ),
          ),
          body: AbsorbPointer(
            absorbing: _busy || _uploads > 0,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(Insets.lg),
              child: Form(
                key: _form,
                child: Column(
                  children: [
                    _section('Product information', [
                      ProductEditorText(
                        label: 'Product name',
                        controller: _name,
                        validator: (v) =>
                            (v ?? '').trim().isEmpty ? 'Required' : null,
                      ),
                      ProductEditorText(
                        label: 'Brand',
                        controller: _brand,
                        validator: (v) =>
                            (v ?? '').trim().isEmpty ? 'Required' : null,
                      ),
                      ProductEditorText(
                        label: 'Model code',
                        controller: _modelCode,
                        validator: (v) =>
                            (v ?? '').trim().isEmpty ? 'Required' : null,
                      ),
                      ProductEditorText(
                        label: 'URL slug',
                        controller: _slug,
                        validator: (v) => (v ?? '').trim().isEmpty ||
                                RegExp(
                                  r'^[a-z0-9]+(?:-[a-z0-9]+)*$',
                                ).hasMatch(v!.trim())
                            ? null
                            : 'Use lowercase letters, digits and hyphens',
                      ),
                      SelectField<ProductCategory>(
                        label: 'Category',
                        hint: 'Pick a category',
                        value: _category,
                        options: ProductCategory.values
                            .map((c) => SelectOption(value: c, label: c.label))
                            .toList(),
                        onChanged: (v) =>
                            setState(() => _category = v ?? _category),
                      ),
                      const SizedBox(height: 16),
                      ProductEditorText(
                        label: 'Highlights (one per line)',
                        controller: _highlights,
                        lines: 4,
                      ),
                      ProductImageInput(
                        label: 'Hero / fallback image',
                        value: _heroImage,
                        onChanged: (v) => setState(() => _heroImage = v),
                        onUploading: _uploading,
                      ),
                      SwitchListTile(
                        contentPadding: EdgeInsets.zero,
                        title:
                            const Text('Featured product (show on homepage)'),
                        value: _featured,
                        onChanged: (v) => setState(() => _featured = v),
                      ),
                      _number('Featured display order', _order),
                      SelectField<bool>(
                        label: 'Status',
                        hint: 'Publication status',
                        value: _active,
                        options: const [
                          SelectOption(value: true, label: 'Published'),
                          SelectOption(value: false, label: 'Hidden'),
                        ],
                        onChanged: (v) =>
                            setState(() => _active = v ?? _active),
                      ),
                    ]),
                    _section('Colours & galleries', [
                      if (_colors.isNotEmpty)
                        SelectField<String>(
                          label: 'Default colour',
                          hint: 'Choose a colour',
                          value: _defaultColorId,
                          options: _colors
                              .map((c) =>
                                  SelectOption(value: c.id!, label: c.name))
                              .toList(),
                          onChanged: (v) => setState(() => _defaultColorId = v),
                        ),
                      const SizedBox(height: 12),
                      ..._colors.asMap().entries.map(
                            (e) => Column(
                              children: [
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  leading: CircleAvatar(
                                    backgroundColor: Color(e.value.argb),
                                  ),
                                  title: Text(e.value.name),
                                  subtitle: Text(
                                    '${e.value.imageUrls.length} photos · ${e.value.inStock ? 'In stock' : 'Out of stock'}',
                                  ),
                                  trailing: const Icon(Icons.edit_outlined),
                                  onTap: () => _editColor(index: e.key),
                                ),
                                _ordering(
                                  _colors,
                                  e.key,
                                  () => _removeColor(e.key),
                                  label: 'colour',
                                ),
                                const Divider(),
                              ],
                            ),
                          ),
                      OutlinedButton.icon(
                        onPressed:
                            _colors.length >= 40 ? null : () => _editColor(),
                        icon: const Icon(Icons.add),
                        label: const Text('Add colour'),
                      ),
                    ]),
                    _section('Feature cards', [
                      const Text(
                        'Shared cards appear for every colour. Select a colour to show a card only for that colour.',
                      ),
                      ..._cards.asMap().entries.map(
                            (e) => Column(
                              children: [
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text(e.value.title),
                                  subtitle: Text(
                                    e.value.visible ? 'Visible' : 'Hidden',
                                  ),
                                  trailing: const Icon(Icons.edit_outlined),
                                  onTap: () => _editCard(index: e.key),
                                ),
                                _ordering(
                                  _cards,
                                  e.key,
                                  () => setState(() => _cards.removeAt(e.key)),
                                  label: 'feature card',
                                ),
                                const Divider(),
                              ],
                            ),
                          ),
                      OutlinedButton.icon(
                        onPressed:
                            _cards.length >= 40 ? null : () => _editCard(),
                        icon: const Icon(Icons.add),
                        label: const Text('Add feature card'),
                      ),
                    ]),
                    _section('Page content', [
                      const Text(
                        'Use {name}, {brand}, and {model} to insert product information. New lines in headings create line breaks.',
                      ),
                      ...productPageGroups.entries.map(_pageGroup),
                      const SizedBox(height: 16),
                      const Text(
                        'Related models (leave empty for automatic selection; up to 12)',
                      ),
                      if (_loadingRelated) const LinearProgressIndicator(),
                      if (_relatedError != null)
                        Column(
                          children: [
                            const Text(
                              'Could not load related models. Your existing selection is preserved.',
                            ),
                            TextButton(
                              onPressed: _loadRelated,
                              child: const Text('Retry'),
                            ),
                          ],
                        ),
                      ..._relatedProducts.map(
                        (p) => CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(p.displayName),
                          value: _relatedIds.contains(p.id),
                          onChanged: (selected) {
                            if (selected == true && _relatedIds.length >= 12) {
                              _message('Choose up to 12 related models');
                              return;
                            }
                            setState(() {
                              selected == true
                                  ? _relatedIds.add(p.id)
                                  : _relatedIds.remove(p.id);
                            });
                          },
                        ),
                      ),
                    ]),
                    _section('Specifications & warranty', [
                      _number('Range (km)', _range),
                      _number('Top speed (km/h)', _speed),
                      ProductEditorText(
                        label: 'Battery capacity',
                        controller: _battery,
                      ),
                      ProductEditorText(
                          label: 'Motor power', controller: _motor),
                      ProductEditorText(
                        label: 'Charging time',
                        controller: _charging,
                      ),
                      _number('Payload capacity (kg)', _load),
                      ProductEditorText(
                        label: 'Product rating (0 to 5)',
                        controller: _rating,
                        number: true,
                        validator: (v) {
                          if ((v ?? '').trim().isEmpty) return null;
                          final rating = double.tryParse(v!.trim());
                          return rating == null ||
                                  !rating.isFinite ||
                                  rating < 0 ||
                                  rating > 5
                              ? 'Use a rating between 0 and 5'
                              : null;
                        },
                      ),
                      _number('Warranty (years)', _warranty),
                      ProductEditorText(
                        label: 'Warranty note',
                        controller: _warrantyNote,
                        lines: 2,
                      ),
                    ]),
                    const Text(
                      'Photos upload from your gallery or camera. Save product applies product details, website content and galleries together.',
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
}
