import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/config/api_config.dart';
import '../../data/api/api_client.dart';
import '../../data/api/api_exception.dart';
import '../../features/profile/app_info.dart';

/// Keeps a known mandatory update enforced across restarts and offline periods.
/// A first-time network failure permits field work; it is checked again on resume.
class UpdateController extends ChangeNotifier {
  UpdateController(this.api, {MethodChannel? channel})
      : _channel = channel ?? const MethodChannel('hazra_ev/updater');

  final ApiClient api;
  final MethodChannel _channel;
  static const cacheKey = 'app.requiredUpdate.v1';
  Map<String, dynamic>? release;
  bool checking = true;
  bool busy = false;
  bool ready = false;
  bool waitingForPermission = false;
  int progress = 0;
  String? error;
  int _sdk = 0;
  bool _disposed = false;
  bool _checking = false;
  SharedPreferences? _prefs;

  bool get requiredUpdate => release != null;
  bool get signedIn => api.isSignedIn;

  bool _newer(Map<String, dynamic> data) =>
      (data['versionCode'] as num).toInt() > (int.tryParse(AppInfo.build) ?? 0);

  Future<void> start() async {
    _channel.setMethodCallHandler((call) async {
      if (call.method == 'progress') {
        progress = call.arguments as int;
        _emit();
      }
    });
    try {
      _sdk = await _channel.invokeMethod<int>('sdk') ?? 0;
      _prefs = await SharedPreferences.getInstance();
      final cached = _prefs?.getString(cacheKey);
      if (cached != null) {
        final data = Map<String, dynamic>.from(jsonDecode(cached) as Map);
        if (_newer(data)) release = data;
      }
    } catch (_) {
      // A damaged cache must not prevent a fresh server check.
    }
    await check();
  }

  Future<void> check() async {
    if (_checking || busy || _disposed) return;
    if (!signedIn) {
      checking = false;
      _emit();
      return;
    }
    _checking = true;
    try {
      final data = (await api.get('/app-releases/update')).map;
      if (_disposed) return;
      final next = _newer(data) ? data : null;
      if (next?['id'] != release?['id']) ready = false;
      release = next;
      error = null;
      if (next == null) {
        await _prefs?.remove(cacheKey);
      } else {
        await _prefs?.setString(cacheKey, jsonEncode(next));
      }
    } on ApiException catch (e) {
      if (e.code == 'NO_PUBLISHED_RELEASE') {
        // Withdrawing the current release is also the emergency escape hatch.
        release = null;
        ready = false;
        error = null;
        await _prefs?.remove(cacheKey);
      } else if (requiredUpdate) {
        error =
            'Could not check the update. Check your connection and try again.';
      }
    } catch (_) {
      // Preserve a known requirement. A transient outage never clears it.
      if (requiredUpdate) {
        error =
            'Could not check the update. Check your connection and try again.';
      }
    } finally {
      _checking = false;
      checking = false;
      _emit();
    }
    if (requiredUpdate && error == null && !ready) await download();
  }

  Future<void> download() async {
    final data = release;
    if (data == null || busy || _disposed || !signedIn) return;
    if (data['available'] != true) {
      error =
          'The update is temporarily unavailable. Please contact your administrator or try again.';
      _emit();
      return;
    }
    if (_sdk > 0 && (data['minAndroidSdk'] as num? ?? 0) > _sdk) {
      error =
          'This update requires ${data['androidRequirement']}. Please contact your administrator.';
      _emit();
      return;
    }
    busy = true;
    error = null;
    progress = 0;
    _emit();
    try {
      // The metadata request has already refreshed the bearer token if needed.
      await _channel.invokeMethod<void>('download', <String, dynamic>{
        'url':
            '${ApiConfig.baseUrl}/app-releases/${Uri.encodeComponent(data['id'] as String)}/update-apk',
        'token': api.session!.accessToken,
        'checksum': data['checksumSha256'],
        'size': data['fileSize'],
        'version': data['versionCode'],
      });
      ready = true;
    } on PlatformException catch (e) {
      error =
          e.message ?? 'The update could not be downloaded. Please try again.';
    } catch (_) {
      error = 'The update could not be downloaded. Please try again.';
    } finally {
      busy = false;
      _emit();
    }
    if (ready && !_disposed) await install();
  }

  Future<void> install() async {
    if (busy || !ready || _disposed) return;
    busy = true;
    error = null;
    _emit();
    try {
      waitingForPermission =
          !(await _channel.invokeMethod<bool>('install') ?? true);
    } on PlatformException catch (e) {
      error = e.message ?? 'Could not open the installer. Please try again.';
    } finally {
      busy = false;
      _emit();
    }
  }

  Future<void> resumed() async {
    if (waitingForPermission) {
      waitingForPermission = false;
      // Retry once after Settings. A denied permission must not cause a loop.
      final allowed = await _channel.invokeMethod<bool>('canInstall') ?? false;
      if (allowed) {
        await install();
      } else {
        error = 'Allow installs from Hazra EV, then tap Install update.';
        _emit();
      }
    } else {
      await check();
    }
  }

  void _emit() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _channel.setMethodCallHandler(null);
    super.dispose();
  }
}
