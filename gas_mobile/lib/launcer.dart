// ignore_for_file: library_private_types_in_public_api
import 'package:flutter/material.dart';
import 'dart:async';
// import 'package:google_fonts/google_fonts.dart';
import 'introduction.dart'; // relative import -> akan menuju IntroductionPage

// Warna utama oranye
const Color orange = Color(0xFFFF5F0A);

class LauncherPage extends StatefulWidget {
  const LauncherPage({super.key});

  @override
  _LauncherPageState createState() => _LauncherPageState();
}

class _LauncherPageState extends State<LauncherPage>
    with TickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fadeAnim;
  late final Animation<double> _scaleAnim;

  late final AnimationController _circleController;
  late final Animation<double> _circlePulse;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _fadeAnim = CurvedAnimation(parent: _controller, curve: Curves.easeOut);
    _scaleAnim = Tween<double>(
      begin: 0.9,
      end: 1.0,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutBack));
    _controller.forward();

    _circleController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2400),
    )..repeat(reverse: true);
    _circlePulse = Tween<double>(begin: 0.96, end: 1.04).animate(
      CurvedAnimation(parent: _circleController, curve: Curves.easeInOut),
    );

    openSplashScreen();
  }

  // Setelah delay, pindah ke IntroductionPage (pushReplacement)
  Future<void> openSplashScreen() async {
    await Future.delayed(const Duration(seconds: 4));
    if (!mounted) return;
    // Always show the IntroductionPage after the launcher/splash.
    // IntroductionPage is responsible for setting onboarding preference
    // and navigating to LoginPage when appropriate.
    Navigator.of(
      context,
    ).pushReplacement(_animatedRoute(const IntroductionPage()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          // Lingkaran kecil kiri atas
          Positioned(
            left: 18,
            top: 68,
            child: ScaleTransition(
              scale: _circlePulse,
              child: _orangeCircle(62, orange),
            ),
          ),
          // Lingkaran besar kanan atas
          Positioned(
            right: -18,
            top: -32,
            child: ScaleTransition(
              scale: _circlePulse,
              child: _orangeCircle(160, orange),
            ),
          ),

          // Logo + loading (tengah layar)
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                FadeTransition(
                  opacity: _fadeAnim,
                  child: ScaleTransition(
                    scale: _scaleAnim,
                    child: Image.asset(
                      'assets/logo.png',
                      width: 320,
                      fit: BoxFit.contain,
                    ),
                  ),
                ),
                const SizedBox(height: 32),
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 8,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                  child: const SizedBox(
                    width: 50,
                    height: 50,
                    child: CircularProgressIndicator(
                      strokeWidth: 5,
                      valueColor: AlwaysStoppedAnimation<Color>(orange),
                      backgroundColor: Color(0xFFFFE0CC),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // 🧡 Footer sama seperti login.dart
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              height: 70,
              width: double.infinity,
              decoration: const BoxDecoration(
                color: orange,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(300),
                  topRight: Radius.circular(300),
                ),
              ),
              alignment: Alignment.center,
              child: Text(
                '© PT. Gusti Global Group',
                style: TextStyle(
                  fontFamily: 'Roboto',
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                  letterSpacing: 0.8,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _orangeCircle(double size, Color color) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            // withValues bukan metode yang ada; gunakan withOpacity
            color: Colors.black.withOpacity(0.2),
            offset: const Offset(0, 4),
            blurRadius: 12,
            spreadRadius: 0,
          ),
          BoxShadow(
            color: color.withOpacity(0.35),
            offset: const Offset(0, 8),
            blurRadius: 24,
            spreadRadius: -4,
          ),
          BoxShadow(
            color: color.withOpacity(0.2),
            offset: const Offset(0, 0),
            blurRadius: 16,
            spreadRadius: 2,
          ),
        ],
      ),
    );
  }

  PageRouteBuilder _animatedRoute(Widget page) {
    return PageRouteBuilder(
      transitionDuration: const Duration(milliseconds: 450),
      pageBuilder: (context, animation, secondaryAnimation) => page,
      transitionsBuilder: (context, animation, secondaryAnimation, child) {
        final fade = CurvedAnimation(parent: animation, curve: Curves.easeOut);
        final scale = Tween<double>(
          begin: 0.98,
          end: 1.0,
        ).animate(CurvedAnimation(parent: animation, curve: Curves.easeOut));
        return FadeTransition(
          opacity: fade,
          child: ScaleTransition(scale: scale, child: child),
        );
      },
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    _circleController.dispose();
    super.dispose();
  }
}
