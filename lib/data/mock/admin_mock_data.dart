import 'dart:math';

import '../../core/utils/formatters.dart';
import '../models/models.dart';
import 'mock_data.dart';
import 'product_catalog.dart';

/// Static demo dataset for the admin console.
///
/// [MockData] is single-employee by design. Rather than retrofit an owner id
/// onto every shared model, this file keys per-employee data by id and derives
/// **everything** — sessions, stops, visits, reports, timeline, route — from
/// that employee's [Attendance] row for the day. That single rule is what
/// makes the numbers agree with each other: the map's distance matches the
/// calendar's distance because both come from the same source.
///
/// Employee 0 **is** [MockData.employee], and for that employee today's data
/// is delegated straight to [MockData], so the admin's view of Rahul is
/// identical to what the employee app shows on the same device.
class AdminMockData {
  AdminMockData._();

  static final DateTime today = MockData.today;
  static final DateTime _now = DateTime.now();

  // ------------------------------------------------------------------ seeding

  /// `String.hashCode` is not stable across VM releases, and a newly created
  /// employee must still get a plausible history. Fold the code units instead
  /// so a given id always produces the same dataset.
  /// One or two catalogue lines for a generated report, colour taken from the
  /// product's own set so a line can never claim a colour that is not offered.
  static List<ProductSaleLine> _saleLines(Random rnd) {
    final int lines = rnd.nextInt(100) < 28 ? 2 : 1;
    final List<ProductSaleLine> out = <ProductSaleLine>[];
    for (int i = 0; i < lines; i++) {
      final Product p =
          ProductCatalog.all[rnd.nextInt(ProductCatalog.all.length)];
      if (out.any((ProductSaleLine l) => l.productId == p.id)) continue;
      final ProductColor c = p.colors[rnd.nextInt(p.colors.length)];
      out.add(
        ProductSaleLine(
          productId: p.id,
          productName: p.displayName,
          category: p.category,
          colorName: c.name,
          colorArgb: c.argb,
          units: 1 + rnd.nextInt(4),
        ),
      );
    }
    return out;
  }

  static int _strSeed(String s) =>
      s.codeUnits.fold<int>(17, (int h, int c) => (h * 31 + c) & 0x3FFFFFFF);

  static int _daySeed(String employeeId, DateTime d) =>
      (_strSeed(employeeId) ^ (d.year * 10000 + d.month * 100 + d.day)) &
      0x3FFFFFFF;

  // -------------------------------------------------------------------- admin

  static final AdminUser admin = AdminUser(
    id: 'adm_001',
    adminCode: 'ADM-001',
    name: 'Imran Kabir',
    role: 'Zonal Manager',
    email: 'imran.kabir@company.com',
    phone: '+880 1713 902 118',
    avatarUrl: '',
    region: 'Bardhaman North & South',
  );

  // ---------------------------------------------------------------- employees

  static const List<_Spec> _specs = <_Spec>[
    _Spec('emp_1043', 'EMP-1043', 'Nusrat Jahan', 'Sales Executive',
        'Field Sales — South Zone', 'Sripally', 23.2312, 87.8530),
    _Spec('emp_1044', 'EMP-1044', 'Tanvir Hasan', 'Senior Sales Executive',
        'Field Sales — North Zone', 'Nababhat', 23.2558, 87.8760),
    _Spec('emp_1045', 'EMP-1045', 'Mahfuz Alam', 'Territory Officer',
        'Field Sales — East Zone', 'Kanchan Nagar', 23.2440, 87.8720),
    _Spec('emp_1046', 'EMP-1046', 'Sadia Rahman', 'Key Account Manager',
        'Key Accounts', 'Golapbag', 23.2270, 87.8490),
    _Spec('emp_1047', 'EMP-1047', 'Arif Chowdhury', 'Sales Executive',
        'Field Sales — South Zone', 'Bara Bazar', 23.2358, 87.8648),
    _Spec('emp_1048', 'EMP-1048', 'Farhana Islam', 'Territory Officer',
        'Field Sales — East Zone', 'Bajepratappur', 23.2530, 87.8620),
    _Spec('emp_1049', 'EMP-1049', 'Rakib Hossain', 'Senior Sales Executive',
        'Key Accounts', 'Khosbagan', 23.2435, 87.8570),
  ];

  static final List<Employee> employees = <Employee>[
    MockData.employee,
    ..._specs.map(_toEmployee),
  ];

  static Employee _toEmployee(_Spec s) => Employee(
        id: s.id,
        employeeCode: s.code,
        name: s.name,
        designation: s.designation,
        department: s.department,
        email:
            '${s.name.toLowerCase().replaceAll(' ', '.')}@company.com',
        phone: '+880 17${(_strSeed(s.id) % 90 + 10)} ${_strSeed(s.id) % 900 + 100} '
            '${_strSeed(s.code) % 900 + 100}',
        avatarUrl: '',
        bannerUrl: '',
        joinedOn: DateTime(2021 + _strSeed(s.id) % 4, 1 + _strSeed(s.id) % 12,
            1 + _strSeed(s.code) % 27),
        reportingTo: '${admin.name} (${admin.role})',
        region: s.region,
        bloodGroup: const <String>['A+', 'B+', 'O+', 'AB+', 'O-'][
            _strSeed(s.id) % 5],
        address: '${s.region}, Bardhaman',
      );

  static int indexOf(String employeeId) =>
      employees.indexWhere((Employee e) => e.id == employeeId);

  static Employee? byId(String employeeId) {
    final int i = indexOf(employeeId);
    return i == -1 ? null : employees[i];
  }

  /// Home base each employee starts and ends the day from.
  static ({double lat, double lng}) homeAnchor(String employeeId) {
    final int i = indexOf(employeeId);
    if (i == 0) return (lat: 23.2440, lng: 87.8560);
    if (i > 0) return (lat: _specs[i - 1].homeLat, lng: _specs[i - 1].homeLng);
    // Employees created at runtime still need a stable anchor.
    final int seed = _strSeed(employeeId);
    return (
      lat: 23.215 + (seed % 60) / 1000,
      lng: 87.840 + (seed % 70) / 1000,
    );
  }

  // ------------------------------------------------------------ customer base

  /// The two-to-four companies this employee tends to call on, derived from
  /// their id alone. Overlap between employees is intentional — sellers work a
  /// loose territory, not a private list, and nobody assigns it to them.
  static List<String> _customerBase(String employeeId) {
    final Random rnd = Random(_strSeed(employeeId));
    final List<String> ids =
        MockData.companies.map((Company c) => c.id).toList()..shuffle(rnd);
    return ids.take(2 + rnd.nextInt(3)).toList()..sort();
  }

  // --------------------------------------------------------- behaviour tuning

  static _Archetype _archetype(String employeeId) {
    final int i = indexOf(employeeId);
    return switch ((i < 0 ? _strSeed(employeeId) : i) % 3) {
      0 => const _Archetype(absenceRate: 5, visitBase: 4, distanceBase: 34, joinHour: 8),
      1 => const _Archetype(absenceRate: 9, visitBase: 3, distanceBase: 27, joinHour: 9),
      _ => const _Archetype(absenceRate: 16, visitBase: 2, distanceBase: 19, joinHour: 9),
    };
  }

  /// Live state forced onto today so every dashboard branch is reachable in
  /// the demo: one offline, one GPS-lost, one long stop, one not started.
  static WorkStatus todayStatus(String employeeId) {
    return switch (indexOf(employeeId)) {
      0 => WorkStatus.working,
      1 => WorkStatus.working,
      2 => WorkStatus.idle,
      3 => WorkStatus.offline,
      4 => WorkStatus.locationUnavailable,
      5 => WorkStatus.ended,
      6 => WorkStatus.notStarted,
      _ => WorkStatus.idle,
    };
  }

  static LocationHealth todayHealth(String employeeId) =>
      switch (indexOf(employeeId)) {
        3 => LocationHealth.noInternet,
        4 => LocationHealth.permissionDenied,
        _ => LocationHealth.ok,
      };

  /// Dwell so far at the current stop — index 2 is deliberately over the
  /// 45-minute long-stop threshold so the dashboard alert always has a case.
  static Duration? openStopDuration(String employeeId) =>
      switch (indexOf(employeeId)) {
        2 => const Duration(minutes: 68),
        7 => const Duration(minutes: 22),
        _ => null,
      };

  // --------------------------------------------------------------- attendance

  static final Map<String, List<Attendance>> _attendance =
      <String, List<Attendance>>{};

  static List<Attendance> attendanceFor(String employeeId) {
    if (employeeId == MockData.employee.id) return MockData.attendanceHistory;
    return _attendance.putIfAbsent(
      employeeId,
      () => _buildAttendance(employeeId),
    );
  }

  static Attendance? attendanceOn(String employeeId, DateTime date) {
    final DateTime day = Fmt.dayOnly(date);
    for (final Attendance a in attendanceFor(employeeId)) {
      if (Fmt.isSameDay(a.date, day)) return a;
    }
    return null;
  }

  static List<Attendance> _buildAttendance(String employeeId) {
    final Random rnd = Random(_strSeed(employeeId));
    final _Archetype arc = _archetype(employeeId);
    final List<Attendance> out = <Attendance>[_todayAttendance(employeeId, arc)];

    for (int i = 1; i <= 120; i++) {
      final DateTime date = today.subtract(Duration(days: i));

      if (date.weekday == DateTime.friday) {
        out.add(_blank(date, AttendanceStatus.weekend));
        continue;
      }
      if (date.day == 21 && date.month % 2 == 0) {
        out.add(_blank(date, AttendanceStatus.holiday));
        continue;
      }

      final int roll = rnd.nextInt(100);
      if (roll < arc.absenceRate) {
        out.add(_blank(date, AttendanceStatus.absent));
        continue;
      }

      final bool partial = roll < arc.absenceRate + 10;
      final DateTime joining = DateTime(
        date.year,
        date.month,
        date.day,
        arc.joinHour + rnd.nextInt(2),
        rnd.nextInt(58),
      );
      final int workedMinutes =
          partial ? 170 + rnd.nextInt(100) : 430 + rnd.nextInt(160);
      final int stopMinutes = partial ? 30 + rnd.nextInt(60) : 55 + rnd.nextInt(120);
      final int visits = partial
          ? 1 + rnd.nextInt(2)
          : max(1, arc.visitBase - 1 + rnd.nextInt(3));
      final double distance = partial
          ? arc.distanceBase * 0.45 + rnd.nextInt(10) + rnd.nextDouble()
          : arc.distanceBase * 0.75 + rnd.nextInt(24) + rnd.nextDouble();

      out.add(
        Attendance(
          id: 'att_${employeeId}_${date.millisecondsSinceEpoch}',
          date: date,
          status: partial ? AttendanceStatus.partial : AttendanceStatus.present,
          joiningTime: joining,
          endTime: joining.add(Duration(minutes: workedMinutes)),
          sessionCount: partial ? 1 : 1 + rnd.nextInt(3),
          workedDuration: Duration(minutes: workedMinutes),
          distanceKm: double.parse(distance.toStringAsFixed(1)),
          companiesVisited: visits,
          reportsSubmitted: visits + (rnd.nextInt(100) < 35 ? 1 : 0),
          stopDuration: Duration(minutes: stopMinutes),
          longestStop: Duration(minutes: 20 + rnd.nextInt(45)),
        ),
      );
    }
    return out;
  }

  /// Today has to agree with the forced live status, otherwise the dashboard
  /// would show "Day Ended" against an open session.
  static Attendance _todayAttendance(String employeeId, _Archetype arc) {
    final WorkStatus status = todayStatus(employeeId);
    if (status == WorkStatus.notStarted) {
      return _blank(today, AttendanceStatus.absent);
    }

    final Random rnd = Random(_daySeed(employeeId, today));
    final DateTime joining = DateTime(
      today.year,
      today.month,
      today.day,
      arc.joinHour,
      rnd.nextInt(50),
    );
    final bool ended = status == WorkStatus.ended;
    final DateTime endTime =
        ended ? _now.subtract(Duration(minutes: 40 + rnd.nextInt(90))) : _now;
    final Duration worked = endTime.difference(joining);
    final int visits = max(1, arc.visitBase - 1 + rnd.nextInt(2));
    final int stopMinutes = 35 + rnd.nextInt(70);

    return Attendance(
      id: 'att_${employeeId}_today',
      date: today,
      status: AttendanceStatus.present,
      joiningTime: joining,
      endTime: ended ? endTime : null,
      sessionCount: 1 + rnd.nextInt(2),
      workedDuration: worked.isNegative ? const Duration(hours: 1) : worked,
      distanceKm: double.parse(
        (arc.distanceBase * 0.6 + rnd.nextInt(16) + rnd.nextDouble())
            .toStringAsFixed(1),
      ),
      companiesVisited: visits,
      reportsSubmitted: visits,
      stopDuration: Duration(minutes: stopMinutes),
      longestStop: openStopDuration(employeeId) ??
          Duration(minutes: 18 + rnd.nextInt(25)),
    );
  }

  static Attendance _blank(DateTime date, AttendanceStatus status) => Attendance(
        id: 'att_blank_${date.millisecondsSinceEpoch}',
        date: date,
        status: status,
        joiningTime: null,
        endTime: null,
        sessionCount: 0,
        workedDuration: Duration.zero,
        distanceKm: 0,
        companiesVisited: 0,
        reportsSubmitted: 0,
        stopDuration: Duration.zero,
        longestStop: Duration.zero,
      );

  // ----------------------------------------------------------------- day plan

  static final Map<String, EmployeeDay> _dayCache = <String, EmployeeDay>{};

  static String _key(String employeeId, DateTime date) =>
      '$employeeId|${date.year}${date.month}${date.day}';

  /// Drops every cached derivation for one employee — called after an edit so a
  /// newly created or renamed employee is rebuilt on the next read.
  static void invalidate(String employeeId) {
    _dayCache.removeWhere((String k, _) => k.startsWith('$employeeId|'));
    _routeCache.removeWhere((String k, _) => k.startsWith('$employeeId|'));
  }

  static EmployeeDay dayFor({
    required Employee employee,
    required DateTime date,
  }) {
    final DateTime day = Fmt.dayOnly(date);
    return _dayCache.putIfAbsent(
      _key(employee.id, day),
      () => _buildDay(employee, day),
    );
  }

  static EmployeeDay _buildDay(Employee employee, DateTime date) {
    final bool isToday = Fmt.isSameDay(date, today);

    // Employee 0 today: reuse the live employee-app fixtures verbatim.
    if (employee.id == MockData.employee.id && isToday) {
      return EmployeeDay(
        employee: employee,
        date: date,
        attendance: attendanceOn(employee.id, date),
        status: WorkStatus.working,
        movement: MovementStatus.moving,
        sessions: MockData.todaySessions,
        stops: MockData.todayStops,
        visits: MockData.todayVisits,
        reports: MockData.todayReports,
        activity: MockData.todayActivity(),
        summary: MockData.todaySummary,
        lastFix: MockData.lastFix,
      );
    }

    final Attendance? att = attendanceOn(employee.id, date);
    final WorkStatus status =
        isToday ? todayStatus(employee.id) : WorkStatus.ended;

    if (att == null ||
        att.status == AttendanceStatus.absent ||
        att.status == AttendanceStatus.weekend ||
        att.status == AttendanceStatus.holiday ||
        att.joiningTime == null) {
      return EmployeeDay(
        employee: employee,
        date: date,
        attendance: att,
        status: isToday ? status : WorkStatus.notStarted,
        movement: MovementStatus.unknown,
        sessions: const <WorkSession>[],
        stops: const <StopRecord>[],
        visits: const <CompanyVisit>[],
        reports: const <VisitReport>[],
        activity: const <ActivityEvent>[],
        summary: DaySummary.empty,
      );
    }

    final _Plan plan = _plan(employee.id, date, att);

    return EmployeeDay(
      employee: employee,
      date: date,
      attendance: att,
      status: status,
      movement: switch (status) {
        WorkStatus.working => MovementStatus.moving,
        WorkStatus.idle => MovementStatus.stationary,
        WorkStatus.ended || WorkStatus.notStarted => MovementStatus.unknown,
        _ => MovementStatus.unknown,
      },
      sessions: plan.sessions,
      stops: plan.stops,
      visits: plan.visits,
      reports: plan.reports,
      activity: plan.activity,
      summary: DaySummary(
        joiningTime: att.joiningTime,
        endTime: att.endTime,
        workedDuration: att.workedDuration,
        sessionCount: plan.sessions.length,
        distanceKm: att.distanceKm,
        companiesVisited: plan.visits.length,
        reportsSubmitted: plan.reports.length,
        stopDuration: att.stopDuration,
        longestStop: att.longestStop,
      ),
      lastFix: plan.lastFix,
    );
  }

  /// Turns one [Attendance] row into a concrete itinerary. Dwell times sum to
  /// `att.stopDuration` with the largest pinned to `att.longestStop`, and
  /// travel fills the rest of `att.workedDuration`, so the derived screens and
  /// the calendar can never disagree.
  static _Plan _plan(String employeeId, DateTime date, Attendance att) {
    final Random rnd = Random(_daySeed(employeeId, date));
    final List<_Target> targets =
        _targets(employeeId, rnd, att.companiesVisited);

    final int n = targets.length;
    final int sessionCount = min(att.sessionCount == 0 ? 1 : att.sessionCount, n);

    // Dwell split.
    final int stopTotal = max(att.stopDuration.inMinutes, n * 8);
    final int longest = min(att.longestStop.inMinutes, stopTotal);
    final List<int> dwells = <int>[longest];
    int remaining = stopTotal - longest;
    for (int i = 1; i < n; i++) {
      final int share = i == n - 1 ? remaining : (remaining / (n - i)).round();
      dwells.add(max(8, share));
      remaining -= share;
    }
    dwells.shuffle(rnd);

    // Travel split.
    final int travelTotal =
        max(att.workedDuration.inMinutes - stopTotal, n * 9);
    final List<int> legs = <int>[];
    int travelLeft = travelTotal;
    for (int i = 0; i < n; i++) {
      final int share =
          i == n - 1 ? travelLeft : (travelLeft / (n - i)).round();
      legs.add(max(6, share));
      travelLeft -= share;
    }

    final List<WorkSession> sessions = <WorkSession>[];
    final List<StopRecord> stops = <StopRecord>[];
    final List<CompanyVisit> visits = <CompanyVisit>[];
    final List<VisitReport> reports = <VisitReport>[];
    final List<ActivityEvent> activity = <ActivityEvent>[];

    final String dayTag =
        '${employeeId}_${date.year}${_two(date.month)}${_two(date.day)}';

    DateTime cursor = att.joiningTime!;
    DateTime sessionStart = cursor;
    int sessionIndex = 1;
    int sessionVisitCount = 0;
    double sessionKm = 0;
    final double perVisitKm = att.distanceKm / n;

    activity.add(
      ActivityEvent(
        id: 'act_${dayTag}_start',
        type: ActivityType.dayStarted,
        time: cursor,
        title: 'Day started',
        subtitle: 'Session 1 opened',
      ),
    );

    for (int i = 0; i < n; i++) {
      final _Target t = targets[i];
      final DateTime departFrom = cursor;
      final DateTime arrival = cursor.add(Duration(minutes: legs[i]));
      final DateTime departure = arrival.add(Duration(minutes: dwells[i]));
      cursor = departure;
      sessionVisitCount++;
      sessionKm += perVisitKm;

      final String visitId = 'vis_${dayTag}_$i';
      final String stopId = 'stp_${dayTag}_$i';
      final String sessionId = 'ses_${dayTag}_$sessionIndex';

      activity.add(
        ActivityEvent(
          id: 'act_${dayTag}_t$i',
          type: ActivityType.travelling,
          time: departFrom,
          endTime: arrival,
          title: 'Travelling',
          subtitle: 'to ${t.company.name}',
          duration: Duration(minutes: legs[i]),
        ),
      );
      activity.add(
        ActivityEvent(
          id: 'act_${dayTag}_a$i',
          type: ActivityType.arrived,
          time: arrival,
          title: 'Arrived at ${t.company.name}',
          companyName: t.company.name,
          branchName: t.branch.name,
        ),
      );
      activity.add(
        ActivityEvent(
          id: 'act_${dayTag}_s$i',
          type: ActivityType.stayed,
          time: arrival,
          endTime: departure,
          title: 'Stayed ${Fmt.duration(Duration(minutes: dwells[i]))}',
          companyName: t.company.name,
          branchName: t.branch.name,
          duration: Duration(minutes: dwells[i]),
          visitId: visitId,
          isAlert: dwells[i] >= 45,
        ),
      );

      stops.add(
        StopRecord(
          id: stopId,
          sessionId: sessionId,
          arrival: arrival,
          departure: departure,
          latitude: t.branch.latitude,
          longitude: t.branch.longitude,
          radiusMetres: 26 + rnd.nextInt(30).toDouble(),
          visitId: visitId,
        ),
      );

      final List<String> reportIds = <String>[];
      if (reports.length < att.reportsSubmitted) {
        final String reportId = 'rep_${dayTag}_$i';
        reportIds.add(reportId);
        final DateTime at = departure.subtract(const Duration(minutes: 4));
        final List<ProductSaleLine> sales =
            rnd.nextInt(100) < 60 ? _saleLines(rnd) : const <ProductSaleLine>[];
        reports.add(
          VisitReport(
            id: reportId,
            companyName: t.company.name,
            branchName: t.branch.name,
            companyId: t.company.id,
            branchId: t.branch.id,
            visitId: visitId,
            sessionId: sessionId,
            title: _titles[rnd.nextInt(_titles.length)],
            body: _bodies[rnd.nextInt(_bodies.length)],
            imageCount: rnd.nextInt(5),
            submittedAt: at,
            latitude: t.branch.latitude,
            longitude: t.branch.longitude,
            status: ReportStatus.submitted,
            dealValue: rnd.nextInt(100) < 45
                ? 'BDT ${(20 + rnd.nextInt(180)) * 1000}'
                : null,
            followUpOn: rnd.nextInt(100) < 30
                ? date.add(Duration(days: 3 + rnd.nextInt(14)))
                : null,
            sales: sales,
            paymentReceived: sales.isEmpty
                ? null
                : 'BDT ${(15 + rnd.nextInt(140)) * 1000}',
          ),
        );
        activity.add(
          ActivityEvent(
            id: 'act_${dayTag}_r$i',
            type: ActivityType.reportSubmitted,
            time: at,
            title: 'Report submitted',
            companyName: t.company.name,
            reportId: reportId,
            visitId: visitId,
          ),
        );
      }

      visits.add(
        CompanyVisit(
          id: visitId,
          sessionId: sessionId,
          companyId: t.company.id,
          branchId: t.branch.id,
          stopId: stopId,
          arrival: arrival,
          departure: departure,
          latitude: t.branch.latitude,
          longitude: t.branch.longitude,
          status: VisitStatus.completed,
          reportIds: reportIds,
          dealReference: null,
        ),
      );

      activity.add(
        ActivityEvent(
          id: 'act_${dayTag}_l$i',
          type: ActivityType.left,
          time: departure,
          title: 'Left ${t.company.name}',
          companyName: t.company.name,
        ),
      );

      // Close the session when this visit is the last one allotted to it.
      final int visitsPerSession = (n / sessionCount).ceil();
      final bool closeHere =
          sessionVisitCount >= visitsPerSession || i == n - 1;
      if (closeHere) {
        final bool last = i == n - 1;
        final DateTime end = departure.add(const Duration(minutes: 6));
        sessions.add(
          WorkSession(
            id: sessionId,
            index: sessionIndex,
            startTime: sessionStart,
            endTime: last && att.endTime == null ? null : end,
            distanceKm: double.parse(sessionKm.toStringAsFixed(1)),
            locationPoints: 60 + rnd.nextInt(240),
            startLatitude: homeAnchor(employeeId).lat,
            startLongitude: homeAnchor(employeeId).lng,
          ),
        );
        activity.add(
          ActivityEvent(
            id: 'act_${dayTag}_se$sessionIndex',
            type: ActivityType.sessionEnded,
            time: end,
            title: 'Session $sessionIndex ended',
          ),
        );
        if (!last) {
          sessionIndex++;
          sessionVisitCount = 0;
          sessionKm = 0;
          sessionStart = end.add(const Duration(minutes: 12));
          cursor = sessionStart;
          activity.add(
            ActivityEvent(
              id: 'act_${dayTag}_ss$sessionIndex',
              type: ActivityType.sessionStarted,
              time: sessionStart,
              title: 'Session $sessionIndex started',
            ),
          );
        }
      }
    }

    if (att.endTime != null) {
      activity.add(
        ActivityEvent(
          id: 'act_${dayTag}_end',
          type: ActivityType.dayEnded,
          time: att.endTime!,
          title: 'Day ended',
          subtitle: Fmt.duration(att.workedDuration),
        ),
      );
    }

    activity.sort((ActivityEvent a, ActivityEvent b) => a.time.compareTo(b.time));
    reports.sort(
      (VisitReport a, VisitReport b) => b.submittedAt.compareTo(a.submittedAt),
    );

    final _Target last = targets.last;
    return _Plan(
      sessions: sessions,
      stops: stops,
      visits: visits,
      reports: reports,
      activity: activity,
      lastFix: LocationLog(
        id: 'loc_${dayTag}_last',
        sessionId: sessions.isEmpty ? 'ses_${dayTag}_1' : sessions.last.id,
        latitude: last.branch.latitude,
        longitude: last.branch.longitude,
        accuracy: 8 + rnd.nextInt(16).toDouble(),
        speedKmh: 0,
        recordedAt: cursor,
        syncState: SyncState.synced,
      ),
    );
  }

  /// Which branches this employee calls on. Derived from their id via
  /// [_customerBase] — nobody assigns a territory, so there is no editable
  /// state to read here.
  static List<_Target> _targets(String employeeId, Random rnd, int wanted) {
    final List<_Target> pool = <_Target>[];
    for (final String id in _customerBase(employeeId)) {
      final Company c = MockData.companyById(id);
      for (final Branch b in c.branches) {
        pool.add(_Target(c, b));
      }
    }
    if (pool.isEmpty) {
      final Company c = MockData.companies.first;
      pool.add(_Target(c, c.branches.first));
    }
    pool.shuffle(rnd);

    final int n = max(1, wanted);
    return List<_Target>.generate(n, (int i) => pool[i % pool.length]);
  }

  // -------------------------------------------------------------------- route

  static final Map<String, RouteTrack> _routeCache = <String, RouteTrack>{};

  static RouteTrack routeFor({
    required Employee employee,
    required DateTime date,
  }) {
    final DateTime day = Fmt.dayOnly(date);
    return _routeCache.putIfAbsent(
      _key(employee.id, day),
      () => _buildRoute(employee, day),
    );
  }

  static RouteTrack _buildRoute(Employee employee, DateTime date) {
    final EmployeeDay day = dayFor(employee: employee, date: date);

    if (day.visits.isEmpty) {
      return RouteTrack(
        employeeId: employee.id,
        date: date,
        points: const <LocationLog>[],
        sessions: day.sessions,
        stops: day.stops,
        visits: day.visits,
        totalDistanceKm: 0,
      );
    }

    final double targetKm = day.summary.distanceKm;
    final int seed = _daySeed(employee.id, date);

    // Straight-line chain first, then bow the legs out until the arc length
    // matches the distance the attendance row claims. Without this the map
    // would quietly disagree with the calendar.
    double amp = 0;
    final double raw = _pathKm(_points(employee.id, day, seed, 0));
    if (targetKm > raw && raw > 0) {
      double lo = 0;
      double hi = 0.02;
      for (int i = 0; i < 7; i++) {
        final double mid = (lo + hi) / 2;
        if (_pathKm(_points(employee.id, day, seed, mid)) < targetKm) {
          lo = mid;
        } else {
          hi = mid;
        }
      }
      amp = (lo + hi) / 2;
    }

    final List<LocationLog> points = _points(employee.id, day, seed, amp);

    return RouteTrack(
      employeeId: employee.id,
      date: date,
      points: points,
      sessions: day.sessions,
      stops: day.stops,
      visits: day.visits,
      totalDistanceKm: double.parse(_pathKm(points).toStringAsFixed(1)),
    );
  }

  /// Builds the whole day's fixes. Deterministic for a given [seed] and [amp],
  /// so the bisection above compares like with like.
  static List<LocationLog> _points(
    String employeeId,
    EmployeeDay day,
    int seed,
    double amp,
  ) {
    final Random rnd = Random(seed);
    final ({double lat, double lng}) home = homeAnchor(employeeId);
    final List<LocationLog> out = <LocationLog>[];

    double fromLat = home.lat;
    double fromLng = home.lng;
    int id = 0;

    for (final CompanyVisit visit in day.visits) {
      final DateTime arrival = visit.arrival;
      final DateTime departure = visit.departure ?? arrival;
      final String sessionId = visit.sessionId;

      // Travel leg into this visit. One fix per ~2 minutes.
      final DateTime legStart =
          arrival.subtract(Duration(minutes: 8 + rnd.nextInt(14)));
      final int legMinutes = arrival.difference(legStart).inMinutes;
      final int steps = max(4, legMinutes ~/ 2);
      final double legKm =
          _haversineKm(fromLat, fromLng, visit.latitude, visit.longitude);
      final double speed =
          legMinutes == 0 ? 0 : legKm / (legMinutes / 60.0);

      // Unit normal of the leg, used to bow the line like a road.
      final double dLat = visit.latitude - fromLat;
      final double dLng = visit.longitude - fromLng;
      final double len = sqrt(dLat * dLat + dLng * dLng);
      final double nLat = len == 0 ? 0 : -dLng / len;
      final double nLng = len == 0 ? 0 : dLat / len;
      final double bow = amp * (rnd.nextBool() ? 1 : -1);

      for (int k = 0; k <= steps; k++) {
        final double t = k / steps;
        final double bend = bow * sin(pi * t);
        out.add(
          LocationLog(
            id: 'loc_${employeeId}_${id++}',
            sessionId: sessionId,
            latitude: fromLat + dLat * t + nLat * bend +
                (rnd.nextDouble() - 0.5) * 0.00035,
            longitude: fromLng + dLng * t + nLng * bend +
                (rnd.nextDouble() - 0.5) * 0.00035,
            accuracy: 6 + rnd.nextDouble() * 18,
            speedKmh: speed * (0.85 + rnd.nextDouble() * 0.3),
            recordedAt: legStart.add(
              Duration(seconds: (legMinutes * 60 * t).round()),
            ),
            syncState:
                rnd.nextInt(100) < 6 ? SyncState.queued : SyncState.synced,
          ),
        );
      }

      // Dwell cluster — a stop should read as a dense blob, not a single dot.
      final int dwellMinutes = departure.difference(arrival).inMinutes;
      final int cluster = (dwellMinutes ~/ 6).clamp(3, 12);
      for (int k = 0; k < cluster; k++) {
        out.add(
          LocationLog(
            id: 'loc_${employeeId}_${id++}',
            sessionId: sessionId,
            latitude: visit.latitude + (rnd.nextDouble() - 0.5) * 0.00045,
            longitude: visit.longitude + (rnd.nextDouble() - 0.5) * 0.00045,
            accuracy: 5 + rnd.nextDouble() * 12,
            speedKmh: rnd.nextDouble() * 1.4,
            recordedAt: arrival.add(
              Duration(minutes: (dwellMinutes * k / cluster).round()),
            ),
            syncState: SyncState.synced,
          ),
        );
      }

      fromLat = visit.latitude;
      fromLng = visit.longitude;
    }

    return out;
  }

  static double _pathKm(List<LocationLog> pts) {
    double sum = 0;
    for (int i = 1; i < pts.length; i++) {
      if (pts[i].sessionId != pts[i - 1].sessionId) continue;
      sum += _haversineKm(
        pts[i - 1].latitude,
        pts[i - 1].longitude,
        pts[i].latitude,
        pts[i].longitude,
      );
    }
    return sum;
  }

  static double _haversineKm(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    const double r = 6371.0088;
    final double dLat = (lat2 - lat1) * pi / 180;
    final double dLng = (lng2 - lng1) * pi / 180;
    final double a = sin(dLat / 2) * sin(dLat / 2) +
        cos(lat1 * pi / 180) *
            cos(lat2 * pi / 180) *
            sin(dLng / 2) *
            sin(dLng / 2);
    return 2 * r * atan2(sqrt(a), sqrt(1 - a));
  }

  static String _two(int v) => v.toString().padLeft(2, '0');

  // --------------------------------------------------------------- copy pools

  static const List<String> _titles = <String>[
    'Monthly stock review & reorder',
    'Payment collection visit',
    'New product line placement',
    'Complaint resolution — damaged goods',
    'Quarterly contract discussion',
    'Competitor activity survey',
    'Display & merchandising audit',
    'Route expansion opportunity',
  ];

  static const List<String> _bodies = <String>[
    'Reviewed current shelf stock and placed a reorder for the fast-moving '
        'SKUs. Owner is satisfied with the delivery turnaround this month.',
    'Collected the outstanding invoice against last month\'s delivery. Receipt '
        'photographed and attached. Remaining balance clears next cycle.',
    'Introduced the new seasonal range. Agreed on a trial placement of two '
        'SKUs for a fortnight, subject to sell-through.',
    'Documented the damaged consignment with batch codes and forwarded the '
        'claim to the warehouse team for replacement.',
    'Discussed renewal terms and volume rebates. Pricing escalated '
        'internally; follow-up scheduled after approval.',
  ];
}

// ---------------------------------------------------------------- private DTOs

class _Spec {
  const _Spec(
    this.id,
    this.code,
    this.name,
    this.designation,
    this.department,
    this.region,
    this.homeLat,
    this.homeLng,
  );

  final String id;
  final String code;
  final String name;
  final String designation;
  final String department;
  final String region;
  final double homeLat;
  final double homeLng;
}

class _Archetype {
  const _Archetype({
    required this.absenceRate,
    required this.visitBase,
    required this.distanceBase,
    required this.joinHour,
  });

  final int absenceRate;
  final int visitBase;
  final double distanceBase;
  final int joinHour;
}

class _Target {
  const _Target(this.company, this.branch);

  final Company company;
  final Branch branch;
}

class _Plan {
  const _Plan({
    required this.sessions,
    required this.stops,
    required this.visits,
    required this.reports,
    required this.activity,
    required this.lastFix,
  });

  final List<WorkSession> sessions;
  final List<StopRecord> stops;
  final List<CompanyVisit> visits;
  final List<VisitReport> reports;
  final List<ActivityEvent> activity;
  final LocationLog lastFix;
}
