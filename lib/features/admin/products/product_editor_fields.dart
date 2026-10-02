import 'dart:convert';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../data/api/api_exception.dart';
import '../../../state/app_scope.dart';
import '../../reports/widgets/product_artwork.dart';

String productEditorId() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
      '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
}

String productEditorError(Object error) =>
    error is ApiException ? error.message : 'Could not save: $error';

String? productWholeNumber(String? value) {
  if ((value ?? '').trim().isEmpty) return null;
  final number = num.tryParse(value!.trim());
  return number == null ||
          !number.isFinite ||
          number < 0 ||
          number != number.floor()
      ? 'Use a non-negative whole number'
      : null;
}

int productWholeNumberValue(String value) =>
    num.tryParse(value.trim())?.toInt() ?? 0;

String? productTextLimit(String? value, int bytes, {bool required = false}) {
  if (required && (value ?? '').trim().isEmpty) return 'Required';
  final text = required ? (value ?? '').trim() : value ?? '';
  return utf8.encode(text).length > bytes ? 'Text is too long' : null;
}

String? productUrl(String? value, {bool allowAnchor = false}) {
  final url = (value ?? '').trim();
  if (url.isEmpty) return null;
  if (utf8.encode(url).length > 2048 ||
      RegExp(r'[\x00-\x20\\]').hasMatch(url) ||
      url.startsWith('//')) {
    return 'Use a valid image path or website URL';
  }
  if (allowAnchor && RegExp(r'^#[A-Za-z][\w-]*$').hasMatch(url)) return null;
  final uri = Uri.tryParse(url);
  if (uri == null) return 'Invalid URL';
  if (uri.hasScheme) {
    return (uri.scheme == 'http' || uri.scheme == 'https') &&
            uri.host.isNotEmpty
        ? null
        : 'Use an HTTP or HTTPS URL';
  }
  return url.contains(':') || url.startsWith('#') ? 'Invalid URL' : null;
}

class ProductEditorText extends StatelessWidget {
  const ProductEditorText({
    super.key,
    required this.label,
    this.controller,
    this.initialValue,
    this.onChanged,
    this.validator,
    this.lines = 1,
    this.number = false,
  });

  final String label;
  final TextEditingController? controller;
  final String? initialValue;
  final ValueChanged<String>? onChanged;
  final String? Function(String?)? validator;
  final int lines;
  final bool number;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: TextFormField(
          controller: controller,
          initialValue: controller == null ? initialValue : null,
          onChanged: onChanged,
          validator: validator,
          maxLines: lines,
          keyboardType: number
              ? const TextInputType.numberWithOptions(decimal: true)
              : lines > 1
                  ? TextInputType.multiline
                  : TextInputType.text,
          decoration: InputDecoration(labelText: label),
        ),
      );
}

/// Uploads immediately and reports progress so a product cannot save mid-upload.
class ProductImageInput extends StatefulWidget {
  const ProductImageInput({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.onUploading,
  });
  final String label;
  final String value;
  final ValueChanged<String> onChanged;
  final ValueChanged<bool>? onUploading;

  @override
  State<ProductImageInput> createState() => _ProductImageInputState();
}

class _ProductImageInputState extends State<ProductImageInput> {
  late final _url = TextEditingController(text: widget.value);
  bool _uploading = false;

  @override
  void didUpdateWidget(ProductImageInput oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.value != _url.text) _url.text = widget.value;
  }

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  Future<void> _pick(ImageSource source) async {
    if (_uploading) return;
    final repository = AppScope.of(context).adminRepository;
    final reportUploading = widget.onUploading;
    setState(() => _uploading = true);
    reportUploading?.call(true);
    try {
      final file = await ImagePicker().pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (file == null || !mounted) return;
      final url = await repository.uploadProductImage(file);
      if (!mounted) return;
      _url.text = url;
      widget.onChanged(url);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(productEditorError(error))));
      }
    } finally {
      reportUploading?.call(false);
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ProductEditorText(
            label: widget.label,
            controller: _url,
            validator: productUrl,
            onChanged: widget.onChanged,
          ),
          if (widget.value.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Image.network(
                ProductArtwork.resolveUrl(widget.value) ?? widget.value,
                height: 110,
                width: 160,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) =>
                    const Text('Image preview unavailable'),
              ),
            ),
          Wrap(
            spacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: _uploading ? null : () => _pick(ImageSource.gallery),
                icon: const Icon(Icons.photo_library_outlined),
                label: const Text('Gallery'),
              ),
              OutlinedButton.icon(
                onPressed: _uploading ? null : () => _pick(ImageSource.camera),
                icon: const Icon(Icons.photo_camera_outlined),
                label: const Text('Camera'),
              ),
              TextButton(
                onPressed: _uploading
                    ? null
                    : () {
                        _url.clear();
                        widget.onChanged('');
                      },
                child: const Text('Clear image'),
              ),
            ],
          ),
          if (_uploading) const LinearProgressIndicator(),
          const SizedBox(height: 16),
        ],
      );
}
