import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// App-level preferences, persisted across restarts.
///
/// Backed by `shared_preferences` — the same store [TokenStore] already uses,
/// so no new dependency. Every setter writes through immediately; there is no
/// save button anywhere in the UI, so a preference that is not written on
/// change is a preference that is lost.
///
/// The plain constructor stays in-memory. Tests use it, and it keeps the
/// controller constructible without a platform channel.
class SettingsController extends ChangeNotifier {
  SettingsController() : _prefs = null;

  SettingsController._(this._prefs);

  /// Loads the stored values. Falls back to a memory-only controller when the
  /// platform store is unavailable, because losing preferences is a smaller
  /// failure than refusing to start.
  static Future<SettingsController> open() async {
    try {
      final SharedPreferences prefs = await SharedPreferences.getInstance();
      return SettingsController._(prefs).._restore();
    } catch (_) {
      return SettingsController();
    }
  }

  final SharedPreferences? _prefs;

  static const String _kThemeMode = 'settings.themeMode';
  static const String _kLanguage = 'settings.language';
  static const String _kNotificationsEnabled = 'settings.notificationsEnabled';
  static const String _kReportReminders = 'settings.reportReminders';
  static const String _kSessionReminders = 'settings.sessionReminders';
  static const String _kSystemNotifications = 'settings.systemNotifications';
  static const String _kHighAccuracyMode = 'settings.highAccuracyMode';
  static const String _kSyncOnMobileData = 'settings.syncOnMobileData';
  static const String _kBatterySaver = 'settings.batterySaver';

  ThemeMode _themeMode = ThemeMode.system;
  String _language = 'English';
  bool _notificationsEnabled = true;
  bool _reportReminders = true;
  bool _sessionReminders = true;
  bool _systemNotifications = true;
  bool _highAccuracyMode = true;
  bool _syncOnMobileData = true;
  bool _batterySaver = false;

  ThemeMode get themeMode => _themeMode;
  String get language => _language;
  bool get notificationsEnabled => _notificationsEnabled;
  bool get reportReminders => _reportReminders;
  bool get sessionReminders => _sessionReminders;
  bool get systemNotifications => _systemNotifications;
  bool get highAccuracyMode => _highAccuracyMode;
  bool get syncOnMobileData => _syncOnMobileData;
  bool get batterySaver => _batterySaver;

  void _restore() {
    final SharedPreferences? p = _prefs;
    if (p == null) return;

    final String? mode = p.getString(_kThemeMode);
    if (mode != null) {
      _themeMode = ThemeMode.values.firstWhere(
        (ThemeMode m) => m.name == mode,
        orElse: () => ThemeMode.system,
      );
    }

    _language = p.getString(_kLanguage) ?? _language;
    _notificationsEnabled =
        p.getBool(_kNotificationsEnabled) ?? _notificationsEnabled;
    _reportReminders = p.getBool(_kReportReminders) ?? _reportReminders;
    _sessionReminders = p.getBool(_kSessionReminders) ?? _sessionReminders;
    _systemNotifications =
        p.getBool(_kSystemNotifications) ?? _systemNotifications;
    _highAccuracyMode = p.getBool(_kHighAccuracyMode) ?? _highAccuracyMode;
    _syncOnMobileData = p.getBool(_kSyncOnMobileData) ?? _syncOnMobileData;
    _batterySaver = p.getBool(_kBatterySaver) ?? _batterySaver;
  }

  /// Fire-and-forget. The in-memory value is already the truth for this run;
  /// a failed write costs the preference at next launch and nothing sooner.
  void _writeBool(String key, bool value) {
    _prefs?.setBool(key, value);
  }

  void _writeString(String key, String value) {
    _prefs?.setString(key, value);
  }

  void setThemeMode(ThemeMode mode) {
    _themeMode = mode;
    _writeString(_kThemeMode, mode.name);
    notifyListeners();
  }

  void setLanguage(String value) {
    _language = value;
    _writeString(_kLanguage, value);
    notifyListeners();
  }

  void setNotificationsEnabled(bool value) {
    _notificationsEnabled = value;
    _writeBool(_kNotificationsEnabled, value);
    notifyListeners();
  }

  void setReportReminders(bool value) {
    _reportReminders = value;
    _writeBool(_kReportReminders, value);
    notifyListeners();
  }

  void setSessionReminders(bool value) {
    _sessionReminders = value;
    _writeBool(_kSessionReminders, value);
    notifyListeners();
  }

  void setSystemNotifications(bool value) {
    _systemNotifications = value;
    _writeBool(_kSystemNotifications, value);
    notifyListeners();
  }

  void setHighAccuracyMode(bool value) {
    _highAccuracyMode = value;
    _writeBool(_kHighAccuracyMode, value);
    notifyListeners();
  }

  void setSyncOnMobileData(bool value) {
    _syncOnMobileData = value;
    _writeBool(_kSyncOnMobileData, value);
    notifyListeners();
  }

  void setBatterySaver(bool value) {
    _batterySaver = value;
    _writeBool(_kBatterySaver, value);
    notifyListeners();
  }
}
