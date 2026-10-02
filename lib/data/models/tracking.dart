import 'day_closeout.dart';

/// Attendance → Session → Location log chain, plus the status vocabulary the
/// whole app renders from.

/// What the employee sees at the top of Home.
enum WorkStatus {
  notStarted,
  working,
  idle,
  locationUnavailable,
  offline,
  ended,
}

extension WorkStatusX on WorkStatus {
  String get label => switch (this) {
        WorkStatus.notStarted => 'Not Started',
        WorkStatus.working => 'Working',
        WorkStatus.idle => 'Stopped / Idle',
        WorkStatus.locationUnavailable => 'Location Unavailable',
        WorkStatus.offline => 'Offline',
        WorkStatus.ended => 'Day Ended',
      };

  /// True when a session is open — drives the live timer + End Day button.
  bool get isSessionOpen => switch (this) {
        WorkStatus.working ||
        WorkStatus.idle ||
        WorkStatus.locationUnavailable ||
        WorkStatus.offline =>
          true,
        WorkStatus.notStarted || WorkStatus.ended => false,
      };

  /// True when tracking is degraded and the employee must be warned.
  bool get isDegraded => switch (this) {
        WorkStatus.locationUnavailable || WorkStatus.offline => true,
        _ => false,
      };
}

/// Movement state derived from the last few GPS fixes.
enum MovementStatus { moving, stationary, unknown }

extension MovementStatusX on MovementStatus {
  String get label => switch (this) {
        MovementStatus.moving => 'Moving',
        MovementStatus.stationary => 'Stationary',
        MovementStatus.unknown => 'Unknown',
      };
}

/// Health of the device location stack — surfaced verbatim in Settings.
enum LocationHealth {
  ok,
  serviceDisabled,
  permissionDenied,
  permissionDeniedForever,
  backgroundDenied,
  poorAccuracy,
  noInternet,
}

extension LocationHealthX on LocationHealth {
  bool get blocksStart => switch (this) {
        LocationHealth.ok || LocationHealth.noInternet || LocationHealth.poorAccuracy => false,
        _ => true,
      };

  String get title => switch (this) {
        LocationHealth.ok => 'Tracking active',
        LocationHealth.serviceDisabled => 'Location service is off',
        LocationHealth.permissionDenied => 'Location permission denied',
        LocationHealth.permissionDeniedForever =>
          'Location permission permanently denied',
        LocationHealth.backgroundDenied => 'Background location not allowed',
        LocationHealth.poorAccuracy => 'Weak GPS signal',
        LocationHealth.noInternet => 'No internet connection',
      };

  String get message => switch (this) {
        LocationHealth.ok =>
          'Your location is being recorded while your session is open.',
        LocationHealth.serviceDisabled =>
          'Turn on device location to start your day. Tracking cannot begin without a valid GPS fix.',
        LocationHealth.permissionDenied =>
          'Grant location permission so your route and visits can be recorded.',
        LocationHealth.permissionDeniedForever =>
          'Open system settings and allow location access for this app.',
        LocationHealth.backgroundDenied =>
          'Set location permission to "Allow all the time" so tracking continues when the screen is off.',
        LocationHealth.poorAccuracy =>
          'GPS accuracy is low. Move to an open area for a better fix.',
        LocationHealth.noInternet =>
          'Locations are queued on this device and will sync automatically when you are back online.',
      };
}

/// One workday. Owns 1..n sessions.
class Attendance {
  const Attendance({
    required this.id,
    required this.date,
    required this.status,
    required this.joiningTime,
    required this.endTime,
    required this.sessionCount,
    required this.workedDuration,
    required this.distanceKm,
    required this.companiesVisited,
    required this.reportsSubmitted,
    required this.stopDuration,
    required this.longestStop,
  });

  final String id;
  final DateTime date;
  final AttendanceStatus status;

  /// First valid session start of the day — the authoritative joining time.
  final DateTime? joiningTime;
  final DateTime? endTime;
  final int sessionCount;
  final Duration workedDuration;
  final double distanceKm;
  final int companiesVisited;
  final int reportsSubmitted;
  final Duration stopDuration;
  final Duration longestStop;
}

enum AttendanceStatus { present, absent, partial, holiday, weekend, noData }

extension AttendanceStatusX on AttendanceStatus {
  String get label => switch (this) {
        AttendanceStatus.present => 'Present',
        AttendanceStatus.absent => 'Absent',
        AttendanceStatus.partial => 'Partial',
        AttendanceStatus.holiday => 'Holiday',
        AttendanceStatus.weekend => 'Weekend',
        AttendanceStatus.noData => 'No data',
      };
}

/// A continuous span of tracked work. A day can hold several.
class WorkSession {
  const WorkSession({
    required this.id,
    required this.index,
    required this.startTime,
    required this.endTime,
    required this.distanceKm,
    required this.locationPoints,
    required this.startLatitude,
    required this.startLongitude,
    this.endReason,
  });

  final String id;

  /// 1-based position within the day — shown as "Session 2".
  final int index;
  final DateTime startTime;
  final DateTime? endTime;
  final double distanceKm;
  final int locationPoints;
  final double startLatitude;
  final double startLongitude;

  /// Why the session stopped. Null while it is still open.
  final SessionEndReason? endReason;

  bool get isOpen => endTime == null;

  /// Closed with nobody watching — GPS and network were both gone, so no
  /// closeout form was ever shown for it.
  bool get wasAutoClosed => endReason == SessionEndReason.auto;

  Duration durationAt(DateTime now) =>
      (endTime ?? now).difference(startTime);
}

/// One GPS fix. Kept client-side only until synced.
class LocationLog {
  const LocationLog({
    required this.id,
    required this.sessionId,
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.speedKmh,
    required this.recordedAt,
    required this.syncState,
  });

  final String id;
  final String sessionId;
  final double latitude;
  final double longitude;
  final double accuracy;
  final double speedKmh;
  final DateTime recordedAt;
  final SyncState syncState;
}

enum SyncState { synced, queued, failed }

extension SyncStateX on SyncState {
  String get label => switch (this) {
        SyncState.synced => 'Synced',
        SyncState.queued => 'Queued',
        SyncState.failed => 'Failed',
      };
}

/// Snapshot of the offline queue, rendered in the tracking status sheet.
class SyncSnapshot {
  const SyncSnapshot({
    required this.queued,
    required this.failed,
    required this.lastSyncedAt,
    required this.isOnline,
  });

  final int queued;
  final int failed;
  final DateTime? lastSyncedAt;
  final bool isOnline;

  bool get isClean => queued == 0 && failed == 0;
}
