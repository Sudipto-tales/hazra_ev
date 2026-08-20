import '../models/models.dart';

/// Contract the UI talks to. Swap the implementation (mock → HTTP) without
/// touching a single widget.
///
/// Method names deliberately track the planned endpoints:
///   home()            → GET /api/employee/home
///   activity()        → GET /api/employee/activity/today
///   reports()         → GET /api/employee/reports/today
///   attendance()      → GET /api/employee/calendar
///   statistics()      → GET /api/employee/statistics
///   profile()         → GET /api/employee/profile
///   products()        → GET /api/catalog/products?category=
///   productById()     → GET /api/catalog/products/{id}
///   notifications()   → GET /api/employee/notifications
abstract class EmployeeRepository {
  Future<Employee> profile();

  Future<HomeSnapshot> home();

  Future<List<ActivityEvent>> activity({DateTime? date});

  /// Reports for [date]; omit [date] for the full history (newest first).
  Future<List<VisitReport>> reports({DateTime? date});

  Future<VisitReport> reportById(String id);

  Future<List<Attendance>> attendance({required DateTime month});

  Future<Attendance?> attendanceForDate(DateTime date);

  Future<PeriodStatistics> statistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  });

  Future<List<Company>> companies();

  /// EV catalogue for the sale card on the report form. Omit [category] for the
  /// whole catalogue. Only products the admin currently has listed come back.
  Future<List<Product>> products({ProductCategory? category, String? query});

  Future<Product> productById(String id);

  /// Feed behind the bell on Home — newest first. The live badge reads
  /// `NotificationCenter` directly; this is the same data over the contract.
  Future<List<AppNotification>> notifications();

  Future<void> markNotificationRead(String id);

  Future<void> markAllNotificationsRead();

  /// Returns the stored record. In the live app this queues offline and retries.
  Future<VisitReport> submitReport(ReportDraft draft);
}

/// What the create-report form collects before it becomes a [VisitReport].
///
/// Company and branch are typed by the seller — visits are not fixed, so there
/// is no id to pick. [companyId] / [branchId] ride along only when the form was
/// opened from, or linked to, a detected visit.
class ReportDraft {
  const ReportDraft({
    required this.companyName,
    required this.branchName,
    required this.title,
    required this.body,
    required this.imageCount,
    this.companyId,
    this.branchId,
    this.visitId,
    this.dealValue,
    this.followUpOn,
    this.sales = const <ProductSaleLine>[],
    this.paymentReceived,
  });

  final String companyName;
  final String? branchName;
  final String? companyId;
  final String? branchId;
  final String title;
  final String body;

  /// Local picker output is represented by a count only in this design pass.
  final int imageCount;
  final String? visitId;
  final String? dealValue;
  final DateTime? followUpOn;

  /// One line per product+colour the seller logged.
  final List<ProductSaleLine> sales;
  final String? paymentReceived;
}
