import 'package:flutter/material.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/utils/street_view.dart';
import '../../../data/company_directory.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/app_card.dart';
import '../../../widgets/stat_tile.dart';
import '../../../widgets/states.dart';
import '../../../widgets/status_badge.dart';
import '../../home/widgets/visit_tile.dart';
import '../widgets/date_scrubber.dart';
import 'route_accuracy.dart';
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
      // The visit card resolves companyId/branchId to names synchronously
      // inside build(), so the directory has to be warm before that card can
      // exist. It loads once per app run, shares one request between callers,
      // and swallows its own failure — a directory that could not be fetched
      // degrades the card to a placeholder instead of failing the whole map.
      await scope.companies.ensureLoaded();
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

  /// Midnight today. Read per build rather than cached in a field so a page
  /// left open across midnight still offers the new day.
  DateTime get _today => Fmt.dayOnly(DateTime.now());

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
            firstDate: _today.subtract(const Duration(days: 120)),
            lastDate: _today,
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

    // Derived once per build, against the config this screen loaded — never
    // against a hard-coded metre value and never per widget. Both renderers,
    // the legend, the stats and the rows below read this same verdict.
    final RouteAccuracy accuracy = RouteAccuracy.of(
      track: track,
      config: _config,
    );

    return <Widget>[
      AppCard(
        padding: EdgeInsets.zero,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(Radii.lg),
          child: _useTiles
              ? RouteTileMap(
                  track: track,
                  config: _config,
                  accuracy: accuracy,
                  selectedId: _selectedId,
                  onSelect: (String? id) =>
                      setState(() => _selectedId = id),
                  onTilesUnavailable: () =>
                      setState(() => _useTiles = false),
                )
              : _RouteCanvas(
                  track: track,
                  config: _config,
                  accuracy: accuracy,
                  // The employee's identity hue, the same colour their trail
                  // wears on the live team map. The tile renderer colours by
                  // work session instead; the painted fallback has no session
                  // palette, so identity is the honest thing to show here.
                  routeColor: AppColors.employeeColor(widget.employee.id),
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
      _Legend(accuracy: accuracy),
      if (_selectedId != null) ...<Widget>[
        const SizedBox(height: Insets.md),
        _SelectionCard(
          track: track,
          id: _selectedId!,
          config: _config,
          accuracy: accuracy,
        ),
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
          // Shown even at zero: "0 rough fixes" is the reassurance that makes
          // the non-zero case mean something.
          StatTile(
            icon: Icons.blur_on_rounded,
            value: '${accuracy.roughCount}',
            label: 'Rough fixes',
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
          child: VisitTile(
            visit: v,
            now: now,
            roughGps: accuracy.visit(v).isRough,
          ),
        ),
      ),
      // Unlinked stops had no row here at all, which is the one place a
      // jitter-derived dwell could hide: it was only reachable by tapping its
      // pin. A reader going down the list now sees it, and sees that it is
      // rough.
      ...track.stops.where((StopRecord s) => !s.isLinked).map(
            (StopRecord s) => Padding(
              padding: const EdgeInsets.only(bottom: Insets.md),
              child: _StopTile(
                stop: s,
                now: now,
                config: _config,
                quality: accuracy.stop(s),
              ),
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
    required this.accuracy,
    required this.routeColor,
    required this.selectedId,
    required this.onSelect,
    this.onUseTiles,
  });

  final RouteTrack track;
  final TrackingConfig config;
  final RouteAccuracy accuracy;

  /// The employee's identity hue for the trace, glow and rough stretches.
  final Color routeColor;

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
    final RouteMapColors colors =
        RouteMapColors.of(context, routeColor: widget.routeColor);

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
                      accuracy: widget.accuracy,
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
  const _Legend({required this.accuracy});

  final RouteAccuracy accuracy;

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
              _LegendItem(
                color: AppColors.primary,
                label: 'Rough GPS',
                dotted: true,
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),
          Text(
            'The route line changes colour per work session. A faint dashed '
            'line is the raw GPS trace under the road-matched route. '
            'Dotted stretches and soft halos are fixes weaker than '
            '${accuracy.thresholdLabel} — the trail is still right about where '
            'the employee was, just not about exactly where.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _LegendItem extends StatelessWidget {
  const _LegendItem({
    required this.color,
    required this.label,
    this.dotted = false,
  });

  final Color color;
  final String label;

  /// Draws a broken bar instead of a dot — the swatch for a treatment that is
  /// about the stroke, not about a new colour.
  final bool dotted;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        if (dotted)
          Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              for (int i = 0; i < 3; i++) ...<Widget>[
                if (i > 0) const SizedBox(width: 2.5),
                Container(
                  width: 4,
                  height: 4,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.55),
                    shape: BoxShape.circle,
                  ),
                ),
              ],
            ],
          )
        else
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
    required this.accuracy,
  });

  final RouteTrack track;
  final String id;
  final TrackingConfig config;
  final RouteAccuracy accuracy;

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

      // Names come from the directory, never from a "nearest match". The old
      // mock lookup fell through to `companies.first` for an unknown id, so a
      // live visit could render a real but wrong company name. A placeholder
      // is the only honest answer when the id is not in the directory.
      final CompanyDirectory directory = AppScope.of(context).companies;
      final String companyLabel = directory.displayCompany(
        visit.companyId,
        fallback: directory.isLoaded
            ? 'Unknown company'
            : 'Company name unavailable',
      );

      return AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(companyLabel, style: theme.textTheme.titleMedium),
            const SizedBox(height: 2),
            Text(
              directory.displayBranch(visit.companyId, visit.branchId),
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
            ..._accuracyRows(context, accuracy.visit(visit)),
            _StreetViewAction(
              latitude: visit.latitude,
              longitude: visit.longitude,
            ),
          ],
        ),
      );
    }

    if (stop != null) {
      final Duration dwell = stop.durationAt(DateTime.now());
      final StopAccuracy quality = accuracy.stop(stop);
      return AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'Unlinked stop',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                if (quality.isRough)
                  const StatusBadge(
                    label: 'Rough GPS',
                    tone: BadgeTone.neutral,
                    icon: Icons.blur_on_rounded,
                    dense: true,
                  ),
              ],
            ),
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
            ..._accuracyRows(context, quality),
            _StreetViewAction(
              latitude: stop.latitude,
              longitude: stop.longitude,
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }

  /// The GPS-quality tail of a detail card.
  ///
  /// Always states the measurement when there is one — an admin reading a
  /// coordinate deserves to know how wide it is — and only adds the sentence
  /// when the dwell was *predominantly* weak, which is the case where the stop
  /// itself might be jitter rather than a visit.
  List<Widget> _accuracyRows(BuildContext context, StopAccuracy quality) {
    if (!quality.isKnown) return const <Widget>[];

    final double? average = quality.averageMetres;
    return <Widget>[
      if (average != null)
        KeyValueRow(
          label: 'GPS accuracy',
          value: '±${average.round()} m average',
          dense: true,
        ),
      if (quality.isRough) ...<Widget>[
        const SizedBox(height: Insets.sm),
        _RoughGpsNote(threshold: accuracy.thresholdLabel),
      ],
    ];
  }
}

/// A "Street View" action for a stop or visit callout.
///
/// Discoverable on purpose: Street View is the obvious question at a stop —
/// "what is actually there?" — so it sits in the card as a labelled button
/// rather than hiding behind a map gesture. Opens an external panorama at the
/// coordinate; no key, no billing, no in-app viewer.
class _StreetViewAction extends StatelessWidget {
  const _StreetViewAction({required this.latitude, required this.longitude});

  final double latitude;
  final double longitude;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Padding(
        padding: const EdgeInsets.only(top: Insets.xs),
        child: TextButton.icon(
          onPressed: () => StreetView.open(context, latitude, longitude),
          icon: const Icon(Icons.streetview_rounded, size: 18),
          label: const Text('Street View'),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: Insets.sm),
            visualDensity: VisualDensity.compact,
          ),
        ),
      ),
    );
  }
}

/// The one sentence that turns a dotted line into information.
///
/// Neutral tone on purpose: a weak fix is a property of the sky and the
/// buildings, not a fault of the employee or the app, and colouring it amber
/// would put a question mark over the person.
class _RoughGpsNote extends StatelessWidget {
  const _RoughGpsNote({required this.threshold});

  final String threshold;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color tone =
        theme.textTheme.bodySmall?.color ?? AppColors.textSecondary;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(Icons.blur_on_rounded, size: 15, color: tone),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            'Most fixes here were weaker than $threshold, so treat this '
            'position as approximate.',
            style: theme.textTheme.bodySmall,
          ),
        ),
      ],
    );
  }
}

/// A derived stop that was never matched to a company.
///
/// Deliberately plainer than [VisitTile]: it is a candidate, not a business
/// event, and the GPS-quality line is the thing that tells a reader whether it
/// is worth chasing.
class _StopTile extends StatelessWidget {
  const _StopTile({
    required this.stop,
    required this.now,
    required this.config,
    required this.quality,
  });

  final StopRecord stop;
  final DateTime now;
  final TrackingConfig config;
  final StopAccuracy quality;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Duration dwell = stop.durationAt(now);
    final bool long = dwell.inMinutes >= config.longStopThresholdMinutes;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: AppColors.warningSoft,
                  borderRadius: BorderRadius.circular(Radii.md),
                ),
                child: const Icon(
                  Icons.pause_rounded,
                  size: 19,
                  color: AppColors.warning,
                ),
              ),
              const SizedBox(width: Insets.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Unlinked stop', style: theme.textTheme.titleMedium),
                    Text(
                      '${Fmt.time(stop.arrival)} · '
                      '${Fmt.duration(dwell)} · '
                      '${Fmt.latLng(stop.latitude, stop.longitude)}',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              if (quality.isRough)
                const StatusBadge(
                  label: 'Rough GPS',
                  tone: BadgeTone.neutral,
                  icon: Icons.blur_on_rounded,
                  dense: true,
                )
              else if (long)
                StatusBadge(
                  label: Fmt.duration(dwell),
                  tone: BadgeTone.danger,
                  icon: Icons.timelapse_rounded,
                  dense: true,
                ),
            ],
          ),
          if (quality.isRough) ...<Widget>[
            const SizedBox(height: Insets.md),
            _RoughGpsNote(
              threshold: '±${config.minAccuracyMetres.round()} m',
            ),
          ],
        ],
      ),
    );
  }
}
