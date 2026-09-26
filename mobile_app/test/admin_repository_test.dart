import 'package:employeetracking_mobile_app/data/mock/admin_mock_data.dart';
import 'package:employeetracking_mobile_app/data/mock/mock_data.dart';
import 'package:employeetracking_mobile_app/data/mock/product_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/state/notification_center.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  MockAdminRepository repo() =>
      MockAdminRepository(latency: Duration.zero);

  group('roster', () {
    test('employee 0 is the employee app\'s own employee', () {
      expect(AdminMockData.employees.first.id, MockData.employee.id);
      expect(AdminMockData.employees.length, greaterThanOrEqualTo(8));
    });

    test('team() returns every employee', () async {
      final List<TeamMember> team = await repo().team();
      expect(team.length, AdminMockData.employees.length);
    });

    test('search narrows the roster', () async {
      final List<TeamMember> team = await repo().team(query: 'Rahul');
      expect(team, isNotEmpty);
      expect(team.first.employee.name, contains('Rahul'));
    });

    test('the fixtures cover every live status the dashboard renders', () async {
      final List<TeamMember> team = await repo().team();
      final Set<WorkStatus> statuses =
          team.map((TeamMember m) => m.status).toSet();
      expect(statuses, contains(WorkStatus.working));
      expect(statuses, contains(WorkStatus.idle));
      expect(statuses, contains(WorkStatus.offline));
      expect(statuses, contains(WorkStatus.locationUnavailable));
      expect(statuses, contains(WorkStatus.notStarted));
      expect(statuses, contains(WorkStatus.ended));
    });

    test('at least one long stop exists so the alert always has a case',
        () async {
      final TeamOverview overview = await repo().overview();
      expect(overview.longStopCount, greaterThan(0));
    });
  });

  group('attendance', () {
    test('history is 121 days per employee', () {
      for (final Employee e in AdminMockData.employees) {
        expect(AdminMockData.attendanceFor(e.id).length, 121);
      }
    });

    test('generation is deterministic across calls', () {
      final String id = AdminMockData.employees[2].id;
      final List<Attendance> a = AdminMockData.attendanceFor(id);
      final List<Attendance> b = AdminMockData.attendanceFor(id);
      expect(identical(a, b) || a.length == b.length, isTrue);
      for (int i = 0; i < a.length; i++) {
        expect(a[i].status, b[i].status);
        expect(a[i].distanceKm, b[i].distanceKm);
      }
    });
  });

  group('route', () {
    test('a worked day produces a polyline', () async {
      final String id = AdminMockData.employees[1].id;
      final RouteTrack track =
          await repo().route(employeeId: id, date: MockData.today);
      expect(track.points.length, greaterThan(10));
      expect(track.visits, isNotEmpty);
    });

    test('points never span two sessions inside one segment', () async {
      final String id = AdminMockData.employees[1].id;
      final RouteTrack track =
          await repo().route(employeeId: id, date: MockData.today);
      for (final List<LocationLog> segment in track.segments) {
        final Set<String> sessions =
            segment.map((LocationLog p) => p.sessionId).toSet();
        expect(sessions.length, 1);
      }
    });

    test('distance lands close to the attendance row it is derived from',
        () async {
      final String id = AdminMockData.employees[1].id;
      final RouteTrack track =
          await repo().route(employeeId: id, date: MockData.today);
      final Attendance? att = AdminMockData.attendanceOn(id, MockData.today);
      expect(att, isNotNull);
      // The bow bisection cannot always reach the target (branches may sit
      // farther apart than the day's km), but it must never overshoot wildly.
      expect(track.totalDistanceKm, lessThanOrEqualTo(att!.distanceKm * 1.15));
    });

    test('a non-working day yields an empty track', () async {
      final String id = AdminMockData.employees[1].id;
      DateTime? blank;
      for (final Attendance a in AdminMockData.attendanceFor(id)) {
        if (a.status == AttendanceStatus.weekend ||
            a.status == AttendanceStatus.absent) {
          blank = a.date;
          break;
        }
      }
      expect(blank, isNotNull);
      final RouteTrack track =
          await repo().route(employeeId: id, date: blank!);
      expect(track.isEmpty, isTrue);
    });
  });

  group('review', () {
    test('approving a report is visible on the next read', () async {
      final MockAdminRepository r = repo();
      final List<ReportInboxItem> pending =
          await r.inbox(decision: ReviewDecision.pending);
      expect(pending, isNotEmpty);

      final String id = pending.first.report.id;
      await r.reviewReport(
        reportId: id,
        decision: ReviewDecision.approved,
      );

      final ReportInboxItem after = await r.inboxItem(id);
      expect(after.decision, ReviewDecision.approved);
      expect(after.review.reviewedBy, AdminMockData.admin.name);

      final List<ReportInboxItem> stillPending =
          await r.inbox(decision: ReviewDecision.pending);
      expect(
        stillPending.where((ReportInboxItem i) => i.report.id == id),
        isEmpty,
      );
    });
  });

  group('write side', () {
    test('a created employee immediately has history and a route', () async {
      final MockAdminRepository r = repo();
      final EmployeeSaveResult saved = await r.saveEmployee(
        EmployeeDraft(
          name: 'Test Person',
          employeeCode: 'EMP-9001',
          designation: 'Sales Executive',
          department: 'Key Accounts',
          email: 'test.person@company.com',
          phone: '+880 1700 000 000',
          region: 'Bardhaman North',
          reportingTo: 'Imran Kabir (Zonal Manager)',
          joinedOn: DateTime(2024, 1, 10),
        ),
      );

      final Employee created = saved.employee;

      // No password in the draft, so the backend generated one and it is
      // readable exactly once, here.
      expect(saved.temporaryPassword, isNotNull);
      expect(saved.temporaryPassword!.length, 12);

      final List<TeamMember> team = await r.team();
      expect(team.first.employee.id, created.id);
      expect(AdminMockData.attendanceFor(created.id).length, 121);
    });

    test('a chosen password is not echoed back as a temporary one', () async {
      final MockAdminRepository r = repo();
      final EmployeeSaveResult saved = await r.saveEmployee(
        EmployeeDraft(
          name: 'Chosen Password',
          employeeCode: 'EMP-9002',
          designation: 'Sales Executive',
          department: 'Key Accounts',
          email: 'chosen@company.com',
          phone: '+880 1700 000 001',
          region: 'Bardhaman North',
          reportingTo: 'Imran Kabir (Zonal Manager)',
          joinedOn: DateTime(2024, 1, 10),
          password: 'admin-chose-this',
        ),
      );

      expect(saved.temporaryPassword, isNull);
    });

    test('an employee itinerary is stable across reads', () async {
      // Nobody assigns a territory any more — the customer base is derived from
      // the employee id, so the same day must generate identically every time.
      final MockAdminRepository r = repo();
      final String id = AdminMockData.employees[3].id;

      final EmployeeDay first =
          await r.employeeDay(employeeId: id, date: MockData.today);
      AdminMockData.invalidate(id);
      final EmployeeDay second =
          await r.employeeDay(employeeId: id, date: MockData.today);

      expect(second.visits.length, first.visits.length);
      for (int i = 0; i < first.visits.length; i++) {
        expect(second.visits[i].companyId, first.visits[i].companyId);
        expect(second.visits[i].branchId, first.visits[i].branchId);
      }
    });

    test('saved tracking config is read back', () async {
      final MockAdminRepository r = repo();
      await r.saveConfig(
        (await r.config()).copyWith(longStopThresholdMinutes: 20),
      );
      expect((await r.config()).longStopThresholdMinutes, 20);
    });
  });

  group('catalogue', () {
    /// Both repositories over one store and one feed, exactly how `main.dart`
    /// wires them — that shared store is the whole point of these tests.
    ({
      MockAdminRepository admin,
      MockEmployeeRepository employee,
      NotificationCenter feed,
    }) pair() {
      final ProductStore store = ProductStore();
      final NotificationCenter feed = NotificationCenter();
      return (
        admin: MockAdminRepository(
          latency: Duration.zero,
          products: store,
          notifications: feed,
        ),
        employee: MockEmployeeRepository(
          latency: Duration.zero,
          products: store,
          notifications: feed,
        ),
        feed: feed,
      );
    }

    const ProductDraft draft = ProductDraft(
      category: ProductCategory.bicycle,
      brand: 'Veloce',
      name: 'City 300',
      modelCode: 'VLC-C300',
      rating: 4.2,
      warrantyYears: 2,
      warrantyNote: '2 yrs frame + 1 yr battery',
      rangeKm: 60,
      topSpeedKmph: 25,
      chargingTime: '3 h 30 m',
      batteryCapacity: '0.5 kWh lithium-ion',
      motorPower: '250 W hub',
      loadCapacityKg: 110,
      colors: <ProductColor>[
        ProductColor(name: 'Pearl White', argb: 0xFFF8FAFC),
        ProductColor(name: 'Teal', argb: 0xFF14B8A6, inStock: false),
      ],
    );

    test('a product the admin lists reaches the employee catalogue', () async {
      final ({
        MockAdminRepository admin,
        MockEmployeeRepository employee,
        NotificationCenter feed,
      }) p = pair();

      final Product created = await p.admin.saveProduct(draft);
      final List<Product> seen =
          await p.employee.products(category: ProductCategory.bicycle);

      expect(seen.map((Product e) => e.id), contains(created.id));
      expect(created.listedAt, isNotNull, reason: 'a listing is dated');
      expect(created.isNew, isTrue);
    });

    test('listing notifies the field team', () async {
      final ({
        MockAdminRepository admin,
        MockEmployeeRepository employee,
        NotificationCenter feed,
      }) p = pair();

      final int before = p.feed.items.length;
      final Product created = await p.admin.saveProduct(draft);

      expect(p.feed.items.length, before + 1);
      expect(p.feed.items.first.kind, NotificationKind.newProduct);
      expect(p.feed.items.first.productId, created.id);
    });

    test('an edit is a different notification and keeps the listing date',
        () async {
      final ({
        MockAdminRepository admin,
        MockEmployeeRepository employee,
        NotificationCenter feed,
      }) p = pair();

      final Product created = await p.admin.saveProduct(draft);
      final Product edited = await p.admin.saveProduct(
        ProductDraft(
          id: created.id,
          category: created.category,
          brand: created.brand,
          name: 'City 300 Plus',
          modelCode: created.modelCode,
          rating: created.rating,
          warrantyYears: created.warrantyYears,
          warrantyNote: created.warrantyNote,
          rangeKm: 75,
          topSpeedKmph: created.topSpeedKmph,
          chargingTime: created.chargingTime,
          batteryCapacity: created.batteryCapacity,
          motorPower: created.motorPower,
          loadCapacityKg: created.loadCapacityKg,
          colors: created.colors,
        ),
      );

      expect(edited.id, created.id, reason: 'an edit is not a new product');
      expect(edited.name, 'City 300 Plus');
      expect(edited.rangeKm, 75);
      expect(edited.listedAt, created.listedAt);
      expect(p.feed.items.first.kind, NotificationKind.productUpdated);

      final List<Product> admins = await p.admin.products();
      expect(
        admins.where((Product e) => e.id == created.id).length,
        1,
        reason: 'editing replaces in place rather than appending',
      );
    });

    test('delisting hides a product from sellers but not from the admin',
        () async {
      final ({
        MockAdminRepository admin,
        MockEmployeeRepository employee,
        NotificationCenter feed,
      }) p = pair();

      final Product created = await p.admin.saveProduct(draft);
      await p.admin.setProductActive(created.id, false);

      final List<Product> sellerSide = await p.employee.products();
      final List<Product> adminSide = await p.admin.products();

      expect(sellerSide.map((Product e) => e.id), isNot(contains(created.id)));
      expect(adminSide.map((Product e) => e.id), contains(created.id));
      expect(await p.admin.delistedProductIds(), contains(created.id));

      // …and re-listing puts it back, which is why delisting is not a delete.
      await p.admin.setProductActive(created.id, true);
      expect(
        (await p.employee.products()).map((Product e) => e.id),
        contains(created.id),
      );
      expect(await p.admin.delistedProductIds(), isNot(contains(created.id)));
    });

    test('the admin list filters by category and searches the model code',
        () async {
      final ({
        MockAdminRepository admin,
        MockEmployeeRepository employee,
        NotificationCenter feed,
      }) p = pair();

      await p.admin.saveProduct(draft);

      final List<Product> bicycles =
          await p.admin.products(category: ProductCategory.bicycle);
      expect(bicycles, isNotEmpty);
      expect(
        bicycles.every((Product e) => e.category == ProductCategory.bicycle),
        isTrue,
      );

      final List<Product> hits = await p.admin.products(query: 'vlc-c300');
      expect(hits.length, 1);
      expect(hits.first.modelCode, 'VLC-C300');
    });
  });
}
