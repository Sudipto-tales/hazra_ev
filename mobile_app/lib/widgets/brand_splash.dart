import 'package:flutter/material.dart';

/// The Hazra mark on a plain white field — the Flutter half of the launch
/// screen.
///
/// Its only job is to be **indistinguishable from the native one**. Android
/// draws `@drawable/launch_background` from the moment the window opens until
/// Flutter's first frame; this widget takes over from there. If the two
/// disagree on background colour, logo size or logo position, the handoff
/// reads as two screens flashing past instead of one screen holding still.
/// So these three values are mirrored by hand in
/// `android/app/src/main/res/drawable/launch_background.xml` (and its `-v21`
/// twin) and must be changed in both places together.
///
/// White in dark mode too, deliberately. The brand mark is drawn for a white
/// field: its "HAZRA / ELECTRIAL BIKE" wordmark is dark purple and all but
/// disappears against the app's dark background, and there is no dark-mode
/// variant of the logo to switch to. One background for both modes also means
/// the native side needs no `values-night` divergence, so the native→Flutter
/// handoff is pixel-identical whatever the system theme is. Revisit when
/// design-agent ships a light-wordmark variant.
class BrandSplash extends StatelessWidget {
  const BrandSplash({super.key});

  /// Mirrored by `android:width`/`android:height` on the launch background's
  /// bitmap layer.
  static const double logoSize = 128;

  /// Mirrored by `@color/splash_background`.
  static const Color background = Color(0xFFFFFFFF);

  @override
  Widget build(BuildContext context) => const ColoredBox(
        color: background,
        // No SafeArea: the native drawable centres in the whole window,
        // insets included, so centring in anything smaller would shift the
        // logo at the handoff.
        child: Center(
          child: Image(
            image: AssetImage('assets/brand/hazra_logo.png'),
            width: logoSize,
            height: logoSize,
            // The source art is a 200px raster, so it is being scaled up.
            // Medium gives a soft edge rather than a stair-stepped one.
            filterQuality: FilterQuality.medium,
            semanticLabel: 'Hazra EV',
          ),
        ),
      );
}
