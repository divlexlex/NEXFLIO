import 'package:flutter/material.dart';
import 'constants.dart';

ThemeData nexflioTheme() {
  return ThemeData(
    scaffoldBackgroundColor: kBackgroundColor,
    primaryColor: kPrimaryColor,
    colorScheme: const ColorScheme.light(
      primary: kPrimaryColor,
      secondary: kSecondaryColor,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: kBackgroundColor,
      elevation: 0,
      iconTheme: IconThemeData(color: kTextColor),
      titleTextStyle: TextStyle(
        color: kTextColor,
        fontSize: 20,
        fontWeight: FontWeight.w600,
      ),
    ),
  );
}
