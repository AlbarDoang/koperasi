import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

class SimulasiPinjamanDialog extends StatefulWidget {
  const SimulasiPinjamanDialog({Key? key}) : super(key: key);

  @override
  State<SimulasiPinjamanDialog> createState() => _SimulasiPinjamanDialogState();
}

class _SimulasiPinjamanDialogState extends State<SimulasiPinjamanDialog> {
  int _selectedAmount = 500000;
  int _selectedTenor = 12;
  // Syariah flat rule: equal monthly installments using floor division, no interest, remainder ignored

  String get _monthlyPayment {
    final int n = _selectedTenor;
    final int amount = _selectedAmount;
    // floor division to compute flat monthly installment
    final int base = (n > 0) ? (amount ~/ n) : 0;
    final monthlyInt = base;
    return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0).format(monthlyInt);
  }

  String get _totalPayment {
    final int n = _selectedTenor;
    final int amount = _selectedAmount;
    final int base = (n > 0) ? (amount ~/ n) : 0;
    final int total = base * n; // total payable = cicilan_per_bulan * tenor (remainder ignored)
    return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0).format(total);
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 16),
      child: SingleChildScrollView(
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header with close button
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Simulasi Pinjaman',
                      style: GoogleFonts.roboto(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => Navigator.pop(context),
                      child: const Icon(Icons.close),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),

              // Content
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Amount display
                    Text(
                      'Pilih Jumlah Pinjaman',
                      style: GoogleFonts.roboto(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Rp${_selectedAmount.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')}',
                      style: GoogleFonts.roboto(
                        fontSize: 24,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFFFF5F0A),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Slider for amount
                    Row(
                      children: [
                        Expanded(
                          child: Slider(
                            value: _selectedAmount.toDouble(),
                            min: 500000,
                            max: 100000000,
                            divisions: 199,
                            activeColor: const Color(0xFFFF5F0A),
                            onChanged: (value) {
                              setState(() => _selectedAmount = value.toInt());
                            },
                          ),
                        ),
                      ],
                    ),

                    // Min and max values
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          '500.000',
                          style: GoogleFonts.roboto(
                            fontSize: 12,
                            color: Colors.grey,
                          ),
                        ),
                        Text(
                          '100.000.000',
                          style: GoogleFonts.roboto(
                            fontSize: 12,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Tenor selection
                    Text(
                      'Pilih Tenor',
                      style: GoogleFonts.roboto(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [2, 3, 6, 9, 12].map((tenor) {
                        return GestureDetector(
                          onTap: () => setState(() => _selectedTenor = tenor),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 8,
                            ),
                            decoration: BoxDecoration(
                              color: _selectedTenor == tenor
                                  ? const Color(0xFFFF5F0A)
                                  : Colors.transparent,
                              border: Border.all(
                                color: _selectedTenor == tenor
                                    ? const Color(0xFFFF5F0A)
                                    : Colors.grey,
                              ),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '$tenor bulan',
                              style: GoogleFonts.roboto(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: _selectedTenor == tenor
                                    ? Colors.white
                                    : Colors.black,
                              ),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 24),

                    // Monthly payment display
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.grey[50],
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.grey[300]!),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Pembayaran Bulanan',
                                  style: GoogleFonts.roboto(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Syariah flat: cicilan sama setiap bulan, tanpa bunga; sisa (jika ada) diabaikan.',
                                  style: GoogleFonts.roboto(
                                    fontSize: 11,
                                    color: Colors.grey,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 20),
                          Flexible(
                            fit: FlexFit.loose,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(
                                  _monthlyPayment,
                                  style: GoogleFonts.roboto(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w700,
                                    color: const Color(0xFFFF5F0A),
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Total: ' + _totalPayment,
                                  style: GoogleFonts.roboto(
                                    fontSize: 12,
                                    color: Colors.grey,
                                  ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
