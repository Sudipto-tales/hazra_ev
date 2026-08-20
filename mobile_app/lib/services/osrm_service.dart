import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';

import '../core/config/map_config.dart';
import '../data/models/models.dart';

/// Snaps a raw GPS trace onto the road network with OSRM's map-matching
/// service, so the admin route map draws the streets the employee actually
/// drove instead of straight lines between fixes.
///
/// **`/match`, not `/route`.** `/route` answers "what is the fastest way from
/// A to B" and will happily invent a motorway nobody took. `/match` is given
/// the whole trace and returns the road path that best explains it — which is
/// the question this screen asks.
///
/// Every failure path returns `null` rather than throwing: an unreachable or
/// rate-limited OSRM host must degrade to the raw polyline, never to an error
/// screen. The demo server has no SLA, so this is the common case, not the
/// exotic one.
class OsrmService {
  OsrmService({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  /// Snapped geometry keyed by trace signature. A day's route is immutable
  /// once recorded, so scrubbing the date back and forth must not re-hit a
  /// rate-limited public server.
  final Map<String, List<LatLng>> _cache = <String, List<LatLng>>{};

  void dispose() => _client.close();

  /// Snaps every session segment independently.
  ///
  /// Segments exist because a gap between two sessions is not travel — joining
  /// them would ask OSRM to route across a lunch break and draw a road that was
  /// never driven. A segment that fails to match falls back to its own raw
  /// points, so one bad segment never blanks the whole day.
  Future<List<List<LatLng>>> snapTrack(List<List<LocationLog>> segments) async {
    final List<List<LatLng>> out = <List<LatLng>>[];
    for (final List<LocationLog> segment in segments) {
      final List<LatLng> raw = segment
          .map((LocationLog p) => LatLng(p.latitude, p.longitude))
          .toList(growable: false);
      if (raw.length < 2) {
        out.add(raw);
        continue;
      }
      out.add(await snapSegment(segment) ?? raw);
    }
    return out;
  }

  /// Returns the snapped geometry for one segment, or `null` if OSRM could not
  /// be reached, refused the request, or matched nothing usable.
  Future<List<LatLng>?> snapSegment(List<LocationLog> points) async {
    if (!MapConfig.snapToRoads || points.length < 2) return null;

    final String key = _cacheKey(points);
    final List<LatLng>? hit = _cache[key];
    if (hit != null) return hit;

    final List<LocationLog> thinned = _thin(points);
    if (thinned.length < 2) return null;

    final List<LatLng> merged = <LatLng>[];
    for (final List<LocationLog> chunk in _chunk(thinned)) {
      final List<LatLng>? part = await _match(chunk);
      // A chunk that fails poisons the whole segment: stitching a snapped
      // half onto a raw half produces a visible seam that reads as a bug.
      if (part == null) return null;
      _append(merged, part);
    }

    if (merged.length < 2) return null;
    _cache[key] = merged;
    return merged;
  }

  Future<List<LatLng>?> _match(List<LocationLog> chunk) async {
    final String coords = chunk
        .map((LocationLog p) =>
            '${_num(p.longitude)},${_num(p.latitude)}')
        .join(';');
    final String radiuses = chunk
        .map((LocationLog p) => _num(
              p.accuracy.clamp(
                MapConfig.minMatchRadiusMetres,
                MapConfig.maxMatchRadiusMetres,
              ),
            ))
        .join(';');

    final Uri uri = Uri.parse('${MapConfig.osrmMatchBase}/$coords').replace(
      queryParameters: <String, String>{
        'geometries': 'geojson',
        'overview': 'full',
        'radiuses': radiuses,
        // Collapses the near-duplicate fixes a stationary phone emits, which
        // otherwise make the matcher spin in place.
        'tidy': 'true',
        // A tracking trace legitimately has holes (tunnel, dead battery,
        // offline queue). Without this OSRM refuses the whole request.
        'gaps': 'ignore',
        'annotations': 'false',
      },
    );

    try {
      final http.Response res =
          await _client.get(uri).timeout(MapConfig.osrmTimeout);
      if (res.statusCode != 200) return null;

      final Object? decoded = jsonDecode(res.body);
      if (decoded is! Map<String, dynamic>) return null;
      if (decoded['code'] != 'Ok') return null;

      final Object? matchings = decoded['matchings'];
      if (matchings is! List || matchings.isEmpty) return null;

      // `gaps=ignore` can split one trace into several matchings. They come
      // back in trace order, so concatenating them is correct.
      final List<LatLng> out = <LatLng>[];
      for (final Object? m in matchings) {
        if (m is! Map<String, dynamic>) continue;
        final Object? geometry = m['geometry'];
        if (geometry is! Map<String, dynamic>) continue;
        final Object? coordinates = geometry['coordinates'];
        if (coordinates is! List) continue;
        _append(out, _decodeGeoJson(coordinates));
      }
      return out.length < 2 ? null : out;
    } on TimeoutException {
      return null;
    } catch (_) {
      // Socket, DNS, TLS, malformed JSON — all the same answer to the caller.
      return null;
    }
  }

  /// GeoJSON is `[longitude, latitude]`. Getting this backwards puts the whole
  /// route in the Indian Ocean, so it is decoded in exactly one place.
  List<LatLng> _decodeGeoJson(List<Object?> coordinates) {
    final List<LatLng> out = <LatLng>[];
    for (final Object? c in coordinates) {
      if (c is! List || c.length < 2) continue;
      final Object? lng = c[0];
      final Object? lat = c[1];
      if (lng is! num || lat is! num) continue;
      out.add(LatLng(lat.toDouble(), lng.toDouble()));
    }
    return out;
  }

  /// Appends [addition] to [target], dropping a repeated join point so the
  /// chunk seam does not leave a zero-length dot in the polyline.
  void _append(List<LatLng> target, List<LatLng> addition) {
    if (addition.isEmpty) return;
    if (target.isNotEmpty &&
        _metresBetween(target.last, addition.first) < 1) {
      target.addAll(addition.skip(1));
      return;
    }
    target.addAll(addition);
  }

  /// Drops fixes closer than [MapConfig.minPointSpacingMetres] to the last one
  /// kept. First and last always survive, so the segment keeps its endpoints.
  List<LocationLog> _thin(List<LocationLog> points) {
    final List<LocationLog> out = <LocationLog>[points.first];
    for (int i = 1; i < points.length - 1; i++) {
      final LocationLog p = points[i];
      final LocationLog last = out.last;
      final double d = _metresBetween(
        LatLng(last.latitude, last.longitude),
        LatLng(p.latitude, p.longitude),
      );
      if (d >= MapConfig.minPointSpacingMetres) out.add(p);
    }
    out.add(points.last);
    return out;
  }

  /// Splits into requests OSRM will accept, overlapping by one coordinate so
  /// consecutive chunks meet on the same road rather than at a guessed point.
  List<List<LocationLog>> _chunk(List<LocationLog> points) {
    const int limit = MapConfig.osrmMaxCoordinates;
    if (points.length <= limit) return <List<LocationLog>>[points];

    final List<List<LocationLog>> out = <List<LocationLog>>[];
    int start = 0;
    while (start < points.length - 1) {
      final int end = math.min(start + limit, points.length);
      out.add(points.sublist(start, end));
      // -1 is the overlap. Without it every chunk boundary is a fresh match
      // with no context and the two halves rarely line up.
      start = end - 1;
    }
    return out;
  }

  String _cacheKey(List<LocationLog> points) =>
      '${points.first.id}|${points.last.id}|${points.length}';

  /// Six decimals is ~11 cm — more than GPS resolves, and it keeps the URL
  /// inside what the demo server accepts.
  String _num(double v) => v.toStringAsFixed(6);
}

/// Great-circle distance in metres.
double _metresBetween(LatLng a, LatLng b) {
  const double r = 6371008.8;
  final double dLat = _rad(b.latitude - a.latitude);
  final double dLng = _rad(b.longitude - a.longitude);
  final double h = math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(_rad(a.latitude)) *
          math.cos(_rad(b.latitude)) *
          math.sin(dLng / 2) *
          math.sin(dLng / 2);
  return 2 * r * math.asin(math.min(1, math.sqrt(h)));
}

double _rad(double deg) => deg * math.pi / 180;
