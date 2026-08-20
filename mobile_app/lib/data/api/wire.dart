import '../../core/config/tracking_config.dart';
import '../models/models.dart';

/// JSON → model. The mirror of `website/api/support/Present.php`.
///
/// Kept out of the model classes on purpose: `lib/data/models/` is pure data
/// with no serialisation and no Flutter imports, and it stays that way. If the
/// wire shape drifts, exactly one file changes.
///
/// Conventions the API guarantees (API plan §1):
///   * timestamps  ISO-8601 UTC with a trailing `Z` → parsed, then `.toLocal()`
///   * durations   integer seconds
///   * distances   kilometres, double
///   * enums       the Dart enum's own `name` (camelCase)
///   * money       free text, never a number — `dealValue` / `paymentReceived`
class Wire {
  const Wire._();

  // ------------------------------------------------------------- primitives

  static T _enum<T extends Enum>(List<T> values, Object? raw, T fallback) {
    if (raw is! String) return fallback;
    for (final T value in values) {
      if (value.name == raw) return value;
    }
    return fallback;
  }

  static DateTime? dateOrNull(Object? raw) {
    if (raw is! String || raw.isEmpty) return null;
    return DateTime.tryParse(raw)?.toLocal();
  }

  static DateTime date(Object? raw, [DateTime? fallback]) =>
      dateOrNull(raw) ?? fallback ?? DateTime.now();

  static Duration duration(Object? raw) =>
      Duration(seconds: (raw as num?)?.toInt() ?? 0);

  static Duration? durationOrNull(Object? raw) =>
      raw == null ? null : Duration(seconds: (raw as num).toInt());

  static double dbl(Object? raw) => (raw as num?)?.toDouble() ?? 0;

  static int integer(Object? raw) => (raw as num?)?.toInt() ?? 0;

  static bool boolean(Object? raw) => raw == true;

  static String str(Object? raw) => raw as String? ?? '';

  static String? strOrNull(Object? raw) {
    final String? value = raw as String?;
    return value == null || value.isEmpty ? null : value;
  }

  static List<Map<String, dynamic>> maps(Object? raw) {
    if (raw is! List) return const <Map<String, dynamic>>[];
    return raw
        .whereType<Map<dynamic, dynamic>>()
        .map((Map<dynamic, dynamic> e) => Map<String, dynamic>.from(e))
        .toList();
  }

  static List<String> strings(Object? raw) {
    if (raw is! List) return const <String>[];
    return raw.whereType<String>().toList();
  }

  // ---------------------------------------------------------------- identity

  static Employee employee(Map<String, dynamic> j) => Employee(
        id: str(j['id']),
        employeeCode: str(j['employeeCode']),
        name: str(j['name']),
        designation: str(j['designation']),
        department: str(j['department']),
        email: str(j['email']),
        phone: str(j['phone']),
        avatarUrl: str(j['avatarUrl']),
        bannerUrl: str(j['bannerUrl']),
        joinedOn: date(j['joinedOn']),
        // Rendered server-side from the manager id now; the raw
        // `reportingToId` also rides along for when the UI stops using a string.
        reportingTo: str(j['reportingTo']),
        region: str(j['region']),
        bloodGroup: str(j['bloodGroup']),
        address: str(j['address']),
      );

  static AdminUser admin(Map<String, dynamic> j) => AdminUser(
        id: str(j['id']),
        adminCode: str(j['adminCode']),
        name: str(j['name']),
        role: str(j['role']),
        email: str(j['email']),
        phone: str(j['phone']),
        avatarUrl: str(j['avatarUrl']),
        region: str(j['region']),
      );

  static TrackingConfig config(Map<String, dynamic> j) => TrackingConfig(
        locationIntervalSeconds: integer(j['locationIntervalSeconds']),
        minAccuracyMetres: dbl(j['minAccuracyMetres']),
        stopRadiusMetres: dbl(j['stopRadiusMetres']),
        stopThresholdMinutes: integer(j['stopThresholdMinutes']),
        longStopThresholdMinutes: integer(j['longStopThresholdMinutes']),
        movementSpeedThresholdKmh: dbl(j['movementSpeedThresholdKmh']),
        maxJumpKmh: dbl(j['maxJumpKmh']),
        offlineThresholdMinutes: integer(j['offlineThresholdMinutes']),
        locationUnavailableThresholdMinutes:
            integer(j['locationUnavailableThresholdMinutes']),
        syncBatchSize: integer(j['syncBatchSize']),
      );

  static Map<String, dynamic> configToJson(TrackingConfig c) =>
      <String, dynamic>{
        'locationIntervalSeconds': c.locationIntervalSeconds,
        'minAccuracyMetres': c.minAccuracyMetres,
        'stopRadiusMetres': c.stopRadiusMetres,
        'stopThresholdMinutes': c.stopThresholdMinutes,
        'longStopThresholdMinutes': c.longStopThresholdMinutes,
        'movementSpeedThresholdKmh': c.movementSpeedThresholdKmh,
        'maxJumpKmh': c.maxJumpKmh,
        'offlineThresholdMinutes': c.offlineThresholdMinutes,
        'locationUnavailableThresholdMinutes':
            c.locationUnavailableThresholdMinutes,
        'syncBatchSize': c.syncBatchSize,
      };

  // -------------------------------------------------------------- aggregates

  static DaySummary daySummary(Map<String, dynamic>? j) {
    if (j == null) return DaySummary.empty;

    return DaySummary(
      joiningTime: dateOrNull(j['joiningTime']),
      endTime: dateOrNull(j['endTime']),
      workedDuration: duration(j['workedDuration']),
      sessionCount: integer(j['sessionCount']),
      distanceKm: dbl(j['distanceKm']),
      companiesVisited: integer(j['companiesVisited']),
      reportsSubmitted: integer(j['reportsSubmitted']),
      stopDuration: duration(j['stopDuration']),
      longestStop: duration(j['longestStop']),
    );
  }

  /// The API nests the nine DaySummary fields under `summary` (API plan §3.5);
  /// the Dart `Attendance` is flat, so this is where the two shapes meet.
  static Attendance attendance(Map<String, dynamic> j) {
    final Map<String, dynamic> s =
        Map<String, dynamic>.from((j['summary'] as Map?) ?? <String, dynamic>{});

    return Attendance(
      id: str(j['id']),
      date: date(j['date']),
      status: _enum(AttendanceStatus.values, j['status'], AttendanceStatus.noData),
      joiningTime: dateOrNull(s['joiningTime']),
      endTime: dateOrNull(s['endTime']),
      sessionCount: integer(s['sessionCount']),
      workedDuration: duration(s['workedDuration']),
      distanceKm: dbl(s['distanceKm']),
      companiesVisited: integer(s['companiesVisited']),
      reportsSubmitted: integer(s['reportsSubmitted']),
      stopDuration: duration(s['stopDuration']),
      longestStop: duration(s['longestStop']),
    );
  }

  // -------------------------------------------------------- sessions & fixes

  static WorkSession session(Map<String, dynamic> j) => WorkSession(
        id: str(j['id']),
        index: integer(j['index']),
        startTime: date(j['startTime']),
        endTime: dateOrNull(j['endTime']),
        distanceKm: dbl(j['distanceKm']),
        locationPoints: integer(j['locationPoints']),
        startLatitude: dbl(j['startLatitude']),
        startLongitude: dbl(j['startLongitude']),
      );

  static LocationLog? fixOrNull(Object? raw) {
    if (raw is! Map) return null;
    return fix(Map<String, dynamic>.from(raw));
  }

  static LocationLog fix(Map<String, dynamic> j) => LocationLog(
        id: str(j['id']),
        sessionId: str(j['sessionId']),
        latitude: dbl(j['latitude']),
        longitude: dbl(j['longitude']),
        accuracy: dbl(j['accuracy']),
        speedKmh: dbl(j['speedKmh']),
        recordedAt: date(j['recordedAt']),
        // The server only ever reports synced; queued/failed are device state.
        syncState: _enum(SyncState.values, j['syncState'], SyncState.synced),
      );

  static StopRecord stop(Map<String, dynamic> j) => StopRecord(
        id: str(j['id']),
        sessionId: str(j['sessionId']),
        arrival: date(j['arrival']),
        departure: dateOrNull(j['departure']),
        latitude: dbl(j['latitude']),
        longitude: dbl(j['longitude']),
        radiusMetres: dbl(j['radiusMetres']),
        visitId: strOrNull(j['visitId']),
      );

  static CompanyVisit visit(Map<String, dynamic> j) => CompanyVisit(
        id: str(j['id']),
        sessionId: str(j['sessionId']),
        companyId: str(j['companyId']),
        branchId: strOrNull(j['branchId']),
        stopId: strOrNull(j['stopId']),
        arrival: date(j['arrival']),
        departure: dateOrNull(j['departure']),
        latitude: dbl(j['latitude']),
        longitude: dbl(j['longitude']),
        status: _enum(VisitStatus.values, j['status'], VisitStatus.unassigned),
        reportIds: strings(j['reportIds']),
        dealReference: strOrNull(j['dealReference']),
      );

  static SyncSnapshot sync(Map<String, dynamic>? j) => SyncSnapshot(
        queued: integer(j?['queued']),
        failed: integer(j?['failed']),
        lastSyncedAt: dateOrNull(j?['lastSyncedAt']),
        isOnline: j?['isOnline'] as bool? ?? true,
      );

  // ----------------------------------------------------------------- reports

  static ProductSaleLine saleLine(Map<String, dynamic> j) => ProductSaleLine(
        productId: str(j['productId']),
        productName: str(j['productName']),
        category: _enum(
          ProductCategory.values,
          j['category'],
          ProductCategory.others,
        ),
        colorName: str(j['colorName']),
        colorArgb: integer(j['colorArgb']),
        units: integer(j['units']),
      );

  static VisitReport report(Map<String, dynamic> j) => VisitReport(
        id: str(j['id']),
        companyName: str(j['companyName']),
        branchName: strOrNull(j['branchName']),
        companyId: strOrNull(j['companyId']),
        branchId: strOrNull(j['branchId']),
        visitId: strOrNull(j['visitId']),
        sessionId: str(j['sessionId']),
        title: str(j['title']),
        body: str(j['body']),
        imageCount: integer(j['imageCount']),
        submittedAt: date(j['submittedAt']),
        latitude: dbl(j['latitude']),
        longitude: dbl(j['longitude']),
        status: _enum(ReportStatus.values, j['status'], ReportStatus.submitted),
        // Free text, exactly as the seller typed it.
        dealValue: strOrNull(j['dealValue']),
        followUpOn: dateOrNull(j['followUpOn']),
        sales: maps(j['sales']).map(saleLine).toList(),
        paymentReceived: strOrNull(j['paymentReceived']),
      );

  static ReportReview review(Map<String, dynamic> j) => ReportReview(
        reportId: str(j['reportId']),
        decision:
            _enum(ReviewDecision.values, j['decision'], ReviewDecision.pending),
        note: strOrNull(j['note']),
        reviewedBy: strOrNull(j['reviewedBy']),
        reviewedAt: dateOrNull(j['reviewedAt']),
      );

  /// `?include=employee,review` turns each row into an envelope rather than a
  /// bare report — the report model carries no owner, so the join lives here.
  static ReportInboxItem inboxItem(Map<String, dynamic> j) {
    final Map<String, dynamic> r =
        Map<String, dynamic>.from(j['report'] as Map);

    return ReportInboxItem(
      report: report(r),
      employee: employee(
        Map<String, dynamic>.from(
          (j['employee'] as Map?) ?? <String, dynamic>{'name': '', 'id': ''},
        ),
      ),
      review: j['review'] == null
          ? ReportReview.pending(str(r['id']))
          : review(Map<String, dynamic>.from(j['review'] as Map)),
      companyName: str(j['companyName'] ?? r['companyName']),
      branchName: strOrNull(j['branchName'] ?? r['branchName']),
    );
  }

  // --------------------------------------------------------------- customers

  static Company company(Map<String, dynamic> j) => Company(
        id: str(j['id']),
        name: str(j['name']),
        category: str(j['category']),
        branches: maps(j['branches']).map(branch).toList(),
      );

  static Branch branch(Map<String, dynamic> j) => Branch(
        id: str(j['id']),
        name: str(j['name']),
        address: str(j['address']),
        latitude: dbl(j['latitude']),
        longitude: dbl(j['longitude']),
      );

  // --------------------------------------------------------------- catalogue

  static Product product(Map<String, dynamic> j) {
    final List<ProductColor> colors =
        maps(j['colors']).map(productColor).toList();

    return Product(
      id: str(j['id']),
      category:
          _enum(ProductCategory.values, j['category'], ProductCategory.others),
      brand: str(j['brand']),
      name: str(j['name']),
      modelCode: str(j['modelCode']),
      rating: dbl(j['rating']),
      warrantyYears: integer(j['warrantyYears']),
      warrantyNote: str(j['warrantyNote']),
      rangeKm: integer(j['rangeKm']),
      topSpeedKmph: integer(j['topSpeedKmph']),
      chargingTime: str(j['chargingTime']),
      batteryCapacity: str(j['batteryCapacity']),
      motorPower: str(j['motorPower']),
      loadCapacityKg: integer(j['loadCapacityKg']),
      // Product.colorByName reads colors.first, so an empty set would crash the
      // detail sheet. The API requires at least one colour on create; this is
      // the guard for older rows.
      colors: colors.isEmpty
          ? const <ProductColor>[ProductColor(name: 'Default', argb: 0xFF9E9E9E)]
          : colors,
      highlights: strings(j['highlights']),
      listedAt: dateOrNull(j['listedAt']),
    );
  }

  static ProductColor productColor(Map<String, dynamic> j) => ProductColor(
        name: str(j['name']),
        argb: integer(j['argb']),
        imageUrls: strings(j['imageUrls']),
        inStock: j['inStock'] as bool? ?? true,
      );

  static Map<String, dynamic> productDraftToJson(ProductDraft d) =>
      <String, dynamic>{
        'category': d.category.name,
        'brand': d.brand,
        'name': d.name,
        'modelCode': d.modelCode,
        'rating': d.rating,
        'warrantyYears': d.warrantyYears,
        'warrantyNote': d.warrantyNote,
        'rangeKm': d.rangeKm,
        'topSpeedKmph': d.topSpeedKmph,
        'chargingTime': d.chargingTime,
        'batteryCapacity': d.batteryCapacity,
        'motorPower': d.motorPower,
        'loadCapacityKg': d.loadCapacityKg,
        'highlights': d.highlights,
        'colors': d.colors
            .map((ProductColor c) => <String, dynamic>{
                  'name': c.name,
                  'argb': c.argb,
                  'inStock': c.inStock,
                  'imageUrls': c.imageUrls,
                })
            .toList(),
      };

  static Map<String, dynamic> employeeDraftToJson(EmployeeDraft d) =>
      <String, dynamic>{
        'name': d.name,
        'employeeCode': d.employeeCode,
        'designation': d.designation,
        // '' is a real value here: department and region are optional free text.
        'department': d.department,
        'region': d.region,
        'email': d.email,
        'phone': d.phone,
        'reportingTo': d.reportingTo,
        'joinedOn': d.joinedOn.toIso8601String().split('T').first,
        'bloodGroup': d.bloodGroup,
        'address': d.address,
      };

  // ------------------------------------------------------- timeline and feed

  static ActivityEvent activity(Map<String, dynamic> j) => ActivityEvent(
        id: str(j['id']),
        type: _enum(ActivityType.values, j['type'], ActivityType.trackingIssue),
        time: date(j['time']),
        endTime: dateOrNull(j['endTime']),
        title: str(j['title']),
        subtitle: strOrNull(j['subtitle']),
        companyName: strOrNull(j['companyName']),
        branchName: strOrNull(j['branchName']),
        duration: durationOrNull(j['duration']),
        reportId: strOrNull(j['reportId']),
        visitId: strOrNull(j['visitId']),
        isAlert: boolean(j['isAlert']),
      );

  static AppNotification notification(Map<String, dynamic> j) => AppNotification(
        id: str(j['id']),
        kind: _enum(
          NotificationKind.values,
          j['kind'],
          NotificationKind.announcement,
        ),
        title: str(j['title']),
        message: str(j['message']),
        createdAt: date(j['createdAt']),
        read: boolean(j['read']),
        productId: strOrNull(j['productId']),
        reportId: strOrNull(j['reportId']),
      );

  // ------------------------------------------------------------ screen views

  static HomeSnapshot homeSnapshot(Map<String, dynamic> j) => HomeSnapshot(
        status: _enum(WorkStatus.values, j['status'], WorkStatus.notStarted),
        movement:
            _enum(MovementStatus.values, j['movement'], MovementStatus.unknown),
        locationHealth:
            _enum(LocationHealth.values, j['locationHealth'], LocationHealth.ok),
        sessions: maps(j['sessions']).map(session).toList(),
        summary: daySummary(
          j['summary'] == null
              ? null
              : Map<String, dynamic>.from(j['summary'] as Map),
        ),
        activity: maps(j['activity']).map(activity).toList(),
        visits: maps(j['visits']).map(visit).toList(),
        stops: maps(j['stops']).map(stop).toList(),
        lastFix: fixOrNull(j['lastFix']),
        sync: sync(
          j['sync'] == null
              ? null
              : Map<String, dynamic>.from(j['sync'] as Map),
        ),
      );

  static EmployeeDay employeeDay(Map<String, dynamic> j, Employee fallback) =>
      EmployeeDay(
        employee: j['employee'] == null
            ? fallback
            : employee(Map<String, dynamic>.from(j['employee'] as Map)),
        date: date(j['date']),
        attendance: j['attendance'] == null
            ? null
            : attendance(Map<String, dynamic>.from(j['attendance'] as Map)),
        status: _enum(WorkStatus.values, j['status'], WorkStatus.notStarted),
        movement:
            _enum(MovementStatus.values, j['movement'], MovementStatus.unknown),
        sessions: maps(j['sessions']).map(session).toList(),
        stops: maps(j['stops']).map(stop).toList(),
        visits: maps(j['visits']).map(visit).toList(),
        reports: maps(j['reports']).map(report).toList(),
        activity: maps(j['activity']).map(activity).toList(),
        summary: daySummary(
          j['summary'] == null
              ? null
              : Map<String, dynamic>.from(j['summary'] as Map),
        ),
        lastFix: fixOrNull(j['lastFix']),
      );

  static TeamMember teamMember(Map<String, dynamic> j) => TeamMember(
        employee: employee(Map<String, dynamic>.from(j['employee'] as Map)),
        status: _enum(WorkStatus.values, j['status'], WorkStatus.notStarted),
        movement:
            _enum(MovementStatus.values, j['movement'], MovementStatus.unknown),
        locationHealth:
            _enum(LocationHealth.values, j['locationHealth'], LocationHealth.ok),
        summary: daySummary(
          j['summary'] == null
              ? null
              : Map<String, dynamic>.from(j['summary'] as Map),
        ),
        attendanceStatus: _enum(
          AttendanceStatus.values,
          j['attendanceStatus'],
          AttendanceStatus.noData,
        ),
        active: j['active'] as bool? ?? true,
        lastFix: fixOrNull(j['lastFix']),
        activeSince: dateOrNull(j['activeSince']),
        openStopDuration: durationOrNull(j['openStopDuration']),
        openStopCompanyId: strOrNull(j['openStopCompanyId']),
      );

  /// The dashboard aggregate rides in `meta.totals` of the same call that
  /// returns the roster — there is no separate /dashboard route.
  static TeamOverview teamOverview(
    List<TeamMember> members,
    Map<String, dynamic> meta,
  ) {
    final Map<String, dynamic> t = Map<String, dynamic>.from(
      (meta['totals'] as Map?) ?? <String, dynamic>{},
    );

    return TeamOverview(
      date: date(meta['date']),
      members: members,
      totalDistanceKm: dbl(t['totalDistanceKm']),
      totalVisits: integer(t['totalVisits']),
      totalReports: integer(t['totalReports']),
      presentCount: integer(t['presentCount']),
      absentCount: integer(t['absentCount']),
      workingCount: integer(t['workingCount']),
      idleCount: integer(t['idleCount']),
      offlineCount: integer(t['offlineCount']),
      pendingReviewCount: integer(t['pendingReviewCount']),
      longStopCount: integer(t['longStopCount']),
    );
  }

  /// `points` arrive as flat `[lat, lng, t, acc, spd, sessionIdx]` tuples —
  /// roughly a 4x saving over objects on the largest payload in the system.
  /// `sessionIdx` is the session's 1-based `index`, so it is resolved back to
  /// the session id here; `RouteTrack.segments` groups on that id to keep a
  /// break from drawing a phantom straight line.
  static RouteTrack routeTrack(Map<String, dynamic> j) {
    final List<WorkSession> sessions =
        maps(j['sessions']).map(session).toList();

    final Map<int, String> sessionIdByIndex = <int, String>{
      for (final WorkSession s in sessions) s.index: s.id,
    };

    final String employeeId = str(j['employeeId']);
    final List<LocationLog> points = <LocationLog>[];
    final List<dynamic> raw = (j['points'] as List<dynamic>?) ?? <dynamic>[];

    for (int i = 0; i < raw.length; i++) {
      final dynamic tuple = raw[i];
      if (tuple is! List || tuple.length < 3) continue;

      final int seq = tuple.length > 5 ? (tuple[5] as num).toInt() : 1;

      points.add(
        LocationLog(
          // The tuple carries no id — the route only needs a stable key.
          id: '$employeeId#$i',
          sessionId: sessionIdByIndex[seq] ?? 'session-$seq',
          latitude: (tuple[0] as num).toDouble(),
          longitude: (tuple[1] as num).toDouble(),
          accuracy: tuple.length > 3 ? (tuple[3] as num).toDouble() : 0,
          speedKmh: tuple.length > 4 ? (tuple[4] as num).toDouble() : 0,
          recordedAt: DateTime.fromMillisecondsSinceEpoch(
            (tuple[2] as num).toInt() * 1000,
            isUtc: true,
          ).toLocal(),
          syncState: SyncState.synced,
        ),
      );
    }

    return RouteTrack(
      employeeId: employeeId,
      date: date(j['date']),
      points: points,
      sessions: sessions,
      stops: maps(j['stops']).map(stop).toList(),
      visits: maps(j['visits']).map(visit).toList(),
      totalDistanceKm: dbl(j['totalDistanceKm']),
    );
  }

  // ------------------------------------------------------------- statistics

  static DailyMetric dailyMetric(Map<String, dynamic> j) => DailyMetric(
        date: date(j['date']),
        value: dbl(j['value']),
        secondaryValue: integer(j['secondaryValue']),
      );

  static PeriodStatistics periodStatistics(Map<String, dynamic> j) =>
      PeriodStatistics(
        rangeLabel: str(j['rangeLabel']),
        averageJoiningTime: date(j['averageJoiningTime']),
        averageWorkedDuration: duration(j['averageWorkedDuration']),
        averageDistanceKm: dbl(j['averageDistanceKm']),
        totalDistanceKm: dbl(j['totalDistanceKm']),
        averageVisitsPerDay: dbl(j['averageVisitsPerDay']),
        totalVisits: integer(j['totalVisits']),
        totalReports: integer(j['totalReports']),
        averageReportsPerDay: dbl(j['averageReportsPerDay']),
        workingDays: integer(j['workingDays']),
        presentDays: integer(j['presentDays']),
        absentDays: integer(j['absentDays']),
        attendancePercent: dbl(j['attendancePercent']),
        dailyDistance: maps(j['dailyDistance']).map(dailyMetric).toList(),
      );

  static EmployeeMetric employeeMetric(Map<String, dynamic> j) => EmployeeMetric(
        employeeId: str(j['employeeId']),
        name: str(j['name']),
        initials: str(j['initials']),
        distanceKm: dbl(j['distanceKm']),
        visits: integer(j['visits']),
        reports: integer(j['reports']),
        presentDays: integer(j['presentDays']),
        absentDays: integer(j['absentDays']),
        attendancePercent: dbl(j['attendancePercent']),
        averageWorkedDuration: duration(j['averageWorkedDuration']),
      );

  static TeamStatistics teamStatistics(Map<String, dynamic> j) => TeamStatistics(
        rangeLabel: str(j['rangeLabel']),
        perEmployee: maps(j['perEmployee']).map(employeeMetric).toList(),
        totalDistanceKm: dbl(j['totalDistanceKm']),
        totalVisits: integer(j['totalVisits']),
        totalReports: integer(j['totalReports']),
        averageDistanceKm: dbl(j['averageDistanceKm']),
        averageWorkedDuration: duration(j['averageWorkedDuration']),
        attendancePercent: dbl(j['attendancePercent']),
        workingDays: integer(j['workingDays']),
        dailyDistance: maps(j['dailyDistance']).map(dailyMetric).toList(),
      );

  /// `format=grid` pivots server-side and keys cells by `yyyymmdd`, so the
  /// client does no date arithmetic. JSON object keys are strings, hence the
  /// parse back to the int key `TeamAttendanceRow.byDayKey` expects.
  static TeamAttendanceGrid attendanceGrid(Map<String, dynamic> j) {
    final List<DateTime> days = strings(j['days'])
        .map((String d) => DateTime.parse(d))
        .toList();

    return TeamAttendanceGrid(
      month: days.isEmpty ? DateTime.now() : days.first,
      days: days,
      rows: maps(j['rows']).map(attendanceRow).toList(),
    );
  }

  static TeamAttendanceRow attendanceRow(Map<String, dynamic> j) {
    final Map<int, AttendanceStatus> byDayKey = <int, AttendanceStatus>{};
    final Map<String, dynamic> raw = Map<String, dynamic>.from(
      (j['byDayKey'] as Map?) ?? <String, dynamic>{},
    );

    raw.forEach((String key, dynamic value) {
      final int? day = int.tryParse(key);
      if (day == null) return;
      byDayKey[day] =
          _enum(AttendanceStatus.values, value, AttendanceStatus.noData);
    });

    return TeamAttendanceRow(
      employee: employee(Map<String, dynamic>.from(j['employee'] as Map)),
      byDayKey: byDayKey,
      presentDays: integer(j['presentDays']),
      absentDays: integer(j['absentDays']),
      partialDays: integer(j['partialDays']),
      attendancePercent: dbl(j['attendancePercent']),
    );
  }

  /// `?date=` and `?month=` take plain calendar days — never an instant.
  static String day(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-'
      '${d.month.toString().padLeft(2, '0')}-'
      '${d.day.toString().padLeft(2, '0')}';

  static String month(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}';
}
