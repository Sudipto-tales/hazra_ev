import 'package:flutter/material.dart';

import 'app_info.dart';

/// Renders the app's version the one way it is rendered everywhere:
/// the word "Version" in the surrounding text style, and the number itself
/// set apart — heavier weight, wider tracking, tabular figures.
///
/// The build number is deliberately not part of this. It is an internal
/// identifier (the thing `update_controller` compares against
/// `versionCode` from the API) and it is not shown in the UI at all; see
/// [AppInfo.build].
///
/// Every version string in the app goes through here rather than through a
/// hand-built `'Version ${AppInfo.version}'`, so a change to how the number
/// looks is one edit, not nine.
class VersionLabel extends StatelessWidget {
  const VersionLabel({
    super.key,
    this.style,
    this.textAlign,
    this.prefix,
    this.numberStyle,
  });

  /// Base style for the whole line. Defaults to `bodySmall`, which is what
  /// almost every caller wants — the login screens, the About footer and the
  /// settings row all render this as the smallest text on the page.
  final TextStyle? style;

  final TextAlign? textAlign;

  /// Text placed before "Version", e.g. `'Admin console · '`. Rendered in
  /// [style], not in the emphasised number style.
  final String? prefix;

  /// Overrides for the number's own span, merged over the emphasis defaults.
  /// The version screen uses it to push the number up to display size.
  final TextStyle? numberStyle;

  @override
  Widget build(BuildContext context) {
    final TextStyle base =
        style ?? Theme.of(context).textTheme.bodySmall ?? const TextStyle();
    final TextStyle number = const TextStyle(
      fontWeight: FontWeight.w800,
      letterSpacing: 0.6,
      fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
    ).merge(numberStyle);

    return Text.rich(
      TextSpan(
        children: <TextSpan>[
          if (prefix != null && prefix!.isNotEmpty) TextSpan(text: prefix),
          const TextSpan(text: 'Version '),
          TextSpan(text: AppInfo.version, style: number),
        ],
      ),
      style: base,
      textAlign: textAlign,
    );
  }
}
