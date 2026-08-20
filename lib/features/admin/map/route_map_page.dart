import 'package:flutter/material.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/mock/mock_data.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../../home/widgets/visit_tile.dart';
import '../widgets/date_scrubber.dart';
import 'route_painter.dart';
import 'route_projection.dart';
import 'route_tile_map.dart';

/// Historical route for one employee on one day.
///
/// Two renderers, one screen:
///
/// * [RouteTileMap] — OpenStreetMap tiles with the trace snapped to real roads
///   by OSRM. What an admin wants: streets, names, recognisable geography.
/// * [_RouteCanvas] — the original hand-painted grid plate. No network, no
///   third-party host. Still here because tiles can be unreachable (no signal,
///   OSM blocked on the customer's network) and a blank grey box is not an
///   answer.
///
/// The map starts on tiles and falls back to the canvas when they fail to
/// load; both directions are also a button, so the choice is never stuck.
/// `--dart-define=OFFLINE_MAP=true` starts on the canvas instead.
class RouteMapPage extends StatefulWidget {
  const RouteMapPage({super.key, required this.employee, required this.date});

  final Employee employee;
  final DateTime date;

  @override
  State<RouteMapPage> createState() => _RouteMapPageState();
}

class _RouteMapPageState extends State<RouteMapPage> {
  late DateTime _date = Fmt.dayOnly(widget.date);
  bool _loading = true;
  Object? _error;
  RouteTrack? _track;
  TrackingConfig _config = TrackingConfig.defaults;
  String? _selectedId;

  /// Which renderer is on screen. Flipped by the tile layer when the basemap
  /// cannot be reached, and by the buttons on either view.
  bool _useTiles = !MapConfig.forceOffline;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _selectedId = null;
    });
    try {
      final AppScope scope = AppScope.of(context);
      final RouteTrack track = await scope.adminRepository.route(
        employeeId: widget.employee.id,
        date: _date,
      );
      final TrackingConfig config = await scope.adminRepository.config();
      if (!mounted) return;
      setState(() {
        _track = track;
        _config = config;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Route · ${widget.employee.firstName}')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          Insets.lg,
          Insets.lg,
          Insets.lg,
          Insets.xxxl,
        ),
        children: <Widget>[
          DateScrubber(
            date: _date,
            firstDate: MockData.today.subtract(const Duration(days: 120)),
            lastDate: MockData.today,
            onChanged: (DateTime d) {
              setState(() => _date = d);
              _load();
            },
          ),
          const SizedBox(height: Insets.lg),
          if (_loading)
            const SkeletonBox(height: 340, radius: Radii.lg)
          else if (_error != null)
            ErrorState(onRetry: _load)
          else
            ..._content(_track!),
        ],
      ),
    );
  }

  List<Widget> _content(RouteTrack track) {
    if (track.isEmpty) {
      return <Widget>[
        AppCard(
          child: EmptyState(
            icon: Icons.route_outlined,
            title: 'No route recorded',
            message:
                '${widget.employee.firstName} did not open a session on '
                '${Fmt.mediumDate(_date)}.',
          ),
        ),
      ];
    }

    final DateTime now = DateTime.now();

    return <Widget>[
      AppCard(
        padding: EdgeInsets.zero,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(Radii.lg),
          child: _useTiles
              ? RouteTileMap(
                  track: track,
                  config: _config,
                  selectedId: _selectedId,
                  onSelect: (String? id) =>
                      setState(() => _selectedId = id),
                  onTilesUnavailable: () =>
                      setState(() => _useTiles = false),
                )
              : _RouteCanvas(
                  track: track,
                  config: _config,
                  selectedId: _selectedId,
                  onSelect: (String? id) =>
                      setState(() => _selectedId = id),
                  onUseTiles: MapConfig.forceOffline
                      ? null
                      : () => setState(() => _useTiles = true),
                ),
        ),
      ),
      const SizedBox(height: Insets.md),
      const _Legend(),
      if (_selectedId != null) ...<Widget>[
        const SizedBox(height: Insets.md),
        _SelectionCard(track: track, id: _selectedId!, config: _config),
      ],

      const SizedBox(height: Insets.xl),
      StatGrid(
        children: <Widget>[
          StatTile(
            icon: Icons.route_outlined,
            value: Fmt.km(track.totalDistanceKm),
            label: 'Distance',
          ),
          StatTile(
            icon: Icons.my_location_rounded,
            value: '${track.points.length}',
            label: 'Fixes logged',
            tone: AppColors.info,
          ),
          StatTile(
            icon: Icons.storefront_outlined,
            value: '${track.visits.length}',
            label: 'Visits',
            tone: AppColors.success,
          ),
          StatTile(
            icon: Icons.pause_circle_outline_rounded,
            value: '${track.stops.length}',
            label: 'Stops',
            tone: AppColors.warning,
          ),
          StatTile(
            icon: Icons.play_arrow_rounded,
            value: Fmt.time(track.firstFixAt),
            label: 'First fix',
          ),
          StatTile(
            icon: Icons.cloud_queue_rounded,
            value: '${track.queuedCount}',
            label: 'Not yet synced',
            tone: AppColors.textTertiary,
          ),
        ],
      ),

      // Text fallback: everything on the canvas is reachable without it.
      const SectionHeader(
        title: 'Stops & visits',
        padding: EdgeInsets.fromLTRB(0, Insets.xxl, 0, Insets.md),
      ),
      ...track.visits.map(
        (CompanyVisit v) => Padding(
          padding: const EdgeInsets.only(bottom: Insets.md),
          child: VisitTile(visit: v, now: now),
        ),
      ),
    ];
  }
}

/// The interactive canvas: fit-to-bounds projection, pinch zoom, tap markers.
class _RouteCanvas extends StatefulWidget {
  const _RouteCanvas({
    required this.track,
    required this.config,
    required this.selectedId,
    required this.onSelect,
    this.onUseTiles,
  });

  final RouteTrack track;
  final TrackingConfig config;
  final String? selectedId;
  final ValueChanged<String?> onSelect;

  /// Back to the OSM basemap. `null` when the build forced the offline view,
  /// in which case offering the button would be a lie.
  final VoidCallback? onUseTiles;

  @override
  State<_RouteCanvas> createState() => _RouteCanvasState();
}

class _RouteCanvasState extends State<_RouteCanvas> {
  final TransformationController _controller = TransformationController();
  double _zoom = 1;

  @override
  void initState() {
    super.initState();
    _controller.addListener(_onTransform);
  }

  void _onTransform() {
    final double z = _controller.value.getMaxScaleOnAxis();
    if ((z - _zoom).abs() > 0.001) setState(() => _zoom = z);
  }

  @override
  void dispose() {
    _controller.removeListener(_onTransform);
    _controller.dispose();
    super.dispose();
  }

  List<RouteMarker> _markers(RouteProjection p) {
    final List<RouteMarker> out = <RouteMarker>[];

    for (final StopRecord s in widget.track.stops) {
      if (s.isLinked) continue;
      out.add(
        RouteMarker(
          id: s.id,
          kind: RouteMarkerKind.stop,
          center: p.project(s.latitude, s.longitude),
          radius: 7 / _zoom,
          stop: s,
        ),
      );
    }
    for (final CompanyVisit v in widget.track.visits) {
      out.add(
        RouteMarker(
          id: v.id,
          kind: RouteMarkerKind.visit,
          center: p.project(v.latitude, v.longitude),
          radius: 11 / _zoom,
          visit: v,
        ),
      );
    }
    return out;
  }

  @override
  Widget build(BuildContext context) {
    final RouteMapColors colors = RouteMapColors.of(context);

    return SizedBox(
      height: 340,
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints constraints) {
          final Size size = Size(constraints.maxWidth, constraints.maxHeight);
          final RouteProjection projection = RouteProjection.fit(
            bounds: widget.track.bounds.padded(0.12),
            size: size,
          );
          final List<RouteMarker> markers = _markers(projection);

          return Stack(
            children: <Widget>[
              GestureDetector(
                onTapUp: (TapUpDetails details) {
                  // Screen → scene, so hit-testing works at any zoom.
                  final Offset scene =
                      _controller.toScene(details.localPosition);
                  RouteMarker? best;
                  double bestDistance = double.infinity;
                  for (final RouteMarker m in markers) {
                    final double d = (m.center - scene).distance;
                    if (d < bestDistance) {
                      bestDistance = d;
                      best = m;
                    }
                  }
                  widget.onSelect(
                    best != null && bestDistance <= 24 / _zoom ? best.id : null,
                  );
                },
                child: InteractiveViewer(
                  transformationController: _controller,
                  minScale: 1,
                  maxScale: 5,
                  child: CustomPaint(
                    size: size,
                    painter: RoutePainter(
                      track: widget.track,
                      projection: projection,
                      colors: colors,
                      markers: markers,
                      longStopThresholdMinutes:
                          widget.config.longStopThresholdMinutes,
                      stopRadiusMetres: widget.config.stopRadiusMetres,
                      zoom: _zoom,
                      selectedId: widget.selectedId,
                    ),
                  ),
                ),
              ),
              Positioned(
                top: Insets.sm,
                right: Insets.sm,
                child: Column(
                  children: <Widget>[
                    if (widget.onUseTiles != null)
                      _CanvasButton(
                        tooltip: 'Map view',
                        icon: Icons.map_outlined,
                        color: colors.plate,
                        onPressed: widget.onUseTiles!,
                      ),
                    if (_zoom > 1.01) ...<Widget>[
                      const SizedBox(height: Insets.xs),
                      _CanvasButton(
                        tooltip: 'Fit route',
                        icon: Icons.fit_screen_outlined,
                        color: colors.plate,
                        onPressed: () =>
                            _controller.value = Matrix4.identity(),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _CanvasButton extends StatelessWidget {
  const _CanvasButton({
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
      child: IconButton(
        tooltip: tooltip,
        icon: Icon(icon, size: 20),
        onPressed: onPressed,
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.all(Insets.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Wrap(
            spacing: Insets.lg,
            runSpacing: Insets.sm,
            children: <Widget>[
              _LegendItem(color: AppColors.success, label: 'Start'),
              _LegendItem(color: AppColors.primary, label: 'Visit'),
              _LegendItem(color: AppColors.warning, label: 'Stop'),
              _LegendItem(color: AppColors.danger, label: 'Long stop'),
              _LegendItem(color: AppColors.textTertiary, label: 'Not synced'),
              _LegendItem(color: AppColors.statusOffline, label: 'End'),
            ],
          ),
          const SizedBox(height: Insets.sm),
          Text(
            'The route line changes colour per work session. A faint dashed '
            'line is the raw GPS trace under the road-matched route.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: Insets.xs),
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}

/// Detail for the tapped marker.
class _SelectionCard extends StatelessWidget {
  const _SelectionCard({
    required this.track,
    required this.id,
    required this.config,
  });

  final RouteTrack track;
  final String id;
  final TrackingConfig config;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    final CompanyVisit? visit = track.visits
        .where((CompanyVisit v) => v.id == id)
        .fold<CompanyVisit?>(null, (CompanyVisit? _, CompanyVisit v) => v);
    final StopRecord? stop = track.stops
        .where((StopRecord s) => s.id == id)
        .fold<StopRecord?>(null, (StopRecord? _, StopRecord s) => s);

    if (visit != null) {
      final Duration dwell = visit.durationAt(DateTime.now());
      return AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              MockData.companyName(visit.companyId),
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 2),
            Text(
              MockData.branchName(visit.companyId, visit.branchId) ?? '—',
              style: theme.textTheme.bodySmall,
            ),
            const Divider(height: Insets.xl),
            KeyValueRow(
              label: 'Arrival',
              value: Fmt.time(visit.arrival),
              dense: true,
            ),
            KeyValueRow(
              label: 'Departure',
              value: Fmt.time(visit.departure),
              dense: true,
            ),
            KeyValueRow(
              label: 'Dwell',
              value: Fmt.duration(dwell),
              dense: true,
              valueColor: dwell.inMinutes >= config.longStopThresholdMinutes
                  ? AppColors.danger
                  : null,
            ),
            KeyValueRow(
              label: 'Coordinates',
              value: Fmt.latLng(visit.latitude, visit.longitude),
              dense: true,
            ),
            KeyValueRow(
              label: 'Reports',
              value: '${visit.reportIds.length}',
              dense: true,
            ),
          ],
        ),
      );
    }

    if (stop != null) {
      final Duration dwell = stop.durationAt(DateTime.now());
      return AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Unlinked stop', style: theme.textTheme.titleMedium),
            const SizedBox(height: 2),
            Text(
              'No company was matched to this stop.',
              style: theme.textTheme.bodySmall,
            ),
            const Divider(height: Insets.xl),
            KeyValueRow(
              label: 'Arrival',
              value: Fmt.time(stop.arrival),
              dense: true,
            ),
            KeyValueRow(
              label: 'Dwell',
              value: Fmt.duration(dwell),
              dense: true,
            ),
            KeyValueRow(
              label: 'Coordinates',
              value: Fmt.latLng(stop.latitude, stop.longitude),
              dense: true,
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }
}
