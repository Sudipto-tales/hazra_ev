import 'dart:math';

import '../api/api_client.dart';
import '../api/wire.dart';
import '../models/models.dart';
import 'employee_repository.dart';

/// `EmployeeRepository` over `website/api`.
///
/// The interface is unchanged from the mock — that was the point of the seam.
/// Method → route follows the map in `docs/02-API-PLAN.md` §4:
///
///   profile()      GET  /me
///   home()         GET  /days/me?include=…
///   activity()     GET  /days/me?include=activity
///   reports()      GET  /reports?subject=me
///   attendance()   GET  /attendance?subject=me&month=
///   statistics()   GET  /statistics?subject=me
///   products()     GET  /products
///   notifications()GET  /notifications
///   submitReport() POST /reports          (Idempotency-Key)
///   submitDayCloseout() POST /days/me/closeout  (Idempotency-Key)
class HttpEmployeeRepository implements EmployeeRepository {
  HttpEmployeeRepository(this._api);

  final ApiClient _api;

  @override
  Future<Employee> profile() async {
    final ApiResult result = await _api.get('/me');
    return Wire.employee(result.map);
  }

  /// Home is one request. `status`, `movement` and `locationHealth` come back
  /// whether or not they were asked for — they are the payload's reason to exist.
  @override
  Future<HomeSnapshot> home() async {
    final ApiResult result = await _api.get('/days/me', query: <String, dynamic>{
      'include': 'sessions,summary,activity,visits,stops,lastFix,sync,closeout',
    });

    return Wire.homeSnapshot(result.map);
  }

  @override
  Future<List<ActivityEvent>> activity({DateTime? date}) async {
    final ApiResult result = await _api.get('/days/me', query: <String, dynamic>{
      'include': 'activity',
      if (date != null) 'date': Wire.day(date),
    });

    return Wire.maps(result.map['activity']).map(Wire.activity).toList();
  }

  /// Omit [date] for the full history, newest first.
  @override
  Future<List<VisitReport>> reports({DateTime? date}) async {
    final ApiResult result = await _api.get('/reports', query: <String, dynamic>{
      'subject': 'me',
      if (date != null) 'date': Wire.day(date),
      'limit': 100,
    });

    return result.list.map(Wire.report).toList();
  }

  @override
  Future<VisitReport> reportById(String id) async {
    final ApiResult result = await _api.get('/reports/$id');
    return Wire.report(result.map);
  }

  @override
  Future<List<Attendance>> attendance({required DateTime month}) async {
    final ApiResult result =
        await _api.get('/attendance', query: <String, dynamic>{
      'subject': 'me',
      'month': Wire.month(month),
    });

    return result.list.map(Wire.attendance).toList();
  }

  /// A lookup, not a fetch — the same route with a one-day window.
  @override
  Future<Attendance?> attendanceForDate(DateTime date) async {
    final String day = Wire.day(date);

    final ApiResult result =
        await _api.get('/attendance', query: <String, dynamic>{
      'subject': 'me',
      'from': day,
      'to': day,
    });

    final List<Map<String, dynamic>> rows = result.list;
    return rows.isEmpty ? null : Wire.attendance(rows.first);
  }

  @override
  Future<PeriodStatistics> statistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  }) async {
    final ApiResult result =
        await _api.get('/statistics', query: <String, dynamic>{
      'subject': 'me',
      'range': range.name,
      if (from != null) 'from': Wire.day(from),
      if (to != null) 'to': Wire.day(to),
      // The chart is the only caller that needs the series, so it is opt-in.
      'include': 'daily',
    });

    return Wire.periodStatistics(result.map);
  }

  @override
  Future<List<Company>> companies() async {
    final ApiResult result =
        await _api.get('/companies', query: <String, dynamic>{
      'include': 'branches',
    });

    return result.list.map(Wire.company).toList();
  }

  /// An employee token only ever sees listed products; `includeDelisted` is a
  /// no-op for this role, so it is not sent.
  @override
  Future<List<Product>> products({
    ProductCategory? category,
    String? query,
  }) async {
    final ApiResult result = await _api.get('/products', query: <String, dynamic>{
      if (category != null) 'category': category.name,
      if (query != null && query.isNotEmpty) 'query': query,
      'limit': 200,
    });

    return result.list.map(Wire.product).toList();
  }

  @override
  Future<Product> productById(String id) async {
    final ApiResult result = await _api.get('/products/$id');
    return Wire.product(result.map);
  }

  @override
  Future<List<AppNotification>> notifications() async {
    final ApiResult result =
        await _api.get('/notifications', query: <String, dynamic>{'limit': 100});

    return result.list.map(Wire.notification).toList();
  }

  @override
  Future<void> markNotificationRead(String id) =>
      _api.patch('/notifications/$id', body: <String, dynamic>{'read': true});

  @override
  Future<void> markAllNotificationsRead() =>
      _api.post('/notifications/read-all');

  /// `clientId` is what makes an offline retry safe: the server returns the
  /// original report rather than filing a second one. The same value doubles as
  /// the `Idempotency-Key` so a retry that never reached the database replays
  /// the original response instead.
  @override
  Future<VisitReport> submitReport(ReportDraft draft) async {
    final String clientId = _clientId();

    final ApiResult result = await _api.post(
      '/reports',
      idempotencyKey: clientId,
      body: <String, dynamic>{
        'clientId': clientId,
        'companyName': draft.companyName,
        'branchName': draft.branchName,
        'companyId': draft.companyId,
        'branchId': draft.branchId,
        'visitId': draft.visitId,
        'title': draft.title,
        'body': draft.body,
        // Free text, exactly as typed — never parsed into a number.
        'dealValue': draft.dealValue,
        'paymentReceived': draft.paymentReceived,
        'followUpOn':
            draft.followUpOn == null ? null : Wire.day(draft.followUpOn!),
        'sales': draft.sales
            .map((ProductSaleLine l) => <String, dynamic>{
                  'productId': l.productId,
                  'productName': l.productName,
                  'colorName': l.colorName,
                  'colorArgb': l.colorArgb,
                  'units': l.units,
                })
            .toList(),
      },
    );

    return Wire.report(result.map);
  }

  /// Device-generated id. Not a real UUID — it only has to be unique per
  /// device, and pulling in a uuid package for one call is not worth it.
  /// Ends the day. No offline queue on purpose — see the interface doc. The
  /// caller checks connectivity first; if it still fails here, the day stays
  /// open, which is the safe direction to fail in.
  @override
  Future<DayCloseout> submitDayCloseout(DayCloseoutDraft draft) async {
    final ApiResult result = await _api.post(
      '/days/me/closeout',
      idempotencyKey: draft.clientId,
      body: <String, dynamic>{
        'clientId': draft.clientId,
        'endedAt': draft.endedAt.toUtc().toIso8601String(),
        'declaredDistanceKm': draft.declaredDistanceKm,
        'declaredVisits': draft.declaredVisits,
        'rating': draft.rating,
        'tags': draft.tags
            .map((DayFeedbackTag t) => t.name)
            .toList(growable: false),
        'feedback': draft.feedback,
      },
    );

    return Wire.dayCloseout(result.map);
  }

  static String _clientId() {
    final int now = DateTime.now().microsecondsSinceEpoch;
    final int salt = Random().nextInt(1 << 32);
    return '$now-${salt.toRadixString(16)}';
  }
}
