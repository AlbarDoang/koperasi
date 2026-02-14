import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'dart:convert';
import 'dart:typed_data';

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

      // Call API
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

        // Add a local notification for the sender (immediate feedback only).
        final recipientLabel = widget.recipientName != null && widget.recipientName!.isNotEmpty
            ? '${widget.recipientName} (${widget.phone})'
            : widget.phone;
        await NotifikasiHelper.addLocalNotification(
          type: 'transaksi',
          title: 'Kirim Uang',
          message: 'Kirim Uang $formatted ke $recipientLabel Berhasil.',
          data: {
            'jenis_transaksi': 'transfer_keluar',
            'amount': widget.amount,
            'status': 'approved',
            if (idTransaksi != null) 'id_transaksi': idTransaksi,
          },
        );

        // Persist a local transaction record so it appears in Riwayat Transaksi
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
          setState(() => _lastTxId = id);

          // Refresh local saldo/profile so Dashboard shows updated balance immediately
          try {
            await EventDB.refreshSaldoForCurrentUser();
          } catch (_) {}

          // Show success UI
          _showSuccessBottomSheet(formatted);
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

  void _showSuccessBottomSheet(String formatted) {
    showModalBottomSheet(
      context: context,
      isDismissible: true,
      enableDrag: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Container(
        color: const Color(0xFFFF4C00),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Drag handle
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.4),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 24),
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
              const SizedBox(height: 24),
              Text(
                'Kirim Uang ke',
                style: GoogleFonts.roboto(
                  fontSize: 13,
                  color: Colors.white.withOpacity(0.85),
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                widget.recipientName ?? widget.phone,
                style: GoogleFonts.roboto(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                formatted,
                style: GoogleFonts.roboto(
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'Kamu bisa cek detailnya di Riwayat Transaksi',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  color: Colors.white.withOpacity(0.8),
                  height: 1.4,
                  fontWeight: FontWeight.w400,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 28),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        // Close and go to dashboard
                        Navigator.pop(ctx);
                        Get.offAllNamed('/dashboard');
                      },
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(color: Colors.white.withOpacity(0.9)),
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: Text(
                        'TUTUP',
                        style: GoogleFonts.roboto(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(ctx);
                        // Navigate to Dashboard first (clear transfer flow), then open Riwayat on top
                        // so that pressing back from Riwayat returns to Dashboard
                        Get.offAllNamed('/dashboard');
                        if (_lastTxId != null) {
                          Get.toNamed(
                            '/riwayat_transaksi',
                            arguments: {'open_id': _lastTxId},
                          );
                        } else {
                          Get.toNamed('/riwayat_transaksi');
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 11),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                        elevation: 0,
                      ),
                      child: Text(
                        'CEK DETAIL',
                        style: GoogleFonts.roboto(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFFFF4C00),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    // Temporarily disable sharing to avoid platform plugin conflicts — show a friendly message
                    CustomToast.info(context, 'Fitur bagikan belum tersedia.');
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 11),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    elevation: 0,
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.share,
                        size: 18,
                        color: Color(0xFFFF4C00),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'BAGIKAN',
                        style: GoogleFonts.roboto(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFFFF4C00),
                        ),
                      ),
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
        title: Text(
          'Konfirmasi Kirim Uang',
          style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Penerima',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 24,
                        backgroundColor: const Color(0xFFFFF3E9),
                        child: const Icon(
                          Icons.person,
                          color: Color(0xFFFF6A00),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.recipientName ?? widget.phone,
                              style: GoogleFonts.roboto(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (widget.recipientName != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                widget.phone,
                                style: GoogleFonts.roboto(
                                  fontSize: 12,
                                  color: Colors.grey,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      if (widget.isFirstTransfer)
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF3E9),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            'BARU',
                            style: GoogleFonts.roboto(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: const Color(0xFFFF6A00),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 16),
              Text(
                'Jumlah',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                formatted,
                style: GoogleFonts.roboto(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                ),
              ),

              const SizedBox(height: 12),
              Text(
                'Catatan',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                widget.note.isNotEmpty ? widget.note : '-',
                style: GoogleFonts.roboto(fontSize: 14),
              ),

              const SizedBox(height: 20),
              Text(
                'Masukkan PIN untuk konfirmasi',
                style: GoogleFonts.roboto(fontSize: 13),
              ),
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
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: _isProcessing
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : Text(
                          'KONFIRMASI',
                          style: GoogleFonts.roboto(
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
