import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../data/repositories/employee_repository.dart';

/// App-level preferences, persisted across restarts and synced to the server.
///
/// Three layers, in order of authority:
///
/// 1. **The server** — `GET/PATCH /me/preferences` holds the nine fields
///    one-for-one. It is the source of truth: the same account on a second
///    device must see the same settings, and the admin `TrackingConfig`
///    ceiling can override the last three flags outright.
/// 2. **`shared_preferences`** — the offline cache, the same store
///    [TokenStore] already uses, so no new dependency. It is what the screen
///    renders at launch, before (and if) the network answers.
/// 3. **Memory** — always the value the seller last tapped, so the switch
///    never lags behind the finger.
///
/// Every setter writes through immediately: there is no save button anywhere
/// in the UI, so a preference that is not written on change is a preference
/// that is lost.
///
/// The plain constructor stays in-memory and unsynced. Tests use it, and it
/// keeps the controller constructible without a platform channel.
class SettingsController extends ChangeNotifier {
  SettingsController() : _prefs = null;

  SettingsController._(this._prefs);

  /// Loads the cached values. Falls back to a memory-only controller when the
  /// platform store is unavailable, because losing preferences is a smaller
  /// failure than refusing to start.
  ///
  /// Pass [repository] to sync with the server as well; without it the
  /// controller behaves exactly as it did before — local only. The composition
  /// root can also call [attach] later, which is the same thing.
  static Future<SettingsController> open({
    EmployeeRepository? repository,
  }) async {
    SettingsController controller;

    try {
      final SharedPreferences prefs = await SharedPreferences.getInstance();
      controller = SettingsController._(prefs).._restore();
    } catch (_) {
      controller = SettingsController();
    }

    if (repository != null) {
      // Not awaited: the cached values are already correct enough to render,
      // and blocking the splash on a network round trip buys nothing.
      unawaited(controller.attach(repository));
    }

    return controller;
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

  /// Wire field names, as `PATCH /me/preferences` expects them. Kept separate
  /// from the storage keys above because the two namespaces are unrelated and
  /// coupling them would be an accident waiting to happen.
  static const String _wThemeMode = 'themeMode';
  static const String _wLanguage = 'language';
  static const String _wNotificationsEnabled = 'notificationsEnabled';
  static const String _wReportReminders = 'reportReminders';
  static const String _wSessionReminders = 'sessionReminders';
  static const String _wSystemNotifications = 'systemNotifications';
  static const String _wHighAccuracyMode = 'highAccuracyMode';
  static const String _wSyncOnMobileData = 'syncOnMobileData';
  static const String _wBatterySaver = 'batterySaver';

  /// A flurry of taps in the Settings screen is one intent, not six. Coalesce
  /// them into a single partial PATCH.
  static const Duration _debounce = Duration(milliseconds: 400);

  ThemeMode _themeMode = ThemeMode.system;
  String _language = 'English';
  bool _notificationsEnabled = true;
  bool _reportReminders = true;
  bool _sessionReminders = true;
  bool _systemNotifications = true;
  bool _highAccuracyMode = true;
  bool _syncOnMobileData = true;
  bool _batterySaver = false;

  EmployeeRepository? _repository;

  /// Changed values waiting to reach the server, keyed by wire field name.
  /// Also acts as the guard that stops an in-flight response from undoing a
  /// tap the seller made while it was on the wire.
  final Map<String, Object> _pending = <String, Object>{};

  Timer? _flush;
  bool _syncing = false;
  Object? _syncError;
  bool _disposed = false;

  ThemeMode get themeMode => _themeMode;
  String get language => _language;
  bool get notificationsEnabled => _notificationsEnabled;
  bool get reportReminders => _reportReminders;
  bool get sessionReminders => _sessionReminders;
  bool get systemNotifications => _systemNotifications;
  bool get highAccuracyMode => _highAccuracyMode;
  bool get syncOnMobileData => _syncOnMobileData;
  bool get batterySaver => _batterySaver;

  /// True once [attach] has been called. False means every value on this
  /// controller is device-local and will never reach the server.
  bool get isServerBacked => _repository != null;

  /// A read or a write is in flight.
  bool get isSyncing => _syncing;

  /// The last sync failure, or null. Not thrown: a field team is offline for
  /// most of the day and the cached values are still the best answer there is.
  /// The Settings screen can surface it; nothing has to.
  Object? get syncError => _syncError;

  /// True when a change is still waiting to reach the server.
  bool get hasUnsyncedChanges => _pending.isNotEmpty;

  // ----------------------------------------------------------------- server

  /// Points the controller at the server and pulls once.
  ///
  /// Separate from [open] because the composition root builds the repository
  /// and the controller independently; call this as soon as both exist. Until
  /// it is called the controller behaves exactly as the local-only version did.
  Future<void> attach(EmployeeRepository repository) {
    _repository = repository;
    return pull();
  }

  /// `GET /me/preferences`. Whatever comes back wins over the cache — the same
  /// account on another device, or an admin tracking policy, can have moved a
  /// value since this device last looked.
  Future<void> pull() async {
    final EmployeeRepository? repository = _repository;
    if (repository == null) return;

    _syncing = true;
    _notify();

    try {
      _adopt(await repository.preferences());
      _syncError = null;
    } catch (e) {
      _syncError = e;
    } finally {
      _syncing = false;
      _notify();
    }
  }

  /// Sends anything still queued immediately instead of waiting out the
  /// debounce. Worth calling when the Settings screen is popped.
  Future<void> flush() {
    _flush?.cancel();
    _flush = null;
    return _flushPending();
  }

  void _queue(String field, Object value) {
    if (_repository == null) return;

    _pending[field] = value;
    _flush?.cancel();
    _flush = Timer(_debounce, _flushPending);
  }

  Future<void> _flushPending() async {
    final EmployeeRepository? repository = _repository;
    if (repository == null || _pending.isEmpty) return;

    final Map<String, Object> batch = Map<String, Object>.from(_pending);
    _pending.clear();

    _syncing = true;
    _notify();

    try {
      _adopt(
        await repository.savePreferences(
          themeMode: batch[_wThemeMode] as String?,
          language: batch[_wLanguage] as String?,
          notificationsEnabled: batch[_wNotificationsEnabled] as bool?,
          reportReminders: batch[_wReportReminders] as bool?,
          sessionReminders: batch[_wSessionReminders] as bool?,
          systemNotifications: batch[_wSystemNotifications] as bool?,
          highAccuracyMode: batch[_wHighAccuracyMode] as bool?,
          syncOnMobileData: batch[_wSyncOnMobileData] as bool?,
          batterySaver: batch[_wBatterySaver] as bool?,
        ),
      );
      _syncError = null;
    } catch (e) {
      _syncError = e;

      // Put the batch back so the next flush carries it. The device keeps
      // showing what the seller chose regardless — the cache already has it —
      // this only decides what the server eventually learns.
      batch.forEach((String field, Object value) {
        _pending.putIfAbsent(field, () => value);
      });
    } finally {
      _syncing = false;
      _notify();
    }
  }

  /// Takes the server's copy, field by field, and refreshes the cache with it.
  ///
  /// A field the seller has changed since the request went out is still in
  /// [_pending]; adopting the server's older value for it would visibly undo
  /// their tap, so those are skipped and the queued write settles it instead.
  void _adopt(DevicePreferences p) {
    if (!_pending.containsKey(_wThemeMode)) {
      // An unrecognised string keeps the current mode rather than snapping to
      // system — a server that grows a fourth theme should not reset anyone.
      _themeMode = ThemeMode.values.firstWhere(
        (ThemeMode m) => m.name == p.themeMode,
        orElse: () => _themeMode,
      );
      _writeString(_kThemeMode, _themeMode.name);
    }

    if (!_pending.containsKey(_wLanguage)) {
      _language = p.language;
      _writeString(_kLanguage, _language);
    }

    if (!_pending.containsKey(_wNotificationsEnabled)) {
      _notificationsEnabled = p.notificationsEnabled;
      _writeBool(_kNotificationsEnabled, _notificationsEnabled);
    }

    if (!_pending.containsKey(_wReportReminders)) {
      _reportReminders = p.reportReminders;
      _writeBool(_kReportReminders, _reportReminders);
    }

    if (!_pending.containsKey(_wSessionReminders)) {
      _sessionReminders = p.sessionReminders;
      _writeBool(_kSessionReminders, _sessionReminders);
    }

    if (!_pending.containsKey(_wSystemNotifications)) {
      _systemNotifications = p.systemNotifications;
      _writeBool(_kSystemNotifications, _systemNotifications);
    }

    if (!_pending.containsKey(_wHighAccuracyMode)) {
      _highAccuracyMode = p.highAccuracyMode;
      _writeBool(_kHighAccuracyMode, _highAccuracyMode);
    }

    if (!_pending.containsKey(_wSyncOnMobileData)) {
      _syncOnMobileData = p.syncOnMobileData;
      _writeBool(_kSyncOnMobileData, _syncOnMobileData);
    }

    if (!_pending.containsKey(_wBatterySaver)) {
      _batterySaver = p.batterySaver;
      _writeBool(_kBatterySaver, _batterySaver);
    }
  }

  // ------------------------------------------------------------ local cache

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

  void _notify() {
    if (_disposed) return;
    notifyListeners();
  }

  // ---------------------------------------------------------------- setters

  void setThemeMode(ThemeMode mode) {
    _themeMode = mode;
    _writeString(_kThemeMode, mode.name);
    _queue(_wThemeMode, mode.name);
    _notify();
  }

  void setLanguage(String value) {
    _language = value;
    _writeString(_kLanguage, value);
    _queue(_wLanguage, value);
    _notify();
  }

  void setNotificationsEnabled(bool value) {
    _notificationsEnabled = value;
    _writeBool(_kNotificationsEnabled, value);
    _queue(_wNotificationsEnabled, value);
    _notify();
  }

  void setReportReminders(bool value) {
    _reportReminders = value;
    _writeBool(_kReportReminders, value);
    _queue(_wReportReminders, value);
    _notify();
  }

  void setSessionReminders(bool value) {
    _sessionReminders = value;
    _writeBool(_kSessionReminders, value);
    _queue(_wSessionReminders, value);
    _notify();
  }

  void setSystemNotifications(bool value) {
    _systemNotifications = value;
    _writeBool(_kSystemNotifications, value);
    _queue(_wSystemNotifications, value);
    _notify();
  }

  /// Clamped server-side against the admin `TrackingConfig` ceiling — a device
  /// may only ask for *less* collection than policy allows, never more — so
  /// the value that sticks can differ from the one passed here. [pull] and the
  /// PATCH response both write the real answer back.
  void setHighAccuracyMode(bool value) {
    _highAccuracyMode = value;
    _writeBool(_kHighAccuracyMode, value);
    _queue(_wHighAccuracyMode, value);
    _notify();
  }

  /// See [setHighAccuracyMode] — same ceiling.
  void setSyncOnMobileData(bool value) {
    _syncOnMobileData = value;
    _writeBool(_kSyncOnMobileData, value);
    _queue(_wSyncOnMobileData, value);
    _notify();
  }

  /// See [setHighAccuracyMode] — same ceiling.
  void setBatterySaver(bool value) {
    _batterySaver = value;
    _writeBool(_kBatterySaver, value);
    _queue(_wBatterySaver, value);
    _notify();
  }

  @override
  void dispose() {
    _disposed = true;
    _flush?.cancel();
    _flush = null;
    super.dispose();
  }
}
