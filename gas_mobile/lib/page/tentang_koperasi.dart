// lib/page/tentang_koperasi.dart
// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';

class TentangKoperasiPage extends StatelessWidget {
  const TentangKoperasiPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9F9F9),
      appBar: OrangeHeader(
        title: "Tentang Aplikasi",
        onBackPressed: () => Navigator.of(context).maybePop(),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Card with about text
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black12.withOpacity(0.03),
                      blurRadius: 8,
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Gusti Artha Sejahtera (GAS)',
                      style: GoogleFonts.poppins(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Koperasi internal kantor yang didedikasikan untuk membantu karyawan dalam menabung dan mengelola keuangan. Kami menyediakan layanan tabungan, informasi saldo, dan penarikan sesuai kebijakan koperasi.',
                      style: GoogleFonts.inter(
                        fontSize: 14,
                        height: 1.5,
                        color: Colors.grey[800],
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Visi',
                      style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Menjadi koperasi terpercaya yang mendukung kesejahteraan karyawan melalui layanan finansial yang aman dan mudah.',
                      style: GoogleFonts.inter(
                        fontSize: 14,
                        color: Colors.grey[800],
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Misi',
                      style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 6),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _bullet(
                          'Memberikan layanan tabungan yang transparan dan mudah diakses.',
                        ),
                        _bullet('Meningkatkan literasi keuangan anggota.'),
                        _bullet('Mendukung program kesejahteraan karyawan.'),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 18),
              // LAYANAN
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black12.withOpacity(0.03),
                      blurRadius: 8,
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Layanan Koperasi',
                      style: GoogleFonts.poppins(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    _bullet('Tabungan Reguler'),
                    _bullet('Tabungan Pelajar'),
                    _bullet('Tabungan Lebaran'),
                    _bullet('Tabungan Qurban'),
                    _bullet('Tabungan Aqiqah'),
                    _bullet('Tabungan Umrah'),
                    _bullet('Tabungan Instalasi'),
                  ],
                ),
              ),

              const SizedBox(height: 18),

              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  Widget _bullet(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('• ', style: TextStyle(fontSize: 18)),
          Expanded(
            child: Text(
              text,
              style: GoogleFonts.inter(fontSize: 14, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}
