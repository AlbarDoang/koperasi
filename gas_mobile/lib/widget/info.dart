import 'package:tabungan/services/notification_service.dart';

class Info {
  static void snackbar(String message) {
    NotificationService.showSuccess(message);
  }
}
