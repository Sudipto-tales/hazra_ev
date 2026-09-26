import '../../core/config/tracking_config.dart';
import '../api/api_client.dart';
import '../api/wire.dart';
import '../models/models.dart';
import 'admin_repository.dart';

/// `AdminRepository` over `website/api`.
///
/// Several of these methods share a route: the dashboard, the roster and
/// member-by-id are all `GET /employees` at a different scope, and the three
/// report lists are all `GET /reports` with different filters. That collapse is
/// the plan's whole design, so it shows up here as several methods pointing at
/// one path rather than as several paths.
class HttpAdminRepository implements AdminRepository {
  HttpAdminRepository(this._api);

  final ApiClient _api;

  static const String _dayIncludes =
      'sessions,stops,visits,reports,activity,summary,lastFix,attendance,'
      'closeout';

  @override
  Future<AdminUser> profile() async {
    final ApiResult result = await _api.get('/me');
    return Wire.admin(result.map);
  }

  /// The dashboard is this endpoint, not a separate /dashboard — the whole
  /// TeamOverview aggregate rides in `meta.totals`.
  @override
  Future<TeamOverview> overview({DateTime? date}) async {
    final ApiResult result =
        await _api.get('/employees', query: <String, dynamic>{
      'include': 'liveStatus,summary',
      'date': date == null ? 'today' : Wire.day(date),
      'limit': 200,
    });

    return Wire.teamOverview(
      result.list.map(Wire.teamMember).toList(),
      result.meta,
    );
  }

  /// `query` matches name / employeeCode / region / department — all four.
  @override
  Future<List<TeamMember>> team({String? query, WorkStatus? status}) async {
    final ApiResult result =
        await _api.get('/employees', query: <String, dynamic>{
      'include': 'liveStatus,summary',
      'date': 'today',
      if (query != null && query.isNotEmpty) 'query': query,
      if (status != null) 'status': status.name,
      'limit': 200,
    });

    return result.list.map(Wire.teamMember).toList();
  }

  @override
  Future<TeamMember> memberById(String employeeId) async {
    final ApiResult result =
        await _api.get('/employees/$employeeId', query: <String, dynamic>{
      'include': 'liveStatus,summary',
      'date': 'today',
    });

    return Wire.teamMember(result.map);
  }

  @override
  Future<EmployeeDay> employeeDay({
    required String employeeId,
    required DateTime date,
  }) async {
    final ApiResult result =
        await _api.get('/days/$employeeId', query: <String, dynamic>{
      'date': Wire.day(date),
      'include': _dayIncludes,
    });

    return Wire.employeeDay(result.map, _placeholder(employeeId));
  }

  /// A single track is the n = 1 case of the same route.
  @override
  Future<RouteTrack> route({
    required String employeeId,
    required DateTime date,
  }) async {
    final ApiResult result = await _api.get('/routes', query: <String, dynamic>{
      'subject': employeeId,
      'date': Wire.day(date),
      'include': 'stops,visits',
      // Full fidelity for a single employee; the team map thins harder below.
      'simplify': 0,
    });

    final List<Map<String, dynamic>> tracks = result.list;

    return tracks.isEmpty
        ? RouteTrack(
            employeeId: employeeId,
            date: date,
            points: const <LocationLog>[],
            sessions: const <WorkSession>[],
            stops: const <StopRecord>[],
            visits: const <CompanyVisit>[],
            totalDistanceKm: 0,
          )
        : Wire.routeTrack(tracks.first);
  }

  /// Empty tracks come back rather than being dropped, so the map legend can
  /// still list who did not work.
  @override
  Future<List<RouteTrack>> teamRoutes({
    required DateTime date,
    List<String>? employeeIds,
  }) async {
    final ApiResult result = await _api.get('/routes', query: <String, dynamic>{
      'subject': 'team',
      'date': Wire.day(date),
      'include': 'stops,visits',
      if (employeeIds != null && employeeIds.isNotEmpty)
        'employeeIds': employeeIds,
      // 8 m is invisible on an overview and roughly a 6x point reduction.
      'simplify': 8,
    });

    return result.list.map(Wire.routeTrack).toList();
  }

  @override
  Future<List<Attendance>> employeeAttendance({
    required String employeeId,
    required DateTime month,
  }) async {
    final ApiResult result =
        await _api.get('/attendance', query: <String, dynamic>{
      'subject': employeeId,
      'month': Wire.month(month),
    });

    return result.list.map(Wire.attendance).toList();
  }

  @override
  Future<List<VisitReport>> employeeReports(
    String employeeId, {
    DateTime? date,
  }) async {
    final ApiResult result =
        await _api.get('/reports', query: <String, dynamic>{
      'subject': employeeId,
      if (date != null) 'date': Wire.day(date),
      'limit': 100,
    });

    return result.list.map(Wire.report).toList();
  }

  /// `format=grid` pivots server-side, so the matrix does no date arithmetic.
  @override
  Future<TeamAttendanceGrid> teamAttendance({required DateTime month}) async {
    final ApiResult result =
        await _api.get('/attendance', query: <String, dynamic>{
      'subject': 'team',
      'month': Wire.month(month),
      'format': 'grid',
    });

    return Wire.attendanceGrid(result.map);
  }

  @override
  Future<TeamStatistics> teamStatistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  }) async {
    final ApiResult result =
        await _api.get('/statistics', query: <String, dynamic>{
      'subject': 'team',
      'range': range.name,
      if (from != null) 'from': Wire.day(from),
      if (to != null) 'to': Wire.day(to),
      'include': 'perEmployee,daily',
    });

    return Wire.teamStatistics(result.map);
  }

  /// The review inbox. `employeeId` narrows the subject; without it the scope
  /// is the whole org.
  @override
  Future<List<ReportInboxItem>> inbox({
    ReviewDecision? decision,
    String? employeeId,
    DateTime? date,
    String? query,
  }) async {
    final ApiResult result =
        await _api.get('/reports', query: <String, dynamic>{
      'subject': employeeId ?? 'all',
      'include': 'employee,review',
      if (decision != null) 'decision': decision.name,
      if (date != null) 'date': Wire.day(date),
      if (query != null && query.isNotEmpty) 'query': query,
      'limit': 100,
    });

    return result.list.map(Wire.inboxItem).toList();
  }

  @override
  Future<ReportInboxItem> inboxItem(String reportId) async {
    final ApiResult result =
        await _api.get('/reports/$reportId', query: <String, dynamic>{
      'include': 'employee,review',
    });

    return Wire.inboxItem(result.map);
  }

  /// Returns the review and flips the report to `reviewed` in the same
  /// response, so nothing has to re-read afterwards.
  @override
  Future<ReportReview> reviewReport({
    required String reportId,
    required ReviewDecision decision,
    String? note,
  }) async {
    final ApiResult result = await _api.post(
      '/reports/$reportId/review',
      body: <String, dynamic>{
        'decision': decision.name,
        if (note != null && note.isNotEmpty) 'note': note,
      },
    );

    return Wire.review(
      Map<String, dynamic>.from(result.map['review'] as Map),
    );
  }

  /// `limit=0` returns meta.total with an empty data array. That is the badge.
  @override
  Future<int> pendingReviewCount() async {
    final ApiResult result =
        await _api.get('/reports', query: <String, dynamic>{
      'subject': 'all',
      'decision': 'pending',
      'limit': 0,
    });

    return result.total;
  }

  /// An admin token widens visibility to delisted products too.
  @override
  Future<List<Product>> products({
    ProductCategory? category,
    String? query,
  }) async {
    final ApiResult result =
        await _api.get('/products', query: <String, dynamic>{
      'includeDelisted': true,
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

  /// `active` is a field on the product now, not a side-channel set — this
  /// derives the set the old interface still asks for rather than calling a
  /// route that no longer exists.
  @override
  Future<Set<String>> delistedProductIds() async {
    final ApiResult result =
        await _api.get('/products', query: <String, dynamic>{
      'includeDelisted': true,
      'limit': 200,
    });

    return result.list
        .where((Map<String, dynamic> row) => row['active'] == false)
        .map((Map<String, dynamic> row) => Wire.str(row['id']))
        .toSet();
  }

  @override
  Future<Product> saveProduct(ProductDraft draft) async {
    final Map<String, dynamic> body = Wire.productDraftToJson(draft);

    final ApiResult result = draft.isCreate
        ? await _api.post('/products', body: body)
        : await _api.patch('/products/${draft.id}', body: body);

    return Wire.product(result.map);
  }

  /// Delisting is a field write, not its own route.
  @override
  Future<void> setProductActive(String id, bool active) =>
      _api.patch('/products/$id', body: <String, dynamic>{'active': active});

  @override
  Future<EmployeeSaveResult> saveEmployee(EmployeeDraft draft) async {
    final Map<String, dynamic> body = Wire.employeeDraftToJson(draft);

    final ApiResult result = draft.isCreate
        ? await _api.post('/employees', body: body)
        : await _api.patch('/employees/${draft.id}', body: body);

    // `temporaryPassword` rides on the create response and nowhere else. It is
    // not stored in plaintext and no route returns it again, so if the caller
    // drops it the only way back is a reset.
    return EmployeeSaveResult(
      Wire.employee(result.map),
      temporaryPassword: result.map['temporaryPassword'] as String?,
    );
  }

  @override
  Future<String?> resetEmployeePassword(
    String employeeId, {
    String? password,
  }) async {
    final ApiResult result = await _api.post(
      '/employees/$employeeId/password',
      body: <String, dynamic>{
        if (password != null && password.isNotEmpty) 'password': password,
      },
    );

    return result.map['temporaryPassword'] as String?;
  }

  @override
  Future<void> setEmployeeActive(String employeeId, bool active) =>
      _api.patch('/employees/$employeeId',
          body: <String, dynamic>{'active': active});

  /// `POST /days/{id}/reopen`. The reason is stored server-side with the
  /// admin's identity, which is what makes a shifted day explainable later.
  @override
  Future<void> reopenDay({
    required String employeeId,
    required DateTime date,
    required String reason,
  }) =>
      _api.post('/days/$employeeId/reopen', body: <String, dynamic>{
        'date': Wire.day(date),
        'reason': reason,
      });

  @override
  Future<TrackingConfig> config() async {
    final ApiResult result = await _api.get('/config');
    return Wire.config(result.map);
  }

  @override
  Future<TrackingConfig> saveConfig(TrackingConfig config) async {
    final ApiResult result =
        await _api.put('/config', body: Wire.configToJson(config));

    return Wire.config(result.map);
  }

  /// `GET /days/{id}` carries the employee for an admin subject, so this is
  /// only ever a fallback for a malformed payload.
  Employee _placeholder(String employeeId) => Employee(
        id: employeeId,
        employeeCode: '',
        name: '',
        designation: '',
        department: '',
        email: '',
        phone: '',
        avatarUrl: '',
        bannerUrl: '',
        joinedOn: DateTime.now(),
        reportingTo: '',
        region: '',
        bloodGroup: '',
        address: '',
      );
}
