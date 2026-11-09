import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/street_view.dart';
import '../../../data/models/models.dart' hide LatLngBounds;
import '../../../services/osrm_service.dart';
import 'map_chrome.dart';
import 'route_accuracy.dart';
import 'route_painter.dart' show RouteMapColors;
import 'trail_arrows.dart';

/// One colour per work session, so a day split by a lunch break reads as two
/// legs rather than one line that mysteriously teleports. Wraps after five —
/// nobody opens six sessions in a day, and if they do, repetition beats
/// inventing unreadable colours.
const List<Color> kSessionPalette = <Color>[
  AppColors.primary,
  AppColors.warning,
  AppColors.danger,
  AppColors.success,
  AppColors.info,
];

Color sessionColor(int index) =>
    kSessionPalette[index % kSessionPalette.length];

/// The raw GPS trace, cut into runs of equal confidence.
///
/// Pure so the rendering *decision* — which stretch gets the rough treatment —
/// can be asserted on without a map, tiles or a network.
///
/// A rough run keeps the session's colour and loses everything that made the
/// line look authoritative: the white casing, most of the weight, and the solid
/// stroke. It is dotted rather than dashed so it never reads as the dashed
/// "raw trace under the road match" line, which is a different statement.
///
/// [underSnap] is true when a road-matched line is drawn on top, in which case
/// the whole raw trace is already a reference rather than the route.
@visibleForTesting
List<Polyline> rawTracePolylines({
  required List<List<LocationLog>> segments,
  required RouteAccuracy accuracy,
  required bool underSnap,
}) {
  final List<Polyline> out = <Polyline>[];

  for (int i = 0; i < segments.length; i++) {
    final Color c = sessionColor(i);
    for (final AccuracyRun run in accuracy.runs(segments[i])) {
      if (run.points.length < 2) continue;
      final List<LatLng> points = run.points
          .map((LocationLog p) => LatLng(p.latitude, p.longitude))
          .toList(growable: false);

      out.add(
        Polyline(
          points: points,
          color: run.rough
              ? c.withValues(alpha: underSnap ? 0.22 : 0.5)
              : (underSnap ? c.withValues(alpha: 0.35) : c),
          strokeWidth: run.rough
              ? (underSnap ? 2 : 3)
              : (underSnap ? 2 : 5),
          borderColor: run.rough || underSnap ? Colors.transparent : Colors.white,
          borderStrokeWidth: run.rough || underSnap ? 0 : 2,
          pattern: run.rough
              ? const StrokePattern.dotted(spacingFactor: 2.2)
              : (underSnap
                  ? StrokePattern.dashed(segments: const <double>[6, 5])
                  : const StrokePattern.solid()),
        ),
      );
    }
  }
  return out;
}

/// The route drawn on a real OpenStreetMap basemap, with the GPS trace snapped
/// to the road network.
///
/// Two polylines are drawn on purpose: the faint one is the raw trace as the
/// handset recorded it, the solid one is the road match. Seeing both is how an
/// admin tells "the employee drove down a side street" apart from "the matcher
/// guessed". When snapping is off or fails, only the raw trace shows.
///
/// The match comes from the server when it has one — matched once when the day
/// closed, cached, and sent as polyline6 beside the trace. Every device that
/// opens that day then draws the same roads without any of them touching a
/// matching host. [OsrmService] is the fallback for the cases the server
/// cannot cover: today's still-open route, and a payload from a server with
/// matching turned off.
///
/// Tiles come from the network, so this widget can fail in a way the painted
/// fallback cannot. It reports that through [onTilesUnavailable] rather than
/// handling it itself — the parent owns which renderer is on screen.
class RouteTileMap extends StatefulWidget {
  const RouteTileMap({
    super.key,
    required this.track,
    required this.config,
    required this.accuracy,
    required this.selectedId,
    required this.onSelect,
    required this.onTilesUnavailable,
    this.height = 340,
    this.fullScreen = false,
  });

  final RouteTrack track;
  final TrackingConfig config;

  /// Read-time GPS-quality verdict for [track], derived by the screen against
  /// the config it loaded. See [RouteAccuracy].
  final RouteAccuracy accuracy;
  final String? selectedId;
  final ValueChanged<String?> onSelect;
  final VoidCallback onTilesUnavailable;
  final double height;

  /// Filling the screen changes two things: one-finger drag becomes a pan
  /// (there is no list underneath to steal it), and the expand button turns
  /// into a close button.
  final bool fullScreen;

  @override
  State<RouteTileMap> createState() => _RouteTileMapState();
}

class _RouteTileMapState extends State<RouteTileMap> {
  final MapController _map = MapController();
  final OsrmService _osrm = OsrmService();

  /// Tracked coarsely (whole zoom levels) so direction arrows re-space when the
  /// admin zooms a level, without rebuilding the whole map on every pinch pixel.
  double _zoom = 15;
  int _zoomStep = 15;

  List<List<LatLng>>? _snapped;
  bool _snapping = false;

  /// True when [_snapped] came from the server rather than a device-side OSRM
  /// call. The drawn line is identical either way; what differs is that the
  /// server knows which sessions it failed on and says so.
  bool _snappedByServer = false;

  /// Tile health. The fallback only fires when nothing at all has rendered —
  /// a handful of 404s at the edge of coverage is not an outage.
  int _tileErrors = 0;
  bool _anyTileLoaded = false;
  bool _reportedOffline = false;

  @override
  void initState() {
    super.initState();
    _snap();
  }

  @override
  void didUpdateWidget(RouteTileMap oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.track, widget.track)) {
      _snapped = null;
      _snappedByServer = false;
      _snap();
    }
  }

  @override
  void dispose() {
    _osrm.dispose();
    _map.dispose();
    super.dispose();
  }

  Future<void> _snap() async {
    final List<List<LatLng>>? served = _servedSegments();
    if (served != null) {
      setState(() {
        _snapped = served;
        _snappedByServer = true;
        _snapping = false;
      });
      return;
    }

    if (!MapConfig.snapToRoads) return;
    setState(() => _snapping = true);
    final List<List<LatLng>> result =
        await _osrm.snapTrack(widget.track.segments);
    if (!mounted) return;
    setState(() {
      _snapped = result;
      _snappedByServer = false;
      _snapping = false;
    });
  }

  /// The server's matched geometry, aligned index-for-index with
  /// [_rawSegments] so a session keeps its colour in both lines.
  ///
  /// A session the server could not match keeps its raw points here rather than
  /// being dropped: the alignment is what [_polylines] indexes on, and a hole in
  /// it would recolour every session after the gap.
  List<List<LatLng>>? _servedSegments() {
    final MatchedRoute? matched = widget.track.matched;
    if (matched == null || !matched.isUsable) return null;

    final List<List<LocationLog>> raw = widget.track.segments;
    final List<WorkSession> sessions = widget.track.sessions;

    final Map<String, int> indexById = <String, int>{
      for (final WorkSession s in sessions) s.id: s.index,
    };

    final List<List<LatLng>> out = <List<LatLng>>[];
    bool any = false;

    for (final List<LocationLog> segment in raw) {
      final int index =
          segment.isEmpty ? -1 : (indexById[segment.first.sessionId] ?? -1);
      final List<GeoPoint>? points =
          index < 0 ? null : matched.pointsForSession(index);

      if (points == null) {
        out.add(segment
            .map((LocationLog p) => LatLng(p.latitude, p.longitude))
            .toList(growable: false));
        continue;
      }

      any = true;
      out.add(points
          .map((GeoPoint p) => LatLng(p.latitude, p.longitude))
          .toList(growable: false));
    }

    return any ? out : null;
  }

  List<List<LatLng>> get _rawSegments => widget.track.segments
      .map((List<LocationLog> s) => s
          .map((LocationLog p) => LatLng(p.latitude, p.longitude))
          .toList(growable: false))
      .toList(growable: false);

  /// True when OSRM actually moved the line. Used to decide whether showing
  /// both polylines is informative or just doubles the ink.
  bool get _hasSnap {
    final List<List<LatLng>>? snapped = _snapped;
    if (snapped == null) return false;
    final List<List<LatLng>> raw = _rawSegments;
    for (int i = 0; i < snapped.length && i < raw.length; i++) {
      if (snapped[i].length != raw[i].length) return true;
    }
    return false;
  }

  /// The caveat the solid line needs, or null when it needs none.
  ///
  /// A partly-matched day is the case worth naming: the line is road-accurate
  /// for most of the day and a straight chord for one session, and without this
  /// there is nothing on screen that distinguishes the two.
  String? get _snapNotice {
    if (_snapped == null) return null;

    if (!_hasSnap) {
      return MapConfig.snapToRoads ? 'Raw GPS trace' : null;
    }

    final MatchedRoute? matched = widget.track.matched;

    return _snappedByServer && matched != null && matched.status == 'partial'
        ? 'Part of this day is raw GPS'
        : null;
  }

  LatLngBounds get _bounds =>
      LatLngBounds.fromPoints(_rawSegments.expand((List<LatLng> s) => s).toList());

  void _fit() => _map.fitCamera(
        CameraFit.bounds(
          bounds: _bounds,
          padding: const EdgeInsets.all(36),
          maxZoom: 17,
        ),
      );

  /// A map inside a scrolling list can only ever be a preview. This is the
  /// same widget with the full gesture set and the whole screen to use.
  Future<void> _openFullScreen() async {
    String? selected = widget.selectedId;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (BuildContext context) => Scaffold(
          appBar: AppBar(title: const Text('Route')),
          body: StatefulBuilder(
            builder: (BuildContext context, StateSetter setSheetState) =>
                RouteTileMap(
              track: widget.track,
              config: widget.config,
              accuracy: widget.accuracy,
              selectedId: selected,
              onSelect: (String? id) => setSheetState(() => selected = id),
              onTilesUnavailable: () {
                Navigator.of(context).pop();
                widget.onTilesUnavailable();
              },
              fullScreen: true,
            ),
          ),
        ),
      ),
    );
    // Carry the full-screen selection back so the detail card below the inline
    // map matches what the admin last tapped.
    if (mounted && selected != widget.selectedId) widget.onSelect(selected);
  }

  void _onTileError() {
    _tileErrors++;
    if (_anyTileLoaded || _reportedOffline || _tileErrors < 5) return;
    _reportedOffline = true;
    // Fired from inside the tile pipeline, so the parent's setState has to
    // wait for the current frame to finish.
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => mounted ? widget.onTilesUnavailable() : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final RouteMapColors colors = RouteMapColors.of(context);
    final bool dark = Theme.of(context).brightness == Brightness.dark;

    return SizedBox(
      height: widget.fullScreen ? double.infinity : widget.height,
      child: Stack(
        children: <Widget>[
          FlutterMap(
            mapController: _map,
            options: MapOptions(
              initialCameraFit: CameraFit.bounds(
                bounds: _bounds,
                padding: const EdgeInsets.all(36),
                maxZoom: 17,
              ),
              minZoom: MapConfig.minZoom,
              maxZoom: MapConfig.maxZoom,
              backgroundColor: colors.plate,
              // Tapping bare map dismisses the detail card.
              onTap: (_, __) => widget.onSelect(null),
              // Long-press any point on the route opens Street View there — no
              // key, no in-app panorama, just a hand-off to the Maps app. The
              // stop/visit callouts carry the same action discoverably.
              onLongPress: (_, LatLng p) =>
                  StreetView.open(context, p.latitude, p.longitude),
              onPositionChanged: (MapCamera camera, bool hasGesture) {
                _zoom = camera.zoom;
                final int step = camera.zoom.floor();
                if (step == _zoomStep) return;
                _zoomStep = step;
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (mounted) setState(() {});
                });
              },
              interactionOptions: InteractionOptions(
                // Inline, the map lives inside a scrolling list, so a
                // one-finger drag has to keep belonging to the list — two
                // fingers pan and zoom. Full screen there is no list to
                // protect, so drag behaves the way every map app behaves.
                flags: widget.fullScreen
                    ? InteractiveFlag.drag |
                        InteractiveFlag.flingAnimation |
                        InteractiveFlag.pinchZoom |
                        InteractiveFlag.pinchMove |
                        InteractiveFlag.doubleTapZoom |
                        InteractiveFlag.scrollWheelZoom
                    : InteractiveFlag.pinchZoom |
                        InteractiveFlag.pinchMove |
                        InteractiveFlag.doubleTapZoom |
                        InteractiveFlag.scrollWheelZoom,
              ),
            ),
            children: <Widget>[
              TileLayer(
                // Esri Light/Dark Gray Base, matched to the app theme,
                // or CARTO Voyager / Dark Matter when a key is defined — see
                // MapConfig for why neither is raw OSM.
                urlTemplate: MapConfig.tileUrlFor(dark: dark),
                userAgentPackageName: MapConfig.userAgentPackageName,
                maxNativeZoom: MapConfig.maxNativeZoom,
                maxZoom: MapConfig.maxZoom,
                tileProvider: NetworkTileProvider(),
                errorTileCallback: (_, __, ___) => _onTileError(),
                tileBuilder: (BuildContext _, Widget tile, TileImage image) {
                  if (!_anyTileLoaded && image.loadFinishedAt != null &&
                      !image.loadError) {
                    _anyTileLoaded = true;
                  }
                  return tile;
                },
              ),
              // Under the geofences and the route: a quality hint must never
              // sit on top of the things it is a hint about.
              if (widget.accuracy.hasRough)
                CircleLayer(circles: _accuracyHalos(colors)),
              CircleLayer(circles: _geofences(colors)),
              if (_unsynced.isNotEmpty)
                CircleLayer(circles: _unsyncedDots(colors)),
              PolylineLayer(polylines: _polylines(colors)),
              if (_zoom > 12) MarkerLayer(markers: _arrowMarkers()),
              MarkerLayer(markers: _markers(colors)),
              Scalebar(
                alignment: Alignment.bottomLeft,
                lineColor: colors.label,
                textStyle: TextStyle(color: colors.label, fontSize: 12),
                padding: const EdgeInsets.fromLTRB(
                  Insets.sm,
                  0,
                  0,
                  Insets.sm,
                ),
              ),
              SimpleAttributionWidget(
                source: Text(MapConfig.attributionText),
                backgroundColor: colors.plate.withValues(alpha: 0.8),
              ),
            ],
          ),
          Positioned(
            top: Insets.sm,
            right: Insets.sm,
            child: Column(
              children: <Widget>[
                MapIconButton(
                  tooltip: widget.fullScreen ? 'Close' : 'Full screen',
                  icon: widget.fullScreen
                      ? Icons.close_fullscreen_rounded
                      : Icons.open_in_full_rounded,
                  color: colors.plate,
                  onPressed: () => widget.fullScreen
                      ? Navigator.of(context).pop()
                      : _openFullScreen(),
                ),
                const SizedBox(height: Insets.xs),
                MapIconButton(
                  tooltip: 'Fit route',
                  icon: Icons.fit_screen_outlined,
                  color: colors.plate,
                  onPressed: _fit,
                ),
                const SizedBox(height: Insets.xs),
                MapIconButton(
                  tooltip: 'Painted view (offline)',
                  icon: Icons.grid_on_outlined,
                  color: colors.plate,
                  onPressed: widget.onTilesUnavailable,
                ),
              ],
            ),
          ),
          Positioned(
            top: Insets.sm,
            left: Insets.sm,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                if (_snapping)
                  MapPill(
                    background: colors.plate,
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        SizedBox(
                          width: 12,
                          height: 12,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        SizedBox(width: Insets.xs),
                        Text('Snapping to roads…'),
                      ],
                    ),
                  )
                else if (_snapNotice != null)
                  MapPill(
                    background: colors.plate,
                    child: Text(_snapNotice!),
                  ),
                // Named, not warned about: the dotted stretches and the soft
                // discs mean nothing unless something on screen says what
                // they are.
                if (widget.accuracy.hasRough) ...<Widget>[
                  const SizedBox(height: Insets.xs),
                  MapPill(
                    background: colors.plate,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(
                          Icons.blur_on_rounded,
                          size: 13,
                          color: colors.label,
                        ),
                        const SizedBox(width: Insets.xs),
                        Text(
                          'Dotted: GPS weaker than '
                          '${widget.accuracy.thresholdLabel}',
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------- layers

  Color _sessionColor(int index) => sessionColor(index);

  /// Direction chevrons along the drawn line — the snapped geometry when there
  /// is one, the raw trace otherwise — so a there-and-back on the same road
  /// reads as two passes. Spaced by ground distance and thinned with zoom (the
  /// layer is gated off below z12), and coloured per work session to match the
  /// line they ride. Subtle on purpose: the stop, visit, start and end pins
  /// stay the loudest things on the route.
  List<Marker> _arrowMarkers() {
    final List<List<LatLng>> segments =
        _hasSnap && _snapped != null ? _snapped! : _rawSegments;

    final List<Marker> out = <Marker>[];
    for (int i = 0; i < segments.length; i++) {
      final List<LatLng> pts = segments[i];
      if (pts.length < 2) continue;
      final Color color = _sessionColor(i);

      // Roughly constant on-screen spacing: metres-per-pixel at this zoom and
      // latitude times a target gap, so zooming out thins the arrows.
      final double mpp = 156543.03392 *
          math.cos(pts.first.latitude * math.pi / 180).abs() /
          math.pow(2, _zoom);
      final double spacingMetres = (mpp * 58).clamp(5, double.infinity);

      for (final TrailArrow arrow
          in arrowsAlongLatLng(pts, spacingMetres: spacingMetres)) {
        out.add(
          Marker(
            point: arrow.position,
            width: 10,
            height: 10,
            child: Transform.rotate(
              angle: arrow.bearingDeg * math.pi / 180,
              child: TrailArrowIcon(color: color, size: 10),
            ),
          ),
        );
      }
    }
    return out;
  }

  List<Polyline> _polylines(RouteMapColors colors) {
    final bool snapped = _hasSnap;
    final List<Polyline> out = rawTracePolylines(
      segments: widget.track.segments,
      accuracy: widget.accuracy,
      underSnap: snapped,
    );

    if (snapped) {
      final List<List<LatLng>> segments = _snapped!;
      for (int i = 0; i < segments.length; i++) {
        if (segments[i].length < 2) continue;
        out.add(
          Polyline(
            points: segments[i],
            color: _sessionColor(i),
            strokeWidth: 5,
            // A white casing is what keeps a coloured line legible over map
            // tiles, which are themselves full of coloured lines.
            borderColor: Colors.white,
            borderStrokeWidth: 2,
          ),
        );
      }
    }
    return out;
  }

  List<CircleMarker> _geofences(RouteMapColors colors) {
    final List<CircleMarker> out = <CircleMarker>[];
    final double radius = widget.config.stopRadiusMetres;

    for (final StopRecord s in widget.track.stops) {
      if (s.isLinked) continue;
      final bool long = s.durationAt(DateTime.now()).inMinutes >=
          widget.config.longStopThresholdMinutes;
      final Color c = long ? colors.longStop : colors.stop;
      final bool rough = widget.accuracy.stop(s).isRough;

      // A rough dwell gets a soft outer wash and a faint edge instead of a
      // crisp ring: the geofence is a guess here, and drawing it hard would
      // claim a precision the fixes never had.
      if (rough) {
        out.add(
          CircleMarker(
            point: LatLng(s.latitude, s.longitude),
            radius: radius * 1.35,
            useRadiusInMeter: true,
            color: c.withValues(alpha: 0.05),
            borderStrokeWidth: 0,
          ),
        );
      }
      out.add(
        CircleMarker(
          point: LatLng(s.latitude, s.longitude),
          radius: radius,
          useRadiusInMeter: true,
          color: c.withValues(alpha: rough ? 0.07 : 0.12),
          borderColor: c.withValues(alpha: rough ? 0.2 : 0.45),
          borderStrokeWidth: 1,
        ),
      );
    }
    for (final CompanyVisit v in widget.track.visits) {
      final bool rough = widget.accuracy.visit(v).isRough;
      if (rough) {
        out.add(
          CircleMarker(
            point: LatLng(v.latitude, v.longitude),
            radius: radius * 1.35,
            useRadiusInMeter: true,
            color: colors.visit.withValues(alpha: 0.05),
            borderStrokeWidth: 0,
          ),
        );
      }
      out.add(
        CircleMarker(
          point: LatLng(v.latitude, v.longitude),
          radius: radius,
          useRadiusInMeter: true,
          color: colors.visit.withValues(alpha: rough ? 0.07 : 0.12),
          borderColor: colors.visit.withValues(alpha: rough ? 0.2 : 0.45),
          borderStrokeWidth: 1,
        ),
      );
    }
    return out;
  }

  /// The uncertainty footprint of the rough fixes: one translucent disc per
  /// fix, sized to the accuracy the handset actually reported.
  ///
  /// This is the honest version of "rough stretch" on a basemap — the admin
  /// sees how wide the doubt is, in metres, against real streets. Subsampled
  /// because a weak-signal hour can be hundreds of fixes and the point is the
  /// shape of the doubt, not every disc in it.
  List<CircleMarker> _accuracyHalos(RouteMapColors colors) {
    final List<LocationLog> rough = widget.accuracy.roughFixes;
    if (rough.isEmpty) return const <CircleMarker>[];

    const int budget = 60;
    final int step = rough.length <= budget ? 1 : (rough.length / budget).ceil();

    final List<CircleMarker> out = <CircleMarker>[];
    for (int i = 0; i < rough.length; i += step) {
      final LocationLog p = rough[i];
      out.add(
        CircleMarker(
          point: LatLng(p.latitude, p.longitude),
          // Fall back to the threshold when the fix carried no metre value:
          // the device flagged it, so the doubt is at least that wide.
          radius: p.accuracy > 0 ? p.accuracy : widget.accuracy.thresholdMetres,
          useRadiusInMeter: true,
          color: colors.stroke.withValues(alpha: 0.06),
          borderStrokeWidth: 0,
        ),
      );
    }
    return out;
  }

  List<LocationLog> get _unsynced => widget.track.points
      .where((LocationLog p) => p.syncState != SyncState.synced)
      .toList(growable: false);

  /// Fixes still sitting in the handset's offline queue. Drawn as pixel-sized
  /// dots so a long unsynced stretch is visible without competing with the
  /// route itself.
  List<CircleMarker> _unsyncedDots(RouteMapColors colors) => _unsynced
      .map(
        (LocationLog p) => CircleMarker(
          point: LatLng(p.latitude, p.longitude),
          radius: 2.5,
          color: colors.queued,
        ),
      )
      .toList(growable: false);

  List<Marker> _markers(RouteMapColors colors) {
    final List<Marker> out = <Marker>[];
    final DateTime now = DateTime.now();

    for (final StopRecord s in widget.track.stops) {
      if (s.isLinked) continue;
      final bool long =
          s.durationAt(now).inMinutes >= widget.config.longStopThresholdMinutes;
      out.add(
        _pin(
          id: s.id,
          point: LatLng(s.latitude, s.longitude),
          color: long ? colors.longStop : colors.stop,
          icon: Icons.pause_rounded,
          size: 30,
          colors: colors,
        ),
      );
    }
    for (final CompanyVisit v in widget.track.visits) {
      out.add(
        _pin(
          id: v.id,
          point: LatLng(v.latitude, v.longitude),
          color: colors.visit,
          icon: Icons.business_rounded,
          size: 36,
          colors: colors,
        ),
      );
    }

    final List<LocationLog> points = widget.track.points;
    if (points.isNotEmpty) {
      out.insert(
        0,
        _pin(
          id: null,
          point: LatLng(points.first.latitude, points.first.longitude),
          color: colors.start,
          icon: Icons.play_arrow_rounded,
          size: 30,
          colors: colors,
        ),
      );
      out.add(
        _pin(
          id: null,
          point: LatLng(points.last.latitude, points.last.longitude),
          color: colors.end,
          icon: Icons.flag_rounded,
          size: 30,
          colors: colors,
        ),
      );
    }
    return out;
  }

  Marker _pin({
    required String? id,
    required LatLng point,
    required Color color,
    required IconData icon,
    required double size,
    required RouteMapColors colors,
  }) {
    final bool selected = id != null && id == widget.selectedId;
    final double width = size;
    final double height = size * 1.32;
    return Marker(
      point: point,
      width: width,
      height: height,
      // The pin's tip is the coordinate, not its middle — without this every
      // marker sits half a pin north of where the employee actually stood.
      alignment: Alignment.topCenter,
      child: GestureDetector(
        onTap: id == null ? null : () => widget.onSelect(selected ? null : id),
        child: MapPin(
          color: color,
          icon: icon,
          width: width,
          height: height,
          borderColor: selected ? colors.selection : Colors.white,
          borderWidth: selected ? 3 : 2,
        ),
      ),
    );
  }
}
