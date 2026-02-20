import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/config/api.dart';
import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;

class NotifikasiHelper {
  /// ✅ FIXED: Trigger global badge update (untuk dashboard)
  static Future<void> triggerBadgeUpdate() async {
    try {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] 🔔 triggerBadgeUpdate() called');
      }
      
      final unreadCount = await getUnreadCount();
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] 📊 Current unread count: $unreadCount');
      }
      
      // ✅ TRIGGER ValueNotifier untuk notify semua listeners
      onNotificationsChanged.value++;
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ✅ Badge update triggered!');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ❌ ERROR in triggerBadgeUpdate: $e');
      }
    }
  }

  /// ✅ NEW: Mark ALL notifications as read di SERVER + local storage
  static Future<void> markAllAsReadOnServer() async {
    try {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] 🌐 markAllAsReadOnServer() START');
      }

      final user = await EventPref.getUser();
      if (user == null || (user.id ?? '').isEmpty) {
        if (kDebugMode) {
          debugPrint('[NotifikasiHelper] ❌ User not found');
        }
        return;
      }

      final userId = user.id.toString();

      // Get semua unread notifications
      final prefs = await SharedPreferences.getInstance();
      final existing = prefs.getString('notifications') ?? '[]';
      final List<dynamic> list = jsonDecode(existing);

      // Collect unread notification IDs
      List<String> unreadIds = [];
      for (var n in list) {
        if (n is Map<String, dynamic>) {
          if ((n['read'] ?? false) == false) {
            final nid = n['id'];
            if (nid != null) {
              unreadIds.add(nid.toString());
            }
          }
        }
      }

      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] Found ${unreadIds.length} unread notifications');
      }

      // Mark each notification as read di server
      for (final nid in unreadIds) {
        try {
          if (kDebugMode) {
            debugPrint('[NotifikasiHelper] 📤 Sending to server: nid=$nid, uid=$userId');
          }

          final response = await http
              .post(
                Uri.parse('${Api.baseUrl}/update_notifikasi_read.php'),
                body: {
                  'id_notifikasi': nid,
                  'id_pengguna': userId,
                },
              )
              .timeout(const Duration(seconds: 5));

          if (response.statusCode == 200) {
            try {
              final json = jsonDecode(response.body);
              if (json['success'] == true) {
                if (kDebugMode) {
                  debugPrint('[NotifikasiHelper] ✅ Server mark: nid=$nid SUCCESS');
                }
              } else {
                if (kDebugMode) {
                  debugPrint('[NotifikasiHelper] ⚠️ Server mark failed: ${json['message']}');
                }
              }
            } catch (e) {
              if (kDebugMode) {
                debugPrint('[NotifikasiHelper] ⚠️ Response parse error: $e');
              }
            }
          } else {
            if (kDebugMode) {
              debugPrint('[NotifikasiHelper] ⚠️ HTTP ${response.statusCode}');
            }
          }
        } catch (e) {
          if (kDebugMode) {
            debugPrint('[NotifikasiHelper] ⚠️ Error marking nid=$nid: $e');
          }
        }
      }

      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ✅ markAllAsReadOnServer() SUCCESS');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ❌ ERROR in markAllAsReadOnServer: $e');
      }
    }
  }

  /// ✅ FULLY FIXED: Tandai semua notifikasi sebagai sudah dibaca (read=true)
  static Future<void> markAllAsRead() async {
    try {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] 🔵 markAllAsRead() START');
      }
      
      final prefs = await SharedPreferences.getInstance();
      final user = await EventPref.getUser();
      final ownerId = _ownerIdFromUser(user);
      final storedOwnerId = prefs.getString('notifications_owner_id') ?? '';
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ownerId=$ownerId, storedOwnerId=$storedOwnerId');
      }
      
      // BACA dari SharedPreferences
      final existing = prefs.getString('notifications') ?? '[]';
      final List<dynamic> list = jsonDecode(existing);
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] Total notifications in storage: ${list.length}');
      }
      
      // Ubah LANGSUNG di list original (BUKAN copy)
      bool changed = false;
      for (var n in list) {
        if (n is Map<String, dynamic>) {
          // Cek apakah notifikasi ini untuk user saat ini
          final isForThisUser = isForOwner(
            n,
            ownerId,
            fallbackOwnerId: storedOwnerId,
          );
          
          if (isForThisUser && ((n['read'] ?? false) == false)) {
            n['read'] = true;
            changed = true;
            if (kDebugMode) {
              debugPrint('[NotifikasiHelper] Marked as read: ${n['title']}');
            }
          }
        }
      }
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] Changed: $changed');
      }
      
      // SAVE kembali ke SharedPreferences
      if (changed) {
        await prefs.setString('notifications', jsonEncode(list));
        
        if (kDebugMode) {
          debugPrint('[NotifikasiHelper] ✅ Saved to SharedPreferences');
        }
      }
      
      // Verify dengan membaca kembali dari prefs
      final verifyExisting = prefs.getString('notifications') ?? '[]';
      final verifyList = jsonDecode(verifyExisting);
      
      int unreadAfter = 0;
      for (var n in verifyList) {
        if (n is Map<String, dynamic>) {
          final isForThisUser = isForOwner(
            n,
            ownerId,
            fallbackOwnerId: storedOwnerId,
          );
          if (isForThisUser && ((n['read'] ?? false) == false)) {
            unreadAfter++;
          }
        }
      }
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] Unread AFTER verification: $unreadAfter');
      }
      
      // ✅ TRIGGER badge update SETELAH mark all as read
      await triggerBadgeUpdate();
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ✅ markAllAsRead() SUCCESS - Unread: $unreadAfter');
      }
      
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ❌ ERROR in markAllAsRead: $e');
      }
    }
  }

  // Notifier for UI to listen to changes in notifications store.
  static final ValueNotifier<int> onNotificationsChanged = ValueNotifier<int>(0);

  static String _ownerIdFromUser(dynamic user) {
    try {
      final idVal = user?.id?.toString() ?? '';
      if (idVal.isNotEmpty) return idVal;
      final hpVal = user?.no_hp?.toString() ?? '';
      if (hpVal.isNotEmpty) return hpVal;
    } catch (_) {}
    return '';
  }

  static bool isForOwner(
    Map<String, dynamic> n,
    String ownerId, {
    String? fallbackOwnerId,
  }) {
    if (ownerId.isEmpty) return true;
    final owner = (n['owner_id'] ?? '').toString();
    if (owner.isNotEmpty) return owner == ownerId;
    if (fallbackOwnerId != null && fallbackOwnerId.isNotEmpty) {
      return fallbackOwnerId == ownerId;
    }
    return true;
  }

  static List<Map<String, dynamic>> filterForOwner(
    List<dynamic> list,
    String ownerId, {
    String? fallbackOwnerId,
  }) {
    final safeCasted = <Map<String, dynamic>>[];
    for (final item in list) {
      try {
        if (item is Map) {
          safeCasted.add(Map<String, dynamic>.from(item));
        }
      } catch (_) {}
    }
    if (ownerId.isEmpty) return safeCasted;
    return safeCasted.where((n) {
      return isForOwner(n, ownerId, fallbackOwnerId: fallbackOwnerId);
    }).toList();
  }

  /// ✅ NEW: Get last sync time dari SharedPreferences
  /// Digunakan untuk incremental fetch dari server
  static Future<DateTime?> getLastSyncTime() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final lastSync = prefs.getString('notifications_last_sync');
      if (lastSync != null && lastSync.isNotEmpty) {
        return DateTime.parse(lastSync);
      }
    } catch (_) {}
    return null;
  }

  /// ✅ NEW: Set last sync time ke SharedPreferences
  static Future<void> setLastSyncTime(DateTime time) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('notifications_last_sync', time.toIso8601String());
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ✅ Last sync time updated: ${time.toIso8601String()}');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ⚠️ Failed to set last sync time: $e');
      }
    }
  }

  /// ✅ NEW: Clear all notifications (untuk debug/reset)
  static Future<void> clearAllNotifications() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('notifications');
      await prefs.remove('notifications_owner_id');
      await prefs.remove('notifications_last_sync');
      await prefs.remove('last_local_notif');
      onNotificationsChanged.value++;
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ✅ All notifications cleared');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ⚠️ Failed to clear notifications: $e');
      }
    }
  }

  static bool _isExcludedNotification(Map<String, dynamic> n) {
    final title = (n['title'] ?? '').toString().toLowerCase();
    final msg = (n['message'] ?? '').toString().toLowerCase();
    final titleOrig = (n['title'] ?? '').toString();
    final type = (n['type'] ?? '').toString();

    if (title.contains('cashback') || msg.contains('cashback')) return true;

    final titleLowerForDecision = titleOrig.toLowerCase();
    if ((titleLowerForDecision.contains('pencairan disetujui') ||
            titleLowerForDecision.contains('pencairan ditolak')) ||
        (titleLowerForDecision.contains('withdrawal') &&
            (titleLowerForDecision.contains('approved') ||
                titleLowerForDecision.contains('rejected') ||
                titleLowerForDecision.contains('disetujui') ||
                titleLowerForDecision.contains('ditolak')))) {
      return false;
    }

    final titleLower = titleOrig.toLowerCase();
    if (titleLower.contains('setoran') &&
        (titleLower.contains('berhasil') ||
            titleLower.contains('ditolak') ||
            titleLower.contains('disetujui')))
      return false;

    if (titleLower.contains('pengajuan setoran')) return false;

    if (titleLower.contains('pencairan') || titleLower.contains('withdrawal'))
      return false;

    final isProcessingText =
        msg.contains('sedang diproses') ||
        title.contains('sedang diproses') ||
        msg.contains('diproses') ||
        title.contains('diproses') ||
        msg.contains('menunggu') ||
        title.contains('menunggu') ||
        msg.contains('verifikasi') ||
        title.contains('verifikasi');

    if (type != 'pinjaman' && type != 'pinjaman_kredit' && isProcessingText)
      return true;

    return false;
  }

  static bool isExcludedNotification(Map<String, dynamic> n) =>
      _isExcludedNotification(n);

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

    DateTime? parseTimestamp(dynamic createdAt) {
      try {
        if (createdAt is String && createdAt.isNotEmpty) {
          return DateTime.parse(createdAt);
        }
      } catch (_) {}
      return null;
    }

    final Set<String> serverKeys = serverFiltered.map((n) => keyFor(n)).toSet();
    final Map<String, Map<String, dynamic>> serverByKey = {};
    for (var n in serverFiltered) {
      serverByKey[keyFor(n)] = n;
    }

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

    final List<Map<String, dynamic>> merged = List<Map<String, dynamic>>.from(
      serverFiltered.where((n) {
        final key = keyFor(n);
        return !(blacklist?.contains(key) ?? false);
      }).toList(),
    );

    for (var e in existingList) {
      try {
        if (_isExcludedNotification(e)) continue;
      } catch (_) {}

      final eKey = keyFor(Map<String, dynamic>.from(e));
      if (blacklist?.contains(eKey) ?? false) continue;

      final exactServerMatch = serverByKey[eKey];
      if (exactServerMatch != null) {
        final localTime = parseTimestamp(e['created_at']);
        final serverTime = parseTimestamp(exactServerMatch['created_at']);
        if (localTime != null &&
            serverTime != null &&
            localTime.isAfter(serverTime)) {
          merged.removeWhere((n) => keyFor(n) == eKey);
          merged.add(Map<String, dynamic>.from(e));
        }
        continue;
      }

      String? localRecordId;
      try {
        final ed = e['data'];
        if (ed != null && ed is Map) {
          localRecordId =
              (ed['mulai_id'] ?? ed['id_mulai_nabung'] ?? ed['id_transaksi'])
                  ?.toString();
        }
      } catch (_) {}

      if (localRecordId != null && localRecordId.isNotEmpty) {
        final serverNotif = serverByRecordId[localRecordId];
        if (serverNotif != null) {
          final localTime = parseTimestamp(e['created_at']);
          final serverTime = parseTimestamp(serverNotif['created_at']);
          if (localTime != null &&
              serverTime != null &&
              localTime.isAfter(serverTime)) {
            merged.removeWhere((n) => keyFor(n) == keyFor(serverNotif));
            merged.add(Map<String, dynamic>.from(e));
          }
          continue;
        }
      }

      if (!serverKeys.contains(eKey)) {
        merged.add(Map<String, dynamic>.from(e));
        serverKeys.add(eKey);
      }
    }

    final Map<String, Map<String, dynamic>> uniqueSetoran = {};
    final List<Map<String, dynamic>> finalMerged = [];
    for (var notif in merged) {
      final title = (notif['title'] ?? '').toString().toLowerCase();
      if (title.contains('pengajuan setoran')) {
        String? id = '';
        final data = notif['data'];
        if (data != null && data is Map) {
          id =
              (data['mulai_id'] ??
                      data['id_mulai_nabung'] ??
                      data['id_transaksi'])
                  ?.toString() ??
              '';
        }
        if (id != null && id.isNotEmpty) {
          final existing = uniqueSetoran[id];
          if (existing == null) {
            uniqueSetoran[id] = notif;
          } else {
            final t1 = parseTimestamp(notif['created_at']);
            final t2 = parseTimestamp(existing['created_at']);
            if (t1 != null && t2 != null && t1.isAfter(t2)) {
              uniqueSetoran[id] = notif;
            }
          }
          continue;
        }
      }
      finalMerged.add(notif);
    }
    finalMerged.addAll(uniqueSetoran.values);
    return _sortNotificationsNewestFirst(finalMerged);
  }

  static DateTime? _parseToDate(dynamic v) {
    if (v == null) return null;
    try {
      if (v is DateTime) return v;
      if (v is int) {
        if (v.abs() > 1000000000000)
          return DateTime.fromMillisecondsSinceEpoch(v);
        return DateTime.fromMillisecondsSinceEpoch(v * 1000);
      }
      if (v is double) {
        final intVal = v.toInt();
        if (intVal.abs() > 1000000000000)
          return DateTime.fromMillisecondsSinceEpoch(intVal);
        return DateTime.fromMillisecondsSinceEpoch(intVal * 1000);
      }
      final s = v.toString().trim();
      try {
        return DateTime.parse(s);
      } catch (_) {}
      try {
        return DateFormat('yyyy-MM-dd HH:mm:ss').parseLoose(s);
      } catch (_) {}
      final m = RegExp(r'\d{10,}').firstMatch(s);
      if (m != null) {
        final digits = m.group(0)!;
        final numVal = int.parse(digits);
        if (digits.length > 10)
          return DateTime.fromMillisecondsSinceEpoch(numVal);
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

  static List<Map<String, dynamic>> _sortNotificationsNewestFirst(
    List<Map<String, dynamic>> list,
  ) {
    final copy = List<Map<String, dynamic>>.from(list);
    copy.sort((a, b) {
      final aCandidate =
          a['created_at'] ??
          a['tanggal'] ??
          a['updated_at'] ??
          (a['data'] is Map ? a['data']['created_at'] : null);
      final bCandidate =
          b['created_at'] ??
          b['tanggal'] ??
          b['updated_at'] ??
          (b['data'] is Map ? b['data']['created_at'] : null);

      final da = _parseToDate(aCandidate);
      final db = _parseToDate(bCandidate);

      if (da != null && db != null) return db.compareTo(da);
      if (da != null && db == null) return -1;
      if (da == null && db != null) return 1;

      final na = _extractNumericId(a);
      final nb = _extractNumericId(b);
      if (na != null && nb != null) return nb.compareTo(na);
      if (na != null && nb == null) return -1;
      if (na == null && nb != null) return 1;

      final sa =
          (a['created_at'] ?? a['tanggal'] ?? a['id'] ?? a['title'] ?? '')
              .toString();
      final sb =
          (b['created_at'] ?? b['tanggal'] ?? b['id'] ?? b['title'] ?? '')
              .toString();
      return sb.compareTo(sa);
    });
    return copy;
  }

  static List<Map<String, dynamic>> sortNotificationsNewestFirst(
    List<Map<String, dynamic>> list,
  ) {
    return _sortNotificationsNewestFirst(list);
  }

  static Future<void> initializeNotifications() async {
    final prefs = await SharedPreferences.getInstance();
    final user = await EventPref.getUser();
    final ownerId = _ownerIdFromUser(user);
    final storedOwnerId = prefs.getString('notifications_owner_id') ?? '';

    try {
      final existingRaw = prefs.getString('notifications') ?? '[]';
      final List<dynamic> existingList = jsonDecode(existingRaw);
      final filteredExisting = existingList
          .cast<Map<String, dynamic>>()
          .where((e) => !_isExcludedNotification(e))
          .toList();
      if (filteredExisting.length != existingList.length) {
        await prefs.setString('notifications', jsonEncode(filteredExisting));
        try {
          onNotificationsChanged.value++;
        } catch (_) {}
        if (kDebugMode)
          debugPrint(
            '[NotifikasiHelper] pruned excluded notifications from prefs count=${filteredExisting.length}',
          );
      }
    } catch (_) {}

    try {
      if (user != null && (user.id ?? '').isNotEmpty) {
        final serverList = await EventDB.getNotifications(user.id ?? '');
        if (kDebugMode)
          debugPrint(
            '[NotifikasiHelper] ✅ serverList received count=${serverList.length}',
          );

        if (serverList.isNotEmpty) {
          final filtered = serverList
              .where((n) {
                final t = (n['type'] ?? '').toString();
                final title = n['title'] ?? '';

                if (!(t == 'transaksi' ||
                    t == 'topup' ||
                    t == 'tabungan' ||
                    t == 'pinjaman' ||
                    t == 'pinjaman_kredit')) {
                  if (kDebugMode)
                    debugPrint(
                      '[NotifikasiHelper] ⚠️ FILTERED unknown type=$t title=$title',
                    );
                  return false;
                }

                if (_isExcludedNotification(n)) {
                  if (kDebugMode)
                    debugPrint(
                      '[NotifikasiHelper] ⚠️ FILTERED excluded notification title=$title',
                    );
                  return false;
                }

                if (kDebugMode)
                  debugPrint(
                    '[NotifikasiHelper] ✅ ACCEPTED type=$t title=$title',
                  );
                return true;
              })
              .map((n) {
                return {
                  'type': n['type'] ?? 'transaksi',
                  'title': n['title'] ?? 'Notifikasi',
                  'message': n['message'] ?? '',
                  'created_at':
                      n['created_at'] ?? DateTime.now().toIso8601String(),
                  'read': n['read'] ?? false,
                  'data': n['data'] ?? null,
                  if (ownerId.isNotEmpty) 'owner_id': ownerId,
                };
              })
              .toList();

          if (kDebugMode)
            debugPrint(
              '[NotifikasiHelper] 🔍 After filtering: accepted=${filtered.length} from total=${serverList.length}',
            );

          try {
            final existingRaw = prefs.getString('notifications') ?? '[]';
            final List<dynamic> existingList = jsonDecode(existingRaw);
            final safeExisting = filterForOwner(
              existingList,
              '',
            );

            final merged = mergeServerWithExisting(
              List<Map<String, dynamic>>.from(filtered),
              safeExisting,
            );

            final sortedMerged = _sortNotificationsNewestFirst(merged);

            await prefs.setString('notifications', jsonEncode(sortedMerged));
            if (ownerId.isNotEmpty) {
              await prefs.setString('notifications_owner_id', ownerId);
            }
            try {
              onNotificationsChanged.value++;
            } catch (_) {}
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiHelper] ✅ initializeNotifications MERGED count=${sortedMerged.length}',
              );
            }
          } catch (e) {
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiHelper] ⚠️ MERGE FAILED, keeping local cache intact. Error: $e',
              );
            }
          }

          try {
            final hasTopup = filtered.any((n) {
              final t = (n['type'] ?? '').toString();
              final title = (n['title'] ?? '').toString().toLowerCase();
              final msg = (n['message'] ?? '').toString().toLowerCase();
              return t == 'topup' ||
                  title.contains('topup') ||
                  msg.contains('topup');
            });
            if (hasTopup) {
              await EventDB.refreshSaldoForCurrentUser();
            }
          } catch (e) {
            if (kDebugMode)
              debugPrint('initializeNotifications refreshSaldo error: $e');
          }

          return;
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('❌ initializeNotifications error: $e');
    }
  }

  static Future<void> addLocalNotification({
    required String type,
    required String title,
    required String message,
    Map<String, dynamic>? data,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final user = await EventPref.getUser();
    final ownerId = _ownerIdFromUser(user);
    final existing = prefs.getString('notifications') ?? '[]';
    final List<dynamic> notifications = jsonDecode(existing);

    final candidate = {'type': type, 'title': title, 'message': message};
    if (_isExcludedNotification(candidate)) return;

    final now = DateTime.now();
    final duplicate = notifications.cast<Map<String, dynamic>>().any((n) {
      final t = (n['type'] ?? '').toString();
      final ti = (n['title'] ?? '').toString();
      final m = (n['message'] ?? '').toString();
      final nd = n['data'];
      final nOwner = (n['owner_id'] ?? '').toString();
      if (ownerId.isNotEmpty && nOwner.isNotEmpty && nOwner != ownerId)
        return false;

      if (t != type || ti != title || m != message) return false;
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

      try {
        final dt = DateTime.parse(n['created_at'] ?? now.toIso8601String());
        final diff = now.difference(dt);
        if (diff.inMinutes <= 5) return true;
      } catch (_) {}

      return false;
    });

    if (duplicate) {
      if (kDebugMode) {
        debugPrint(
          '[NotifikasiHelper] skipped duplicate local notification: $title',
        );
      }
      return;
    }

    final newNotification = {
      'type': type,
      'title': title,
      'message': message,
      'created_at': now.toIso8601String(),
      'read': false,
      'data': (data != null && data.isNotEmpty) ? data : null,
      if (ownerId.isNotEmpty) 'owner_id': ownerId,
    };

    notifications.insert(0, newNotification);
    await prefs.setString('notifications', jsonEncode(notifications));
    if (ownerId.isNotEmpty) {
      await prefs.setString('notifications_owner_id', ownerId);
    }

    await prefs.setString('last_local_notif', jsonEncode(newNotification));

    try {
      onNotificationsChanged.value++;
    } catch (_) {}

    if (kDebugMode) {
      debugPrint('[NotifikasiHelper] addLocalNotification stored: $title');
    }
  }

  static Future<void> addNotification({
    required String type,
    required String title,
    required String message,
    Map<String, dynamic>? data,
  }) async {
    return addLocalNotification(
      type: type,
      title: title,
      message: message,
      data: data,
    );
  }

  static Future<int> getUnreadCount() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final user = await EventPref.getUser();
      final ownerId = _ownerIdFromUser(user);
      final storedOwnerId = prefs.getString('notifications_owner_id') ?? '';
      final existing = prefs.getString('notifications') ?? '[]';
      final List<dynamic> list = jsonDecode(existing);
      
      int unread = 0;
      for (var n in list) {
        if (n is Map<String, dynamic>) {
          final isForThisUser = isForOwner(
            n,
            ownerId,
            fallbackOwnerId: storedOwnerId,
          );
          if (isForThisUser && ((n['read'] ?? false) == false)) {
            unread++;
          }
        }
      }
      
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] getUnreadCount() = $unread');
      }
      
      return unread;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiHelper] ERROR in getUnreadCount: $e');
      }
      return 0;
    }
  }
}