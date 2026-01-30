import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Utility to completely clear notification cache
void clearNotificationCache() async {
  try {
    final prefs = await SharedPreferences.getInstance();
    
    // Remove all notification-related keys
    await prefs.remove('notifications');
    await prefs.remove('notifications_blacklist');
    await prefs.remove('last_local_notif');
    await prefs.remove('notif_last_sync_time');
    
    if (kDebugMode) {
      debugPrint('[clearNotificationCache] ✅ All notification cache cleared');
    }
  } catch (e) {
    if (kDebugMode) {
      debugPrint('[clearNotificationCache] ❌ Error: $e');
    }
  }
}
