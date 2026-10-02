/// Stop candidates (derived from GPS history) and the company visits they get
/// promoted to.

enum VisitStatus { inProgress, completed, unassigned }

extension VisitStatusX on VisitStatus {
  String get label => switch (this) {
        VisitStatus.inProgress => 'In progress',
        VisitStatus.completed => 'Completed',
        VisitStatus.unassigned => 'Unassigned stop',
      };
}

/// A dwell detected from location history — no geofence required.
/// Promoted to a [CompanyVisit] once linked to a company/branch.
class StopRecord {
  const StopRecord({
    required this.id,
    required this.sessionId,
    required this.arrival,
    required this.departure,
    required this.latitude,
    required this.longitude,
    required this.radiusMetres,
    required this.visitId,
  });

  final String id;
  final String sessionId;
  final DateTime arrival;
  final DateTime? departure;
  final double latitude;
  final double longitude;
  final double radiusMetres;

  /// Null while the stop is not yet associated with a company visit.
  final String? visitId;

  bool get isLinked => visitId != null;

  Duration durationAt(DateTime now) => (departure ?? now).difference(arrival);
}

/// The business event: employee reached a company, dealt, left.
class CompanyVisit {
  const CompanyVisit({
    required this.id,
    required this.sessionId,
    required this.companyId,
    required this.branchId,
    required this.stopId,
    required this.arrival,
    required this.departure,
    required this.latitude,
    required this.longitude,
    required this.status,
    required this.reportIds,
    required this.dealReference,
  });

  final String id;
  final String sessionId;
  final String companyId;
  final String? branchId;
  final String? stopId;
  final DateTime arrival;
  final DateTime? departure;
  final double latitude;
  final double longitude;
  final VisitStatus status;

  /// A visit can carry many reports — never collapse to one.
  final List<String> reportIds;
  final String? dealReference;

  bool get hasReport => reportIds.isNotEmpty;

  Duration durationAt(DateTime now) => (departure ?? now).difference(arrival);
}
