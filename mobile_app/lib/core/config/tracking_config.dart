/// Business thresholds for tracking. Kept in one place so the values can later
/// be driven by the backend (`GET /api/config`) without touching the UI.
class TrackingConfig {
  const TrackingConfig({
    this.locationIntervalSeconds = 60,
    this.distanceFilterMetres = 20,
    this.minAccuracyMetres = 50,
    this.stopRadiusMetres = 75,
    this.stopThresholdMinutes = 10,
    this.longStopThresholdMinutes = 45,
    this.movementSpeedThresholdKmh = 2,
    this.maxJumpKmh = 180,
    this.offlineThresholdMinutes = 10,
    this.locationUnavailableThresholdMinutes = 15,
    this.syncBatchSize = 50,
    this.syncIntervalSeconds = 300,
    this.autoCloseAfterMinutes = 30,
  });

  /// How often a GPS fix is captured while a session is active. 60s keeps the
  /// field trail accurate while halving GPS wakeups versus the old 30s cadence.
  final int locationIntervalSeconds;

  /// Minimum metres the device must move before the OS emits another streamed
  /// fix. While the employee is parked this lets the GPS radio idle instead of
  /// reporting the same spot every interval. A local default for now; like the
  /// rest of this class it can later be driven by the backend.
  final int distanceFilterMetres;

  /// Fixes worse than this horizontal accuracy are dropped.
  final double minAccuracyMetres;

  /// Employee must stay inside this radius to be a stop candidate.
  final double stopRadiusMetres;

  /// Minimum dwell time before a stop candidate is recorded.
  final int stopThresholdMinutes;

  /// Dwell time after which the admin sees a red "long stop".
  final int longStopThresholdMinutes;

  /// Below this speed the employee is considered stationary.
  final double movementSpeedThresholdKmh;

  /// Implied speed above this marks the point as an invalid GPS jump.
  final double maxJumpKmh;

  /// No fix uploaded for this long → status becomes Offline.
  final int offlineThresholdMinutes;

  /// No valid fix for this long → status becomes Location Unavailable.
  final int locationUnavailableThresholdMinutes;

  /// Queued offline fixes flushed per request.
  final int syncBatchSize;

  /// How often the offline queue is drained to the server (the `_drain` batch
  /// timer in `location_service.dart`). Server-driven from config `version` 8;
  /// a v7 server omits it and the local default (300s) stands. This is *upload*
  /// cadence and is independent of [locationIntervalSeconds] (GPS *capture*
  /// cadence) — the server clamps it below the offline window so a device
  /// always posts at least twice before it could be flagged offline.
  final int syncIntervalSeconds;

  /// GPS off *and* no network for this long with a session open → the session
  /// closes itself. The day stays open: an automatic close is not a
  /// declaration, so no closeout form is shown for it. The server enforces the
  /// same rule for devices that are simply dead.
  final int autoCloseAfterMinutes;

  static const TrackingConfig defaults = TrackingConfig();

  /// Used by the admin "tracking rules" form, which edits one field at a time.
  TrackingConfig copyWith({
    int? locationIntervalSeconds,
    int? distanceFilterMetres,
    double? minAccuracyMetres,
    double? stopRadiusMetres,
    int? stopThresholdMinutes,
    int? longStopThresholdMinutes,
    double? movementSpeedThresholdKmh,
    double? maxJumpKmh,
    int? offlineThresholdMinutes,
    int? locationUnavailableThresholdMinutes,
    int? syncBatchSize,
    int? syncIntervalSeconds,
    int? autoCloseAfterMinutes,
  }) {
    return TrackingConfig(
      locationIntervalSeconds:
          locationIntervalSeconds ?? this.locationIntervalSeconds,
      distanceFilterMetres: distanceFilterMetres ?? this.distanceFilterMetres,
      minAccuracyMetres: minAccuracyMetres ?? this.minAccuracyMetres,
      stopRadiusMetres: stopRadiusMetres ?? this.stopRadiusMetres,
      stopThresholdMinutes: stopThresholdMinutes ?? this.stopThresholdMinutes,
      longStopThresholdMinutes:
          longStopThresholdMinutes ?? this.longStopThresholdMinutes,
      movementSpeedThresholdKmh:
          movementSpeedThresholdKmh ?? this.movementSpeedThresholdKmh,
      maxJumpKmh: maxJumpKmh ?? this.maxJumpKmh,
      offlineThresholdMinutes:
          offlineThresholdMinutes ?? this.offlineThresholdMinutes,
      locationUnavailableThresholdMinutes:
          locationUnavailableThresholdMinutes ??
              this.locationUnavailableThresholdMinutes,
      syncBatchSize: syncBatchSize ?? this.syncBatchSize,
      syncIntervalSeconds: syncIntervalSeconds ?? this.syncIntervalSeconds,
      autoCloseAfterMinutes:
          autoCloseAfterMinutes ?? this.autoCloseAfterMinutes,
    );
  }
}
