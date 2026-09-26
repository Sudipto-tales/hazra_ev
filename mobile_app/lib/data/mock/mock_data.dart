import 'dart:math';

import '../../core/utils/formatters.dart';
import '../models/models.dart';
import 'product_catalog.dart';

/// Static demo dataset.
///
/// Everything is anchored to `DateTime.now()` so the screens stay coherent
/// whenever the app is opened. This file is the ONLY place fake data lives —
/// deleting it and swapping [MockEmployeeRepository] for an HTTP repository is
/// the whole "go live" change.
class MockData {
  MockData._();

  static final DateTime _now = DateTime.now();
  static final DateTime today = Fmt.dayOnly(_now);

  static DateTime _ago({int h = 0, int m = 0}) =>
      _now.subtract(Duration(hours: h, minutes: m));

  // ---------------------------------------------------------------- employee

  static final Employee employee = Employee(
    id: 'emp_1042',
    employeeCode: 'EMP-1042',
    name: 'Rahul Ahmed',
    designation: 'Senior Sales Executive',
    department: 'Field Sales — North Zone',
    email: 'rahul.ahmed@company.com',
    phone: '+880 1712 445 908',
    avatarUrl: '',
    bannerUrl: '',
    joinedOn: DateTime(2022, 3, 14),
    reportingTo: 'Imran Kabir (Zonal Manager)',
    region: 'Bardhaman North',
    bloodGroup: 'B+',
    address: '14 Tilak Road, Khosbagan, Bardhaman 713101',
  );

  // --------------------------------------------------------------- companies

  static const List<Company> companies = <Company>[
    Company(
      id: 'co_abc',
      name: 'ABC Trading Ltd.',
      category: 'Distributor',
      branches: <Branch>[
        Branch(
          id: 'br_abc_hq',
          name: 'Khosbagan Head Office',
          address: '38 Tilak Road, Khosbagan, Bardhaman 713101',
          latitude: 23.2432,
          longitude: 87.8567,
        ),
        Branch(
          id: 'br_abc_curzon',
          name: 'Curzon Gate Outlet',
          address: 'G.T. Road, Curzon Gate, Bardhaman 713101',
          latitude: 23.2370,
          longitude: 87.8635,
        ),
      ],
    ),
    Company(
      id: 'co_xyz',
      name: 'XYZ Pharmaceuticals',
      category: 'Corporate',
      branches: <Branch>[
        Branch(
          id: 'br_xyz_bcroad',
          name: 'B.C. Road Branch',
          address: 'Birhata, B.C. Road, Bardhaman 713101',
          latitude: 23.2350,
          longitude: 87.8640,
        ),
      ],
    ),
    Company(
      id: 'co_nova',
      name: 'Nova Retail House',
      category: 'Retail',
      branches: <Branch>[
        Branch(
          id: 'br_nova_golapbag',
          name: 'Golapbag Store',
          address: 'Golapbag More, Bardhaman 713104',
          latitude: 23.2262,
          longitude: 87.8482,
        ),
      ],
    ),
    Company(
      id: 'co_orion',
      name: 'Orion Electronics',
      category: 'Corporate',
      branches: <Branch>[
        Branch(
          id: 'br_orion_nababhat',
          name: 'Nababhat Showroom',
          address: 'Nababhat, G.T. Road, Bardhaman 713102',
          latitude: 23.2565,
          longitude: 87.8772,
        ),
      ],
    ),
    Company(
      id: 'co_meridian',
      name: 'Meridian Foods',
      category: 'Distributor',
      branches: <Branch>[
        Branch(
          id: 'br_meridian_ullas',
          name: 'Ullas Depot',
          address: 'Ullas More, Alisha, Bardhaman 713103',
          latitude: 23.2273,
          longitude: 87.8935,
        ),
      ],
    ),
  ];

  static Company companyById(String id) =>
      companies.firstWhere((Company c) => c.id == id, orElse: () => companies.first);

  static String companyName(String id) => companyById(id).name;

  static String? branchName(String companyId, String? branchId) =>
      companyById(companyId).branchById(branchId)?.name;

  // ---------------------------------------------------------- today: sessions

  static final DateTime _s1Start = _ago(h: 5, m: 48);
  static final DateTime _s1End = _ago(h: 2, m: 43);
  static final DateTime _s2Start = _ago(h: 1, m: 52);

  static final List<WorkSession> todaySessions = <WorkSession>[
    WorkSession(
      id: 'ses_1',
      index: 1,
      startTime: _s1Start,
      endTime: _s1End,
      distanceKm: 27.4,
      locationPoints: 372,
      startLatitude: 23.2440,
      startLongitude: 87.8560,
    ),
    WorkSession(
      id: 'ses_2',
      index: 2,
      startTime: _s2Start,
      endTime: null,
      distanceKm: 15.2,
      locationPoints: 214,
      startLatitude: 23.2262,
      startLongitude: 87.8482,
    ),
  ];

  // ------------------------------------------------------------- today: stops

  static final List<StopRecord> todayStops = <StopRecord>[
    StopRecord(
      id: 'stp_1',
      sessionId: 'ses_1',
      arrival: _ago(h: 4, m: 55),
      departure: _ago(h: 4, m: 11),
      latitude: 23.2432,
      longitude: 87.8567,
      radiusMetres: 38,
      visitId: 'vis_1',
    ),
    StopRecord(
      id: 'stp_2',
      sessionId: 'ses_1',
      arrival: _ago(h: 3, m: 20),
      departure: _ago(h: 2, m: 52),
      latitude: 23.2350,
      longitude: 87.8640,
      radiusMetres: 44,
      visitId: 'vis_2',
    ),
    StopRecord(
      id: 'stp_3',
      sessionId: 'ses_2',
      arrival: _ago(h: 1, m: 5),
      departure: _ago(m: 26),
      latitude: 23.2262,
      longitude: 87.8482,
      radiusMetres: 29,
      visitId: 'vis_3',
    ),
  ];

  // ------------------------------------------------------------ today: visits

  static final List<CompanyVisit> todayVisits = <CompanyVisit>[
    CompanyVisit(
      id: 'vis_1',
      sessionId: 'ses_1',
      companyId: 'co_abc',
      branchId: 'br_abc_hq',
      stopId: 'stp_1',
      arrival: _ago(h: 4, m: 55),
      departure: _ago(h: 4, m: 11),
      latitude: 23.2432,
      longitude: 87.8567,
      status: VisitStatus.completed,
      reportIds: <String>['rep_1'],
      dealReference: 'ORD-88413',
    ),
    CompanyVisit(
      id: 'vis_2',
      sessionId: 'ses_1',
      companyId: 'co_xyz',
      branchId: 'br_xyz_bcroad',
      stopId: 'stp_2',
      arrival: _ago(h: 3, m: 20),
      departure: _ago(h: 2, m: 52),
      latitude: 23.2350,
      longitude: 87.8640,
      status: VisitStatus.completed,
      reportIds: <String>['rep_2'],
      dealReference: null,
    ),
    CompanyVisit(
      id: 'vis_3',
      sessionId: 'ses_2',
      companyId: 'co_nova',
      branchId: 'br_nova_golapbag',
      stopId: 'stp_3',
      arrival: _ago(h: 1, m: 5),
      departure: _ago(m: 26),
      latitude: 23.2262,
      longitude: 87.8482,
      status: VisitStatus.completed,
      reportIds: <String>['rep_3'],
      dealReference: 'ORD-88419',
    ),
  ];

  // ----------------------------------------------------------- today: reports

  /// Note `rep_4` — a second report for ABC Trading on the same day. Multiple
  /// reports per company is a hard requirement, never merge them.
  static final List<VisitReport> todayReports = <VisitReport>[
    VisitReport(
      id: 'rep_1',
      companyName: 'ABC Trading Ltd.',
      branchName: 'Khosbagan Head Office',
      companyId: 'co_abc',
      branchId: 'br_abc_hq',
      visitId: 'vis_1',
      sessionId: 'ses_1',
      title: 'Monthly stock review & reorder',
      body:
          'Met the procurement lead. Reviewed shelf movement for the last 30 days — '
          'premium SKUs moved 18% faster than the previous cycle. Reorder placed for '
          '240 cartons of the 500ml line and 90 cartons of the family pack. '
          'They asked for the revised trade discount sheet before the next visit. '
          'Display units at the entrance need replacement, photos attached.',
      imageCount: 3,
      submittedAt: _ago(h: 4, m: 13),
      latitude: 23.2432,
      longitude: 87.8567,
      status: ReportStatus.submitted,
      dealValue: 'BDT 1,84,000',
      followUpOn: today.add(const Duration(days: 14)),
      sales: const <ProductSaleLine>[
        ProductSaleLine(
          productId: 'prd_chalo_1000_v2',
          productName: 'Chalo 1000 V2',
          category: ProductCategory.scooty,
          colorName: 'Sunset Orange',
          colorArgb: 0xFFF97316,
          units: 2,
        ),
        ProductSaleLine(
          productId: 'prd_vida_v1_pro',
          productName: 'Hero Vida V1 Pro',
          category: ProductCategory.scooty,
          colorName: 'Matte Black',
          colorArgb: 0xFF1E293B,
          units: 1,
        ),
      ],
      paymentReceived: 'BDT 1,20,000',
    ),
    VisitReport(
      id: 'rep_2',
      companyName: 'XYZ Pharmaceuticals',
      branchName: 'B.C. Road Branch',
      companyId: 'co_xyz',
      branchId: 'br_xyz_bcroad',
      visitId: 'vis_2',
      sessionId: 'ses_1',
      title: 'Quarterly contract discussion',
      body:
          'Discussed renewal terms for the Q4 supply contract. Branch manager wants a '
          '5% volume rebate above 1,000 units per month. Escalated to zonal manager. '
          'No order placed today — follow-up scheduled once pricing is approved.',
      imageCount: 1,
      submittedAt: _ago(h: 3, m: 5),
      latitude: 23.2350,
      longitude: 87.8640,
      status: ReportStatus.submitted,
      dealValue: null,
      followUpOn: today.add(const Duration(days: 5)),
    ),
    VisitReport(
      id: 'rep_3',
      companyName: 'Nova Retail House',
      branchName: 'Golapbag Store',
      companyId: 'co_nova',
      branchId: 'br_nova_golapbag',
      visitId: 'vis_3',
      sessionId: 'ses_2',
      title: 'New product line placement',
      body:
          'Placed the new seasonal line on two end-caps. Store manager agreed to a '
          'two-week trial. Collected outstanding payment receipt for last month. '
          'Competitor has an aggressive promo running on the adjacent shelf.',
      imageCount: 4,
      submittedAt: _ago(m: 35),
      latitude: 23.2262,
      longitude: 87.8482,
      status: ReportStatus.submitted,
      dealValue: 'BDT 62,500',
      followUpOn: null,
      sales: const <ProductSaleLine>[
        ProductSaleLine(
          productId: 'prd_lectro_c5',
          productName: 'Hero Lectro C5',
          category: ProductCategory.bicycle,
          colorName: 'Sky Blue',
          colorArgb: 0xFF7DD3FC,
          units: 4,
        ),
      ],
      paymentReceived: 'BDT 62,500',
    ),
    VisitReport(
      id: 'rep_4',
      companyName: 'ABC Trading Ltd.',
      branchName: 'Curzon Gate Outlet',
      companyId: 'co_abc',
      branchId: 'br_abc_curzon',
      visitId: null,
      sessionId: 'ses_2',
      title: 'Follow-up: damaged carton claim',
      body:
          'Second report for ABC today. The Curzon Gate outlet raised a claim for 12 '
          'damaged cartons from last week\'s delivery. Photographed the batch codes '
          'and forwarded to the warehouse team. Replacement expected within 48 hours.',
      imageCount: 2,
      submittedAt: _ago(m: 9),
      latitude: 23.2370,
      longitude: 87.8635,
      status: ReportStatus.queued,
      dealValue: null,
      followUpOn: today.add(const Duration(days: 2)),
    ),
    // A walk-in: typed company, no id, no visit, not on any master list. This
    // is the case free-text company/branch exists for.
    VisitReport(
      id: 'rep_5',
      companyName: 'Mohakhali EV Point',
      branchName: 'Wireless Gate',
      companyId: null,
      branchId: null,
      visitId: null,
      sessionId: 'ses_2',
      title: 'Walk-in dealer — first order',
      body:
          'Unlisted shop opposite the Wireless Gate bus stand. Owner runs two '
          'outlets and wants to start with high-speed scooters. Took an order for '
          'three units against a part payment; the rest is on delivery. Added his '
          'trade licence photo for onboarding.',
      imageCount: 2,
      submittedAt: _ago(m: 52),
      latitude: 23.2440,
      longitude: 87.8560,
      status: ReportStatus.submitted,
      dealValue: 'BDT 2,55,000',
      followUpOn: today.add(const Duration(days: 3)),
      sales: const <ProductSaleLine>[
        ProductSaleLine(
          productId: 'prd_chalo_1000_v2',
          productName: 'Chalo 1000 V2',
          category: ProductCategory.scooty,
          colorName: 'Pearl White',
          colorArgb: 0xFFF8FAFC,
          units: 3,
        ),
      ],
      paymentReceived: 'BDT 90,000',
    ),
  ];

  // ------------------------------------------------------------ today: extras

  static final SyncSnapshot syncSnapshot = SyncSnapshot(
    queued: 6,
    failed: 0,
    lastSyncedAt: _ago(m: 2),
    isOnline: true,
  );

  static final LocationLog lastFix = LocationLog(
    id: 'loc_last',
    sessionId: 'ses_2',
    latitude: 23.2405,
    longitude: 87.8600,
    accuracy: 11.4,
    speedKmh: 24.6,
    recordedAt: _ago(m: 1),
    syncState: SyncState.synced,
  );

  static final DaySummary todaySummary = DaySummary(
    joiningTime: _s1Start,
    endTime: null,
    workedDuration: _s1End.difference(_s1Start) + _now.difference(_s2Start),
    sessionCount: 2,
    distanceKm: 42.6,
    companiesVisited: 3,
    reportsSubmitted: 4,
    stopDuration: const Duration(hours: 2, minutes: 11),
    longestStop: const Duration(minutes: 44),
  );

  /// Chronological timeline rendered on Home.
  static List<ActivityEvent> todayActivity() {
    final List<ActivityEvent> events = <ActivityEvent>[
      ActivityEvent(
        id: 'ev_1',
        type: ActivityType.dayStarted,
        time: _s1Start,
        title: 'Started work',
        subtitle: 'Session 1 · joining time recorded',
      ),
      ActivityEvent(
        id: 'ev_2',
        type: ActivityType.travelling,
        time: _s1Start.add(const Duration(minutes: 4)),
        title: 'Travelling',
        subtitle: '9.2 km toward Nababhat',
        duration: const Duration(minutes: 49),
      ),
      ActivityEvent(
        id: 'ev_3',
        type: ActivityType.arrived,
        time: _ago(h: 4, m: 55),
        title: 'Reached ABC Trading Ltd.',
        companyName: 'ABC Trading Ltd.',
        branchName: 'Khosbagan Head Office',
        visitId: 'vis_1',
      ),
      ActivityEvent(
        id: 'ev_4',
        type: ActivityType.stayed,
        time: _ago(h: 4, m: 55),
        endTime: _ago(h: 4, m: 11),
        title: 'Stayed 44 minutes',
        companyName: 'ABC Trading Ltd.',
        duration: const Duration(minutes: 44),
        visitId: 'vis_1',
      ),
      ActivityEvent(
        id: 'ev_5',
        type: ActivityType.reportSubmitted,
        time: _ago(h: 4, m: 13),
        title: 'Report submitted',
        subtitle: 'Monthly stock review & reorder · 3 images',
        companyName: 'ABC Trading Ltd.',
        reportId: 'rep_1',
        visitId: 'vis_1',
      ),
      ActivityEvent(
        id: 'ev_6',
        type: ActivityType.left,
        time: _ago(h: 4, m: 11),
        title: 'Left ABC Trading Ltd.',
        companyName: 'ABC Trading Ltd.',
        visitId: 'vis_1',
      ),
      ActivityEvent(
        id: 'ev_7',
        type: ActivityType.trackingIssue,
        time: _ago(h: 3, m: 58),
        title: 'Weak GPS signal',
        subtitle: 'Accuracy above 50 m for 6 minutes · points discarded',
        isAlert: true,
      ),
      ActivityEvent(
        id: 'ev_8',
        type: ActivityType.arrived,
        time: _ago(h: 3, m: 20),
        title: 'Reached XYZ Pharmaceuticals',
        companyName: 'XYZ Pharmaceuticals',
        branchName: 'B.C. Road Branch',
        visitId: 'vis_2',
      ),
      ActivityEvent(
        id: 'ev_9',
        type: ActivityType.stayed,
        time: _ago(h: 3, m: 20),
        endTime: _ago(h: 2, m: 52),
        title: 'Stayed 28 minutes',
        companyName: 'XYZ Pharmaceuticals',
        duration: const Duration(minutes: 28),
        visitId: 'vis_2',
      ),
      ActivityEvent(
        id: 'ev_10',
        type: ActivityType.reportSubmitted,
        time: _ago(h: 3, m: 5),
        title: 'Report submitted',
        subtitle: 'Quarterly contract discussion · 1 image',
        companyName: 'XYZ Pharmaceuticals',
        reportId: 'rep_2',
        visitId: 'vis_2',
      ),
      ActivityEvent(
        id: 'ev_11',
        type: ActivityType.left,
        time: _ago(h: 2, m: 52),
        title: 'Left XYZ Pharmaceuticals',
        companyName: 'XYZ Pharmaceuticals',
        visitId: 'vis_2',
      ),
      ActivityEvent(
        id: 'ev_12',
        type: ActivityType.sessionEnded,
        time: _s1End,
        title: 'Session 1 ended',
        subtitle: 'Break · 27.4 km travelled',
      ),
      ActivityEvent(
        id: 'ev_13',
        type: ActivityType.sessionStarted,
        time: _s2Start,
        title: 'Session 2 started',
        subtitle: 'Tracking resumed',
      ),
      ActivityEvent(
        id: 'ev_14',
        type: ActivityType.arrived,
        time: _ago(h: 1, m: 5),
        title: 'Reached Nova Retail House',
        companyName: 'Nova Retail House',
        branchName: 'Golapbag Store',
        visitId: 'vis_3',
      ),
      ActivityEvent(
        id: 'ev_15',
        type: ActivityType.stayed,
        time: _ago(h: 1, m: 5),
        endTime: _ago(m: 26),
        title: 'Stayed 39 minutes',
        companyName: 'Nova Retail House',
        duration: const Duration(minutes: 39),
        visitId: 'vis_3',
      ),
      ActivityEvent(
        id: 'ev_16',
        type: ActivityType.reportSubmitted,
        time: _ago(m: 35),
        title: 'Report submitted',
        subtitle: 'New product line placement · 4 images',
        companyName: 'Nova Retail House',
        reportId: 'rep_3',
        visitId: 'vis_3',
      ),
      ActivityEvent(
        id: 'ev_17',
        type: ActivityType.left,
        time: _ago(m: 26),
        title: 'Left Nova Retail House',
        companyName: 'Nova Retail House',
        visitId: 'vis_3',
      ),
      ActivityEvent(
        id: 'ev_18',
        type: ActivityType.reportSubmitted,
        time: _ago(m: 9),
        title: 'Report queued',
        subtitle: 'Follow-up: damaged carton claim · waiting for network',
        companyName: 'ABC Trading Ltd.',
        reportId: 'rep_4',
      ),
      ActivityEvent(
        id: 'ev_19',
        type: ActivityType.travelling,
        time: _ago(m: 26),
        title: 'Travelling',
        subtitle: 'On the move · last fix ${Fmt.time(lastFix.recordedAt)}',
      ),
    ];
    events.sort((ActivityEvent a, ActivityEvent b) => b.time.compareTo(a.time));
    return events;
  }

  // ------------------------------------------------------------- history: 120d

  static final List<Attendance> _history = _buildHistory();

  static List<Attendance> get attendanceHistory => _history;

  static List<Attendance> _buildHistory() {
    final Random rnd = Random(20260816);
    final List<Attendance> out = <Attendance>[];

    // Today, built from the live mock summary.
    out.add(
      Attendance(
        id: 'att_today',
        date: today,
        status: AttendanceStatus.present,
        joiningTime: todaySummary.joiningTime,
        endTime: null,
        sessionCount: todaySummary.sessionCount,
        workedDuration: todaySummary.workedDuration,
        distanceKm: todaySummary.distanceKm,
        companiesVisited: todaySummary.companiesVisited,
        reportsSubmitted: todaySummary.reportsSubmitted,
        stopDuration: todaySummary.stopDuration,
        longestStop: todaySummary.longestStop,
      ),
    );

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
      if (roll < 7) {
        out.add(_blank(date, AttendanceStatus.absent));
        continue;
      }

      final bool partial = roll < 17;
      final int joinHour = 8 + rnd.nextInt(2);
      final int joinMinute = rnd.nextInt(58);
      final DateTime joining =
          DateTime(date.year, date.month, date.day, joinHour, joinMinute);
      final int workedMinutes =
          partial ? 180 + rnd.nextInt(90) : 450 + rnd.nextInt(150);
      final int sessions = partial ? 1 : 1 + rnd.nextInt(3);
      final double distance = partial
          ? 12 + rnd.nextInt(18) + rnd.nextDouble()
          : 24 + rnd.nextInt(46) + rnd.nextDouble();
      final int visits = partial ? 1 + rnd.nextInt(2) : 2 + rnd.nextInt(5);
      final int reports = visits + (rnd.nextInt(100) < 35 ? 1 : 0);
      final int stopMinutes = 40 + rnd.nextInt(140);

      out.add(
        Attendance(
          id: 'att_${date.millisecondsSinceEpoch}',
          date: date,
          status: partial ? AttendanceStatus.partial : AttendanceStatus.present,
          joiningTime: joining,
          endTime: joining.add(Duration(minutes: workedMinutes + stopMinutes ~/ 2)),
          sessionCount: sessions,
          workedDuration: Duration(minutes: workedMinutes),
          distanceKm: double.parse(distance.toStringAsFixed(1)),
          companiesVisited: visits,
          reportsSubmitted: reports,
          stopDuration: Duration(minutes: stopMinutes),
          longestStop: Duration(minutes: 25 + rnd.nextInt(50)),
        ),
      );
    }
    return out;
  }

  static Attendance _blank(DateTime date, AttendanceStatus status) => Attendance(
        id: 'att_${date.millisecondsSinceEpoch}',
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

  // -------------------------------------------------------- history: reports

  static final List<VisitReport> _historyReports = _buildHistoryReports();

  static List<VisitReport> get allReports =>
      <VisitReport>[...todayReports, ..._historyReports];

  /// One or two catalogue lines, colour picked from that product's own set.
  static List<ProductSaleLine> _saleLines(Random rnd) {
    final int lines = rnd.nextInt(100) < 30 ? 2 : 1;
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

  static List<VisitReport> _buildHistoryReports() {
    final Random rnd = Random(4711);
    final List<VisitReport> out = <VisitReport>[];
    const List<String> titles = <String>[
      'Monthly stock review & reorder',
      'Payment collection visit',
      'New product line placement',
      'Complaint resolution — damaged goods',
      'Quarterly contract discussion',
      'Competitor activity survey',
      'Display & merchandising audit',
      'Route expansion opportunity',
    ];
    const List<String> bodies = <String>[
      'Reviewed current shelf stock and placed a reorder for the fast-moving SKUs. '
          'Owner is satisfied with the delivery turnaround this month.',
      'Collected the outstanding invoice against last month\'s delivery. Receipt '
          'photographed and attached. Remaining balance to be cleared next cycle.',
      'Introduced the new seasonal range. Agreed on a trial placement of two SKUs '
          'for a fortnight, subject to sell-through.',
      'Documented the damaged consignment with batch codes and forwarded the claim '
          'to the warehouse team for replacement.',
      'Discussed renewal terms and volume rebates. Pricing escalated internally; '
          'follow-up scheduled after approval.',
    ];

    for (int day = 1; day <= 45; day++) {
      final DateTime date = today.subtract(Duration(days: day));
      if (date.weekday == DateTime.friday) continue;
      final int count = rnd.nextInt(4);
      for (int i = 0; i < count; i++) {
        final Company company = companies[rnd.nextInt(companies.length)];
        final Branch branch = company.branches[rnd.nextInt(company.branches.length)];
        final DateTime at = DateTime(
          date.year,
          date.month,
          date.day,
          10 + rnd.nextInt(7),
          rnd.nextInt(59),
        );
        // Roughly half of the historical visits closed a sale.
        final List<ProductSaleLine> sales =
            rnd.nextInt(100) < 55 ? _saleLines(rnd) : const <ProductSaleLine>[];
        out.add(
          VisitReport(
            id: 'rep_h_${day}_$i',
            companyName: company.name,
            branchName: branch.name,
            companyId: company.id,
            branchId: branch.id,
            visitId: 'vis_h_${day}_$i',
            sessionId: 'ses_h_$day',
            title: titles[rnd.nextInt(titles.length)],
            body: bodies[rnd.nextInt(bodies.length)],
            imageCount: rnd.nextInt(5),
            submittedAt: at,
            latitude: branch.latitude,
            longitude: branch.longitude,
            status: rnd.nextInt(100) < 25
                ? ReportStatus.reviewed
                : ReportStatus.submitted,
            dealValue: rnd.nextInt(100) < 40
                ? 'BDT ${(20 + rnd.nextInt(180)) * 1000}'
                : null,
            followUpOn: null,
            sales: sales,
            paymentReceived: sales.isEmpty
                ? null
                : 'BDT ${(15 + rnd.nextInt(120)) * 1000}',
          ),
        );
      }
    }
    out.sort((VisitReport a, VisitReport b) => b.submittedAt.compareTo(a.submittedAt));
    return out;
  }
}
