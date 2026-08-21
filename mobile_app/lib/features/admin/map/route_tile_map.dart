import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart' hide LatLngBounds;
import '../../../services/osrm_service.dart';
import 'route_painter.dart' show RouteMapColors;

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
    required this.selectedId,
    required this.onSelect,
    required this.onTilesUnavailable,
    this.height = 340,
    this.fullScreen = false,
  });

  final RouteTrack track;
  final TrackingConfig config;
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
                urlTemplate: MapConfig.tileUrlTemplate,
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
              CircleLayer(circles: _geofences(colors)),
              if (_unsynced.isNotEmpty)
                CircleLayer(circles: _unsyncedDots(colors)),
              PolylineLayer(polylines: _polylines(colors)),
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
                source: const Text(MapConfig.attributionText),
                backgroundColor: colors.plate.withValues(alpha: 0.8),
              ),
            ],
          ),
          Positioned(
            top: Insets.sm,
            right: Insets.sm,
            child: Column(
              children: <Widget>[
                _MapButton(
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
                _MapButton(
                  tooltip: 'Fit route',
                  icon: Icons.fit_screen_outlined,
                  color: colors.plate,
                  onPressed: _fit,
                ),
                const SizedBox(height: Insets.xs),
                _MapButton(
                  tooltip: 'Painted view (offline)',
                  icon: Icons.grid_on_outlined,
                  color: colors.plate,
                  onPressed: widget.onTilesUnavailable,
                ),
              ],
            ),
          ),
          if (_snapping)
            Positioned(
              top: Insets.sm,
              left: Insets.sm,
              child: _Pill(
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
              ),
            )
          else if (_snapNotice != null)
            Positioned(
              top: Insets.sm,
              left: Insets.sm,
              child: _Pill(
                background: colors.plate,
                child: Text(_snapNotice!),
              ),
            ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------- layers

  /// One colour per work session, so a day split by a lunch break reads as two
  /// legs rather than one line that mysteriously teleports. Wraps after five —
  /// nobody opens six sessions in a day, and if they do, repetition beats
  /// inventing unreadable colours.
  static const List<Color> _sessionPalette = <Color>[
    AppColors.primary,
    AppColors.warning,
    AppColors.danger,
    AppColors.success,
    AppColors.info,
  ];

  Color _sessionColor(int index) =>
      _sessionPalette[index % _sessionPalette.length];

  List<Polyline> _polylines(RouteMapColors colors) {
    final List<Polyline> out = <Polyline>[];
    final List<List<LatLng>> raw = _rawSegments;
    final bool snapped = _hasSnap;

    for (int i = 0; i < raw.length; i++) {
      final List<LatLng> segment = raw[i];
      if (segment.length < 2) continue;
      final Color c = _sessionColor(i);
      out.add(
        Polyline(
          points: segment,
          // Underneath the snapped line it is a reference, on its own it is
          // the route — so it changes weight rather than disappearing.
          color: snapped ? c.withValues(alpha: 0.35) : c,
          strokeWidth: snapped ? 2 : 5,
          borderColor: snapped ? Colors.transparent : Colors.white,
          borderStrokeWidth: snapped ? 0 : 2,
          pattern: snapped
              ? StrokePattern.dashed(segments: const <double>[6, 5])
              : const StrokePattern.solid(),
        ),
      );
    }

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
      out.add(
        CircleMarker(
          point: LatLng(s.latitude, s.longitude),
          radius: radius,
          useRadiusInMeter: true,
          color: c.withValues(alpha: 0.12),
          borderColor: c.withValues(alpha: 0.45),
          borderStrokeWidth: 1,
        ),
      );
    }
    for (final CompanyVisit v in widget.track.visits) {
      out.add(
        CircleMarker(
          point: LatLng(v.latitude, v.longitude),
          radius: radius,
          useRadiusInMeter: true,
          color: colors.visit.withValues(alpha: 0.12),
          borderColor: colors.visit.withValues(alpha: 0.45),
          borderStrokeWidth: 1,
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
        child: _MapPin(
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

/// The classic map teardrop: a balloon whose tip is the coordinate.
///
/// Drawn rather than composed from an icon font so the tip lands on an exact
/// pixel and the white casing survives over busy tiles.
class _MapPin extends StatelessWidget {
  const _MapPin({
    required this.color,
    required this.icon,
    required this.width,
    required this.height,
    required this.borderColor,
    required this.borderWidth,
  });

  final Color color;
  final IconData icon;
  final double width;
  final double height;
  final Color borderColor;
  final double borderWidth;

  @override
  Widget build(BuildContext context) {
    final double head = width / 2;
    final double disc = width * 0.46;
    return SizedBox(
      width: width,
      height: height,
      child: Stack(
        children: <Widget>[
          Positioned.fill(
            child: CustomPaint(
              painter: _PinPainter(
                color: color,
                borderColor: borderColor,
                borderWidth: borderWidth,
              ),
            ),
          ),
          Positioned(
            left: head - disc / 2,
            top: head - disc / 2,
            width: disc,
            height: disc,
            child: DecoratedBox(
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: disc * 0.68, color: color),
            ),
          ),
        ],
      ),
    );
  }
}

class _PinPainter extends CustomPainter {
  const _PinPainter({
    required this.color,
    required this.borderColor,
    required this.borderWidth,
  });

  final Color color;
  final Color borderColor;
  final double borderWidth;

  @override
  void paint(Canvas canvas, Size size) {
    final double w = size.width;
    final double h = size.height;
    final double r = w / 2 - borderWidth / 2;
    final Offset centre = Offset(w / 2, w / 2);

    // Round head, then two curves drawn back down to the tip.
    // latlong2 exports its own `Path`, so the canvas one is named explicitly.
    final ui.Path path = ui.Path()
      ..moveTo(w / 2, h)
      ..cubicTo(w * 0.06, h * 0.66, centre.dx - r, w * 0.86, centre.dx - r,
          centre.dy)
      ..arcToPoint(
        Offset(centre.dx + r, centre.dy),
        radius: Radius.circular(r),
        clockwise: true,
      )
      ..cubicTo(centre.dx + r, w * 0.86, w * 0.94, h * 0.66, w / 2, h)
      ..close();

    canvas.drawShadow(path, Colors.black, 2, false);
    canvas.drawPath(path, Paint()..color = color);
    canvas.drawPath(
      path,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = borderWidth
        ..color = borderColor,
    );
  }

  @override
  bool shouldRepaint(_PinPainter old) =>
      old.color != color ||
      old.borderColor != borderColor ||
      old.borderWidth != borderWidth;
}

class _MapButton extends StatelessWidget {
  const _MapButton({
    required this.tooltip,
    required this.icon,
    required this.color,
    required this.onPressed,
  });

  final String tooltip;
  final IconData icon;
  final Color color;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      shape: const CircleBorder(),
      elevation: 1,
      child: IconButton(
        tooltip: tooltip,
        icon: Icon(icon, size: 20),
        onPressed: onPressed,
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.child, required this.background});

  final Widget child;
  final Color background;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(Radii.pill),
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: Insets.md,
          vertical: Insets.xs,
        ),
        child: DefaultTextStyle.merge(
          style: Theme.of(context).textTheme.bodySmall ?? const TextStyle(),
          child: child,
        ),
      ),
    );
  }
}
