import 'package:flutter/material.dart';
import '../utils/constants.dart';
import '../utils/page_transitions.dart';

/// A full-screen branded cover shown briefly after a login/logout/register
/// action, before automatically fading into [nextScreen]. Used instead of a
/// popup/dialog so the transition takes over the whole screen.
class AuthTransitionScreen extends StatefulWidget {
  final String message;
  final IconData icon;
  final Widget nextScreen;

  const AuthTransitionScreen({
    super.key,
    required this.message,
    required this.icon,
    required this.nextScreen,
  });

  @override
  State<AuthTransitionScreen> createState() => _AuthTransitionScreenState();
}

class _AuthTransitionScreenState extends State<AuthTransitionScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fade;
  late final Animation<double> _iconScale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );
    _fade = CurvedAnimation(parent: _controller, curve: Curves.easeOut);
    _iconScale = CurvedAnimation(parent: _controller, curve: Curves.elasticOut);
    _controller.forward();

    Future.delayed(const Duration(milliseconds: 1100), () {
      if (mounted) {
        Navigator.of(context).pushReplacement(fadeSlideRoute(widget.nextScreen));
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kAccentColor,
      body: Center(
        child: FadeTransition(
          opacity: _fade,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ScaleTransition(
                scale: _iconScale,
                child: Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(widget.icon, color: Colors.white, size: 56),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                widget.message,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
