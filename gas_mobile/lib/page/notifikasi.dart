import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter/foundation.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'package:tabungan/config/api.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/page/transaction_detail_page.dart';

class NotifikasiPage extends StatefulWidget {
  const NotifikasiPage({Key? key}) : super(key: key);

  @override
  State<NotifikasiPage> createState() => _NotifikasiPageState();
}

class _NotifikasiPageState extends State<NotifikasiPage> {
  List<Map<String, dynamic>> _notifications = [];
  bool _isLoading = true;
  String? _errorMessage;
  bool _isMarkingAllAsRead = false; // ✅ NEW: Track marking all as read

  Timer? _refreshTimer;
  bool _needsRefreshNotifs = false;

  @override
  void initState() {
    super.initState();
    
    if (kDebugMode) {
      debugPrint('========== [NotifikasiPage] initState() CALLED ==========');
    }
    
    _markAllAsReadWithDebug();
    
    _initializeAndLoad();

    try {
      NotifikasiHelper.onNotificationsChanged.addListener(_onNotifsChanged);
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Listener added for notification changes');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ❌ Error adding listener: $e');
      }
    }
  }

  /// ✅ NEW: Debug wrapper untuk markAllAsRead
  Future<void> _markAllAsReadWithDebug() async {
    try {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] 🔵 STEP 1: Calling markAllAsRead()...');
      }
      
      await NotifikasiHelper.markAllAsRead();
      
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ STEP 2: markAllAsRead() SUCCESS');
      }
      
      final unreadCount = await NotifikasiHelper.getUnreadCount();
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] 📊 STEP 3: Unread count after markAllAsRead = $unreadCount');
      }
      
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ❌ ERROR in markAllAsReadWithDebug: $e');
      }
    }
  }

  /// ✅ NEW: Mark all notifications as read + trigger badge update
  Future<void> _markAllNotificationsAsRead() async {
    if (_isMarkingAllAsRead) return;
    
    setState(() => _isMarkingAllAsRead = true);
    
    try {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] 🔘 BUTTON CLICKED: Mark All As Read');
      }

      // Step 1: Mark semua notifikasi sebagai read DI SERVER
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 1a: Marking on server...');
      }
      await NotifikasiHelper.markAllAsReadOnServer();
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 1b: Server mark completed');
      }

      // Step 2: Mark semua notifikasi di local storage juga
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 2a: Marking locally...');
      }
      await NotifikasiHelper.markAllAsRead();
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 2b: Local mark completed');
      }

      // Step 3: Reload notifikasi dari local storage untuk update UI
      if (mounted) {
        await _loadNotifications();
      }

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 3: Notifications reloaded');
      }

      // Step 4: ✅ EXPLICIT: Force trigger badge update di dashboard
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] 🔔 Step 4: Force triggering badge update...');
      }
      
      // Trigger dengan explicit delay untuk ensure state update
      await Future.delayed(const Duration(milliseconds: 300));
      await NotifikasiHelper.triggerBadgeUpdate();
      
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Step 4: Badge update forced!');
      }

      if (mounted) {
        setState(() => _isMarkingAllAsRead = false);
      }

      // Show success message
      if (mounted) {
        NotificationHelper.showSuccess('Semua notifikasi telah ditandai sebagai dibaca');
      }

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ SUCCESS: All notifications marked as read (server + local)');
      }

    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ❌ ERROR in _markAllNotificationsAsRead: $e');
      }
      if (mounted) {
        setState(() => _isMarkingAllAsRead = false);
        NotificationHelper.showError('Gagal menandai semua notifikasi');
      }
    }
  }

  @override
  void dispose() {
    if (kDebugMode) {
      debugPrint('========== [NotifikasiPage] dispose() CALLED ==========');
    }
    
    _refreshTimer?.cancel();
    try {
      NotifikasiHelper.onNotificationsChanged.removeListener(_onNotifsChanged);
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✅ Listener removed');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ⚠️ Error removing listener: $e');
      }
    }
    super.dispose();
  }

  Future<void> _initializeAndLoad() async {
    await _initializeNotificationsFromServer();
    if (mounted) {
      await _loadNotifications();
    }
    _setupAutoRefresh();
  }

  void _setupAutoRefresh() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && !_isLoading) {
        _loadNotifications();
      }
    });
  }

  void _onNotifsChanged() {
    if (kDebugMode) {
      debugPrint('[NotifikasiPage] 🔔 onNotifsChanged LISTENER TRIGGERED');
    }
    try {
      _needsRefreshNotifs = true;
      setState(() {});
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ❌ Error in _onNotifsChanged: $e');
      }
    }
  }

  Future<void> _initializeNotificationsFromServer() async {
    try {
      final user = await EventPref.getUser();
      if (user == null || (user.id ?? '').isEmpty) {
        if (kDebugMode) debugPrint('[NotifikasiPage] User not found');
        return;
      }

      final lastSync = await NotifikasiHelper.getLastSyncTime();

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Last sync: $lastSync');
      }

      final Map<String, dynamic> body = {'id_pengguna': user.id.toString()};
      if (lastSync != null) {
        body['created_after'] = lastSync.toIso8601String();
      }

      final response = await http
          .post(
            Uri.parse('${Api.baseUrl}/get_notifications.php'),
            body: body,
          )
          .timeout(const Duration(seconds: 15));

      if (response.statusCode == 200) {
        final result = jsonDecode(response.body) as Map<String, dynamic>;
        if (result['success'] == true && result['data'] is List) {
          final serverTimestamp = result['timestamp'];
          if (serverTimestamp != null) {
            await NotifikasiHelper.setLastSyncTime(
              DateTime.parse(serverTimestamp.toString()),
            );
          }

          if (kDebugMode) {
            debugPrint('[NotifikasiPage] ✅ Server sync successful, fetched ${(result['data'] as List).length} notifications');
          }
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ⚠️ Server sync error: $e');
      }
    }
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final user = await EventPref.getUser();
      final ownerId = (user?.id?.toString() ?? '').isNotEmpty
          ? user!.id.toString()
          : (user?.no_hp?.toString() ?? '');
      final prefs = await SharedPreferences.getInstance();
      final storedOwnerId = prefs.getString('notifications_owner_id') ?? '';

      bool serverFetchSuccess = false;
      try {
        if (user != null && (user.id ?? '').isNotEmpty) {
          final response = await http
              .post(
                Uri.parse('${Api.baseUrl}/get_notifications.php'),
                body: {'id_pengguna': user.id.toString()},
              )
              .timeout(const Duration(seconds: 10));

          if (response.statusCode == 200) {
            final body = jsonDecode(response.body) as Map<String, dynamic>;

            if ((body['success'] == true || body['status'] == 'SUCCESS') &&
                body['data'] is List) {
              
              final serverTimestamp = body['timestamp'];
              if (serverTimestamp != null) {
                await NotifikasiHelper.setLastSyncTime(
                  DateTime.parse(serverTimestamp.toString()),
                );
              }

              final filtered = (body['data'] as List)
                  .cast<Map<String, dynamic>>()
                  .where((n) {
                    final t = (n['type'] ?? '').toString();
                    if (!(t == 'transaksi' ||
                        t == 'topup' ||
                        t == 'tabungan' ||
                        t == 'mulai_nabung' ||
                        t == 'pinjaman' ||
                        t == 'pinjaman_kredit' ||
                        t == 'withdrawal_pending' ||
                        t == 'withdrawal_approved' ||
                        t == 'withdrawal_rejected')) {
                      return false;
                    }
                    try {
                      if (NotifikasiHelper.isExcludedNotification(n))
                        return false;
                    } catch (_) {}
                    return true;
                  })
                  .map((n) => {
                    'type': (n['type'] ?? 'transaksi').toString(),
                    'title': n['title'] ?? 'Notifikasi',
                    'message': n['message'] ?? '',
                    'created_at':
                        n['created_at'] ?? DateTime.now().toIso8601String(),
                    'read': n['read'] ?? false,
                    'data': n['data'] ?? null,
                    if (ownerId.isNotEmpty) 'owner_id': ownerId,
                  })
                  .toList();

              await prefs.setString('notifications', jsonEncode(filtered));
              if (ownerId.isNotEmpty) {
                await prefs.setString('notifications_owner_id', ownerId);
              }
              serverFetchSuccess = true;

              if (kDebugMode) {
                debugPrint(
                    '[NotifikasiPage] ✅ Server fetch success, saved ${filtered.length} notifications');
              }
            }
          }
        }
      } catch (e) {
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] ⚠️ Server fetch error: $e');
        }
      }

      final data = prefs.getString('notifications') ?? '[]';
      final List<dynamic> decoded = jsonDecode(data);

      try {
        final lastRaw = prefs.getString('last_local_notif') ?? '';
        if (lastRaw.isNotEmpty) {
          final Map<String, dynamic> lastLocal = jsonDecode(lastRaw);
          if (!NotifikasiHelper.isForOwner(
            lastLocal,
            ownerId,
            fallbackOwnerId: storedOwnerId,
          )) {
            // skip
          } else {
            try {
              if (NotifikasiHelper.isExcludedNotification(lastLocal)) {
                // skip
              } else {
                String? lastId = '';
                final lastData = lastLocal['data'];
                if (lastData != null && lastData is Map) {
                  lastId =
                      (lastData['mulai_id'] ??
                              lastData['id_mulai_nabung'] ??
                              lastData['id_transaksi'])
                          ?.toString() ??
                      '';
                }
                bool alreadyExists = false;
                for (final e in decoded) {
                  final data = e['data'];
                  if (data != null && data is Map) {
                    final eid =
                        (data['mulai_id'] ??
                                data['id_mulai_nabung'] ??
                                data['id_transaksi'])
                            ?.toString() ??
                        '';
                    if (eid.isNotEmpty && lastId.isNotEmpty && eid == lastId) {
                      alreadyExists = true;
                      break;
                    }
                  }
                }
                if (!alreadyExists) {
                  decoded.insert(0, lastLocal);
                  await prefs.setString('notifications', jsonEncode(decoded));
                }
              }
            } catch (_) {}
          }
        }
      } catch (_) {}

      final ownerFiltered = NotifikasiHelper.filterForOwner(
        decoded,
        ownerId,
        fallbackOwnerId: storedOwnerId,
      );
      final filteredLocal = ownerFiltered.where((n) {
        final t = (n['type'] ?? '').toString();
        if (!(t == 'transaksi' ||
            t == 'topup' ||
            t == 'tabungan' ||
            t == 'mulai_nabung' ||
            t == 'pinjaman' ||
            t == 'pinjaman_kredit')) {
          return false;
        }
        try {
          if (NotifikasiHelper.isExcludedNotification(n)) return false;
        } catch (_) {}
        return true;
      }).toList();

      final Map<String, Map<String, dynamic>> dikirimById = {};
      for (final n in filteredLocal) {
        final title = (n['title'] ?? '').toString();
        final message = (n['message'] ?? '').toString();
        if (title == 'Pengajuan Setoran Dikirim') {
          String? id = '';
          final data = n['data'];
          if (data != null && data is Map) {
            id = (data['mulai_id'] ??
                    data['id_mulai_nabung'] ??
                    data['id_transaksi'])
                ?.toString() ??
                '';
          }
          if (id != null && id.isNotEmpty) {
            if (dikirimById[id] != null) {
              final existingMsg =
                  (dikirimById[id]!['message'] ?? '').toString();
              if (existingMsg.contains('Tabungan') &&
                  existingMsg.contains('sebesar Rp')) {
                continue;
              }
            }
            dikirimById[id] = n;
            continue;
          }
        }
      }
      final filteredFinal = filteredLocal.where((n) {
        final title = (n['title'] ?? '').toString();
        if (title == 'Pengajuan Setoran Dikirim') {
          String? id = '';
          final data = n['data'];
          if (data != null && data is Map) {
            id = (data['mulai_id'] ??
                    data['id_mulai_nabung'] ??
                    data['id_transaksi'])
                ?.toString() ??
                '';
          }
          if (id != null && id.isNotEmpty) {
            return dikirimById[id] == n;
          }
        }
        return true;
      }).toList();

      final sortedLocal = NotifikasiHelper.sortNotificationsNewestFirst(
        filteredFinal,
      );

      setState(() {
        _notifications = sortedLocal;
        _isLoading = false;
        _errorMessage = null;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
        _errorMessage = 'Gagal memuat notifikasi: $e';
      });
      if (kDebugMode) debugPrint('[NotifikasiPage] Error: $e');
    }
  }

  Future<void> _markAsRead(int index) async {
    try {
      if ((_notifications[index]['read'] ?? false) == true) return;
      _notifications[index]['read'] = true;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('notifications', jsonEncode(_notifications));
      setState(() {});

      try {
        final notif = _notifications[index];
        final nid = notif['id'] ?? 0;
        if (nid != null &&
            int.tryParse(nid.toString() ?? '') != null &&
            int.parse(nid.toString()) > 0) {
          final user = await EventPref.getUser();
          final uid = user?.id ?? '';
          if (uid != null && uid.toString().isNotEmpty) {
            try {
              final resp = await http
                  .post(
                    Uri.parse(Api.updateNotifikasiRead),
                    body: {
                      'id_notifikasi': nid.toString(),
                      'id_pengguna': uid.toString(),
                    },
                  )
                  .timeout(const Duration(seconds: 8));
              if (resp.statusCode == 200) {
                try {
                  final json = jsonDecode(resp.body);
                  if (json['success'] != true) {
                    if (kDebugMode)
                      debugPrint(
                        '[Notifikasi] update read failed: ' +
                            (json['message'] ?? ''),
                      );
                  }
                } catch (e) {}
              }
            } catch (e) {
              if (kDebugMode)
                debugPrint('[Notifikasi] update read request error: $e');
            }
          }
        }
      } catch (_) {}
    } catch (e) {
      NotificationHelper.showError('Gagal mengubah status');
    }
  }

  Future<void> _onNotificationTap(
    int index,
    List<Map<String, dynamic>> list,
  ) async {
    final notif = list[index];
    try {
      if (kDebugMode) {
        debugPrint('=== [NotifikasiPage] NOTIFICATION TAP DEBUG ===');
        debugPrint('[NotifikasiPage] Full notification object: $notif');
      }

      if (!(notif['read'] ?? false)) {
        await _markAsRead(index);
      }

      final statusFromNotif = _getStatusFromNotif(notif);
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Status detected: $statusFromNotif');
      }

      if (statusFromNotif == 'menunggu') {
        if (kDebugMode)
          debugPrint(
            '[NotifikasiPage] Status is pending (menunggu) - NOT navigating',
          );
        return;
      }

      final data = notif['data'];
      if (kDebugMode) {
        debugPrint(
          '[NotifikasiPage] Raw data field: $data (type: ${data.runtimeType})',
        );
      }

      Map<String, dynamic>? notifParsed;
      if (data is String) {
        try {
          notifParsed = jsonDecode(data) as Map<String, dynamic>;
          if (kDebugMode)
            debugPrint(
              '[NotifikasiPage] Parsed data from JSON string: $notifParsed',
            );
        } catch (e) {
          if (kDebugMode)
            debugPrint('[NotifikasiPage] Failed to parse data JSON: $e');
          notifParsed = null;
        }
      } else if (data is Map) {
        notifParsed = Map<String, dynamic>.from(data);
        if (kDebugMode)
          debugPrint('[NotifikasiPage] Data is already a Map: $notifParsed');
      }

      dynamic mulaiId;
      dynamic tabKeluarId;
      dynamic noTransaksi;
      if (notifParsed != null && notifParsed.isNotEmpty) {
        noTransaksi = notifParsed['no_transaksi'];
        mulaiId =
            notifParsed['mulai_id'] ??
            notifParsed['id_mulai_nabung'] ??
            notifParsed['id_transaksi'];
        tabKeluarId = notifParsed['tabungan_keluar_id'];
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] Extracted no_transaksi=$noTransaksi, mulai_id=$mulaiId, tabungan_keluar_id=$tabKeluarId',
          );
        }
      }

      if (noTransaksi == null) {
        noTransaksi = notif['no_transaksi'];
      }
      if (mulaiId == null) {
        mulaiId =
            notif['mulai_id'] ??
            notif['id_mulai_nabung'] ??
            notif['id_transaksi'];
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] Extracted ID from top-level notif: $mulaiId',
          );
        }
      }
      if (tabKeluarId == null) {
        tabKeluarId = notif['tabungan_keluar_id'];
      }

      final notifTitleLower = (notif['title'] ?? '').toString().toLowerCase();
      final isPinjamanNotif = notifTitleLower.contains('pinjaman');
      final isTransferNotif =
          notifTitleLower.contains('kirim uang') ||
          (notifTitleLower.contains('transfer') &&
              !notifTitleLower.contains('terima'));
      final isReceiveTransferNotif =
          notifTitleLower.contains('terima uang') ||
          (notifTitleLower.contains('terima') &&
              notifTitleLower.contains('transfer'));
      dynamic pinjamanId;
      if (isPinjamanNotif && notifParsed != null) {
        pinjamanId = notifParsed['id'] ?? notifParsed['application_id'];
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] Pinjaman notification detected, pinjamanId=$pinjamanId',
          );
        }
      }

      final jenisFromNotif = _extractJenisTabunganFromNotif(notif, notifParsed);
      if (kDebugMode) {
        debugPrint(
          '[NotifikasiPage] Extracted jenis from notif: $jenisFromNotif',
        );
      }

      dynamic transferAmount;
      if (isTransferNotif || isReceiveTransferNotif) {
        transferAmount = notifParsed?['amount'] ?? notifParsed?['nominal'];
        if (transferAmount == null || transferAmount == 0) {
          final notifMsg = (notif['message'] ?? '').toString();
          final amtMatch = RegExp(r'Rp\s*[\d.,]+').firstMatch(notifMsg);
          if (amtMatch != null) {
            transferAmount =
                int.tryParse(
                  amtMatch.group(0)!.replaceAll(RegExp(r'[^0-9]'), ''),
                ) ??
                0;
          }
        }
      }

      if (noTransaksi != null ||
          mulaiId != null ||
          tabKeluarId != null ||
          isPinjamanNotif ||
          isTransferNotif ||
          isReceiveTransferNotif) {
        final searchId = noTransaksi ?? mulaiId ?? tabKeluarId;
        final notifMessage = (notif['message'] ?? '').toString().trim();
        if (kDebugMode)
          debugPrint(
            '[NotifikasiPage] ✓ Have transaction ID: $searchId (isPinjaman=$isPinjamanNotif, isTransfer=$isTransferNotif, isReceiveTransfer=$isReceiveTransferNotif) - fetching complete transaction data...',
          );

        final txData = await _fetchCompleteTransactionData(
          noTransaksi ?? mulaiId,
          tabKeluarId: tabKeluarId,
          notifMessage: isPinjamanNotif ? notifMessage : null,
          isPinjaman: isPinjamanNotif,
          pinjamanAmount: notifParsed?['amount'],
          isTransfer: isTransferNotif,
          transferAmount: transferAmount,
          isReceiveTransfer: isReceiveTransferNotif,
          receiveTransferAmount: isReceiveTransferNotif ? transferAmount : null,
        );

        if (txData != null) {
          if (jenisFromNotif != null && jenisFromNotif.trim().isNotEmpty) {
            txData['jenis_tabungan'] = jenisFromNotif.trim();
          }
          final notifMessage = (notif['message'] ?? '').toString().trim();
          if (notifMessage.isNotEmpty &&
              _looksLikeTabunganMessage(notifMessage)) {
            txData['keterangan'] = notifMessage;
          }
          if (noTransaksi != null) {
            txData['no_transaksi'] = noTransaksi;
          }
          if (kDebugMode) {
            debugPrint(
              '[NotifikasiPage] ✓✓✓ Got complete transaction! Navigating to detail page...',
            );
            debugPrint('[NotifikasiPage] Transaction data: $txData');
          }
          Get.to(
            () => TransactionDetailPage(transaction: txData),
            transition: Transition.rightToLeft,
            duration: const Duration(milliseconds: 300),
          );
          return;
        } else {
          if (kDebugMode) {
            debugPrint(
              '[NotifikasiPage] ✗ Failed to fetch complete transaction data for ID: $searchId',
            );
          }
        }
      }

      {
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] Building synthetic transaction from notification data...',
          );
        }
        final notifTitle = (notif['title'] ?? '').toString();
        final notifMessage = (notif['message'] ?? '').toString();
        final notifTime = (notif['created_at'] ?? '').toString();

        final titleLower = notifTitle.toLowerCase();
        String jenisTransaksi = 'penarikan';
        String status = 'pending';

        if (titleLower.contains('pinjaman') &&
            titleLower.contains('disetujui')) {
          jenisTransaksi = 'pinjaman';
          status = 'approved';
        } else if (titleLower.contains('pinjaman') &&
            titleLower.contains('ditolak')) {
          jenisTransaksi = 'pinjaman';
          status = 'rejected';
        } else if (titleLower.contains('pinjaman') &&
            (titleLower.contains('diajukan') ||
                titleLower.contains('verifikasi') ||
                titleLower.contains('menunggu'))) {
          jenisTransaksi = 'pinjaman';
          status = 'pending';
        } else if (titleLower.contains('pencairan disetujui')) {
          status = 'approved';
        } else if (titleLower.contains('pencairan ditolak')) {
          status = 'rejected';
        } else if (titleLower.contains('setoran') &&
            titleLower.contains('disetujui')) {
          jenisTransaksi = 'setoran';
          status = 'approved';
        } else if (titleLower.contains('setoran') &&
            titleLower.contains('ditolak')) {
          jenisTransaksi = 'setoran';
          status = 'rejected';
        } else if (titleLower.contains('pengajuan pencairan')) {
          status = 'pending';
        } else if (titleLower.contains('pengajuan setoran')) {
          jenisTransaksi = 'setoran';
          status = 'pending';
        } else if (titleLower.contains('kirim uang') ||
            (titleLower.contains('transfer') &&
                titleLower.contains('berhasil') &&
                !titleLower.contains('terima'))) {
          jenisTransaksi = 'transfer_keluar';
          status = 'approved';
        } else if (titleLower.contains('terima uang') ||
            (titleLower.contains('terima') &&
                titleLower.contains('transfer'))) {
          jenisTransaksi = 'transfer_masuk';
          status = 'approved';
        }

        dynamic amount = notifParsed?['amount'] ?? 0;
        if (amount == 0 || amount == null) {
          final amtMatch = RegExp(r'Rp\s*[\d.,]+').firstMatch(notifMessage);
          if (amtMatch != null) {
            amount =
                int.tryParse(
                  amtMatch.group(0)!.replaceAll(RegExp(r'[^0-9]'), ''),
                ) ??
                0;
          }
        }

        dynamic tenor = notifParsed?['tenor'] ?? 0;
        if ((tenor == 0 || tenor == null) && jenisTransaksi == 'pinjaman') {
          final tenorMatch = RegExp(
            r'tenor\s*(\d+)\s*bulan',
            caseSensitive: false,
          ).firstMatch(notifMessage);
          if (tenorMatch != null) {
            tenor = int.tryParse(tenorMatch.group(1) ?? '') ?? 0;
          }
        }

        String? syntheticNoTransaksi = notifParsed?['no_transaksi'];
        if ((syntheticNoTransaksi == null || syntheticNoTransaksi.isEmpty) &&
            (jenisTransaksi == 'setoran' ||
                jenisTransaksi == 'setoran tabungan')) {
          final idTransaksi =
              notifParsed?['id_transaksi'] ??
              notifParsed?['id'] ??
              notifParsed?['application_id'] ??
              mulaiId;
          final now = DateTime.now();
          final dateStr =
              '${now.year.toString().padLeft(4, '0')}${now.month.toString().padLeft(2, '0')}${now.day.toString().padLeft(2, '0')}';
          final idStr = (idTransaksi ?? '').toString().padLeft(6, '0');
          if (idTransaksi != null && idStr != '000000') {
            syntheticNoTransaksi = 'SAV-$dateStr-$idStr';
          }
        }
        final syntheticTx = <String, dynamic>{
          'id_transaksi':
              notifParsed?['id_transaksi'] ??
              notifParsed?['id'] ??
              notifParsed?['application_id'] ??
              mulaiId ??
              0,
          'no_transaksi': syntheticNoTransaksi ?? '',
          'jenis_transaksi': jenisTransaksi,
          'jumlah': amount,
          'status': status,
          'jenis_tabungan': jenisFromNotif ?? '',
          'keterangan': notifMessage,
          'created_at': notifTime,
          if (tenor != null && tenor != 0) 'tenor': tenor,
          '_isSynthetic': true,
          if (tabKeluarId != null) '_tabKeluarId': tabKeluarId,
        };
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Synthetic transaction: $syntheticTx');
        }
        Get.to(
          () => TransactionDetailPage(transaction: syntheticTx),
          transition: Transition.rightToLeft,
          duration: const Duration(milliseconds: 300),
        );
        return;
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ERROR in _onNotificationTap: $e');
      }
    }
  }

  String? _extractJenisTabunganFromNotif(
    Map<String, dynamic> notif,
    Map<String, dynamic>? notifParsed,
  ) {
    final msg = (notif['message'] ?? '').toString();
    if (msg.isNotEmpty) {
      final match = RegExp(
        r'(?:dari\s+)?Tabungan\s+([A-Za-z]+(?:\s+[A-Za-z]+)*?)(?:\s+(?:Anda|anda|sebesar|ditolak|disetujui|menunggu|sedang|berhasil|telah|dikirim)|,|\.)',
        caseSensitive: false,
      ).firstMatch(msg);
      if (match != null && match.groupCount >= 1) {
        final extracted = match.group(1)?.trim();
        if (extracted != null && extracted.isNotEmpty) {
          return extracted;
        }
      }
    }

    String? pickFromMap(Map<String, dynamic>? m) {
      if (m == null) return null;
      const keys = [
        'jenis_name',
        'jenis_tabungan',
        'jenis',
        'nama_tabungan',
        'tabungan',
        'purpose',
      ];
      for (final k in keys) {
        final v = m[k];
        if (v != null && v.toString().trim().isNotEmpty) {
          return v.toString().trim();
        }
      }
      return null;
    }

    final fromParsed = pickFromMap(notifParsed);
    if (fromParsed != null) return fromParsed;

    return null;
  }

  bool _looksLikeTabunganMessage(String message) {
    final lower = message.toLowerCase();
    if (!lower.contains('tabungan')) return false;
    return lower.contains('ditolak') ||
        lower.contains('disetujui') ||
        lower.contains('menunggu') ||
        lower.contains('sedang') ||
        lower.contains('berhasil') ||
        lower.contains('sukses');
  }

  Future<Map<String, dynamic>?> _fetchCompleteTransactionData(
    dynamic mulaiId, {
    dynamic tabKeluarId,
    String? notifMessage,
    bool isPinjaman = false,
    dynamic pinjamanAmount,
    bool isTransfer = false,
    dynamic transferAmount,
    bool isReceiveTransfer = false,
    dynamic receiveTransferAmount,
  }) async {
    try {
      if (mulaiId == null &&
          tabKeluarId == null &&
          !isPinjaman &&
          !isTransfer &&
          !isReceiveTransfer)
        return null;

      final user = await EventPref.getUser();
      if (user == null || (user.id ?? '').isEmpty) {
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] ✗ Cannot fetch transaction: user not found or has no ID',
          );
        }
        return null;
      }

      final userId = user.id ?? '';

      if (kDebugMode) {
        debugPrint(
          '[NotifikasiPage] Fetching complete transaction: userId=$userId, mulaiId=$mulaiId',
        );
      }

      final response = await http
          .post(
            Uri.parse('${Api.baseUrl}/get_riwayat_transaksi.php'),
            body: {'id_pengguna': userId},
          )
          .timeout(const Duration(seconds: 15));

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Response status: ${response.statusCode}');
      }

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] API Response: success=${body['success']}, data count=${body['data'] is List ? (body['data'] as List).length : 0}',
          );
        }

        if ((body['success'] == true || body['status'] == 'SUCCESS') &&
            body['data'] is List) {
          final transactions = (body['data'] as List)
              .cast<Map<String, dynamic>>();

          if (kDebugMode) {
            debugPrint(
              '[NotifikasiPage] Searching through ${transactions.length} transactions for match...',
            );
          }

          for (final tx in transactions) {
            final txMulaiId = tx['id_mulai_nabung'];
            final txTransaksiId = tx['id_transaksi'] ?? tx['id_transaksi'];
            final txKeterangan = (tx['keterangan'] ?? '').toString();
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiPage]   Checking: id_mulai_nabung=$txMulaiId, id_transaksi=$txTransaksiId vs searching mulaiId=$mulaiId, tabKeluarId=$tabKeluarId',
              );
            }

            if (mulaiId != null) {
              if ((txMulaiId != null && txMulaiId == mulaiId) ||
                  (txMulaiId != null &&
                      txMulaiId.toString() == mulaiId.toString()) ||
                  (txTransaksiId != null && txTransaksiId == mulaiId) ||
                  (txTransaksiId != null &&
                      txTransaksiId.toString() == mulaiId.toString())) {
                if (kDebugMode) {
                  debugPrint(
                    '[NotifikasiPage] ✓✓✓ MATCHED by ID! Transaction: ${tx['jenis_transaksi']} - Rp ${tx['jumlah']} - Status: ${tx['status']}',
                  );
                }
                return tx;
              }
            }

            if (tabKeluarId != null &&
                txKeterangan.contains('[tabungan_keluar_id=$tabKeluarId]')) {
              if (kDebugMode) {
                debugPrint(
                  '[NotifikasiPage] ✓✓✓ MATCHED by keterangan tabungan_keluar_id! Transaction: ${tx['jenis_transaksi']} - Rp ${tx['jumlah']}',
                );
              }
              return tx;
            }
          }

          if (isTransfer && transferAmount != null) {
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiPage] Trying transfer amount-based matching (amount=$transferAmount)...',
              );
            }
            Map<String, dynamic>? bestTransferMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '')
                  .toString()
                  .toLowerCase();
              if (txJenis != 'transfer_keluar') continue;
              final txAmount = tx['jumlah'];
              if (txAmount != null &&
                  txAmount.toString() == transferAmount.toString()) {
                if (bestTransferMatch == null) {
                  bestTransferMatch = tx;
                } else {
                  final currentId =
                      int.tryParse(
                        (bestTransferMatch['id_transaksi'] ?? 0).toString(),
                      ) ??
                      0;
                  final newId =
                      int.tryParse((tx['id_transaksi'] ?? 0).toString()) ?? 0;
                  if (newId > currentId) {
                    bestTransferMatch = tx;
                  }
                }
              }
            }
            if (bestTransferMatch != null) {
              if (kDebugMode) {
                debugPrint(
                  '[NotifikasiPage] ✓✓✓ MATCHED transfer by amount! Transaction: ${bestTransferMatch['jenis_transaksi']} - id=${bestTransferMatch['id_transaksi']}',
                );
              }
              return bestTransferMatch;
            }
          }

          if (isReceiveTransfer && receiveTransferAmount != null) {
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiPage] Trying receive transfer (transfer_masuk) amount-based matching (amount=$receiveTransferAmount)...',
              );
            }
            Map<String, dynamic>? bestReceiveMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '')
                  .toString()
                  .toLowerCase();
              if (txJenis != 'transfer_masuk') continue;
              final txAmount = tx['jumlah'];
              if (txAmount != null &&
                  txAmount.toString() == receiveTransferAmount.toString()) {
                if (bestReceiveMatch == null) {
                  bestReceiveMatch = tx;
                } else {
                  final currentId =
                      int.tryParse(
                        (bestReceiveMatch['id_transaksi'] ?? 0).toString(),
                      ) ??
                      0;
                  final newId =
                      int.tryParse((tx['id_transaksi'] ?? 0).toString()) ?? 0;
                  if (newId > currentId) {
                    bestReceiveMatch = tx;
                  }
                }
              }
            }
            if (bestReceiveMatch != null) {
              if (kDebugMode) {
                debugPrint(
                  '[NotifikasiPage] ✓✓✓ MATCHED receive transfer by amount! Transaction: ${bestReceiveMatch['jenis_transaksi']} - id=${bestReceiveMatch['id_transaksi']}',
                );
              }
              return bestReceiveMatch;
            }
          }

          if (isPinjaman && notifMessage != null && notifMessage.isNotEmpty) {
            if (kDebugMode) {
              debugPrint(
                '[NotifikasiPage] Trying pinjaman keterangan-based matching...',
              );
            }
            final notifMsgNorm = notifMessage.trim().toLowerCase();
            Map<String, dynamic>? bestMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '')
                  .toString()
                  .toLowerCase();
              if (!txJenis.contains('pinjaman')) continue;
              final txKeterangan = (tx['keterangan'] ?? '')
                  .toString()
                  .trim()
                  .toLowerCase();
              if (txKeterangan == notifMsgNorm) {
                bestMatch = tx;
                break;
              }
              if (txKeterangan.isNotEmpty &&
                  (txKeterangan.contains(notifMsgNorm) ||
                      notifMsgNorm.contains(txKeterangan))) {
                bestMatch = tx;
                break;
              }
              if (pinjamanAmount != null && bestMatch == null) {
                final txAmount = tx['jumlah'];
                if (txAmount != null &&
                    txAmount.toString() == pinjamanAmount.toString()) {
                  bestMatch = tx;
                }
              }
            }
            if (bestMatch != null) {
              if (kDebugMode) {
                debugPrint(
                  '[NotifikasiPage] ✓✓✓ MATCHED pinjaman by keterangan! Transaction: ${bestMatch['jenis_transaksi']} - id=${bestMatch['id_transaksi']}',
                );
              }
              return bestMatch;
            }
          }

          if (kDebugMode) {
            debugPrint(
              '[NotifikasiPage] ✗ No matching transaction found in API response for mulaiId=$mulaiId',
            );
          }
        } else {
          if (kDebugMode) {
            debugPrint(
              '[NotifikasiPage] ✗ API response missing data or success is not true. Body: $body',
            );
          }
        }
      } else {
        if (kDebugMode) {
          debugPrint(
            '[NotifikasiPage] ✗ API request failed with status: ${response.statusCode}',
          );
          debugPrint('[NotifikasiPage] Response body: ${response.body}');
        }
      }

      return null;
    } catch (e) {
      if (kDebugMode) {
        debugPrint(
          '[NotifikasiPage] ✗✗✗ ERROR fetching transaction from API: $e',
        );
      }
      return null;
    }
  }

  String _getToken() {
    return '';
  }

  String _formatTime(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime.now();

      final Duration diff = now.difference(date);

      if (diff.inSeconds < 60) {
        return 'Baru saja';
      }

      if (diff.inMinutes < 60) {
        final mins = diff.inMinutes;
        return mins == 1 ? '$mins menit lalu' : '$mins menit lalu';
      }

      if (diff.inHours < 24) {
        final hours = diff.inHours;
        return hours == 1 ? '$hours jam lalu' : '$hours jam lalu';
      }

      if (diff.inDays < 7) {
        final days = diff.inDays;
        return days == 1 ? '$days hari lalu' : '$days hari lalu';
      }

      return DateFormat('dd MMM yyyy').format(date);
    } catch (_) {
      return 'Notifikasi';
    }
  }

  IconData _getIconForType(String type) {
    switch (type) {
      case 'transaksi':
        return Icons.payment;
      case 'promo':
        return Icons.local_offer;
      case 'pinjaman':
        return Icons.attach_money;
      case 'sistem':
        return Icons.info_outline;
      case 'peringatan':
        return Icons.warning_amber;
      default:
        return Icons.notifications;
    }
  }

  Color _getColorForType(String type) {
    switch (type) {
      case 'transaksi':
        return const Color(0xFF4CAF50);
      case 'promo':
        return const Color(0xFFFF9800);
      case 'pinjaman':
        return const Color(0xFF2196F3);
      case 'sistem':
        return const Color(0xFF9C27B0);
      case 'peringatan':
        return const Color(0xFFF44336);
      default:
        return const Color(0xFFFF4D00);
    }
  }

  String? _getStatusFromNotif(Map<String, dynamic> n) {
    try {
      dynamic d = n['data'];
      if (d != null && d is String) {
        try {
          d = jsonDecode(d);
        } catch (_) {}
      }
      if (d != null && d is Map) {
        final s = (d['status'] ?? '').toString().toLowerCase();
        if (s.isNotEmpty) {
          if (s.contains('berhasil') ||
              s.contains('sukses') ||
              s.contains('success') ||
              s.contains('done'))
            return 'berhasil';
          if (s.contains('ditolak') ||
              s.contains('tolak') ||
              s.contains('rejected'))
            return 'ditolak';
          if (s.contains('menunggu') || s.contains('pending'))
            return 'menunggu';
        }
      }
    } catch (_) {}

    final title = (n['title'] ?? '').toString().toLowerCase();
    if (title.contains('disetujui') || title.contains('berhasil'))
      return 'berhasil';
    if (title.contains('ditolak')) return 'ditolak';
    if (title.contains('terima uang') || title.contains('kirim uang'))
      return 'berhasil';
    if (title.contains('pengajuan') ||
        title.contains('menunggu') ||
        title.contains('verifikasi'))
      return 'menunggu';
    return null;
  }

  Color _getColorForStatus(String status) {
    switch (status) {
      case 'menunggu':
        return const Color(0xFFFF9800);
      case 'berhasil':
        return const Color(0xFF4CAF50);
      case 'ditolak':
        return const Color(0xFFF44336);
      default:
        return const Color(0xFFFF4D00);
    }
  }

  IconData _getIconForStatus(String status) {
    switch (status) {
      case 'menunggu':
        return Icons.hourglass_bottom;
      case 'berhasil':
        return Icons.check_circle;
      case 'ditolak':
        return Icons.cancel;
      default:
        return Icons.notifications;
    }
  }

  Widget _buildNotificationCard(
    Map<String, dynamic> notif,
    int index,
    List<Map<String, dynamic>> list,
  ) {
    final isRead = notif['read'] ?? false;
    final type = notif['type'] ?? 'sistem';
    final title = notif['title'] ?? 'Notifikasi';
    final message = notif['message'] ?? '';
    final time = notif['created_at'] ?? '';
    final status = _getStatusFromNotif(notif);
    final iconData = status != null
        ? _getIconForStatus(status)
        : _getIconForType(type);
    final color = status != null
        ? _getColorForStatus(status)
        : _getColorForType(type);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Material(
          color: Theme.of(context).cardColor,
          child: InkWell(
            onTap: () => _onNotificationTap(index, list),
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: (isRead
                    ? Theme.of(context).cardColor
                    : color.withOpacity(0.06)),
                border: Border(left: BorderSide(color: color, width: 4)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: color.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Center(
                          child: Icon(iconData, color: color, size: 22),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    title,
                                    style: GoogleFonts.roboto(
                                      fontSize: 14,
                                      fontWeight: isRead
                                          ? FontWeight.w400
                                          : FontWeight.w700,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                const SizedBox.shrink(),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _formatTime(time),
                              style: GoogleFonts.roboto(
                                fontSize: 12,
                                color: Theme.of(
                                  context,
                                ).textTheme.bodySmall?.color,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    message,
                    style: GoogleFonts.roboto(
                      fontSize: 13,
                      color: Theme.of(context).textTheme.bodyMedium?.color,
                      height: 1.5,
                    ),
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    final theme = Theme.of(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.notifications_none, size: 80, color: theme.dividerColor),
          const SizedBox(height: 16),
          Text(
            'Tidak ada notifikasi',
            style: GoogleFonts.roboto(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: theme.textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Notifikasi baru akan muncul di sini',
            style: GoogleFonts.roboto(
              fontSize: 13,
              color: theme.textTheme.bodySmall?.color,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState() {
    final theme = Theme.of(context);
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.error_outline, size: 80, color: Colors.red[300]),
          const SizedBox(height: 16),
          Text(
            'Gagal memuat notifikasi',
            style: GoogleFonts.roboto(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: theme.textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _errorMessage ?? 'Terjadi kesalahan saat memuat data',
            style: GoogleFonts.roboto(
              fontSize: 12,
              color: theme.textTheme.bodySmall?.color,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 16),
          ElevatedButton(
            onPressed: _loadNotifications,
            child: Text('Coba Lagi', style: GoogleFonts.roboto()),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Column(
        children: [
          // ✅ Custom Header - Centered Title + Refresh Button + Mark All Read Button
          Container(
            padding: EdgeInsets.fromLTRB(
              12,
              MediaQuery.of(context).padding.top + 16,
              12,
              16,
            ),
            width: double.infinity,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
              ),
            ),
            child: Stack(
              alignment: Alignment.center,
              children: [
                // Back Button (Left)
                Align(
                  alignment: Alignment.centerLeft,
                  child: GestureDetector(
                    onTap: () => Navigator.of(context).maybePop(),
                    child: const Icon(
                      Icons.arrow_back,
                      color: Colors.white,
                      size: 26,
                    ),
                  ),
                ),
                // Centered Title
                Center(
                  child: Text(
                    'Notifikasi',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.roboto(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 18,
                    ),
                  ),
                ),
                // ✅ NEW: Mark All As Read Button (Right)
                Align(
                  alignment: Alignment.centerRight,
                  child: _notifications.isNotEmpty
                      ? GestureDetector(
                          onTap: _isMarkingAllAsRead
                              ? null
                              : _markAllNotificationsAsRead,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (_isMarkingAllAsRead)
                                  const SizedBox(
                                    width: 12,
                                    height: 12,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      valueColor:
                                          AlwaysStoppedAnimation<Color>(
                                        Colors.white,
                                      ),
                                    ),
                                  )
                                else
                                  const Icon(
                                    Icons.done_all,
                                    color: Colors.white,
                                    size: 16,
                                  ),
                                const SizedBox(width: 6),
                                Text(
                                  'Baca Semua',
                                  style: GoogleFonts.roboto(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                    color: Colors.white,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      : const SizedBox.shrink(),
                ),
              ],
            ),
          ),

          // ✅ Content dengan Pull-to-Refresh & Error Handling
          Expanded(
            child: _isLoading
                ? Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(
                        const Color(0xFFFF4D00),
                      ),
                    ),
                  )
                : (_errorMessage != null
                    ? _buildErrorState()
                    : (_notifications.isEmpty
                        ? _buildEmptyState()
                        : RefreshIndicator(
                            onRefresh: _loadNotifications,
                            color: const Color(0xFFFF4D00),
                            child: ListView.builder(
                              padding: const EdgeInsets.symmetric(vertical: 8),
                              itemCount: _notifications.length,
                              itemBuilder: (context, index) {
                                return _buildNotificationCard(
                                  _notifications[index],
                                  index,
                                  _notifications,
                                );
                              },
                            ),
                          ))),
          ),
        ],
      ),
    );
  }
}