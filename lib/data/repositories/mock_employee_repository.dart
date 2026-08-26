import '../../core/utils/formatters.dart';
import '../../state/notification_center.dart';
import '../mock/day_lock_store.dart';
import '../mock/mock_data.dart';
import '../mock/product_store.dart';
import '../models/models.dart';
import 'employee_repository.dart';

/// In-memory [EmployeeRepository] backed by [MockData].
///
/// Adds a small artificial delay so loading / empty / error states are real
/// code paths rather than decoration.
class MockEmployeeRepository implements EmployeeRepository {
  MockEmployeeRepository({
    this.latency = const Duration(milliseconds: 450),
    ProductStore? products,
    NotificationCenter? notifications,
    DayLockStore? dayLock,
  })  : _products = products ?? ProductStore(),
        _notifications = notifications ?? NotificationCenter(),
        _dayLock = dayLock ?? DayLockStore();

  final Duration latency;

  /// Shared with `MockAdminRepository` — see [ProductStore]. Reading the store
  /// rather than `ProductCatalog` is what makes an admin listing show up here.
  final ProductStore _products;
  final NotificationCenter _notifications;

  final List<VisitReport> _extraReports = <VisitReport>[];
  int _draftSeq = 0;
  int _imageSeq = 0;

  /// Device preferences, held for the run. `USE_MOCKS=true` has no server, so
  /// this is the whole store — [SettingsController] still has its own
  /// `shared_preferences` cache underneath, which is what survives a restart.
  DevicePreferences _preferences = DevicePreferences.defaults;

  /// Shared with `MockAdminRepository` — see [DayLockStore]. This is what
  /// makes an admin reopen show up on the employee's Home.
  final DayLockStore _dayLock;

  /// Mutable so [updateProfile] is observable across calls, the way the real
  /// endpoint is. Seeded from the fixture.
  Employee _me = MockData.employee;

  DayCloseout? get _closeout => _dayLock.closeout;

  Future<T> _delayed<T>(T value) => Future<T>.delayed(latency, () => value);

  List<VisitReport> get _reports =>
      <VisitReport>[..._extraReports, ...MockData.allReports];

  @override
  Future<Employee> profile() => _delayed(_me);

  /// Mirrors the server's field whitelist: everything else on the record is
  /// admin-owned and stays put.
  @override
  Future<Employee> updateProfile({String? phone, String? avatarUrl}) {
    _me = _me.copyWith(phone: phone, avatarUrl: avatarUrl);
    return _delayed(_me);
  }

  @override
  Future<HomeSnapshot> home() {
    final List<VisitReport> today = _reports
        .where((VisitReport r) => Fmt.isSameDay(r.submittedAt, MockData.today))
        .toList();

    return _delayed(
      HomeSnapshot(
        status: _closeout == null ? WorkStatus.working : WorkStatus.ended,
        movement: MovementStatus.moving,
        locationHealth: LocationHealth.ok,
        sessions: MockData.todaySessions,
        summary: DaySummary(
          joiningTime: MockData.todaySummary.joiningTime,
          endTime: MockData.todaySummary.endTime,
          workedDuration: MockData.todaySummary.workedDuration,
          sessionCount: MockData.todaySummary.sessionCount,
          distanceKm: MockData.todaySummary.distanceKm,
          companiesVisited: MockData.todaySummary.companiesVisited,
          reportsSubmitted: today.length,
          stopDuration: MockData.todaySummary.stopDuration,
          longestStop: MockData.todaySummary.longestStop,
        ),
        activity: MockData.todayActivity(),
        visits: MockData.todayVisits,
        stops: MockData.todayStops,
        lastFix: MockData.lastFix,
        sync: MockData.syncSnapshot,
        dayState: _dayLock.state,
        closeout: _closeout,
      ),
    );
  }

  @override
  Future<List<ActivityEvent>> activity({DateTime? date}) {
    if (date != null && !Fmt.isSameDay(date, MockData.today)) {
      return _delayed(<ActivityEvent>[]);
    }
    return _delayed(MockData.todayActivity());
  }

  @override
  Future<List<VisitReport>> reports({DateTime? date}) {
    final List<VisitReport> all = _reports.toList()
      ..sort((VisitReport a, VisitReport b) =>
          b.submittedAt.compareTo(a.submittedAt));
    if (date == null) return _delayed(all);
    return _delayed(
      all
          .where((VisitReport r) => Fmt.isSameDay(r.submittedAt, date))
          .toList(growable: false),
    );
  }

  @override
  Future<VisitReport> reportById(String id) {
    final VisitReport report = _reports.firstWhere(
      (VisitReport r) => r.id == id,
      orElse: () => _reports.first,
    );
    return _delayed(report);
  }

  @override
  Future<List<Attendance>> attendance({required DateTime month}) {
    final List<Attendance> rows = MockData.attendanceHistory
        .where((Attendance a) =>
            a.date.year == month.year && a.date.month == month.month)
        .toList(growable: false);
    return _delayed(rows);
  }

  @override
  Future<Attendance?> attendanceForDate(DateTime date) {
    Attendance? match;
    for (final Attendance a in MockData.attendanceHistory) {
      if (Fmt.isSameDay(a.date, date)) {
        match = a;
        break;
      }
    }
    return _delayed(match);
  }

  @override
  Future<List<Company>> companies() => _delayed(MockData.companies);

  @override
  Future<List<Product>> products({ProductCategory? category, String? query}) {
    final String q = (query ?? '').trim().toLowerCase();
    // Delisted products are invisible to the seller.
    List<Product> rows =
        category == null ? _products.active : _products.byCategory(category);
    if (q.isNotEmpty) {
      rows = rows
          .where((Product p) =>
              p.displayName.toLowerCase().contains(q) ||
              p.modelCode.toLowerCase().contains(q))
          .toList(growable: false);
    }
    return _delayed(rows);
  }

  @override
  Future<Product> productById(String id) {
    final Product? p = _products.byId(id);
    if (p == null) {
      return Future<Product>.delayed(
        latency,
        () => throw StateError('Unknown product $id'),
      );
    }
    return _delayed(p);
  }

  @override
  Future<List<AppNotification>> notifications() =>
      _delayed(_notifications.items);

  @override
  Future<void> markNotificationRead(String id) {
    _notifications.markRead(id);
    return _delayed(null);
  }

  @override
  Future<void> markAllNotificationsRead() {
    _notifications.markAllRead();
    return _delayed(null);
  }

  /// Files the text only, with `imageCount: 0`, exactly like `POST /reports`.
  /// The pictures arrive in [uploadReportImages] or they do not arrive at all
  /// — pretending otherwise here would hide the very failure mode the two-step
  /// flow exists to expose.
  @override
  Future<VisitReport> submitReport(ReportDraft draft) async {
    await Future<void>.delayed(latency);
    final VisitReport report = VisitReport(
      id: 'rep_local_${_draftSeq++}',
      companyName: draft.companyName,
      branchName: draft.branchName,
      companyId: draft.companyId,
      branchId: draft.branchId,
      visitId: draft.visitId,
      sessionId: MockData.todaySessions.last.id,
      title: draft.title,
      body: draft.body,
      imageCount: 0,
      submittedAt: DateTime.now(),
      latitude: MockData.lastFix.latitude,
      longitude: MockData.lastFix.longitude,
      status: ReportStatus.submitted,
      dealValue: draft.dealValue,
      followUpOn: draft.followUpOn,
      sales: draft.sales,
      paymentReceived: draft.paymentReceived,
    );
    _extraReports.insert(0, report);
    return report;
  }

  /// Nothing leaves the device, and nothing claims it did: the "stored" url is
  /// the local path the picture already lives at. What this does reproduce
  /// faithfully is the server's validation — an oversized or unsupported file
  /// throws after the acceptable prefix has been stored, so the caller's
  /// partial-upload path is a real code path in mock builds too.
  @override
  Future<List<ReportImage>> uploadReportImages(
    String reportId,
    List<ReportAttachment> images,
  ) async {
    await Future<void>.delayed(latency);

    if (images.isEmpty) {
      return const <ReportImage>[];
    }

    final List<ReportImage> stored = <ReportImage>[];

    for (final ReportAttachment a in images) {
      final String? rejection = a.rejection;
      if (rejection != null) {
        _bumpImageCount(reportId, stored.length);
        throw StateError(rejection);
      }

      stored.add(
        ReportImage(
          id: 'img_local_${_imageSeq++}',
          reportId: reportId,
          url: a.path,
          byteSize: a.byteSize,
        ),
      );
    }

    _bumpImageCount(reportId, stored.length);
    return stored;
  }

  /// Rewrites the stored report with the images it now has. `VisitReport` is
  /// immutable and has no `copyWith`, and adding one to a shared model for the
  /// mock's benefit is not worth it — this is the only caller.
  void _bumpImageCount(String reportId, int added) {
    if (added == 0) return;

    final int i = _extraReports.indexWhere((VisitReport r) => r.id == reportId);
    if (i < 0) return;

    final VisitReport r = _extraReports[i];

    _extraReports[i] = VisitReport(
      id: r.id,
      companyName: r.companyName,
      branchName: r.branchName,
      companyId: r.companyId,
      branchId: r.branchId,
      visitId: r.visitId,
      sessionId: r.sessionId,
      title: r.title,
      body: r.body,
      imageCount: r.imageCount + added,
      submittedAt: r.submittedAt,
      latitude: r.latitude,
      longitude: r.longitude,
      status: r.status,
      dealValue: r.dealValue,
      followUpOn: r.followUpOn,
      sales: r.sales,
      paymentReceived: r.paymentReceived,
    );
  }

  @override
  Future<DevicePreferences> preferences() => _delayed(_preferences);

  /// Partial, like the endpoint. No `TrackingConfig` ceiling to clamp against
  /// offline, so the last three flags come back exactly as sent — against the
  /// real server they may not, which is why callers adopt the return value.
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
  }) {
    _preferences = _preferences.copyWith(
      themeMode: themeMode,
      language: language,
      notificationsEnabled: notificationsEnabled,
      reportReminders: reportReminders,
      sessionReminders: sessionReminders,
      systemNotifications: systemNotifications,
      highAccuracyMode: highAccuracyMode,
      syncOnMobileData: syncOnMobileData,
      batterySaver: batterySaver,
    );

    return _delayed(_preferences);
  }

  @override
  Future<DayCloseout> submitDayCloseout(DayCloseoutDraft draft) async {
    await Future<void>.delayed(latency);

    final int visits = MockData.todayVisits.length;
    final DayCloseout stored = DayCloseout(
      id: 'clo_local_${draft.clientId}',
      date: MockData.today,
      submittedAt: DateTime.now(),
      declaredDistanceKm: draft.declaredDistanceKm,
      declaredVisits: draft.declaredVisits,
      measuredDistanceKm: MockData.todaySummary.distanceKm,
      measuredVisits: visits,
      rating: draft.rating,
      tags: draft.tags,
      feedback: draft.feedback,
    );
    // Idempotent, like the endpoint: a retry gets the record already on file.
    return _dayLock.close(stored);
  }

  @override
  Future<PeriodStatistics> statistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  }) {
    final (DateTime start, DateTime end) = _resolveRange(range, from, to);

    final List<Attendance> rows = MockData.attendanceHistory
        .where(
            (Attendance a) => !a.date.isBefore(start) && !a.date.isAfter(end))
        .toList()
      ..sort((Attendance a, Attendance b) => a.date.compareTo(b.date));

    final List<Attendance> worked = rows
        .where((Attendance a) =>
            a.status == AttendanceStatus.present ||
            a.status == AttendanceStatus.partial)
        .toList();

    final List<Attendance> countable = rows
        .where((Attendance a) =>
            a.status != AttendanceStatus.holiday &&
            a.status != AttendanceStatus.weekend &&
            a.status != AttendanceStatus.noData)
        .toList();

    final int n = worked.isEmpty ? 1 : worked.length;

    final int avgJoinMinutes = worked.isEmpty
        ? 0
        : worked
                .map((Attendance a) =>
                    (a.joiningTime?.hour ?? 0) * 60 +
                    (a.joiningTime?.minute ?? 0))
                .reduce((int a, int b) => a + b) ~/
            n;

    final int totalWorkedMinutes = worked.fold<int>(
        0, (int sum, Attendance a) => sum + a.workedDuration.inMinutes);
    final double totalDistance = worked.fold<double>(
        0, (double sum, Attendance a) => sum + a.distanceKm);
    final int totalVisits = worked.fold<int>(
        0, (int sum, Attendance a) => sum + a.companiesVisited);
    final int totalReports = worked.fold<int>(
        0, (int sum, Attendance a) => sum + a.reportsSubmitted);
    final int absentDays = countable
        .where((Attendance a) => a.status == AttendanceStatus.absent)
        .length;

    return _delayed(
      PeriodStatistics(
        rangeLabel: _rangeLabel(range, start, end),
        averageJoiningTime: DateTime(
          start.year,
          start.month,
          start.day,
          avgJoinMinutes ~/ 60,
          avgJoinMinutes % 60,
        ),
        averageWorkedDuration: Duration(minutes: totalWorkedMinutes ~/ n),
        averageDistanceKm: totalDistance / n,
        totalDistanceKm: totalDistance,
        averageVisitsPerDay: totalVisits / n,
        totalVisits: totalVisits,
        totalReports: totalReports,
        averageReportsPerDay: totalReports / n,
        workingDays: countable.length,
        presentDays: worked.length,
        absentDays: absentDays,
        attendancePercent:
            countable.isEmpty ? 0 : worked.length / countable.length * 100,
        dailyDistance: worked
            .map((Attendance a) => DailyMetric(
                  date: a.date,
                  value: a.distanceKm,
                  secondaryValue: a.companiesVisited,
                ))
            .toList(growable: false),
      ),
    );
  }

  (DateTime, DateTime) _resolveRange(
      StatsRange range, DateTime? from, DateTime? to) {
    final DateTime today = MockData.today;
    return switch (range) {
      StatsRange.thisWeek => (
          today.subtract(Duration(days: today.weekday - 1)),
          today,
        ),
      StatsRange.thisMonth => (DateTime(today.year, today.month, 1), today),
      StatsRange.lastMonth => (
          DateTime(today.year, today.month - 1, 1),
          DateTime(today.year, today.month, 0),
        ),
      StatsRange.custom => (
          from ?? today.subtract(const Duration(days: 30)),
          to ?? today,
        ),
    };
  }

  String _rangeLabel(StatsRange range, DateTime start, DateTime end) =>
      switch (range) {
        StatsRange.thisWeek =>
          'This week · ${Fmt.monthDay(start)} – ${Fmt.monthDay(end)}',
        StatsRange.thisMonth => Fmt.monthYear(start),
        StatsRange.lastMonth => Fmt.monthYear(start),
        StatsRange.custom =>
          '${Fmt.mediumDate(start)} – ${Fmt.mediumDate(end)}',
      };
}
