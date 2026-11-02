import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../data/models/models.dart';

/// The durable form of the offline location queue.
///
/// Holds the fixes still owed to the server plus the two counters the status
/// sheet renders from — [failed] (fixes permanently lost to capacity overflow)
/// and [lastSyncedAt]. Everything else about the queue (batching, parking,
/// capacity) is runtime state and is deliberately *not* persisted.
@immutable
class LocationQueueData {
  const LocationQueueData({
    this.fixes = const <LocationLog>[],
    this.failed = 0,
    this.lastSyncedAt,
  });

  final List<LocationLog> fixes;
  final int failed;
  final DateTime? lastSyncedAt;

  bool get isEmpty => fixes.isEmpty && failed == 0 && lastSyncedAt == null;

  Map<String, dynamic> toJson() => <String, dynamic>{
        'fixes': fixes.map((LocationLog f) => f.toJson()).toList(growable: false),
        'failed': failed,
        'lastSyncedAt': lastSyncedAt?.toUtc().toIso8601String(),
      };

  static LocationQueueData fromJson(Map<String, dynamic> json) {
    final List<dynamic> raw =
        json['fixes'] as List<dynamic>? ?? const <dynamic>[];
    return LocationQueueData(
      fixes: raw
          .map((dynamic e) =>
              LocationLog.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(growable: false),
      failed: (json['failed'] as num?)?.toInt() ?? 0,
      lastSyncedAt: json['lastSyncedAt'] == null
          ? null
          : DateTime.parse(json['lastSyncedAt'] as String),
    );
  }
}

/// Durable backing for `GeoLocationService`'s offline queue.
///
/// Abstracted so the service can be unit-tested against an in-memory fake
/// without binding the `shared_preferences` plugin, and so a heavier local DB
/// could replace the implementation later without touching the service.
abstract class LocationQueueStore {
  Future<LocationQueueData> load();
  Future<void> save(LocationQueueData data);
  Future<void> clear();
}

/// `shared_preferences`-backed store — the production implementation.
///
/// Why `shared_preferences` and not a local DB: it is already a dependency, the
/// queue is a single bounded blob (≤ 5000 small fixes, a few hundred KB at the
/// cap) read once on launch and written on a debounce, and the access pattern
/// is "replace the whole blob", not "query rows". A DB would buy indexing and
/// partial writes this workload never needs. The service debounces writes so a
/// burst of fixes (or a batch drain) collapses into one encode + one write
/// rather than one per fix.
class SharedPrefsLocationQueueStore implements LocationQueueStore {
  SharedPrefsLocationQueueStore({Future<SharedPreferences>? prefs})
      : _prefs = prefs ?? SharedPreferences.getInstance();

  static const String _key = 'tracking.location_queue.v1';

  final Future<SharedPreferences> _prefs;

  @override
  Future<LocationQueueData> load() async {
    try {
      final SharedPreferences prefs = await _prefs;
      final String? raw = prefs.getString(_key);
      if (raw == null) return const LocationQueueData();
      return LocationQueueData.fromJson(
        jsonDecode(raw) as Map<String, dynamic>,
      );
    } catch (e) {
      // A blob from an older shape, or a corrupt one, is worth nothing. Drop it
      // rather than crash on launch — losing an unreadable queue is strictly
      // better than refusing to start a day.
      debugPrint('[location] queue load failed, discarding: $e');
      try {
        (await _prefs).remove(_key);
      } catch (_) {}
      return const LocationQueueData();
    }
  }

  @override
  Future<void> save(LocationQueueData data) async {
    final SharedPreferences prefs = await _prefs;
    await prefs.setString(_key, jsonEncode(data.toJson()));
  }

  @override
  Future<void> clear() async {
    final SharedPreferences prefs = await _prefs;
    await prefs.remove(_key);
  }
}
