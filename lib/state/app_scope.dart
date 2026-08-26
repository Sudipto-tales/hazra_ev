import 'package:flutter/widgets.dart';

import '../data/api/api_client.dart';
import '../data/company_directory.dart';
import '../data/repositories/admin_repository.dart';
import '../data/repositories/employee_repository.dart';
import '../data/repositories/tracking_repository.dart';
import '../services/location_service.dart';
import 'notification_center.dart';
import 'settings_controller.dart';
import 'tracking_controller.dart';

/// Minimal dependency injection — no third-party state package needed.
///
/// `AppScope.of(context)` reads the container; the individual controllers are
/// listened to with [ListenableBuilder] where a rebuild is actually wanted.
class AppScope extends InheritedWidget {
  const AppScope({
    super.key,
    required this.repository,
    required this.adminRepository,
    required this.companies,
    required this.locationService,
    required this.tracking,
    required this.settings,
    required this.notifications,
    required super.child,
    this.trackingRepository = const NoopTrackingRepository(),
    this.api,
  });

  final EmployeeRepository repository;

  /// Admin console backend. Separate from [repository] because the two speak
  /// different contracts: `/api/employee/*` is implicitly "me", `/api/admin/*`
  /// takes an employee id.
  final AdminRepository adminRepository;

  /// Id → name for companies and branches. Visits carry ids only, so every
  /// screen that renders a visit label reads this instead of guessing.
  /// Call `ensureLoaded()` from the screen's own load path.
  final CompanyDirectory companies;

  /// The tracking write path. [TrackingController] is its main caller — this
  /// is exposed so a screen that needs to write a session or a fix does not
  /// reach for `http` on its own. Defaults to the no-op so a test tree can be
  /// built without a server.
  final TrackingRepository trackingRepository;

  final LocationService locationService;
  final TrackingController tracking;
  final SettingsController settings;

  /// Shared by both shells: the admin writes to it when a product is listed,
  /// the employee's bell listens to it. Same instance the two mock
  /// repositories were constructed with.
  final NotificationCenter notifications;

  /// Null when the app was started with `--dart-define=USE_MOCKS=true`.
  /// The sign-in screens read it to authenticate; everything else goes through
  /// the two repositories and never sees it.
  final ApiClient? api;

  /// True when the app is talking to a real server, which is what decides
  /// whether the sign-in forms validate credentials or just continue.
  bool get isLive => api != null;

  static AppScope of(BuildContext context) {
    final AppScope? scope =
        context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope missing above this widget');
    return scope!;
  }

  @override
  bool updateShouldNotify(AppScope oldWidget) =>
      repository != oldWidget.repository ||
      adminRepository != oldWidget.adminRepository ||
      companies != oldWidget.companies ||
      trackingRepository != oldWidget.trackingRepository ||
      api != oldWidget.api ||
      tracking != oldWidget.tracking ||
      settings != oldWidget.settings ||
      notifications != oldWidget.notifications;
}
