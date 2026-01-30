import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';

class SkGaspinjamPage extends StatelessWidget {
  const SkGaspinjamPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Get.back(),
        ),
        title: Text(
          'Syarat & Ketentuan GAS Pinjam',
          style: GoogleFonts.roboto(
            fontWeight: FontWeight.w700,
            color: Colors.white,
            fontSize: 16,
          ),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildSection(
              '1. Ketentuan Umum',
              'GAS Pinjam adalah layanan pinjaman digital yang disediakan oleh GAS Koperasi. Dengan menggunakan layanan ini, Anda setuju untuk mematuhi semua syarat dan ketentuan yang berlaku.',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '2. Persyaratan Pendaftar',
              '• Berusia minimal 21 tahun atau telah menikah\n'
                  '• Memiliki KTP atau identitas resmi lainnya\n'
                  '• Memiliki nomor telepon yang aktif\n'
                  '• Memiliki akun email yang valid\n'
                  '• Bersedia memberikan data pribadi yang akurat',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '3. Proses Permohonan',
              '• Pengajuan pinjaman dapat dilakukan melalui aplikasi GAS Pinjam\n'
                  '• Proses verifikasi dapat memakan waktu hingga 5 menit\n'
                  '• Persetujuan pinjaman tergantung pada kelayakan dan analisis risiko\n'
                  '• Dana akan ditransfer langsung ke rekening Anda setelah disetujui',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '4. Informasi Pembiayaan',
              '• Semua rincian terkait pembiayaan akan diinformasikan secara jelas sebelum Anda menyetujui transaksi.\n'
                  '• Jika terdapat ketentuan biaya, itu akan disebutkan ketika Anda memilih produk dan sebelum konfirmasi.',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '5. Tenor Pinjaman',
              '• Tenor pinjaman berkisar dari 2 hingga 24 bulan\n'
                  '• Tenor dapat dipilih sesuai dengan kemampuan dan kebutuhan Anda\n'
                  '• Cicilan dibayarkan setiap bulan dengan jumlah yang sama',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '6. Pembayaran Cicilan',
              '• Pembayaran dapat dilakukan melalui berbagai metode yang tersedia\n'
                  '• Pembayaran harus dilakukan tepat waktu sesuai dengan jadwal\n'
                  '• Denda keterlambatan akan dikenakan apabila pembayaran melewati batas waktu\n'
                  '• Pembayaran dapat dilakukan lebih awal tanpa penalti tambahan',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '7. Keamanan Data',
              '• Data pribadi Anda dijaga dengan keamanan tingkat tinggi\n'
                  '• Kami tidak akan membagikan data Anda kepada pihak ketiga tanpa persetujuan\n'
                  '• Semua transaksi dilindungi dengan enkripsi end-to-end\n'
                  '• Anda bertanggung jawab atas keamanan akun Anda',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '8. Hak dan Kewajiban Peminjam',
              'Hak Peminjam:\n'
                  '• Mendapatkan informasi lengkap tentang pinjaman Anda\n'
                  '• Mengajukan pertanyaan atau keluhan\n'
                  '• Melakukan pelunasan lebih awal\n\n'
                  'Kewajiban Peminjam:\n'
                  '• Membayar cicilan tepat waktu\n'
                  '• Memberikan informasi yang akurat dan jujur\n'
                  '• Menjaga keamanan data akun Anda\n'
                  '• Mematuhi semua peraturan yang berlaku',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '9. Pembatalan dan Pengembalian',
              '• Pinjaman dapat dibatalkan sebelum dana ditransfer\n'
                  '• Pengembalian dana akan dilakukan sesuai dengan metode pembayaran yang digunakan\n'
                  '• Pembatalan harus dilakukan dalam waktu 24 jam setelah persetujuan',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '10. Kebijakan Privasi',
              'Kami berkomitmen untuk melindungi privasi Anda. Data yang Anda berikan hanya akan digunakan untuk keperluan layanan pinjaman dan sesuai dengan peraturan yang berlaku. Anda dapat menghubungi kami jika memiliki pertanyaan tentang privasi data Anda.',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '11. Penghentian Layanan',
              'GAS Pinjam berhak untuk menghentikan layanan atau menutup akun Anda apabila:\n'
                  '• Anda melanggar syarat dan ketentuan yang berlaku\n'
                  '• Terdapat aktivitas mencurigakan atau penipuan\n'
                  '• Anda melakukan pembayaran yang terlambat secara berulang kali',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '12. Perubahan Syarat dan Ketentuan',
              'GAS Pinjam berhak untuk mengubah syarat dan ketentuan kapan saja. Perubahan akan diberitahukan melalui aplikasi atau email. Penggunaan layanan lebih lanjut berarti Anda menyetujui perubahan tersebut.',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '13. Penyelesaian Perselisihan',
              'Jika terdapat perselisihan, kedua belah pihak setuju untuk menyelesaikannya melalui musyawarah. Jika tidak dapat diselesaikan, perselisihan akan dibawa ke lembaga arbitrase yang berwenang.',
            ),
            const SizedBox(height: 16),
            _buildSection(
              '14. Hubungi Kami',
              'Jika Anda memiliki pertanyaan atau keluhan mengenai GAS Pinjam, silakan hubungi kami:\n\n'
                  'Email: koperasigas@gmail.com\n'
                  'WhatsApp: +62 819 9060 8817\n'
                  'Jam Operasional: Senin - Jumat, 08:00 - 16:00 WIB',
            ),
            const SizedBox(height: 24),
            Center(
              child: Text(
                '© 2025 PT. Gusti Global Group',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  color: Colors.grey,
                  fontStyle: FontStyle.italic,
                ),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildSection(String title, String content) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: GoogleFonts.roboto(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            color: const Color(0xFFFF4C00),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          content,
          style: GoogleFonts.roboto(
            fontSize: 13,
            color: Colors.black87,
            height: 1.6,
          ),
          textAlign: TextAlign.justify,
        ),
      ],
    );
  }
}
