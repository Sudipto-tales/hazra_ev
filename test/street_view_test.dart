import 'package:employeetracking_mobile_app/core/utils/street_view.dart';
import 'package:flutter_test/flutter_test.dart';

/// The Street View URL is the whole contract of Phase 4 — if the params are
/// wrong the Maps app opens a pin instead of a panorama, or nothing at all. So
/// the builder is pure and pinned here against Google's documented "Maps URLs"
/// Street View form.
void main() {
  group('StreetView.uri', () {
    test('builds the documented pano form', () {
      final Uri uri = StreetView.uri(22.5726, 88.3639);
      expect(uri.scheme, 'https');
      expect(uri.host, 'www.google.com');
      expect(uri.path, '/maps/@');
      expect(uri.queryParameters['api'], '1');
      expect(uri.queryParameters['map_action'], 'pano');
      expect(uri.queryParameters['viewpoint'], '22.5726,88.3639');
    });

    test('negative and zero coordinates survive formatting', () {
      final Uri uri = StreetView.uri(-33.8568, 0);
      expect(uri.queryParameters['viewpoint'], '-33.8568,0.0');
    });

    test('the whole string matches the expected template', () {
      expect(
        StreetView.uri(1.5, -2.25).toString(),
        'https://www.google.com/maps/@?api=1&map_action=pano'
        '&viewpoint=1.5,-2.25',
      );
    });
  });
}
