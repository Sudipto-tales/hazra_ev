/// Build identity for the app, in one place.
///
/// Hand-maintained on purpose. Reading the real value at runtime needs
/// `package_info_plus`, and pulling in a platform plugin to render one string
/// in Settings is not a trade this app wants to make. The rule instead: these
/// two constants and `version:` in `pubspec.yaml` change in the same commit.
///
/// Everything user-facing reads [label] instead of writing its own string —
/// the Settings row used to say `0.1.0 (static demo data)` while `AboutPage`
/// said `Version 0.1.0 · build 1`, and only one of them could stay true.
class AppInfo {
  const AppInfo._();

  /// Mirrors the part of `pubspec.yaml` `version:` before the `+`.
  static const String version = '0.1.0';

  /// Mirrors the part after the `+`.
  static const String build = '1';

  static const String label = 'Version $version · build $build';

  /// The version line with the data source appended. "Which build is this?"
  /// and "is it actually talking to the server?" are the same question when
  /// somebody reports a bug, so the answer is one string.
  static String labelFor({required bool live}) =>
      '$label · ${live ? 'connected to server' : 'demo data'}';
}
