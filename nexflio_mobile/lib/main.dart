// lib/main.dart
import 'package:flutter/material.dart';
import 'utils/theme.dart';
import 'screens/home/home_screen.dart';

void main() {
  runApp(const NexflioApp());
}

class NexflioApp extends StatelessWidget {
  const NexflioApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'NEXFLIO',
      debugShowCheckedModeBanner: false,
      theme: nexflioTheme(),
      home: const HomeScreen(), // Deretso na sa Home
    );
  }
}
