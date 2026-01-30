import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';

import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/utils/url_launcher_util.dart';

Future<void> bukaWhatsApp(String pesan) async {
  if (pesan.isEmpty) {
    final ctx = Get.context;
    if (ctx != null) {
      CustomToast.error(ctx, 'Pesan kosong');
    }
    return;
  }

  final ctx = Get.context;
  if (ctx != null) {
    await URLLauncherUtil.openWhatsApp(
      ctx,
      phoneNumber: '6287822451601',
      message: pesan,
    );
  }
}

class BantuanPage extends StatelessWidget {
  const BantuanPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF5F0A),
        elevation: 0,
        centerTitle: true,
        title: Text(
          'Bantuan',
          style: GoogleFonts.poppins(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w700,
          ),
        ),
        iconTheme: const IconThemeData(color: Colors.white),
        toolbarHeight: 56,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: ListView(
          children: [
            const Text(
              'Silakan hubungi admin jika butuh bantuan.',
              style: TextStyle(fontSize: 16),
            ),
            const SizedBox(height: 16),

            // FAQ Pengguna Aktif
            ExpansionTile(
              title: const Text('FAQ Pengguna Aktif'),
              children: const [
                ListTile(
                  title: Text('Bagaimana cara melihat saldo tabungan saya?'),
                  subtitle: Text(
                    'Anda dapat melihat saldo di halaman Dashboard.',
                  ),
                ),
                ListTile(
                  title: Text('Bagaimana cara mengubah PIN?'),
                  subtitle: Text(
                    'Pergi ke Pengaturan Akun dan pilih Ubah PIN.',
                  ),
                ),
                ListTile(
                  title: Text(
                    'Kenapa saldo saya belum bertambah setelah setor?',
                  ),
                  subtitle: Text(
                    'Pastikan Anda telah menyelesaikan proses setor.',
                  ),
                ),
                ListTile(
                  title: Text('Bagaimana cara menarik tabungan?'),
                  subtitle: Text(
                    'Gunakan fitur Tarik Tabungan di halaman utama.',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            // Hubungi Admin
            ListTile(
              leading: const Icon(Icons.message, color: Colors.green),
              title: const Text('Hubungi Admin'),
              onTap: () => bukaWhatsApp(
                'Halo Admin, saya ingin konsultasi mengenai tabungan.',
              ),
            ),
            const SizedBox(height: 16),

            // Panduan Penggunaan
            ListTile(
              leading: const Icon(Icons.menu_book_outlined),
              title: const Text('Panduan Penggunaan'),
              onTap: () {
                showModalBottomSheet(
                  context: context,
                  builder: (context) {
                    return Padding(
                      padding: const EdgeInsets.all(16.0),
                      child: ListView(
                        children: const [
                          Text(
                            'Panduan Penggunaan',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          SizedBox(height: 8),
                          Text('- Cara setor dan tarik tabungan.'),
                          Text('- Cara ubah profil.'),
                          Text('- Cara ganti PIN.'),
                          Text('- Tips logout aman.'),
                        ],
                      ),
                    );
                  },
                );
              },
            ),
            const SizedBox(height: 16),

            // Laporkan Masalah
            ListTile(
              leading: const Icon(Icons.report_problem, color: Colors.red),
              title: const Text('Laporkan Masalah'),
              onTap: () => bukaWhatsApp(
                'Halo Admin, saya menemukan masalah di aplikasi.',
              ),
            ),
            const SizedBox(height: 16),

            // Info Layanan Koperasi
            ExpansionTile(
              leading: const Icon(Icons.info_outline),
              title: const Text('Info Layanan Koperasi'),
              children: const [
                ListTile(
                  title: Text('Jam operasional: Senin–Jumat, 08.00–16.00'),
                ),
                ListTile(title: Text('Nomor hotline: 0811-1056-9900')),
                ListTile(title: Text('Email: koperasigas@gmail.com')),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
