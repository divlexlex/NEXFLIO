import 'package:flutter/material.dart';
import 'constants.dart';

// Dark palette lives in constants.dart (kDarkBackground / kDarkSurface / …)
// so the mutable surface tokens and the ThemeData stay in sync.

/// Theme-aware colour helpers so screens can support light + dark without
/// hard-coding the palette. New screens use these; older screens fall back to
/// the light constants.
extension AppColors on BuildContext {
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
  Color get appBg => Theme.of(this).scaffoldBackgroundColor;
  Color get appSurface => Theme.of(this).colorScheme.surface;
  Color get appText => Theme.of(this).colorScheme.onSurface;
  Color get appSubtle => appText.withOpacity(0.6);
  Color get appBorder => isDark ? kDarkBorder : kSecondaryColor;
  Color get appPrimary => Theme.of(this).colorScheme.primary;
}

ThemeData nexflioTheme() => _buildTheme(Brightness.light);
ThemeData nexflioDarkTheme() => _buildTheme(Brightness.dark);

ThemeData _buildTheme(Brightness brightness) {
  final isDark = brightness == Brightness.dark;
  final base = isDark ? ThemeData.dark(useMaterial3: true) : ThemeData.light(useMaterial3: true);

  final bg = isDark ? kDarkBackground : kLightBackground;
  final surface = isDark ? kDarkSurface : kLightCard;
  final text = isDark ? kDarkText : kLightText;
  final primary = isDark ? kDarkPrimary : kPrimaryColor;
  final border = isDark ? kDarkBorder : kLightSecondary;

  return base.copyWith(
    scaffoldBackgroundColor: bg,
    primaryColor: primary,
    colorScheme: (isDark ? const ColorScheme.dark() : const ColorScheme.light()).copyWith(
      primary: primary,
      secondary: kAccentColor,
      surface: surface,
      onSurface: text,
      brightness: brightness,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: bg,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      iconTheme: IconThemeData(color: text),
      titleTextStyle: TextStyle(
        color: text,
        fontFamily: kHeadingFont,
        fontSize: 20,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: base.textTheme.copyWith(
      displaySmall: base.textTheme.displaySmall?.copyWith(
        fontFamily: kHeadingFont, color: text, fontWeight: FontWeight.w700),
      headlineMedium: base.textTheme.headlineMedium?.copyWith(
        fontFamily: kHeadingFont, color: text, fontWeight: FontWeight.w700),
      headlineSmall: base.textTheme.headlineSmall?.copyWith(
        fontFamily: kHeadingFont, color: text, fontWeight: FontWeight.w700),
      titleLarge: base.textTheme.titleLarge?.copyWith(
        fontFamily: kHeadingFont, color: text, fontWeight: FontWeight.w700),
    ),
    cardTheme: CardThemeData(
      color: surface,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: border),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: kAccentColor,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: text,
        side: BorderSide(color: border),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: primary),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      hintStyle: TextStyle(color: text.withOpacity(0.5)),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: primary, width: 2),
      ),
    ),
    dividerTheme: DividerThemeData(color: border),
  );
}
