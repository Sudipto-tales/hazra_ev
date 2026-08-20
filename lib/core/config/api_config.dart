import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Where the app looks for `website/api`.
///
/// The default is the local dev server on this machine —
/// `http://localhost:8000/api/v1`. That is the right address for the desktop
/// and Chrome builds and for anything sharing a loopback with the PHP server.
///
/// **"localhost" is not one address.** A device or emulator resolves it to
/// *itself*, not to your machine, so those two cases need a flag:
///
/// | Running on | `--dart-define` |
/// | --- | --- |
/// | Desktop / Chrome / iOS simulator | none — `localhost` is correct |
/// | Linux desktop | `API_HOST=127.0.0.1` — see below |
/// | Android emulator | `API_HOST=10.0.2.2` (alias for the host loopback) |
/// | Physical Android/iOS device | `API_HOST=<your machine's LAN IP>` |
///
/// Linux is called out because `getent hosts localhost` answers `::1` first on
/// a stock Ubuntu, while `php -S 127.0.0.1:8000` listens on IPv4 only. The
/// first connection attempt then goes to a port nothing is bound to. Naming
/// the v4 address skips the guess.
///
/// ```sh
/// flutter run                                        # localhost:8000
/// flutter run -d linux --dart-define=API_HOST=127.0.0.1
/// flutter run --dart-define=API_HOST=10.0.2.2        # Android emulator
/// flutter run --dart-define=API_HOST=192.168.1.109   # physical handset
/// flutter run --dart-define=API_BASE_URL=https://staging.example.com/api/v1
/// ```
///
/// A phone or emulator also needs the server listening on all interfaces —
/// `php vayu run --host 0.0.0.0 --port 8000`. For plain `localhost` the
/// default bind is enough.
class ApiConfig {
  const ApiConfig._();

  /// Full override. Wins over every other value when non-empty.
  static const String _baseUrlOverride =
      String.fromEnvironment('API_BASE_URL');

  /// Host that serves the API. Change with `--dart-define=API_HOST=…`.
  static const String host =
      String.fromEnvironment('API_HOST', defaultValue: 'localhost');

  static const int _port = int.fromEnvironment('API_PORT', defaultValue: 8000);

  /// Set `--dart-define=USE_MOCKS=true` to run entirely offline against
  /// `lib/data/mock/`, which is what the app did before it had a client.
  static const bool useMocks =
      bool.fromEnvironment('USE_MOCKS', defaultValue: false);

  static const String _apiPrefix = '/api/v1';

  /// Every request is built from this. Never ends with a slash.
  static String get baseUrl {
    if (_baseUrlOverride.isNotEmpty) {
      return _baseUrlOverride.replaceAll(RegExp(r'/+$'), '');
    }

    return 'http://$host:$_port$_apiPrefix';
  }

  /// Report images come back as paths relative to the PHP document root
  /// (`storage/uploads/…`), so they resolve against the origin, not the API
  /// prefix.
  static String get originUrl {
    final Uri uri = Uri.parse(baseUrl);
    return '${uri.scheme}://${uri.authority}';
  }

  /// One line for the debug banner and for `flutter logs`, so a failed request
  /// says which address it actually tried.
  static String describe() =>
      'API ${useMocks ? '(mocks)' : baseUrl} · '
      '${kIsWeb ? 'web' : Platform.operatingSystem}';
}
