import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/transfer_confirm.dart';

class TransferBankDetailPage extends StatefulWidget {
  final String bankName;
  const TransferBankDetailPage({super.key, required this.bankName});

  @override
  State<TransferBankDetailPage> createState() => _TransferBankDetailPageState();
}

class _TransferBankDetailPageState extends State<TransferBankDetailPage> {
  final TextEditingController _accountController = TextEditingController();
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _noteController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _amountController.addListener(_formatCurrency);
  }

  @override
  void dispose() {
    _accountController.dispose();
    _amountController.removeListener(_formatCurrency);
    _amountController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  void _formatCurrency() {
    final raw = _amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (raw.isEmpty) {
      if (_amountController.text.isNotEmpty) {
        _amountController.clear();
      }
      if (mounted) setState(() {});
      return;
    }
    final val = int.tryParse(raw) ?? 0;
    final formatted = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(val);
    if (_amountController.text != formatted) {
      _amountController.value = _amountController.value.copyWith(
        text: formatted,
        selection: TextSelection.collapsed(offset: formatted.length),
      );
    }
    if (mounted) setState(() {});
  }

  int _amountValue() {
    final cleaned = _amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
    return int.tryParse(cleaned) ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final amountVal = _amountValue();

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: OrangeHeader(title: 'Transfer ke ${widget.bankName}'),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Card(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 28,
                        backgroundColor: const Color(0xFFFFF3E9),
                        child: Text(
                          widget.bankName.length <= 3
                              ? widget.bankName
                              : widget.bankName.substring(0, 3).toUpperCase(),
                          style: GoogleFonts.roboto(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFFFF6A00),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              widget.bankName,
                              style: GoogleFonts.roboto(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Kamu akan transfer ke rekening bank ini',
                              style: GoogleFonts.roboto(
                                fontSize: 12,
                                color: theme.textTheme.bodySmall?.color,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),
              Text(
                'NO. REKENING',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _accountController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  hintText: 'Masukkan nomor rekening',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),

              const SizedBox(height: 18),
              Text(
                'JUMLAH TRANSFER',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  hintText: '0',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  suffixIcon: _amountController.text.isNotEmpty
                      ? IconButton(
                          onPressed: () =>
                              setState(() => _amountController.clear()),
                          icon: const Icon(Icons.clear),
                        )
                      : null,
                ),
              ),

              const SizedBox(height: 8),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [10000, 50000, 100000, 500000].map((val) {
                    final formatted = NumberFormat.currency(
                      locale: 'id_ID',
                      symbol: 'Rp ',
                      decimalDigits: 0,
                    ).format(val);
                    return Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: OutlinedButton(
                        onPressed: () =>
                            setState(() => _amountController.text = formatted),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: Color(0xFFFFC9B8)),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: Text(
                          formatted,
                          style: GoogleFonts.roboto(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),

              const SizedBox(height: 12),
              TextFormField(
                controller: _noteController,
                decoration: InputDecoration(
                  hintText: 'Tulis catatan (opsional)',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  prefixIcon: const Icon(Icons.note_outlined),
                ),
                maxLines: 2,
              ),

              const SizedBox(height: 24),
              Text(
                'Cek lagi nomor rekening dan nominal transfer sudah benar.',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  color: theme.textTheme.bodySmall?.color,
                ),
              ),
              const SizedBox(height: 64),
            ],
          ),
        ),
      ),
      resizeToAvoidBottomInset: false,
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        child: SizedBox(
          height: 56,
          child: ElevatedButton(
            onPressed: amountVal > 0 && _accountController.text.isNotEmpty
                ? () {
                    // Bank transfer flow is not yet supported in-app; show informative notice
                    NotificationService.showInfo('Metode transfer ini belum tersedia');
                  }
                : null,
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFF4C00),
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            child: Text(
              'LANJUT',
              style: GoogleFonts.roboto(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
