import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../data/models/product.dart';

class WebsiteLinks {
  static const String baseUrl = String.fromEnvironment('WEBSITE_BASE_URL',
      defaultValue: 'https://hazraelectricalbike.com');
  static Uri page(String path) =>
      Uri.parse('${baseUrl.replaceAll(RegExp(r'/+$'), '')}/$path');
  static Uri get privacy => page('privacy-policy');
  static Uri get terms => page('terms-and-conditions');
  static Uri product(Product product, {ProductColor? color}) =>
      page('product-detail').replace(queryParameters: {
        if (product.slug.isNotEmpty) 'slug': product.slug else 'id': product.id,
        if (color != null) 'color': color.id ?? color.name,
      });
  static Future<void> open(BuildContext context, Uri uri) async {
    bool opened = false;
    try {
      opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {/* Show a retryable error below. */}
    if (!opened && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Unable to open the website. Please try again.')));
    }
  }
}
