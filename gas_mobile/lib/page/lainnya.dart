// lib/page/lainnya.dart
// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/page/tentang_koperasi.dart';
import 'package:tabungan/page/peraturan_koperasi.dart';

class LainnyaPage extends StatelessWidget {
  const LainnyaPage({super.key});

  final Color primaryOrange = const Color(0xFFFF5F0A);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF9F9F9),
      appBar: OrangeHeader(
        title: "Lainnya",
        onBackPressed: () => Navigator.of(context).maybePop(),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 16),
          child: Column(
            children: [
              // Card container
              Container(
                width: double.infinity,
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
                  children: [
                    _buildMenuTile(
                      context,
                      icon: Icons.info_outline,
                      title: 'Tentang Koperasi',
                      subtitle: 'Visi, misi dan profil singkat',
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const TentangKoperasiPage(),
                        ),
                      ),
                    ),
                    _divider(),
                    _buildMenuTile(
                      context,
                      icon: Icons.rule_folder,
                      title: 'Peraturan & Kebijakan',
                      subtitle: 'Syarat, aturan penarikan, kebijakan privasi',
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const PeraturanKoperasiPage(),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  Widget _divider() => const Divider(height: 0, thickness: 1);

  Widget _buildMenuTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      leading: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.orange.shade50,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(icon, color: Colors.orange, size: 22),
      ),
      title: Text(
        title,
        style: GoogleFonts.poppins(fontSize: 15, fontWeight: FontWeight.w600),
      ),
      subtitle: subtitle == null
          ? null
          : Text(
              subtitle,
              style: GoogleFonts.inter(fontSize: 13, color: Colors.grey[700]),
            ),
      trailing: const Icon(Icons.navigate_next),
      onTap: onTap,
    );
  }
}
