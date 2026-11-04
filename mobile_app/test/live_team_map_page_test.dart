import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/admin/map/live_team_map.dart';
import 'package:employeetracking_mobile_app/features/admin/map/live_team_map_page.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The screen builds, on both layouts, and keeps re-posting the watch.
///
/// Tiles never load here (the test binding answers every HTTP request with a
/// 400), which is itself worth exercising: the live map has to be a working
/// screen with an unreachable basemap, because that is also a field phone on a
/// bad network.

/// Records every `/tracking/watch` call so the re-post loop can be asserted
/// on, and can be switched to answering 404 — a server that predates the
/// endpoint, which is what production is today.
class WatchRepo extends MockAdminRepository {
  WatchRepo({this.ttlSeconds = 120, this.notFound = false})
      : super(latency: Duration.zero);

  final int ttlSeconds;

  /// Answer 404, the way every server does today: `POST /tracking/watch` is
  /// specced and not built.
  final bool notFound;

  final List<List<String>> calls = <List<String>>[];

  @override
  Future<LiveWatch> watchLive({
    required List<String> employeeIds,
    int? ttlSeconds,
  }) async {
    calls.add(employeeIds);
    if (notFound) {
      throw const ApiException(
        code: 'NOT_FOUND',
        message: 'no route POST /tracking/watch',
        status: 404,
      );
    }
    final DateTime until =
        DateTime.now().add(Duration(seconds: this.ttlSeconds));
    return LiveWatch(
      ttlSeconds: this.ttlSeconds,
      watchedUntil: until,
      watching: employeeIds
          .map((String id) => WatchedEmployee(
                employeeId: id,
                watchingUntil: until,
                extended: false,
              ))
          .toList(growable: false),
      rejected: const <RejectedWatch>[],
    );
  }

  @override
  Future<TrackingConfig> config() async => TrackingConfig.defaults;
}

class MapHarness {
  MapHarness({
    required this.app,
    required this.tracking,
    required this.location,
    required this.admin,
  });

  final Widget app;
  final TrackingController tracking;
  final MockLocationService location;
  final WatchRepo admin;

  Future<void> shutDown(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    tracking.dispose();
    location.dispose();
    await tester.pump(const Duration(seconds: 32));
  }
}

MapHarness buildPage({WatchRepo? admin}) {
  final ProductStore products = ProductStore();
  final NotificationCenter notifications = NotificationCenter();
  final EmployeeRepository repository = MockEmployeeRepository(
    latency: Duration.zero,
    products: products,
    notifications: notifications,
  );
  final WatchRepo adminRepository = admin ?? WatchRepo();
  final MockLocationService location =
      MockLocationService(emitInterval: const Duration(seconds: 30));
  final TrackingController tracking = TrackingController(
    repository: repository,
    locationService: location,
  );

  return MapHarness(
    tracking: tracking,
    location: location,
    admin: adminRepository,
    app: AppScope(
      repository: repository,
      adminRepository: adminRepository,
      companies: CompanyDirectory(() => repository.companies()),
      locationService: location,
      tracking: tracking,
      settings: SettingsController(),
      notifications: notifications,
      child: const MaterialApp(home: LiveTeamMapPage()),
    ),
  );
}

void main() {
  testWidgets('the phone layout renders the map plus a roster sheet',
      (WidgetTester tester) async {
    final MapHarness h = buildPage();
    await tester.pumpWidget(h.app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));

    expect(find.text('Live team map'), findsOneWidget);
    expect(find.byType(LiveTeamMap), findsOneWidget);
    expect(find.byType(DraggableScrollableSheet), findsOneWidget);

    await h.shutDown(tester);
  });

  testWidgets('a watch goes out for the whole visible team in one call',
      (WidgetTester tester) async {
    final MapHarness h = buildPage();
    await tester.pumpWidget(h.app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));

    expect(h.admin.calls, hasLength(1),
        reason: 'opening a map of eight is one request, not eight');
    expect(h.admin.calls.single.length, greaterThan(1));

    await h.shutDown(tester);
  });

  testWidgets('the watch is re-posted at roughly half the granted TTL',
      (WidgetTester tester) async {
    // A short TTL so the renew lands inside a test rather than 60 s later.
    final MapHarness h = buildPage(admin: WatchRepo(ttlSeconds: 30));
    await tester.pumpWidget(h.app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));
    expect(h.admin.calls, hasLength(1));

    // renewInterval floors at 15 s; one dropped call must not drop the team
    // out of live mode, which is the whole reason this loop exists.
    await tester.pump(const Duration(seconds: 16));
    await tester.pump();

    expect(h.admin.calls.length, greaterThanOrEqualTo(2));

    await h.shutDown(tester);
  });

  testWidgets('a server with no watch endpoint is asked once, and said so',
      (WidgetTester tester) async {
    final MapHarness h = buildPage(admin: WatchRepo(notFound: true));
    await tester.pumpWidget(h.app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));

    // The map is a working map. It simply cannot promise 15 s data, and it
    // does not pretend to — nor does it retry a 404 every minute.
    expect(find.byType(LiveTeamMap), findsOneWidget);
    expect(h.admin.calls, hasLength(1));

    await tester.pump(const Duration(seconds: 20));
    await tester.pump();
    expect(h.admin.calls, hasLength(1),
        reason: 'a 404 is permanent; re-asking is noise');

    await h.shutDown(tester);
  });

  testWidgets('the wide layout puts the same roster in a side panel',
      (WidgetTester tester) async {
    tester.view.physicalSize = const Size(1600, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final MapHarness h = buildPage();
    await tester.pumpWidget(h.app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));

    expect(find.byType(LiveTeamMap), findsOneWidget,
        reason: 'one map, one camera — the layout changes, not the screen');
    expect(find.byType(DraggableScrollableSheet), findsNothing);

    await h.shutDown(tester);
  });
}
