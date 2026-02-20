import 'package:flutter/material.dart';
import 'package:tabungan/services/notification_service.dart';

/// Legacy wrapper – delegates to [NotificationService].
void showCustomBanner(
  BuildContext context,
  String message, {
  Color color = Colors.orange,
}) {
  if (color == Colors.redAccent || color == Colors.red) {
    NotificationHelper.showError(message);
  } else if (color == Colors.orange) {
    NotificationHelper.showWarning(message);
  } else {
    NotificationHelper.showSuccess(message);
  }
}
