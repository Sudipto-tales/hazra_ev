import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/company_directory.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/features/admin/map/route_map_page.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/app_scope.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:employeetracking_mobile_app/state/settings_controller.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:employeetracking_mobile_app/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The route screen has to *say* when a stretch of the day was recorded on a
/// weak fix. The flag existed end to end for weeks and was invisible: the
/// painter, the tile map and every row read a clean trail and a confirmed stop
/// out of GPS jitter. These tests hold the visible half of that down.
///
/// Tiles never load here (the test binding answers every request with a 400),
/// which is fine — the rows under the map are what is being asserted.

final DateTime _day = DateTime(2026, 11, 5);

Employee _employee() => Employee(
      id: 'e1',
      name: 'Asha Roy',
      employeeCode: 'EMP-001',
      designation: 'Sales Executive',
      department: 'Sales',
      email: 'asha@example.com',
      phone: '9000000000',
      region: 'Bankura',
      reportingTo: 'ADM-001',
      joinedOn: DateTime(2025, 1, 1),
      avatarUrl: '',
      bannerUrl: '',
      bloodGroup: '',
      address: '',
    );

LocationLog _fix({
  required int minute,
  required double accuracy,
  double lat = 23.24,
  double lng = 87.86,
}) =>
    LocationLog(
      id: 'f$minute',
      sessionId: 's1',
      latitude: lat,
      longitude: lng,
      accuracy: accuracy,
      speedKmh: 10,
      recordedAt: _day.add(Duration(hours: 9, minutes: minute)),
      syncState: SyncState.synced,
    );

/// A day with one unlinked stop and one visit.
///
/// [weak] swaps the accuracy of every fix behind both of them from a healthy
/// ±9 m to a ±130 m scrape, and nothing else — same coordinates, same times,
/// same stop and visit records. Any difference the tests see is the accuracy
/// treatment and nothing else.
RouteTrack _track({required bool weak}) {
  final double dwellAccuracy = weak ? 130 : 9;

  final List<LocationLog> points = <LocationLog>[
    // Travel leg in — always clean, so a rough stop cannot be mistaken for a
    // wholly rough day.
    for (int i = 0; i < 4; i++)
      _fix(minute: i, accuracy: 8, lat: 23.235 + i * 0.001, lng: 87.855),
    // Dwell at the unlinked stop.
    for (int i = 0; i < 4; i++)
      _fix(minute: 10 + i, accuracy: dwellAccuracy, lat: 23.24, lng: 87.86),
    // Travel leg on.
    for (int i = 0; i < 4; i++)
      _fix(minute: 20 + i, accuracy: 8, lat: 23.245 + i * 0.001, lng: 87.865),
    // Dwell at the visit.
    for (int i = 0; i < 4; i++)
      _fix(minute: 30 + i, accuracy: dwellAccuracy, lat: 23.25, lng: 87.87),
  ];

  return RouteTrack(
    employeeId: 'e1',
    date: _day,
    points: points,
    sessions: <WorkSession>[
      WorkSession(
        id: 's1',
        index: 1,
        startTime: _day.add(const Duration(hours: 9)),
        endTime: _day.add(const Duration(hours: 18)),
        distanceKm: 4,
        locationPoints: points.length,
        startLatitude: 23.235,
        startLongitude: 87.855,
      ),
    ],
    stops: <StopRecord>[
      StopRecord(
        id: 'stop1',
        sessionId: 's1',
        arrival: _day.add(const Duration(hours: 9, minutes: 10)),
        departure: _day.add(const Duration(hours: 9, minutes: 13)),
        latitude: 23.24,
        longitude: 87.86,
        radiusMetres: 40,
        visitId: null,
      ),
    ],
    visits: <CompanyVisit>[
      CompanyVisit(
        id: 'v1',
        sessionId: 's1',
        companyId: 'c1',
        branchId: 'b1',
        stopId: null,
        arrival: _day.add(const Duration(hours: 9, minutes: 30)),
        departure: _day.add(const Duration(hours: 9, minutes: 33)),
        latitude: 23.25,
        longitude: 87.87,
        status: VisitStatus.completed,
        reportIds: const <String>[],
        dealReference: null,
      ),
    ],
    totalDistanceKm: 4,
  );
}

class _RouteRepo extends MockAdminRepository {
  _RouteRepo({required this.track}) : super(latency: Duration.zero);

  final RouteTrack track;

  @override
  Future<RouteTrack> route({
    required String employeeId,
    required DateTime date,
  }) async =>
      track;

  @override
  Future<TrackingConfig> config() async => const TrackingConfig();
}

({Widget app, TrackingController tracking, MockLocationService location})
    _buildPage(RouteTrack track) {
  final ProductStore products = ProductStore();
  final NotificationCenter notifications = NotificationCenter();
  final EmployeeRepository repository = MockEmployeeRepository(
    latency: Duration.zero,
    products: products,
    notifications: notifications,
  );
  final MockLocationService location =
      MockLocationService(emitInterval: const Duration(seconds: 30));
  final TrackingController tracking = TrackingController(
    repository: repository,
    locationService: location,
  );

  return (
    tracking: tracking,
    location: location,
    app: AppScope(
      repository: repository,
      adminRepository: _RouteRepo(track: track),
      companies: CompanyDirectory(() => repository.companies()),
      locationService: location,
      tracking: tracking,
      settings: SettingsController(),
      notifications: notifications,
      child: MaterialApp(
        theme: ThemeData.light(),
        home: RouteMapPage(employee: _employee(), date: _day),
      ),
    ),
  );
}

Future<void> _pumpRoute(WidgetTester tester, RouteTrack track) async {
  // A tall viewport so the whole list is laid out — the rows under the map
  // are the subject, and a lazy ListView would otherwise never build them.
  tester.view.physicalSize = const Size(1000, 4000);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  final ({Widget app, TrackingController tracking, MockLocationService location})
      h = _buildPage(track);
  addTearDown(() {
    h.tracking.dispose();
    h.location.dispose();
  });

  await tester.pumpWidget(h.app);
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 700));
}

void main() {
  testWidgets('a jitter-derived stop is marked, and says why',
      (WidgetTester tester) async {
    await _pumpRoute(tester, _track(weak: true));

    // The badge on the unlinked stop row: a reader going down the list must
    // not take this dwell for a confirmed one. Matched on the badge rather
    // than the bare string, because the legend always spells the term out.
    expect(find.widgetWithText(StatusBadge, 'Rough GPS'), findsOneWidget);
    expect(
      find.textContaining('treat this position as approximate'),
      findsWidgets,
      reason: 'a badge with no sentence behind it is decoration',
    );

    // The visit row carries the same hint in the same words.
    expect(
      find.text('GPS was rough here — position approximate'),
      findsOneWidget,
    );

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('the same day recorded cleanly is left alone',
      (WidgetTester tester) async {
    await _pumpRoute(tester, _track(weak: false));

    expect(find.widgetWithText(StatusBadge, 'Rough GPS'), findsNothing);
    expect(find.textContaining('treat this position as approximate'),
        findsNothing);
    expect(find.text('GPS was rough here — position approximate'),
        findsNothing);

    // The stop row itself is still there — only the hint went away.
    expect(find.text('Unlinked stop'), findsOneWidget);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('the day is counted, including when the count is zero',
      (WidgetTester tester) async {
    await _pumpRoute(tester, _track(weak: true));
    expect(find.text('Rough fixes'), findsOneWidget);
    expect(find.text('8'), findsWidgets, reason: 'both dwells, 4 fixes each');

    await tester.pumpWidget(const SizedBox.shrink());

    await _pumpRoute(tester, _track(weak: false));
    expect(find.text('Rough fixes'), findsOneWidget);
    expect(find.text('0'), findsWidgets);

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('the legend names the treatment and the live threshold',
      (WidgetTester tester) async {
    await _pumpRoute(tester, _track(weak: true));

    expect(find.text('Rough GPS'), findsWidgets);
    expect(
      find.textContaining('Dotted stretches and soft halos are fixes weaker '
          'than ±50 m'),
      findsOneWidget,
      reason: 'the number comes from config, so the legend must quote it '
          'rather than hard-code one',
    );

    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('it reads the same in dark mode', (WidgetTester tester) async {
    tester.view.physicalSize = const Size(1000, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);

    final ProductStore products = ProductStore();
    final NotificationCenter notifications = NotificationCenter();
    final EmployeeRepository repository = MockEmployeeRepository(
      latency: Duration.zero,
      products: products,
      notifications: notifications,
    );
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(seconds: 30));
    final TrackingController tracking = TrackingController(
      repository: repository,
      locationService: location,
    );
    addTearDown(() {
      tracking.dispose();
      location.dispose();
    });

    await tester.pumpWidget(
      AppScope(
        repository: repository,
        adminRepository: _RouteRepo(track: _track(weak: true)),
        companies: CompanyDirectory(() => repository.companies()),
        locationService: location,
        tracking: tracking,
        settings: SettingsController(),
        notifications: notifications,
        child: MaterialApp(
          theme: ThemeData.dark(),
          home: RouteMapPage(employee: _employee(), date: _day),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));

    expect(find.widgetWithText(StatusBadge, 'Rough GPS'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox.shrink());
  });
}
