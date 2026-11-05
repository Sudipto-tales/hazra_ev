import 'package:package_info_plus/package_info_plus.dart';

/// Build identity for the app, in one place.
///
/// Read from the real build at runtime via `package_info_plus`, so the value
/// shown in Settings, on the login screens and in the device payload always
/// matches `version:` in `pubspec.yaml` without anyone hand-editing a constant.
///
/// [init] runs once during startup (see `main.dart`), awaits
/// [PackageInfo.fromPlatform] and caches the result into the mutable statics
/// below. [version]/[build]/[label] stay plain sync getters so the widgets
/// that read them in `build()` don't have to change shape. The `'0.1.0'`-style
/// values are only a last-resort fallback for when the plugin call fails (e.g.
/// a test harness with no platform channels).
class AppInfo {
  const AppInfo._();

  /// Populated by [init] from `pubspec.yaml` `version:` (before the `+`).
  /// Falls back to the literal below if the plugin never ran or failed.
  static String version = '0.1.0';

  /// Populated by [init] from the part after the `+`.
  static String build = '1';

  static String get label => 'Version $version · build $build';

  /// Loads the real build identity once. Safe to call without `await` blocking
  /// the UI, but startup awaits it so the first frame already has the value.
  static Future<void> init() async {
    try {
      final PackageInfo info = await PackageInfo.fromPlatform();
      if (info.version.isNotEmpty) version = info.version;
      if (info.buildNumber.isNotEmpty) build = info.buildNumber;
    } catch (_) {
      // Keep the fallback constants — better a slightly stale string than a
      // crash on a platform where the channel is unavailable.
    }
  }

  // `labelFor({required bool live})` used to live here, appending
  // "connected to server" / "demo data" to [label]. It is gone: the only
  // caller was the Settings version row, and the whole class of
  // "which backend is this build pointed at?" detail is no longer rendered in
  // the UI at all. `ApiConfig.describe()` answers it for `debugPrint` and
  // `flutter logs`, which is where that belongs.
}
