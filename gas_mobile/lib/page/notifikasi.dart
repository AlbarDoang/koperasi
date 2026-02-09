import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter/foundation.dart';
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
      // Try to fetch latest notifications from server first (real-time-ish)
      try {
        final user = await EventPref.getUser();
        if (user != null && (user.id ?? '').isNotEmpty) {
          final server = await EventDB.getNotifications(user.id ?? '');
          final prefs = await SharedPreferences.getInstance();
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
                },
              )
              .toList();

          // Merge server results with existing local notifications so immediate local
          // notifications (added after submit) are not overwritten by an empty/slow server response.
          if (filtered.isNotEmpty) {
            try {
              final existingRaw = prefs.getString('notifications') ?? '[]';
              final List<dynamic> existingList = jsonDecode(existingRaw);

              // Load blacklist of deleted notifications
              final blacklistRaw =
                  prefs.getString('notifications_blacklist') ?? '[]';
              final List<dynamic> blacklistDyn = jsonDecode(blacklistRaw);
              final Set<String> blacklist = Set<String>.from(
                blacklistDyn.cast<String>(),
              );

              final merged = NotifikasiHelper.mergeServerWithExisting(
                List<Map<String, dynamic>>.from(filtered),
                existingList,
                blacklist: blacklist,
              );

              // Ensure newest-first ordering before persisting
              final sortedMerged =
                  NotifikasiHelper.sortNotificationsNewestFirst(merged);
              await prefs.setString('notifications', jsonEncode(sortedMerged));
            } catch (_) {
              // Fallback: if merge fails, still write server-only list
              await prefs.setString('notifications', jsonEncode(filtered));
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
      final prefs = await SharedPreferences.getInstance();
      final data = prefs.getString('notifications') ?? '[]';
      if (kDebugMode)
        debugPrint('[NotifikasiPage] prefs.notifications = ' + data);
      final List<dynamic> decoded = jsonDecode(data);

      // If there is an un-included local notification (last_local_notif), insert it at top
      try {
        final lastRaw = prefs.getString('last_local_notif') ?? '';
        if (lastRaw.isNotEmpty) {
          final Map<String, dynamic> lastLocal = jsonDecode(lastRaw);
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
      } catch (_) {}

      // Keep only transaction-related notifications (no dummy/system/promo)
      final filteredLocal = decoded.cast<Map<String, dynamic>>().where((n) {
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
      Get.snackbar(
        'Error',
        'Gagal memuat notifikasi',
        snackPosition: SnackPosition.TOP,
      );
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
      Get.snackbar(
        'Error',
        'Gagal mengubah status',
        snackPosition: SnackPosition.TOP,
      );
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
      dynamic mulaiId;
      if (notifParsed != null && notifParsed.isNotEmpty) {
        mulaiId = notifParsed['mulai_id'] ?? 
                  notifParsed['id_mulai_nabung'] ?? 
                  notifParsed['id_transaksi'];
        if (kDebugMode) {
          debugPrint('[NotifikasiPage] Extracted mulai_id/id_mulai_nabung/id_transaksi: $mulaiId');
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

      // DIRECT NAVIGATION: Extract transaction ID and navigate
      if (mulaiId != null) {
        if (kDebugMode) debugPrint('[NotifikasiPage] ✓ Have transaction ID: $mulaiId - fetching complete transaction data...');
        
        // FETCH complete transaction data from API using the ID from notification
        final txData = await _fetchCompleteTransactionData(mulaiId);
        
        if (txData != null) {
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
            debugPrint('[NotifikasiPage] ✗ Failed to fetch complete transaction data for ID: $mulaiId');
          }
        }
      }
      
      // Fallback: if we can't fetch the transaction, don't navigate
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ✗ No transaction ID extracted from notification - not navigating');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[NotifikasiPage] ERROR in _onNotificationTap: $e');
      }
    }
  }

  /// Fetch complete transaction data from API using mulai_id
  Future<Map<String, dynamic>?> _fetchCompleteTransactionData(dynamic mulaiId) async {
    try {
      if (mulaiId == null) return null;

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

          // Find matching transaction by id_mulai_nabung or id_transaksi
          for (final tx in transactions) {
            final txMulaiId = tx['id_mulai_nabung'];
            final txTransaksiId = tx['id_transaksi'] ?? tx['id_transaksi'];
            if (kDebugMode) {
              debugPrint('[NotifikasiPage]   Checking: id_mulai_nabung=$txMulaiId, id_transaksi=$txTransaksiId vs searching=$mulaiId');
            }
            
            // Match either by id_mulai_nabung or id_transaksi
            if ((txMulaiId != null && txMulaiId == mulaiId) ||
                (txMulaiId != null && txMulaiId.toString() == mulaiId.toString()) ||
                (txTransaksiId != null && txTransaksiId == mulaiId) ||
                (txTransaksiId != null && txTransaksiId.toString() == mulaiId.toString())) {
              if (kDebugMode) {
                debugPrint('[NotifikasiPage] ✓✓✓ MATCHED! Transaction: ${tx['jenis_transaksi']} - Rp ${tx['jumlah']} - Status: ${tx['status']}');
              }
              return tx;
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
      final d = n['data'];
      if (d != null && d is Map) {
        final s = (d['status'] ?? '').toString().toLowerCase();
        if (s.isNotEmpty) {
          if (s.contains('menunggu')) return 'menunggu';
          if (s.contains('berhasil') ||
              s.contains('sukses') ||
              s.contains('success') ||
              s.contains('done'))
            return 'berhasil';
          if (s.contains('ditolak') ||
              s.contains('tolak') ||
              s.contains('rejected'))
            return 'ditolak';
        }
      }
    } catch (_) {}

    final title = (n['title'] ?? '').toString().toLowerCase();
    if (title.contains('pengajuan') ||
        title.contains('menunggu') ||
        title.contains('verifikasi'))
      return 'menunggu';
    if (title.contains('disetujui') || title.contains('berhasil'))
      return 'berhasil';
    if (title.contains('ditolak')) return 'ditolak';
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
