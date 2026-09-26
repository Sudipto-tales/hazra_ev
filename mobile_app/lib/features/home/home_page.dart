import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/dimens.dart';
import '../../core/theme/theme_ext.dart';
import '../../core/utils/formatters.dart';
import '../../data/models/models.dart';
import '../../state/app_scope.dart';
import '../../state/tracking_controller.dart';
import '../../widgets/app_card.dart';
import '../../widgets/avatar.dart';
import '../../widgets/stat_tile.dart';
import '../../widgets/states.dart';
import '../../widgets/status_badge.dart';
import '../reports/create_report_page.dart';
import '../reports/report_detail_page.dart';
import '../shell/main_shell.dart';
import 'activity_page.dart';
import 'widgets/activity_timeline.dart';
import 'widgets/day_closeout_sheet.dart';
import 'widgets/day_dialogs.dart';
import 'widgets/session_card.dart';
import 'widgets/tracking_sheet.dart';
import 'widgets/visit_tile.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  bool _loading = true;
  Employee? _me;
  Map<String, String> _reportTitles = const <String, String>{};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  /// Everything on this screen that [TrackingController] does not already
  /// carry: who the employee is, the company directory the visit tiles read
  /// ids against, and today's report titles so a tile can label its rows.
  ///
  /// Kept separate from the tracking load on purpose. The session card and the
  /// summary grid are the reason this tab exists and must not sit behind a
  /// profile fetch, so a failure here costs a name in the header — not the
  /// day's tracking.
  Future<void> _load() async {
    setState(() => _loading = true);

    final AppScope scope = AppScope.of(context);
    try {
      // The tiles look names up synchronously in `build`, so the directory has
      // to be warm before the first visit renders. It caches, so a second call
      // on refresh is free. It also swallows its own errors — an unresolved id
      // shows a placeholder rather than taking Home down.
      await scope.companies.ensureLoaded();

      final Employee me = await scope.repository.profile();
      final List<VisitReport> todaysReports =
          await scope.repository.reports(date: DateTime.now());
      if (!mounted) return;

      setState(() {
        _me = me;
        _reportTitles = <String, String>{
          for (final VisitReport r in todaysReports)
            r.id: '${r.title} · ${Fmt.time(r.submittedAt)}',
        };
        _loading = false;
      });
    } catch (_) {
      // A null [_me] with [_loading] false *is* the failure: the header
      // renders its own retry strip for it. There is no separate error field
      // because nothing else on this screen keys off one.
      if (!mounted) return;
      setState(() {
        _me = null;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppScope scope = AppScope.of(context);

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: ListenableBuilder(
          listenable: scope.tracking,
          builder: (BuildContext context, _) {
            final TrackingController c = scope.tracking;

            if (c.isLoading) {
              return const Padding(
                padding: EdgeInsets.all(Insets.lg),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    SkeletonBox(width: 160, height: 18),
                    SizedBox(height: Insets.xl),
                    SkeletonBox(height: 210, radius: Radii.xl),
                    SizedBox(height: Insets.xl),
                    LoadingCards(count: 2),
                  ],
                ),
              );
            }

            if (c.error != null && c.snapshot == null) {
              return ErrorState(onRetry: c.load);
            }

            return RefreshIndicator(
              // Pull-to-refresh means the whole screen, not just tracking.
              onRefresh: () =>
                  Future.wait<void>(<Future<void>>[c.refresh(), _load()]),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                  Insets.lg,
                  Insets.sm,
                  Insets.lg,
                  Insets.xxxl,
                ),
                children: <Widget>[
                  _HomeHeader(
                    employee: _me,
                    loading: _loading,
                    onRetry: _load,
                  ),
                  const SizedBox(height: Insets.lg),
                  if (c.locationHealth != LocationHealth.ok ||
                      c.status.isDegraded) ...<Widget>[
                    AlertBanner(
                      icon: Icons.warning_amber_rounded,
                      tone: c.locationHealth.blocksStart
                          ? AppColors.danger
                          : AppColors.warning,
                      title: c.locationHealth.title,
                      message: c.locationHealth.message,
                      actionLabel: 'Details',
                      onAction: () => showTrackingSheet(context),
                    ),
                    const SizedBox(height: Insets.lg),
                  ],
                  if (c.autoClosedAt != null) ...<Widget>[
                    AlertBanner(
                      icon: Icons.timer_off_rounded,
                      tone: AppColors.info,
                      title: 'Session closed automatically',
                      message:
                          'GPS and internet were both off at ${Fmt.time(c.autoClosedAt)}, '
                          'so your session was closed. Your day is still open — '
                          'start a new session whenever you are ready.',
                      actionLabel: 'Dismiss',
                      onAction: c.acknowledgeAutoClose,
                    ),
                    const SizedBox(height: Insets.lg),
                  ],
                  SessionCard(
                    controller: c,
                    onStartDay: () => _startDay(context, c),
                    onEndDay: () => _endDay(context, c),
                    onPause: () => _pause(context, c),
                    onTrackingTap: () => showTrackingSheet(context),
                  ),
                  if (c.closeout != null) ...<Widget>[
                    const SizedBox(height: Insets.lg),
                    _CloseoutRecap(closeout: c.closeout!),
                  ],
                  const SizedBox(height: Insets.xl),
                  const _QuickActions(),
                  const SectionHeader(
                    title: "Today's summary",
                    padding: EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
                  ),
                  _SummaryGrid(controller: c),
                  SectionHeader(
                    title: 'Company visits',
                    subtitle: '${c.snapshot?.visits.length ?? 0} today',
                    padding: const EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
                  ),
                  _VisitsBlock(controller: c, reportTitles: _reportTitles),
                  SectionHeader(
                    title: 'Activity',
                    subtitle: 'Where you went and what you submitted',
                    actionLabel: 'View all',
                    onAction: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => ActivityPage(
                          events: c.snapshot?.activity ?? const <ActivityEvent>[],
                        ),
                      ),
                    ),
                    padding: const EdgeInsets.fromLTRB(2, Insets.xxl, 0, Insets.md),
                  ),
                  _ActivityBlock(controller: c),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _startDay(BuildContext context, TrackingController c) async {
    final StartDayResult result = await c.startDay();
    if (!context.mounted) return;

    switch (result.outcome) {
      case StartDayOutcome.started:
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Day started at ${Fmt.time(c.joiningTime)} · tracking is on',
            ),
          ),
        );
      case StartDayOutcome.blocked:
        await showLocationBlockedDialog(
          context,
          result.health,
          onEnable: () async {
            await AppScope.of(context)
                .locationService
                .requestPermission(background: true);
            if (!context.mounted) return;
            await _startDay(context, c);
          },
        );
      case StartDayOutcome.offline:
        await showOfflineDialog(context, forEndDay: false);
      case StartDayOutcome.dayLocked:
        await showDayLockedDialog(context, result.dayState);
      case StartDayOutcome.alreadyRunning:
        break;
    }
  }

  /// Gate first, form second, submit third.
  ///
  /// The checks run *before* the form opens so nobody fills in six fields only
  /// to be told they cannot submit. And the day is only marked closed here once
  /// the server has accepted it — a failure leaves the day open on both sides,
  /// which is the safe direction to fail in.
  Future<void> _endDay(BuildContext context, TrackingController c) async {
    final EndDayGate gate = await c.checkEndDay();
    if (!context.mounted) return;

    switch (gate.block) {
      case EndDayBlock.alreadyClosed:
        await showDayLockedDialog(context, c.dayState);
        return;
      case EndDayBlock.offline:
        await showOfflineDialog(context, forEndDay: true);
        return;
      case EndDayBlock.locationOff:
        await showLocationBlockedDialog(
          context,
          gate.health,
          note: 'Your day cannot be closed without a valid GPS fix.',
          onEnable: () async {
            await AppScope.of(context)
                .locationService
                .requestPermission(background: true);
            if (!context.mounted) return;
            await _endDay(context, c);
          },
        );
        return;
      case EndDayBlock.none:
        break;
    }

    if (!context.mounted) return;
    final DayCloseoutDraft? draft = await showDayCloseoutSheet(
      context,
      summary: c.summary,
      workedToday: c.workedToday,
      sessionCount: c.sessions.length,
      joiningTime: c.joiningTime,
      visitsDetected: c.snapshot?.visits.length ?? c.summary.companiesVisited,
      endedAt: gate.fix?.recordedAt ?? DateTime.now(),
    );
    if (draft == null || !context.mounted) return;

    try {
      await c.endDay(draft, finalFix: gate.fix);
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: AppColors.danger,
          content: Text(
            'Could not close your day. It is still open — try again.',
          ),
        ),
      );
      return;
    }

    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Day closed · ${Fmt.duration(c.workedToday)} worked, '
          '${Fmt.km(c.summary.distanceKm)} tracked',
        ),
      ),
    );
  }

  Future<void> _pause(BuildContext context, TrackingController c) async {
    await c.pauseSession();
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Session closed · start a new one when you resume'),
      ),
    );
  }
}

/// Greeting, date and the notification bell.
///
/// Carries its own loading and error state rather than gating the whole tab:
/// the profile call is the only thing on Home that this strip depends on.
class _HomeHeader extends StatelessWidget {
  const _HomeHeader({
    required this.employee,
    required this.loading,
    required this.onRetry,
  });

  /// Null while [loading], and after a failed load.
  final Employee? employee;
  final bool loading;
  final VoidCallback onRetry;

  String get _greeting {
    final int h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Employee? me = employee;

    return Row(
      children: <Widget>[
        if (loading)
          const SkeletonBox(
            width: Sizes.avatarMd,
            height: Sizes.avatarMd,
            radius: Sizes.avatarMd / 2,
          )
        else
          ProfileAvatar(
            // '?' rather than a stale name: the header would sooner admit it
            // does not know who you are than show the wrong person.
            initials: me?.initials ?? '?',
            imageUrl: me?.avatarUrl,
            size: Sizes.avatarMd,
            statusColor: me == null ? null : AppColors.success,
          ),
        const SizedBox(width: Insets.md),
        Expanded(
          child: loading
              ? const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    SkeletonBox(width: 150, height: 15),
                    SizedBox(height: 8),
                    SkeletonBox(width: 110, height: 11),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      me == null ? _greeting : '$_greeting, ${me.firstName}',
                      style: theme.textTheme.titleLarge,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    if (me == null)
                      // The only failure the strip can report, and the only
                      // one it can fix.
                      InkWell(
                        onTap: onRetry,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            const Icon(Icons.refresh_rounded,
                                size: 13, color: AppColors.danger),
                            const SizedBox(width: 4),
                            Text(
                              'Profile did not load · tap to retry',
                              style: theme.textTheme.bodySmall
                                  ?.copyWith(color: AppColors.danger),
                            ),
                          ],
                        ),
                      )
                    else
                      Text(
                        Fmt.longDate(DateTime.now()),
                        style: theme.textTheme.bodySmall,
                      ),
                  ],
                ),
        ),
        IconButton(
          onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('No new notifications')),
          ),
          style: IconButton.styleFrom(
            backgroundColor: context.cardColor,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(Radii.md),
              side: BorderSide(color: context.lineColor),
            ),
          ),
          icon: const Icon(Icons.notifications_none_rounded, size: 21),
        ),
      ],
    );
  }
}

class _QuickActions extends StatelessWidget {
  const _QuickActions();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: _ActionChip(
            icon: Icons.note_add_outlined,
            label: 'New report',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const CreateReportPage(),
              ),
            ),
          ),
        ),
        const SizedBox(width: Insets.md),
        Expanded(
          child: _ActionChip(
            icon: Icons.description_outlined,
            label: 'My reports',
            onTap: () => ShellScope.maybeOf(context)?.goToTab(1),
          ),
        ),
        const SizedBox(width: Insets.md),
        Expanded(
          child: _ActionChip(
            icon: Icons.my_location_rounded,
            label: 'Tracking',
            onTap: () => showTrackingSheet(context),
          ),
        ),
      ],
    );
  }
}

class _ActionChip extends StatelessWidget {
  const _ActionChip({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.symmetric(vertical: Insets.md),
      child: Column(
        children: <Widget>[
          Icon(icon, size: 21, color: AppColors.primary),
          const SizedBox(height: 6),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.controller});

  final TrackingController controller;

  @override
  Widget build(BuildContext context) {
    final DaySummary s = controller.summary;
    return StatGrid(
      children: <Widget>[
        StatTile(
          icon: Icons.login_rounded,
          value: Fmt.time(controller.joiningTime),
          label: 'Joining time',
          tone: AppColors.success,
        ),
        StatTile(
          icon: Icons.timer_outlined,
          value: Fmt.duration(controller.workedToday),
          label: 'Working time',
          tone: AppColors.primary,
        ),
        StatTile(
          icon: Icons.layers_outlined,
          value: '${controller.sessions.length}',
          label: 'Sessions',
          tone: AppColors.info,
        ),
        StatTile(
          icon: Icons.route_rounded,
          value: Fmt.km(s.distanceKm),
          label: 'Distance',
          tone: AppColors.primary,
        ),
        StatTile(
          icon: Icons.business_rounded,
          value: '${s.companiesVisited}',
          label: 'Companies visited',
          tone: AppColors.warning,
        ),
        StatTile(
          icon: Icons.description_outlined,
          value: '${s.reportsSubmitted}',
          label: 'Reports submitted',
          tone: AppColors.primary,
        ),
        StatTile(
          icon: Icons.pause_circle_outline_rounded,
          value: Fmt.duration(s.stopDuration),
          label: 'Total stop time',
          tone: AppColors.warning,
          caption: 'Longest ${Fmt.duration(s.longestStop)}',
        ),
        StatTile(
          icon: Icons.place_outlined,
          value: '${controller.snapshot?.stops.length ?? 0}',
          label: 'Stops detected',
          tone: AppColors.info,
        ),
      ],
    );
  }
}

class _VisitsBlock extends StatelessWidget {
  const _VisitsBlock({required this.controller, required this.reportTitles});

  final TrackingController controller;

  /// Report id → label for every report filed today, loaded once by the page.
  /// A tile holds ids only, and must not fetch a body of its own.
  final Map<String, String> reportTitles;

  @override
  Widget build(BuildContext context) {
    final List<CompanyVisit> visits =
        controller.snapshot?.visits ?? const <CompanyVisit>[];

    if (visits.isEmpty) {
      return AppCard(
        child: EmptyState(
          icon: Icons.business_outlined,
          title: 'No visits yet',
          message: 'Company visits appear here once a stop is detected at a '
              'customer location.',
        ),
      );
    }

    return Column(
      children: visits
          .map(
            (CompanyVisit v) => Padding(
              padding: const EdgeInsets.only(bottom: Insets.md),
              child: VisitTile(
                visit: v,
                now: controller.now,
                reportTitles: reportTitles,
                onReportTap: (String id) => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ReportDetailPage(reportId: id),
                  ),
                ),
                onAddReport: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => CreateReportPage(visit: v),
                  ),
                ),
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

class _ActivityBlock extends StatelessWidget {
  const _ActivityBlock({required this.controller});

  final TrackingController controller;

  @override
  Widget build(BuildContext context) {
    final List<ActivityEvent> events =
        controller.snapshot?.activity ?? const <ActivityEvent>[];

    if (events.isEmpty) {
      return AppCard(
        child: EmptyState(
          icon: Icons.timeline_rounded,
          title: 'Nothing recorded yet',
          message: controller.status == WorkStatus.notStarted
              ? 'Start your day and your movements, visits and reports will show up here.'
              : 'Your activity will appear as you travel and visit companies.',
        ),
      );
    }

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              StatusBadge.work(controller.status, dense: true),
              const Spacer(),
              Text(
                '${events.length} events',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: Insets.lg),
          ActivityTimeline(
            events: events,
            maxItems: 7,
            onReportTap: (String id) => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => ReportDetailPage(reportId: id),
              ),
            ),
          ),
          if (events.length > 7) ...<Widget>[
            const SizedBox(height: Insets.md),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => ActivityPage(events: events),
                  ),
                ),
                child: Text('View all ${events.length} events'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// What the employee declared when they closed the day, shown back to them.
///
/// The declared and tracked figures sit side by side rather than merged: the
/// employee should see exactly what their manager will see.
class _CloseoutRecap extends StatelessWidget {
  const _CloseoutRecap({required this.closeout});

  final DayCloseout closeout;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.lock_outline_rounded,
                  size: 18, color: AppColors.info),
              const SizedBox(width: Insets.sm),
              Expanded(
                child: Text(
                  'Day closed at ${Fmt.time(closeout.submittedAt)}',
                  style: theme.textTheme.titleMedium?.copyWith(fontSize: 15),
                ),
              ),
              Row(
                children: <Widget>[
                  for (int i = 1; i <= 5; i++)
                    Icon(
                      i <= closeout.rating
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 16,
                      color: i <= closeout.rating
                          ? AppColors.warning
                          : AppColors.textTertiary,
                    ),
                ],
              ),
            ],
          ),
          const Divider(height: Insets.xl),
          KeyValueRow(
            label: 'You reported',
            value: '${Fmt.km(closeout.declaredDistanceKm)} · '
                '${closeout.declaredVisits} visits',
            dense: true,
          ),
          KeyValueRow(
            label: 'GPS tracked',
            value: '${Fmt.km(closeout.measuredDistanceKm)} · '
                '${closeout.measuredVisits} visits',
            dense: true,
            valueColor: closeout.distanceLooksOff ? AppColors.warning : null,
          ),
          if (closeout.tags.isNotEmpty) ...<Widget>[
            const SizedBox(height: Insets.md),
            Wrap(
              spacing: Insets.sm,
              runSpacing: Insets.xs,
              children: <Widget>[
                for (final DayFeedbackTag tag in closeout.tags)
                  StatusBadge(
                    label: tag.label,
                    tone: BadgeTone.neutral,
                    dense: true,
                  ),
              ],
            ),
          ],
          if (closeout.feedback != null) ...<Widget>[
            const SizedBox(height: Insets.md),
            Text(closeout.feedback!, style: theme.textTheme.bodySmall),
          ],
        ],
      ),
    );
  }
}
