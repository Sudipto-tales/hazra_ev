/// Who this app is for and who built it, in one place.
///
/// These strings appear in About, on the version screen and on the sign-in
/// footers. They used to be literals scattered across those files — including
/// a `'© 2026 Company Ltd.'` that was wrong the moment the year turned, and a
/// placeholder product name the app never actually used.
///
/// Nothing here is a secret or an address. The API base URL is deliberately
/// **not** in this class and is never rendered: see `ApiConfig`, whose
/// `describe()` is for `debugPrint` and logs only.
class Brand {
  const Brand._();

  /// The product the field team is using.
  static const String product = 'Hazra EV';

  /// The studio that builds and maintains it.
  static const String developer = 'Promix';

  /// What the app calls itself on its own About header.
  static const String appName = 'Hazra EV Field Tracker';

  /// Read at call time, never baked in: a `const` year is a bug with a
  /// one-year fuse. `DateTime.now()` is local, which is the right clock for a
  /// copyright line.
  static int get year => DateTime.now().year;

  /// `© 2026 Hazra EV. All rights reserved.`
  static String get copyright => '© $year $product. All rights reserved.';
}
