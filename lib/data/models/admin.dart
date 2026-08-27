import 'dart:math' as math;

import 'activity_event.dart';
import 'day_closeout.dart';
import 'employee.dart';
import 'report.dart';
import 'statistics.dart';
import 'tracking.dart';
import 'visit.dart';

/// Admin-side models.
///
/// Deliberately additive: the employee models carry no owner field, so
/// ownership lives in the admin repository (a map keyed by employee id) rather
/// than being retrofitted onto [Attendance], [WorkSession], [VisitReport] and
/// friends. When the real backend lands, `/api/admin/employees/{id}/...`
/// supplies exactly these shapes.

/// Whoever is signed into the admin console.
class AdminUser {
  const AdminUser({
    required this.id,
    required this.adminCode,
    required this.name,
    required this.role,
    required this.email,
    required this.phone,
    required this.avatarUrl,
    required this.region,
  });

  final String id;

  /// Human-facing ID shown next to the name (`ADM-001`).
  final String adminCode;
  final String name;

  /// Job title — "Zonal Manager", "Operations Admin".
  final String role;
  final String email;
  final String phone;
  final String avatarUrl;
  final String region;

  String get initials {
    final List<String> parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((String p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }
}

/// One row of the live team roster: an employee plus today's tracking state.
class TeamMember {
  const TeamMember({
    required this.employee,
    required this.status,
    required this.movement,
    required this.locationHealth,
    required this.summary,
    required this.attendanceStatus,
    required this.active,
    this.lastFix,
    this.activeSince,
    this.openStopDuration,
    this.openStopCompanyId,
  });

  final Employee employee;
  final WorkStatus status;
  final MovementStatus movement;
  final LocationHealth locationHealth;
  final DaySummary summary;
  final AttendanceStatus attendanceStatus;

  /// Deactivated employees stay in the roster but are excluded from alerts.
  final bool active;

  final LocationLog? lastFix;

  /// Start of the currently open session, null when no session is open.
  final DateTime? activeSince;

  /// How long the employee has been parked at the current stop.
  final Duration? openStopDuration;
  final String? openStopCompanyId;

  bool isLongStop(int thresholdMinutes) =>
      active &&
      openStopDuration != null &&
      openStopDuration!.inMinutes >= thresholdMinutes;

  bool get isDegraded => active && status.isDegraded;

  bool get isWorking => status == WorkStatus.working;
}

/// Dashboard payload — `GET /api/admin/overview`.
class TeamOverview {
  const TeamOverview({
    required this.date,
    required this.members,
    required this.totalDistanceKm,
    required this.totalVisits,
    required this.totalReports,
    required this.presentCount,
    required this.absentCount,
    required this.workingCount,
    required this.idleCount,
    required this.offlineCount,
    required this.pendingReviewCount,
    required this.longStopCount,
  });

  final DateTime date;
  final List<TeamMember> members;
  final double totalDistanceKm;
  final int totalVisits;
  final int totalReports;
  final int presentCount;
  final int absentCount;
  final int workingCount;
  final int idleCount;
  final int offlineCount;
  final int pendingReviewCount;
  final int longStopCount;

  int get headcount => members.length;

  double get attendancePercent =>
      headcount == 0 ? 0 : presentCount * 100 / headcount;
}

/// Geographic extent of a set of points. Pure geometry — no widget imports, so
/// it can be unit-tested and reused by the route painter.
class LatLngBounds {
  const LatLngBounds({
    required this.minLat,
    required this.maxLat,
    required this.minLng,
    required this.maxLng,
  });

  final double minLat;
  final double maxLat;
  final double minLng;
  final double maxLng;

  static const LatLngBounds empty =
      LatLngBounds(minLat: 0, maxLat: 0, minLng: 0, maxLng: 0);

  factory LatLngBounds.of(Iterable<LocationLog> points) {
    if (points.isEmpty) return empty;
    double minLat = points.first.latitude;
    double maxLat = minLat;
    double minLng = points.first.longitude;
    double maxLng = minLng;
    for (final LocationLog p in points) {
      minLat = math.min(minLat, p.latitude);
      maxLat = math.max(maxLat, p.latitude);
      minLng = math.min(minLng, p.longitude);
      maxLng = math.max(maxLng, p.longitude);
    }
    return LatLngBounds(
      minLat: minLat,
      maxLat: maxLat,
      minLng: minLng,
      maxLng: maxLng,
    );
  }

  double get spanLat => maxLat - minLat;
  double get spanLng => maxLng - minLng;
  double get midLat => (minLat + maxLat) / 2;
  double get midLng => (minLng + maxLng) / 2;

  /// Grows the box by [fraction] of its own span on every side. A degenerate
  /// box (single point) is given a small fixed margin so it still projects.
  LatLngBounds padded(double fraction) {
    final double padLat = spanLat == 0 ? 0.002 : spanLat * fraction;
    final double padLng = spanLng == 0 ? 0.002 : spanLng * fraction;
    return LatLngBounds(
      minLat: minLat - padLat,
      maxLat: maxLat + padLat,
      minLng: minLng - padLng,
      maxLng: maxLng + padLng,
    );
  }
}

/// One employee's whole day in one payload — the admin mirror of
/// [HomeSnapshot]. `GET /api/admin/employees/{id}/day`.
class EmployeeDay {
  const EmployeeDay({
    required this.employee,
    required this.date,
    required this.attendance,
    required this.status,
    required this.movement,
    required this.sessions,
    required this.stops,
    required this.visits,
    required this.reports,
    required this.activity,
    required this.summary,
    this.lastFix,
    this.dayState = DayState.open,
    this.closeout,
  });

  final Employee employee;
  final DateTime date;
  final Attendance? attendance;
  final WorkStatus status;
  final MovementStatus movement;
  final List<WorkSession> sessions;
  final List<StopRecord> stops;
  final List<CompanyVisit> visits;
  final List<VisitReport> reports;
  final List<ActivityEvent> activity;
  final DaySummary summary;
  final LocationLog? lastFix;

  /// Whether the employee can still work this day. Only an admin can move it
  /// back to [DayState.open] once it is closed.
  final DayState dayState;

  /// The employee's end-of-day declaration, when they made one. Absent on a
  /// day that was auto-closed — nobody was there to ask.
  final DayCloseout? closeout;

  bool get isEmpty => sessions.isEmpty;

  /// Overlays the lock, which is the only part of the day that changes after
  /// the fact. Everything else arrives in one payload and stays put.
  EmployeeDay withLock({required DayState dayState, DayCloseout? closeout}) =>
      EmployeeDay(
        employee: employee,
        date: date,
        attendance: attendance,
        status: status,
        movement: movement,
        sessions: sessions,
        stops: stops,
        visits: visits,
        reports: reports,
        activity: activity,
        summary: summary,
        lastFix: lastFix,
        dayState: dayState,
        closeout: closeout,
      );
}

/// Admin verdict on a report.
///
/// Deliberately separate from [ReportStatus], which is the *upload* vocabulary
/// (`queued` / `uploading` / `failed`). Review is admin metadata with a
/// reviewer, a note and a timestamp; conflating the two would make the
/// employee-side status switches lie.
enum ReviewDecision { pending, approved, rejected }

extension ReviewDecisionX on ReviewDecision {
  String get label => switch (this) {
        ReviewDecision.pending => 'Pending review',
        ReviewDecision.approved => 'Approved',
        ReviewDecision.rejected => 'Sent back',
      };

  bool get isDecided => this != ReviewDecision.pending;
}

class ReportReview {
  const ReportReview({
    required this.reportId,
    required this.decision,
    this.note,
    this.reviewedBy,
    this.reviewedAt,
  });

  const ReportReview.pending(this.reportId)
      : decision = ReviewDecision.pending,
        note = null,
        reviewedBy = null,
        reviewedAt = null;

  final String reportId;
  final ReviewDecision decision;
  final String? note;
  final String? reviewedBy;
  final DateTime? reviewedAt;
}

/// A report plus who filed it — the report model carries no owner field, so
/// the join happens here.
class ReportInboxItem {
  const ReportInboxItem({
    required this.report,
    required this.employee,
    required this.review,
    required this.companyName,
    this.branchName,
  });

  final VisitReport report;
  final Employee employee;
  final ReportReview review;
  final String companyName;
  final String? branchName;

  ReviewDecision get decision => review.decision;
}

/// Whole-team attendance for a month, ready for the matrix view.
class TeamAttendanceGrid {
  const TeamAttendanceGrid({
    required this.month,
    required this.days,
    required this.rows,
  });

  final DateTime month;
  final List<DateTime> days;
  final List<TeamAttendanceRow> rows;
}

class TeamAttendanceRow {
  const TeamAttendanceRow({
    required this.employee,
    required this.byDayKey,
    required this.presentDays,
    required this.absentDays,
    required this.partialDays,
    required this.attendancePercent,
  });

  final Employee employee;

  /// Key is `yyyymmdd` so the cell lookup needs no date arithmetic.
  final Map<int, AttendanceStatus> byDayKey;
  final int presentDays;
  final int absentDays;
  final int partialDays;
  final double attendancePercent;

  static int dayKey(DateTime d) => d.year * 10000 + d.month * 100 + d.day;
}

/// Everything the route map needs for one employee on one day.
/// `GET /api/admin/employees/{id}/route`.
/// A bare coordinate.
///
/// The models layer deliberately owns no map-package type — swapping
/// flutter_map for anything else must not reach in here — so matched geometry
/// decodes to this and the map widget converts once.
class GeoPoint {
  const GeoPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;
}

/// One session's road-matched geometry.
///
/// [matched] false means the matcher could not explain this session's fixes and
/// [points] is the raw trace instead. That is drawn, but drawn honestly — the
/// alternative, hiding the fallback, makes a straight line across a field look
/// like a road.
class MatchedSegment {
  const MatchedSegment({
    required this.sessionIndex,
    required this.points,
    required this.distanceKm,
    required this.matched,
    required this.confidence,
    this.ratio,
  });

  /// The session's 1-based index, so the segment can be lined up with
  /// [RouteTrack.segments] and keep the same colour.
  final int sessionIndex;

  final List<GeoPoint> points;
  final double distanceKm;
  final bool matched;

  /// The engine's own score. OSRM reports 0 on a good match in several builds,
  /// so this is a hint — [ratio] is what the server actually gated on.
  final double confidence;

  /// Matched road length over raw trace length. Near 1 is a clean match; the
  /// server rejects anything far from it, so a present value is already sane.
  final double? ratio;
}

/// The day's route as the road network explains it.
///
/// Sits beside the raw trace in [RouteTrack], never instead of it: showing both
/// is how an admin tells "rode down a side street" apart from "the matcher
/// guessed".
class MatchedRoute {
  const MatchedRoute({
    required this.engine,
    required this.profile,
    required this.status,
    required this.confidence,
    required this.distanceKm,
    required this.segments,
  });

  /// `osrm` or `valhalla`.
  final String engine;

  /// `motorcycle`, `motor_scooter`, `driving`. On OSRM this is a label the
  /// server was configured with, not proof of how the graph was built.
  final String profile;

  /// `ok`, `partial` or `skipped`. `partial` means at least one session fell
  /// back to its raw trace.
  final String status;

  final double confidence;
  final double distanceKm;
  final List<MatchedSegment> segments;

  /// Whether anything here is worth drawing over the raw trace.
  bool get isUsable => segments.any((MatchedSegment s) => s.matched);

  /// Geometry for the session with this 1-based index, or null when the server
  /// sent none for it.
  List<GeoPoint>? pointsForSession(int index) {
    for (final MatchedSegment s in segments) {
      if (s.sessionIndex == index && s.matched) return s.points;
    }
    return null;
  }
}

class RouteTrack {
  const RouteTrack({
    required this.employeeId,
    required this.date,
    required this.points,
    required this.sessions,
    required this.stops,
    required this.visits,
    required this.totalDistanceKm,
    this.matched,
  });

  final String employeeId;
  final DateTime date;
  final List<LocationLog> points;
  final List<WorkSession> sessions;
  final List<StopRecord> stops;
  final List<CompanyVisit> visits;
  final double totalDistanceKm;

  /// Road-matched geometry, when the server had it. Null for today (the trace
  /// is still being written), when snapping is off, and whenever the matcher
  /// was unavailable — all of which mean "draw the raw trace".
  final MatchedRoute? matched;

  bool get isEmpty => points.length < 2;

  LatLngBounds get bounds => LatLngBounds.of(points);

  /// Points grouped by session, in order. The painter strokes one path per
  /// group so a lunch break never draws a phantom straight line across the
  /// map between where the employee stopped and where they resumed.
  List<List<LocationLog>> get segments {
    final List<List<LocationLog>> out = <List<LocationLog>>[];
    String? current;
    for (final LocationLog p in points) {
      if (p.sessionId != current) {
        out.add(<LocationLog>[]);
        current = p.sessionId;
      }
      out.last.add(p);
    }
    return out;
  }

  DateTime? get firstFixAt => points.isEmpty ? null : points.first.recordedAt;
  DateTime? get lastFixAt => points.isEmpty ? null : points.last.recordedAt;

  int get queuedCount =>
      points.where((LocationLog p) => p.syncState != SyncState.synced).length;
}

/// Write-side payload for add / edit employee. A null [id] means create.
class EmployeeDraft {
  const EmployeeDraft({
    required this.name,
    required this.employeeCode,
    required this.designation,
    required this.department,
    required this.email,
    required this.phone,
    required this.region,
    required this.reportingTo,
    required this.joinedOn,
    this.id,
    this.bloodGroup = '',
    this.address = '',
    this.password,
  });

  /// Seeds the edit form from an existing record.
  factory EmployeeDraft.from(Employee employee) {
    return EmployeeDraft(
      id: employee.id,
      name: employee.name,
      employeeCode: employee.employeeCode,
      designation: employee.designation,
      department: employee.department,
      email: employee.email,
      phone: employee.phone,
      region: employee.region,
      reportingTo: employee.reportingTo,
      joinedOn: employee.joinedOn,
      bloodGroup: employee.bloodGroup,
      address: employee.address,
    );
  }

  final String? id;
  final String name;
  final String employeeCode;
  final String designation;
  final String department;
  final String email;
  final String phone;
  final String region;
  final String reportingTo;
  final DateTime joinedOn;
  final String bloodGroup;
  final String address;

  /// First-login credential, create only. Null means "let the server generate
  /// one" — it comes back on the create response as
  /// [EmployeeSaveResult.temporaryPassword] and is readable exactly once.
  /// Never populated from an existing record: a password cannot be read back.
  final String? password;

  bool get isCreate => id == null;
}

/// What a save returns. A bare [Employee] cannot carry the generated
/// credential, and the credential has nowhere else to live: it is not a field
/// of the record and no route will hand it back a second time.
class EmployeeSaveResult {
  const EmployeeSaveResult(this.employee, {this.temporaryPassword});

  final Employee employee;

  /// Non-null only when the server generated the password — a create with no
  /// `password` in the draft. Show it once, then it is gone.
  final String? temporaryPassword;
}

/// One employee's contribution to the team numbers over a range.
class EmployeeMetric {
  const EmployeeMetric({
    required this.employeeId,
    required this.name,
    required this.initials,
    required this.distanceKm,
    required this.visits,
    required this.reports,
    required this.presentDays,
    required this.absentDays,
    required this.attendancePercent,
    required this.averageWorkedDuration,
  });

  final String employeeId;
  final String name;
  final String initials;
  final double distanceKm;
  final int visits;
  final int reports;
  final int presentDays;
  final int absentDays;
  final double attendancePercent;
  final Duration averageWorkedDuration;
}

/// Team-wide aggregate — `GET /api/admin/statistics`. Field names mirror
/// [PeriodStatistics] so the two statistics screens read the same way.
class TeamStatistics {
  const TeamStatistics({
    required this.rangeLabel,
    required this.perEmployee,
    required this.totalDistanceKm,
    required this.totalVisits,
    required this.totalReports,
    required this.averageDistanceKm,
    required this.averageWorkedDuration,
    required this.attendancePercent,
    required this.workingDays,
    required this.dailyDistance,
  });

  final String rangeLabel;
  final List<EmployeeMetric> perEmployee;
  final double totalDistanceKm;
  final int totalVisits;
  final int totalReports;

  /// Per employee, per working day.
  final double averageDistanceKm;
  final Duration averageWorkedDuration;
  final double attendancePercent;
  final int workingDays;

  /// Team distance per calendar day, for the bar chart.
  final List<DailyMetric> dailyDistance;

  int get headcount => perEmployee.length;
}
