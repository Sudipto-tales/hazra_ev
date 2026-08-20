import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/theme/theme_ext.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/select_field.dart';
import '../../reports/widgets/product_artwork.dart';
import '../../reports/widgets/product_widgets.dart';

/// List a product or edit a listed one. A null [product] means create, the same
/// way [ProductDraft] treats a null id.
///
/// Saving pops `true` so the list behind it reloads. Every field here is a
/// field the field team sees on the report form or in the spec sheet — there is
/// **no price input**, because there is no price anywhere in this build.
class ProductFormPage extends StatefulWidget {
  const ProductFormPage({super.key, this.product});

  final Product? product;

  @override
  State<ProductFormPage> createState() => _ProductFormPageState();
}

class _ProductFormPageState extends State<ProductFormPage> {
  final GlobalKey<FormState> _form = GlobalKey<FormState>();

  late final TextEditingController _brand =
      TextEditingController(text: widget.product?.brand ?? '');
  late final TextEditingController _name =
      TextEditingController(text: widget.product?.name ?? '');
  late final TextEditingController _modelCode =
      TextEditingController(text: widget.product?.modelCode ?? '');
  late final TextEditingController _warrantyNote =
      TextEditingController(text: widget.product?.warrantyNote ?? '');
  late final TextEditingController _range = TextEditingController(
    text: widget.product == null ? '' : '${widget.product!.rangeKm}',
  );
  late final TextEditingController _topSpeed = TextEditingController(
    text: widget.product == null ? '' : '${widget.product!.topSpeedKmph}',
  );
  late final TextEditingController _charging =
      TextEditingController(text: widget.product?.chargingTime ?? '');
  late final TextEditingController _battery =
      TextEditingController(text: widget.product?.batteryCapacity ?? '');
  late final TextEditingController _motor =
      TextEditingController(text: widget.product?.motorPower ?? '');
  late final TextEditingController _load = TextEditingController(
    text: widget.product == null ? '' : '${widget.product!.loadCapacityKg}',
  );

  /// One highlight per line — a list field would be four taps to enter three
  /// bullet points.
  late final TextEditingController _highlights = TextEditingController(
    text: (widget.product?.highlights ?? const <String>[]).join('\n'),
  );

  late ProductCategory _category =
      widget.product?.category ?? ProductCategory.scooty;
  late double _rating = widget.product?.rating ?? 4.5;
  late int _warrantyYears = widget.product?.warrantyYears ?? 2;
  late List<ProductColor> _colors = <ProductColor>[
    ...?widget.product?.colors,
  ];

  bool _busy = false;

  bool get _isCreate => widget.product == null;

  @override
  void dispose() {
    _brand.dispose();
    _name.dispose();
    _modelCode.dispose();
    _warrantyNote.dispose();
    _range.dispose();
    _topSpeed.dispose();
    _charging.dispose();
    _battery.dispose();
    _motor.dispose();
    _load.dispose();
    _highlights.dispose();
    super.dispose();
  }

  void _complain(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  Future<void> _editColor({ProductColor? existing, int? index}) async {
    final ProductColor? result = await showModalBottomSheet<ProductColor>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (BuildContext ctx) => _ColorEditorSheet(color: existing),
    );
    if (result == null || !mounted) return;

    // Colour names are the key a sale line is written against, so two colours
    // of one product may not share a name.
    final bool clash = _colors.asMap().entries.any(
          (MapEntry<int, ProductColor> e) =>
              e.key != index &&
              e.value.name.toLowerCase() == result.name.toLowerCase(),
        );
    if (clash) {
      _complain('${result.name} is already on this product');
      return;
    }

    setState(() {
      if (index == null) {
        _colors = <ProductColor>[..._colors, result];
      } else {
        _colors = <ProductColor>[..._colors]..[index] = result;
      }
    });
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    if (_colors.isEmpty) {
      _complain('Add at least one colour — the gallery is per colour');
      return;
    }
    if (!_colors.any((ProductColor c) => c.inStock)) {
      _complain('At least one colour has to be in stock to list this');
      return;
    }

    setState(() => _busy = true);
    final List<String> highlights = _highlights.text
        .split('\n')
        .map((String l) => l.trim())
        .where((String l) => l.isNotEmpty)
        .toList(growable: false);

    await AppScope.of(context).adminRepository.saveProduct(
          ProductDraft(
            id: widget.product?.id,
            category: _category,
            brand: _brand.text.trim(),
            name: _name.text.trim(),
            modelCode: _modelCode.text.trim().toUpperCase(),
            rating: _rating,
            warrantyYears: _warrantyYears,
            warrantyNote: _warrantyNote.text.trim(),
            rangeKm: int.parse(_range.text.trim()),
            topSpeedKmph: int.parse(_topSpeed.text.trim()),
            chargingTime: _charging.text.trim(),
            batteryCapacity: _battery.text.trim(),
            motorPower: _motor.text.trim(),
            loadCapacityKg: int.parse(_load.text.trim()),
            colors: _colors,
            highlights: highlights,
          ),
        );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          _isCreate
              ? 'Listed — the field team has been notified'
              : 'Product updated — the field team has been notified',
        ),
      ),
    );
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(_isCreate ? 'List product' : 'Edit product'),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(Insets.lg),
          child: SizedBox(
            height: Sizes.primaryActionHeight,
            child: FilledButton(
              onPressed: _busy ? null : _save,
              child: _busy
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  : Text(_isCreate ? 'List product' : 'Save changes'),
            ),
          ),
        ),
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            Insets.lg,
            Insets.lg,
            Insets.lg,
            Insets.xxxl,
          ),
          children: <Widget>[
            // What the seller sees first, so it is what the admin sees first.
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      SizedBox(
                        width: 104,
                        child: ProductArtwork(
                          category: _category,
                          argb: _colors.isEmpty
                              ? AppColors.primary.toARGB32()
                              : _colors.first.argb,
                          height: 68,
                          padding: Insets.xs,
                          radius: Radii.sm,
                        ),
                      ),
                      const SizedBox(width: Insets.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              <String>[
                                _brand.text.trim(),
                                _name.text.trim(),
                              ].where((String s) => s.isNotEmpty).join(' '),
                              style: theme.textTheme.titleMedium,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${_category.label} · '
                              '${_colors.length} colour'
                              '${_colors.length == 1 ? '' : 's'}',
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                      WarrantyBadge(years: _warrantyYears, dense: true),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            AppCard(
              child: Column(
                children: <Widget>[
                  SelectField<ProductCategory>(
                    label: 'Category',
                    hint: 'Pick a category',
                    icon: Icons.category_outlined,
                    value: _category,
                    options: ProductCategory.values
                        .map(
                          (ProductCategory c) => SelectOption<ProductCategory>(
                            value: c,
                            label: c.label,
                          ),
                        )
                        .toList(),
                    onChanged: (ProductCategory? v) =>
                        setState(() => _category = v ?? _category),
                  ),
                  const SizedBox(height: Insets.md),
                  _field(
                    controller: _brand,
                    label: 'Brand',
                    icon: Icons.storefront_outlined,
                    onChanged: (_) => setState(() {}),
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _name,
                    label: 'Model name',
                    icon: Icons.label_outline_rounded,
                    onChanged: (_) => setState(() {}),
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _modelCode,
                    label: 'Model code',
                    icon: Icons.qr_code_2_rounded,
                    textCapitalization: TextCapitalization.characters,
                    validator: (String? v) => (v ?? '').trim().length < 3
                        ? 'At least 3 characters'
                        : null,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            _SectionTitle(
              'Colours',
              trailing: TextButton.icon(
                onPressed: () => _editColor(),
                icon: const Icon(Icons.add_rounded, size: 18),
                label: const Text('Add colour'),
              ),
            ),
            AppCard(
              child: Column(
                children: <Widget>[
                  if (_colors.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: Insets.md,
                      ),
                      child: Text(
                        'No colours yet. The gallery is per colour, so a '
                        'product needs at least one.',
                        style: theme.textTheme.bodySmall,
                      ),
                    )
                  else
                    ..._colors.asMap().entries.map(
                          (MapEntry<int, ProductColor> e) => _ColorRow(
                            color: e.value,
                            onEdit: () =>
                                _editColor(existing: e.value, index: e.key),
                            onRemove: () => setState(() {
                              _colors = <ProductColor>[..._colors]
                                ..removeAt(e.key);
                            }),
                          ),
                        ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            const _SectionTitle('Warranty & rating'),
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          'Warranty',
                          style: theme.textTheme.bodyMedium,
                        ),
                      ),
                      UnitStepper(
                        value: _warrantyYears,
                        min: 1,
                        max: 10,
                        onChanged: (int v) =>
                            setState(() => _warrantyYears = v),
                      ),
                      const SizedBox(width: Insets.sm),
                      Text('yrs', style: theme.textTheme.bodySmall),
                    ],
                  ),
                  const SizedBox(height: Insets.md),
                  _field(
                    controller: _warrantyNote,
                    label: 'Warranty detail',
                    icon: Icons.verified_user_outlined,
                    maxLines: 2,
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text('Rating', style: theme.textTheme.bodyMedium),
                      ),
                      RatingStars(rating: _rating),
                    ],
                  ),
                  Slider(
                    value: _rating,
                    min: 1,
                    max: 5,
                    divisions: 8,
                    label: _rating.toStringAsFixed(1),
                    onChanged: (double v) => setState(() => _rating = v),
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.lg),
            const _SectionTitle('Specs'),
            AppCard(
              child: Column(
                children: <Widget>[
                  _field(
                    controller: _range,
                    label: 'Range on a full charge (km)',
                    icon: Icons.route_outlined,
                    keyboardType: TextInputType.number,
                    digitsOnly: true,
                    validator: _positiveInt,
                  ),
                  _field(
                    controller: _topSpeed,
                    label: 'Top speed (km/h)',
                    icon: Icons.speed_rounded,
                    keyboardType: TextInputType.number,
                    digitsOnly: true,
                    validator: _positiveInt,
                  ),
                  _field(
                    controller: _charging,
                    label: 'Charging time',
                    icon: Icons.electrical_services_rounded,
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _battery,
                    label: 'Battery',
                    icon: Icons.battery_charging_full_rounded,
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _motor,
                    label: 'Motor',
                    icon: Icons.settings_input_component_rounded,
                    validator: (String? v) =>
                        (v ?? '').trim().isEmpty ? 'Required' : null,
                  ),
                  _field(
                    controller: _load,
                    label: 'Load capacity (kg)',
                    icon: Icons.fitness_center_rounded,
                    keyboardType: TextInputType.number,
                    digitsOnly: true,
                    validator: _positiveInt,
                  ),
                  _field(
                    controller: _highlights,
                    label: 'Highlights — one per line',
                    icon: Icons.star_outline_rounded,
                    maxLines: 4,
                  ),
                ],
              ),
            ),
            const SizedBox(height: Insets.md),
            Text(
              'Listing a product notifies every field employee. Photos are not '
              'uploaded in this build — the catalogue is drawn from the colour '
              'you pick.',
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }

  static String? _positiveInt(String? v) {
    final int? n = int.tryParse((v ?? '').trim());
    if (n == null) return 'Numbers only';
    return n <= 0 ? 'Must be more than 0' : null;
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    String? Function(String?)? validator,
    TextInputType? keyboardType,
    TextCapitalization textCapitalization = TextCapitalization.sentences,
    ValueChanged<String>? onChanged,
    bool digitsOnly = false,
    int maxLines = 1,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.md),
      child: TextFormField(
        controller: controller,
        validator: validator,
        keyboardType: keyboardType,
        textCapitalization: textCapitalization,
        onChanged: onChanged,
        maxLines: maxLines,
        inputFormatters: digitsOnly
            ? <TextInputFormatter>[FilteringTextInputFormatter.digitsOnly]
            : null,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon),
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title, {this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: Insets.sm, left: Insets.xs),
      child: Row(
        children: <Widget>[
          Expanded(
            child: Text(title, style: Theme.of(context).textTheme.titleSmall),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

class _ColorRow extends StatelessWidget {
  const _ColorRow({
    required this.color,
    required this.onEdit,
    required this.onRemove,
  });

  final ProductColor color;
  final VoidCallback onEdit;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      onTap: onEdit,
      leading: Container(
        width: 30,
        height: 30,
        decoration: BoxDecoration(
          color: Color(color.argb),
          shape: BoxShape.circle,
          border: Border.all(color: context.lineColor),
        ),
      ),
      title: Text(color.name),
      subtitle: Text(
        color.inStock
            ? '${color.imageCount} images'
            : 'Out of stock — sellers cannot log it',
        style: theme.textTheme.bodySmall?.copyWith(
          color: color.inStock ? null : AppColors.danger,
        ),
      ),
      trailing: IconButton(
        onPressed: onRemove,
        icon: const Icon(Icons.delete_outline_rounded),
        tooltip: 'Remove colour',
      ),
    );
  }
}

/// Add or edit one colourway. Colours are picked from a fixed swatch set rather
/// than a colour wheel — the catalogue reads better when "Pearl White" is the
/// same white on every product.
class _ColorEditorSheet extends StatefulWidget {
  const _ColorEditorSheet({this.color});

  final ProductColor? color;

  @override
  State<_ColorEditorSheet> createState() => _ColorEditorSheetState();
}

class _ColorEditorSheetState extends State<_ColorEditorSheet> {
  late final TextEditingController _name =
      TextEditingController(text: widget.color?.name ?? '');
  late int _argb = widget.color?.argb ?? _swatches.first.argb;
  late bool _inStock = widget.color?.inStock ?? true;

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  void _submit() {
    final String name = _name.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Name the colour')),
      );
      return;
    }
    Navigator.of(context).pop(
      ProductColor(
        name: name,
        argb: _argb,
        inStock: _inStock,
        imageUrls: widget.color?.imageUrls ?? const <String>[],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        Insets.lg,
        0,
        Insets.lg,
        MediaQuery.viewInsetsOf(context).bottom + Insets.lg,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            widget.color == null ? 'Add colour' : 'Edit colour',
            style: theme.textTheme.titleMedium,
          ),
          const SizedBox(height: Insets.md),
          TextField(
            controller: _name,
            autofocus: widget.color == null,
            textCapitalization: TextCapitalization.words,
            onSubmitted: (_) => _submit(),
            decoration: const InputDecoration(
              labelText: 'Colour name',
              hintText: 'Pearl White',
              prefixIcon: Icon(Icons.palette_outlined),
            ),
          ),
          const SizedBox(height: Insets.lg),
          Text('Swatch', style: theme.textTheme.labelSmall),
          const SizedBox(height: Insets.sm),
          Wrap(
            spacing: Insets.sm,
            runSpacing: Insets.sm,
            children: _swatches
                .map(
                  (_Swatch s) => Tooltip(
                    message: s.label,
                    child: InkWell(
                      onTap: () => setState(() {
                        _argb = s.argb;
                        if (_name.text.trim().isEmpty) _name.text = s.label;
                      }),
                      borderRadius: BorderRadius.circular(Radii.pill),
                      child: Container(
                        padding: const EdgeInsets.all(2),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: s.argb == _argb
                                ? AppColors.primary
                                : Colors.transparent,
                            width: 2,
                          ),
                        ),
                        child: Container(
                          width: 30,
                          height: 30,
                          decoration: BoxDecoration(
                            color: Color(s.argb),
                            shape: BoxShape.circle,
                            border: Border.all(color: context.lineColor),
                          ),
                        ),
                      ),
                    ),
                  ),
                )
                .toList(growable: false),
          ),
          const SizedBox(height: Insets.sm),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _inStock,
            onChanged: (bool v) => setState(() => _inStock = v),
            title: const Text('In stock'),
            subtitle: const Text(
              'Out-of-stock colours stay on the product but cannot be sold',
            ),
          ),
          const SizedBox(height: Insets.sm),
          SizedBox(
            width: double.infinity,
            height: Sizes.primaryActionHeight,
            child: FilledButton(
              onPressed: _submit,
              child: Text(widget.color == null ? 'Add colour' : 'Save colour'),
            ),
          ),
        ],
      ),
    );
  }
}

class _Swatch {
  const _Swatch(this.label, this.argb);
  final String label;
  final int argb;
}

/// Same values the seed catalogue uses, so a colour an admin adds sits next to
/// the shipped ones without a shade clash.
const List<_Swatch> _swatches = <_Swatch>[
  _Swatch('Pearl White', 0xFFF8FAFC),
  _Swatch('Matte Black', 0xFF1E293B),
  _Swatch('Space Grey', 0xFF94A3B8),
  _Swatch('Sports Red', 0xFFDC2626),
  _Swatch('Sunset Orange', 0xFFF97316),
  _Swatch('Solar Yellow', 0xFFFACC15),
  _Swatch('Lime Green', 0xFF22C55E),
  _Swatch('Teal', 0xFF14B8A6),
  _Swatch('Sky Blue', 0xFF7DD3FC),
  _Swatch('Midnight Blue', 0xFF1D4ED8),
  _Swatch('Electric Purple', 0xFF8B5CF6),
];
