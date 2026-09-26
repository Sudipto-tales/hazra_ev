import 'dart:io';

import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/api/api_client.dart';
import 'package:employeetracking_mobile_app/data/api/api_exception.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/api/token_store.dart';
import 'package:employeetracking_mobile_app/data/models/models.dart';
import 'package:employeetracking_mobile_app/data/repositories/admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/employee_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/http_admin_repository.dart';
import 'package:employeetracking_mobile_app/data/repositories/http_employee_repository.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Contract test against a running `website/api`.
///
/// This is the only place the decoders in `lib/data/api/wire.dart` are checked
/// against real payloads rather than hand-written fixtures — a fixture would
/// drift from the server the moment `Present.php` changes, which is exactly the
/// failure this is meant to catch.
///
/// Start the API first, then point the test at it:
///
/// ```sh
/// cd website && php vayu migrate --fresh --seed && php vayu run --host 0.0.0.0
/// cd mobile_app && flutter test test/api_contract_test.dart \
///   --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
/// ```
///
/// Every test is skipped, not failed, when the server is unreachable, so a
/// normal `flutter test` run does not depend on a live backend.
void main() {
  const String employeeEmail = 'arif@hazra-ev.test';
  const String adminEmail = 'admin@hazra-ev.test';
  const String password = 'password123';

  late bool serverUp;

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();

    // The binding installs an HttpOverrides that answers every request with a
    // canned 400 and never touches the network. This suite exists precisely to
    // talk to a real server, so the override is removed.
    HttpOverrides.global = null;

    SharedPreferences.setMockInitialValues(<String, Object>{});
    serverUp = await _reachable();

    if (!serverUp) {
      // ignore: avoid_print
      print('API unreachable — skipping contract tests.');
    }
  });

  Future<ApiClient> signIn(String email) async {
    final TokenStore tokens = await TokenStore.open();
    final ApiClient api = ApiClient(tokens: tokens);
    await api.login(email: email, password: password);
    return api;
  }

  group('employee surface', () {
    late EmployeeRepository repo;

    setUp(() async {
      if (!serverUp) return;
      repo = HttpEmployeeRepository(await signIn(employeeEmail));
    });

    test('profile decodes an Employee', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final Employee me = await repo.profile();
      expect(me.id, isNotEmpty);
      expect(me.employeeCode, startsWith('EMP-'));
      expect(me.email, employeeEmail);
      // Rendered server-side from the manager id.
      expect(me.reportingTo, isNotEmpty);
      expect(me.initials, hasLength(2));
    });

    test('home decodes a HomeSnapshot with a derived timeline', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final HomeSnapshot home = await repo.home();
      expect(home.sessions, isNotEmpty);
      expect(home.activity, isNotEmpty);
      expect(home.summary.distanceKm, greaterThan(0));
      // Durations arrive as integer seconds.
      expect(home.summary.workedDuration, greaterThan(Duration.zero));
      expect(home.sync.queued, 0);
    });

    test('activity decodes every ActivityType it is sent', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<ActivityEvent> events = await repo.activity();
      expect(events, isNotEmpty);
      // A fallback would silently mask an unmapped wire value.
      expect(
        events.every((ActivityEvent e) => e.title.isNotEmpty),
        isTrue,
      );
    });

    test('reports decode with denormalised sale lines', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<VisitReport> reports = await repo.reports();
      expect(reports, isNotEmpty);

      final VisitReport withSale =
          reports.firstWhere((VisitReport r) => r.hasSale);
      expect(withSale.sales.first.productName, isNotEmpty);
      expect(withSale.unitsSold, greaterThan(0));
      // Money is free text, never a number.
      expect(withSale.dealValue, isA<String?>());

      final VisitReport one = await repo.reportById(reports.first.id);
      expect(one.id, reports.first.id);
    });

    test('attendance flattens the embedded summary', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<Attendance> month =
          await repo.attendance(month: DateTime.now());
      expect(month, isNotEmpty);

      final Attendance worked = month.firstWhere(
        (Attendance a) =>
            a.status == AttendanceStatus.present ||
            a.status == AttendanceStatus.partial,
      );

      // The API nests these under `summary`; the model is flat.
      expect(worked.workedDuration, greaterThan(Duration.zero));
      expect(worked.distanceKm, greaterThan(0));
      expect(worked.joiningTime, isNotNull);

      // Weekends have to be present or the calendar renders holes.
      expect(
        month.any((Attendance a) => a.status == AttendanceStatus.weekend),
        isTrue,
      );

      final Attendance? single = await repo.attendanceForDate(worked.date);
      expect(single?.date.day, worked.date.day);
    });

    test('statistics decode including the opt-in daily series', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final PeriodStatistics stats =
          await repo.statistics(range: StatsRange.thisMonth);
      expect(stats.rangeLabel, 'This month');
      expect(stats.dailyDistance, isNotEmpty);
      expect(stats.attendancePercent, inInclusiveRange(0, 100));
    });

    test('catalogue decodes with per-colour galleries and no price', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<Product> products = await repo.products();
      expect(products, isNotEmpty);
      expect(products.every((Product p) => p.colors.isNotEmpty), isTrue);
      // The spec sheet must never leak a price.
      expect(
        products.first.specSheet
            .any((({String label, String value}) r) =>
                r.label.toLowerCase().contains('price')),
        isFalse,
      );

      final Product one = await repo.productById(products.first.id);
      expect(one.displayName, products.first.displayName);
    });

    test('companies decode with branches', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<Company> companies = await repo.companies();
      expect(companies, isNotEmpty);
      expect(companies.any((Company c) => c.branches.isNotEmpty), isTrue);
    });

    test('submitReport is idempotent per clientId', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<Product> products = await repo.products();

      final VisitReport filed = await repo.submitReport(
        ReportDraft(
          companyName: 'Contract Test Motors',
          branchName: 'Test branch',
          title: 'Contract test',
          body: 'Filed by test/api_contract_test.dart',
          dealValue: 'approx 1.5L',
          paymentReceived: '10% advance',
          sales: <ProductSaleLine>[
            ProductSaleLine(
              productId: products.first.id,
              productName: products.first.displayName,
              category: products.first.category,
              colorName: products.first.colors.first.name,
              colorArgb: products.first.colors.first.argb,
              units: 2,
            ),
          ],
        ),
      );

      expect(filed.id, isNotEmpty);
      expect(filed.companyName, 'Contract Test Motors');
      expect(filed.unitsSold, 2);
      // Stored exactly as typed.
      expect(filed.dealValue, 'approx 1.5L');
      expect(filed.status, ReportStatus.submitted);
    });

    test('notifications decode and mark read', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<AppNotification> feed = await repo.notifications();
      if (feed.isNotEmpty) {
        await repo.markNotificationRead(feed.first.id);
      }
      await repo.markAllNotificationsRead();
      final List<AppNotification> after = await repo.notifications();
      expect(after.every((AppNotification n) => n.read), isTrue);
    });
  });

  group('admin surface', () {
    late AdminRepository repo;

    setUp(() async {
      if (!serverUp) return;
      repo = HttpAdminRepository(await signIn(adminEmail));
    });

    test('overview reads the aggregate out of meta.totals', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final TeamOverview overview = await repo.overview();
      expect(overview.members, isNotEmpty);
      expect(overview.headcount, overview.members.length);
      expect(overview.attendancePercent, inInclusiveRange(0, 100));
      // Proves meta.totals survived the envelope.
      expect(
        overview.presentCount + overview.absentCount,
        lessThanOrEqualTo(overview.headcount),
      );
    });

    test('team and memberById decode TeamMember', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<TeamMember> team = await repo.team();
      expect(team, isNotEmpty);

      final TeamMember one = await repo.memberById(team.first.employee.id);
      expect(one.employee.id, team.first.employee.id);
      expect(one.summary.sessionCount, greaterThanOrEqualTo(0));
    });

    test('employeeDay decodes the admin mirror of Home', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<TeamMember> team = await repo.team();

      final EmployeeDay day = await repo.employeeDay(
        employeeId: team.first.employee.id,
        date: DateTime.now(),
      );

      expect(day.employee.id, team.first.employee.id);
      expect(day.sessions, isNotEmpty);
      expect(day.activity, isNotEmpty);
      expect(day.isEmpty, isFalse);
    });

    test('route decodes flat tuples back into segmented points', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<TeamMember> team = await repo.team();

      final RouteTrack track = await repo.route(
        employeeId: team.first.employee.id,
        date: DateTime.now(),
      );

      expect(track.points.length, greaterThan(2));
      expect(track.isEmpty, isFalse);
      // sessionIdx resolved back to a real session id, so segments group.
      expect(track.segments, isNotEmpty);
      expect(track.segments.first.first.sessionId, isNotEmpty);
      expect(track.bounds.spanLat, greaterThan(0));
      expect(track.firstFixAt, isNotNull);
    });

    test('teamRoutes keeps empty tracks so the legend stays complete', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<RouteTrack> tracks =
          await repo.teamRoutes(date: DateTime.now());
      final List<TeamMember> team = await repo.team();
      expect(tracks.length, team.length);
    });

    test('teamAttendance pivots into byDayKey', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final TeamAttendanceGrid grid =
          await repo.teamAttendance(month: DateTime.now());

      expect(grid.days, isNotEmpty);
      expect(grid.rows, isNotEmpty);

      final TeamAttendanceRow row = grid.rows.first;
      final int key = TeamAttendanceRow.dayKey(grid.days.first);
      // The client does no date arithmetic — the key must already be there.
      expect(row.byDayKey.containsKey(key), isTrue);
    });

    test('teamStatistics decode with perEmployee', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final TeamStatistics stats =
          await repo.teamStatistics(range: StatsRange.thisMonth);
      expect(stats.perEmployee, isNotEmpty);
      expect(stats.headcount, stats.perEmployee.length);
      expect(stats.dailyDistance, isNotEmpty);
    });

    test('inbox decodes the joined envelope and review flips status', () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<ReportInboxItem> pending =
          await repo.inbox(decision: ReviewDecision.pending);
      expect(pending, isNotEmpty);
      expect(pending.first.employee.name, isNotEmpty);
      expect(pending.first.decision, ReviewDecision.pending);

      final int before = await repo.pendingReviewCount();
      expect(before, greaterThan(0));

      final ReportReview review = await repo.reviewReport(
        reportId: pending.first.report.id,
        decision: ReviewDecision.approved,
        note: 'Approved by contract test.',
      );

      expect(review.decision, ReviewDecision.approved);
      expect(review.decision.isDecided, isTrue);
      expect(await repo.pendingReviewCount(), before - 1);

      final ReportInboxItem item =
          await repo.inboxItem(pending.first.report.id);
      expect(item.review.decision, ReviewDecision.approved);
    });

    test('admin catalogue widens to delisted and PATCH toggles active',
        () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      if (!serverUp) return markTestSkipped('API unreachable');
      final List<Product> all = await repo.products();
      expect(all, isNotEmpty);

      final Product target = all.first;
      await repo.setProductActive(target.id, false);
      expect(await repo.delistedProductIds(), contains(target.id));

      await repo.setProductActive(target.id, true);
      expect(
        await repo.delistedProductIds(),
        isNot(contains(target.id)),
      );
    });

    test('config round-trips and read-only fields are still writable',
        () async {
      if (!serverUp) return markTestSkipped('API unreachable');
      if (!serverUp) return markTestSkipped('API unreachable');
      final TrackingConfig original = await repo.config();

      final TrackingConfig saved = await repo.saveConfig(
        original.copyWith(stopRadiusMetres: 88, maxJumpKmh: 190),
      );

      expect(saved.stopRadiusMetres, 88);
      // maxJumpKmh is read-only in the UI, never in the API.
      expect(saved.maxJumpKmh, 190);

      await repo.saveConfig(original);
    });
  });

  // The two gaps that used to make this pair unreachable: End Day had no server
  // side at all, and a created employee had a password hash nobody had ever
  // seen. Both are wire-level, so they belong here rather than in a mock test.
  group('day closeout', () {
    late EmployeeRepository employee;
    late AdminRepository admin;
    late String employeeId;

    setUp(() async {
      if (!serverUp) return;
      final ApiClient employeeApi = await signIn(employeeEmail);
      employee = HttpEmployeeRepository(employeeApi);
      admin = HttpAdminRepository(await signIn(adminEmail));
      employeeId = (await employee.profile()).id;
    });

    test('a closeout stores the claim beside the measurement', () async {
      if (!serverUp) return markTestSkipped('API unreachable');

      // The day may already be closed from an earlier run of this suite.
      // Reopening is idempotent enough for a fixture: it either clears a lock
      // or reports there was nothing to clear.
      try {
        await admin.reopenDay(
          employeeId: employeeId,
          date: DateTime.now(),
          reason: 'Contract test fixture',
        );
      } on ApiException catch (e) {
        if (e.code != 'DAY_NOT_CLOSED') rethrow;
      }

      final String clientId = 'contract-${DateTime.now().microsecondsSinceEpoch}';
      final DayCloseout stored = await employee.submitDayCloseout(
        DayCloseoutDraft(
          clientId: clientId,
          endedAt: DateTime.now(),
          declaredDistanceKm: 41.5,
          declaredVisits: 4,
          rating: 4,
          tags: const <DayFeedbackTag>[
            DayFeedbackTag.traffic,
            DayFeedbackTag.vehicleIssue,
          ],
          feedback: 'Contract test',
        ),
      );

      expect(stored.id, isNotEmpty);
      expect(stored.declaredDistanceKm, 41.5);
      expect(stored.declaredVisits, 4);
      // Declared never overwrites measured — the gap is the whole point.
      expect(stored.measuredDistanceKm, isNot(41.5));
      // Enums survive the snake_case round trip in both directions.
      expect(stored.tags, contains(DayFeedbackTag.vehicleIssue));

      // Same clientId replays rather than closing the day twice.
      final DayCloseout replay = await employee.submitDayCloseout(
        DayCloseoutDraft(
          clientId: clientId,
          endedAt: DateTime.now(),
          declaredDistanceKm: 41.5,
          declaredVisits: 4,
          rating: 4,
        ),
      );
      expect(replay.id, stored.id);

      // dayState is unconditional, and the lock survives a reload.
      final HomeSnapshot locked = await employee.home();
      expect(locked.dayState, DayState.closedByEmployee);
      expect(locked.dayState.isLocked, isTrue);
      expect(locked.closeout?.declaredVisits, 4);

      await admin.reopenDay(
        employeeId: employeeId,
        date: DateTime.now(),
        reason: 'Contract test cleanup',
      );

      // Reopened: workable again, but the declaration is kept.
      final HomeSnapshot reopened = await employee.home();
      expect(reopened.dayState, DayState.open);
      expect(reopened.closeout, isNotNull);
    });
  });

  group('credentials', () {
    late AdminRepository admin;

    setUp(() async {
      if (!serverUp) return;
      admin = HttpAdminRepository(await signIn(adminEmail));
    });

    test('a created employee can actually sign in', () async {
      if (!serverUp) return markTestSkipped('API unreachable');

      final int seq = DateTime.now().millisecondsSinceEpoch % 90000 + 10000;
      final String email = 'contract.$seq@hazra-ev.test';

      final EmployeeSaveResult created = await admin.saveEmployee(
        EmployeeDraft(
          name: 'Contract Test',
          employeeCode: 'EMP-$seq',
          designation: 'Field Executive',
          department: '',
          email: email,
          phone: '+8801700000000',
          region: '',
          reportingTo: '',
          joinedOn: DateTime(2026, 1, 10),
        ),
      );

      // No password in the draft, so the server generated one and this is the
      // only response that will ever carry it.
      final String? temporary = created.temporaryPassword;
      expect(temporary, isNotNull);

      final TokenStore tokens = await TokenStore.open();
      final ApiClient fresh = ApiClient(tokens: tokens);
      final Employee principal =
          Wire.employee((await fresh.login(email: email, password: temporary!)).principal);

      expect(principal.email, email);
      // Generated, so nobody has chosen this password yet.
      expect(principal.mustChangePassword, isTrue);

      // Self change: the wrong current password is a 403, not a 401 — the
      // token is fine, so ApiClient must not treat it as a session problem.
      final EmployeeRepository self = HttpEmployeeRepository(fresh);
      await expectLater(
        self.changePassword(current: 'not-my-password', next: 'a-new-password'),
        throwsA(isA<ApiException>().having((ApiException e) => e.isForbidden, 'isForbidden', isTrue)),
      );

      await self.changePassword(current: temporary, next: 'a-new-password');

      // The old one is dead and the new one carries no must-change flag.
      final ApiClient after = ApiClient(tokens: await TokenStore.open());
      await expectLater(
        after.login(email: email, password: temporary),
        throwsA(isA<ApiException>()),
      );

      final Employee changed = Wire.employee(
        (await after.login(email: email, password: 'a-new-password')).principal,
      );
      expect(changed.mustChangePassword, isFalse);
    });

    test('a chosen password is accepted and not echoed back', () async {
      if (!serverUp) return markTestSkipped('API unreachable');

      final int seq = DateTime.now().millisecondsSinceEpoch % 90000 + 10000;
      final String email = 'chosen.$seq@hazra-ev.test';

      final EmployeeSaveResult created = await admin.saveEmployee(
        EmployeeDraft(
          name: 'Chosen Password',
          employeeCode: 'EMP-${seq + 1}',
          designation: 'Field Executive',
          department: '',
          email: email,
          phone: '+8801700000001',
          region: '',
          reportingTo: '',
          joinedOn: DateTime(2026, 1, 10),
          password: 'chosen-by-the-admin',
        ),
      );

      expect(created.temporaryPassword, isNull);

      final ApiClient fresh = ApiClient(tokens: await TokenStore.open());
      final Employee principal = Wire.employee(
        (await fresh.login(email: email, password: 'chosen-by-the-admin')).principal,
      );
      expect(principal.mustChangePassword, isFalse);

      // An admin reset issues a new one and invalidates what came before.
      final String? reset = await admin.resetEmployeePassword(created.employee.id);
      expect(reset, isNotNull);

      final ApiClient afterReset = ApiClient(tokens: await TokenStore.open());
      await expectLater(
        afterReset.login(email: email, password: 'chosen-by-the-admin'),
        throwsA(isA<ApiException>()),
      );
      final Employee back = Wire.employee(
        (await afterReset.login(email: email, password: reset!)).principal,
      );
      expect(back.mustChangePassword, isTrue);
    });
  });
}

/// A cheap TCP probe — cheaper than a failed HTTP round trip per test.
Future<bool> _reachable() async {
  const String raw = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8123/api/v1',
  );

  final Uri uri = Uri.parse(raw);

  try {
    final Socket socket = await Socket.connect(
      uri.host,
      uri.port,
      timeout: const Duration(seconds: 2),
    );
    socket.destroy();
    return true;
  } catch (_) {
    return false;
  }
}
