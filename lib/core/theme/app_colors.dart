import 'package:flutter/material.dart';

/// Single source of truth for colour. Nothing in the feature layer should
/// declare a raw `Color(0x...)` — add it here instead.
class AppColors {
  const AppColors._();

  // Brand
  static const Color primary = Color(0xFF2F6BFF);
  static const Color primaryDark = Color(0xFF1E4FD8);
  static const Color primarySoft = Color(0xFFEAF0FF);

  // Semantic
  static const Color success = Color(0xFF16A34A);
  static const Color successSoft = Color(0xFFE6F6EC);
  static const Color warning = Color(0xFFF59E0B);
  static const Color warningSoft = Color(0xFFFEF3E2);
  static const Color danger = Color(0xFFDC2626);
  static const Color dangerSoft = Color(0xFFFDECEC);
  static const Color info = Color(0xFF0EA5E9);
  static const Color infoSoft = Color(0xFFE4F5FE);

  // Neutrals (light)
  static const Color background = Color(0xFFF4F6FB);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceAlt = Color(0xFFF8FAFC);
  static const Color border = Color(0xFFE6EAF2);
  static const Color textPrimary = Color(0xFF0F172A);
  static const Color textSecondary = Color(0xFF64748B);
  static const Color textTertiary = Color(0xFF94A3B8);

  // Neutrals (dark)
  static const Color backgroundDark = Color(0xFF0B1120);
  static const Color surfaceDark = Color(0xFF141C2E);
  static const Color surfaceAltDark = Color(0xFF1B2438);
  static const Color borderDark = Color(0xFF26314A);
  static const Color textPrimaryDark = Color(0xFFF1F5F9);
  static const Color textSecondaryDark = Color(0xFF94A3B8);

  // Status dots used across employee + admin surfaces
  static const Color statusMoving = success;
  static const Color statusIdle = warning;
  static const Color statusLongStop = danger;
  static const Color statusOffline = Color(0xFF64748B);
}
