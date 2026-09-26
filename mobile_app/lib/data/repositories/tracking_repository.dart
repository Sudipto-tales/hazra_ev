import '../api/api_client.dart';
import '../api/wire.dart';
import '../models/models.dart';

/// The tracking **write** path — the only part of the app that sends data the
/// server cannot derive from anything else.
///
/// Deliberately separate from [EmployeeRepository]: that one is a read surface
/// the UI polls, this one is written by the device's own state machine
/// ([TrackingController]) and by the offline queue in `GeoLocationService`.
/// Mixing them would put "retry forever in the background" semantics on an
/// interface whose other methods are "fetch and show".
///
/// Method → route, following `docs/02-API-PLAN.md` §3.11:
///
///   openSession()   POST  /tracking/sessions        (idempotent on clientId)
///   closeSession()  PATCH /tracking/sessions/{id}
///   pushLocations() POST  /tracking/locations       (batched, ≤ syncBatchSize)
///   reportHealth()  POST  /tracking/health          (fire-and-forget, 204)
///
/// Every method may throw [ApiException]. Callers are expected to treat a
/// throw as "not delivered yet" and retry — nothing here may be swallowed into
/// a fake success, because the whole record of the workday depends on it.
abstract class TrackingRepository {
  /// `POST /tracking/sessions` — opens a work session server-side.
  ///
  /// [clientId] is the device's own session id and is the idempotency key: a
  /// repeated open with the same value returns the **existing** session rather
  /// than creating a second one. That is what makes Start Day safe to retry on
  /// a dead network, and it is why the caller must keep the same id across
  /// retries instead of generating a new one.
  ///
  /// The session starts at [fix], not at the tap — the server takes
  /// `startedAt` from the fix when one is not supplied, and ingests the fix as
  /// the first point of the chain.
  Future<WorkSession> openSession({
    required String clientId,
    required LocationLog fix,
    DateTime? startedAt,
  });

  /// `PATCH /tracking/sessions/{id}` — closes it.
  ///
  /// [sessionId] is the **server** id returned by [openSession], not the
  /// device's clientId. Naturally idempotent: the server only writes
  /// `endedAt` when it is still null and returns the session either way, so a
  /// retry after a timeout cannot move a close time that already landed.
  Future<WorkSession> closeSession({
    required String sessionId,
    required SessionEndReason reason,
    LocationLog? fix,
    DateTime? endedAt,
  });

  /// `POST /tracking/locations` — the fix ingest, and the only batched call in
  /// the system.
  ///
  /// [fixes] must hold at most `TrackingConfig.syncBatchSize` entries; the
  /// server rejects a larger batch outright rather than truncating it. The
  /// caller owns the batching because the caller owns the queue.
  ///
  /// The response names every fix the server has ruled on, accepted or
  /// rejected, so the device can drop exactly those from its queue — see
  /// [LocationSyncResult.settled].
  Future<LocationSyncResult> pushLocations({
    required String sessionId,
    required List<LocationLog> fixes,
  });

  /// `POST /tracking/health` — what turns a team member red on the admin
  /// dashboard while their GPS is off. No response body; cheap enough to send
  /// on every change and only on a change.
  Future<void> reportHealth({
    required LocationHealth health,
    bool isOnline = true,
    String? permission,
  });
}

/// One fix the server refused, and why.
///
/// [reason] is a stable server code — `ACCURACY`, `JUMP`, `DUPLICATE` or
/// `MALFORMED`. It is kept as a raw string on purpose: a new server-side
/// reason must not crash a device running an older build, and nothing in the
/// UI branches on it.
class RejectedFix {
  const RejectedFix({required this.id, required this.reason});

  final String id;
  final String reason;

  @override
  String toString() => '$id:$reason';
}

/// What `POST /tracking/locations` answers with.
class LocationSyncResult {
  const LocationSyncResult({required this.accepted, required this.rejected});

  /// Client ids the server stored.
  final List<String> accepted;

  /// Client ids the server refused. A rejection is final — the same fix
  /// re-sent gets the same answer — so these leave the queue too.
  final List<RejectedFix> rejected;

  static const LocationSyncResult empty =
      LocationSyncResult(accepted: <String>[], rejected: <RejectedFix>[]);

  /// Every id the server has ruled on. These, and only these, may be dropped
  /// from the device queue: a fix the server did not mention was not received,
  /// and must be sent again.
  Set<String> get settled => <String>{
        ...accepted,
        ...rejected.map((RejectedFix r) => r.id),
      };

  bool get isEmpty => accepted.isEmpty && rejected.isEmpty;
}

/// [TrackingRepository] over `website/api`.
class HttpTrackingRepository implements TrackingRepository {
  HttpTrackingRepository(this._api);

  final ApiClient _api;

  @override
  Future<WorkSession> openSession({
    required String clientId,
    required LocationLog fix,
    DateTime? startedAt,
  }) async {
    final ApiResult result = await _api.post(
      '/tracking/sessions',
      // The header is belt and braces — the server's real guarantee for this
      // route is the `(employee_id, client_id)` lookup in its own handler, and
      // that one is implemented. See the note on `_idempotencyKey`.
      idempotencyKey: clientId,
      body: <String, dynamic>{
        'clientId': clientId,
        'startedAt': (startedAt ?? fix.recordedAt).toUtc().toIso8601String(),
        'fix': _fixObject(fix),
      },
    );

    return Wire.session(result.map);
  }

  @override
  Future<WorkSession> closeSession({
    required String sessionId,
    required SessionEndReason reason,
    LocationLog? fix,
    DateTime? endedAt,
  }) async {
    final ApiResult result = await _api.patch(
      '/tracking/sessions/$sessionId',
      body: <String, dynamic>{
        'reason': _reason(reason),
        'endedAt': (endedAt ?? DateTime.now()).toUtc().toIso8601String(),
        if (fix != null) 'fix': _fixObject(fix),
      },
    );

    return Wire.session(result.map);
  }

  @override
  Future<LocationSyncResult> pushLocations({
    required String sessionId,
    required List<LocationLog> fixes,
  }) async {
    if (fixes.isEmpty) {
      // The server answers 422 on an empty batch. Nothing was sent, so nothing
      // was settled — saying so beats making the caller special-case it.
      return LocationSyncResult.empty;
    }

    final ApiResult result = await _api.post(
      '/tracking/locations',
      idempotencyKey: _batchKey(sessionId, fixes),
      body: <String, dynamic>{
        'sessionId': sessionId,
        'fixes': fixes.map(_fixTuple).toList(growable: false),
      },
    );

    final Map<String, dynamic> data = result.map;

    return LocationSyncResult(
      accepted: (data['accepted'] as List<dynamic>? ?? <dynamic>[])
          .map((dynamic e) => e.toString())
          .toList(growable: false),
      rejected: Wire.maps(data['rejected'])
          .map((Map<String, dynamic> j) => RejectedFix(
                id: Wire.str(j['id']),
                reason: Wire.str(j['reason']),
              ))
          .toList(growable: false),
    );
  }

  @override
  Future<void> reportHealth({
    required LocationHealth health,
    bool isOnline = true,
    String? permission,
  }) async {
    await _api.post(
      '/tracking/health',
      body: <String, dynamic>{
        // Enums travel as the Dart enum's own name (API plan §1); the server
        // converts camelCase to its snake_case column value.
        'locationHealth': health.name,
        'isOnline': isOnline,
        if (permission != null) 'permission': permission,
      },
    );
  }

  // ------------------------------------------------------------- internals

  /// Object form, used by session open/close where a single fix rides along.
  /// `id` is the device's fix id and is what the server stores as `client_id`
  /// — omit it and the fix comes back as `MALFORMED`.
  Map<String, dynamic> _fixObject(LocationLog fix) => <String, dynamic>{
        'id': fix.id,
        'latitude': fix.latitude,
        'longitude': fix.longitude,
        'accuracy': fix.accuracy,
        'speedKmh': fix.speedKmh,
        'recordedAt': fix.recordedAt.toUtc().toIso8601String(),
      };

  /// Flat `[lat, lng, t, acc, spd, clientId]` tuple — the same shape §3.7
  /// delivers routes in, and roughly a 4x saving over objects on the highest
  /// volume payload in the system. `t` is epoch **seconds**, UTC.
  List<Object> _fixTuple(LocationLog fix) => <Object>[
        fix.latitude,
        fix.longitude,
        fix.recordedAt.toUtc().millisecondsSinceEpoch ~/ 1000,
        fix.accuracy,
        fix.speedKmh,
        fix.id,
      ];

  /// Deterministic per batch, so a retry of the *same* batch replays the
  /// original response instead of being re-evaluated.
  ///
  /// Worth stating plainly: `TrackingController.php` does not currently route
  /// these writes through `V1Controller::idempotent()`, so the header is
  /// accepted and ignored. Retries are still safe, because the fix table has a
  /// unique `(employee_id, client_id)` index and a replay comes back as
  /// `DUPLICATE` rather than a second row. The header is sent anyway: it costs
  /// nothing and it is what the plan (§1.5) asks for.
  String _batchKey(String sessionId, List<LocationLog> fixes) =>
      '$sessionId:${fixes.first.id}:${fixes.last.id}:${fixes.length}';

  /// The server accepts `pause` or `end` only.
  ///
  /// [SessionEndReason.auto] — the watchdog's close, when GPS and network have
  /// both been gone too long — maps to `pause`, because that is what it means:
  /// the session stops but the **day stays open**, and the employee may start
  /// another session when they are back. Sending `end` would tell the server
  /// the opposite.
  String _reason(SessionEndReason reason) => switch (reason) {
        SessionEndReason.manual => 'end',
        SessionEndReason.pause || SessionEndReason.auto => 'pause',
      };
}

/// The `USE_MOCKS=true` implementation: accepts everything, sends nothing.
///
/// It is a no-op, not a simulator. The offline build has no server to open a
/// session against, so the device's own id **is** the session id — returning
/// it unchanged keeps the id mapping in [TrackingController] an identity and
/// leaves the mock build behaving exactly as it did before the write path
/// existed.
///
/// Note what it does *not* do: it never reports fixes as accepted. Locations
/// stay in the device queue, which is the truth — nothing uploaded them.
class NoopTrackingRepository implements TrackingRepository {
  const NoopTrackingRepository();

  @override
  Future<WorkSession> openSession({
    required String clientId,
    required LocationLog fix,
    DateTime? startedAt,
  }) async {
    final DateTime start = startedAt ?? fix.recordedAt;

    return WorkSession(
      id: clientId,
      index: 1,
      startTime: start,
      endTime: null,
      distanceKm: 0,
      locationPoints: 1,
      startLatitude: fix.latitude,
      startLongitude: fix.longitude,
    );
  }

  @override
  Future<WorkSession> closeSession({
    required String sessionId,
    required SessionEndReason reason,
    LocationLog? fix,
    DateTime? endedAt,
  }) async {
    final DateTime end = endedAt ?? fix?.recordedAt ?? DateTime.now();

    return WorkSession(
      id: sessionId,
      index: 1,
      startTime: end,
      endTime: end,
      distanceKm: 0,
      locationPoints: 0,
      startLatitude: fix?.latitude ?? 0,
      startLongitude: fix?.longitude ?? 0,
      endReason: reason,
    );
  }

  @override
  Future<LocationSyncResult> pushLocations({
    required String sessionId,
    required List<LocationLog> fixes,
  }) async =>
      LocationSyncResult.empty;

  @override
  Future<void> reportHealth({
    required LocationHealth health,
    bool isOnline = true,
    String? permission,
  }) async {}
}
