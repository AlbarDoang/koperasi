import 'package:flutter/material.dart';
import 'package:tabungan/services/notification_service.dart';

/// Legacy wrapper – delegates everything to [NotificationService] which uses
/// the global [scaffoldMessengerKey] and never touches BuildContext / Overlay.
///
/// Kept for backward-compatibility so existing call-sites keep compiling while
/// they are gradually migrated to `NotificationService.showSuccess(msg)` etc.
class CustomToast {
  /// Generic show – maps [baseColor] to the appropriate NotificationService method.
  static void show(
    BuildContext context,
    String message, {
    Color baseColor = const Color(0xFF4CAF50),
    IconData? icon,
    Duration duration = const Duration(seconds: 2),
  }) {
    if (baseColor == Colors.redAccent ||
        baseColor.value == const Color(0xFFE53935).value ||
        baseColor == Colors.red) {
      NotificationService.showError(message);
    } else if (baseColor == Colors.orange ||
        baseColor.value == const Color(0xFFFB8C00).value) {
      NotificationService.showWarning(message);
    } else if (baseColor == Colors.blue ||
        baseColor.value == const Color(0xFF1E88E5).value) {
      NotificationService.showInfo(message);
    } else {
      NotificationService.showSuccess(message);
    }
  }

  static void success(BuildContext context, String message) =>
      NotificationService.showSuccess(message);

  static void error(BuildContext context, String message) =>
      NotificationService.showError(message);

  static void warning(BuildContext context, String message) =>
      NotificationService.showWarning(message);

  static void info(BuildContext context, String message) =>
      NotificationService.showInfo(message);
}
