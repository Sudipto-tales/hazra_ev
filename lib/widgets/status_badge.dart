import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../core/theme/dimens.dart';
import '../data/models/models.dart';

/// Tone drives colour so badges stay consistent across every screen.
enum BadgeTone { neutral, positive, warning, danger, info, brand }

extension BadgeToneX on BadgeTone {
  Color get fg => switch (this) {
        BadgeTone.neutral => AppColors.textSecondary,
        BadgeTone.positive => AppColors.success,
        BadgeTone.warning => AppColors.warning,
        BadgeTone.danger => AppColors.danger,
        BadgeTone.info => AppColors.info,
        BadgeTone.brand => AppColors.primary,
      };

  Color get bg => switch (this) {
        BadgeTone.neutral => const Color(0x1464748B),
        BadgeTone.positive => AppColors.successSoft,
        BadgeTone.warning => AppColors.warningSoft,
        BadgeTone.danger => AppColors.dangerSoft,
        BadgeTone.info => AppColors.infoSoft,
        BadgeTone.brand => AppColors.primarySoft,
      };
}

class StatusBadge extends StatelessWidget {
  const StatusBadge({
    super.key,
    required this.label,
    this.tone = BadgeTone.neutral,
    this.icon,
    this.showDot = false,
    this.dense = false,
    this.onLight = true,
  });

  /// Maps the workday status to its badge in one place.
  factory StatusBadge.work(WorkStatus status, {bool dense = false}) {
    final BadgeTone tone = switch (status) {
      WorkStatus.working => BadgeTone.positive,
      WorkStatus.idle => BadgeTone.warning,
      WorkStatus.locationUnavailable => BadgeTone.danger,
      WorkStatus.offline => BadgeTone.neutral,
      WorkStatus.ended => BadgeTone.info,
      WorkStatus.notStarted => BadgeTone.neutral,
    };
    return StatusBadge(
      label: status.label,
      tone: tone,
      showDot: true,
      dense: dense,
    );
  }

  factory StatusBadge.report(ReportStatus status, {bool dense = false}) {
    final BadgeTone tone = switch (status) {
      ReportStatus.submitted => BadgeTone.positive,
      ReportStatus.reviewed => BadgeTone.brand,
      ReportStatus.queued || ReportStatus.uploading => BadgeTone.warning,
      ReportStatus.failed => BadgeTone.danger,
      ReportStatus.draft => BadgeTone.neutral,
    };
    final IconData icon = switch (status) {
      ReportStatus.submitted => Icons.check_circle_outline,
      ReportStatus.reviewed => Icons.verified_outlined,
      ReportStatus.queued => Icons.schedule_outlined,
      ReportStatus.uploading => Icons.cloud_upload_outlined,
      ReportStatus.failed => Icons.error_outline,
      ReportStatus.draft => Icons.edit_note_outlined,
    };
    return StatusBadge(
      label: status.label,
      tone: tone,
      icon: icon,
      dense: dense,
    );
  }

  /// Admin verdict on a report. Distinct from [StatusBadge.report], which
  /// shows the upload state.
  factory StatusBadge.review(ReviewDecision decision, {bool dense = false}) {
    final BadgeTone tone = switch (decision) {
      ReviewDecision.approved => BadgeTone.positive,
      ReviewDecision.rejected => BadgeTone.danger,
      ReviewDecision.pending => BadgeTone.warning,
    };
    final IconData icon = switch (decision) {
      ReviewDecision.approved => Icons.verified_outlined,
      ReviewDecision.rejected => Icons.undo_rounded,
      ReviewDecision.pending => Icons.hourglass_empty_rounded,
    };
    return StatusBadge(
      label: decision.label,
      tone: tone,
      icon: icon,
      dense: dense,
    );
  }

  factory StatusBadge.attendance(AttendanceStatus status) {
    final BadgeTone tone = switch (status) {
      AttendanceStatus.present => BadgeTone.positive,
      AttendanceStatus.partial => BadgeTone.warning,
      AttendanceStatus.absent => BadgeTone.danger,
      AttendanceStatus.holiday => BadgeTone.info,
      AttendanceStatus.weekend => BadgeTone.neutral,
      AttendanceStatus.noData => BadgeTone.neutral,
    };
    return StatusBadge(label: status.label, tone: tone, showDot: true);
  }

  final String label;
  final BadgeTone tone;
  final IconData? icon;
  final bool showDot;
  final bool dense;

  /// Set false when the badge sits on a coloured hero surface.
  final bool onLight;

  @override
  Widget build(BuildContext context) {
    final Color fg = onLight ? tone.fg : Colors.white;
    final Color bg = onLight ? tone.bg : Colors.white.withValues(alpha: 0.18);

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: dense ? Insets.sm : Insets.md,
        vertical: dense ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(Radii.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (showDot) ...<Widget>[
            Container(
              width: 7,
              height: 7,
              decoration: BoxDecoration(color: fg, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
          ] else if (icon != null) ...<Widget>[
            Icon(icon, size: dense ? 12 : 14, color: fg),
            const SizedBox(width: 5),
          ],
          Text(
            label,
            style: TextStyle(
              color: fg,
              fontSize: dense ? 11 : 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }
}
