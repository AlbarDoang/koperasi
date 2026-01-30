import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import 'package:tabungan/controller/notifikasi_helper.dart';

import 'package:tabungan/utils/custom_toast.dart';

class IsiKuotaPage extends StatefulWidget {
  const IsiKuotaPage({Key? key}) : super(key: key);

  @override
  State<IsiKuotaPage> createState() => _IsiKuotaPageState();
}

class _IsiKuotaPageState extends State<IsiKuotaPage> {
  final TextEditingController _phoneCtrl = TextEditingController();
  String operatorName = 'Pilih Operator';
  List<Map<String, dynamic>> kuotaPackages = [];
  int? selectedIndex;

  @override
  void initState() {
    super.initState();
    _initData();
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    super.dispose();
  }

  void _initData() {
    kuotaPackages = [
      {
        'label': 'Rp 15.000',
        'price': 15000,
        'quota': '1.5 GB',
        'validity': '7 hari',
      },
      {
        'label': 'Rp 30.000',
        'price': 30000,
        'quota': '3 GB',
        'validity': '15 hari',
      },
      {
        'label': 'Rp 50.000',
        'price': 50000,
        'quota': '6 GB',
        'validity': '30 hari',
      },
      {
        'label': 'Rp 75.000',
        'price': 75000,
        'quota': '12 GB',
        'validity': '30 hari',
      },
      {
        'label': 'Rp 120.000',
        'price': 120000,
        'quota': '20 GB',
        'validity': '30 hari',
      },
      {
        'label': 'Rp 150.000',
        'price': 150000,
        'quota': '30 GB',
        'validity': '30 hari',
      },
    ];
  }

  void _pickOperator() async {
    final sel = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: const Text('Telkomsel'),
              onTap: () => Navigator.of(c).pop('Telkomsel'),
            ),
            ListTile(
              title: const Text('XL Axiata'),
              onTap: () => Navigator.of(c).pop('XL Axiata'),
            ),
            ListTile(
              title: const Text('Indosat'),
              onTap: () => Navigator.of(c).pop('Indosat'),
            ),
            ListTile(
              title: const Text('Smartfren'),
              onTap: () => Navigator.of(c).pop('Smartfren'),
            ),
          ],
        ),
      ),
    );
    if (sel != null) setState(() => operatorName = sel);
  }

  void _confirmBuy(Map<String, dynamic> pkg) {
    final phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) {
      CustomToast.error(context, 'Silakan masukkan nomor HP terlebih dahulu');
      return;
    }
    if (phone.length < 10) {
      CustomToast.error(context, 'Nomor HP harus minimal 10 digit');
      return;
    }

    Get.dialog(
      AlertDialog(
        title: Text(
          'Konfirmasi Pembelian Kuota',
          style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Operator: $operatorName',
              style: GoogleFonts.roboto(fontSize: 12),
            ),
            const SizedBox(height: 8),
            Text('Nomor HP: $phone', style: GoogleFonts.roboto(fontSize: 12)),
            const SizedBox(height: 12),
            Text(
              'Paket: ${pkg['label']}',
              style: GoogleFonts.roboto(
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Kuota: ${pkg['quota']} • Berlaku ${pkg['validity']}',
              style: GoogleFonts.roboto(fontSize: 11, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            Text(
              'Harga: Rp ${pkg['price']}',
              style: GoogleFonts.roboto(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: const Color(0xFFFF4C00),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Get.back(), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFF4C00),
            ),
            onPressed: () async {
              Get.back();

              final prefs = await SharedPreferences.getInstance();
              final txnList = prefs.getString('transactions') ?? '[]';
              final list = jsonDecode(txnList) as List;

              list.add({
                'id': DateTime.now().millisecondsSinceEpoch,
                'type': 'kuota',
                'operator': operatorName,
                'phone': phone,
                'label': pkg['label'],
                'price': pkg['price'],
                'created_at': DateTime.now().toIso8601String(),
              });

              await prefs.setString('transactions', jsonEncode(list));

              await NotifikasiHelper.addNotification(
                type: 'transaksi',
                title: 'Pembelian Kuota Berhasil',
                message:
                    'Kuota $operatorName senilai Rp ${pkg['price']} ke $phone telah berhasil diproses',
              );

              CustomToast.success(context, 'Kuota berhasil dibeli');
            },
            child: const Text('Beli', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F6F6),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Get.back(),
        ),
        title: Text(
          'Isi Kuota',
          style: GoogleFonts.roboto(
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
        actions: const [
          Padding(
            padding: EdgeInsets.only(right: 12),
            child: Icon(
              Icons.cloud_download_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // Input section
          Container(
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Operator picker
                Row(
                  children: [
                    const Icon(Icons.sim_card, size: 18, color: Colors.grey),
                    const SizedBox(width: 8),
                    Text(
                      'Pilih Operator',
                      style: GoogleFonts.roboto(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                GestureDetector(
                  onTap: _pickOperator,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 12,
                    ),
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(12),
                      color: Colors.grey.shade50,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          operatorName,
                          style: GoogleFonts.roboto(
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const Icon(
                          Icons.keyboard_arrow_down,
                          color: Colors.grey,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Phone number input
                Row(
                  children: [
                    const Icon(Icons.phone, size: 18, color: Colors.grey),
                    const SizedBox(width: 8),
                    Text(
                      'Nomor HP',
                      style: GoogleFonts.roboto(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _phoneCtrl,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    hintText: 'Masukkan nomor HP',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Paket list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: kuotaPackages.length,
              itemBuilder: (context, index) {
                final paket = kuotaPackages[index];
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
                          onChanged: (val) =>
                              setState(() => selectedIndex = val),
                          activeColor: const Color(0xFFFF4C00),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                paket['label'],
                                style: GoogleFonts.roboto(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Kuota: ${paket['quota']} • Berlaku ${paket['validity']}',
                                style: GoogleFonts.roboto(
                                  fontSize: 11,
                                  color: Colors.grey,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              'Rp ${paket['price']}',
                              style: GoogleFonts.roboto(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: const Color(0xFFFF4C00),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          // Buy button
          Container(
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFF4C00),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onPressed: selectedIndex != null
                    ? () => _confirmBuy(kuotaPackages[selectedIndex!])
                    : null,
                child: Text(
                  'Beli Kuota',
                  style: GoogleFonts.roboto(
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                    fontSize: 16,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
