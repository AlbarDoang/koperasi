import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/ajukan_pk.dart';

class ReviewPkPage extends StatelessWidget {
  final String itemName;
  final double price;
  final double dp;
  final int tenor;
  final double principal;
  final double totalPayable;
  final double monthly;

  const ReviewPkPage({
    Key? key,
    required this.itemName,
    required this.price,
    required this.dp,
    required this.tenor,
    required this.principal,
    required this.totalPayable,
    required this.monthly,
  }) : super(key: key);

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: GoogleFonts.roboto(fontSize: 14, color: Colors.black54)),
          Text(value, style: GoogleFonts.roboto(fontSize: 14, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }

  String _formatCurrency(double v) {
    final intVal = v.round();
    final s = intVal.toString().replaceAllMapped(RegExp(r"\B(?=(\d{3})+(?!\d))"), (m) => '.');
    return 'Rp$s';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Lihat Pengajuan', style: GoogleFonts.roboto(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFFFF4C00),
        centerTitle: true,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Card(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Ringkasan Pengajuan', style: GoogleFonts.roboto(fontSize: 16, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 12),
                      _row('Nama Barang', itemName),
                      _row('Harga', _formatCurrency(price)),
                      _row('DP', _formatCurrency(dp)),
                      _row('Pokok', _formatCurrency(principal)),
                      _row('Tenor (bulan)', '$tenor bulan'),
                      _row('Total Bayar', _formatCurrency(totalPayable)),
                      _row('Per Bulan', _formatCurrency(monthly)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        // Pop back to edit the form
                        Navigator.pop(context);
                      },
                      child: Text('Edit', style: GoogleFonts.roboto(fontWeight: FontWeight.w700)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        // Proceed to upload page and pass details
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => AjukanPkPage(
                              itemName: itemName,
                              price: price,
                              dp: dp,
                              tenor: tenor,
                            ),
                          ),
                        );
                      },
                      style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFFF4C00)),
                      child: Text('Lanjutkan', style: GoogleFonts.roboto(fontWeight: FontWeight.w700, color: Colors.white)),
                    ),
                  ),
                ],
              )
            ],
          ),
        ),
      ),
    );
  }
}
