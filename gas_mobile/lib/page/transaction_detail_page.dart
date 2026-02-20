import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/rendering.dart';
import 'dart:ui' show ImageByteFormat;
import 'dart:io';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:share_plus/share_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/utils/custom_toast.dart';

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
  final GlobalKey _detailKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    data = Map<String, dynamic>.from(widget.transaction);
    _fetchFreshDataFromApi();
  }

  Future<void> _fetchFreshDataFromApi() async {
    try {
      final idTransaksi = data['id_transaksi'] ?? data['id'];

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
        debugPrint('[TransactionDetail] Fetching fresh data for id_transaksi=$idStr');
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

          final keysToPreserve = ['type', 'title', 'processing'];
          final merged = Map<String, dynamic>.from(data);
          for (final entry in freshData.entries) {
            if (entry.value != null && entry.value.toString().trim().isNotEmpty) {
              if (entry.key == 'keterangan' &&
                  data['keterangan'] != null &&
                  data['keterangan'].toString().trim().isNotEmpty) {
                continue;
              }
              merged[entry.key] = entry.value;
            }
          }
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
        debugPrint('[TransactionDetail] API returned non-success or non-200: ${response.body}');
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
    if (value == null) return '-';
    final raw = value.toString().trim();
    if (raw.isEmpty || raw == '0') return '-';
    if (RegExp(r'^[A-Z]{3}-\d{8}-\d+$').hasMatch(raw)) return raw;
    final match = RegExp(r'(\d+)$').firstMatch(raw);
    return match?.group(1) ?? raw;
  }

  dynamic _getBestTransactionNo() {
    final noTransaksi = (data['no_transaksi'] ?? '').toString().trim();
    if (noTransaksi.isNotEmpty && noTransaksi != '0') return noTransaksi;
    final idTransaksi = data['id_transaksi'];
    if (idTransaksi != null && idTransaksi.toString().trim().isNotEmpty && idTransaksi.toString().trim() != '0') return idTransaksi;
    final id = data['id'];
    if (id != null && id.toString().trim().isNotEmpty && id.toString().trim() != '0') return id;
    return null;
  }

  String _formatJenisTabungan(String jenis) {
    final jenisTrimmed = jenis.trim();
    if (jenisTrimmed.toLowerCase().startsWith('tabungan ')) return jenisTrimmed;
    return 'Tabungan ' + jenisTrimmed;
  }

  String _getJenisPinjamanLabel() {
    final jenisPinjaman = (data['jenis_pinjaman'] ?? '').toString().toLowerCase().trim();
    final jenisTransaksi = (data['jenis_transaksi'] ?? '').toString().toLowerCase().trim();
    if (jenisPinjaman == 'kredit' || jenisTransaksi.contains('kredit')) return 'Pinjaman Kredit';
    return 'Pinjaman Biasa';
  }

  String _getBestDateTime() {
    final candidates = [
      data['detail_created_at'],
      data['created_at'],
      data['tanggal'],
      data['updated_at'],
    ];
    for (final c in candidates) {
      if (c != null && c.toString().trim().isNotEmpty) {
        final str = c.toString().trim();
        if (str.contains('-') || str.contains('/')) return str;
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
    if (keterangan == null || keterangan.isEmpty) return '-';

    final keteranganLower = keterangan.toLowerCase().trim();
    final dataJenis = (data['jenis_tabungan'] ?? '').toString().trim();
    final normalizedJenis = _normalizeJenis(dataJenis);

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
      } else {
        return keterangan;
      }
    }

    final statusLabel = _getStatusLabel();
    final transactionType = _getTransactionType();
    final nominal = data['jumlah'] ?? data['price'] ?? data['nominal'] ?? data['amount'] ?? 0;
    final formattedAmount = _formatCurrency(nominal);

    var jenisTabungan = (data['jenis_tabungan'] ?? '').toString().trim();
    if (jenisTabungan.isEmpty) {
      jenisTabungan = 'Tabungan';
    } else if (!jenisTabungan.toLowerCase().startsWith('tabungan')) {
      jenisTabungan = 'Tabungan $jenisTabungan';
    }

    if (keteranganLower.contains('setoran manual oleh admin')) {
      return 'Admin telah menambahkan saldo $jenisTabungan Anda sebesar $formattedAmount';
    }

    if (transactionType == 'Setoran Tabungan') {
      if (statusLabel == 'Ditolak') return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      else if (statusLabel == 'Disetujui') return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount disetujui, silahkan cek saldo di halaman Tabungan';
      else if (statusLabel == 'Menunggu') return 'Pengajuan Setoran $jenisTabungan Anda sebesar $formattedAmount berhasil dikirim dan sedang menunggu persetujuan dari admin.';
    }

    if (transactionType == 'Pencairan Tabungan') {
      if (statusLabel == 'Ditolak') return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      else if (statusLabel == 'Disetujui') return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount disetujui, silahkan cek saldo di halaman Tabungan';
      else if (statusLabel == 'Menunggu') return 'Pengajuan Pencairan $jenisTabungan Anda sebesar $formattedAmount sedang menunggu persetujuan dari admin.';
    }

    if (transactionType == 'Pinjaman') {
      final tenor = data['tenor'] ?? 0;
      final tenorStr = (tenor != null && tenor != 0) ? ' untuk tenor $tenor bulan' : '';
      if (statusLabel == 'Ditolak') return 'Pengajuan Pinjaman Anda sebesar $formattedAmount$tenorStr ditolak oleh admin, silahkan hubungi admin untuk informasi lebih lanjut.';
      else if (statusLabel == 'Disetujui') return 'Pengajuan Pinjaman Anda sebesar $formattedAmount$tenorStr disetujui oleh admin, silahkan anda cek saldo di halaman dashboard.';
      else if (statusLabel == 'Menunggu') return 'Pengajuan Pinjaman sebesar $formattedAmount$tenorStr sedang menunggu persetujuan admin.';
    }

    if (transactionType == 'Transfer' || transactionType == 'Transfer Keluar' || transactionType == 'Kirim Uang') {
      if (statusLabel == 'Ditolak') return 'Transfer Anda sebesar $formattedAmount ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
      else if (statusLabel == 'Disetujui' || statusLabel == 'Berhasil') return 'Transfer Anda sebesar $formattedAmount berhasil.';
      else if (statusLabel == 'Menunggu') return 'Transfer Anda sebesar $formattedAmount sedang diproses.';
    }

    if (transactionType == 'Terima Uang') return 'Anda menerima transfer sebesar $formattedAmount.';

    return keterangan;
  }

  String _getTransactionType() {
    final jenisTransaksi = (data['jenis_transaksi'] ?? '').toString().toLowerCase();
    if (jenisTransaksi.isNotEmpty) {
      if (jenisTransaksi == 'setoran') return 'Setoran Tabungan';
      if (jenisTransaksi == 'penarikan') return 'Pencairan Tabungan';
      if (jenisTransaksi == 'pinjaman' || jenisTransaksi == 'pinjaman_biasa' || jenisTransaksi == 'pinjaman_kredit') return 'Pinjaman';
      if (jenisTransaksi == 'transfer_masuk') return 'Terima Uang';
      if (jenisTransaksi == 'transfer_keluar') return 'Kirim Uang';
    }

    final type = (data['type'] ?? '').toString().toLowerCase();
    final title = (data['title'] ?? '').toString().trim().toLowerCase();

    if (title.isNotEmpty) {
      if (title.contains('transfer')) return 'Transfer';
      if (title.contains('top-up') || title.contains('topup')) return 'Setoran Tabungan';
      if (title.contains('listrik')) return 'Bayar Listrik';
      if (title.contains('pulsa')) return 'Beli Pulsa';
      if (title.contains('kuota')) return 'Beli Kuota';
    }

    return type == 'listrik' ? 'Bayar Listrik'
        : type == 'pulsa' ? 'Beli Pulsa'
        : type == 'kuota' ? 'Beli Kuota'
        : type == 'topup' ? 'Setoran Tabungan'
        : type == 'transfer' ? 'Transfer'
        : type == 'pinjaman' ? 'Pinjaman'
        : 'Transaksi';
  }

  String _getStatusLabel() {
    final status = (data['status'] ?? '').toString().toLowerCase();
    final keterangan = (data['keterangan'] ?? '').toString().toLowerCase();
    final jenisTransaksi = (data['jenis_transaksi'] ?? '').toString().toLowerCase();
    final isTransferType = jenisTransaksi == 'transfer_keluar' || jenisTransaksi == 'transfer_masuk';

    if (status == 'rejected' || status == 'ditolak') return 'Ditolak';
    if (status == 'approved' || status == 'disetujui') return isTransferType ? 'Berhasil' : 'Disetujui';
    if (status == 'success' || status == 'selesai' || status == 'berhasil') return isTransferType ? 'Berhasil' : 'Disetujui';
    if (status == 'pending' || status == 'menunggu') return 'Menunggu';

    if (keterangan.contains('ditolak') || keterangan.contains('gagal') || status == 'failed' || status == 'error') return 'Ditolak';
    if (keterangan.contains('disetujui') || keterangan.contains('berhasil') || keterangan.contains('sukses')) return isTransferType ? 'Berhasil' : 'Disetujui';
    if (data['processing'] == true) return 'Menunggu';

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

  Future<Uint8List?> _captureDetailPage() async {
    try {
      RenderRepaintBoundary? boundary = _detailKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
      if (boundary == null) {
        CustomToast.error(context, 'Gagal membuat gambar');
        return null;
      }
      final image = await boundary.toImage(pixelRatio: 3.0);
      final byteData = await image.toByteData(format: ImageByteFormat.png);
      if (byteData == null) return null;
      return byteData.buffer.asUint8List();
    } catch (e) {
      if (kDebugMode) debugPrint('Error capturing detail page: $e');
      CustomToast.error(context, 'Gagal membuat gambar');
      return null;
    }
  }

  // ✅ LOADING OVERLAY
  void _showLoadingOverlay() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => const Center(
        child: CircularProgressIndicator(
          color: Color(0xFFFF6A00),
          strokeWidth: 3,
        ),
      ),
    );
  }

  Future<void> _shareToWhatsApp() async {
    try {
      _showLoadingOverlay();
      final imageBytes = await _captureDetailPage();
      if (mounted) Navigator.pop(context);
      if (imageBytes == null) return;

      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/rincian_${DateTime.now().millisecondsSinceEpoch}.png');
      await file.writeAsBytes(imageBytes);
      await Share.shareXFiles([XFile(file.path)]);
    } catch (e) {
      if (mounted) Navigator.pop(context);
      CustomToast.error(context, 'Gagal membagikan: $e');
    }
  }

  Future<void> _shareToApps() async {
    try {
      _showLoadingOverlay();
      final imageBytes = await _captureDetailPage();
      if (mounted) Navigator.pop(context);
      if (imageBytes == null) return;

      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/rincian_${DateTime.now().millisecondsSinceEpoch}.png');
      await file.writeAsBytes(imageBytes);
      await Share.shareXFiles([XFile(file.path)], subject: 'Rincian Transaksi');
    } catch (e) {
      if (mounted) Navigator.pop(context);
      CustomToast.error(context, 'Gagal membagikan: $e');
    }
  }

  void _showShareOptions() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Container(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Bagikan Transaksi',
              style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 20),
            ListTile(
              leading: Icon(Icons.chat, color: Colors.green.shade600),
              title: Text('WhatsApp', style: GoogleFonts.roboto(fontSize: 14)),
              onTap: () {
                Navigator.pop(ctx);
                _shareToWhatsApp();
              },
            ),
            ListTile(
              leading: Icon(Icons.share, color: Colors.blue.shade600),
              title: Text('Bagikan ke Aplikasi Lain', style: GoogleFonts.roboto(fontSize: 14)),
              onTap: () {
                Navigator.pop(ctx);
                _shareToApps();
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final nominal = data['jumlah'] ?? data['price'] ?? data['nominal'] ?? data['amount'] ?? 0;
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
            RepaintBoundary(
              key: _detailKey,
              child: Container(
                color: Colors.white,
                child: Column(
                  children: [
                    Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: Colors.grey.shade50,
                        border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
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
                              color: Colors.grey,
                            ),
                          ),
                        ],
                      ),
                    ),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
                      decoration: BoxDecoration(
                        color: statusColor.withOpacity(0.1),
                        border: Border(bottom: BorderSide(color: statusColor.withOpacity(0.3))),
                      ),
                      child: Row(
                        children: [
                          Icon(statusIcon, color: statusColor, size: 24),
                          const SizedBox(width: 12),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Status', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 4),
                              Text(
                                statusLabel,
                                style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w600, color: statusColor),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Informasi Transaksi', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                          const SizedBox(height: 12),
                          _buildDetailRowLight('No. Transaksi', _formatTransactionId(_getBestTransactionNo())),
                          const SizedBox(height: 12),
                          _buildDetailRowLight('Jenis Transaksi', transactionType),
                          if (transactionType == 'Pinjaman') ...[
                            const SizedBox(height: 12),
                            _buildDetailRowLight('Jenis Pinjaman', _getJenisPinjamanLabel()),
                          ] else if (transactionType != 'Kirim Uang' && transactionType != 'Terima Uang' && (data['jenis_tabungan'] ?? '').toString().trim().isNotEmpty) ...[
                            const SizedBox(height: 12),
                            _buildDetailRowLight('Jenis Tabungan', _formatJenisTabungan((data['jenis_tabungan'] ?? '').toString())),
                          ],
                          if ((data['metode'] ?? '').toString().trim().isNotEmpty) ...[
                            const SizedBox(height: 12),
                            _buildDetailRowLight('Metode Pembayaran', (data['metode'] ?? '').toString()),
                          ],
                          const SizedBox(height: 20),
                          Text('Detail', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                          const SizedBox(height: 12),
                          _buildDetailRowLight('Nominal', _formatCurrency(nominal)),
                          if ((data['keterangan'] ?? '').toString().trim().isNotEmpty) ...[
                            const SizedBox(height: 12),
                            _buildDetailRowLight('Keterangan', _formatKeterangan((data['keterangan'] ?? '').toString()), isLongText: true),
                          ],
                          const SizedBox(height: 20),
                          Text('Waktu', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                          const SizedBox(height: 12),
                          _buildDetailRowLight('Tanggal', _formatDateOnly(_getBestDateTime())),
                          const SizedBox(height: 12),
                          _buildDetailRowLight('Waktu', _formatTimeOnly(_getBestDateTime())),
                          if ((data['updated_at'] ?? '').toString().isNotEmpty && data['updated_at'] != data['created_at']) ...[
                            const SizedBox(height: 12),
                            _buildDetailRowLight('Waktu Pembaruan', _formatDateTime(data['updated_at'])),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(16),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _showShareOptions,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF5F0A),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.share, color: Colors.white, size: 20),
                      const SizedBox(width: 8),
                      Text(
                        'BAGIKAN',
                        style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white, letterSpacing: 0.5),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRowLight(String label, String value, {bool isLongText = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
        const SizedBox(height: 6),
        isLongText
            ? Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: Colors.grey.shade100, borderRadius: BorderRadius.circular(6)),
                child: Text(value, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
              )
            : Text(value, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
      ],
    );
  }

  Widget _buildDetailRow(BuildContext context, String label, String value, {bool isLongText = false}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: GoogleFonts.roboto(fontSize: 12, color: isDark ? Colors.grey.shade500 : Colors.grey)),
        const SizedBox(height: 6),
        isLongText
            ? Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: isDark ? Colors.grey.shade800 : Colors.grey.shade100, borderRadius: BorderRadius.circular(6)),
                child: Text(value, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
              )
            : Text(value, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
      ],
    );
  }
}