import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'dart:ui' show ImageByteFormat;
import 'dart:io';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'dart:convert';
import 'dart:typed_data';
import 'package:url_launcher/url_launcher.dart';
import 'package:share_plus/share_plus.dart';
import 'package:path_provider/path_provider.dart';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';

class TransferConfirmPage extends StatefulWidget {
  final String phone;
  final String? recipientName;
  final int amount;
  final String note;
  final bool isFirstTransfer;

  const TransferConfirmPage({
    super.key,
    required this.phone,
    this.recipientName,
    required this.amount,
    required this.note,
    this.isFirstTransfer = true,
  });

  @override
  State<TransferConfirmPage> createState() => _TransferConfirmPageState();
}

class _TransferConfirmPageState extends State<TransferConfirmPage> {
  final TextEditingController _pinController = TextEditingController();
  bool _isProcessing = false;
  int? _lastTxId;
  final GlobalKey _receiptKey = GlobalKey();
  bool _transferSuccess = false;
  String _formattedAmount = '';
  DateTime? _transferTime;

  @override
  void dispose() {
    _pinController.dispose();
    super.dispose();
  }

  Future<void> _completeTransfer() async {
    final pin = _pinController.text.trim();
    if (!RegExp(r'^\d{6}$').hasMatch(pin)) {
      CustomToast.error(context, 'Masukkan PIN 6 digit');
      return;
    }

    setState(() => _isProcessing = true);

    try {
      final user = await EventPref.getUser();
      if (user == null) {
        CustomToast.error(context, 'Pengguna belum login');
        return;
      }

      final idPengirim = user.id ?? user.no_hp ?? '';
      if (idPengirim.isEmpty) {
        CustomToast.error(context, 'ID pengguna tidak tersedia');
        return;
      }

      final result = await EventDB.addTransfer(
        idPengirim,
        widget.phone,
        pin,
        widget.note.trim(),
        widget.amount.toString(),
      );

      final reason = (result['message'] ?? 'Transfer Gagal').toString();
      final transferSuccess = result['success'] == true;
      final idTransaksi = result['id_transaksi'] as int?;

      if (transferSuccess) {
        final formatted = NumberFormat.currency(
          locale: 'id_ID',
          symbol: 'Rp ',
          decimalDigits: 0,
        ).format(widget.amount);

        final recipientLabel = widget.recipientName != null && widget.recipientName!.isNotEmpty
            ? '${widget.recipientName} - ${widget.phone}'
            : widget.phone;

        try {
          final prefs = await SharedPreferences.getInstance();
          final existing = prefs.getString('transactions') ?? '[]';
          final list = jsonDecode(existing) as List;
          final id = idTransaksi ?? DateTime.now().millisecondsSinceEpoch;
          list.add({
            'id': id,
            'id_transaksi': idTransaksi ?? id,
            'id_pengguna': int.tryParse(idPengirim) ?? idPengirim,
            'type': 'transfer',
            'jenis_transaksi': 'transfer_keluar',
            'title': 'Kirim Uang',
            'direction': 'keluar',
            'to': widget.phone,
            'amount': widget.amount,
            'jumlah': widget.amount,
            'keterangan': 'Kirim Uang $formatted ke $recipientLabel Berhasil.',
            'status': 'approved',
            'created_at': DateTime.now().toIso8601String(),
          });
          await prefs.setString('transactions', jsonEncode(list));

          try {
            await EventDB.refreshSaldoForCurrentUser();
          } catch (_) {}

          setState(() {
            _lastTxId = id;
            _transferSuccess = true;
            _formattedAmount = formatted;
            _transferTime = DateTime.now();
          });

          WidgetsBinding.instance.addPostFrameCallback((_) {
            _showSuccessBottomSheet(formatted);
          });
        } catch (_) {}
      } else {
        CustomToast.error(context, reason);
      }
    } catch (e) {
      CustomToast.error(context, 'Gagal: $e');
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  Future<Uint8List?> _captureReceipt() async {
    try {
      RenderRepaintBoundary? boundary =
          _receiptKey.currentContext?.findRenderObject() as RenderRepaintBoundary?;
      if (boundary == null) {
        CustomToast.error(context, 'Gagal membuat gambar');
        return null;
      }
      final image = await boundary.toImage(pixelRatio: 3.0);
      final byteData = await image.toByteData(format: ImageByteFormat.png);
      if (byteData == null) return null;
      return byteData.buffer.asUint8List();
    } catch (e) {
      CustomToast.error(context, 'Gagal membuat gambar');
      return null;
    }
  }

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
      final imageBytes = await _captureReceipt();
      if (mounted) Navigator.pop(context);
      if (imageBytes == null) return;

      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/transfer_receipt_${DateTime.now().millisecondsSinceEpoch}.png');
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
      final imageBytes = await _captureReceipt();
      if (mounted) Navigator.pop(context);
      if (imageBytes == null) return;

      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/transfer_receipt_${DateTime.now().millisecondsSinceEpoch}.png');
      await file.writeAsBytes(imageBytes);
      await Share.shareXFiles([XFile(file.path)], subject: 'Transfer Berhasil');
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
            Text('Bagikan Transfer', style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w700)),
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

  void _showSuccessBottomSheet(String formatted) {
    showModalBottomSheet(
      context: context,
      isDismissible: true,
      enableDrag: true,
      backgroundColor: const Color(0xFFFF4C00),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 16, 24, 28),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.4),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 28),
              Container(
                width: double.infinity,
                color: const Color(0xFFFF4C00),
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFFD54F),
                        shape: BoxShape.circle,
                      ),
                      child: const Center(
                        child: Icon(Icons.check, size: 44, color: Colors.white),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text('Kirim Uang ke', style: GoogleFonts.roboto(fontSize: 14, color: Colors.white)),
                    const SizedBox(height: 4),
                    Text(
                      widget.recipientName ?? widget.phone,
                      style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      formatted,
                      style: GoogleFonts.poppins(fontSize: 32, fontWeight: FontWeight.w700, color: Colors.white),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Kamu bisa cek detailnya di Riwayat Transaksi',
                      style: GoogleFonts.roboto(fontSize: 12, color: Colors.white.withOpacity(0.85)),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 32),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        Navigator.pop(ctx);
                        Get.offAllNamed('/dashboard');
                      },
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(color: Colors.white.withOpacity(0.9)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: Text('TUTUP', style: GoogleFonts.roboto(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(ctx);
                        Get.offAllNamed('/dashboard');
                        if (_lastTxId != null) {
                          Get.toNamed('/riwayat_transaksi', arguments: {'open_id': _lastTxId});
                        } else {
                          Get.toNamed('/riwayat_transaksi');
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        elevation: 0,
                      ),
                      child: Text('CEK DETAIL', style: GoogleFonts.roboto(fontSize: 13, fontWeight: FontWeight.w600, color: const Color(0xFFFF4C00))),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    _showShareOptions();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 11),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    elevation: 0,
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.share, size: 18, color: Color(0xFFFF4C00)),
                      const SizedBox(width: 8),
                      Text('BAGIKAN', style: GoogleFonts.roboto(fontSize: 13, fontWeight: FontWeight.w600, color: const Color(0xFFFF4C00))),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final formatted = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(widget.amount);

    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        centerTitle: true,
        title: Text('Konfirmasi Kirim Uang', style: GoogleFonts.roboto(fontWeight: FontWeight.w700)),
      ),
      body: Stack(
        children: [
          // ✅ RepaintBoundary berisi tampilan RINCIAN TRANSAKSI (putih)
          // disembunyikan di luar layar, yang akan di-capture saat BAGIKAN
          if (_transferSuccess)
            Positioned(
              left: -9999,
              top: 0,
              child: RepaintBoundary(
                key: _receiptKey,
                child: Material(
                  color: Colors.white,
                  child: Container(
                    width: 400,
                    color: Colors.white,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Header nominal
                        Container(
                          width: double.infinity,
                          color: Colors.grey.shade50,
                          padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 16),
                          child: Column(
                            children: [
                              Text(
                                _formattedAmount,
                                style: GoogleFonts.poppins(
                                  fontSize: 36,
                                  fontWeight: FontWeight.w700,
                                  color: const Color(0xFFFF5F0A),
                                ),
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'Kirim Uang',
                                style: GoogleFonts.roboto(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w500,
                                  color: Colors.grey,
                                ),
                              ),
                            ],
                          ),
                        ),

                        // Status
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
                          color: Colors.green.withOpacity(0.1),
                          child: Row(
                            children: [
                              const Icon(Icons.check_circle, color: Colors.green, size: 24),
                              const SizedBox(width: 12),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('Status', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                                  const SizedBox(height: 4),
                                  Text('Berhasil', style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.green)),
                                ],
                              ),
                            ],
                          ),
                        ),

                        // Isi detail
                        Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Informasi Transaksi', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                              const SizedBox(height: 12),
                              Text('Penerima', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 6),
                              Text(widget.recipientName ?? widget.phone, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
                              const SizedBox(height: 12),
                              Text('No. HP', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 6),
                              Text(widget.phone, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
                              const SizedBox(height: 20),

                              Text('Detail', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                              const SizedBox(height: 12),
                              Text('Nominal', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 6),
                              Text(_formattedAmount, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
                              if (widget.note.isNotEmpty) ...[
                                const SizedBox(height: 12),
                                Text('Catatan', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                                const SizedBox(height: 6),
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: Colors.grey.shade100,
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(widget.note, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500)),
                                ),
                              ],
                              const SizedBox(height: 20),

                              Text('Waktu', style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700, color: const Color(0xFFFF5F0A))),
                              const SizedBox(height: 12),
                              Text('Tanggal', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 6),
                              Text(
                                DateFormat('dd MMM yyyy').format(_transferTime ?? DateTime.now()),
                                style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500),
                              ),
                              const SizedBox(height: 12),
                              Text('Waktu', style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                              const SizedBox(height: 6),
                              Text(
                                DateFormat('HH:mm').format(_transferTime ?? DateTime.now()),
                                style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w500),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),

          // Konten utama halaman konfirmasi
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Penerima', style: GoogleFonts.roboto(fontSize: 12, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 24,
                            backgroundColor: const Color(0xFFFFF3E9),
                            child: const Icon(Icons.person, color: Color(0xFFFF6A00)),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(widget.recipientName ?? widget.phone, style: GoogleFonts.roboto(fontWeight: FontWeight.w700)),
                                if (widget.recipientName != null) ...[
                                  const SizedBox(height: 4),
                                  Text(widget.phone, style: GoogleFonts.roboto(fontSize: 12, color: Colors.grey)),
                                ],
                              ],
                            ),
                          ),
                          if (widget.isFirstTransfer)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF3E9),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text('BARU', style: GoogleFonts.roboto(fontSize: 11, fontWeight: FontWeight.w700, color: const Color(0xFFFF6A00))),
                            ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),
                  Text('Jumlah', style: GoogleFonts.roboto(fontSize: 12, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Text(formatted, style: GoogleFonts.roboto(fontSize: 20, fontWeight: FontWeight.w700)),

                  const SizedBox(height: 12),
                  Text('Catatan', style: GoogleFonts.roboto(fontSize: 12, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Text(widget.note.isNotEmpty ? widget.note : '-', style: GoogleFonts.roboto(fontSize: 14)),

                  const SizedBox(height: 20),
                  Text('Masukkan PIN untuk konfirmasi', style: GoogleFonts.roboto(fontSize: 13)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _pinController,
                    keyboardType: TextInputType.number,
                    obscureText: true,
                    maxLength: 6,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      hintText: 'PIN 6 digit',
                    ),
                  ),

                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _isProcessing ? null : _completeTransfer,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFF4C00),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      child: _isProcessing
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                            )
                          : Text('KONFIRMASI', style: GoogleFonts.roboto(fontWeight: FontWeight.w700, color: Colors.white)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}