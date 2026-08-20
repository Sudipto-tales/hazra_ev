/// Business thresholds for tracking. Kept in one place so the values can later
/// be driven by the backend (`GET /api/config`) without touching the UI.
class TrackingConfig {
  const TrackingConfig({
    this.locationIntervalSeconds = 30,
    this.minAccuracyMetres = 50,
    this.stopRadiusMetres = 75,
    this.stopThresholdMinutes = 10,
    this.longStopThresholdMinutes = 45,
    this.movementSpeedThresholdKmh = 2,
    this.maxJumpKmh = 180,
    this.offlineThresholdMinutes = 10,
    this.locationUnavailableThresholdMinutes = 15,
    this.syncBatchSize = 50,
  });

  /// How often a GPS fix is captured while a session is active.
  final int locationIntervalSeconds;

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

  static const TrackingConfig defaults = TrackingConfig();

  /// Used by the admin "tracking rules" form, which edits one field at a time.
  TrackingConfig copyWith({
    int? locationIntervalSeconds,
    double? minAccuracyMetres,
    double? stopRadiusMetres,
    int? stopThresholdMinutes,
    int? longStopThresholdMinutes,
    double? movementSpeedThresholdKmh,
    double? maxJumpKmh,
    int? offlineThresholdMinutes,
    int? locationUnavailableThresholdMinutes,
    int? syncBatchSize,
  }) {
    return TrackingConfig(
      locationIntervalSeconds:
          locationIntervalSeconds ?? this.locationIntervalSeconds,
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
    );
  }
}
