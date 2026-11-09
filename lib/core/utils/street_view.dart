import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens an external Google Street View panorama at a coordinate.
///
/// No API key, no billing, no in-app panorama widget: it hands the point to
/// whatever already knows how to show it — the Google Maps app on a phone, the
/// browser everywhere else. That is exactly what the cross-platform **Maps
/// URLs** scheme is for, and the one form that opens a *panorama* rather than a
/// map pin is
///
/// ```
/// https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=<lat>,<lng>
/// ```
///
/// (Google "Maps URLs" → *Display a Street View panorama*: `api=1`,
/// `map_action=pano`, `viewpoint=lat,lng`. Verified against a live request —
/// the URL resolves 200 and the Maps app claims the intent on device.) If there
/// is no panorama at the exact point, Maps drops the user at the nearest one,
/// which is the honest behaviour for a GPS fix that may be a few metres off.
class StreetView {
  const StreetView._();

  /// The panorama URL for [latitude], [longitude]. Pure, so it can be asserted
  /// without launching anything.
  static Uri uri(double latitude, double longitude) => Uri.parse(
        'https://www.google.com/maps/@?api=1&map_action=pano'
        '&viewpoint=$latitude,$longitude',
      );

  /// Launches the panorama externally and, if nothing can handle it, says so
  /// instead of failing silently or crashing. A device with no Maps app and no
  /// browser is the case this guards.
  static Future<void> open(
    BuildContext context,
    double latitude,
    double longitude,
  ) async {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    bool opened = false;
    try {
      opened = await launchUrl(
        uri(latitude, longitude),
        mode: LaunchMode.externalApplication,
      );
    } catch (_) {
      opened = false;
    }
    if (!opened) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('Could not open Street View on this device.'),
        ),
      );
    }
  }
}
