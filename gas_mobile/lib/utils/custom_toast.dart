import 'package:flutter/material.dart';
import 'package:get/get.dart';

class CustomToast {
  static OverlayEntry? _currentOverlay;

  static void show(
    BuildContext context,
    String message, {
    Color baseColor = const Color(0xFF4CAF50),
    IconData? icon,
    Duration duration = const Duration(seconds: 2),
  }) {
    _removeCurrentOverlay();

    OverlayState? overlay;
    try {
      overlay = Overlay.of(context);
    } catch (_) {
      overlay = null;
    }

    // If no overlay is available (rare), fallback to Get.snackbar to avoid crashes
    if (overlay == null) {
      try {
        // Prefer Get (if available) so toast appears in similar place
        // Delay slightly to mimic toast duration
        // ignore: avoid_print
        print('CustomToast: overlay not found, falling back to Get.snackbar');
        Get.snackbar('', message, snackPosition: SnackPosition.TOP, backgroundColor: baseColor, colorText: Colors.white);
        return;
      } catch (_) {
        // Last resort: print message and return
        // ignore: avoid_print
        print('Toast fallback: $message');
        return;
      }
    }

    _currentOverlay = OverlayEntry(
      builder: (ctx) {
        return Positioned(
          top: MediaQuery.of(ctx).padding.top + 12,
          left: 16,
          right: 16,
          child: Material(
            color: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: baseColor,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.15),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[
                    Icon(icon, color: Colors.white),
                    const SizedBox(width: 10),
                  ],
                  Expanded(
                    child: Text(
                      message,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );

    overlay.insert(_currentOverlay!);

    Future.delayed(duration, () {
      _removeCurrentOverlay();
    });
  }

  static void _removeCurrentOverlay() {
    if (_currentOverlay != null) {
      _currentOverlay!.remove();
      _currentOverlay = null;
    }
  }

  // Convenience helpers
  static void success(BuildContext context, String message) {
    // WARNA HIJAU 0xFF4CAF50 - PASTI HIJAU!
    // ignore: avoid_print
    print('🟢🟢🟢 CustomToast.SUCCESS DIPANGGIL - MESSAGE: $message');
    // ignore: avoid_print
    print('🎨 WARNA YANG DIGUNAKAN: 0xFF4CAF50 (HIJAU TERANG)');
    show(
      context,
      message,
      baseColor: const Color(0xFF4CAF50), // HIJAU - Material Green 500
      icon: Icons.check_circle_outline,
    );
  }

  static void error(BuildContext context, String message) {
    // ignore: avoid_print
    print('🔴🔴🔴 CustomToast.ERROR DIPANGGIL - MESSAGE: $message');
    show(
      context,
      message,
      baseColor: Colors.redAccent,
      icon: Icons.error_outline,
    );
  }

  static void warning(BuildContext context, String message) {
    show(
      context,
      message,
      baseColor: Colors.orange,
      icon: Icons.warning_amber_rounded,
    );
  }

  static void info(BuildContext context, String message) {
    show(context, message, baseColor: Colors.blue, icon: Icons.info_outline);
  }
}
