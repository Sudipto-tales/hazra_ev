import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/street_view.dart';
import '../../../data/models/models.dart' hide LatLngBounds;
import 'live_marker.dart';
import 'live_pin.dart';
import 'map_chrome.dart';
import 'trail_arrows.dart';

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
    required this.fullTrails,
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

  /// Employee id → their last 30 minutes, oldest first. The default glanceable
  /// view — enough tail to read direction, little enough to keep a team legible.
  final Map<String, List<GeoPoint>> trails;

  /// Employee id → their *whole day*, grouped by session (snapped geometry when
  /// the server had it, raw trace otherwise). Opt-in: only the **selected**
  /// employee's full trail is drawn, so picking one person swaps their 30-minute
  /// tail for the full day without turning the whole map into spaghetti.
  final Map<String, List<List<GeoPoint>>> fullTrails;

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

  /// The identity legend starts open — a map of coloured lines is unreadable
  /// until you can name them — but collapses to a chip the moment an admin
  /// wants the pixels back.
  bool _legendOpen = true;

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
            // Long-press anywhere on the map opens Street View at that exact
            // point — the "look at where they are" gesture, without a key or an
            // in-app panorama. The selected pin's callout offers the same thing
            // discoverably; this is the power-user path for any point on a trail.
            onLongPress: (_, LatLng p) =>
                StreetView.open(context, p.latitude, p.longitude),
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
              // Esri Light/Dark Gray Base by default, CARTO Voyager / Dark
              // Matter when a key is defined — see MapConfig for why, and for
              // why maxNativeZoom is a getter rather than a constant.
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
                    final List<_TrailDraw> draws = _trailDraws(markers);
                    return Stack(
                      children: <Widget>[
                        PolylineLayer<Object>(
                          polylines: _trailLines(draws),
                        ),
                        if (_zoom > MapConfig.liveClusterMaxZoom)
                          MarkerLayer(markers: _trailArrows(draws)),
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
              // Both parties, whichever provider is active: someone serves the
              // tiles, OSM contributors made the data they are built from.
              source: Text(MapConfig.attributionText),
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
        Positioned(
          top: Insets.sm,
          left: Insets.sm,
          child: _LiveLegend(
            entries: _legendEntries(),
            open: _legendOpen,
            selectedId: widget.selectedId,
            plate: plate,
            onToggle: () => setState(() => _legendOpen = !_legendOpen),
            onSelect: widget.onSelect,
          ),
        ),
      ],
    );
  }

  /// Name + identity hue for everyone currently on the map, sorted by name so
  /// the legend order is stable between rebuilds. Derived straight from the
  /// roster — no per-second data here, so it does not ride the clock.
  List<_LegendEntry> _legendEntries() {
    final List<_LegendEntry> out = <_LegendEntry>[];
    for (final LiveMarker m in _derive(DateTime.now())) {
      out.add(
        _LegendEntry(
          id: m.employeeId,
          name: m.member.employee.name,
          color: AppColors.employeeColor(m.employeeId),
        ),
      );
    }
    out.sort((_LegendEntry a, _LegendEntry b) => a.name.compareTo(b.name));
    return out;
  }

  // ------------------------------------------------------------- layers

  /// The last half hour behind each marker, fading out at the tail.
  ///
  /// The fade is the information: the bright end is where they are now and the
  /// transparent end is where they were, so direction of travel reads off the
  /// line itself without an arrowhead to decode.
  ///
  /// **The line's colour is identity, not state.** Each trail wears its
  /// employee's stable hue ([AppColors.employeeColor]) so an admin can tell
  /// two overlapping trails apart — and match a line to a name in the legend —
  /// while the *marker* at its head keeps carrying moving/stopped/alert state.
  /// Line answers "whose?", pin answers "doing what?".
  /// What to draw behind each marker: the 30-minute window for everyone, or the
  /// whole day for the one selected employee.
  ///
  /// The selected person's full-day trail is opt-in on purpose — it is why
  /// `fullTrails` is only consulted for `selectedId`. Picking someone trades
  /// their short glance-tail for the full route (one polyline per session, so a
  /// lunch break never draws a phantom chord), and everyone else stays on the
  /// legible 30-minute window.
  List<_TrailDraw> _trailDraws(List<LiveMarker> markers) {
    final List<_TrailDraw> out = <_TrailDraw>[];

    for (final LiveMarker marker in markers) {
      final bool selected = marker.employeeId == widget.selectedId;
      final Color color = AppColors.employeeColor(marker.employeeId);

      final List<List<GeoPoint>>? full =
          selected ? widget.fullTrails[marker.employeeId] : null;

      if (full != null && full.isNotEmpty) {
        for (final List<GeoPoint> seg in full) {
          if (seg.length < 2) continue;
          out.add(_TrailDraw(
            points: _toLatLng(seg),
            color: color,
            selected: true,
            fullDay: true,
            stale: marker.stale,
          ));
        }
      } else {
        final List<GeoPoint>? trail = widget.trails[marker.employeeId];
        if (trail == null || trail.length < 2) continue;
        out.add(_TrailDraw(
          points: _toLatLng(trail),
          color: color,
          selected: selected,
          fullDay: false,
          stale: marker.stale,
        ));
      }
    }

    return out;
  }

  static List<LatLng> _toLatLng(List<GeoPoint> points) => points
      .map((GeoPoint p) => LatLng(p.latitude, p.longitude))
      .toList(growable: false);

  /// The trail polylines. The 30-minute window keeps its fade — bright head,
  /// transparent tail, so recency reads off the line. The full-day trail is a
  /// flat line instead: "now" is not a meaningful end of a whole day, so a fade
  /// would imply a freshness that is not there.
  List<Polyline<Object>> _trailLines(List<_TrailDraw> draws) {
    final List<Polyline<Object>> out = <Polyline<Object>>[];

    for (final _TrailDraw d in draws) {
      if (d.fullDay) {
        out.add(
          Polyline<Object>(
            points: d.points,
            strokeWidth: 4,
            color: d.color.withValues(alpha: 0.75),
          ),
        );
      } else {
        out.add(
          Polyline<Object>(
            points: d.points,
            strokeWidth: d.selected ? 4.5 : 3,
            gradientColors: <Color>[
              d.color.withValues(alpha: 0),
              d.color.withValues(alpha: d.stale ? 0.35 : 0.85),
            ],
          ),
        );
      }
    }

    return out;
  }

  /// Direction chevrons along every trail, spaced by ground distance and thinned
  /// with zoom so they read as a flow rather than smearing into a bar. Gated
  /// above the cluster zoom by the caller: when markers collapse into counts,
  /// arrows would be noise.
  List<Marker> _trailArrows(List<_TrailDraw> draws) {
    final List<Marker> out = <Marker>[];

    for (final _TrailDraw d in draws) {
      if (d.points.isEmpty) continue;
      // Keep the on-screen spacing roughly constant: metres-per-pixel at this
      // zoom and latitude, times a target pixel gap. Zooming out grows the gap,
      // which is what thins the arrows instead of piling them up.
      final double mpp = 156543.03392 *
          math.cos(d.points.first.latitude * math.pi / 180).abs() /
          math.pow(2, _zoom);
      final double spacingMetres = (mpp * 58).clamp(5, double.infinity);
      final double size = d.selected ? 11 : 9.5;

      for (final TrailArrow arrow
          in arrowsAlongLatLng(d.points, spacingMetres: spacingMetres)) {
        out.add(
          Marker(
            point: arrow.position,
            width: size,
            height: size,
            child: Transform.rotate(
              angle: arrow.bearingDeg * math.pi / 180,
              child: TrailArrowIcon(
                color: d.color.withValues(alpha: d.stale ? 0.5 : 1),
                size: size,
              ),
            ),
          ),
        );
      }
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

/// One trail to render: its points, its identity hue, and the two flags that
/// decide how it is stroked (full day vs 30-minute window, selected vs not).
@immutable
class _TrailDraw {
  const _TrailDraw({
    required this.points,
    required this.color,
    required this.selected,
    required this.fullDay,
    required this.stale,
  });

  final List<LatLng> points;
  final Color color;
  final bool selected;
  final bool fullDay;
  final bool stale;
}

/// One row of the identity legend: a name and the hue its trail is drawn in.
@immutable
class _LegendEntry {
  const _LegendEntry({
    required this.id,
    required this.name,
    required this.color,
  });

  final String id;
  final String name;
  final Color color;
}

/// The map's identity key: which colour is whose trail.
///
/// Colour-by-employee is only as readable as the thing that names the colours,
/// so this is not optional chrome. It is deliberately **collapsible** — a team
/// of twenty would otherwise wall off a quarter of the map — and **scrollable**
/// past a handful of rows, so it never grows taller than the plate. Tapping a
/// row selects that employee, the same selection the roster and the pins share.
class _LiveLegend extends StatelessWidget {
  const _LiveLegend({
    required this.entries,
    required this.open,
    required this.selectedId,
    required this.plate,
    required this.onToggle,
    required this.onSelect,
  });

  final List<_LegendEntry> entries;
  final bool open;
  final String? selectedId;
  final Color plate;
  final VoidCallback onToggle;
  final ValueChanged<String?> onSelect;

  @override
  Widget build(BuildContext context) {
    if (entries.isEmpty) return const SizedBox.shrink();

    final ThemeData theme = Theme.of(context);
    final BorderRadius radius = BorderRadius.circular(Radii.md);
    final Color surface = plate.withValues(alpha: 0.92);

    if (!open) {
      return Material(
        color: surface,
        borderRadius: radius,
        elevation: 1,
        child: InkWell(
          borderRadius: radius,
          onTap: onToggle,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Insets.sm,
              vertical: 6,
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const Icon(
                  Icons.palette_outlined,
                  size: 15,
                  color: AppColors.textSecondary,
                ),
                const SizedBox(width: 4),
                Text('Legend', style: theme.textTheme.labelMedium),
              ],
            ),
          ),
        ),
      );
    }

    return Material(
      color: surface,
      borderRadius: radius,
      elevation: 1,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 190, maxHeight: 240),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            InkWell(
              borderRadius: BorderRadius.only(
                topLeft: radius.topLeft,
                topRight: radius.topRight,
              ),
              onTap: onToggle,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(Insets.sm, 6, 6, 6),
                child: Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        'Trails',
                        style: theme.textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                    ),
                    const Icon(
                      Icons.keyboard_arrow_up_rounded,
                      size: 17,
                      color: AppColors.textSecondary,
                    ),
                  ],
                ),
              ),
            ),
            Flexible(
              child: ListView.builder(
                padding: const EdgeInsets.only(bottom: 6),
                shrinkWrap: true,
                itemCount: entries.length,
                itemBuilder: (BuildContext context, int index) {
                  final _LegendEntry e = entries[index];
                  final bool selected = e.id == selectedId;
                  return InkWell(
                    onTap: () => onSelect(selected ? null : e.id),
                    child: Container(
                      color: selected
                          ? AppColors.primary.withValues(alpha: 0.12)
                          : Colors.transparent,
                      padding: const EdgeInsets.symmetric(
                        horizontal: Insets.sm,
                        vertical: 4,
                      ),
                      child: Row(
                        children: <Widget>[
                          Container(
                            width: 12,
                            height: 4,
                            decoration: BoxDecoration(
                              color: e.color,
                              borderRadius: BorderRadius.circular(Radii.pill),
                            ),
                          ),
                          const SizedBox(width: Insets.sm),
                          Expanded(
                            child: Text(
                              e.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                fontSize: 11.5,
                                fontWeight: selected
                                    ? FontWeight.w700
                                    : FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
