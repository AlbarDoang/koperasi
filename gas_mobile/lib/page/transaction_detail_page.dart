import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:tabungan/config/api.dart';
import 'package:tabungan/page/orange_header.dart';

class TransactionDetailPage extends StatefulWidget {
  final Map<String, dynamic> transaction;

  const TransactionDetailPage({Key? key, required this.transaction})
    : super(key: key);

  @override
  State<TransactionDetailPage> createState() => _TransactionDetailPageState();
}

class _TransactionDetailPageState extends State<TransactionDetailPage> {
  late Map<String, dynamic> data;
  bool _isRefreshing = false;

  @override
  void initState() {
    super.initState();
    data = Map<String, dynamic>.from(widget.transaction);
    _fetchFreshDataFromApi();
  }

  /// Fetch fresh transaction data from get_detail_transaksi.php API
  /// This ensures the detail page always shows accurate data from the database
  Future<void> _fetchFreshDataFromApi() async {
    try {
      // Determine the best ID to use for API lookup
      final idTransaksi = data['id_transaksi'] ?? data['id'];

      // Skip re-fetch for synthetic transactions that have no real id_transaksi
      // (value is null, empty, or 0). If a synthetic transaction has a valid
      // id_transaksi (e.g., from notification data), allow the re-fetch so the
      // detail page shows the most accurate data from the database.
      if (data['_isSynthetic'] == true) {
        final idStr = (idTransaksi ?? '').toString().trim();
        if (idStr.isEmpty || idStr == '0') {
          if (kDebugMode) {
            debugPrint('[TransactionDetail] Skipping API re-fetch for synthetic transaction without valid id');
          }
          return;
        }
      }

      if (idTransaksi == null) return;

      final idStr = idTransaksi.toString().trim();
      if (idStr.isEmpty || idStr == '0') return;

      if (kDebugMode) {
        debugPrint(
          '[TransactionDetail] Fetching fresh data for id_transaksi=$idStr',
        );
      }

      setState(() => _isRefreshing = true);

      final response = await http
          .post(
            Uri.parse('${Api.baseUrl}/get_detail_transaksi.php'),
            body: {'id_transaksi': idStr},
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        if (body['success'] == true && body['data'] is Map) {
          final freshData = Map<String, dynamic>.from(body['data'] as Map);
          if (kDebugMode) {
            debugPrint('[TransactionDetail] Got fresh data: $freshData');
          }

          // Merge fresh data into current data (fresh data takes priority)
          // But preserve some keys from original data if not in fresh response
          final keysToPreserve = ['type', 'title', 'processing'];
          final merged = Map<String, dynamic>.from(data);
          for (final entry in freshData.entries) {
            if (entry.value != null &&
                entry.value.toString().trim().isNotEmpty) {
              // Don't overwrite keterangan from notification with raw API keterangan
              if (entry.key == 'keterangan' &&
                  data['keterangan'] != null &&
                  data['keterangan'].toString().trim().isNotEmpty) {
                continue;
              }
              merged[entry.key] = entry.value;
            }
          }
          // Restore preserved keys if they were overwritten with empty values
          for (final key in keysToPreserve) {
            if (data.containsKey(key) &&
                (!merged.containsKey(key) || merged[key] == null)) {
              merged[key] = data[key];
            }
          }

          if (mounted) {
            setState(() {
              data = merged;
              _isRefreshing = false;
            });
          }
          return;
        }
      }

      if (kDebugMode) {
        debugPrint(
          '[TransactionDetail] API returned non-success or non-200: ${response.body}',
        );
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[TransactionDetail] Error fetching fresh data: $e');
      }
    }

    if (mounted) {
      setState(() => _isRefreshing = false);
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

  String _formatDateTime(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      return DateFormat('dd MMM yyyy, HH:mm').format(d);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatDateOnly(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      return DateFormat('dd MMM yyyy').format(d);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatTimeOnly(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      return DateFormat('HH:mm').format(d);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatTransactionId(dynamic value) {
    if (value == null) {
      return '-';
    }
    final raw = value.toString().trim();
    if (raw.isEmpty || raw == '0') {
      return '-';
    }
    // If no_transaksi is available (formatted like KRM-20260212-000452), use it directly
    if (RegExp(r'^[A-Z]{3}-\d{8}-\d+$').hasMatch(raw)) {
      return raw;
    }
    final match = RegExp(r'(\d+)$').firstMatch(raw);
    return match?.group(1) ?? raw;
  }

  /// Returns the best available transaction number, preferring no_transaksi over id_transaksi
  dynamic _getBestTransactionNo() {
    // Priority 1: no_transaksi (formatted like KRM-20260212-000452)
    final noTransaksi = (data['no_transaksi'] ?? '').toString().trim();
    if (noTransaksi.isNotEmpty && noTransaksi != '0') {
      return noTransaksi;
    }
    // Priority 2: id_transaksi (numeric DB id)
    final idTransaksi = data['id_transaksi'];
    if (idTransaksi != null && idTransaksi.toString().trim().isNotEmpty && idTransaksi.toString().trim() != '0') {
      return idTransaksi;
    }
    // Priority 3: id
    final id = data['id'];
    if (id != null && id.toString().trim().isNotEmpty && id.toString().trim() != '0') {
      return id;
    }
    return null;
  }

  String _formatJenisTabungan(String jenis) {
    // Add "Tabungan " prefix if not already present
    final jenisTrimmed = jenis.trim();
    if (jenisTrimmed.toLowerCase().startsWith('tabungan ')) {
      return jenisTrimmed; // Already has prefix
    }
    return 'Tabungan ' + jenisTrimmed; // Add prefix
  }

  /// Returns the best label for jenis pinjaman (e.g. 'Pinjaman Biasa', 'Pinjaman Kredit')
  String _getJenisPinjamanLabel() {
    final jenisPinjaman = (data['jenis_pinjaman'] ?? '')
        .toString()
        .toLowerCase()
        .trim();
    final jenisTransaksi = (data['jenis_transaksi'] ?? '')
        .toString()
        .toLowerCase()
        .trim();
    if (jenisPinjaman == 'kredit' || jenisTransaksi.contains('kredit')) {
      return 'Pinjaman Kredit';
    }
    return 'Pinjaman Biasa';
  }

  /// Returns the best available datetime string from the data
  String _getBestDateTime() {
    // Try multiple date fields in priority order
    final candidates = [
      data['detail_created_at'],
      data['created_at'],
      data['tanggal'],
      data['updated_at'],
    ];
    for (final c in candidates) {
      if (c != null && c.toString().trim().isNotEmpty) {
        final str = c.toString().trim();
        // Validate it looks like a date (not just a number/ID)
        if (str.contains('-') || str.contains('/')) {
          return str;
        }
      }
    }
    return DateTime.now().toIso8601String();
  }

  String _normalizeJenis(String jenis) {
    return jenis
        .toLowerCase()
        .replaceAll(RegExp(r'\btabungan\b', caseSensitive: false), '')
        .trim();
  }

  String _formatKeterangan(String? keterangan) {
    if (keterangan == null || keterangan.isEmpty) {
      return '-';
    }

    final keteranganLower = keterangan.toLowerCase().trim();
    final dataJenis = (data['jenis_tabungan'] ?? '').toString().trim();
    final normalizedJenis = _normalizeJenis(dataJenis);

    // If already a full notification-style descriptive message, keep it
    if (keteranganLower.startsWith('pengajuan ') ||
        keteranganLower.startsWith('pencairan ') ||
        keteranganLower.startsWith('setoran ') ||
        keteranganLower.startsWith('admin telah') ||
        keteranganLower.startsWith('anda menerima') ||
        keteranganLower.startsWith('kirim uang') ||
        keteranganLower.startsWith('terima uang')) {
      if (normalizedJenis.isNotEmpty &&
          keteranganLower.contains('tabungan') &&
          !keteranganLower.contains(normalizedJenis)) {
        // Fall through to rebuild with correct jenis tabungan.
      } else {
        return keterangan;
      }
    }

    // Build notification-style descriptive message
    final statusLabel = _getStatusLabel();
    final transactionType = _getTransactionType();
    final nominal =
        data['jumlah'] ??
        data['price'] ??
        data['nominal'] ??
        data['amount'] ??
        0;
    final formattedAmount = _formatCurrency(nominal);

    // Get jenis tabungan with proper prefix
    var jenisTabungan = (data['jenis_tabungan'] ?? '').toString().trim();
    if (jenisTabungan.isEmpty) {
      jenisTabungan = 'Tabungan';
    } else if (!jenisTabungan.toLowerCase().startsWith('tabungan')) {
      jenisTabungan = 'Tabungan $jenisTabungan';
    }

    // Handle "Setoran manual oleh admin" - convert to notification style
    if (keteranganLower.contains('setoran manual oleh admin')) {
      return 'Admin telah menambahkan saldo $jenisTabungan Anda sebesar $formattedAmount';
    }

    // Setoran Tabungan
    if (transactionType == 'Setoran Tabungan') {
      if (statusLabel == 'Ditolak') {
        return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      } else if (statusLabel == 'Disetujui') {
        return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount disetujui, silahkan cek saldo di halaman Tabungan';
      } else if (statusLabel == 'Menunggu') {
        return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount berhasil dikirim dan sedang menunggu persetujuan dari admin.';
      }
    }

    // Pencairan Tabungan
    if (transactionType == 'Pencairan Tabungan') {
      if (statusLabel == 'Ditolak') {
        return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      } else if (statusLabel == 'Disetujui') {
        return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount disetujui, silahkan cek saldo di halaman Tabungan';
      } else if (statusLabel == 'Menunggu') {
        return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount sedang menunggu persetujuan dari admin.';
      }
    }

    // Pinjaman
    if (transactionType == 'Pinjaman') {
      final tenor = data['tenor'] ?? 0;
      final tenorStr = (tenor != null && tenor != 0)
          ? ' untuk tenor $tenor bulan'
          : '';
      if (statusLabel == 'Ditolak') {
        return 'Pengajuan Pinjaman Anda sebesar $formattedAmount$tenorStr ditolak oleh admin, silahkan hubungi admin untuk informasi lebih lanjut.';
      } else if (statusLabel == 'Disetujui') {
        return 'Pengajuan Pinjaman Anda sebesar $formattedAmount$tenorStr disetujui oleh admin, silahkan anda cek saldo di halaman dashboard.';
      } else if (statusLabel == 'Menunggu') {
        return 'Pengajuan Pinjaman sebesar $formattedAmount$tenorStr sedang menunggu persetujuan admin.';
      }
    }

    // Transfer Keluar
    if (transactionType == 'Transfer' || transactionType == 'Transfer Keluar' || transactionType == 'Kirim Uang') {
      if (statusLabel == 'Ditolak') {
        return 'Transfer Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      } else if (statusLabel == 'Disetujui' || statusLabel == 'Berhasil') {
        return 'Transfer Anda sebesar $formattedAmount berhasil.';
      } else if (statusLabel == 'Menunggu') {
        return 'Transfer Anda sebesar $formattedAmount sedang diproses.';
      }
    }

    // Terima Uang
    if (transactionType == 'Terima Uang') {
      return 'Anda menerima transfer sebesar $formattedAmount.';
    }

    // Return original if no pattern matched
    return keterangan;
  }

  String _getTransactionType() {
    // Check jenis_transaksi field first (from API response)
    final jenisTransaksi = (data['jenis_transaksi'] ?? '')
        .toString()
        .toLowerCase();
    if (jenisTransaksi.isNotEmpty) {
      if (jenisTransaksi == 'setoran') return 'Setoran Tabungan';
      if (jenisTransaksi == 'penarikan') return 'Pencairan Tabungan';
      if (jenisTransaksi == 'pinjaman' ||
          jenisTransaksi == 'pinjaman_biasa' ||
          jenisTransaksi == 'pinjaman_kredit')
        return 'Pinjaman';
      if (jenisTransaksi == 'transfer_masuk') return 'Terima Uang';
      if (jenisTransaksi == 'transfer_keluar') return 'Kirim Uang';
    }

    // Fallback to type field
    final type = (data['type'] ?? '').toString().toLowerCase();
    final title = (data['title'] ?? '').toString().trim().toLowerCase();

    if (title.isNotEmpty) {
      if (title.contains('transfer')) return 'Transfer';
      if (title.contains('top-up') || title.contains('topup'))
        return 'Setoran Tabungan';
      if (title.contains('listrik')) return 'Bayar Listrik';
      if (title.contains('pulsa')) return 'Beli Pulsa';
      if (title.contains('kuota')) return 'Beli Kuota';
    }

    return type == 'listrik'
        ? 'Bayar Listrik'
        : type == 'pulsa'
        ? 'Beli Pulsa'
        : type == 'kuota'
        ? 'Beli Kuota'
        : type == 'topup'
        ? 'Setoran Tabungan'
        : type == 'transfer'
        ? 'Transfer'
        : type == 'pinjaman'
        ? 'Pinjaman'
        : 'Transaksi';
  }

  String _getStatusLabel() {
    final status = (data['status'] ?? '').toString().toLowerCase();
    final keterangan = (data['keterangan'] ?? '').toString().toLowerCase();
    final jenisTransaksi = (data['jenis_transaksi'] ?? '').toString().toLowerCase();

    // For transfer types (kirim uang / terima uang), show 'Berhasil' instead of 'Disetujui'
    // because transfers don't require admin approval
    final isTransferType = jenisTransaksi == 'transfer_keluar' || jenisTransaksi == 'transfer_masuk';

    // Prioritize explicit status values
    if (status == 'rejected' || status == 'ditolak') {
      return 'Ditolak';
    }
    if (status == 'approved' || status == 'disetujui') {
      return isTransferType ? 'Berhasil' : 'Disetujui';
    }
    if (status == 'success' || status == 'selesai' || status == 'berhasil') {
      return isTransferType ? 'Berhasil' : 'Disetujui';
    }
    if (status == 'pending' || status == 'menunggu') {
      return 'Menunggu';
    }

    // Fall back to keterangan checking
    if (keterangan.contains('ditolak') ||
        keterangan.contains('gagal') ||
        status == 'failed' ||
        status == 'error') {
      return 'Ditolak';
    }
    if (keterangan.contains('disetujui') ||
        keterangan.contains('berhasil') ||
        keterangan.contains('sukses')) {
      return isTransferType ? 'Berhasil' : 'Disetujui';
    }
    if (data['processing'] == true) {
      return 'Menunggu';
    }

    return 'Unknown';
  }

  Color _getStatusColor() {
    final status = _getStatusLabel();
    switch (status) {
      case 'Disetujui':
      case 'Berhasil':
        return Colors.green;
      case 'Ditolak':
        return Colors.red;
      case 'Menunggu':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  IconData _getStatusIcon() {
    final status = _getStatusLabel();
    switch (status) {
      case 'Disetujui':
      case 'Berhasil':
        return Icons.check_circle;
      case 'Ditolak':
        return Icons.cancel;
      case 'Menunggu':
        return Icons.schedule;
      default:
        return Icons.info;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final nominal =
        data['jumlah'] ??
        data['price'] ??
        data['nominal'] ??
        data['amount'] ??
        0;
    final transactionType = _getTransactionType();
    final statusLabel = _getStatusLabel();
    final statusColor = _getStatusColor();
    final statusIcon = _getStatusIcon();

    return Scaffold(
      appBar: OrangeHeader(
        title: 'Rincian Transaksi',
        onBackPressed: () => Navigator.of(context).pop(),
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Header with large amount
            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: isDark
                    ? theme.scaffoldBackgroundColor
                    : Colors.grey.shade50,
                border: Border(
                  bottom: BorderSide(
                    color: isDark ? Colors.grey.shade700 : Colors.grey.shade200,
                  ),
                ),
              ),
              padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
              child: Column(
                children: [
                  Text(
                    _formatCurrency(nominal),
                    style: GoogleFonts.poppins(
                      fontSize: 36,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFFF5F0A),
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    transactionType,
                    style: GoogleFonts.roboto(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      color: isDark ? Colors.grey.shade400 : Colors.grey,
                    ),
                  ),
                ],
              ),
            ),

            // Status section
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
              decoration: BoxDecoration(
                color: statusColor.withOpacity(0.1),
                border: Border(
                  bottom: BorderSide(color: statusColor.withOpacity(0.3)),
                ),
              ),
              child: Row(
                children: [
                  Icon(statusIcon, color: statusColor, size: 24),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Status',
                        style: GoogleFonts.roboto(
                          fontSize: 12,
                          color: isDark ? Colors.grey.shade500 : Colors.grey,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        statusLabel,
                        style: GoogleFonts.roboto(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: statusColor,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Transaction Details
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Informasi Transaksi
                  Text(
                    'Informasi Transaksi',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFFF5F0A),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'No. Transaksi',
                    _formatTransactionId(_getBestTransactionNo()),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(context, 'Jenis Transaksi', transactionType),
                  // Show Jenis Pinjaman for pinjaman, Jenis Tabungan for others (hide for Kirim Uang)
                  if (transactionType == 'Pinjaman') ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Jenis Pinjaman',
                      _getJenisPinjamanLabel(),
                    ),
                  ] else if (transactionType != 'Kirim Uang' && transactionType != 'Terima Uang' && (data['jenis_tabungan'] ?? '')
                      .toString()
                      .trim()
                      .isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Jenis Tabungan',
                      _formatJenisTabungan(
                        (data['jenis_tabungan'] ?? '').toString(),
                      ),
                    ),
                  ],

                  // Metode pembayaran
                  if ((data['metode'] ?? '').toString().trim().isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Metode Pembayaran',
                      (data['metode'] ?? '').toString(),
                    ),
                  ],

                  const SizedBox(height: 20),

                  // Detail
                  Text(
                    'Detail',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFFF5F0A),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(context, 'Nominal', _formatCurrency(nominal)),

                  // Keterangan/description
                  if ((data['keterangan'] ?? '')
                      .toString()
                      .trim()
                      .isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Keterangan',
                      _formatKeterangan((data['keterangan'] ?? '').toString()),
                      isLongText: true,
                    ),
                  ],

                  const SizedBox(height: 20),

                  // Waktu
                  Text(
                    'Waktu',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFFF5F0A),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'Tanggal',
                    _formatDateOnly(_getBestDateTime()),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'Waktu',
                    _formatTimeOnly(_getBestDateTime()),
                  ),

                  // Updated at if different from created_at
                  if ((data['updated_at'] ?? '').toString().isNotEmpty &&
                      data['updated_at'] != data['created_at']) ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Waktu Pembaruan',
                      _formatDateTime(data['updated_at']),
                    ),
                  ],

                  const SizedBox(height: 16),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(
    BuildContext context,
    String label,
    String value, {
    bool isLongText = false,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.roboto(
            fontSize: 12,
            color: isDark ? Colors.grey.shade500 : Colors.grey,
          ),
        ),
        const SizedBox(height: 6),
        isLongText
            ? Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: isDark ? Colors.grey.shade800 : Colors.grey.shade100,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  value,
                  style: GoogleFonts.roboto(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              )
            : Text(
                value,
                style: GoogleFonts.roboto(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
      ],
    );
  }
}
