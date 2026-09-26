import 'dart:ui' show FontFeature;

import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/models.dart';
import '../../../state/tracking_controller.dart';
import '../../../widgets/status_badge.dart';

/// Hero card on Home: live timer, today's session facts and the primary
/// Start Day / End Day action.
class SessionCard extends StatelessWidget {
  const SessionCard({
    super.key,
    required this.controller,
    required this.onStartDay,
    required this.onEndDay,
    required this.onPause,
    required this.onTrackingTap,
  });

  final TrackingController controller;
  final VoidCallback onStartDay;
  final VoidCallback onEndDay;
  final VoidCallback onPause;
  final VoidCallback onTrackingTap;

  @override
  Widget build(BuildContext context) {
    final WorkStatus status = controller.status;
    final bool live = status.isSessionOpen;

    final List<Color> gradient = switch (status) {
      WorkStatus.working => <Color>[AppColors.primary, AppColors.primaryDark],
      WorkStatus.idle => <Color>[const Color(0xFFF59E0B), const Color(0xFFD97706)],
      WorkStatus.locationUnavailable =>
        <Color>[const Color(0xFFEF4444), const Color(0xFFB91C1C)],
      WorkStatus.offline =>
        <Color>[const Color(0xFF64748B), const Color(0xFF475569)],
      WorkStatus.ended =>
        <Color>[const Color(0xFF0EA5E9), const Color(0xFF0369A1)],
      WorkStatus.notStarted =>
        <Color>[const Color(0xFF334155), const Color(0xFF0F172A)],
    };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Insets.xl),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: gradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(Radii.xl),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: gradient.first.withValues(alpha: 0.30),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              _LiveDot(active: live),
              const SizedBox(width: Insets.sm),
              Text(
                status.label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 14.5,
                  letterSpacing: 0.2,
                ),
              ),
              const Spacer(),
              if (live)
                StatusBadge(
                  label: controller.movement.label,
                  tone: BadgeTone.neutral,
                  icon: controller.movement == MovementStatus.moving
                      ? Icons.navigation_rounded
                      : Icons.pause_circle_outline_rounded,
                  onLight: false,
                  dense: true,
                ),
            ],
          ),
          const SizedBox(height: Insets.lg),
          Text(
            live ? Fmt.clock(controller.sessionElapsed) : _idleHeadline(controller),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 40,
              height: 1.05,
              fontWeight: FontWeight.w700,
              letterSpacing: -1.4,
              fontFeatures: <FontFeature>[FontFeature.tabularFigures()],
            ),
          ),
          const SizedBox(height: 2),
          Text(
            live
                ? 'Session ${controller.activeSession?.index ?? 1} running'
                : _idleSubtitle(status),
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.78),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: Insets.xl),
          Row(
            children: <Widget>[
              _HeroStat(
                label: 'Started',
                value: Fmt.time(controller.activeSession?.startTime),
              ),
              _HeroStat(
                label: "Today's joining",
                value: Fmt.time(controller.joiningTime),
              ),
              _HeroStat(
                label: 'Sessions',
                value: '${controller.sessions.length}',
              ),
              _HeroStat(
                label: 'Travel',
                value: Fmt.km(controller.summary.distanceKm),
                last: true,
              ),
            ],
          ),
          const SizedBox(height: Insets.lg),
          InkWell(
            onTap: onTrackingTap,
            borderRadius: BorderRadius.circular(Radii.md),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Row(
                children: <Widget>[
                  Icon(
                    controller.locationHealth == LocationHealth.ok
                        ? Icons.my_location_rounded
                        : Icons.location_disabled_rounded,
                    size: 15,
                    color: Colors.white.withValues(alpha: 0.85),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      controller.lastFix == null
                          ? 'No location recorded yet'
                          : 'Last update ${Fmt.relative(controller.lastFix!.recordedAt, now: controller.now)}'
                              ' · ±${Fmt.metres(controller.lastFix!.accuracy)}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: Colors.white.withValues(alpha: 0.85),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: Insets.lg),
          _Actions(
            status: status,
            locked: controller.isDayLocked,
            busy: controller.isBusy,
            onStartDay: onStartDay,
            onEndDay: onEndDay,
            onPause: onPause,
          ),
        ],
      ),
    );
  }

  String _idleHeadline(TrackingController c) {
    return switch (c.status) {
      WorkStatus.notStarted => 'Ready',
      WorkStatus.ended => Fmt.clock(c.workedToday),
      _ => Fmt.clock(c.workedToday),
    };
  }

  String _idleSubtitle(WorkStatus status) => switch (status) {
        WorkStatus.notStarted => 'Start your day to begin tracking',
        WorkStatus.ended => 'Total worked today · day closed',
        WorkStatus.idle => 'On break · tracking paused',
        _ => '',
      };
}

class _Actions extends StatelessWidget {
  const _Actions({
    required this.status,
    required this.locked,
    required this.busy,
    required this.onStartDay,
    required this.onEndDay,
    required this.onPause,
  });

  final WorkStatus status;

  /// The day is closed server-side. Only an admin can undo that, so the
  /// primary action is dead rather than merely discouraged.
  final bool locked;
  final bool busy;
  final VoidCallback onStartDay;
  final VoidCallback onEndDay;
  final VoidCallback onPause;

  @override
  Widget build(BuildContext context) {
    if (locked || status == WorkStatus.ended) {
      return _WhiteButton(
        label: 'Day completed',
        icon: Icons.check_circle_rounded,
        onPressed: null,
      );
    }

    if (!status.isSessionOpen) {
      return _WhiteButton(
        label: status == WorkStatus.idle ? 'Resume session' : 'Start Day',
        icon: Icons.play_arrow_rounded,
        busy: busy,
        onPressed: onStartDay,
      );
    }

    return Row(
      children: <Widget>[
        Expanded(
          child: _GhostButton(
            label: 'Break',
            icon: Icons.pause_rounded,
            onPressed: onPause,
          ),
        ),
        const SizedBox(width: Insets.md),
        Expanded(
          flex: 2,
          child: _WhiteButton(
            label: 'End Day',
            icon: Icons.stop_circle_outlined,
            busy: busy,
            onPressed: onEndDay,
          ),
        ),
      ],
    );
  }
}

class _WhiteButton extends StatelessWidget {
  const _WhiteButton({
    required this.label,
    required this.icon,
    required this.onPressed,
    this.busy = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: Sizes.primaryActionHeight,
      child: FilledButton.icon(
        onPressed: busy ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: Colors.white,
          foregroundColor: AppColors.textPrimary,
          disabledBackgroundColor: Colors.white.withValues(alpha: 0.55),
          disabledForegroundColor: AppColors.textPrimary.withValues(alpha: 0.6),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Radii.md),
          ),
        ),
        icon: busy
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2.2),
              )
            : Icon(icon, size: 20),
        label: Text(label),
      ),
    );
  }
}

class _GhostButton extends StatelessWidget {
  const _GhostButton({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: Sizes.primaryActionHeight,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.white,
          side: BorderSide(color: Colors.white.withValues(alpha: 0.45)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Radii.md),
          ),
        ),
        icon: Icon(icon, size: 19),
        label: Text(label),
      ),
    );
  }
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({
    required this.label,
    required this.value,
    this.last = false,
  });

  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        decoration: last
            ? null
            : BoxDecoration(
                border: Border(
                  right: BorderSide(color: Colors.white.withValues(alpha: 0.22)),
                ),
              ),
        padding: const EdgeInsets.only(right: Insets.sm),
        margin: const EdgeInsets.only(right: Insets.sm),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 14.5,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.72),
                fontSize: 10.5,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pulsing dot — the immediate "am I being tracked?" signal.
class _LiveDot extends StatefulWidget {
  const _LiveDot({required this.active});
  final bool active;

  @override
  State<_LiveDot> createState() => _LiveDotState();
}

class _LiveDotState extends State<_LiveDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.active) {
      return Container(
        width: 9,
        height: 9,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.65),
          shape: BoxShape.circle,
        ),
      );
    }
    return FadeTransition(
      opacity: Tween<double>(begin: 0.35, end: 1).animate(_c),
      child: Container(
        width: 9,
        height: 9,
        decoration: const BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
        ),
      ),
    );
  }
}
