import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../utils/constants.dart';

/// The app's branding mark — shown on the login screen and reused wherever
/// else the app logo should appear (e.g. onboarding) so it stays consistent.
/// Mirrors the "Perfect Nails — Wellness & Aesthetics" wordmark reference.
class AppLogo extends StatelessWidget {
  final double fontSize;

  const AppLogo({super.key, this.fontSize = 34});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        RichText(
          text: TextSpan(
            children: [
              TextSpan(
                text: 'Perfect',
                style: GoogleFonts.playfairDisplay(
                  fontSize: fontSize,
                  fontWeight: FontWeight.w700,
                  fontStyle: FontStyle.italic,
                  color: kPrimaryColor,
                ),
              ),
              TextSpan(
                text: 'Nails',
                style: GoogleFonts.alexBrush(
                  fontSize: fontSize * 1.4,
                  color: kPrimaryColor,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        Text(
          "WELLNESS & AESTHETICS",
          style: GoogleFonts.montserrat(
            fontSize: fontSize * 0.24,
            fontWeight: FontWeight.w500,
            letterSpacing: 3,
            color: kPrimaryColor,
          ),
        ),
      ],
    );
  }
}
