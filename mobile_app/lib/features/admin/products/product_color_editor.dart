import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../reports/widgets/product_artwork.dart';
import 'product_editor_fields.dart';

class ProductColorEditor extends StatefulWidget {
  const ProductColorEditor({super.key, this.color});
  final ProductColor? color;
  @override
  State<ProductColorEditor> createState() => _ProductColorEditorState();
}

class _ProductColorEditorState extends State<ProductColorEditor> {
  final _form = GlobalKey<FormState>();
  late final _name = TextEditingController(text: widget.color?.name ?? '');
  late final _hex = TextEditingController(
    text: ((widget.color?.argb ?? 0xff202020) & 0xffffff)
        .toRadixString(16)
        .padLeft(6, '0'),
  );
  late bool _inStock = widget.color?.inStock ?? true;
  late final _urls = List<String>.from(widget.color?.imageUrls ?? []);
  bool _uploading = false;
  static const _palette = <String, int>{
    'Matte Black': 0x202020,
    'Pearl White': 0xefeaf8,
    'Midnight Indigo': 0x241640,
    'Ocean Blue': 0x12a5e0,
    'Cyan': 0x00d4ff,
    'Flame Orange': 0xf0532b,
    'Sunset Flame': 0xea580c,
    'Amber Gold': 0xf7941d,
    'Champagne Gold': 0xb45309,
    'Magenta': 0xa41fbf,
    'Violet': 0x7b2ff7,
    'Forest Green': 0x166534,
  };

  @override
  void dispose() {
    _name.dispose();
    _hex.dispose();
    super.dispose();
  }

  Future<void> _upload({bool camera = false}) async {
    if (_uploading) return;
    final repo = AppScope.of(context).adminRepository;
    setState(() => _uploading = true);
    try {
      final picker = ImagePicker();
      final List<XFile> files;
      if (camera) {
        final file = await picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 85,
          maxWidth: 1600,
        );
        files = file == null ? [] : [file];
      } else {
        files = await picker.pickMultiImage(imageQuality: 85, maxWidth: 1600);
      }
      if (!mounted || files.isEmpty) return;
      if (_urls.length + files.length > 40) {
        throw const FormatException('Each colour supports up to 40 photos');
      }
      // Keep successful uploads visible if a later file fails, so the user
      // can retry just the remaining photos.
      for (final file in files) {
        final url = await repo.uploadProductImage(file);
        if (!mounted) return;
        setState(() => _urls.add(url));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(productEditorError(error))));
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  void _save() {
    if (_uploading || !_form.currentState!.validate()) return;
    Navigator.pop(
      context,
      ProductColor(
        id: widget.color?.id ?? productEditorId(),
        position: widget.color?.position ?? 0,
        name: _name.text.trim(),
        argb:
            0xff000000 | int.parse(_hex.text.replaceFirst('#', ''), radix: 16),
        inStock: _inStock,
        imageUrls: List<String>.from(_urls),
      ),
    );
  }

  void _move(int index, int direction) => setState(() {
        final url = _urls.removeAt(index);
        _urls.insert(index + direction, url);
      });

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: !_uploading,
        child: Scaffold(
          appBar: AppBar(
            title: Text(widget.color == null ? 'Add colour' : 'Edit colour'),
          ),
          bottomNavigationBar: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton(
                onPressed: _uploading ? null : _save,
                child: const Text('Save colour'),
              ),
            ),
          ),
          body: AbsorbPointer(
            absorbing: _uploading,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (_uploading) const LinearProgressIndicator(),
                    ProductEditorText(
                      label: 'Colour name',
                      controller: _name,
                      validator: (v) =>
                          productTextLimit(v, 250, required: true),
                    ),
                    ProductEditorText(
                      label: 'Custom colour (HEX)',
                      controller: _hex,
                      onChanged: (_) => setState(() {}),
                      validator: (v) =>
                          RegExp(r'^#?[0-9a-fA-F]{6}$').hasMatch(v ?? '')
                              ? null
                              : 'Enter six HEX digits, e.g. #12a5e0',
                    ),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: _palette.entries
                          .map(
                            (e) => Tooltip(
                              message: e.key,
                              child: InkWell(
                                onTap: () => setState(() {
                                  _hex.text =
                                      e.value.toRadixString(16).padLeft(6, '0');
                                  if (_name.text.trim().isEmpty) {
                                    _name.text = e.key;
                                  }
                                }),
                                child: Container(
                                  width: 36,
                                  height: 36,
                                  decoration: BoxDecoration(
                                    color: Color(0xff000000 | e.value),
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      width: 3,
                                      color: _hex.text
                                                  .replaceFirst('#', '')
                                                  .toLowerCase() ==
                                              e.value
                                                  .toRadixString(16)
                                                  .padLeft(6, '0')
                                          ? Theme.of(context)
                                              .colorScheme
                                              .primary
                                          : Colors.grey,
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          )
                          .toList(),
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('In stock'),
                      value: _inStock,
                      onChanged: (v) => setState(() => _inStock = v),
                    ),
                    const Text(
                        'Photos — the first photo is the primary image.'),
                    const SizedBox(height: 12),
                    ..._urls.asMap().entries.map(
                          (e) => Card(
                            child: Padding(
                              padding: const EdgeInsets.all(8),
                              child: Column(
                                children: [
                                  Image.network(
                                    ProductArtwork.resolveUrl(e.value) ??
                                        e.value,
                                    height: 140,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, __, ___) =>
                                        const Text('Preview unavailable'),
                                  ),
                                  Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      Expanded(
                                        child: Text(
                                          e.key == 0
                                              ? 'Primary image'
                                              : 'Photo ${e.key + 1}',
                                        ),
                                      ),
                                      IconButton(
                                        tooltip: 'Move photo up',
                                        icon: const Icon(Icons.arrow_upward),
                                        onPressed: e.key == 0
                                            ? null
                                            : () => _move(e.key, -1),
                                      ),
                                      IconButton(
                                        tooltip: 'Move photo down',
                                        icon: const Icon(Icons.arrow_downward),
                                        onPressed: e.key == _urls.length - 1
                                            ? null
                                            : () => _move(e.key, 1),
                                      ),
                                      IconButton(
                                        tooltip: 'Remove photo',
                                        icon: const Icon(Icons.delete_outline),
                                        onPressed: () => setState(
                                            () => _urls.removeAt(e.key)),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                    Wrap(
                      spacing: 8,
                      children: [
                        OutlinedButton.icon(
                          onPressed:
                              _urls.length >= 40 ? null : () => _upload(),
                          icon: const Icon(Icons.photo_library_outlined),
                          label: const Text('Gallery'),
                        ),
                        OutlinedButton.icon(
                          onPressed: _urls.length >= 40
                              ? null
                              : () => _upload(camera: true),
                          icon: const Icon(Icons.photo_camera_outlined),
                          label: const Text('Camera'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
}
