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

  Timer? _refreshTimer;
  bool _needsRefreshNotifs = false;

  @override
  void initState() {
    super.initState();
    _loadNotifications();

    // Listen for programmatic notification changes (e.g., local topup added)
    try {
      NotifikasiHelper.onNotificationsChanged.addListener(_onNotifsChanged);
    } catch (_) {}

    // REMOVED: Automatic periodic refresh - user will do manual refresh instead
    // This gives better control and prevents unnecessary network calls
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    try {
      NotifikasiHelper.onNotificationsChanged.removeListener(_onNotifsChanged);
    } catch (_) {}
    super.dispose();
  }

  void _onNotifsChanged() {
    // When a local notification is added/initialized, mark as needing refresh
    // instead of auto-refreshing to avoid interrupting the user.
    try {
      _needsRefreshNotifs = true;
      setState(() {});
    } catch (_) {}
  }

  Future<void> _loadNotifications() async {
    setState(() => _isLoading = true);
    try {
      final user = await EventPref.getUser();
      final ownerId = (user?.id?.toString() ?? '').isNotEmpty
          ? user!.id.toString()
          : (user?.no_hp?.toString() ?? '');
      final prefs = await SharedPreferences.getInstance();
      final storedOwnerId = prefs.getString('notifications_owner_id') ?? '';

      // Try to fetch latest notifications from server first (real-time-ish)
      try {
        if (user != null && (user.id ?? '').isNotEmpty) {
          final server = await EventDB.getNotifications(user.id ?? '');
          final filtered = server
              .where((n) {
                final t = (n['type'] ?? '').toString();
                // Accept legacy 'tabungan' plus 'transaksi', 'topup', 'mulai_nabung', 'pinjaman' (including pinjaman_kredit),
                // and withdrawal-related types ('withdrawal_pending', 'withdrawal_approved', 'withdrawal_rejected')
                if (!(t == 'transaksi' ||
                    t == 'topup' ||
                    t == 'tabungan' ||
                    t == 'mulai_nabung' ||
                    t == 'pinjaman' ||
                    t == 'pinjaman_kredit' ||
                    t == 'withdrawal_pending' ||
                    t == 'withdrawal_approved' ||
                    t == 'withdrawal_rejected'))
                  return false;
                // Exclude generic processing/waiting/verifikasi texts for non-loan notifications
                try {
                  if (NotifikasiHelper.isExcludedNotification(n)) return false;
                } catch (_) {}
                return true;
              })
              .map(
                (n) => {
                  'type': (n['type'] ?? 'transaksi').toString(),
                  'title': n['title'] ?? 'Notifikasi',
                  'message': n['message'] ?? '',
                  'created_at':
                      n['created_at'] ?? DateTime.now().toIso8601String(),
                  'read': n['read'] ?? false,
                  'data': n['data'] ?? null,
                  if (ownerId.isNotEmpty) 'owner_id': ownerId,
                },
              )
              .toList();

          // Merge server results with existing local notifications so immediate local
          // notifications (added after submit) are not overwritten by an empty/slow server response.
          if (filtered.isNotEmpty) {
            try {
              final existingRaw = prefs.getString('notifications') ?? '[]';
              final List<dynamic> existingList = jsonDecode(existingRaw);
              // Use the FULL existing list for merge (safe cast), not owner-filtered subset
              // Owner filtering is only for display, not for storage
              final safeExisting = NotifikasiHelper.filterForOwner(
                existingList,
                '',  // empty = return all items safely cast
              );

              // Load blacklist of deleted notifications
              final blacklistRaw =
                  prefs.getString('notifications_blacklist') ?? '[]';
              final List<dynamic> blacklistDyn = jsonDecode(blacklistRaw);
              final Set<String> blacklist = Set<String>.from(
                blacklistDyn.cast<String>(),
              );

              final merged = NotifikasiHelper.mergeServerWithExisting(
                List<Map<String, dynamic>>.from(filtered),
                safeExisting,
                blacklist: blacklist,
              );

              // Ensure newest-first ordering before persisting
              final sortedMerged =
                  NotifikasiHelper.sortNotificationsNewestFirst(merged);
              await prefs.setString('notifications', jsonEncode(sortedMerged));
              if (ownerId.isNotEmpty) {
                await prefs.setString('notifications_owner_id', ownerId);
              }
            } catch (_) {
              // If merge fails, do NOT overwrite prefs with server-only data
              // to avoid permanently destroying local-only notifications.
              // Local cache is preserved as-is.
            }
          } else {
            // If server returned empty, avoid overwriting local notifications so immediate
            // local items remain visible while server-side processing completes.
          }
        }
      } catch (_) {
        // Ignore server-side fetch errors and continue with local cache
      }

      // Load local notifications and filter for display
      final data = prefs.getString('notifications') ?? '[]';
      if (kDebugMode)
        debugPrint('[NotifikasiPage] prefs.notifications = ' + data);
      final List<dynamic> decoded = jsonDecode(data);

      // If there is an un-included local notification (last_local_notif), insert it at top
      try {
        final lastRaw = prefs.getString('last_local_notif') ?? '';
        if (lastRaw.isNotEmpty) {
          final Map<String, dynamic> lastLocal = jsonDecode(lastRaw);
          if (!NotifikasiHelper.isForOwner(
            lastLocal,
            ownerId,
            fallbackOwnerId: storedOwnerId,
          )) {
            if (kDebugMode)
              debugPrint(
                '[NotifikasiPage] skipped last_local_notif because owner mismatch',
              );
          } else {
          // Skip inserting last_local_notif if it should be excluded per helper rules
          try {
            if (NotifikasiHelper.isExcludedNotification(lastLocal)) {
              if (kDebugMode)
                debugPrint(
                  '[NotifikasiPage] skipped last_local_notif because it is excluded',
                );
            } else {
              final lastKey =
                  (lastLocal['title'] ?? '').toString() +
                  '|' +
                  (lastLocal['message'] ?? '').toString();
              final existingKeys = decoded.cast<Map<String, dynamic>>().map((
                e,
              ) {
                final t = (e['title'] ?? '').toString();
                final m = (e['message'] ?? '').toString();
                return t + '|' + m;
              }).toSet();
              if (!existingKeys.contains(lastKey)) {
                decoded.insert(0, lastLocal);
                // persist merged result so subsequent reads see it
                await prefs.setString('notifications', jsonEncode(decoded));
                if (kDebugMode)
                  debugPrint(
                    '[NotifikasiPage] inserted last_local_notif into prefs.notifications',
                  );
              }
            }
          } catch (_) {}
          }
        }
      } catch (_) {}

      // Keep only transaction-related notifications (no dummy/system/promo)
      final ownerFiltered = NotifikasiHelper.filterForOwner(
        decoded,
        ownerId,
        fallbackOwnerId: storedOwnerId,
      );
      final filteredLocal = ownerFiltered.where((n) {
        final t = (n['type'] ?? '').toString();

        // Accept transaksi, topup, legacy 'tabungan', mulai_nabung and pinjaman (treated as transaction-like)
        if (!(t == 'transaksi' ||
            t == 'topup' ||
            t == 'tabungan' ||
            t == 'mulai_nabung' ||
            t == 'pinjaman' ||
            t == 'pinjaman_kredit')) {
          return false;
        }

        // Use the centralized exclusion rules so behavior is consistent with NotifikasiHelper
        try {
          if (NotifikasiHelper.isExcludedNotification(n)) return false;
        } catch (_) {}

        return true;
      }).toList();

      // Sort newest -> oldest before displaying
      final sortedLocal = NotifikasiHelper.sortNotificationsNewestFirst(
        filteredLocal,
      );

      setState(() {
        _notifications = sortedLocal;
        _isLoading = false;
      });
    } catch (e) {
      setState(() => _isLoading = false);
      NotificationService.showError('Gagal memuat notifikasi');
    }
  }

  Future<void> _markAsRead(int index) async {
    try {
      // Optimistic local update so UI changes immediately
      if ((_notifications[index]['read'] ?? false) == true) return;
      _notifications[index]['read'] = true;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('notifications', jsonEncode(_notifications));
      setState(() {});

      // Try to update server-side read_status if this notification has an id and user is known
      try {
        final notif = _notifications[index];
        final nid = notif['id'] ?? 0;
        if (nid != null &&
            int.tryParse(nid.toString() ?? '') != null &&
            int.parse(nid.toString()) > 0) {
          final user = await EventPref.getUser();
          final uid = user?.id ?? '';
          if (uid != null && uid.toString().isNotEmpty) {
            // Fire-and-forget but catch errors to avoid crashing the UI
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
                // parse response but we don't strictly require success to keep UI responsive
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
      NotificationService.showError('Gagal mengubah status');
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

      // mark as read locally first
      if (!(notif['read'] ?? false)) {
        await _markAsRead(index);
      }

      // Get status from notification
      final statusFromNotif = _getStatusFromNotif(notif);
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Status detected: $statusFromNotif');
      }

      // Only navigate for final statuses: 'berhasil' or 'ditolak'
      if (statusFromNotif == 'menunggu') {
        if (kDebugMode) debugPrint('[NotifikasiPage] Status is pending (menunggu) - NOT navigating');
        return;
      }

      // Parse notification data
      final data = notif['data'];
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Raw data field: $data (type: ${data.runtimeType})');
      }

      Map<String, dynamic>? notifParsed;
      if (data is String) {
        try {
          notifParsed = jsonDecode(data) as Map<String, dynamic>;
          if (kDebugMode) debugPrint('[NotifikasiPage] Parsed data from JSON string: $notifParsed');
        } catch (e) {
          if (kDebugMode) debugPrint('[NotifikasiPage] Failed to parse data JSON: $e');
          notifParsed = null;
        }
      } else if (data is Map) {
        notifParsed = Map<String, dynamic>.from(data);
        if (kDebugMode) debugPrint('[NotifikasiPage] Data is already a Map: $notifParsed');
      }

      // Extract transaction ID from notification data
      // Support multiple ID formats: mulai_id, id_mulai_nabung, id_transaksi, tabungan_keluar_id
      // Also support pinjaman-specific keys: id, application_id
      dynamic mulaiId;
      dynamic tabKeluarId;
      if (notifParsed != null && notifParsed.isNotEmpty) {
        mulaiId = notifParsed['mulai_id'] ?? 
                  notifParsed['id_mulai_nabung'] ?? 
                  notifParsed['id_transaksi'];
        tabKeluarId = notifParsed['tabungan_keluar_id'];
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Extracted mulai_id=$mulaiId, tabungan_keluar_id=$tabKeluarId');
        }
      }

      // Also try to extract from top-level notification
      if (mulaiId == null) {
        mulaiId = notif['mulai_id'] ?? 
                  notif['id_mulai_nabung'] ?? 
                  notif['id_transaksi'];
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Extracted ID from top-level notif: $mulaiId');
        }
      }
      if (tabKeluarId == null) {
        tabKeluarId = notif['tabungan_keluar_id'];
      }

      // Detect if this is a pinjaman notification
      final notifTitleLower = (notif['title'] ?? '').toString().toLowerCase();
      final isPinjamanNotif = notifTitleLower.contains('pinjaman');
      // Detect if this is a transfer notification (sender side)
      final isTransferNotif = notifTitleLower.contains('kirim uang') ||
          (notifTitleLower.contains('transfer') && !notifTitleLower.contains('terima'));
      // Detect if this is a receive-transfer notification (receiver side)
      final isReceiveTransferNotif = notifTitleLower.contains('terima uang') ||
          (notifTitleLower.contains('terima') && notifTitleLower.contains('transfer'));
      // Extract pinjaman-specific IDs (from pinjaman_approval.php / approval_helpers.php)
      dynamic pinjamanId;
      if (isPinjamanNotif && notifParsed != null) {
        pinjamanId = notifParsed['id'] ?? notifParsed['application_id'];
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Pinjaman notification detected, pinjamanId=$pinjamanId');
        }
      }

      // Extract correct jenis from notification (for overriding wrong API data)
      final jenisFromNotif = _extractJenisTabunganFromNotif(notif, notifParsed);
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Extracted jenis from notif: $jenisFromNotif');
      }

      // Extract transfer amount from notification data for matching
      dynamic transferAmount;
      if (isTransferNotif || isReceiveTransferNotif) {
        transferAmount = notifParsed?['amount'] ?? notifParsed?['nominal'];
        if (transferAmount == null || transferAmount == 0) {
          final notifMsg = (notif['message'] ?? '').toString();
          final amtMatch = RegExp(r'Rp\s*[\d.,]+').firstMatch(notifMsg);
          if (amtMatch != null) {
            transferAmount = int.tryParse(amtMatch.group(0)!.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
          }
        }
      }

      // DIRECT NAVIGATION: Extract transaction ID and navigate
      if (mulaiId != null || tabKeluarId != null || isPinjamanNotif || isTransferNotif || isReceiveTransferNotif) {
        final searchId = mulaiId ?? tabKeluarId;
        final notifMessage = (notif['message'] ?? '').toString().trim();
        if (kDebugMode) debugPrint('[NotifikasiPage] ✓ Have transaction ID: $searchId (isPinjaman=$isPinjamanNotif, isTransfer=$isTransferNotif, isReceiveTransfer=$isReceiveTransferNotif) - fetching complete transaction data...');
        
        // FETCH complete transaction data from API using the ID from notification
        // For pinjaman: also pass notification message for keterangan-based matching
        // Pass tabKeluarId separately so we don't confuse it with mulaiId/id_transaksi
        final txData = await _fetchCompleteTransactionData(
          mulaiId,
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
          // ALWAYS override jenis_tabungan from notification source (more reliable than API)
          if (jenisFromNotif != null && jenisFromNotif.trim().isNotEmpty) {
            txData['jenis_tabungan'] = jenisFromNotif.trim();
          }
          final notifMessage = (notif['message'] ?? '').toString().trim();
          if (notifMessage.isNotEmpty && _looksLikeTabunganMessage(notifMessage)) {
            txData['keterangan'] = notifMessage;
          }
          if (kDebugMode) {
            debugPrint('[NotifikasiPage] ✓✓✓ Got complete transaction! Navigating to detail page...');
            debugPrint('[NotifikasiPage] Transaction data: $txData');
          }
          // DIRECT navigate to TransactionDetailPage (NO Riwayat page!)
          Get.to(
            () => TransactionDetailPage(transaction: txData),
            transition: Transition.rightToLeft,
            duration: const Duration(milliseconds: 300),
          );
          return;
        } else {
          if (kDebugMode) {
            debugPrint('[NotifikasiPage] ✗ Failed to fetch complete transaction data for ID: $searchId');
          }
        }
      }
      
      // FALLBACK: Build a synthetic transaction from notification data and navigate
      // This handles withdrawal notifications that don't have matching mulai_id
      {
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Building synthetic transaction from notification data...');
        }
        final notifTitle = (notif['title'] ?? '').toString();
        final notifMessage = (notif['message'] ?? '').toString();
        final notifTime = (notif['created_at'] ?? '').toString();
        
        // Determine transaction type and status from title
        final titleLower = notifTitle.toLowerCase();
        String jenisTransaksi = 'penarikan';
        String status = 'pending';
        
        if (titleLower.contains('pinjaman') && titleLower.contains('disetujui')) {
          jenisTransaksi = 'pinjaman';
          status = 'approved';
        } else if (titleLower.contains('pinjaman') && titleLower.contains('ditolak')) {
          jenisTransaksi = 'pinjaman';
          status = 'rejected';
        } else if (titleLower.contains('pinjaman') && (titleLower.contains('diajukan') || titleLower.contains('verifikasi') || titleLower.contains('menunggu'))) {
          jenisTransaksi = 'pinjaman';
          status = 'pending';
        } else if (titleLower.contains('pencairan disetujui')) {
          status = 'approved';
        } else if (titleLower.contains('pencairan ditolak')) {
          status = 'rejected';
        } else if (titleLower.contains('setoran') && titleLower.contains('disetujui')) {
          jenisTransaksi = 'setoran';
          status = 'approved';
        } else if (titleLower.contains('setoran') && titleLower.contains('ditolak')) {
          jenisTransaksi = 'setoran';
          status = 'rejected';
        } else if (titleLower.contains('pengajuan pencairan')) {
          status = 'pending';
        } else if (titleLower.contains('pengajuan setoran')) {
          jenisTransaksi = 'setoran';
          status = 'pending';
        } else if (titleLower.contains('kirim uang') || (titleLower.contains('transfer') && titleLower.contains('berhasil') && !titleLower.contains('terima'))) {
          jenisTransaksi = 'transfer_keluar';
          status = 'approved';
        } else if (titleLower.contains('terima uang') || (titleLower.contains('terima') && titleLower.contains('transfer'))) {
          jenisTransaksi = 'transfer_masuk';
          status = 'approved';
        }
        
        // Extract amount from notification data or message
        dynamic amount = notifParsed?['amount'] ?? 0;
        if (amount == 0 || amount == null) {
          final amtMatch = RegExp(r'Rp\s*[\d.,]+').firstMatch(notifMessage);
          if (amtMatch != null) {
            amount = int.tryParse(amtMatch.group(0)!.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
          }
        }

        // Extract tenor from notification data for pinjaman
        dynamic tenor = notifParsed?['tenor'] ?? 0;
        if ((tenor == 0 || tenor == null) && jenisTransaksi == 'pinjaman') {
          final tenorMatch = RegExp(r'tenor\s*(\d+)\s*bulan', caseSensitive: false).firstMatch(notifMessage);
          if (tenorMatch != null) {
            tenor = int.tryParse(tenorMatch.group(1) ?? '') ?? 0;
          }
        }
        
        // For transfer_masuk, also try to get no_transaksi from notification data
        final syntheticNoTransaksi = notifParsed?['no_transaksi'] ?? '';
        
        final syntheticTx = <String, dynamic>{
          'id_transaksi': notifParsed?['id_transaksi'] ?? notifParsed?['id'] ?? notifParsed?['application_id'] ?? mulaiId ?? 0,
          'no_transaksi': syntheticNoTransaksi,
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
    // STRATEGY: Always try to extract from the notification MESSAGE first,
    // because the message is generated by the backend at the time of the actual
    // transaction and always contains the correct jenis. The data field may
    // contain stale or wrong values from API lookups.
    
    // Step 1: Extract from human-readable message (most reliable source)
    final msg = (notif['message'] ?? '').toString();
    if (msg.isNotEmpty) {
      // Pattern: "dari Tabungan Qurban" or "Tabungan Investasi Anda"
      // Use a word-boundary approach: capture word(s) between "Tabungan" and a known stop word
      // IMPORTANT: Use *? (lazy) to avoid capturing stop words like "ditolak" as part of jenis name
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

    // Step 2: Try structured data from parsed notification data
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

  /// Fetch complete transaction data from API using mulai_id
  /// For pinjaman notifications, also supports keterangan-based matching
  /// For transfer notifications, matches by jenis_transaksi + amount
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
      if (mulaiId == null && tabKeluarId == null && !isPinjaman && !isTransfer && !isReceiveTransfer) return null;

      // Get user ID from preferences
      final user = await EventPref.getUser();
      if (user == null || (user.id ?? '').isEmpty) {
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] ✗ Cannot fetch transaction: user not found or has no ID');
        }
        return null;
      }
      
      final userId = user.id ?? '';

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Fetching complete transaction: userId=$userId, mulaiId=$mulaiId');
      }

      final response = await http.post(
        Uri.parse('${Api.baseUrl}/get_riwayat_transaksi.php'),
        body: {'id_pengguna': userId},
      ).timeout(const Duration(seconds: 15));

      if (kDebugMode) {
        debugPrint('[NotifikasiPage] Response status: ${response.statusCode}');
      }

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] API Response: success=${body['success']}, data count=${body['data'] is List ? (body['data'] as List).length : 0}');
        }

        if ((body['success'] == true || body['status'] == 'SUCCESS') && body['data'] is List) {
          final transactions = (body['data'] as List).cast<Map<String, dynamic>>();
          
          if (kDebugMode) {
            debugPrint('[NotifikasiPage] Searching through ${transactions.length} transactions for match...');
          }

          // Find matching transaction by id_mulai_nabung, id_transaksi, 
          // or tabungan_keluar_id embedded in keterangan
          for (final tx in transactions) {
            final txMulaiId = tx['id_mulai_nabung'];
            final txTransaksiId = tx['id_transaksi'] ?? tx['id_transaksi'];
            final txKeterangan = (tx['keterangan'] ?? '').toString();
            if (kDebugMode) {
              debugPrint('[NotifikasiPage]   Checking: id_mulai_nabung=$txMulaiId, id_transaksi=$txTransaksiId vs searching mulaiId=$mulaiId, tabKeluarId=$tabKeluarId');
            }
            
            // Match by id_mulai_nabung or id_transaksi ONLY when mulaiId is available
            // (never compare tabungan_keluar_id against these — different ID spaces)
            if (mulaiId != null) {
              if ((txMulaiId != null && txMulaiId == mulaiId) ||
                  (txMulaiId != null && txMulaiId.toString() == mulaiId.toString()) ||
                  (txTransaksiId != null && txTransaksiId == mulaiId) ||
                  (txTransaksiId != null && txTransaksiId.toString() == mulaiId.toString())) {
                if (kDebugMode) {
                  debugPrint('[NotifikasiPage] ✓✓✓ MATCHED by ID! Transaction: ${tx['jenis_transaksi']} - Rp ${tx['jumlah']} - Status: ${tx['status']}');
                }
                return tx;
              }
            }
            
            // Match by tabungan_keluar_id embedded in keterangan (e.g., "[tabungan_keluar_id=123]")
            if (tabKeluarId != null && txKeterangan.contains('[tabungan_keluar_id=$tabKeluarId]')) {
              if (kDebugMode) {
                debugPrint('[NotifikasiPage] ✓✓✓ MATCHED by keterangan tabungan_keluar_id! Transaction: ${tx['jenis_transaksi']} - Rp ${tx['jumlah']}');
              }
              return tx;
            }
          }
          
          // FALLBACK for transfer: match by jenis_transaksi + amount (most recent)
          if (isTransfer && transferAmount != null) {
            if (kDebugMode) {
              debugPrint('[NotifikasiPage] Trying transfer amount-based matching (amount=$transferAmount)...');
            }
            Map<String, dynamic>? bestTransferMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '').toString().toLowerCase();
              if (txJenis != 'transfer_keluar') continue;
              final txAmount = tx['jumlah'];
              if (txAmount != null && txAmount.toString() == transferAmount.toString()) {
                // Pick the most recent matching transfer
                if (bestTransferMatch == null) {
                  bestTransferMatch = tx;
                } else {
                  // Compare by id_transaksi (higher = newer)
                  final currentId = int.tryParse((bestTransferMatch['id_transaksi'] ?? 0).toString()) ?? 0;
                  final newId = int.tryParse((tx['id_transaksi'] ?? 0).toString()) ?? 0;
                  if (newId > currentId) {
                    bestTransferMatch = tx;
                  }
                }
              }
            }
            if (bestTransferMatch != null) {
              if (kDebugMode) {
                debugPrint('[NotifikasiPage] ✓✓✓ MATCHED transfer by amount! Transaction: ${bestTransferMatch['jenis_transaksi']} - id=${bestTransferMatch['id_transaksi']}');
              }
              return bestTransferMatch;
            }
          }

          // FALLBACK for receive transfer (transfer_masuk): match by jenis_transaksi + amount (most recent)
          if (isReceiveTransfer && receiveTransferAmount != null) {
            if (kDebugMode) {
              debugPrint('[NotifikasiPage] Trying receive transfer (transfer_masuk) amount-based matching (amount=$receiveTransferAmount)...');
            }
            Map<String, dynamic>? bestReceiveMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '').toString().toLowerCase();
              if (txJenis != 'transfer_masuk') continue;
              final txAmount = tx['jumlah'];
              if (txAmount != null && txAmount.toString() == receiveTransferAmount.toString()) {
                // Pick the most recent matching transfer_masuk
                if (bestReceiveMatch == null) {
                  bestReceiveMatch = tx;
                } else {
                  // Compare by id_transaksi (higher = newer)
                  final currentId = int.tryParse((bestReceiveMatch['id_transaksi'] ?? 0).toString()) ?? 0;
                  final newId = int.tryParse((tx['id_transaksi'] ?? 0).toString()) ?? 0;
                  if (newId > currentId) {
                    bestReceiveMatch = tx;
                  }
                }
              }
            }
            if (bestReceiveMatch != null) {
              if (kDebugMode) {
                debugPrint('[NotifikasiPage] ✓✓✓ MATCHED receive transfer by amount! Transaction: ${bestReceiveMatch['jenis_transaksi']} - id=${bestReceiveMatch['id_transaksi']}');
              }
              return bestReceiveMatch;
            }
          }

          // FALLBACK for pinjaman: match by keterangan (notification message = transaction keterangan)
          if (isPinjaman && notifMessage != null && notifMessage.isNotEmpty) {
            if (kDebugMode) {
              debugPrint('[NotifikasiPage] Trying pinjaman keterangan-based matching...');
            }
            // Normalize the notification message for comparison
            final notifMsgNorm = notifMessage.trim().toLowerCase();
            Map<String, dynamic>? bestMatch;
            for (final tx in transactions) {
              final txJenis = (tx['jenis_transaksi'] ?? '').toString().toLowerCase();
              if (!txJenis.contains('pinjaman')) continue;
              final txKeterangan = (tx['keterangan'] ?? '').toString().trim().toLowerCase();
              // Exact keterangan match
              if (txKeterangan == notifMsgNorm) {
                bestMatch = tx;
                break;
              }
              // Partial match: notification message is contained in keterangan or vice versa
              if (txKeterangan.isNotEmpty && (txKeterangan.contains(notifMsgNorm) || notifMsgNorm.contains(txKeterangan))) {
                bestMatch = tx;
                break;
              }
              // Amount-based fallback: match pinjaman by amount if provided
              if (pinjamanAmount != null && bestMatch == null) {
                final txAmount = tx['jumlah'];
                if (txAmount != null && txAmount.toString() == pinjamanAmount.toString()) {
                  bestMatch = tx;
                  // Don't break - keep looking for exact keterangan match
                }
              }
            }
            if (bestMatch != null) {
              if (kDebugMode) {
                debugPrint('[NotifikasiPage] ✓✓✓ MATCHED pinjaman by keterangan! Transaction: ${bestMatch['jenis_transaksi']} - id=${bestMatch['id_transaksi']}');
              }
              return bestMatch;
            }
          }

          if (kDebugMode) {
            debugPrint('[NotifikasiPage] ✗ No matching transaction found in API response for mulaiId=$mulaiId');
          }
        } else {
          if (kDebugMode) {
            debugPrint('[NotifikasiPage] ✗ API response missing data or success is not true. Body: $body');
          }
        }
      } else {
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] ✗ API request failed with status: ${response.statusCode}');
          debugPrint('[NotifikasiPage] Response body: ${response.body}');
        }
      }

      return null;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✗✗✗ ERROR fetching transaction from API: $e');
      }
      return null;
    }
  }

  String _getToken() {
    // Token will be added via interceptor in production
    // For now, return empty string
    return '';
  }

  String _formatTime(String dateStr) {
    try {
      // Parse the created_at timestamp (assume it's in UTC or local time consistently)
      final date = DateTime.parse(dateStr);
      final now = DateTime.now();

      // If the parsed date doesn't have timezone info, treat as local time for consistency
      // If it does have Z or timezone offset, it's already been parsed correctly
      final Duration diff = now.difference(date);

      // Display "Baru saja" if less than 1 minute
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

  // Determine notification status from data or title so UI can use status-specific colors/icons.
  String? _getStatusFromNotif(Map<String, dynamic> n) {
    try {
      dynamic d = n['data'];
      // Parse JSON string data if needed
      if (d != null && d is String) {
        try {
          d = jsonDecode(d);
        } catch (_) {}
      }
      if (d != null && d is Map) {
        final s = (d['status'] ?? '').toString().toLowerCase();
        if (s.isNotEmpty) {
          // Check specific statuses BEFORE generic ones
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
    // IMPORTANT: Check disetujui/ditolak BEFORE pengajuan, because
    // titles like "Pengajuan Pinjaman Disetujui" contain both words.
    if (title.contains('disetujui') || title.contains('berhasil'))
      return 'berhasil';
    if (title.contains('ditolak')) return 'ditolak';
    // "Terima Uang" and "Kirim Uang" are always final (berhasil) - transfers are instant
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
        return const Color(0xFFFF9800); // orange
      case 'berhasil':
        return const Color(0xFF4CAF50); // green
      case 'ditolak':
        return const Color(0xFFF44336); // red
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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Column(
        children: [
          // Custom Header
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
                Text(
                  'Notifikasi',
                  style: GoogleFonts.roboto(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 18,
                  ),
                ),
                Align(
                  alignment: Alignment.centerRight,
                  child: GestureDetector(
                    onTap: _isLoading ? null : () => _loadNotifications(),
                    child: Icon(
                      Icons.refresh,
                      color: _isLoading ? Colors.white54 : Colors.white,
                      size: 24,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Content
          Expanded(
            child: _isLoading
                ? Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(
                        const Color(0xFFFF4D00),
                      ),
                    ),
                  )
                : (_notifications.isEmpty
                      ? _buildEmptyState()
                      : RefreshIndicator(
                          onRefresh: _loadNotifications,
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
                        )),
          ),
        ],
      ),
    );
  }
}
