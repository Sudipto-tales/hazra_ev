import 'package:flutter/material.dart';

/// Convenience accessors so widgets don't reach into ThemeData internals whose
/// shape changes between Flutter versions.
extension AppThemeX on BuildContext {
  ThemeData get theme => Theme.of(this);

  /// Background for every card-like surface.
  Color get cardColor => Theme.of(this).colorScheme.surface;

  /// Hairline border used on cards, dividers and grouped lists.
  Color get lineColor => Theme.of(this).dividerColor;

  bool get isDark => Theme.of(this).brightness == Brightness.dark;
}
