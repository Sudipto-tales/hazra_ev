import 'package:employeetracking_mobile_app/app.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/repositories/admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/admin/shell/admin_shell.dart';
import 'package:employeetracking_mobile_app/features/auth/role_select_page.dart';
import 'package:employeetracking_mobile_app/features/shell/main_shell.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The sign-in back stack.
///
/// [RoleSelectPage] *pushes* each login form, so a login that finishes with
/// `pushReplacement` only swaps the form out and leaves the role picker
/// underneath: Android back on Home then walks a signed-in user back to the
/// sign-in screen. Both forms use `pushAndRemoveUntil` instead, and these
/// tests are what keep it that way — nothing else in the suite looks at the
/// stack *below* the shell.
///
/// Same harness as `smoke_test.dart`: no `api` in the [AppScope], so
/// `scope.isLive` is false and both forms take their stubbed-success path (a
/// short delay, then navigate) with no server involved.
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

  /// [TrackingController] runs a 1 s ticker and [MockLocationService] a
  /// periodic emitter; the test binding fails a test that ends with either
  /// still pending, so every test has to call this before it returns.
  Future<void> shutDown(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    tracking.dispose();
    location.dispose();
    await tester.pump(emitInterval + const Duration(seconds: 2));
  }
}

_Harness buildApp() {
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

/// Waits out the stubbed 600 ms sign-in delay and the route transition.
///
/// Deliberately not `pumpAndSettle`: the employee shell runs a continuous
/// animation, so settling never completes there (the same reason
/// `smoke_test.dart` pumps fixed frames on that path).
Future<void> settleSignIn(WidgetTester tester) async {
  await tester.pump(const Duration(seconds: 1));
  // The replaced routes are only disposed once the incoming transition has
  // finished, so the stack is not actually empty until a few frames later.
  for (int i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  testWidgets('employee sign-in leaves nothing under the shell',
      (WidgetTester tester) async {
    final _Harness h = buildApp();
    await tester.pumpWidget(h.app);

    await tester.tap(find.text('Employee Login'));
    await tester.pumpAndSettle();
    expect(find.text('Employee access'), findsOneWidget);
    // The form is pushed, so at this point there *is* something to pop. That
    // is the precondition the bug came from.
    expect(Navigator.of(tester.element(find.text('Employee access'))).canPop(),
        isTrue);

    await scrollAndTap(tester, find.text('Sign in'));
    await settleSignIn(tester);

    expect(find.byType(MainShell), findsOneWidget);
    expect(
      Navigator.of(tester.element(find.byType(MainShell))).canPop(),
      isFalse,
      reason: 'the shell must be the only route after sign-in',
    );
    expect(find.byType(RoleSelectPage, skipOffstage: false), findsNothing);

    // And an actual Android back press changes nothing.
    await tester.binding.handlePopRoute();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(MainShell), findsOneWidget);
    expect(find.byType(RoleSelectPage, skipOffstage: false), findsNothing);

    await h.shutDown(tester);
  });

  testWidgets('admin sign-in leaves nothing under the console',
      (WidgetTester tester) async {
    final _Harness h = buildApp();
    await tester.pumpWidget(h.app);

    await tester.tap(find.text('Restricted'));
    await tester.pumpAndSettle();
    expect(find.text('Admin console'), findsOneWidget);
    expect(Navigator.of(tester.element(find.text('Admin console'))).canPop(),
        isTrue);

    await scrollAndTap(tester, find.text('Enter admin console'));
    await settleSignIn(tester);

    expect(find.byType(AdminShell), findsOneWidget);
    expect(
      Navigator.of(tester.element(find.byType(AdminShell))).canPop(),
      isFalse,
      reason: 'the console must be the only route after sign-in',
    );
    expect(find.byType(RoleSelectPage, skipOffstage: false), findsNothing);

    await tester.binding.handlePopRoute();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(AdminShell), findsOneWidget);
    expect(find.byType(RoleSelectPage, skipOffstage: false), findsNothing);

    await h.shutDown(tester);
  });
}
