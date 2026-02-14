import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/utils/url_launcher_util.dart';

// Method untuk cek status layanan
bool _isServiceOpen() {
  final now = DateTime.now();
  final hour = now.hour;
  return hour >= 8 && hour < 16;
}

// Method untuk widget status layanan
Widget _buildStatusCard() {
  final isOpen = _isServiceOpen();
  final now = DateTime.now();
  final cardColor = isOpen ? Colors.green[50] : Colors.red[50];
  final iconColor = isOpen ? Colors.green : Colors.red;
  final statusText = isOpen ? '🟢 Online sekarang' : '🔴 Sedang Offline';
  final descText = isOpen
      ? 'Jam operasional: 08.00 – 16.00\nEstimasi respon: 1–5 menit'
      : 'Buka kembali besok pukul 08.00';
  return Card(
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    color: cardColor,
    elevation: 0.5,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 16,
                height: 16,
                decoration: BoxDecoration(
                  color: iconColor,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 8),
              Text(
                'Status Layanan',
                style: GoogleFonts.poppins(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: Colors.black87,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            statusText,
            style: GoogleFonts.poppins(
              fontSize: 15,
              fontWeight: FontWeight.w500,
              color: iconColor,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            descText,
            style: GoogleFonts.poppins(
              fontSize: 13,
              color: Colors.black54,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Layanan buka SETIAP HARI (Senin–Minggu)',
            style: TextStyle(fontSize: 12, color: Colors.black45),
          ),
        ],
      ),
    ),
  );
}

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
      phoneNumber: '6281110569900',
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
            // Card Status Layanan
            _buildStatusCard(),
            const SizedBox(height: 20),

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
                ListTile(title: Text('Nomor Admin: 0811-1056-9900')),
                ListTile(title: Text('Email: gustiarthasejahtera@gmail.com')),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
