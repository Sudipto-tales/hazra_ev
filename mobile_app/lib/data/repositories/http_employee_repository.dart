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
///   updateProfile() PATCH /me
///   home()         GET  /days/me?include=…
///   activity()     GET  /days/me?include=activity
///   reports()      GET  /reports?subject=me
///   attendance()   GET  /attendance?subject=me&month=
///   statistics()   GET  /statistics?subject=me
///   products()     GET  /products
///   notifications()GET  /notifications
///   submitReport() POST /reports          (Idempotency-Key)
///   uploadReportImages() POST /reports/{id}/images   (multipart)
///   submitDayCloseout() POST /days/me/closeout  (Idempotency-Key)
///   preferences()      GET   /me/preferences
///   savePreferences()  PATCH /me/preferences
class HttpEmployeeRepository implements EmployeeRepository {
  HttpEmployeeRepository(this._api);

  final ApiClient _api;

  @override
  Future<Employee> profile() async {
    final ApiResult result = await _api.get('/me');
    return Wire.employee(result.map);
  }

  @override
  Future<Employee> updateProfile({String? phone, String? avatarUrl}) async {
    // Only send what changed. The endpoint treats an absent key as "leave it",
    // and rejects a body with no recognised key at all.
    final Map<String, dynamic> body = <String, dynamic>{
      if (phone != null) 'phone': phone,
      if (avatarUrl != null) 'avatarUrl': avatarUrl,
    };

    final ApiResult result = await _api.patch('/me', body: body);
    return Wire.employee(result.map);
  }

  /// Home is one request. `status`, `movement` and `locationHealth` come back
  /// whether or not they were asked for — they are the payload's reason to exist.
  @override
  Future<HomeSnapshot> home() async {
    final ApiResult result =
        await _api.get('/days/me', query: <String, dynamic>{
      'include': 'sessions,summary,activity,visits,stops,lastFix,sync,closeout',
    });

    return Wire.homeSnapshot(result.map);
  }

  @override
  Future<List<ActivityEvent>> activity({DateTime? date}) async {
    final ApiResult result =
        await _api.get('/days/me', query: <String, dynamic>{
      'include': 'activity',
      if (date != null) 'date': Wire.day(date),
    });

    return Wire.maps(result.map['activity']).map(Wire.activity).toList();
  }

  /// Omit [date] for the full history, newest first.
  @override
  Future<List<VisitReport>> reports({DateTime? date}) async {
    final ApiResult result =
        await _api.get('/reports', query: <String, dynamic>{
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
    final ApiResult result =
        await _api.get('/products', query: <String, dynamic>{
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
    final ApiResult result = await _api
        .get('/notifications', query: <String, dynamic>{'limit': 100});

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

  /// Second leg of the submit. See the interface doc for why it is separate.
  ///
  /// No `Idempotency-Key`: the endpoint appends, it does not upsert, so a
  /// replayed key would either duplicate the pictures or silently swallow a
  /// genuine second batch. Retry safety is the caller's — it drops what the
  /// server confirmed before trying again.
  @override
  Future<List<ReportImage>> uploadReportImages(
    String reportId,
    List<ReportAttachment> images,
  ) async {
    if (images.isEmpty) {
      return const <ReportImage>[];
    }

    final List<Map<String, dynamic>> stored = await _api.uploadReportImages(
      reportId,
      images.map((ReportAttachment a) => a.path).toList(growable: false),
    );

    return stored.map(_image).toList(growable: false);
  }

  /// Decoded here rather than in `lib/data/api/wire.dart` because this shape
  /// has exactly one producer — the upload response. No report payload carries
  /// images, so there is nothing for a shared decoder to be shared with.
  static ReportImage _image(Map<String, dynamic> j) => ReportImage(
        id: '${j['id']}',
        reportId: '${j['reportId']}',
        url: '${j['url']}',
        thumbnailUrl: j['thumbnailUrl'] as String?,
        width: (j['width'] as num?)?.toInt(),
        height: (j['height'] as num?)?.toInt(),
        byteSize: (j['bytes'] as num?)?.toInt(),
      );

  @override
  Future<DevicePreferences> preferences() async {
    final ApiResult result = await _api.get('/me/preferences');
    return _preferences(result.map);
  }

  /// Partial: only the arguments that were passed reach the body, and the
  /// server leaves every absent column alone. The response is the stored row
  /// after the `TrackingConfig` ceiling has been applied, so it is the answer
  /// — not an echo.
  @override
  Future<DevicePreferences> savePreferences({
    String? themeMode,
    String? language,
    bool? notificationsEnabled,
    bool? reportReminders,
    bool? sessionReminders,
    bool? systemNotifications,
    bool? highAccuracyMode,
    bool? syncOnMobileData,
    bool? batterySaver,
  }) async {
    final Map<String, dynamic> body = <String, dynamic>{
      if (themeMode != null) 'themeMode': themeMode,
      if (language != null) 'language': language,
      if (notificationsEnabled != null)
        'notificationsEnabled': notificationsEnabled,
      if (reportReminders != null) 'reportReminders': reportReminders,
      if (sessionReminders != null) 'sessionReminders': sessionReminders,
      if (systemNotifications != null)
        'systemNotifications': systemNotifications,
      if (highAccuracyMode != null) 'highAccuracyMode': highAccuracyMode,
      if (syncOnMobileData != null) 'syncOnMobileData': syncOnMobileData,
      if (batterySaver != null) 'batterySaver': batterySaver,
    };

    // Nothing changed. A PATCH with an empty body is a write that writes
    // nothing, so read instead — same result, no risk of touching a row.
    if (body.isEmpty) {
      return preferences();
    }

    final ApiResult result = await _api.patch('/me/preferences', body: body);
    return _preferences(result.map);
  }

  /// `Present::preferences` casts to real JSON booleans and never omits a
  /// field, so this reads them straight. Defaults are still applied per field
  /// rather than trusting that — an older server missing a column should cost
  /// one preference, not the whole screen.
  static DevicePreferences _preferences(Map<String, dynamic> j) {
    bool flag(String key, bool fallback) =>
        j[key] is bool ? j[key] as bool : fallback;

    const DevicePreferences d = DevicePreferences.defaults;

    return DevicePreferences(
      themeMode: j['themeMode'] as String? ?? d.themeMode,
      language: j['language'] as String? ?? d.language,
      notificationsEnabled:
          flag('notificationsEnabled', d.notificationsEnabled),
      reportReminders: flag('reportReminders', d.reportReminders),
      sessionReminders: flag('sessionReminders', d.sessionReminders),
      systemNotifications: flag('systemNotifications', d.systemNotifications),
      highAccuracyMode: flag('highAccuracyMode', d.highAccuracyMode),
      syncOnMobileData: flag('syncOnMobileData', d.syncOnMobileData),
      batterySaver: flag('batterySaver', d.batterySaver),
    );
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
