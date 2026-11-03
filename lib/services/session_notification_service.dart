import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';

/// An action the employee triggers from the ongoing session notification.
enum SessionNotificationAction { pause, endDay }

const String _kPauseButtonId = 'pause';
const String _kEndDayButtonId = 'end_day';
const String _kChannelId = 'work_session';
const int _kServiceId = 42;

/// Owns the persistent Android notification that shows a **live session timer**
/// and **Pause / End Day** buttons while a work session is open.
///
/// It is a thin wrapper over `flutter_foreground_task`. Two jobs:
///
///  1. Run a *location* foreground service so the app process — and therefore
///     the main-isolate GPS stream in `TrackingController` — keeps running with
///     the screen off. This replaces geolocator's own foreground-service
///     notification, so there is exactly one ongoing notification, not two.
///  2. Post and keep updating that notification (the timer ticks once a second
///     from the controller's clock) and report button taps back to the main
///     isolate through [actions].
///
/// Android only. On every other platform all methods are no-ops: iOS has no
/// equivalent ongoing foreground notification, and tracking there only runs
/// while the app is open.
class SessionNotificationService {
  SessionNotificationService();

  final StreamController<SessionNotificationAction> _actions =
      StreamController<SessionNotificationAction>.broadcast();

  /// Button taps coming back from the notification, delivered on the main
  /// isolate. `main.dart` listens and drives the controller.
  Stream<SessionNotificationAction> get actions => _actions.stream;

  bool get _supported => defaultTargetPlatform == TargetPlatform.android;

  bool _initialized = false;
  // Cached so the per-second [update] does not make a platform round-trip just
  // to ask whether the service is up.
  bool _running = false;

  void _onData(Object data) {
    switch (data) {
      case _kPauseButtonId:
        _actions.add(SessionNotificationAction.pause);
      case _kEndDayButtonId:
        _actions.add(SessionNotificationAction.endDay);
    }
  }

  /// Wires the isolate port and notification channel. Safe to call more than
  /// once; only the first call does the work. Call it once at startup.
  Future<void> init() async {
    if (!_supported || _initialized) return;
    _initialized = true;

    FlutterForegroundTask.initCommunicationPort();
    FlutterForegroundTask.addTaskDataCallback(_onData);

    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: _kChannelId,
        channelName: 'Work session tracking',
        channelDescription:
            'Shows your running session timer while your location is recorded.',
        // LOW keeps the notification silent and non-intrusive while still
        // ongoing; onlyAlertOnce stops the once-a-second timer update from
        // buzzing or re-announcing.
        channelImportance: NotificationChannelImportance.LOW,
        priority: NotificationPriority.LOW,
        onlyAlertOnce: true,
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: false,
        playSound: false,
      ),
      foregroundTaskOptions: ForegroundTaskOptions(
        // The timer is pushed from the main isolate on the controller's own
        // one-second clock, so the service isolate needs no repeat event.
        eventAction: ForegroundTaskEventAction.nothing(),
        autoRunOnBoot: false,
        allowWakeLock: true,
        allowWifiLock: false,
      ),
    );
  }

  /// Starts (or, if already up, refreshes) the ongoing notification for an open
  /// session. Idempotent.
  Future<void> start(Duration elapsed) async {
    if (!_supported) return;
    await init();

    try {
      // Android 13+ gates the notification behind a runtime permission.
      final NotificationPermission permission =
          await FlutterForegroundTask.checkNotificationPermission();
      if (permission != NotificationPermission.granted) {
        await FlutterForegroundTask.requestNotificationPermission();
      }

      if (await FlutterForegroundTask.isRunningService) {
        _running = true;
        await update(elapsed);
        return;
      }

      final ServiceRequestResult result =
          await FlutterForegroundTask.startService(
        serviceId: _kServiceId,
        serviceTypes: const <ForegroundServiceTypes>[
          ForegroundServiceTypes.location,
        ],
        notificationTitle: 'Work session running',
        notificationText: _timerLine(elapsed),
        notificationButtons: _buttons(),
        callback: startSessionNotificationCallback,
      );

      _running = result is ServiceRequestSuccess;
      if (!_running) {
        debugPrint('[session-notif] start failed: $result');
      }
    } catch (e) {
      // The notification is a comfort, never the tracking itself — a failure
      // here must never take a work session down with it.
      debugPrint('[session-notif] start error: $e');
    }
  }

  /// Pushes the latest elapsed time into the live notification.
  Future<void> update(Duration elapsed) async {
    if (!_supported || !_running) return;
    try {
      await FlutterForegroundTask.updateService(
        notificationTitle: 'Work session running',
        notificationText: _timerLine(elapsed),
        notificationButtons: _buttons(),
      );
    } catch (e) {
      debugPrint('[session-notif] update error: $e');
    }
  }

  /// Tears the notification and foreground service down when the session ends.
  Future<void> stop() async {
    if (!_supported || !_initialized) return;
    _running = false;
    try {
      if (await FlutterForegroundTask.isRunningService) {
        await FlutterForegroundTask.stopService();
      }
    } catch (e) {
      debugPrint('[session-notif] stop error: $e');
    }
  }

  List<NotificationButton> _buttons() => const <NotificationButton>[
        NotificationButton(id: _kPauseButtonId, text: 'Pause'),
        NotificationButton(id: _kEndDayButtonId, text: 'End Day'),
      ];

  String _timerLine(Duration d) {
    String two(int n) => n.toString().padLeft(2, '0');
    final String clock =
        '${two(d.inHours)}:${two(d.inMinutes.remainder(60))}:${two(d.inSeconds.remainder(60))}';
    return '$clock · your route is being recorded';
  }

  void dispose() {
    if (_supported) {
      FlutterForegroundTask.removeTaskDataCallback(_onData);
    }
    _actions.close();
  }
}

/// Entry point for the foreground-service isolate.
///
/// Its only job is to forward notification-button taps to the main isolate
/// (where `TrackingController` lives) and to bring the app forward for End Day,
/// which needs the in-app closeout form.
@pragma('vm:entry-point')
void startSessionNotificationCallback() {
  FlutterForegroundTask.setTaskHandler(_SessionTaskHandler());
}

class _SessionTaskHandler extends TaskHandler {
  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {}

  @override
  void onRepeatEvent(DateTime timestamp) {}

  @override
  Future<void> onDestroy(DateTime timestamp, bool isTimeout) async {}

  @override
  void onNotificationButtonPressed(String id) {
    // Ending the day needs the six-field closeout form, so surface the app;
    // Pause runs headless on the main isolate.
    if (id == _kEndDayButtonId) {
      FlutterForegroundTask.launchApp();
    }
    FlutterForegroundTask.sendDataToMain(id);
  }

  @override
  void onNotificationPressed() {
    FlutterForegroundTask.launchApp();
  }
}
