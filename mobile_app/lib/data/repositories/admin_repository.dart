import '../../core/config/tracking_config.dart';
import '../models/models.dart';

/// Everything the admin console reads and writes.
///
/// Sibling of `EmployeeRepository`: the UI never touches mock data directly,
/// and swapping this mock for an HTTP implementation in `main.dart` is the
/// whole "go live" change.
///
/// The employee models carry no owner field on purpose — `/api/employee/*`
/// takes its identity from the auth token. Admin routes legitimately carry
/// `{id}` in the path, so ownership lives in this contract and in the
/// admin-only envelope models ([TeamMember], [EmployeeDay], [RouteTrack],
/// [ReportInboxItem]) rather than being retrofitted onto the shared models.
///
///   profile()            → GET   /api/admin/profile
///   overview()           → GET   /api/admin/dashboard?date=
///   team()               → GET   /api/admin/employees
///   memberById()         → GET   /api/admin/employees/{id}/status
///   employeeDay()        → GET   /api/admin/employees/{id}/day?date=
///   route()              → GET   /api/admin/employees/{id}/route?date=
///   teamRoutes()         → GET   /api/admin/routes?date=
///   employeeAttendance() → GET   /api/admin/employees/{id}/calendar?month=
///   employeeReports()    → GET   /api/admin/employees/{id}/reports
///   teamAttendance()     → GET   /api/admin/attendance?month=
///   teamStatistics()     → GET   /api/admin/statistics
///   inbox()              → GET   /api/admin/reports
///   reviewReport()       → POST  /api/admin/reports/{id}/review
///   products()           → GET   /api/admin/products?category=
///   delistedProductIds() → GET   /api/admin/products/delisted
///   saveProduct()        → POST  /api/admin/products  |  PATCH .../{id}
///   setProductActive()   → PATCH /api/admin/products/{id}
///   saveEmployee()       → POST  /api/admin/employees  |  PATCH .../{id}
///   setEmployeeActive()  → PATCH /api/admin/employees/{id}
///   config()/saveConfig()→ GET/PUT /api/admin/config
abstract class AdminRepository {
  /// The signed-in admin.
  Future<AdminUser> profile();

  /// Dashboard payload for [date] (defaults to today).
  Future<TeamOverview> overview({DateTime? date});

  /// Roster, optionally narrowed by a free-text [query] over name / code /
  /// region / department, and by live [status].
  Future<List<TeamMember>> team({String? query, WorkStatus? status});

  Future<TeamMember> memberById(String employeeId);

  /// One employee's whole day — sessions, stops, visits, reports, timeline.
  Future<EmployeeDay> employeeDay({
    required String employeeId,
    required DateTime date,
  });

  /// Historical route for the map. Returns an empty point list for days the
  /// employee did not work.
  Future<RouteTrack> route({
    required String employeeId,
    required DateTime date,
  });

  /// Every employee's track for [date] in one call — backs the all-team map.
  /// Narrow it with [employeeIds]; omit for the whole active roster. Tracks for
  /// days somebody did not work come back empty rather than being dropped, so
  /// the legend can still list them.
  Future<List<RouteTrack>> teamRoutes({
    required DateTime date,
    List<String>? employeeIds,
  });

  Future<List<Attendance>> employeeAttendance({
    required String employeeId,
    required DateTime month,
  });

  /// Reports by one employee; omit [date] for their recent history.
  Future<List<VisitReport>> employeeReports(
    String employeeId, {
    DateTime? date,
  });

  /// Whole-team attendance for [month] — backs the attendance matrix.
  Future<TeamAttendanceGrid> teamAttendance({required DateTime month});

  Future<TeamStatistics> teamStatistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  });

  /// Review queue, newest first.
  Future<List<ReportInboxItem>> inbox({
    ReviewDecision? decision,
    String? employeeId,
    DateTime? date,
    String? query,
  });

  Future<ReportInboxItem> inboxItem(String reportId);

  /// Approve or send back a report, with an optional [note].
  Future<ReportReview> reviewReport({
    required String reportId,
    required ReviewDecision decision,
    String? note,
  });

  /// How many reports are still awaiting a decision — drives the nav badge.
  Future<int> pendingReviewCount();

  /// The EV catalogue the admin owns. Unlike the employee-side `products()`,
  /// this returns inactive listings too — the admin has to be able to see and
  /// re-enable them.
  Future<List<Product>> products({ProductCategory? category, String? query});

  Future<Product> productById(String id);

  /// Ids the admin has delisted. Listing state is an admin-only concern, so it
  /// rides alongside [products] instead of being bolted onto the shared
  /// [Product] model the employee side also reads.
  Future<Set<String>> delistedProductIds();

  /// Creates when [ProductDraft.id] is null, otherwise updates. Either way the
  /// field team gets a notification, which is the point of the screen.
  Future<Product> saveProduct(ProductDraft draft);

  /// Delisting hides a product from the employee report form without deleting
  /// the reports that already reference it.
  Future<void> setProductActive(String id, bool active);

  /// Creates when [EmployeeDraft.id] is null, otherwise updates.
  Future<Employee> saveEmployee(EmployeeDraft draft);

  Future<void> setEmployeeActive(String employeeId, bool active);

  Future<TrackingConfig> config();

  Future<TrackingConfig> saveConfig(TrackingConfig config);
}
