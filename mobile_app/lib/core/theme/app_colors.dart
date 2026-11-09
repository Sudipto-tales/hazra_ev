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

  /// Identity colours for the admin map: one stable hue per employee, worn by
  /// their route line and their live trail so an admin learns "the violet one
  /// is Asha" and keeps that across screens and sessions. Resolve an id to a
  /// colour with [employeeColor], never by indexing this list directly.
  ///
  /// This is a **categorical** palette, a different job from the semantic
  /// colours above. Two rules shaped it:
  ///
  /// * **It stays off the status hues.** The marker still carries state in
  ///   green/amber/red (see `LiveMarkerTone`); if a trail could also be a
  ///   saturated green or red, line and pin would argue. So the palette leans
  ///   into blues, violets, teals, pinks and magentas and keeps its two warm
  ///   entries (orange, olive) clearly off the exact `warning`/`success`
  ///   shades. Identity lives on the line, state lives on the pin, and the two
  ///   channels never reach for the same colour.
  /// * **It reads on both plates.** Every hue is mid-toned rather than pale or
  ///   near-black, so it holds up on the light Voyager plate and the dark
  ///   Dark-Matter one without a second list — the trail is drawn with a
  ///   fading alpha gradient, and a washed-out or ink-dark hue would vanish at
  ///   the transparent end.
  ///
  /// Colour-blind-aware, not colour-blind-proof: no categorical palette this
  /// size can be fully unambiguous under deuteranopia, which is the other
  /// reason the legend exists and the reason names ride beside the swatches.
  static const List<Color> trailPalette = <Color>[
    Color(0xFF4263EB), // indigo
    Color(0xFF0CA678), // teal-green
    Color(0xFF7048E8), // violet
    Color(0xFFE64980), // pink
    Color(0xFF1098AD), // cyan
    Color(0xFFF76707), // orange
    Color(0xFF9C36B5), // purple
    Color(0xFF1864AB), // deep blue
    Color(0xFFC2255C), // raspberry
    Color(0xFF5C940D), // olive
  ];

  /// The stable identity colour for [employeeId].
  ///
  /// Deterministic and self-contained: it hashes the id with FNV-1a rather than
  /// `String.hashCode`, because the VM is free to seed `hashCode` per run and a
  /// colour that changed between launches would defeat the entire point of an
  /// identity hue. The same id maps to the same [trailPalette] slot on every
  /// device, every session, forever — which is also what makes it a pure,
  /// unit-testable function with no dependency on load order or roster index.
  static Color employeeColor(String employeeId) =>
      trailPalette[_employeeColorIndex(employeeId)];

  /// Visible for testing: the palette index [employeeColor] resolves to.
  static int employeeColorIndex(String employeeId) =>
      _employeeColorIndex(employeeId);

  static int _employeeColorIndex(String employeeId) {
    // FNV-1a over UTF-16 code units, 32-bit. Masked back to 32 bits each round
    // so it behaves identically on the VM (64-bit ints) and the web (doubles),
    // then reduced to a palette slot. An empty id hashes to the FNV offset
    // basis, which is a defined, stable slot rather than a crash.
    int hash = 0x811c9dc5;
    for (int i = 0; i < employeeId.length; i++) {
      hash ^= employeeId.codeUnitAt(i);
      hash = (hash * 0x01000193) & 0xFFFFFFFF;
    }
    return hash % trailPalette.length;
  }
}
