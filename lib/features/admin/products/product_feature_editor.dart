import 'package:flutter/material.dart';

import '../../../data/models/models.dart';
import '../../../widgets/select_field.dart';
import 'product_editor_fields.dart';

class ProductFeatureEditor extends StatefulWidget {
  const ProductFeatureEditor({super.key, this.card, required this.colors});
  final ProductFeatureCard? card;
  final List<ProductColor> colors;
  @override
  State<ProductFeatureEditor> createState() => _ProductFeatureEditorState();
}

class _ProductFeatureEditorState extends State<ProductFeatureEditor> {
  final _form = GlobalKey<FormState>();
  late final _title = TextEditingController(text: widget.card?.title ?? '');
  late final _description = TextEditingController(
    text: widget.card?.description ?? '',
  );
  late final _alt = TextEditingController(text: widget.card?.alt ?? '');
  late String _image = widget.card?.image ?? '';
  late String _colorId = widget.card?.colorId ?? '';
  late bool _visible = widget.card?.visible ?? true;
  bool _uploading = false;
  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _alt.dispose();
    super.dispose();
  }

  void _save() {
    if (_uploading || !_form.currentState!.validate()) return;
    Navigator.pop(
      context,
      ProductFeatureCard(
        id: widget.card?.id ?? productEditorId(),
        title: _title.text.trim(),
        description: _description.text,
        alt: _alt.text,
        image: _image.trim(),
        colorId: _colorId,
        visible: _visible,
        legacyCrop: widget.card?.legacyCrop ?? false,
      ),
    );
  }

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: !_uploading,
        child: Scaffold(
          appBar: AppBar(
            title: Text(
              widget.card == null ? 'Add feature card' : 'Edit feature card',
            ),
          ),
          bottomNavigationBar: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton(
                onPressed: _uploading ? null : _save,
                child: const Text('Save feature card'),
              ),
            ),
          ),
          body: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Form(
              key: _form,
              child: Column(
                children: [
                  ProductEditorText(
                    label: 'Title',
                    controller: _title,
                    validator: (v) => productTextLimit(v, 250, required: true),
                  ),
                  ProductEditorText(
                    label: 'Description (optional)',
                    controller: _description,
                    lines: 3,
                    validator: (v) => productTextLimit(v, 3000),
                  ),
                  ProductImageInput(
                    label: 'Feature image',
                    value: _image,
                    onChanged: (v) => setState(() => _image = v),
                    onUploading: (v) {
                      if (mounted) setState(() => _uploading = v);
                    },
                  ),
                  ProductEditorText(
                    label: 'Image alt text',
                    controller: _alt,
                    validator: (v) => productTextLimit(v, 500),
                  ),
                  SelectField<String>(
                    label: 'Show for colour',
                    hint: 'All colours',
                    value: _colorId,
                    options: [
                      const SelectOption(
                        value: '',
                        label: 'All colours (shared card)',
                      ),
                      ...widget.colors.where((c) => c.id != null).map(
                          (c) => SelectOption(value: c.id!, label: c.name)),
                    ],
                    onChanged: (v) => setState(() => _colorId = v ?? ''),
                  ),
                  SwitchListTile(
                    title: const Text('Visible'),
                    value: _visible,
                    onChanged: (v) => setState(() => _visible = v),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
}
