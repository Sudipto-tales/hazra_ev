import 'package:flutter/material.dart';

/// App-level preferences. Backed by memory in this design pass; swap the
/// setters for a persisted store (shared_preferences / secure storage) later.
class SettingsController extends ChangeNotifier {
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

  void setThemeMode(ThemeMode mode) {
    _themeMode = mode;
    notifyListeners();
  }

  void setLanguage(String value) {
    _language = value;
    notifyListeners();
  }

  void setNotificationsEnabled(bool value) {
    _notificationsEnabled = value;
    notifyListeners();
  }

  void setReportReminders(bool value) {
    _reportReminders = value;
    notifyListeners();
  }

  void setSessionReminders(bool value) {
    _sessionReminders = value;
    notifyListeners();
  }

  void setSystemNotifications(bool value) {
    _systemNotifications = value;
    notifyListeners();
  }

  void setHighAccuracyMode(bool value) {
    _highAccuracyMode = value;
    notifyListeners();
  }

  void setSyncOnMobileData(bool value) {
    _syncOnMobileData = value;
    notifyListeners();
  }

  void setBatterySaver(bool value) {
    _batterySaver = value;
    notifyListeners();
  }
}
