import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Centralized notification service that uses a custom [Overlay] to display
/// notifications at the **top** of the screen **without** requiring
/// a [BuildContext].
///
/// Usage:
/// ```dart
/// NotificationService.showSuccess('Berhasil!');
/// NotificationService.showError('Gagal!');
/// NotificationService.showWarning('Perhatian!');
/// NotificationService.showInfo('Informasi');
/// ```
class NotificationService {
  NotificationService._();

  static OverlayEntry? _currentEntry;
  static Timer? _dismissTimer;

  // ───────────────────── public API ─────────────────────

  static void showSuccess(String message) {
    _show(
      message,
      backgroundColor: const Color(0xFF4CAF50),
      icon: Icons.check_circle_outline,
    );
  }

  static void showError(String message) {
    _show(
      message,
      backgroundColor: const Color(0xFFE53935),
      icon: Icons.error_outline,
    );
  }

  static void showWarning(String message) {
    _show(
      message,
      backgroundColor: const Color(0xFFFB8C00),
      icon: Icons.warning_amber_rounded,
    );
  }

  static void showInfo(String message) {
    _show(
      message,
      backgroundColor: const Color(0xFF1E88E5),
      icon: Icons.info_outline,
    );
  }

  // ───────────────────── internal ─────────────────────

  static void _dismiss() {
    _dismissTimer?.cancel();
    _dismissTimer = null;
    try {
      _currentEntry?.remove();
    } catch (_) {
      // Entry might already be removed (e.g., after navigation).
    }
    _currentEntry = null;
  }

  static void _show(
    String message, {
    required Color backgroundColor,
    required IconData icon,
    Duration duration = const Duration(seconds: 3),
  }) {
    // Find the overlay — try GetX's navigator key first, then fallback.
    OverlayState? overlay;
    try {
      overlay = Get.key.currentState?.overlay;
    } catch (_) {}
    // Fallback: use Get.overlayContext if available.
    if (overlay == null) {
      try {
        final ctx = Get.overlayContext;
        if (ctx != null) {
          overlay = Overlay.of(ctx);
        }
      } catch (_) {}
    }
    if (overlay == null) {
      debugPrint('[NotificationService] overlay is null — skipping "$message"');
      return;
    }

    // Dismiss any currently visible notification.
    _dismiss();

    final entry = OverlayEntry(
      builder: (context) => _TopNotification(
        message: message,
        backgroundColor: backgroundColor,
        icon: icon,
        onDismiss: _dismiss,
      ),
    );

    _currentEntry = entry;
    overlay.insert(entry);

    // Auto-dismiss after duration.
    _dismissTimer = Timer(duration, _dismiss);
  }
}

/// The animated widget rendered inside the [Overlay].
class _TopNotification extends StatefulWidget {
  final String message;
  final Color backgroundColor;
  final IconData icon;
  final VoidCallback onDismiss;

  const _TopNotification({
    required this.message,
    required this.backgroundColor,
    required this.icon,
    required this.onDismiss,
  });

  @override
  State<_TopNotification> createState() => _TopNotificationState();
}

class _TopNotificationState extends State<_TopNotification>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<Offset> _slideAnimation;
  late final Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, -1),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
    _fadeAnimation = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeOut),
    );
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;
    return Positioned(
      top: topPadding + 6,
      left: 14,
      right: 14,
      child: SlideTransition(
        position: _slideAnimation,
        child: FadeTransition(
          opacity: _fadeAnimation,
          child: Dismissible(
            key: UniqueKey(),
            direction: DismissDirection.up,
            onDismissed: (_) => widget.onDismiss(),
            child: GestureDetector(
              onTap: widget.onDismiss,
              child: Material(
                color: Colors.transparent,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 11,
                  ),
                  decoration: BoxDecoration(
                    color: widget.backgroundColor,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: widget.backgroundColor.withAlpha(60),
                        blurRadius: 8,
                        spreadRadius: 0,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Icon(widget.icon, color: Colors.white, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          widget.message,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13.5,
                            fontWeight: FontWeight.w500,
                            height: 1.3,
                            decoration: TextDecoration.none,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
