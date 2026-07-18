// lib/main.dart
import 'package:flutter/material.dart';
import 'utils/theme.dart';
import 'utils/theme_controller.dart';
import 'utils/constants.dart';
import 'screens/home/home_screen.dart';
import 'screens/staff/staff_dashboard_screen.dart';
import 'services/auth_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AuthService.instance.restoreSession();
  await ThemeController.instance.load();
  runApp(const NexflioApp());
}

class NexflioApp extends StatelessWidget {
  const NexflioApp({super.key});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: ThemeController.instance,
      builder: (context, _) {
        return MaterialApp(
          title: kAppName,
          debugShowCheckedModeBanner: false,
          theme: nexflioTheme(),
          darkTheme: nexflioDarkTheme(),
          themeMode: ThemeController.instance.mode,
          // Keep the mutable surface/text tokens in sync with the resolved
          // brightness (covers ThemeMode.system) before any screen builds.
          builder: (context, child) {
            applyThemePalette(Theme.of(context).brightness == Brightness.dark);
            return child ?? const SizedBox.shrink();
          },
          home: _landingForRole(),
        );
      },
    );
  }

  /// Staff land on their dashboard; guests, clients, and any owner/manager who
  /// signs in on mobile land on the client Home (owner/manager work primarily
  /// in the web admin portal).
  Widget _landingForRole() {
    final user = AuthService.instance.currentUser;
    if (user?.roleId == kStaffRoleId) {
      return const StaffDashboardScreen();
    }
    return const HomeScreen();
  }
}
