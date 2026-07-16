// lib/main.dart
import 'package:flutter/material.dart';
import 'utils/theme.dart';
import 'utils/constants.dart';
import 'screens/home/home_screen.dart';
import 'services/auth_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AuthService.instance.restoreSession();
  runApp(const NexflioApp());
}

class NexflioApp extends StatelessWidget {
  const NexflioApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: kAppName,
      debugShowCheckedModeBanner: false,
      theme: nexflioTheme(),
      home: const HomeScreen(), // Deretso na sa Home
    );
  }
}
