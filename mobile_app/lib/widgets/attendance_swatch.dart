import 'package:flutter/material.dart';

import '../core/theme/app_colors.dart';
import '../data/models/models.dart';

/// The one attendance colour map.
///
/// Lifted out of the calendar's month grid so the employee calendar and the
/// admin attendance matrix cannot drift apart — a day that reads "partial"
/// green on one screen and amber on the other would be worse than either.
class AttendanceSwatch {
  const AttendanceSwatch._();

  /// Background / foreground pair for a cell.
  static (Color bg, Color fg) colors(
    BuildContext context,
    AttendanceStatus? status, {
    bool isFuture = false,
  }) {
    final bool dark = Theme.of(context).brightness == Brightness.dark;
    final Color muted =
        dark ? Colors.white.withValues(alpha: 0.04) : const Color(0xFFF1F4F9);
    final Color mutedText =
        dark ? AppColors.textSecondaryDark : AppColors.textTertiary;

    if (isFuture || status == null) return (muted, mutedText);

    return switch (status) {
      AttendanceStatus.present => (AppColors.successSoft, AppColors.success),
      AttendanceStatus.partial => (
          AppColors.warningSoft,
          const Color(0xFFB45309),
        ),
      AttendanceStatus.absent => (AppColors.dangerSoft, AppColors.danger),
      AttendanceStatus.holiday => (
          AppColors.infoSoft,
          const Color(0xFF0369A1),
        ),
      AttendanceStatus.weekend => (muted, mutedText),
      AttendanceStatus.noData => (muted, mutedText),
    };
  }
}
