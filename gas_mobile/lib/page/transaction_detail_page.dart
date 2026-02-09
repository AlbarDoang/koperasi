import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:tabungan/page/orange_header.dart';

class TransactionDetailPage extends StatefulWidget {
  final Map<String, dynamic> transaction;

  const TransactionDetailPage({
    Key? key,
    required this.transaction,
  }) : super(key: key);

  @override
  State<TransactionDetailPage> createState() => _TransactionDetailPageState();
}

class _TransactionDetailPageState extends State<TransactionDetailPage> {
  late Map<String, dynamic> data;

  @override
  void initState() {
    super.initState();
    data = Map<String, dynamic>.from(widget.transaction);
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
      // Convert from UTC to Jakarta timezone (UTC+7)
      final jakartaTime = d.add(Duration(hours: 7));
      return DateFormat('dd MMM yyyy, HH:mm').format(jakartaTime);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatDateOnly(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      // Convert from UTC to Jakarta timezone (UTC+7)
      final jakartaTime = d.add(Duration(hours: 7));
      return DateFormat('dd MMM yyyy').format(jakartaTime);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatTimeOnly(dynamic v) {
    try {
      final d = DateTime.parse(v.toString());
      // Convert from UTC to Jakarta timezone (UTC+7)
      // Server typically sends times in UTC, so we need to add 7 hours
      final jakartaTime = d.add(Duration(hours: 7));
      return DateFormat('HH:mm').format(jakartaTime);
    } catch (_) {
      return v?.toString() ?? '-';
    }
  }

  String _formatJenisTabungan(String jenis) {
    // Add "Tabungan " prefix if not already present
    final jenisTrimmed = jenis.trim();
    if (jenisTrimmed.toLowerCase().startsWith('tabungan ')) {
      return jenisTrimmed;  // Already has prefix
    }
    return 'Tabungan ' + jenisTrimmed;  // Add prefix
  }

  String _formatKeterangan(String? keterangan) {
    if (keterangan == null || keterangan.isEmpty) {
      return '-';
    }
    
    final keteranganLower = keterangan.toLowerCase().trim();
    
    // Handle format "Pencairan Tabungan Disetujui - [Jenis]" or "Pencairan Tabungan Ditolak - [Jenis]"
    // Extract just the status part without the jenis in brackets
    if (keteranganLower.contains('pencairan tabungan')) {
      if (keteranganLower.contains('disetujui')) {
        return 'Pencairan Tabungan Disetujui';
      } else if (keteranganLower.contains('ditolak')) {
        return 'Pencairan Tabungan Ditolak';
      }
    }
    
    // Handle format "Pencairan Tabungan: Withdrawal approved" or "Penarikan Tabungan: Withdrawal approved"
    if ((keteranganLower.contains('pencairan tabungan') || keteranganLower.contains('penarikan tabungan')) && 
        (keteranganLower.contains('withdrawal approved') || keteranganLower.contains('approved'))) {
      return 'Pencairan Tabungan Disetujui';
    }
    
    // Handle format setor manual: "Setoran manual oleh admin - keterangan (tabungan_masuk X)"
    if (keteranganLower.contains('setoran manual oleh admin') && keteranganLower.contains('(tabungan_masuk')) {
      // Extract the part before "(tabungan_masuk" and replace with new format
      final parts = keterangan.split('(tabungan_masuk');
      var cleanText = parts[0].trim();
      
      // Remove trailing dash and spaces
      if (cleanText.endsWith('-')) {
        cleanText = cleanText.substring(0, cleanText.length - 1).trim();
      }
      
      // Add "berhasil" before dash if not already present
      if (!cleanText.toLowerCase().contains('berhasil')) {
        // Check if there's a dash at the end for the note
        if (cleanText.contains('-')) {
          final beforeDash = cleanText.substring(0, cleanText.lastIndexOf('-')).trim();
          final afterDash = cleanText.substring(cleanText.lastIndexOf('-') + 1).trim();
          cleanText = beforeDash + ' berhasil - ' + afterDash;
        } else {
          cleanText = cleanText + ' berhasil';
        }
      }
      
      return cleanText;
    }
    
    // If keterangan has the format "Setoran Tabungan X (mulai_nabung Y)", extract just the first part
    if (keteranganLower.contains('setoran tabungan') && keteranganLower.contains('mulai_nabung')) {
      // Extract text before "(mulai_nabung"
      final cleanText = keterangan.split('(mulai_nabung')[0].trim();
      return cleanText;
    }
    
    // If already formatted with "Setoran Tabungan", return as is
    if (keteranganLower.contains('setoran tabungan')) {
      return keterangan;
    }
    
    // Check for old format and convert
    if (keteranganLower.contains('mulai nabung tunai') || keteranganLower.contains('mulai_nabung')) {
      // Extract status from the keterangan or use status field
      final statusLabel = _getStatusLabel();
      if (statusLabel == 'Ditolak') {
        return 'Setoran Tabungan Ditolak';
      } else if (statusLabel == 'Disetujui') {
        return 'Setoran Tabungan Disetujui';
      } else if (statusLabel == 'Menunggu') {
        return 'Pengajuan Setoran Tabungan Menunggu Persetujuan';
      }
    }
    
    // Return original if no pattern matched
    return keterangan;
  }

  String _getTransactionType() {
    // Check jenis_transaksi field first (from API response)
    final jenisTransaksi = (data['jenis_transaksi'] ?? '').toString().toLowerCase();
    if (jenisTransaksi.isNotEmpty) {
      if (jenisTransaksi == 'setoran') return 'Setoran Tabungan';
      if (jenisTransaksi == 'penarikan') return 'Pencairan Tabungan';
      if (jenisTransaksi == 'transfer_masuk') return 'Transfer Masuk';
      if (jenisTransaksi == 'transfer_keluar') return 'Transfer Keluar';
    }

    // Fallback to type field
    final type = (data['type'] ?? '').toString().toLowerCase();
    final title = (data['title'] ?? '').toString().trim().toLowerCase();

    if (title.isNotEmpty) {
      if (title.contains('transfer')) return 'Transfer';
      if (title.contains('top-up') || title.contains('topup')) return 'Setoran Tabungan';
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

    // Prioritize explicit status values
    if (status == 'rejected' || status == 'ditolak') {
      return 'Ditolak';
    }
    if (status == 'approved' || status == 'disetujui') {
      return 'Disetujui';
    }
    if (status == 'success' || status == 'selesai') {
      return 'Disetujui';
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
      return 'Disetujui';
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
            // Header with large amount
            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: isDark
                    ? theme.scaffoldBackgroundColor
                    : Colors.grey.shade50,
                border: Border(
                  bottom: BorderSide(
                    color:
                        isDark ? Colors.grey.shade700 : Colors.grey.shade200,
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
                  bottom: BorderSide(
                    color: statusColor.withOpacity(0.3),
                  ),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    statusIcon,
                    color: statusColor,
                    size: 24,
                  ),
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
                    data['id_transaksi']?.toString() ?? data['id']?.toString() ?? '-',
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'Jenis Transaksi',
                    transactionType,
                  ),
                  if ((data['jenis_tabungan'] ?? '').toString().trim().isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _buildDetailRow(
                      context,
                      'Jenis Tabungan',
                      _formatJenisTabungan((data['jenis_tabungan'] ?? '').toString()),
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

                  // Detail Setoran
                  Text(
                    'Detail Setoran',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFFFF5F0A),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'Nominal',
                    _formatCurrency(nominal),
                  ),

                  // Keterangan/description
                  if ((data['keterangan'] ?? '').toString().trim().isNotEmpty) ...[
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
                    _formatDateOnly(data['created_at'] ?? data['id']),
                  ),
                  const SizedBox(height: 12),
                  _buildDetailRow(
                    context,
                    'Waktu',
                    _formatTimeOnly(data['created_at'] ?? data['id']),
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
                  color: isDark
                      ? Colors.grey.shade800
                      : Colors.grey.shade100,
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
