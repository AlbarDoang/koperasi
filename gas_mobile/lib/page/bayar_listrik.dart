import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'dart:convert';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';

import 'package:tabungan/utils/custom_toast.dart';

class BayarListrikPage extends StatefulWidget {
  const BayarListrikPage({Key? key}) : super(key: key);

  @override
  State<BayarListrikPage> createState() => _BayarListrikPageState();
}   

class _BayarListrikPageState extends State<BayarListrikPage> {
  final List<Map<String, dynamic>> paketListrik = [
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
    {'nominal': 20000, 'price': 18000, 'label': 'Pulsa Listrik'},
  ];

  int? selectedIndex;
  String _mode = 'Token';
  final TextEditingController _idController = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Get.back(),
        ),
        title: Text(
          'Isi Listrik',
          style: GoogleFonts.roboto(
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: Image.asset('assets/logo.png', height: 36),
          ),
        ],
      ),
      body: Column(
        children: [
          // Header section with ID input
          Container(
            padding: const EdgeInsets.all(16),
            color: theme.cardColor,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.info_outline,
                      size: 18,
                      color: Colors.grey,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'ID Pelanggan',
                      style: GoogleFonts.roboto(
                        fontSize: 12,
                        color: theme.textTheme.bodySmall?.color ?? Colors.grey,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _idController,
                  decoration: InputDecoration(
                    hintText: 'Masukkan ID pelanggan',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() => _mode = 'Token'),
                        child: _tokenButton(
                          'Token',
                          Icons.electrical_services,
                          selected: _mode == 'Token',
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() => _mode = 'Pascabayar'),
                        child: _tokenButton(
                          'Pascabayar',
                          Icons.receipt,
                          selected: _mode == 'Pascabayar',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          // Paket list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: paketListrik.length,
              itemBuilder: (context, index) {
                final paket = paketListrik[index];
                final isSelected = selectedIndex == index;
                return GestureDetector(
                  onTap: () => setState(() => selectedIndex = index),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isSelected
                            ? const Color(0xFFFF4C00)
                            : Colors.grey.shade300,
                        width: isSelected ? 2 : 1,
                      ),
                    ),
                    child: Row(
                      children: [
                        Radio(
                          value: index,
                          groupValue: selectedIndex,
                          onChanged: (value) =>
                              setState(() => selectedIndex = value),
                          activeColor: const Color(0xFFFF4C00),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFF4C00),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  'MURAH!!!',
                                  style: GoogleFonts.roboto(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Rp${paket['nominal'].toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (match) => '${match[1]}.')}',
                                style: GoogleFonts.roboto(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.black,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Rp${paket['price'].toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (match) => '${match[1]}.')}',
                                style: GoogleFonts.roboto(
                                  fontSize: 13,
                                  color: Colors.grey,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          // Bottom button
          Container(
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: selectedIndex != null
                    ? () async {
                        final paket = paketListrik[selectedIndex!];
                        await _confirmAndSave(paket);
                      }
                    : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFF4C00),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  disabledBackgroundColor: Colors.grey,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: Text(
                  'Lanjut',
                  style: GoogleFonts.roboto(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _tokenButton(String label, IconData icon, {required bool selected}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: Border.all(
          color: selected ? const Color(0xFFFF4C00) : Colors.grey.shade300,
        ),
        borderRadius: BorderRadius.circular(8),
        color: selected ? const Color(0xFFFFF3EB) : Colors.white,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 18, color: const Color(0xFFFF4C00)),
          const SizedBox(width: 6),
          Text(
            label,
            style: GoogleFonts.roboto(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Colors.black,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _confirmAndSave(Map<String, dynamic> paket) async {
    final idPel = _idController.text.trim();
    if (idPel.isEmpty) {
      CustomToast.error(context, 'Masukkan ID pelanggan terlebih dahulu');
      return;
    }

    final confirm = await Get.dialog<bool>(
      AlertDialog(
        title: Text(
          'Konfirmasi Pembayaran',
          style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
        ),
        content: Text(
          'ID: $idPel\nPaket: Rp${paket['nominal']} (Harga: Rp${paket['price']})\nMode: $_mode',
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Get.back(result: true),
            child: const Text('Bayar'),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    final txn = {
      'id': DateTime.now().millisecondsSinceEpoch,
      'type': 'listrik',
      'mode': _mode,
      'id_pelanggan': idPel,
      'nominal': paket['nominal'],
      'price': paket['price'],
      'has_ktp': false,
      'created_at': DateTime.now().toIso8601String(),
    };

    if (_mode == 'Token') {
      // dummy token generation
      txn['token'] = List.generate(20, (i) => ((i * 7) % 10).toString()).join();
    }

    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString('transactions');
    List list = existing != null ? jsonDecode(existing) : [];
    list.add(txn);
    await prefs.setString('transactions', jsonEncode(list));

    final amount = txn['nominal'] ?? 0;
    final merek = txn['merek'] ?? 'PLN';

    if (_mode == 'Token') {
      await NotifikasiHelper.addNotification(
        type: 'transaksi',
        title: 'Pembelian Token Listrik Berhasil',
        message:
            'Token listrik $merek senilai Rp $amount telah berhasil diproses',
      );
    } else {
      await NotifikasiHelper.addNotification(
        type: 'transaksi',
        title: 'Pembayaran Listrik Berhasil',
        message:
            'Pembayaran listrik $merek senilai Rp $amount telah berhasil diproses',
      );
    }

    if (_mode == 'Token') {
      await Get.dialog(
        AlertDialog(
          title: const Text('Token Berhasil Diterima'),
          content: Text('Token: ${txn['token']}'),
          actions: [
            TextButton(onPressed: () => Get.back(), child: const Text('Tutup')),
          ],
        ),
      );
    } else {
      CustomToast.success(context, 'Pembayaran Pascabayar berhasil (dummy)');
    }
  }
}
