import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/services/notification_service.dart';
import 'dart:convert';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/config/api.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/page/transaction_detail_page.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';

class RiwayatTransaksiPage extends StatefulWidget {
  const RiwayatTransaksiPage({Key? key}) : super(key: key);

  @override
  State<RiwayatTransaksiPage> createState() => _RiwayatTransaksiPageState();
}

class _RiwayatTransaksiPageState extends State<RiwayatTransaksiPage> with WidgetsBindingObserver {
  List<Map<String, dynamic>> items = [];
  bool loading = true;
  String sortBy = 'newest'; // newest, oldest, amount_asc, amount_desc
  String filterType = 'semua'; // semua, listrik, pinjaman, pulsa, kuota, topup

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();

    // Listen for notification changes that might indicate transaction status updates
    try {
      NotifikasiHelper.onNotificationsChanged.addListener(_onNotificationsChanged);
    } catch (_) {}
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    try {
      NotifikasiHelper.onNotificationsChanged.removeListener(_onNotificationsChanged);
    } catch (_) {}
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.resumed) {
      // App resumed from background, refresh data in case transactions were updated
      _load();
    }
  }

  void _onNotificationsChanged() {
    // When notifications change, it might indicate that some transactions were approved/rejected
    // Refresh the transaction list to show updated statuses
    _load();
  }

  Future<void> _load() async {
    loading = true;
    setState(() {});
    
    List<Map<String, dynamic>> list = [];
    final prefs = await SharedPreferences.getInstance();
    
    // Get user ID from controller
    final userCtrl = Get.find<CUser>();
    final userId = int.tryParse(userCtrl.user.id ?? '0') ?? 0;
    
    // Helper function to create a dedup key based on ID or full timestamp
    // Prefer database ID if available (from API), fallback to id_mulai_nabung or timestamp
    String _makeDedupKey(Map<String, dynamic> item) {
      try {
        // PRIORITY 1: If item has id_transaksi (from API), use that as PRIMARY key
        if (item.containsKey('id_transaksi') && item['id_transaksi'] != null && item['id_transaksi'].toString().isNotEmpty) {
          return 'txn:${item['id_transaksi'].toString()}';
        }
        
        // PRIORITY 2: If item has id_mulai_nabung (from local or API), use that
        // This links local pending transactions to their approved counterparts
        if (item.containsKey('id_mulai_nabung') && item['id_mulai_nabung'] != null && item['id_mulai_nabung'].toString().isNotEmpty) {
          return 'mulai:${item['id_mulai_nabung'].toString()}';
        }
        
        // PRIORITY 3: If item has plain id (from API or local), use that
        if (item.containsKey('id') && item['id'] != null && item['id'].toString().isNotEmpty) {
          return 'id:${item['id'].toString()}';
        }
        
        // FALLBACK: Use full timestamp + type + amount for local transactions without IDs
        String tsStr = '';
        if (item.containsKey('created_at')) {
          tsStr = item['created_at'].toString();
        } else if (item.containsKey('tanggal')) {
          tsStr = item['tanggal'].toString();
        } else if (item.containsKey('updated_at')) {
          tsStr = item['updated_at'].toString();
        }
        
        if (tsStr.isEmpty) {
          return '';
        }
        
        // Extract amount and type for additional uniqueness
        final amountVal = item['amount'] ?? item['jumlah'] ?? item['nominal'] ?? 0;
        final amountStr = amountVal.toString();
        String typeStr = item['type'] ?? item['jenis_transaksi'] ?? 'unknown';
        typeStr = typeStr.toString().toLowerCase();
        
        // Create composite key with full timestamp
        return 'ts:$tsStr|$amountStr|$typeStr';
      } catch (_) {
        return '';
      }
    }
    
    // STEP 1: Fetch fresh data from API get_riwayat_transaksi.php (approved/completed transactions)
    final Set<String> apiDedupKeys = {};  // Track dedup keys to avoid duplicates
    
    if (userId > 0) {
      try {
        final resp = await http
            .post(
              Uri.parse('${Api.baseUrl}/get_riwayat_transaksi.php'),
              body: {'id_pengguna': userId.toString()},
            )
            .timeout(const Duration(seconds: 10));
        
        if (resp.statusCode == 200) {
          final json = jsonDecode(resp.body);
          print('[RiwayatTransaksi] API Response: ${json.toString()}');
          
          if (json['success'] == true && json['data'] != null) {
            final transactions = (json['data'] as List).cast<Map<String, dynamic>>();
            print('[RiwayatTransaksi] Got ${transactions.length} transactions from API');
            
            for (var txn in transactions) {
              final item = Map<String, dynamic>.from(txn);
              print('[RiwayatTransaksi] Processing transaction: ${item['id']} - status: ${item['status']}');
              
              // Track dedup key - use both PRIORITY 1 AND PRIORITY 2 to catch all duplicates
              final dedupKey1 = _makeDedupKey(item);
              if (dedupKey1.isNotEmpty) {
                apiDedupKeys.add(dedupKey1);
              }
              
              // ALSO add id_mulai_nabung key if it exists (to match local pending entries)
              if (item.containsKey('id_mulai_nabung') && item['id_mulai_nabung'] != null && item['id_mulai_nabung'].toString().isNotEmpty) {
                apiDedupKeys.add('mulai:${item['id_mulai_nabung'].toString()}');
              }
              
              // MAP jenis_transaksi ke type
              if (item.containsKey('jenis_transaksi')) {
                final jenisTrans = (item['jenis_transaksi'] ?? '').toString().toLowerCase();
                
                if (jenisTrans == 'setoran') {
                  item['type'] = 'topup';
                  item['title'] = 'Setoran Tabungan';
                } else if (jenisTrans == 'penarikan' || jenisTrans == 'transfer_keluar' || jenisTrans == 'withdrawal_approved' || jenisTrans == 'withdrawal_rejected') {
                  item['type'] = 'transfer';
                  item['title'] = 'Pencairan Tabungan';
                } else if (jenisTrans == 'transfer_masuk') {
                  item['type'] = 'transfer_masuk';
                  item['title'] = 'Transfer Masuk';
                } else {
                  item['type'] = 'lainnya';
                  item['title'] = jenisTrans;
                }
              }
              
              // MAP jumlah ke amount jika belum ada
              if (!item.containsKey('amount') && item.containsKey('jumlah')) {
                item['amount'] = item['jumlah'];
              }
              
              // STATUS: normalize to 'success', 'pending', 'rejected'
              // IMPORTANT: Only transactions from Riwayat Transaksi should have final status
              // (approved/done/rejected) - never pending for this endpoint
              if (item['status'] != null) {
                final statusStr = item['status'].toString().toLowerCase().trim();
                if (statusStr == 'approved' || statusStr == 'done' || statusStr == 'berhasil' || statusStr == 'sukses') {
                  item['status'] = 'success';
                  item['processing'] = false;  // Explicitly mark as not processing
                } else if (statusStr == 'rejected' || statusStr == 'ditolak' || statusStr == 'tolak' || statusStr == 'failed') {
                  item['status'] = 'rejected';
                  item['processing'] = false;  // Explicitly mark as not processing
                  // Ensure keterangan is set for rejected transactions
                  if (item['keterangan'] == null || item['keterangan'].toString().trim().isEmpty) {
                    item['keterangan'] = 'Pengajuan Anda ditolak oleh admin.';
                  }
                } else if (statusStr == 'pending' || statusStr == 'menunggu' || statusStr == 'menunggu_admin' || statusStr == 'menunggu_penyerahan') {
                  // PENDING transactions will be filtered out in _buildList()
                  // Keep them here so backend sync works properly
                  item['status'] = 'pending';
                  item['processing'] = true;
                } else {
                  // Unknown status - treat as pending and log
                  print('[RiwayatTransaksi] Unknown status: $statusStr - treating as pending');
                  item['status'] = 'pending';
                  item['processing'] = true;
                }
              } else {
                // No status provided - should not happen, default to pending to be safe
                item['status'] = 'pending';
                item['processing'] = true;
              }
              
              // Add ALL transactions to list - filtering happens in _buildList()
              list.add(item);
              print('[RiwayatTransaksi] Added transaction to list: ${item['id']} - final status: ${item['status']}');
            }
          }
        } else {
          print('[RiwayatTransaksi] API error: HTTP ${resp.statusCode} - ${resp.body}');
        }
      } catch (e) {
        print('[RiwayatTransaksi] API error: $e');
      }
    }
    
    // NOTE: Pending transactions are NO LONGER stored in SharedPreferences
    // They only appear in Notifikasi page
    // Only FINAL transactions (approved/rejected) appear in Riwayat Transaksi
    
    // STEP 3: Tambah pengajuan pinjaman dari SharedPreferences jika ada
    final pengajuan = prefs.getString('pengajuan_list');
    if (pengajuan != null) {
      try {
        final parsed = jsonDecode(pengajuan) as List;
        for (var e in parsed) {
          final m = Map<String, dynamic>.from(e);
          m['type'] = 'pinjaman';
          list.add(m);
        }
      } catch (e) {
        print('[RiwayatTransaksi] Error parsing loans: $e');
      }
    }

    // STEP 4: Initial sort by created_at DESC (newest first)
    // This will be applied first, then overridden by user's sort preference
// STEP 4: Initial sort by newest-first using centralized helper
list = NotifikasiHelper.sortNotificationsNewestFirst(list);

    items = list;
    loading = false;
    setState(() {});

    // If navigated with an argument to open a specific transaction, show it
    try {
      final args = Get.arguments;
      if (args != null && args is Map && args['open_id'] != null) {
        final openId = args['open_id'];
        final idx = items.indexWhere(
          (e) => e['id'] == openId || e['id'].toString() == openId.toString(),
        );
        if (idx != -1) {
          final it = Map<String, dynamic>.from(items[idx]);
          WidgetsBinding.instance.addPostFrameCallback((_) {
            _showDetail(it);
          });
        }
      }
    } catch (_) {}

    final changed = await _checkPendingTopups();
    if (changed) {
      await _load();
      return;
    }
  }

  /// Create a unique, consistent key for a transaction that survives across rebuilds
  /// Uses multiple priority levels to ensure same transaction always gets same key
  String _createUniqueKeyForTransaction(Map<String, dynamic> item) {
    try {
      // PRIORITY 1: id_transaksi from API (most reliable for approved/rejected transactions)
      if (item.containsKey('id_transaksi') && item['id_transaksi'] != null) {
        final id = item['id_transaksi'].toString().trim();
        if (id.isNotEmpty) {
          return 'txn_${item['id_transaksi']}';
        }
      }

      // PRIORITY 2: id_mulai_nabung (reliable for pending -> final transitions)
      if (item.containsKey('id_mulai_nabung') && item['id_mulai_nabung'] != null) {
        final id = item['id_mulai_nabung'].toString().trim();
        if (id.isNotEmpty) {
          return 'mulai_${item['id_mulai_nabung']}';
        }
      }

      // PRIORITY 3: Plain id field
      if (item.containsKey('id') && item['id'] != null) {
        final id = item['id'].toString().trim();
        if (id.isNotEmpty) {
          return 'id_${item['id']}';
        }
      }

      // FALLBACK: Create stable key from created_at + amount + type
      // This ensures same transaction always gets same key even without database IDs
      final createdAt = item['created_at'] ?? item['tanggal'] ?? item['updated_at'] ?? '';
      final amount = item['amount'] ?? item['jumlah'] ?? item['nominal'] ?? 0;
      final type = item['type'] ?? item['jenis_transaksi'] ?? 'unknown';
      
      if (createdAt.toString().isNotEmpty) {
        return 'composite_${createdAt}_${amount}_$type';
      }

      // Last resort: use hash of entire item (not ideal but prevents duplicates)
      return 'item_${item.hashCode}';
    } catch (_) {
      // Absolute fallback
      return 'key_${DateTime.now().millisecondsSinceEpoch}_${item.hashCode}';
    }
  }

  /// Check for pending topups in local shared prefs and update their status from server
  /// Check for any pending top-ups from mulai_nabung table via API
  /// If status changes to final (approved/rejected), add to Riwayat Transaksi
  /// Since we no longer store pending transactions locally, we rely entirely on API
  Future<bool> _checkPendingTopups() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userCtrl = Get.find<CUser>();
      final userId = int.tryParse(userCtrl.user.id ?? '0') ?? 0;
      
      if (userId <= 0) return false;
      
      // Query API get_riwayat_transaksi.php for latest transactions
      // This will include any newly approved/rejected transactions
      final resp = await http
          .post(
            Uri.parse('${Api.baseUrl}/get_riwayat_transaksi.php'),
            body: {'id_pengguna': userId.toString()},
          )
          .timeout(const Duration(seconds: 10));
      
      if (resp.statusCode != 200) return false;
      
      final json = jsonDecode(resp.body);
      if (json['success'] != true || json['data'] == null) return false;
      
      if (kDebugMode) {
        debugPrint('[_checkPendingTopups] API response received: ${json['meta']}');
      }
      
      final transactions = (json['data'] as List).cast<Map<String, dynamic>>();
      
      if (kDebugMode && transactions.isNotEmpty) {
        final firstTx = transactions.first;
        debugPrint('[_checkPendingTopups] First TX from API: id=${firstTx['id_transaksi']} jenis_tabungan="${firstTx['jenis_tabungan']}" jumlah=${firstTx['jumlah']}');
      }
      final txns = prefs.getString('transactions');
      final list = txns != null
          ? (jsonDecode(txns) as List).cast<Map<String, dynamic>>()
          : <Map<String, dynamic>>[];
      
      var changed = false;
      
      // For each API transaction, check if it's already in local Riwayat
      for (final apiTxn in transactions) {
        final apiId = apiTxn['id_transaksi'] ?? apiTxn['id'];
        final apiIdMulai = apiTxn['id_mulai_nabung'];
        
        // Check if transaction already exists locally
        final existingIdx = list.indexWhere((m) {
          if (m['id_transaksi'] != null && m['id_transaksi'] == apiId) return true;
          if (m['id'] != null && m['id'] == apiId) return true;
          if (apiIdMulai != null && m['id_mulai_nabung'] != null && m['id_mulai_nabung'] == apiIdMulai) return true;
          return false;
        });
        
        // If NOT in local list, add it (means it's a newly final transaction from API)
        if (existingIdx == -1) {
          if (kDebugMode) {
            debugPrint('[_checkPendingTopups] NEW TX from API: id=${apiTxn['id_transaksi']} status=${apiTxn['status']}');
            debugPrint('[_checkPendingTopups] API TX keys: ${apiTxn.keys.join(", ")}');
            debugPrint('[_checkPendingTopups] jenis_tabungan from API: "${apiTxn['jenis_tabungan']}"');
          }
          
          final newEntry = Map<String, dynamic>.from(apiTxn);
          
          // Normalize the entry format
          if (newEntry.containsKey('jenis_transaksi')) {
            final jenisTrans = (newEntry['jenis_transaksi'] ?? '').toString().toLowerCase();
            if (jenisTrans == 'setoran') {
              newEntry['type'] = 'topup';
              newEntry['title'] = 'Setoran Tabungan';
            } else if (jenisTrans == 'penarikan' || jenisTrans == 'transfer_keluar' || jenisTrans == 'withdrawal_approved' || jenisTrans == 'withdrawal_rejected') {
              newEntry['type'] = 'transfer';
              newEntry['title'] = 'Pencairan Tabungan';
            }
          }
          
          if (!newEntry.containsKey('amount') && newEntry.containsKey('jumlah')) {
            newEntry['amount'] = newEntry['jumlah'];
          }
          
          // Normalize status
          final statusStr = (newEntry['status'] ?? '').toString().toLowerCase();
          if (statusStr == 'approved' || statusStr == 'done' || statusStr == 'berhasil' || statusStr == 'sukses') {
            newEntry['status'] = 'success';
          } else if (statusStr == 'rejected' || statusStr == 'ditolak' || statusStr == 'tolak' || statusStr == 'failed') {
            newEntry['status'] = 'rejected';
          }
          
          list.add(newEntry);
          changed = true;
          
          // Add notification for this newly final transaction
          try {
            final notifType = newEntry['status'] == 'rejected' ? 'rejected' : 'approved';
            final notifTitle = newEntry['status'] == 'rejected' 
                ? 'Setoran Tabungan Ditolak' 
                : 'Setoran Tabungan Disetujui';
            
            // Get jenis_tabungan and jumlah from entry with robust fallback
            var jenisTabs = newEntry['jenis_tabungan'];
            
            // Debug: log what we received from API
            if (kDebugMode) {
              debugPrint('[Riwayat] TX ID=${newEntry['id_transaksi']}: jenis_tabungan="${jenisTabs}" (type: ${jenisTabs?.runtimeType})');
              debugPrint('[Riwayat] Full entry keys: ${newEntry.keys.join(", ")}');
            }
            
            // Multiple fallback strategies
            if (jenisTabs == null || jenisTabs.toString().isEmpty) {
              jenisTabs = 'Tabungan Reguler';
            } else {
              jenisTabs = jenisTabs.toString().trim();
              if (jenisTabs.isEmpty) {
                jenisTabs = 'Tabungan Reguler';
              }
            }
            
            final jumlahVal = newEntry['jumlah'] ?? newEntry['amount'] ?? 0;
            final jumlahStr = jumlahVal is String 
                ? jumlahVal 
                : 'Rp ${NumberFormat('#,##0', 'id_ID').format(jumlahVal)}';
            
            final notifMsg = newEntry['status'] == 'rejected'
                ? 'Pengajuan Setoran $jenisTabs Anda sebesar $jumlahStr ditolak, silahkan hubungi admin untuk informasi lebih lanjut.'
                : 'Pengajuan Setoran $jenisTabs Anda sebesar $jumlahStr disetujui, silahkan cek saldo di halaman Tabungan';
            
            if (kDebugMode) {
              debugPrint('[Riwayat] FINAL MESSAGE: $notifMsg');
            }
            
            await NotifikasiHelper.addLocalNotification(
              type: notifType,
              title: notifTitle,
              message: notifMsg,
              data: {
                'mulai_id': apiIdMulai?.toString(),
                'status': newEntry['status'] == 'rejected' ? 'ditolak' : 'berhasil',
                'amount': jumlahVal,
                'jenis_tabungan': jenisTabs,
              },
            );
            
            if (kDebugMode) {
              debugPrint('[_checkPendingTopups] ✓ Notifikasi created. Data: {jenis_tabungan: $jenisTabs, amount: $jumlahVal, status: ${newEntry['status']}}');
            }
            
            await NotifikasiHelper.initializeNotifications();
          } catch (_) {}
          
          // If approved, refresh profile
          if (newEntry['status'] == 'success') {
            try {
              final localUser = await EventPref.getUser();
              if (localUser != null && localUser.id != null) {
                final updated = await EventDB.getProfilLengkap(localUser.id!);
                if (updated != null) {
                  await EventPref.saveUser(updated);
                  final c = Get.find<CUser>();
                  c.setUser(updated);
                }
              }
            } catch (_) {}
          }
        }
      }
      
      if (changed) {
        await prefs.setString('transactions', jsonEncode(list));
      }
      
      return changed;
    } catch (_) {
      return false;
    }
  }

  String _formatCurrency(dynamic value) {
    try {
      final numVal = value is num ? value : num.tryParse(value.toString()) ?? 0;
      final f = NumberFormat.currency(
        locale: 'id_ID',
        symbol: 'Rp ',
        decimalDigits: 0,
      );
      return f.format(numVal);
    } catch (_) {
      return value?.toString() ?? '-';
    }
  }

  String _formatTime(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      return DateFormat('dd MMM yyyy HH:mm').format(d);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  void _showSortDialog() {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Urutkan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadioListTile<String>(
              title: const Text('Terbaru'),
              value: 'newest',
              groupValue: sortBy,
              onChanged: (v) {
                setState(() => sortBy = v ?? 'newest');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Terlama'),
              value: 'oldest',
              groupValue: sortBy,
              onChanged: (v) {
                setState(() => sortBy = v ?? 'oldest');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Nominal Terendah'),
              value: 'amount_asc',
              groupValue: sortBy,
              onChanged: (v) {
                setState(() => sortBy = v ?? 'amount_asc');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Nominal Tertinggi'),
              value: 'amount_desc',
              groupValue: sortBy,
              onChanged: (v) {
                setState(() => sortBy = v ?? 'amount_desc');
                Navigator.of(c).pop();
              },
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterDialog() {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Filter'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadioListTile<String>(
              title: const Text('Semua'),
              value: 'semua',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'semua');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Bayar Listrik'),
              value: 'listrik',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'listrik');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Pinjaman'),
              value: 'pinjaman',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'pinjaman');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Beli Pulsa'),
              value: 'pulsa',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'pulsa');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Beli Kuota'),
              value: 'kuota',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'kuota');
                Navigator.of(c).pop();
              },
            ),
            RadioListTile<String>(
              title: const Text('Top-up'),
              value: 'topup',
              groupValue: filterType,
              onChanged: (v) {
                setState(() => filterType = v ?? 'topup');
                Navigator.of(c).pop();
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      appBar: const OrangeHeader(title: 'Riwayat Transaksi'),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // list with pull-to-refresh
                Expanded(
                  child: RefreshIndicator(
                    onRefresh: _load,
                    child: _buildList(),
                  ),
                ),
              ],
            ),
      bottomNavigationBar: Container(
        height: 56,
        decoration: BoxDecoration(
          color: theme.scaffoldBackgroundColor,
          border: Border(
            top: BorderSide(
              color: isDark ? Colors.grey.shade700 : Colors.grey.shade300,
            ),
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton.icon(
              onPressed: _showSortDialog,
              icon: const Icon(Icons.swap_vert, color: Color(0xFFFF6B2C)),
              label: Text(
                'Urutkan',
                style: GoogleFonts.roboto(color: const Color(0xFFFF6B2C)),
              ),
            ),
            TextButton.icon(
              onPressed: _showFilterDialog,
              icon: const Icon(Icons.filter_list, color: Color(0xFFFF6B2C)),
              label: Text(
                'Filter',
                style: GoogleFonts.roboto(color: const Color(0xFFFF6B2C)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildList() {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    var list = items.toList(); // Show all items (all are final transactions only)

    print('[RiwayatTransaksi] _buildList: Total items in state: ${list.length}');

    // IMPORTANT FILTER: Only show FINAL transactions (success or rejected)
    // NEVER show PENDING transactions in Riwayat Transaksi
    // Pending transactions should only appear in Notifikasi page
    list = list.where((it) {
      final status = (it['status'] ?? '').toString().toLowerCase();
      // Only include transactions with final status
      final isFinal = status == 'success' || 
                     status == 'approved' || 
                     status == 'done' || 
                     status == 'berhasil' || 
                     status == 'sukses' ||
                     status == 'rejected' || 
                     status == 'ditolak' || 
                     status == 'tolak' || 
                     status == 'failed';
      if (!isFinal) print('[RiwayatTransaksi] Filtering out: ${it['id']} - status=$status');
      if (isFinal) print('[RiwayatTransaksi] Including: ${it['id']} - status=$status');
      return isFinal;
    }).toList();

    print('[RiwayatTransaksi] After filter: ${list.length} items');

    // Filter by transaction type
    if (filterType != 'semua') {
      list = list.where((it) => (it['type'] ?? '') == filterType).toList();
    }

    // Apply sort order
    list.sort((a, b) {
      switch (sortBy) {
        case 'newest':
          // Newest first: newer dates come first
          final dateStrA = a['created_at'] ?? a['tanggal'] ?? a['updated_at'] ?? a['id']?.toString() ?? '';
          final dateStrB = b['created_at'] ?? b['tanggal'] ?? b['updated_at'] ?? b['id']?.toString() ?? '';
          try {
            final dateA = DateTime.parse(dateStrA.toString());
            final dateB = DateTime.parse(dateStrB.toString());
            return dateB.compareTo(dateA);  // DESC: newest first
          } catch (_) {
            return dateStrB.toString().compareTo(dateStrA.toString());
          }
        case 'oldest':
          // Oldest first: older dates come first
          final dateStrA = a['created_at'] ?? a['tanggal'] ?? a['updated_at'] ?? a['id']?.toString() ?? '';
          final dateStrB = b['created_at'] ?? b['tanggal'] ?? b['updated_at'] ?? b['id']?.toString() ?? '';
          try {
            final dateA = DateTime.parse(dateStrA.toString());
            final dateB = DateTime.parse(dateStrB.toString());
            return dateA.compareTo(dateB);  // ASC: oldest first
          } catch (_) {
            return dateStrA.toString().compareTo(dateStrB.toString());
          }
        case 'amount_asc':
          final amountA =
              (a['price'] ?? a['nominal'] ?? a['amount'] ?? 0) as dynamic;
          final amountB =
              (b['price'] ?? b['nominal'] ?? b['amount'] ?? 0) as dynamic;
          final numA = amountA is num
              ? amountA
              : num.tryParse(amountA.toString()) ?? 0;
          final numB = amountB is num
              ? amountB
              : num.tryParse(amountB.toString()) ?? 0;
          return numA.compareTo(numB);
        case 'amount_desc':
          final amountA =
              (a['price'] ?? a['nominal'] ?? a['amount'] ?? 0) as dynamic;
          final amountB =
              (b['price'] ?? b['nominal'] ?? b['amount'] ?? 0) as dynamic;
          final numA = amountA is num
              ? amountA
              : num.tryParse(amountA.toString()) ?? 0;
          final numB = amountB is num
              ? amountB
              : num.tryParse(amountB.toString()) ?? 0;
          return numB.compareTo(numA);
        default: // newest (default)
          final dateStrA = a['created_at'] ?? a['tanggal'] ?? a['updated_at'] ?? a['id']?.toString() ?? '';
          final dateStrB = b['created_at'] ?? b['tanggal'] ?? b['updated_at'] ?? b['id']?.toString() ?? '';
          try {
            final dateA = DateTime.parse(dateStrA.toString());
            final dateB = DateTime.parse(dateStrB.toString());
            return dateB.compareTo(dateA);  // DESC: newest first
          } catch (_) {
            return dateStrB.toString().compareTo(dateStrA.toString());
          }
      }
    });

    if (list.isEmpty) {
      return Center(
        child: Text('Belum ada transaksi', style: GoogleFonts.roboto()),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemBuilder: (context, index) {
        final it = list[index];
        final originalIndex = items.indexWhere(
          (e) => e['id'] != null ? e['id'] == it['id'] : identical(e, it),
        );

        // Create a reliable composite key that uniquely identifies this transaction
        // Priority: id_transaksi > id > id_mulai_nabung > (created_at + amount + type)
        final String uniqueKey = _createUniqueKeyForTransaction(it);

        return Dismissible(
          key: ValueKey(uniqueKey),
          direction: DismissDirection.endToStart,
          background: Container(
            color: Colors.redAccent,
            alignment: Alignment.centerRight,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: const Icon(Icons.delete, color: Colors.white),
          ),
          confirmDismiss: (direction) async {
            final confirm = await showDialog<bool>(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('Konfirmasi'),
                content: const Text('Hapus transaksi ini?'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.of(c).pop(false),
                    child: const Text('Batal'),
                  ),
                  TextButton(
                    onPressed: () => Navigator.of(c).pop(true),
                    child: const Text('Hapus'),
                  ),
                ],
              ),
            );

            if (confirm == true) {
              final removed = items.removeAt(
                originalIndex == -1 ? index : originalIndex,
              );
              setState(() {});
              await _saveAll(items);
              NotificationService.showSuccess('Transaksi dihapus');
              return true;
            }
            return false;
          },
          child: Card(
            elevation: 1.5,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
            margin: EdgeInsets.zero,
            child: InkWell(
              onTap: () => _showDetail(it),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 12,
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // left icon box
                    Container(
                      width: 56,
                      height: 56,
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: isDark
                            ? const Color(0xFF2A1810)
                            : const Color(0xFFFFF3EB),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Icon(
                        it['type'] == 'listrik'
                            ? Icons.flash_on
                            : it['type'] == 'pulsa'
                            ? Icons.phone_android
                            : it['type'] == 'kuota'
                            ? Icons.cloud
                            : it['type'] == 'topup'
                            ? Icons.account_balance_wallet
                            : it['type'] == 'transfer'
                            ? Icons.swap_horiz
                            : Icons.request_quote,
                        color: const Color(0xFFFF6B2C),
                        size: 28,
                      ),
                    ),
                    const SizedBox(width: 12),

                    // center text
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  // Prefer server-supplied `title` when available so the UI shows the exact transaction label
                                  (it['title'] != null &&
                                          it['title']
                                              .toString()
                                              .trim()
                                              .isNotEmpty)
                                      ? it['title']
                                      : (it['type'] == 'listrik'
                                            ? 'Bayar Listrik'
                                            : it['type'] == 'pulsa'
                                            ? 'Beli Pulsa'
                                            : it['type'] == 'kuota'
                                            ? 'Beli Kuota'
                                            : it['type'] == 'topup'
                                            ? 'Setoran Tabungan'
                                            : it['type'] == 'transfer'
                                            ? 'Transfer'
                                            : 'Pengajuan Pinjaman'),
                                  style: GoogleFonts.roboto(
                                    fontWeight: FontWeight.w700,
                                    fontSize: 14,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 6),
                              // status indicator
                              Builder(
                                builder: (c) {
                                  final keterangan = (it['keterangan'] ?? '')
                                      .toString()
                                      .toLowerCase();
                                  final status = (it['status'] ?? '')
                                      .toString()
                                      .toLowerCase();
                                  final processingFlag = it['processing'] == true;
                                  
                                  // Determine status with clear priority:
                                  // 1. Explicit status field takes precedence
                                  // 2. rejected status shows X icon regardless of processing flag
                                  // 3. success status shows checkmark regardless of processing flag
                                  
                                  final isRejected =
                                      status == 'rejected' ||
                                      status == 'ditolak' ||
                                      status == 'tolak' ||
                                      keterangan.contains('ditolak') ||
                                      keterangan.contains('gagal') ||
                                      status == 'failed' ||
                                      status == 'error';
                                  
                                  final isSuccess =
                                      status == 'success' ||
                                      status == 'done' ||
                                      status == 'approved' ||
                                      status == 'berhasil' ||
                                      status == 'sukses' ||
                                      keterangan.contains('berhasil') ||
                                      keterangan.contains('sukses');
                                  
                                  final isProcessing =
                                      !isRejected &&
                                      !isSuccess &&
                                      (status == 'pending' || processingFlag);

                                  if (isRejected) {
                                    return const Icon(
                                      Icons.cancel,
                                      color: Colors.red,
                                      size: 18,
                                    );
                                  } else if (isSuccess) {
                                    return const Icon(
                                      Icons.check_circle,
                                      color: Colors.green,
                                      size: 18,
                                    );
                                  } else if (isProcessing) {
                                    return SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.2,
                                        color: Colors.orange,
                                      ),
                                    );
                                  }
                                  return const SizedBox.shrink();
                                },
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _formatTime(it['created_at'] ?? it['id']),
                            style: GoogleFonts.roboto(
                              color: isDark
                                  ? Colors.grey.shade400
                                  : Colors.grey,
                              fontSize: 12,
                            ),
                          ),
                          if ((it['keterangan'] ?? '')
                              .toString()
                              .toLowerCase()
                              .contains('gagal'))
                            Padding(
                              padding: const EdgeInsets.only(top: 6.0),
                              child: Text(
                                (it['keterangan'] ?? '').toString(),
                                style: GoogleFonts.roboto(
                                  color: isDark
                                      ? Colors.grey.shade400
                                      : Colors.grey,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),

                    // right amount
                    SizedBox(
                      width: 110,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            _formatCurrency(
                              it['price'] ?? it['nominal'] ?? it['amount'],
                            ),
                            style: GoogleFonts.roboto(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 6),
                          IconButton(
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                            onPressed: () => _showDetail(it),
                            icon: Icon(
                              Icons.info_outline,
                              size: 18,
                              color: isDark
                                  ? Colors.grey.shade500
                                  : Colors.grey,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemCount: list.length,
    );
  }

  Future<void> _saveAll(List<Map<String, dynamic>> list) async {
    final prefs = await SharedPreferences.getInstance();

    // Separate pinjaman
    final pengajuan = list.where((e) => e['type'] == 'pinjaman').map((e) {
      final m = Map<String, dynamic>.from(e);
      m.remove('type');
      return m;
    }).toList();

    // Keep all transactions (listrik, pulsa, kuota) with type in transactions key
    final allTxns = list.where((e) => e['type'] != 'pinjaman').toList();

    await prefs.setString('transactions', jsonEncode(allTxns));
    await prefs.setString('pengajuan_list', jsonEncode(pengajuan));
  }

  Future<void> _refreshMulaiNabungStatusIfNeeded(
    Map<String, dynamic> it,
  ) async {
    try {
      if ((it['type'] ?? '') != 'topup') return;
      final idMulai = it['id_mulai_nabung']?.toString();
      if (idMulai == null || idMulai.isEmpty) return;
      // Fetch latest status from server
      final resp = await http
          .post(
            Uri.parse('${Api.baseUrl}/get_mulai_nabung.php'),
            body: {'id_mulai_nabung': idMulai},
          )
          .timeout(const Duration(seconds: 10));
      if (resp.statusCode != 200) return;
      final json = jsonDecode(resp.body);
      if (json['success'] != true) return;
      final data = json['data'] ?? {};
      final ns = (data['status'] ?? '').toString().toLowerCase();
      if (ns.isEmpty) return;

      // find the local transaction and update it
      final idx = items.indexWhere(
        (m) => m['id_mulai_nabung']?.toString() == idMulai,
      );
      if (idx == -1) return;
      final m = Map<String, dynamic>.from(items[idx]);
      m['status'] = ns;
      final finalStatuses = [
        'sukses',
        'berhasil',
        'done',
        'success',
        'failed',
        'ditolak',
        'tolak',
        'rejected',
      ];
      m['processing'] = !finalStatuses.contains(ns);
      if (data.containsKey('keterangan') &&
          (data['keterangan'] ?? '').toString().trim().isNotEmpty) {
        m['keterangan'] = data['keterangan'];
      } else if (!m['processing'] &&
          (ns == 'ditolak' || ns == 'tolak' || ns == 'rejected')) {
        m['keterangan'] = 'Pengajuan setoran tabungan Anda ditolak oleh admin. Silakan hubungi admin untuk informasi lebih lanjut.';
      }
      m['updated_at'] = data['updated_at'] ?? DateTime.now().toIso8601String();
      items[idx] = m;
      await _saveAll(items);
      setState(() {});
    } catch (_) {
      // ignore errors (best effort)
    }
  }

  void _showDetail(Map<String, dynamic> it) async {
    // ensure we refresh from server for topup items so the detail reflects the real latest state
    await _refreshMulaiNabungStatusIfNeeded(it);

    // Navigate to the new transaction detail page
    final result = await Get.to(
      () => TransactionDetailPage(transaction: it),
    );

    // If transaction was deleted, refresh the list
    if (result == true) {
      await _load();
    }
  }
}
