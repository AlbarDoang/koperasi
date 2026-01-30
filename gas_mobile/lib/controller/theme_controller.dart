import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/material.dart';

class ThemeController extends GetxController {
  static const String _themeKey = 'isDarkMode';

  late SharedPreferences _prefs;
  final RxBool isDarkMode = false.obs;

  @override
  void onInit() async {
    super.onInit();
    _prefs = await SharedPreferences.getInstance();
    _loadTheme();
  }

  void _loadTheme() {
    isDarkMode.value = _prefs.getBool(_themeKey) ?? false;
    _updateTheme();
  }

  void toggleTheme() async {
    isDarkMode.toggle();
    await _prefs.setBool(_themeKey, isDarkMode.value);
    _updateTheme();
  }

  void _updateTheme() {
    Get.changeTheme(isDarkMode.value ? darkTheme : lightTheme);
    Get.changeThemeMode(isDarkMode.value ? ThemeMode.dark : ThemeMode.light);
  }

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: false,
      brightness: Brightness.light,
      primarySwatch: Colors.orange,
      scaffoldBackgroundColor: const Color(0xFFF9F9F9),
      appBarTheme: const AppBarTheme(
        backgroundColor: Color(0xFFFF4C00),
        elevation: 0,
        iconTheme: IconThemeData(color: Colors.white),
        titleTextStyle: TextStyle(
          color: Colors.white,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        color: Colors.white,
        elevation: 2,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      textTheme: const TextTheme(
        bodyLarge: TextStyle(color: Colors.black87),
        bodyMedium: TextStyle(color: Colors.black87),
        bodySmall: TextStyle(color: Colors.black87),
      ),
    );
  }

  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: false,
      brightness: Brightness.dark,
      primarySwatch: Colors.orange,
      scaffoldBackgroundColor: const Color(0xFF121212),
      appBarTheme: const AppBarTheme(
        backgroundColor: Color(0xFF1F1F1F),
        elevation: 0,
        iconTheme: IconThemeData(color: Colors.white),
        titleTextStyle: TextStyle(
          color: Colors.white,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        color: const Color(0xFF1F1F1F),
        elevation: 2,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      textTheme: const TextTheme(
        bodyLarge: TextStyle(color: Colors.white70),
        bodyMedium: TextStyle(color: Colors.white70),
        bodySmall: TextStyle(color: Colors.white70),
      ),
    );
  }
}
