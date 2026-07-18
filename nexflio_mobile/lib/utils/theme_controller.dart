import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

const _kThemeKey = 'theme_mode';

/// App-wide light/dark mode, persisted to disk. Defaults to following the
/// system setting until the user explicitly picks light or dark.
class ThemeController extends ChangeNotifier {
  ThemeController._();
  static final ThemeController instance = ThemeController._();

  ThemeMode _mode = ThemeMode.system;
  ThemeMode get mode => _mode;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    switch (prefs.getString(_kThemeKey)) {
      case 'light':
        _mode = ThemeMode.light;
        break;
      case 'dark':
        _mode = ThemeMode.dark;
        break;
      default:
        _mode = ThemeMode.system;
    }
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    _mode = mode;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kThemeKey, mode.name);
  }

  /// Convenience toggle used by a simple switch: dark when true, light when false.
  Future<void> setDark(bool isDark) =>
      setMode(isDark ? ThemeMode.dark : ThemeMode.light);
}
