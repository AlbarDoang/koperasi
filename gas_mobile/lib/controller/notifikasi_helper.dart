import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

class NotifikasiHelper {
  // Notifier for UI to listen to changes in notifications store.
  static final ValueNotifier<int> onNotificationsChanged = ValueNotifier<int>(0);

  /// Fetch latest notifications from server and save *only* transaction notifications.
  /// If fetching fails, keep existing local notifications to avoid showing an empty list.
  static bool _isExcludedNotification(Map<String, dynamic> n) {
    final title = (n['title'] ?? '').toString().toLowerCase();
    final msg = (n['message'] ?? '').toString().toLowerCase();
    final titleOrig = (n['title'] ?? '').toString();
    final type = (n['type'] ?? '').toString();

    // Exclude cashback examples and various "processing/waiting for verification" messages per product request
    if (title.contains('cashback') || msg.contains('cashback')) return true;
    
    // ALWAYS ALLOW WITHDRAWAL RESULTS - These are critical decision notifications that MUST be shown
    // Check for both title keywords for approval/rejection
    final titleLowerForDecision = titleOrig.toLowerCase();
    if ((titleLowerForDecision.contains('pencairan disetujui') || titleLowerForDecision.contains('pencairan ditolak')) ||
        (titleLowerForDecision.contains('withdrawal') && (titleLowerForDecision.contains('approved') || titleLowerForDecision.contains('rejected') || titleLowerForDecision.contains('disetujui') || titleLowerForDecision.contains('ditolak')))) {
      return false;  // ALWAYS SHOW withdrawal decision notifications
    }
    
    // Exception: Always allow tabungan result notifications (e.g., 'Setoran Tabungan Disetujui', 'Setoran Tabungan Ditolak',
    // or other variations like 'Setoran tabungan berhasil') — match using lowercase contains so variants are accepted
    final titleLower = titleOrig.toLowerCase();
    if (titleLower.contains('setoran') && (titleLower.contains('berhasil') || titleLower.contains('ditolak') || titleLower.contains('disetujui'))) return false;

    // Allow explicit submission notifications for setoran (e.g., 'Pengajuan Setoran Tabungan') even if they contain
    // words like 'menunggu' or 'verifikasi' so the user sees a clear confirmation right after submitting.
    if (titleLower.contains('pengajuan setoran')) return false;

    // FIX: Allow 'sedang diproses' for withdrawal notifications (e.g., 'Permintaan Pencairan Sedang Diproses')
    // These are important transaction status updates that users should see immediately.
    // Only exclude generic processing/waiting messages for non-loan, non-withdrawal types.
    if (titleLower.contains('pencairan') || titleLower.contains('withdrawal')) return false;

    // Allow 'sedang diproses' for loan submissions so users see "Pengajuan sedang diproses" notices
    // but filter them for other non-loan, non-withdrawal types (e.g., topup)
    final isProcessingText = msg.contains('sedang diproses') || title.contains('sedang diproses') || 
                             msg.contains('diproses') || title.contains('diproses') || 
                             msg.contains('menunggu') || title.contains('menunggu') || 
                             msg.contains('verifikasi') || title.contains('verifikasi');
    
    if (type != 'pinjaman' && type != 'pinjaman_kredit' && isProcessingText) return true;

    return false;
  }

  /// Public wrapper to check whether a notification should be excluded from display.
  /// Returns true for notifications that are promotional or generic processing/waiting messages
  /// which shouldn't be shown in the user's notification feed.
  static bool isExcludedNotification(Map<String, dynamic> n) => _isExcludedNotification(n);

  /// Merge a server-filtered notifications list with local existing list so local-only
  /// notifications (e.g., newly-created topups) are preserved.
  /// CRITICAL: Prefer LOCAL timestamp if fresher than SERVER (prevents showing old times)
  /// Also respects the blacklist of deleted notifications to prevent resurrection.
  static List<Map<String, dynamic>> mergeServerWithExisting(
    List<Map<String, dynamic>> serverFiltered,
    List<dynamic> existingList, {
    Set<String>? blacklist,
  }) {
    String keyFor(Map<String, dynamic> n) {
      final t = (n['title'] ?? '').toString();
      final m = (n['message'] ?? '').toString();
      String d;
      try {
        d = n['data'] != null ? jsonEncode(n['data']) : '';
      } catch (_) {
        d = '';
      }
      return '${t}|${m}|${d}';
    }

    // Parse created_at timestamp safely
    DateTime? parseTimestamp(dynamic createdAt) {
      try {
        if (createdAt is String && createdAt.isNotEmpty) {
          return DateTime.parse(createdAt);
        }
      } catch (_) {}
      return null;
    }

    // Create a set of keys from server results for fast lookup
    final Set<String> serverKeys = serverFiltered.map((n) => keyFor(n)).toSet();
    
    // Build a map: key -> server notification (for finding exact duplicates)
    final Map<String, Map<String, dynamic>> serverByKey = {};
    for (var n in serverFiltered) {
      serverByKey[keyFor(n)] = n;
    }

    // Build map of record ID -> server notification (for timestamp comparison)
    final Map<String, Map<String, dynamic>> serverByRecordId = {};
    for (var n in serverFiltered) {
      try {
        final d = n['data'];
        if (d != null && d is Map) {
          final mid = (d['mulai_id'] ?? d['id_mulai_nabung'])?.toString();
          if (mid != null && mid.isNotEmpty) {
            serverByRecordId[mid] = n;
          }
        }
      } catch (_) {}
    }

    // Start with server results FILTERED by blacklist
    final List<Map<String, dynamic>> merged = List<Map<String, dynamic>>.from(
      serverFiltered.where((n) {
        final key = keyFor(n);
        return !(blacklist?.contains(key) ?? false);
      }).toList()
    );

    // Add or update with local notifications
    for (var e in existingList) {
      try {
        if (_isExcludedNotification(e)) continue;
      } catch (_) {}

      // Skip if this notification is in the blacklist (user deleted it)
      final eKey = keyFor(Map<String, dynamic>.from(e));
      if (blacklist?.contains(eKey) ?? false) continue;

      // Check if EXACT notification (by title+message) exists on server
      final exactServerMatch = serverByKey[eKey];
      if (exactServerMatch != null) {
        // Exact match found! Compare timestamps
        // CRITICAL: Prefer LOCAL if it's fresher
        final localTime = parseTimestamp(e['created_at']);
        final serverTime = parseTimestamp(exactServerMatch['created_at']);
        
        if (localTime != null && serverTime != null && localTime.isAfter(serverTime)) {
          // Local is fresher! Replace server notif with local version
          merged.removeWhere((n) => keyFor(n) == eKey);
          merged.add(Map<String, dynamic>.from(e));
        }
        // Otherwise server version is same/fresher, skip adding local
        continue;
      }

      // Check if this local notification's record exists on server (by mulai_id)
      String? localRecordId;
      try {
        final ed = e['data'];
        if (ed != null && ed is Map) {
          localRecordId = (ed['mulai_id'] ?? ed['id_mulai_nabung'])?.toString();
        }
      } catch (_) {}

      if (localRecordId != null && localRecordId.isNotEmpty) {
        final serverNotif = serverByRecordId[localRecordId];
        if (serverNotif != null) {
          // Same record exists on server
          // CRITICAL FIX: If local timestamp is FRESHER, use local instead of server
          final localTime = parseTimestamp(e['created_at']);
          final serverTime = parseTimestamp(serverNotif['created_at']);
          
          if (localTime != null && serverTime != null && localTime.isAfter(serverTime)) {
            // Local is fresher! Replace server notif with local version
            merged.removeWhere((n) => keyFor(n) == keyFor(serverNotif));
            merged.add(Map<String, dynamic>.from(e));
          }
          // Otherwise use server version (it's same or fresher)
          continue;
        }
      }

      // No match found - add as new notification
      if (!serverKeys.contains(eKey)) {
        merged.add(Map<String, dynamic>.from(e));
        serverKeys.add(eKey);
      }
    }

    return merged;
  }

  /// Robust parse for various timestamp formats (ISO, "yyyy-MM-dd HH:mm:ss", unix secs/millis).
  static DateTime? _parseToDate(dynamic v) {
    if (v == null) return null;
    try {
      if (v is DateTime) return v;
      if (v is int) {
        if (v.abs() > 1000000000000) return DateTime.fromMillisecondsSinceEpoch(v);
        return DateTime.fromMillisecondsSinceEpoch(v * 1000);
      }
      if (v is double) {
        final intVal = v.toInt();
        if (intVal.abs() > 1000000000000) return DateTime.fromMillisecondsSinceEpoch(intVal);
        return DateTime.fromMillisecondsSinceEpoch(intVal * 1000);
      }
      final s = v.toString().trim();
      // Try ISO-like parse
      try {
        return DateTime.parse(s);
      } catch (_) {}
      // Try common format yyyy-MM-dd HH:mm:ss
      try {
        return DateFormat('yyyy-MM-dd HH:mm:ss').parseLoose(s);
      } catch (_) {}
      // Try extracting unix timestamp digits (10..13+ digits)
      final m = RegExp(r'\d{10,}').firstMatch(s);
      if (m != null) {
        final digits = m.group(0)!;
        final numVal = int.parse(digits);
        if (digits.length > 10) return DateTime.fromMillisecondsSinceEpoch(numVal);
        return DateTime.fromMillisecondsSinceEpoch(numVal * 1000);
      }
    } catch (_) {}
    return null;
  }

  static int? _extractNumericId(Map<String, dynamic> m) {
    try {
      final candidates = ['id_transaksi', 'id_mulai_nabung', 'id'];
      for (final k in candidates) {
        if (m.containsKey(k) && m[k] != null) {
          final n = int.tryParse(m[k].toString());
          if (n != null) return n;
        }
      }
    } catch (_) {}
    return null;
  }

  /// Sort helper: newest notifications first by created_at, with robust fallbacks.
  static List<Map<String, dynamic>> _sortNotificationsNewestFirst(List<Map<String, dynamic>> list) {
    final copy = List<Map<String, dynamic>>.from(list);
    copy.sort((a, b) {
      final aCandidate = a['created_at'] ?? a['tanggal'] ?? a['updated_at'] ?? (a['data'] is Map ? a['data']['created_at'] : null);
      final bCandidate = b['created_at'] ?? b['tanggal'] ?? b['updated_at'] ?? (b['data'] is Map ? b['data']['created_at'] : null);

      final da = _parseToDate(aCandidate);
      final db = _parseToDate(bCandidate);

      if (da != null && db != null) return db.compareTo(da); // newest-first
      if (da != null && db == null) return -1; // a has date -> considered newer -> a before b
      if (da == null && db != null) return 1;

      // Fallback: compare numeric IDs if both available
      final na = _extractNumericId(a);
      final nb = _extractNumericId(b);
      if (na != null && nb != null) return nb.compareTo(na); // higher id -> newer
      if (na != null && nb == null) return -1;
      if (na == null && nb != null) return 1;

      // Final fallback: string comparison on created_at/title
      final sa = (a['created_at'] ?? a['tanggal'] ?? a['id'] ?? a['title'] ?? '').toString();
      final sb = (b['created_at'] ?? b['tanggal'] ?? b['id'] ?? b['title'] ?? '').toString();
      return sb.compareTo(sa);
    });
    return copy;
  }

  /// Public wrapper to sort notifications newest-first
  static List<Map<String, dynamic>> sortNotificationsNewestFirst(List<Map<String, dynamic>> list) {
    return _sortNotificationsNewestFirst(list);
  }

  static Future<void> initializeNotifications() async {
    final prefs = await SharedPreferences.getInstance();

    // Remove previously stored excluded notifications so they don't remain visible
    // if the server fetch fails or contains no valid items.
    try {
      final existingRaw = prefs.getString('notifications') ?? '[]';
      final List<dynamic> existingList = jsonDecode(existingRaw);
      final filteredExisting = existingList.where((e) => !_isExcludedNotification(e)).toList();
      if (filteredExisting.length != existingList.length) {
        await prefs.setString('notifications', jsonEncode(filteredExisting));
        // Notify listeners that the notification store changed
        try { onNotificationsChanged.value++; } catch (_) {}
        if (kDebugMode) debugPrint('[NotifikasiHelper] pruned excluded notifications from prefs count=${filteredExisting.length}');
      }
    } catch (_) {}

    try {
      final user = await EventPref.getUser();
      if (user != null && (user.id ?? '').isNotEmpty) {
        final serverList = await EventDB.getNotifications(user.id ?? '');
        if (kDebugMode) debugPrint('[NotifikasiHelper] ✅ serverList received count=${serverList.length}');
        
        if (serverList.isNotEmpty) {
          // Accept 'transaksi', 'topup', 'tabungan' and 'pinjaman' so pinjaman-related
          // server notifications are preserved and not dropped by dashboard polling.
          final filtered = serverList
              .where((n) {
                final t = (n['type'] ?? '').toString();
                final title = n['title'] ?? '';
                
                // Type check: accept known types
                if (!(t == 'transaksi' || t == 'topup' || t == 'tabungan' || t == 'pinjaman' || t == 'pinjaman_kredit')) {
                  if (kDebugMode) debugPrint('[NotifikasiHelper] ⚠️ FILTERED unknown type=$t title=$title');
                  return false;
                }
                
                // Exclusion check: verify notification is not in exclusion list
                if (_isExcludedNotification(n)) {
                  if (kDebugMode) debugPrint('[NotifikasiHelper] ⚠️ FILTERED excluded notification title=$title');
                  return false;
                }
                
                if (kDebugMode) debugPrint('[NotifikasiHelper] ✅ ACCEPTED type=$t title=$title');
                return true;
              })
              .map((n) {
            return {
              'type': n['type'] ?? 'transaksi',
              'title': n['title'] ?? 'Notifikasi',
              'message': n['message'] ?? '',
              'created_at': n['created_at'] ?? DateTime.now().toIso8601String(),
              'read': n['read'] ?? false,
              'data': n['data'] ?? null,
            };
          }).toList();

          if (kDebugMode) debugPrint('[NotifikasiHelper] 🔍 After filtering: accepted=${filtered.length} from total=${serverList.length}');

          // Merge server results with existing local notifications so immediate local
          // notifications (added after submit) are not overwritten by a server poll.
          try {
            final existingRaw = prefs.getString('notifications') ?? '[]';
            final List<dynamic> existingList = jsonDecode(existingRaw);

            final merged = mergeServerWithExisting(List<Map<String, dynamic>>.from(filtered), existingList);

            // Ensure newest notifications appear first (created_at descending)
            final sortedMerged = _sortNotificationsNewestFirst(merged);

            await prefs.setString('notifications', jsonEncode(sortedMerged));
            // Notify listeners that the notification store changed
            try { onNotificationsChanged.value++; } catch (_) {}
            if (kDebugMode) {
              debugPrint('[NotifikasiHelper] ✅ initializeNotifications MERGED count=${sortedMerged.length}');
              for (int i = 0; i < (sortedMerged.length > 5 ? 5 : sortedMerged.length); i++) {
                debugPrint('[NotifikasiHelper]   [$i] type=${sortedMerged[i]['type']} title=${sortedMerged[i]['title']}');
              }
            }
          } catch (e) {
            // On any failure, fall back to server-only list
            await prefs.setString('notifications', jsonEncode(filtered));
            // Notify listeners that the notification store changed
            try { onNotificationsChanged.value++; } catch (_) {}
            if (kDebugMode) {
              debugPrint('[NotifikasiHelper] ⚠️ MERGE FAILED, using server-only count=${filtered.length}. Error: $e');
            }
          }

          // If any of the notifications indicate a successful topup, proactively
          // refresh user's saldo so the UI reflects the change immediately.
          try {
            final hasTopup = filtered.any((n) {
              final t = (n['type'] ?? '').toString();
              final title = (n['title'] ?? '').toString().toLowerCase();
              final msg = (n['message'] ?? '').toString().toLowerCase();
              return t == 'topup' || title.contains('topup') || msg.contains('topup');
            });
            if (hasTopup) {
              await EventDB.refreshSaldoForCurrentUser();
            }
          } catch (e) {
            if (kDebugMode) print('initializeNotifications refreshSaldo error: $e');
          }

          return;
        }
      }
    } catch (e) {
      if (kDebugMode) print('❌ initializeNotifications error: $e');
    }

    // If we reached here: server fetch failed or no items; leave local notifications as-is.
  }

  /// Add a local notification (used for immediate local feedback such as sender confirmation)
  /// with robust duplicate prevention
  static Future<void> addLocalNotification({
    required String type,
    required String title,
    required String message,
    Map<String, dynamic>? data,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString('notifications') ?? '[]';
    final List<dynamic> notifications = jsonDecode(existing);

    // Exclude unwanted messages
    final candidate = {'type': type, 'title': title, 'message': message};
    if (_isExcludedNotification(candidate)) return;

    // Prevent duplicates: check if same notification already exists recently
    final now = DateTime.now();
    final duplicate = notifications.cast<Map<String, dynamic>>().any((n) {
      final t = (n['type'] ?? '').toString();
      final ti = (n['title'] ?? '').toString();
      final m = (n['message'] ?? '').toString();
      final nd = n['data'];
      
      // Must match type, title, and message
      if (t != type || ti != title || m != message) return false;
      // Check if data matches (or both are empty)
      final hasNewData = data != null && data.isNotEmpty;
      final ndMap = nd is Map ? nd : null;
      final hasExistingData = ndMap != null && ndMap.isNotEmpty;
      
      if (hasNewData && hasExistingData) {
        try {
          final existingDataJson = jsonEncode(nd);
          final newDataJson = jsonEncode(data);
          if (existingDataJson != newDataJson) return false;
        } catch (_) {
          return false;
        }
      } else if (hasNewData != hasExistingData) {
        return false;
      }

      // If we got here, title/message/data all match
      // Check if this is a recent duplicate (within 5 minutes)
      try {
        final dt = DateTime.parse(n['created_at'] ?? now.toIso8601String());
        final diff = now.difference(dt);
        if (diff.inMinutes <= 5) return true;
      } catch (_) {}
      
      return false;
    });

    if (duplicate) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] skipped duplicate local notification: $title');
      }
      return;
    }

    // Create new notification with current timestamp
    final newNotification = {
      'type': type,
      'title': title,
      'message': message,
      'created_at': now.toIso8601String(),
      'read': false,
      'data': (data != null && data.isNotEmpty) ? data : null,
    };

    // Insert at top (newest first)
    notifications.insert(0, newNotification);
    await prefs.setString('notifications', jsonEncode(notifications));
    
    // Persist last local notification explicitly
    await prefs.setString('last_local_notif', jsonEncode(newNotification));
    
    // Notify listeners
    try { 
      onNotificationsChanged.value++; 
    } catch (_) {}

    if (kDebugMode) {
      debugPrint('[NotifikasiHelper] addLocalNotification stored: $title');
      debugPrint('[NotifikasiHelper] last_local_notif: ${jsonEncode(newNotification)}');
    }
  }

  /// Backwards-compatible wrapper so older call sites using `addNotification` keep working.
  static Future<void> addNotification({
    required String type,
    required String title,
    required String message,
    Map<String, dynamic>? data,
  }) async {
    return addLocalNotification(type: type, title: title, message: message, data: data);
  }

  /// Return the count of unread notifications (reads from local storage).
  static Future<int> getUnreadCount() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final existing = prefs.getString('notifications') ?? '[]';
      final List<dynamic> list = jsonDecode(existing);
      final unread = list.cast<Map<String, dynamic>>().where((n) => (n['read'] ?? false) == false).length;
      return unread;
    } catch (_) {
      return 0;
    }
  }
}