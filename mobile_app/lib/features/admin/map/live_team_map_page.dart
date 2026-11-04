import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../../core/config/map_config.dart';
import '../../../core/config/tracking_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/api/api_exception.dart';
import '../../../data/models/models.dart';
import '../../../state/app_scope.dart';
import '../../../widgets/auto_refresh.dart';
import '../../../widgets/avatar.dart';
import '../../../widgets/states.dart';
import '../employees/employee_detail_page.dart';
import 'live_marker.dart';
import 'live_team_map.dart';

/// Where the whole field team is, right now.
///
/// A **different screen** from `route_map_page.dart`, which is one employee's
/// finished day on a historical date and must stay that way: the two answer
/// different questions ("what happened?" versus "where is everyone?") and
/// merging them would give both a date picker neither wants.
///
/// Three loops run while this is open, at three cadences, on purpose:
///
/// | Loop | Cadence | Why |
/// | --- | --- | --- |
/// | roster re-read ([AutoRefresh]) | 15 s | the positions themselves |
/// | `POST /tracking/watch` | `ttlSeconds / 2` | keeps devices in live mode |
/// | trail re-read | 1 min | a 30-minute tail does not change in 15 s |
///
/// The watch loop is the only thing that makes the first loop worth running at
/// 15 s: without it the devices are still draining their queues every 300 s
/// and re-polling faster just redraws the same fixes. With it, a device picks
/// up the watch on its next batch and starts posting every 15 s — which is why
/// **entering live mode takes up to one drain interval** and why every marker
/// carries its real fix age (API plan §3.11). A watch is a request for
/// freshness, not a claim of it.
class LiveTeamMapPage extends StatefulWidget {
  const LiveTeamMapPage({super.key});

  @override
  State<LiveTeamMapPage> createState() => _LiveTeamMapPageState();
}

class _LiveTeamMapPageState extends State<LiveTeamMapPage>
    with WidgetsBindingObserver, AutoRefresh<LiveTeamMapPage> {
  /// Narrower than this and the roster becomes a bottom sheet instead of a
  /// side panel. Same widgets either way — the layout changes, not the screen.
  static const double _wideBreakpoint = 900;

  /// A 30-minute trail does not meaningfully change in 15 s, and it is the
  /// larger payload of the two by an order of magnitude.
  static const Duration _trailInterval = Duration(minutes: 1);

  bool _loading = true;
  Object? _error;

  List<TeamMember> _members = const <TeamMember>[];
  Map<String, List<GeoPoint>> _trails = const <String, List<GeoPoint>>{};
  TrackingConfig _config = TrackingConfig.defaults;
  String? _selectedId;

  DateTime? _trailsAt;

  // ----------------------------------------------------------- watch state

  Timer? _watchTimer;
  LiveWatch _watch = LiveWatch.none;

  /// The server has no `/tracking/watch` route — it predates the live work.
  /// Set once on a 404 and never retried: the map keeps working, it simply
  /// says so, and the ages under the markers stop being a surprise.
  bool _watchUnsupported = false;

  /// Last watch attempt failed for a reason that might clear (network, 5xx).
  bool _watchFailing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance
        .addPostFrameCallback((_) => runRefresh(manual: true));
  }

  @override
  void dispose() {
    // There is deliberately no unwatch call (§3.11): an explicit unwatch would
    // have to reach the device, which is the one thing we cannot do. Closing
    // the map stops re-posting and the TTL expires on its own, within a
    // couple of minutes.
    _watchTimer?.cancel();
    _watchTimer = null;
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // AutoRefresh arms and disarms the poll here; the watch loop has to follow
    // it, or a backgrounded map keeps a whole team's phones on a 15 s drain
    // for nothing.
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.resumed) {
      unawaited(_postWatch());
    } else {
      _watchTimer?.cancel();
      _watchTimer = null;
    }
  }

  @override
  Future<bool> onAutoRefresh() => _load();

  // ---------------------------------------------------------------- loading

  Future<bool> _load({bool silent = true}) async {
    if (!silent || _members.isEmpty) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final AppScope scope = AppScope.of(context);
      final List<TeamMember> members = await scope.adminRepository.team();

      // Thresholds, once. They are what the marker rules read (offline window,
      // long-stop threshold) and an admin does not retune them mid-glance, so
      // re-fetching every 15 s would be three requests a minute for a value
      // that has not moved.
      final TrackingConfig config = _members.isEmpty
          ? await scope.adminRepository.config()
          : _config;

      final Map<String, List<GeoPoint>> trails = await _loadTrails(scope);

      if (!mounted) return false;
      setState(() {
        _members = members;
        _config = config;
        _trails = trails;
        _error = null;
        _loading = false;
      });

      // Ids can appear (someone starts their day) and vanish, so the watch is
      // re-posted against whoever is actually on the map now.
      unawaited(_postWatch());
      return true;
    } catch (e) {
      if (!mounted) return false;
      setState(() {
        // A failed poll must not throw away positions that are merely a little
        // old — the age chips already say how old. The error state is for a
        // screen with nothing at all to show.
        if (_members.isEmpty) _error = e;
        _loading = false;
      });
      return false;
    }
  }

  Future<Map<String, List<GeoPoint>>> _loadTrails(AppScope scope) async {
    final DateTime? at = _trailsAt;
    if (at != null &&
        DateTime.now().difference(at) < _trailInterval &&
        _trails.isNotEmpty) {
      return _trails;
    }

    try {
      final List<RouteTrack> tracks =
          await scope.adminRepository.teamRoutes(date: DateTime.now());
      final DateTime cutoff = DateTime.now().subtract(MapConfig.liveTrailWindow);
      final Map<String, List<GeoPoint>> out = <String, List<GeoPoint>>{};

      for (final RouteTrack track in tracks) {
        final List<GeoPoint> recent = <GeoPoint>[];
        for (final LocationLog p in track.points) {
          if (p.recordedAt.isBefore(cutoff)) continue;
          recent.add(GeoPoint(p.latitude, p.longitude));
        }
        if (recent.length >= 2) out[track.employeeId] = recent;
      }

      _trailsAt = DateTime.now();
      return out;
    } catch (e) {
      // A trail is context, not the answer. Losing it must never cost the
      // markers, which are the whole point of the screen.
      debugPrint('[live-map] trail load failed: $e');
      return _trails;
    }
  }

  // ------------------------------------------------------------ watch loop

  /// `POST /tracking/watch` for everyone currently drawable, then schedule the
  /// next post at half the granted TTL.
  ///
  /// One call for the whole map — the endpoint takes a list precisely so that
  /// opening a map of eight is one request rather than eight. Re-posting at
  /// `ttl / 2` means a single dropped call cannot drop the team out of live
  /// mode; the deadline simply gets extended again before it lapses.
  Future<void> _postWatch() async {
    if (!mounted || _watchUnsupported) return;

    final List<String> ids = _members
        .where((TeamMember m) => m.active && m.lastFix != null)
        .map((TeamMember m) => m.employee.id)
        .toList(growable: false);

    if (ids.isEmpty) return;

    try {
      final LiveWatch watch = await AppScope.of(context)
          .adminRepository
          .watchLive(employeeIds: ids);
      if (!mounted) return;
      setState(() {
        _watch = watch;
        _watchFailing = false;
      });
      _scheduleWatch(watch.renewInterval);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isNotFound) {
        // Server predates the watch endpoint. Stop asking — a 404 every minute
        // is noise, and the map is already honest about fix age without it.
        setState(() {
          _watchUnsupported = true;
          _watch = LiveWatch.none;
        });
        _watchTimer?.cancel();
        _watchTimer = null;
        return;
      }
      setState(() => _watchFailing = true);
      _scheduleWatch(const Duration(seconds: 30));
    } catch (e) {
      if (!mounted) return;
      setState(() => _watchFailing = true);
      _scheduleWatch(const Duration(seconds: 30));
    }
  }

  void _scheduleWatch(Duration delay) {
    _watchTimer?.cancel();
    _watchTimer = Timer(delay, () => unawaited(_postWatch()));
  }

  // --------------------------------------------------------------- actions

  void _select(String? id) => setState(() => _selectedId = id);

  void _openEmployee(String employeeId) {
    Navigator.of(context)
        .push(
          MaterialPageRoute<void>(
            builder: (_) => EmployeeDetailPage(employeeId: employeeId),
          ),
        )
        .then((_) {
      if (mounted) unawaited(runRefresh(manual: true));
    });
  }

  // ----------------------------------------------------------------- build

  @override
  Widget build(BuildContext context) {
    final bool wide = MediaQuery.sizeOf(context).width >= _wideBreakpoint;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live team map'),
        actions: <Widget>[
          IconButton(
            onPressed: () => unawaited(runRefresh(manual: true)),
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
          ),
          const SizedBox(width: Insets.sm),
        ],
      ),
      body: _loading && _members.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? ErrorState(onRetry: () => unawaited(_load(silent: false)))
              : wide
                  ? _wideLayout()
                  : _phoneLayout(),
    );
  }

  /// The map, built once and shared by both layouts. Two instances would mean
  /// two cameras, two animation controllers and two sets of markers to keep in
  /// step — and the phone/tablet difference is a layout question, not a
  /// different screen.
  Widget _map() => LiveTeamMap(
        members: _members,
        config: _config,
        clock: autoRefreshClock,
        trails: _trails,
        selectedId: _selectedId,
        onSelect: _select,
      );

  Widget _wideLayout() {
    return Row(
      children: <Widget>[
        Expanded(child: _map()),
        SizedBox(
          width: 340,
          child: DecoratedBox(
            decoration: const BoxDecoration(
              border: Border(left: BorderSide(color: AppColors.border)),
            ),
            child: Column(
              children: <Widget>[
                _StatusStrip(
                  watch: _watch,
                  liveSeconds: _config.liveSyncIntervalSeconds,
                  unsupported: _watchUnsupported,
                  failing: _watchFailing,
                  clock: autoRefreshClock,
                  lastRefreshedAt: () => lastRefreshedAt,
                ),
                Expanded(child: _roster(const EdgeInsets.all(Insets.md))),
              ],
            ),
          ),
        ),
      ],
    );
  }

  /// Full-bleed map with the roster on a sheet at three snap points: a peek
  /// (the status line and one row), half (a scannable list), and nearly full
  /// (the whole team, map still visible above it so the context is never
  /// lost).
  Widget _phoneLayout() {
    return Stack(
      children: <Widget>[
        Positioned.fill(child: _map()),
        DraggableScrollableSheet(
          initialChildSize: 0.3,
          minChildSize: 0.14,
          maxChildSize: 0.85,
          snap: true,
          snapSizes: const <double>[0.14, 0.3, 0.85],
          builder: (BuildContext context, ScrollController controller) {
            final ThemeData theme = Theme.of(context);
            return DecoratedBox(
              decoration: BoxDecoration(
                color: theme.colorScheme.surface,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(Radii.xl),
                ),
                boxShadow: const <BoxShadow>[
                  BoxShadow(
                    color: Color(0x22000000),
                    blurRadius: 12,
                    offset: Offset(0, -2),
                  ),
                ],
              ),
              child: Column(
                children: <Widget>[
                  const _SheetGrip(),
                  _StatusStrip(
                    watch: _watch,
                    liveSeconds: _config.liveSyncIntervalSeconds,
                    unsupported: _watchUnsupported,
                    failing: _watchFailing,
                    clock: autoRefreshClock,
                    lastRefreshedAt: () => lastRefreshedAt,
                  ),
                  Expanded(
                    child: _roster(
                      const EdgeInsets.fromLTRB(
                        Insets.md,
                        0,
                        Insets.md,
                        Insets.xxl,
                      ),
                      controller: controller,
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _roster(EdgeInsets padding, {ScrollController? controller}) {
    final DateTime now = DateTime.now();
    final List<LiveMarker> markers = <LiveMarker>[];
    final List<TeamMember> offMap = <TeamMember>[];

    for (final TeamMember m in _members) {
      final LiveMarker? marker =
          LiveMarker.of(m, config: _config, now: now);
      if (marker == null) {
        offMap.add(m);
      } else {
        markers.add(marker);
      }
    }

    // Loudest first: anything that needs a human, then moving, then settled,
    // then the people with nothing to show.
    markers.sort((LiveMarker a, LiveMarker b) {
      int rank(LiveMarker m) => switch (m.tone) {
            LiveMarkerTone.alert => 0,
            LiveMarkerTone.stale => 1,
            LiveMarkerTone.moving => 2,
            LiveMarkerTone.stopped => 3,
            LiveMarkerTone.lastKnown => 4,
          };
      final int byTone = rank(a).compareTo(rank(b));
      if (byTone != 0) return byTone;
      return a.member.employee.name.compareTo(b.member.employee.name);
    });

    if (markers.isEmpty && offMap.isEmpty) {
      return const EmptyState(
        icon: Icons.groups_outlined,
        title: 'No employees yet',
        message: 'Add a field employee from the Admin tab to get started.',
      );
    }

    return ListView.builder(
      controller: controller,
      padding: padding,
      itemCount: markers.length + (offMap.isEmpty ? 0 : 1),
      itemBuilder: (BuildContext context, int index) {
        if (index == markers.length) {
          return _OffMapNote(members: offMap);
        }
        final LiveMarker marker = markers[index];
        return _RosterRow(
          marker: marker,
          clock: autoRefreshClock,
          selected: marker.employeeId == _selectedId,
          onTap: () => _select(
            marker.employeeId == _selectedId ? null : marker.employeeId,
          ),
          onOpen: () => _openEmployee(marker.employeeId),
        );
      },
    );
  }
}

/// One line that answers "is this screen actually live?" — and is allowed to
/// answer "no".
class _StatusStrip extends StatelessWidget {
  const _StatusStrip({
    required this.watch,
    required this.liveSeconds,
    required this.unsupported,
    required this.failing,
    required this.clock,
    required this.lastRefreshedAt,
  });

  final LiveWatch watch;

  /// `liveSyncIntervalSeconds` as the server reported it — 15 by default, and
  /// 15 as the local fallback on a v7 server, so the number shown is the one
  /// the devices will actually use.
  final int liveSeconds;

  final bool unsupported;
  final bool failing;
  final ValueListenable<DateTime> clock;
  final DateTime? Function() lastRefreshedAt;

  @override
  Widget build(BuildContext context) {
    final String watchLine;
    final Color tone;

    if (unsupported) {
      // Honest about the degradation rather than quietly implying 15 s data.
      watchLine = 'Server has no live watch — fixes arrive on their own '
          'schedule';
      tone = AppColors.warning;
    } else if (failing) {
      watchLine = 'Live watch failed — retrying';
      tone = AppColors.warning;
    } else if (watch.isLive) {
      final int rejected = watch.rejected.length;
      watchLine = 'Watching ${watch.watching.length}'
          '${rejected == 0 ? '' : ' · $rejected unavailable'}'
          ' · ${liveSeconds}s uploads from each device\'s next batch';
      tone = AppColors.success;
    } else {
      watchLine = 'Requesting live mode…';
      tone = AppColors.textTertiary;
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(Insets.lg, Insets.sm, Insets.lg, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          AutoRefreshStamp(clock: clock, lastRefreshedAt: lastRefreshedAt),
          const SizedBox(height: 2),
          Row(
            children: <Widget>[
              Icon(Icons.bolt_rounded, size: 13, color: tone),
              const SizedBox(width: 3),
              Expanded(
                child: Text(
                  watchLine,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontSize: 11.5,
                        color: AppColors.textTertiary,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Insets.sm),
        ],
      ),
    );
  }
}

class _SheetGrip extends StatelessWidget {
  const _SheetGrip();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: Insets.sm),
      child: Container(
        width: 36,
        height: 4,
        decoration: BoxDecoration(
          color: AppColors.border,
          borderRadius: BorderRadius.circular(Radii.pill),
        ),
      ),
    );
  }
}

/// A roster line, carrying exactly what the marker carries: the same colour,
/// the same state label, the same fix age. An admin reading the list and an
/// admin reading the map must never be told two different things.
class _RosterRow extends StatelessWidget {
  const _RosterRow({
    required this.marker,
    required this.clock,
    required this.selected,
    required this.onTap,
    required this.onOpen,
  });

  final LiveMarker marker;
  final ValueListenable<DateTime> clock;
  final bool selected;
  final VoidCallback onTap;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Employee e = marker.member.employee;
    final Color tone = marker.tone.color;

    return Opacity(
      opacity: marker.stale ? 0.75 : 1,
      child: Material(
        color: selected ? AppColors.primarySoft : Colors.transparent,
        borderRadius: BorderRadius.circular(Radii.md),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(Radii.md),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: Insets.sm,
              vertical: Insets.sm,
            ),
            child: Row(
              children: <Widget>[
                ProfileAvatar(
                  initials: e.initials,
                  imageUrl: e.avatarUrl,
                  size: Sizes.avatarSm,
                  statusColor: tone,
                ),
                const SizedBox(width: Insets.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        e.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall,
                      ),
                      // The one line the honesty rule is about. Rebuilt from
                      // the clock so the seconds really count, instead of
                      // freezing at whatever the last poll said.
                      ValueListenableBuilder<DateTime>(
                        valueListenable: clock,
                        builder: (BuildContext context, DateTime now, _) {
                          final String age =
                              Fmt.freshness(marker.fix.recordedAt, now: now);
                          final String dwell = marker.member
                                      .openStopDuration ==
                                  null
                              ? ''
                              : ' · stopped ${Fmt.duration(marker.member.openStopDuration!)}';
                          return Text(
                            '${marker.stateLabel} · fix $age$dwell',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: marker.stale
                                  ? AppColors.statusOffline
                                  : AppColors.textSecondary,
                              fontSize: 11.5,
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: "Open today's detail",
                  onPressed: onOpen,
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Who is deliberately **not** drawn, and why.
///
/// `not_started` has nowhere to go on a map (§3.14), and a member with no fix
/// at all has no coordinate — but silently dropping them from a team screen
/// would leave an admin counting heads and coming up short.
class _OffMapNote extends StatelessWidget {
  const _OffMapNote({required this.members});

  final List<TeamMember> members;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(top: Insets.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Not on the map · ${members.length}',
            style: theme.textTheme.labelLarge
                ?.copyWith(color: AppColors.textTertiary),
          ),
          const SizedBox(height: 2),
          Text(
            members.map((TeamMember m) => m.employee.firstName).join(', '),
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: 2),
          Text(
            'No session started today, or no position reported yet.',
            style: theme.textTheme.bodySmall
                ?.copyWith(color: AppColors.textTertiary),
          ),
        ],
      ),
    );
  }
}
