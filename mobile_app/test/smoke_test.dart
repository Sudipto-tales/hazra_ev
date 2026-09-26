import 'package:employeetracking_mobile_app/app.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/repositories/admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// One app plus the handle needed to shut its timers down.
///
/// [TrackingController] runs a 1 s ticker and [MockLocationService] a periodic
/// emitter; the test binding fails a test that ends with either still pending,
/// so every test has to call [shutDown] before it returns.
class _Harness {
  _Harness({
    required this.app,
    required this.tracking,
    required this.location,
    required this.notifications,
  });

  final Widget app;
  final TrackingController tracking;
  final MockLocationService location;
  final NotificationCenter notifications;

  static const Duration emitInterval = Duration(seconds: 30);

  Future<void> shutDown(WidgetTester tester) async {
    // Tear the tree down first so nothing schedules new work, then cancel the
    // subscriptions, then let any delay already in flight expire.
    await tester.pumpWidget(const SizedBox.shrink());
    tracking.dispose();
    location.dispose();
    await tester.pump(emitInterval + const Duration(seconds: 2));
  }
}

_Harness buildApp() {
  // One catalogue and one feed shared by both repositories, exactly as
  // `main.dart` wires it.
  final ProductStore products = ProductStore();
  final NotificationCenter notifications = NotificationCenter();

  final EmployeeRepository repository = MockEmployeeRepository(
    latency: Duration.zero,
    products: products,
    notifications: notifications,
  );
  final AdminRepository adminRepository = MockAdminRepository(
    latency: Duration.zero,
    products: products,
    notifications: notifications,
  );
  final MockLocationService location = MockLocationService(
    emitInterval: _Harness.emitInterval,
  );
  final TrackingController tracking = TrackingController(
    repository: repository,
    locationService: location,
  );

  return _Harness(
    tracking: tracking,
    location: location,
    notifications: notifications,
    app: AppScope(
      repository: repository,
      adminRepository: adminRepository,
      companies: CompanyDirectory(() => repository.companies()),
      locationService: location,
      tracking: tracking,
      settings: SettingsController(),
      notifications: notifications,
      child: const TrackingApp(),
    ),
  );
}

/// Scrolls [finder] into view before tapping. Both login forms are taller than
/// the 800×600 test surface, so a blind tap lands on nothing.
Future<void> scrollAndTap(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
}

void main() {
  testWidgets('role select offers both entry points', (
    WidgetTester tester,
  ) async {
    final _Harness h = buildApp();
    await tester.pumpWidget(h.app);

    expect(find.text('Field Tracker'), findsOneWidget);
    expect(find.text('Employee Login'), findsOneWidget);
    expect(find.text('Restricted'), findsOneWidget);
    expect(find.text('Authorized Personnel Only'), findsOneWidget);

    await h.shutDown(tester);
  });

  testWidgets('employee card leads to the employee shell', (
    WidgetTester tester,
  ) async {
    final _Harness h = buildApp();
    await tester.pumpWidget(h.app);

    await tester.tap(find.text('Employee Login'));
    await tester.pumpAndSettle();

    expect(find.text('Employee access'), findsOneWidget);

    await scrollAndTap(tester, find.text('Sign in'));
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();

    expect(find.text('Home'), findsOneWidget);
    expect(find.text('Reports'), findsWidgets);
    expect(find.text('Calendar'), findsOneWidget);
    expect(find.text('Profile'), findsOneWidget);

    await h.shutDown(tester);
  });

  testWidgets('restricted card leads to the admin console', (
    WidgetTester tester,
  ) async {
    final _Harness h = buildApp();
    await tester.pumpWidget(h.app);

    await tester.tap(find.text('Restricted'));
    await tester.pumpAndSettle();

    expect(find.text('Admin console'), findsOneWidget);
    expect(find.text('Authorized personnel only'), findsOneWidget);

    await scrollAndTap(tester, find.text('Enter admin console'));
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard'), findsWidgets);
    expect(find.text('Team'), findsWidgets);
    expect(find.text('Admin'), findsWidgets);

    // The catalogue is a pushed route behind the Admin tab, not a destination —
    // same convention as the employee Profile tab and its Settings screen.
    await tester.tap(find.text('Admin').last);
    await tester.pumpAndSettle();
    await scrollAndTap(tester, find.text('Products'));
    await tester.pumpAndSettle();

    expect(find.text('List product'), findsWidgets);

    await h.shutDown(tester);
  });
}
