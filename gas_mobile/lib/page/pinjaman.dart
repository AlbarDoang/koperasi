import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/pinjaman_biasa.dart';
import 'package:tabungan/page/pinjaman_kredit.dart';

class PinjamanPage extends StatefulWidget {
  const PinjamanPage({Key? key}) : super(key: key);

  @override
  State<PinjamanPage> createState() => _PinjamanPageState();
}

class _PinjamanPageState extends State<PinjamanPage> {
  bool showPinjamanBiasa = true;

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
          'GAS Pinjam',
          style: GoogleFonts.roboto(
            fontWeight: FontWeight.w700,
            color: Colors.white,
            fontSize: 20,
          ),
        ),
      ),
      body: Column(
        children: [
          // Custom Tab Switcher
          Container(
            margin: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFFF4C00),
              borderRadius: BorderRadius.circular(24),
            ),
            padding: const EdgeInsets.all(4),
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    onTap: () => setState(() => showPinjamanBiasa = true),
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        color: showPinjamanBiasa
                            ? Colors.white
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        'Pinjaman Biasa',
                        style: GoogleFonts.roboto(
                          fontWeight: FontWeight.w700,
                          color: showPinjamanBiasa
                              ? const Color(0xFFFF4C00)
                              : Colors.white,
                        ),
                      ),
                    ),
                  ),
                ),
                Container(width: 1, height: 36, color: Colors.white24),
                Expanded(
                  child: InkWell(
                    onTap: () => setState(() => showPinjamanBiasa = false),
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        color: !showPinjamanBiasa
                            ? Colors.white
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        'Pinjaman Kredit',
                        style: GoogleFonts.roboto(
                          fontWeight: FontWeight.w700,
                          color: !showPinjamanBiasa
                              ? const Color(0xFFFF4C00)
                              : Colors.white,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          // Content
          Expanded(
            child: showPinjamanBiasa
                ? PinjamanBiasaPage()
                : PinjamanKreditPage(),
          ),
        ],
      ),
    );
  }
}
