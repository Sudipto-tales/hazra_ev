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

/// What an employee is doing right now, as **one** value — API plan §3.14.
///
/// Derived entirely server-side from `status` + `movement` + `locationHealth` +
/// the open-stop row + the two fix timestamps, and sent *beside* those fields
/// rather than instead of them. It exists so the live map picks one glyph from
/// one value: recombining three enums and two timestamps on the client is the
/// exact duplication `activity` was specced to remove, so nothing in this app
/// may rebuild it locally.
///
/// Absent on a server below config `version` 9 (today's production serves 7).
/// That is why every holder of this type is nullable — see
/// `LiveMarker.activity`, which degrades to a neutral glyph rather than
/// guessing.
enum ActivityState {
  moving,
  stopped,
  idle,
  locationUnavailable,
  offline,
  paused,
  ended,

  /// No session today at all. §3.14's mapping note: *not on the map*.
  notStarted,
}

extension ActivityStateX on ActivityState {
  String get label => switch (this) {
        ActivityState.moving => 'Moving',
        ActivityState.stopped => 'Stopped',
        ActivityState.idle => 'Idle',
        ActivityState.locationUnavailable => 'No GPS',
        ActivityState.offline => 'Offline',
        ActivityState.paused => 'On break',
        ActivityState.ended => 'Day ended',
        ActivityState.notStarted => 'Not started',
      };

  /// `not_started` is the one value with no marker: there is nowhere to put it.
  bool get isMappable => this != ActivityState.notStarted;
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
    this.lowAccuracy = false,
    this.headingDeg,
  });

  /// Rebuild a fix from the on-disk offline queue. Only the device writes this
  /// shape (via [toJson]); it never crosses the wire, so it stays private to
  /// the persistence layer rather than living in `Wire`.
  factory LocationLog.fromJson(Map<String, dynamic> json) => LocationLog(
        id: json['id'] as String,
        sessionId: json['sessionId'] as String,
        latitude: (json['latitude'] as num).toDouble(),
        longitude: (json['longitude'] as num).toDouble(),
        accuracy: (json['accuracy'] as num).toDouble(),
        speedKmh: (json['speedKmh'] as num).toDouble(),
        recordedAt: DateTime.parse(json['recordedAt'] as String),
        syncState: SyncState.values.firstWhere(
          (SyncState s) => s.name == json['syncState'],
          orElse: () => SyncState.queued,
        ),
        lowAccuracy: json['lowAccuracy'] as bool? ?? false,
        headingDeg: (json['headingDeg'] as num?)?.toDouble(),
      );

  final String id;
  final String sessionId;
  final double latitude;
  final double longitude;
  final double accuracy;
  final double speedKmh;
  final DateTime recordedAt;
  final SyncState syncState;

  /// This fix was captured with horizontal accuracy worse than
  /// `TrackingConfig.minAccuracyMetres`. It is kept and uploaded anyway — a
  /// weak-signal point is still roughly where the employee was, and dropping it
  /// left weak-GPS stretches with no trail at all — but it is flagged so the
  /// server (and later the UI) can tell a confident fix from a rough one.
  final bool lowAccuracy;

  /// Degrees clockwise from true north, `0`–`359.9`, or **null when unknown**
  /// (API plan §3.11). `null` is not `0`: `0` means due north, and a parked
  /// device, a synthetic heartbeat fix, Android's `-1` and iOS's `NaN` all mean
  /// *no heading*. Coalescing unknown to `0` would swing a direction-of-travel
  /// marker north on every parked fix, which renders missing data as data — so
  /// the live map never rotates anything on a null.
  ///
  /// Absent from every server below the §3.11 `heading` work (tuple slot 6 /
  /// object `headingDeg`); absent and present-but-null are the same thing.
  final double? headingDeg;

  /// On-disk shape for the offline queue. Not a wire payload — see
  /// [LocationLog.fromJson].
  Map<String, dynamic> toJson() => <String, dynamic>{
        'id': id,
        'sessionId': sessionId,
        'latitude': latitude,
        'longitude': longitude,
        'accuracy': accuracy,
        'speedKmh': speedKmh,
        'recordedAt': recordedAt.toUtc().toIso8601String(),
        'syncState': syncState.name,
        'lowAccuracy': lowAccuracy,
        if (headingDeg != null) 'headingDeg': headingDeg,
      };
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

/// What `POST /tracking/watch` answers with — API plan §3.11.
///
/// A watch is a request for freshness, not a claim of it. It makes a device
/// drain faster (`liveSyncIntervalSeconds` instead of `syncIntervalSeconds`)
/// for [ttlSeconds], and nothing else: it never changes what is stored,
/// derived or returned about any fix, and the device only learns about it when
/// it next posts a batch. Entering live mode therefore costs up to one full
/// drain interval — which is exactly why the map must show each marker's real
/// fix age rather than assume a watch worked.
///
/// There is deliberately **no unwatch call**. Closing the map stops
/// re-posting and the TTL expires on its own.
class LiveWatch {
  const LiveWatch({
    required this.ttlSeconds,
    required this.watchedUntil,
    required this.watching,
    required this.rejected,
  });

  /// Nobody is being watched — what a server that has never heard of
  /// `/tracking/watch` effectively means. [isLive] is false, so the map keeps
  /// working and simply says so.
  static const LiveWatch none = LiveWatch(
    ttlSeconds: 0,
    watchedUntil: null,
    watching: <WatchedEmployee>[],
    rejected: <RejectedWatch>[],
  );

  /// What the server actually granted after clamping (`30..600`). The response
  /// is the acknowledgement, so the re-watch cadence is computed from this and
  /// never from what was asked for.
  final int ttlSeconds;

  /// Absolute UTC deadline, parsed to local. Never a duration: the device
  /// compares it to its own clock minutes after it was issued.
  final DateTime? watchedUntil;

  final List<WatchedEmployee> watching;

  /// Ids the server would not watch — `NOT_FOUND` / `NOT_IN_ORG` / `INACTIVE`.
  /// One stale id must not stop a map of eight from going live, so these are
  /// reported rather than thrown.
  final List<RejectedWatch> rejected;

  bool get isLive => watching.isNotEmpty;

  /// Half the granted TTL, so a single dropped call does not fall out of live
  /// mode. Floored at 15 s so a pathologically small TTL cannot turn the
  /// re-watch into a hot loop.
  Duration get renewInterval => Duration(
        seconds: ttlSeconds <= 0 ? 60 : (ttlSeconds ~/ 2).clamp(15, 300),
      );
}

class WatchedEmployee {
  const WatchedEmployee({
    required this.employeeId,
    required this.watchingUntil,
    required this.extended,
  });

  final String employeeId;
  final DateTime? watchingUntil;

  /// This employee was already being watched and the shared deadline moved
  /// later. The watch row is per employee, not per admin.
  final bool extended;
}

class RejectedWatch {
  const RejectedWatch({required this.employeeId, required this.reason});

  final String employeeId;

  /// Kept as a raw string: a new server-side reason must not crash an older
  /// device, and nothing in the UI branches on it.
  final String reason;
}
