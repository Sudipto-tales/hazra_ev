import 'package:employeetracking_mobile_app/core/config/tracking_config.dart';
import 'package:employeetracking_mobile_app/data/api/wire.dart';
import 'package:employeetracking_mobile_app/data/repositories/mock_employee_repository.dart';
import 'package:employeetracking_mobile_app/services/location_service.dart';
import 'package:employeetracking_mobile_app/state/tracking_controller.dart';
import 'package:flutter_test/flutter_test.dart';

/// P1.2: tracking thresholds are server-driven, not hardcoded.
///
/// The controller re-reads `GET /config` at login/resume and at each session
/// start and pushes the result into the live stack; a fetch failure must leave
/// the last good config (or [TrackingConfig.defaults]) in force so tracking
/// never blocks on the network.

/// Serves a fixed config, or fails, on the `config()` route only. Everything
/// else is the real mock repository, so `load()` behaves normally.
class _ConfigRepo extends MockEmployeeRepository {
  _ConfigRepo({this.served, this.fail = false})
      : super(latency: Duration.zero);

  final TrackingConfig? served;
  final bool fail;

  @override
  Future<TrackingConfig> config() async {
    if (fail) throw StateError('config endpoint down');
    return served ?? TrackingConfig.defaults;
  }
}

void main() {
  // A config that differs from the defaults in every field the live stack
  // actually reads, so adoption is unambiguous.
  const TrackingConfig serverConfig = TrackingConfig(
    locationIntervalSeconds: 90,
    minAccuracyMetres: 25,
    movementSpeedThresholdKmh: 5,
    syncBatchSize: 20,
    autoCloseAfterMinutes: 45,
  );

  Future<void> settle() =>
      Future<void>.delayed(const Duration(milliseconds: 20));

  test('applyConfig propagates to both the controller and the location service',
      () {
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final TrackingController tracking = TrackingController(
      repository: _ConfigRepo(),
      locationService: location,
    );
    addTearDown(() {
      tracking.dispose();
      location.dispose();
    });

    expect(tracking.config.locationIntervalSeconds,
        TrackingConfig.defaults.locationIntervalSeconds);

    tracking.applyConfig(serverConfig);

    expect(tracking.config.locationIntervalSeconds, 90);
    expect(location.config.locationIntervalSeconds, 90,
        reason: 'the service must receive the same config the controller holds');
    expect(location.config.syncBatchSize, 20);
  });

  test('load() adopts the server-provided config for the live stack', () async {
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final TrackingController tracking = TrackingController(
      repository: _ConfigRepo(served: serverConfig),
      locationService: location,
    );
    addTearDown(() {
      tracking.dispose();
      location.dispose();
    });

    await tracking.load();
    await settle();

    expect(tracking.config.locationIntervalSeconds, 90);
    expect(tracking.config.movementSpeedThresholdKmh, 5);
    expect(location.config.locationIntervalSeconds, 90,
        reason: 'server config must reach the GPS stream before it subscribes');
  });

  test('a failed config fetch keeps the defaults in force', () async {
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final TrackingController tracking = TrackingController(
      repository: _ConfigRepo(fail: true),
      locationService: location,
    );
    addTearDown(() {
      tracking.dispose();
      location.dispose();
    });

    // Must not throw, and must not clear the config to anything unusable.
    await tracking.load();
    await settle();

    expect(tracking.config.locationIntervalSeconds,
        TrackingConfig.defaults.locationIntervalSeconds);
    expect(location.config.locationIntervalSeconds,
        TrackingConfig.defaults.locationIntervalSeconds);
  });

  test('config decode leaves autoCloseAfterMinutes at the default', () {
    // The server sends 10 fields and never autoCloseAfterMinutes, so adopting a
    // server config must not reset it away from the local default.
    final MockLocationService location =
        MockLocationService(emitInterval: const Duration(minutes: 5));
    final TrackingController tracking = TrackingController(
      repository: _ConfigRepo(),
      locationService: location,
    );
    addTearDown(() {
      tracking.dispose();
      location.dispose();
    });

    // A config as Wire.config would build it: autoCloseAfterMinutes omitted, so
    // it falls to the TrackingConfig default rather than being zeroed.
    const TrackingConfig decoded = TrackingConfig(locationIntervalSeconds: 60);
    tracking.applyConfig(decoded);

    expect(tracking.config.autoCloseAfterMinutes,
        TrackingConfig.defaults.autoCloseAfterMinutes);
  });

  group('Wire.config version 8', () {
    test('parses syncIntervalSeconds and distanceFilterMetres from a v8 payload',
        () {
      final TrackingConfig decoded = Wire.config(<String, dynamic>{
        'locationIntervalSeconds': 60,
        'distanceFilterMetres': 50,
        'syncBatchSize': 50,
        'syncIntervalSeconds': 120,
        'version': 8,
      });

      expect(decoded.distanceFilterMetres, 50);
      expect(decoded.syncIntervalSeconds, 120);
    });

    test('a v7 payload missing the new fields falls back to the local defaults',
        () {
      // A server still on version 7 sends neither field; the parser must not
      // null-out or zero the drain interval / distance filter.
      final TrackingConfig decoded = Wire.config(<String, dynamic>{
        'locationIntervalSeconds': 60,
        'syncBatchSize': 50,
        'version': 7,
      });

      expect(decoded.syncIntervalSeconds,
          TrackingConfig.defaults.syncIntervalSeconds); // 300
      expect(decoded.distanceFilterMetres,
          TrackingConfig.defaults.distanceFilterMetres); // 20
    });
  });
}
