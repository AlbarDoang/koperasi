import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:tabungan/page/orange_header.dart';

class TransferEwalletDetailPage extends StatefulWidget {
  final String ewalletName;
  const TransferEwalletDetailPage({super.key, required this.ewalletName});

  @override
  State<TransferEwalletDetailPage> createState() =>
      _TransferEwalletDetailPageState();
}

class _TransferEwalletDetailPageState extends State<TransferEwalletDetailPage> {
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _amountController = TextEditingController();
  final TextEditingController _noteController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _amountController.addListener(_formatCurrency);
  }

  @override
  void dispose() {
    _phoneController.dispose();
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
      appBar: OrangeHeader(title: 'Transfer ke ${widget.ewalletName}'),
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
                          widget.ewalletName.length <= 3
                              ? widget.ewalletName
                              : widget.ewalletName
                                    .substring(0, 3)
                                    .toUpperCase(),
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
                              widget.ewalletName,
                              style: GoogleFonts.roboto(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Kamu akan transfer ke ${widget.ewalletName}',
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
                'NO. TELEPON / EMAIL',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  hintText: 'Masukkan nomor telepon atau email',
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
                'Cek lagi nomor/email penerima dan nominal transfer sudah benar.',
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
            onPressed: amountVal > 0 && _phoneController.text.isNotEmpty
                ? () {
                    // E-wallet transfer flow is not yet supported in-app; show informative notice
                    Get.snackbar(
                      'Info',
                      'Metode transfer ini belum tersedia',
                      backgroundColor: Colors.orange.shade700,
                      colorText: Colors.white,
                      snackPosition: SnackPosition.TOP,
                      margin: const EdgeInsets.all(16),
                    );
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
