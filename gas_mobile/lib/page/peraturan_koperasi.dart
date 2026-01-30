// lib/page/peraturan_koperasi.dart
// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';

class PeraturanKoperasiPage extends StatelessWidget {
  const PeraturanKoperasiPage({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: OrangeHeader(
        title: "Peraturan & Kebijakan",
        onBackPressed: () => Navigator.of(context).maybePop(),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
          child: Column(
            children: [
              _policyCard(
                title: 'Syarat Menabung',
                content:
                    '1. Anggota aktif minimal terdaftar pada sistem koperasi.\n2. Setoran awal minimal sesuai kebijakan internal.\n3. Data identitas harus valid.',
                context: context,
              ),
              const SizedBox(height: 12),
              _policyCard(
                title: 'Aturan Penarikan',
                content:
                    '1. Penarikan dapat dilakukan secara berkala sesuai ketentuan.\n2. Proses penarikan membutuhkan verifikasi admin apabila jumlah di atas batas tertentu.\n3. Biaya administrasi berlaku sesuai tabel kebijakan internal.',
                context: context,
              ),
              const SizedBox(height: 12),
              _policyCard(
                title: 'Kebijakan Privasi Singkat',
                content:
                    'Data anggota disimpan aman dan hanya digunakan untuk operasional koperasi. Data tidak dibagikan ke pihak ketiga tanpa persetujuan anggota.',
                context: context,
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }

  Widget _policyCard({
    required String title,
    required String content,
    required BuildContext context,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(
              Theme.of(context).brightness == Brightness.dark ? 0.45 : 0.03,
            ),
            blurRadius: 8,
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: GoogleFonts.poppins(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Theme.of(context).textTheme.bodyLarge?.color,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            content,
            style: GoogleFonts.inter(
              fontSize: 14,
              color: Theme.of(context).textTheme.bodyMedium?.color,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }
}
