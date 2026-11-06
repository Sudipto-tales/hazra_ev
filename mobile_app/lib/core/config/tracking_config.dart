/// Business thresholds for tracking. Kept in one place so the values can later
/// be driven by the backend (`GET /api/config`) without touching the UI.
class TrackingConfig {
  const TrackingConfig({
    this.locationIntervalSeconds = 60,
    this.distanceFilterMetres = 20,
    this.minStepMetres = 15,
    this.accuracyStepFactor = 1,
    this.minAccuracyMetres = 50,
    this.stopRadiusMetres = 75,
    this.stopThresholdMinutes = 10,
    this.longStopThresholdMinutes = 45,
    this.movementSpeedThresholdKmh = 2,
    this.movementExitSpeedKmh = 3,
    this.maxJumpKmh = 180,
    this.offlineThresholdMinutes = 10,
    this.locationUnavailableThresholdMinutes = 15,
    this.syncBatchSize = 50,
    this.syncIntervalSeconds = 300,
    this.liveSyncIntervalSeconds = 15,
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

  /// The server's **measurement** floor, mirrored so the device's live
  /// distance uses the identical rule the server will reconcile it to. A leg
  /// counts only when it clears `max(minStepMetres, accuracyStepFactor *
  /// fix.accuracy)` — never [distanceFilterMetres], which is the OS *capture*
  /// filter and a different number doing a different job (036). Server-driven;
  /// a pre-036 server omits it → local default (15 m).
  final double minStepMetres;

  /// Scales the measurement floor by each fix's own error, so a weak-GPS leg
  /// must out-travel what it could be wrong by before it books distance. 0
  /// restores a flat [minStepMetres] floor. Server-driven; a pre-036 server
  /// omits it → local default (1).
  final double accuracyStepFactor;

  /// Fixes worse than this horizontal accuracy are dropped.
  final double minAccuracyMetres;

  /// Employee must stay inside this radius to be a stop candidate.
  final double stopRadiusMetres;

  /// Minimum dwell time before a stop candidate is recorded.
  final int stopThresholdMinutes;

  /// Dwell time after which the admin sees a red "long stop".
  final int longStopThresholdMinutes;

  /// Below this speed the employee is considered stationary — the **lower**
  /// edge of the movement dead band. Unchanged in meaning by v9.
  final double movementSpeedThresholdKmh;

  /// Upper edge of the movement dead band (§3.12): enter `moving` at or above
  /// this, enter `stationary` below [movementSpeedThresholdKmh], and hold the
  /// current state in between so one noisy fix cannot flip the marker.
  /// Server-driven from config `version` 9; a v7/v8 server omits it and the
  /// local default (3 km/h) stands. Equality with the lower edge means "no
  /// dead band", i.e. the pre-v9 behaviour.
  ///
  /// Consumed by the **server's** `Engine::liveStatus()`, which is what the
  /// live map's `activity` is derived from. The device's own cadence state
  /// machine keeps its 90 s debounce (`_evaluateMovement`) — see the note in
  /// `docs/.inbox/` on why that was deliberately left alone.
  final double movementExitSpeedKmh;

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

  /// Drain cadence used **only** while an admin is actively watching this
  /// device — `now < liveUntil`, where `liveUntil` rides in the `meta` of the
  /// `POST /tracking/locations` response (§3.11). [syncIntervalSeconds]
  /// applies otherwise. Server-driven from config `version` 9; a v7 server
  /// omits it and the local default (15s) stands.
  ///
  /// This is *upload* cadence and nothing else: a watch costs radio time, not
  /// GPS time, so [locationIntervalSeconds], [distanceFilterMetres], the
  /// adaptive moving/stationary state machine and the ~5 min heartbeat are all
  /// untouched by it.
  final int liveSyncIntervalSeconds;

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
    double? minStepMetres,
    double? accuracyStepFactor,
    double? minAccuracyMetres,
    double? stopRadiusMetres,
    int? stopThresholdMinutes,
    int? longStopThresholdMinutes,
    double? movementSpeedThresholdKmh,
    double? movementExitSpeedKmh,
    double? maxJumpKmh,
    int? offlineThresholdMinutes,
    int? locationUnavailableThresholdMinutes,
    int? syncBatchSize,
    int? syncIntervalSeconds,
    int? liveSyncIntervalSeconds,
    int? autoCloseAfterMinutes,
  }) {
    return TrackingConfig(
      locationIntervalSeconds:
          locationIntervalSeconds ?? this.locationIntervalSeconds,
      distanceFilterMetres: distanceFilterMetres ?? this.distanceFilterMetres,
      minStepMetres: minStepMetres ?? this.minStepMetres,
      accuracyStepFactor: accuracyStepFactor ?? this.accuracyStepFactor,
      minAccuracyMetres: minAccuracyMetres ?? this.minAccuracyMetres,
      stopRadiusMetres: stopRadiusMetres ?? this.stopRadiusMetres,
      stopThresholdMinutes: stopThresholdMinutes ?? this.stopThresholdMinutes,
      longStopThresholdMinutes:
          longStopThresholdMinutes ?? this.longStopThresholdMinutes,
      movementSpeedThresholdKmh:
          movementSpeedThresholdKmh ?? this.movementSpeedThresholdKmh,
      movementExitSpeedKmh: movementExitSpeedKmh ?? this.movementExitSpeedKmh,
      maxJumpKmh: maxJumpKmh ?? this.maxJumpKmh,
      offlineThresholdMinutes:
          offlineThresholdMinutes ?? this.offlineThresholdMinutes,
      locationUnavailableThresholdMinutes:
          locationUnavailableThresholdMinutes ??
              this.locationUnavailableThresholdMinutes,
      syncBatchSize: syncBatchSize ?? this.syncBatchSize,
      syncIntervalSeconds: syncIntervalSeconds ?? this.syncIntervalSeconds,
      liveSyncIntervalSeconds:
          liveSyncIntervalSeconds ?? this.liveSyncIntervalSeconds,
      autoCloseAfterMinutes:
          autoCloseAfterMinutes ?? this.autoCloseAfterMinutes,
    );
  }
}
