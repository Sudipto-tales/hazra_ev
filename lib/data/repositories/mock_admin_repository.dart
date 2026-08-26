import '../../core/config/tracking_config.dart';
import '../../core/utils/formatters.dart';
import '../../state/notification_center.dart';
import '../mock/admin_mock_data.dart';
import '../mock/day_lock_store.dart';
import '../mock/mock_data.dart';
import '../mock/product_store.dart';
import '../models/models.dart';
import 'admin_repository.dart';

/// In-memory admin backend.
///
/// Mirrors `MockEmployeeRepository`: a small artificial delay so loading and
/// empty states are real code paths, and never throws. Writes (reviews,
/// employee edits, product listings, thresholds) are held in overlay maps and
/// composed over the generated base on every read, so an approval is visible
/// across every screen immediately — and is lost on restart, exactly like the
/// employee side's submitted reports.
///
/// [products] is the exception: it is backed by the shared [ProductStore], the
/// same instance the employee repository reads, so a product the admin lists
/// shows up in the employee's report form with no plumbing in between.
class MockAdminRepository implements AdminRepository {
  MockAdminRepository({
    this.latency = const Duration(milliseconds: 450),
    ProductStore? products,
    NotificationCenter? notifications,
    DayLockStore? dayLock,
  })  : _products = products ?? ProductStore(),
        _notifications = notifications ?? NotificationCenter(),
        _dayLock = dayLock ?? DayLockStore();

  final Duration latency;
  final ProductStore _products;
  final NotificationCenter _notifications;

  final List<Employee> _created = <Employee>[];
  final Map<String, Employee> _edited = <String, Employee>{};
  final Set<String> _inactive = <String>{};
  final Map<String, ReportReview> _reviews = <String, ReportReview>{};
  TrackingConfig _config = TrackingConfig.defaults;
  int _seq = 0;

  /// How far back the inbox and the "recent reports" views look.
  static const int _inboxDays = 14;

  Future<T> _delayed<T>(T value) => Future<T>.delayed(latency, () => value);

  // ----------------------------------------------------------------- roster

  List<Employee> get _roster => <Employee>[
        ..._created,
        ...AdminMockData.employees.map((Employee e) => _edited[e.id] ?? e),
      ];

  Employee? _find(String employeeId) {
    for (final Employee e in _roster) {
      if (e.id == employeeId) return e;
    }
    return null;
  }

  /// Shared with `MockEmployeeRepository` — see [DayLockStore]. Only the
  /// mock employee's own day carries a lock; the rest of the generated roster
  /// has no closeout to show.
  final DayLockStore _dayLock;

  EmployeeDay _day(Employee employee, DateTime date) {
    final EmployeeDay day =
        AdminMockData.dayFor(employee: employee, date: date);
    if (employee.id != MockData.employee.id ||
        !Fmt.isSameDay(date, MockData.today)) {
      return day;
    }
    return day.withLock(
      dayState: _dayLock.state,
      closeout: _dayLock.closeout,
    );
  }

  // ---------------------------------------------------------------- identity

  @override
  Future<AdminUser> profile() => _delayed(AdminMockData.admin);

  // --------------------------------------------------------------- dashboard

  @override
  Future<TeamOverview> overview({DateTime? date}) async {
    final DateTime day = Fmt.dayOnly(date ?? MockData.today);
    final List<TeamMember> members =
        _roster.map((Employee e) => _member(e, day)).toList();

    double distance = 0;
    int visits = 0;
    int reports = 0;
    int present = 0;
    int absent = 0;
    int working = 0;
    int idle = 0;
    int offline = 0;
    int longStop = 0;

    for (final TeamMember m in members) {
      distance += m.summary.distanceKm;
      visits += m.summary.companiesVisited;
      reports += m.summary.reportsSubmitted;
      switch (m.attendanceStatus) {
        case AttendanceStatus.present:
        case AttendanceStatus.partial:
          present++;
        case AttendanceStatus.absent:
          absent++;
        case AttendanceStatus.holiday:
        case AttendanceStatus.weekend:
        case AttendanceStatus.noData:
          break;
      }
      switch (m.status) {
        case WorkStatus.working:
          working++;
        case WorkStatus.idle:
          idle++;
        case WorkStatus.offline:
        case WorkStatus.locationUnavailable:
          offline++;
        case WorkStatus.notStarted:
        case WorkStatus.ended:
          break;
      }
      if (m.isLongStop(_config.longStopThresholdMinutes)) longStop++;
    }

    final int pending = _inboxItems()
        .where(
          (ReportInboxItem i) => i.decision == ReviewDecision.pending,
        )
        .length;

    return _delayed(
      TeamOverview(
        date: day,
        members: members,
        totalDistanceKm: double.parse(distance.toStringAsFixed(1)),
        totalVisits: visits,
        totalReports: reports,
        presentCount: present,
        absentCount: absent,
        workingCount: working,
        idleCount: idle,
        offlineCount: offline,
        pendingReviewCount: pending,
        longStopCount: longStop,
      ),
    );
  }

  TeamMember _member(Employee employee, DateTime date) {
    final EmployeeDay day = _day(employee, date);
    final bool isToday = Fmt.isSameDay(date, MockData.today);
    final Duration? dwell =
        isToday ? AdminMockData.openStopDuration(employee.id) : null;
    final WorkSession? open = day.sessions
        .where((WorkSession s) => s.isOpen)
        .fold<WorkSession?>(null, (WorkSession? a, WorkSession s) => s);

    return TeamMember(
      employee: employee,
      status: day.status,
      movement: day.movement,
      locationHealth:
          isToday ? AdminMockData.todayHealth(employee.id) : LocationHealth.ok,
      summary: day.summary,
      attendanceStatus: day.attendance?.status ?? AttendanceStatus.noData,
      active: !_inactive.contains(employee.id),
      lastFix: day.lastFix,
      activeSince: open?.startTime ?? day.summary.joiningTime,
      openStopDuration: dwell,
      openStopCompanyId: dwell == null || day.visits.isEmpty
          ? null
          : day.visits.last.companyId,
    );
  }

  // ------------------------------------------------------------------- team

  @override
  Future<List<TeamMember>> team({String? query, WorkStatus? status}) async {
    final String q = (query ?? '').trim().toLowerCase();
    List<TeamMember> members =
        _roster.map((Employee e) => _member(e, MockData.today)).toList();

    if (q.isNotEmpty) {
      members = members.where((TeamMember m) {
        final Employee e = m.employee;
        return e.name.toLowerCase().contains(q) ||
            e.employeeCode.toLowerCase().contains(q) ||
            e.region.toLowerCase().contains(q) ||
            e.department.toLowerCase().contains(q);
      }).toList();
    }
    if (status != null) {
      members = members.where((TeamMember m) => m.status == status).toList();
    }
    return _delayed(members);
  }

  @override
  Future<TeamMember> memberById(String employeeId) async {
    final Employee employee = _find(employeeId) ?? _roster.first;
    return _delayed(_member(employee, MockData.today));
  }

  // -------------------------------------------------------- per-employee day

  @override
  Future<EmployeeDay> employeeDay({
    required String employeeId,
    required DateTime date,
  }) async {
    final Employee employee = _find(employeeId) ?? _roster.first;
    return _delayed(_day(employee, date));
  }

  @override
  Future<RouteTrack> route({
    required String employeeId,
    required DateTime date,
  }) async {
    final Employee employee = _find(employeeId) ?? _roster.first;
    return _delayed(AdminMockData.routeFor(employee: employee, date: date));
  }

  @override
  Future<List<RouteTrack>> teamRoutes({
    required DateTime date,
    List<String>? employeeIds,
  }) async {
    final List<RouteTrack> tracks = <RouteTrack>[];
    for (final Employee e in _roster) {
      if (employeeIds != null && !employeeIds.contains(e.id)) continue;
      if (_inactive.contains(e.id)) continue;
      tracks.add(AdminMockData.routeFor(employee: e, date: date));
    }
    return _delayed(tracks);
  }

  @override
  Future<List<Attendance>> employeeAttendance({
    required String employeeId,
    required DateTime month,
  }) async {
    final List<Attendance> rows = AdminMockData.attendanceFor(employeeId)
        .where((Attendance a) =>
            a.date.year == month.year && a.date.month == month.month)
        .toList();
    return _delayed(rows);
  }

  @override
  Future<List<VisitReport>> employeeReports(
    String employeeId, {
    DateTime? date,
  }) async {
    final Employee? employee = _find(employeeId);
    if (employee == null) return _delayed(const <VisitReport>[]);

    final List<VisitReport> out = <VisitReport>[];
    if (date != null) {
      out.addAll(_day(employee, date).reports);
    } else {
      for (int i = 0; i < _inboxDays * 3; i++) {
        out.addAll(
            _day(employee, MockData.today.subtract(Duration(days: i))).reports);
      }
    }
    out.sort(
      (VisitReport a, VisitReport b) => b.submittedAt.compareTo(a.submittedAt),
    );
    return _delayed(out);
  }

  // ------------------------------------------------------------- attendance

  @override
  Future<TeamAttendanceGrid> teamAttendance({required DateTime month}) async {
    final int dayCount = DateUtilsLite.daysInMonth(month.year, month.month);
    final List<DateTime> days = List<DateTime>.generate(
      dayCount,
      (int i) => DateTime(month.year, month.month, i + 1),
    );

    final List<TeamAttendanceRow> rows = _roster.map((Employee e) {
      final Map<int, AttendanceStatus> byKey = <int, AttendanceStatus>{};
      int present = 0;
      int absent = 0;
      int partial = 0;
      int countable = 0;

      for (final Attendance a in AdminMockData.attendanceFor(e.id)) {
        if (a.date.year != month.year || a.date.month != month.month) continue;
        byKey[TeamAttendanceRow.dayKey(a.date)] = a.status;
        switch (a.status) {
          case AttendanceStatus.present:
            present++;
            countable++;
          case AttendanceStatus.partial:
            partial++;
            countable++;
          case AttendanceStatus.absent:
            absent++;
            countable++;
          case AttendanceStatus.holiday:
          case AttendanceStatus.weekend:
          case AttendanceStatus.noData:
            break;
        }
      }

      return TeamAttendanceRow(
        employee: e,
        byDayKey: byKey,
        presentDays: present,
        absentDays: absent,
        partialDays: partial,
        attendancePercent:
            countable == 0 ? 0 : (present + partial) * 100 / countable,
      );
    }).toList();

    return _delayed(
      TeamAttendanceGrid(month: month, days: days, rows: rows),
    );
  }

  // ------------------------------------------------------------- statistics

  @override
  Future<TeamStatistics> teamStatistics({
    required StatsRange range,
    DateTime? from,
    DateTime? to,
  }) async {
    final (DateTime start, DateTime end) = _resolveRange(range, from, to);

    final List<EmployeeMetric> perEmployee = <EmployeeMetric>[];
    final Map<int, ({double km, int visits})> daily =
        <int, ({double km, int visits})>{};

    double totalKm = 0;
    int totalVisits = 0;
    int totalReports = 0;
    int totalPresent = 0;
    int totalCountable = 0;
    int workedMinutes = 0;
    int workedDays = 0;

    for (final Employee e in _roster) {
      double km = 0;
      int visits = 0;
      int reports = 0;
      int present = 0;
      int absent = 0;
      int countable = 0;
      int minutes = 0;
      int days = 0;

      for (final Attendance a in AdminMockData.attendanceFor(e.id)) {
        if (a.date.isBefore(start) || a.date.isAfter(end)) continue;
        switch (a.status) {
          case AttendanceStatus.present:
          case AttendanceStatus.partial:
            present++;
            countable++;
            days++;
            km += a.distanceKm;
            visits += a.companiesVisited;
            reports += a.reportsSubmitted;
            minutes += a.workedDuration.inMinutes;
            final int key = TeamAttendanceRow.dayKey(a.date);
            final ({double km, int visits}) prev =
                daily[key] ?? (km: 0, visits: 0);
            daily[key] = (
              km: prev.km + a.distanceKm,
              visits: prev.visits + a.companiesVisited,
            );
          case AttendanceStatus.absent:
            absent++;
            countable++;
          case AttendanceStatus.holiday:
          case AttendanceStatus.weekend:
          case AttendanceStatus.noData:
            break;
        }
      }

      totalKm += km;
      totalVisits += visits;
      totalReports += reports;
      totalPresent += present;
      totalCountable += countable;
      workedMinutes += minutes;
      workedDays += days;

      perEmployee.add(
        EmployeeMetric(
          employeeId: e.id,
          name: e.name,
          initials: e.initials,
          distanceKm: double.parse(km.toStringAsFixed(1)),
          visits: visits,
          reports: reports,
          presentDays: present,
          absentDays: absent,
          attendancePercent: countable == 0 ? 0 : present * 100 / countable,
          averageWorkedDuration:
              Duration(minutes: days == 0 ? 0 : minutes ~/ days),
        ),
      );
    }

    perEmployee.sort(
      (EmployeeMetric a, EmployeeMetric b) =>
          b.distanceKm.compareTo(a.distanceKm),
    );

    final List<int> keys = daily.keys.toList()..sort();
    final List<DailyMetric> series = keys
        .map(
          (int k) => DailyMetric(
            date: DateTime(k ~/ 10000, (k ~/ 100) % 100, k % 100),
            value: double.parse(daily[k]!.km.toStringAsFixed(1)),
            secondaryValue: daily[k]!.visits,
          ),
        )
        .toList();

    return _delayed(
      TeamStatistics(
        rangeLabel: _rangeLabel(range, start, end),
        perEmployee: perEmployee,
        totalDistanceKm: double.parse(totalKm.toStringAsFixed(1)),
        totalVisits: totalVisits,
        totalReports: totalReports,
        averageDistanceKm: workedDays == 0
            ? 0
            : double.parse((totalKm / workedDays).toStringAsFixed(1)),
        averageWorkedDuration: Duration(
          minutes: workedDays == 0 ? 0 : workedMinutes ~/ workedDays,
        ),
        attendancePercent:
            totalCountable == 0 ? 0 : totalPresent * 100 / totalCountable,
        workingDays: series.length,
        dailyDistance: series,
      ),
    );
  }

  (DateTime, DateTime) _resolveRange(
    StatsRange range,
    DateTime? from,
    DateTime? to,
  ) {
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
      range == StatsRange.custom
          ? '${Fmt.mediumDate(start)} – ${Fmt.mediumDate(end)}'
          : range.label;

  // ------------------------------------------------------------------ inbox

  /// Base inbox: the last [_inboxDays] days across the whole roster, with the
  /// review overlay applied.
  List<ReportInboxItem> _inboxItems() {
    final List<ReportInboxItem> out = <ReportInboxItem>[];

    for (final Employee e in _roster) {
      for (int i = 0; i < _inboxDays; i++) {
        final DateTime date = MockData.today.subtract(Duration(days: i));
        for (final VisitReport r in _day(e, date).reports) {
          // Company/branch are free text on the report itself — sellers type
          // whatever shop they walked into, so there is nothing to look up.
          out.add(
            ReportInboxItem(
              report: r,
              employee: e,
              review: _reviews[r.id] ?? ReportReview.pending(r.id),
              companyName: r.companyName,
              branchName: r.branchName,
            ),
          );
        }
      }
    }

    out.sort(
      (ReportInboxItem a, ReportInboxItem b) =>
          b.report.submittedAt.compareTo(a.report.submittedAt),
    );
    return out;
  }

  @override
  Future<List<ReportInboxItem>> inbox({
    ReviewDecision? decision,
    String? employeeId,
    DateTime? date,
    String? query,
  }) async {
    final String q = (query ?? '').trim().toLowerCase();
    List<ReportInboxItem> items = _inboxItems();

    if (decision != null) {
      items =
          items.where((ReportInboxItem i) => i.decision == decision).toList();
    }
    if (employeeId != null) {
      items = items
          .where((ReportInboxItem i) => i.employee.id == employeeId)
          .toList();
    }
    if (date != null) {
      items = items
          .where(
              (ReportInboxItem i) => Fmt.isSameDay(i.report.submittedAt, date))
          .toList();
    }
    if (q.isNotEmpty) {
      items = items.where((ReportInboxItem i) {
        return i.report.title.toLowerCase().contains(q) ||
            i.companyName.toLowerCase().contains(q) ||
            i.employee.name.toLowerCase().contains(q);
      }).toList();
    }
    return _delayed(items);
  }

  @override
  Future<ReportInboxItem> inboxItem(String reportId) async {
    final List<ReportInboxItem> items = _inboxItems();
    final ReportInboxItem item = items.firstWhere(
      (ReportInboxItem i) => i.report.id == reportId,
      orElse: () => items.first,
    );
    return _delayed(item);
  }

  @override
  Future<ReportReview> reviewReport({
    required String reportId,
    required ReviewDecision decision,
    String? note,
  }) async {
    final ReportReview review = ReportReview(
      reportId: reportId,
      decision: decision,
      note: note,
      reviewedBy: AdminMockData.admin.name,
      reviewedAt: DateTime.now(),
    );
    _reviews[reportId] = review;
    return _delayed(review);
  }

  @override
  Future<int> pendingReviewCount() async => _delayed(
        _inboxItems()
            .where((ReportInboxItem i) => i.decision == ReviewDecision.pending)
            .length,
      );

  // ---------------------------------------------------------------- catalogue

  @override
  Future<List<Product>> products({
    ProductCategory? category,
    String? query,
  }) async {
    final String q = (query ?? '').trim().toLowerCase();
    List<Product> rows = category == null
        ? _products.all
        : _products.byCategory(category, activeOnly: false);
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
  Future<Set<String>> delistedProductIds() async => _delayed(
        _products.all
            .where((Product p) => !_products.isActive(p.id))
            .map((Product p) => p.id)
            .toSet(),
      );

  @override
  Future<Product> productById(String id) async {
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
  Future<Product> saveProduct(ProductDraft draft) async {
    final bool isCreate = draft.isCreate;
    final Product product = _products.upsert(draft);
    // Listing a product is the event the field team needs to hear about.
    _notifications.push(
      AppNotification(
        id: 'ntf_${_seq++}_${product.id}',
        kind: isCreate
            ? NotificationKind.newProduct
            : NotificationKind.productUpdated,
        title: isCreate ? 'New product listed' : 'Product updated',
        message: isCreate
            ? '${product.displayName} (${product.category.label}) is now '
                'available in ${product.colors.length} '
                'colour${product.colors.length == 1 ? '' : 's'}. '
                'You can log it on a report.'
            : '${product.displayName} has been updated — check the specs and '
                'colours before your next visit.',
        createdAt: DateTime.now(),
        productId: product.id,
      ),
    );
    return _delayed(product);
  }

  @override
  Future<void> setProductActive(String id, bool active) async {
    _products.setActive(id, active);
    return _delayed(null);
  }

  // ------------------------------------------------------------- management

  @override
  Future<Employee> saveEmployee(EmployeeDraft draft) async {
    final Employee employee;
    if (draft.isCreate) {
      employee = Employee(
        id: 'emp_local_${_seq++}',
        employeeCode: draft.employeeCode,
        name: draft.name,
        designation: draft.designation,
        department: draft.department,
        email: draft.email,
        phone: draft.phone,
        avatarUrl: '',
        bannerUrl: '',
        joinedOn: draft.joinedOn,
        reportingTo: draft.reportingTo,
        region: draft.region,
        bloodGroup: draft.bloodGroup,
        address: draft.address,
      );
      _created.insert(0, employee);
    } else {
      final Employee base = _find(draft.id!) ?? _roster.first;
      employee = base.copyWith(
        employeeCode: draft.employeeCode,
        name: draft.name,
        designation: draft.designation,
        department: draft.department,
        email: draft.email,
        phone: draft.phone,
        joinedOn: draft.joinedOn,
        reportingTo: draft.reportingTo,
        region: draft.region,
        bloodGroup: draft.bloodGroup,
        address: draft.address,
      );
      _edited[employee.id] = employee;
    }

    // A created employee has no generated history yet, and an edited one may
    // have changed something the generator reads — drop their caches.
    AdminMockData.invalidate(employee.id);
    return _delayed(employee);
  }

  @override
  Future<void> reopenDay({
    required String employeeId,
    required DateTime date,
    required String reason,
  }) async {
    await Future<void>.delayed(latency);
    _dayLock.reopen(reason: reason, by: AdminMockData.admin.name);
  }

  @override
  Future<void> setEmployeeActive(String employeeId, bool active) async {
    if (active) {
      _inactive.remove(employeeId);
    } else {
      _inactive.add(employeeId);
    }
    return _delayed(null);
  }

  @override
  Future<TrackingConfig> config() => _delayed(_config);

  @override
  Future<TrackingConfig> saveConfig(TrackingConfig config) async {
    _config = config;
    return _delayed(_config);
  }
}

/// Days-in-month without pulling in a date package.
class DateUtilsLite {
  const DateUtilsLite._();

  static int daysInMonth(int year, int month) =>
      DateTime(year, month + 1, 0).day;
}
