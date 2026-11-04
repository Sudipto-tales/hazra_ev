import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../data/models/models.dart' hide LatLngBounds;
import 'live_marker.dart';
import 'live_pin.dart';
import 'map_chrome.dart';

/// The map half of the live team screen: tiles, trails, markers, clusters.
///
/// Deliberately dumb about where data comes from. It is handed the roster, the
/// config and a clock, and it owns exactly three things the page cannot:
/// the camera, the 600 ms slide between fixes, and the current zoom (which is
/// what decides whether markers cluster).
///
/// **Per-second values ride the clock, never `setState`.** Fix ages have to
/// count seconds to be worth anything, and a `setState` a second would rebuild
/// the whole screen for a label. [clock] + a [ValueListenableBuilder] rebuild
/// only the layers — the same discipline the tracking controller's ticker
/// follows, and for the same reason.
class LiveTeamMap extends StatefulWidget {
  const LiveTeamMap({
    super.key,
    required this.members,
    required this.config,
    required this.clock,
    required this.trails,
    required this.selectedId,
    required this.onSelect,
  });

  /// The roster exactly as the repository returned it. Markers are derived per
  /// tick rather than passed in, so the age under each pin is the real age at
  /// *this* second and not at the last poll.
  final List<TeamMember> members;

  final TrackingConfig config;

  /// Ticks once a second. Only the layers listen.
  final ValueListenable<DateTime> clock;

  /// Employee id → their last 30 minutes, oldest first.
  final Map<String, List<GeoPoint>> trails;

  final String? selectedId;
  final ValueChanged<String?> onSelect;

  @override
  State<LiveTeamMap> createState() => _LiveTeamMapState();
}

class _LiveTeamMapState extends State<LiveTeamMap>
    with SingleTickerProviderStateMixin {
  final MapController _map = MapController();

  /// One controller for every marker rather than one each: they all move on
  /// the same poll, so they can share a clock. Nine `AnimationController`s
  /// ticking out of phase would be nine rebuild storms.
  late final AnimationController _motion = AnimationController(
    duration: MapConfig.liveMarkerMotion,
    vsync: this,
  );

  late final Animation<double> _eased =
      CurvedAnimation(parent: _motion, curve: Curves.easeOut);

  /// Employee id → the slide currently being drawn.
  final Map<String, MarkerMotion> _motions = <String, MarkerMotion>{};

  /// Whole-degree zoom, which is all the clustering needs. Tracked coarsely on
  /// purpose: re-bucketing on every pixel of a pinch would rebuild the marker
  /// layer continuously for no visible gain.
  int _zoomStep = 11;
  double _zoom = 11;

  bool _followSelection = false;

  @override
  void initState() {
    super.initState();
    _seedMotions();
  }

  @override
  void didUpdateWidget(LiveTeamMap old) {
    super.didUpdateWidget(old);
    if (!identical(old.members, widget.members)) _retarget();
    if (old.selectedId != widget.selectedId && widget.selectedId != null) {
      _centreOn(widget.selectedId!);
    }
  }

  @override
  void dispose() {
    _motion.dispose();
    _map.dispose();
    super.dispose();
  }

  // ------------------------------------------------------------- motion

  List<LiveMarker> _derive(DateTime now) {
    final List<LiveMarker> out = <LiveMarker>[];
    for (final TeamMember m in widget.members) {
      final LiveMarker? marker =
          LiveMarker.of(m, config: widget.config, now: now);
      if (marker != null) out.add(marker);
    }
    return out;
  }

  void _seedMotions() {
    final DateTime now = DateTime.now();
    for (final LiveMarker m in _derive(now)) {
      _motions[m.employeeId] = MarkerMotion.still(m);
    }
    _motion.value = 1;
  }

  /// Point every marker's slide at its new fix and run the animation once.
  ///
  /// The "from" is where the marker is *currently drawn*, not where the last
  /// payload said it was: a fix landing mid-slide must not snap the marker back
  /// to the start before setting off again.
  void _retarget() {
    final DateTime now = DateTime.now();
    final double t = _eased.value;
    final Map<String, MarkerMotion> next = <String, MarkerMotion>{};
    bool anyMoved = false;

    for (final LiveMarker m in _derive(now)) {
      final MarkerMotion? current = _motions[m.employeeId];
      if (current == null) {
        // First sighting. Nothing to slide from — it appears where it is.
        next[m.employeeId] = MarkerMotion.still(m);
        continue;
      }

      final double fromLat = current.latAt(t);
      final double fromLng = current.lngAt(t);
      final double? fromBearing = current.bearingAt(t);

      if (fromLat == m.latitude &&
          fromLng == m.longitude &&
          fromBearing == m.bearingDeg) {
        next[m.employeeId] = current;
        continue;
      }

      anyMoved = true;
      next[m.employeeId] = MarkerMotion(
        fromLat: fromLat,
        fromLng: fromLng,
        toLat: m.latitude,
        toLng: m.longitude,
        fromBearing: fromBearing,
        toBearing: m.bearingDeg,
      );
    }

    _motions
      ..clear()
      ..addAll(next);

    if (anyMoved) {
      _motion
        ..reset()
        ..forward();
    } else {
      _motion.value = 1;
    }

    if (_followSelection && widget.selectedId != null) {
      _centreOn(widget.selectedId!);
    }
  }

  // ------------------------------------------------------------- camera

  LatLng _positionOf(LiveMarker marker, double t) {
    final MarkerMotion? motion = _motions[marker.employeeId];
    if (motion == null) return LatLng(marker.latitude, marker.longitude);
    return LatLng(motion.latAt(t), motion.lngAt(t));
  }

  double? _bearingOf(LiveMarker marker, double t) {
    final MarkerMotion? motion = _motions[marker.employeeId];
    if (motion == null) return marker.bearingDeg;
    // Null all the way down. A marker with no heading never rotates, and
    // nothing on this path may invent one for it.
    return motion.bearingAt(t);
  }

  void _centreOn(String employeeId) {
    final List<LiveMarker> markers = _derive(DateTime.now());
    for (final LiveMarker m in markers) {
      if (m.employeeId != employeeId) continue;
      _map.move(
        LatLng(m.latitude, m.longitude),
        math.max(_zoom, 15),
      );
      return;
    }
  }

  void _fit() {
    final List<LiveMarker> markers = _derive(DateTime.now());
    if (markers.isEmpty) return;
    if (markers.length == 1) {
      _map.move(LatLng(markers.first.latitude, markers.first.longitude), 15);
      return;
    }
    _map.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds.fromPoints(
          markers
              .map((LiveMarker m) => LatLng(m.latitude, m.longitude))
              .toList(growable: false),
        ),
        padding: const EdgeInsets.all(48),
        maxZoom: 16,
      ),
    );
  }

  CameraFit? _initialFit() {
    final List<LiveMarker> markers = _derive(DateTime.now());
    if (markers.length < 2) return null;
    return CameraFit.bounds(
      bounds: LatLngBounds.fromPoints(
        markers
            .map((LiveMarker m) => LatLng(m.latitude, m.longitude))
            .toList(growable: false),
      ),
      padding: const EdgeInsets.all(48),
      maxZoom: 16,
    );
  }

  LatLng get _initialCentre {
    final List<LiveMarker> markers = _derive(DateTime.now());
    if (markers.isNotEmpty) {
      return LatLng(markers.first.latitude, markers.first.longitude);
    }
    // Nobody on the map yet. Centre on the operating region rather than on
    // null island — an empty map at (0, 0) looks broken.
    return const LatLng(23.2405, 87.8600);
  }

  // ------------------------------------------------------------- build

  @override
  Widget build(BuildContext context) {
    final bool dark = Theme.of(context).brightness == Brightness.dark;
    final Color plate =
        dark ? AppColors.surfaceAltDark : AppColors.surfaceAlt;

    return Stack(
      children: <Widget>[
        FlutterMap(
          mapController: _map,
          options: MapOptions(
            initialCenter: _initialCentre,
            initialZoom: 11,
            initialCameraFit: _initialFit(),
            minZoom: MapConfig.minZoom,
            maxZoom: MapConfig.maxZoom,
            backgroundColor: plate,
            onTap: (_, __) => widget.onSelect(null),
            onPositionChanged: (MapCamera camera, bool hasGesture) {
              _zoom = camera.zoom;
              final int step = camera.zoom.floor();
              // Crossing a whole zoom level is the only thing clustering
              // reacts to. Deferred to after the frame because this fires from
              // inside the camera update — including the initial fit, where a
              // synchronous setState would land during build.
              if (step == _zoomStep) return;
              _zoomStep = step;
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted) setState(() {});
              });
            },
            interactionOptions: const InteractionOptions(
              flags: InteractiveFlag.drag |
                  InteractiveFlag.flingAnimation |
                  InteractiveFlag.pinchZoom |
                  InteractiveFlag.pinchMove |
                  InteractiveFlag.doubleTapZoom |
                  InteractiveFlag.scrollWheelZoom,
            ),
          ),
          children: <Widget>[
            TileLayer(
              urlTemplate: MapConfig.tileUrlFor(dark: dark),
              userAgentPackageName: MapConfig.userAgentPackageName,
              maxNativeZoom: MapConfig.maxNativeZoom,
              maxZoom: MapConfig.maxZoom,
              tileProvider: NetworkTileProvider(),
            ),
            // One subtree for everything that changes per second or per frame.
            // The tile layer above is not in it, so a slide never re-requests
            // a tile.
            AnimatedBuilder(
              animation: _eased,
              builder: (BuildContext context, _) {
                return ValueListenableBuilder<DateTime>(
                  valueListenable: widget.clock,
                  builder: (BuildContext context, DateTime now, _) {
                    final List<LiveMarker> markers = _derive(now);
                    final double t = _eased.value;
                    return Stack(
                      children: <Widget>[
                        PolylineLayer<Object>(
                          polylines: _trailLines(markers),
                        ),
                        MarkerLayer(markers: _markers(markers, t)),
                      ],
                    );
                  },
                );
              },
            ),
            const Scalebar(
              alignment: Alignment.bottomLeft,
              lineColor: AppColors.textSecondary,
              textStyle: TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
              ),
              padding: EdgeInsets.fromLTRB(Insets.sm, 0, 0, Insets.sm),
            ),
            SimpleAttributionWidget(
              // Both parties: CARTO serve the tiles, OSM contributors made the
              // data they are built from.
              source: const Text(MapConfig.attributionText),
              backgroundColor: plate.withValues(alpha: 0.8),
            ),
          ],
        ),
        Positioned(
          top: Insets.sm,
          right: Insets.sm,
          child: Column(
            children: <Widget>[
              MapIconButton(
                tooltip: 'Fit team',
                icon: Icons.fit_screen_outlined,
                color: plate,
                onPressed: _fit,
              ),
              const SizedBox(height: Insets.xs),
              MapIconButton(
                tooltip: _followSelection
                    ? 'Stop following'
                    : 'Follow the selected employee',
                icon: _followSelection
                    ? Icons.my_location_rounded
                    : Icons.location_searching_rounded,
                color: plate,
                onPressed: () {
                  setState(() => _followSelection = !_followSelection);
                  final String? id = widget.selectedId;
                  if (_followSelection && id != null) _centreOn(id);
                },
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ------------------------------------------------------------- layers

  /// The last half hour behind each marker, fading out at the tail.
  ///
  /// The fade is the information: the bright end is where they are now and the
  /// transparent end is where they were, so direction of travel reads off the
  /// line itself without an arrowhead to decode.
  List<Polyline<Object>> _trailLines(List<LiveMarker> markers) {
    final List<Polyline<Object>> out = <Polyline<Object>>[];

    for (final LiveMarker marker in markers) {
      final List<GeoPoint>? trail = widget.trails[marker.employeeId];
      if (trail == null || trail.length < 2) continue;

      final bool selected = marker.employeeId == widget.selectedId;
      final Color color = marker.tone.color;

      out.add(
        Polyline<Object>(
          points: trail
              .map((GeoPoint p) => LatLng(p.latitude, p.longitude))
              .toList(growable: false),
          strokeWidth: selected ? 4.5 : 3,
          gradientColors: <Color>[
            color.withValues(alpha: 0),
            color.withValues(alpha: marker.stale ? 0.35 : 0.85),
          ],
        ),
      );
    }

    return out;
  }

  List<Marker> _markers(List<LiveMarker> markers, double t) {
    final List<MarkerCluster> clusters = MarkerCluster.group(
      markers,
      zoom: _zoom,
      maxClusterZoom: MapConfig.liveClusterMaxZoom,
    );

    final List<Marker> out = <Marker>[];
    Marker? selected;

    for (final MarkerCluster cluster in clusters) {
      if (!cluster.isSingle) {
        out.add(_clusterBadge(cluster));
        continue;
      }
      final Marker pin = _pin(cluster.single, t);
      // Appended last, so the selected pin is never hidden under a neighbour.
      if (cluster.single.employeeId == widget.selectedId) {
        selected = pin;
      } else {
        out.add(pin);
      }
    }

    if (selected != null) out.add(selected);
    return out;
  }

  Marker _pin(LiveMarker marker, double t) {
    final bool selected = marker.employeeId == widget.selectedId;
    final double width = selected ? 40 : 34;
    final double height = LivePin.totalHeight(width);

    return Marker(
      key: ValueKey<String>(marker.employeeId),
      point: _positionOf(marker, t),
      width: math.max(width, 68),
      height: height,
      // The teardrop's tip is the coordinate; the age chip hangs below it and
      // must not push the pin off the spot it is reporting.
      alignment: Marker.computePixelAlignment(
        width: math.max(width, 68),
        height: height,
        left: math.max(width, 68) / 2,
        top: LivePin.pinHeight(width),
      ),
      child: GestureDetector(
        onTap: () => widget.onSelect(selected ? null : marker.employeeId),
        child: Align(
          alignment: Alignment.topCenter,
          child: LivePin(
            marker: marker,
            bearingDeg: _bearingOf(marker, t),
            selected: selected,
            width: width,
          ),
        ),
      ),
    );
  }

  Marker _clusterBadge(MarkerCluster cluster) {
    const double size = 42;
    return Marker(
      point: LatLng(cluster.latitude, cluster.longitude),
      width: size,
      height: size,
      child: GestureDetector(
        // Zooming in is the only sensible action: the cluster exists because
        // the markers cannot be told apart at this zoom.
        onTap: () => _map.move(
          LatLng(cluster.latitude, cluster.longitude),
          math.min(_zoom + 2.5, MapConfig.maxZoom),
        ),
        child: ClusterBadge(
          count: cluster.markers.length,
          tone: cluster.tone,
          size: size,
        ),
      ),
    );
  }
}
