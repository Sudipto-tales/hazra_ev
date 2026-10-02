import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'dimens.dart';

/// Builds the light + dark [ThemeData]. Feature widgets read colours through
/// `Theme.of(context)` wherever possible so the dark theme stays free.
class AppTheme {
  const AppTheme._();

  static ThemeData light() => _build(Brightness.light);
  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final bool isDark = brightness == Brightness.dark;

    final ColorScheme scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: brightness,
    ).copyWith(
      primary: AppColors.primary,
      surface: isDark ? AppColors.surfaceDark : AppColors.surface,
      error: AppColors.danger,
    );

    final Color textPrimary =
        isDark ? AppColors.textPrimaryDark : AppColors.textPrimary;
    final Color textSecondary =
        isDark ? AppColors.textSecondaryDark : AppColors.textSecondary;
    final Color border = isDark ? AppColors.borderDark : AppColors.border;
    final Color surface = isDark ? AppColors.surfaceDark : AppColors.surface;

    final TextTheme text = TextTheme(
      displaySmall: TextStyle(
        fontSize: 34,
        height: 1.1,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.6,
        color: textPrimary,
      ),
      headlineSmall: TextStyle(
        fontSize: 22,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.3,
        color: textPrimary,
      ),
      titleLarge: TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.2,
        color: textPrimary,
      ),
      titleMedium: TextStyle(
        fontSize: 15.5,
        fontWeight: FontWeight.w600,
        color: textPrimary,
      ),
      bodyLarge: TextStyle(fontSize: 15, height: 1.45, color: textPrimary),
      bodyMedium: TextStyle(fontSize: 13.5, height: 1.45, color: textSecondary),
      bodySmall: TextStyle(fontSize: 12, height: 1.4, color: textSecondary),
      labelLarge: TextStyle(
        fontSize: 14.5,
        fontWeight: FontWeight.w600,
        color: textPrimary,
      ),
      labelSmall: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.4,
        color: textSecondary,
      ),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor:
          isDark ? AppColors.backgroundDark : AppColors.background,
      textTheme: text,
      // dividerColor feeds `context.lineColor`; dividerTheme styles the widget.
      dividerColor: border,
      dividerTheme: DividerThemeData(color: border, space: 1, thickness: 1),
      appBarTheme: AppBarTheme(
        backgroundColor: isDark ? AppColors.backgroundDark : AppColors.background,
        foregroundColor: textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleLarge,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(Sizes.touchTarget),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Radii.md),
          ),
          textStyle: const TextStyle(
            fontSize: 15.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(Sizes.touchTarget),
          side: BorderSide(color: border),
          foregroundColor: textPrimary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Radii.md),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary,
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Insets.lg,
          vertical: Insets.lg,
        ),
        hintStyle: text.bodyMedium,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.md),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.md),
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Radii.md),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.6),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: isDark ? AppColors.surfaceAltDark : AppColors.surfaceAlt,
        side: BorderSide(color: border),
        labelStyle: text.bodySmall!.copyWith(fontWeight: FontWeight.w600),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Radii.pill),
        ),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(Radii.xl)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AppColors.primarySoft,
        height: 68,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (Set<WidgetState> states) => TextStyle(
            fontSize: 11.5,
            fontWeight: FontWeight.w600,
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : textSecondary,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (Set<WidgetState> states) => IconThemeData(
            size: 24,
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : textSecondary,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: isDark ? AppColors.surfaceAltDark : AppColors.textPrimary,
        contentTextStyle: const TextStyle(color: Colors.white, fontSize: 13.5),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Radii.md),
        ),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: textSecondary,
        titleTextStyle: text.titleMedium,
        subtitleTextStyle: text.bodySmall,
      ),
    );
  }
}
