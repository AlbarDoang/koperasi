import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/orange_header.dart';

class PrivacyPolicyPage extends StatelessWidget {
  const PrivacyPolicyPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OrangeHeader(title: "Kebijakan Privasi"),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Kebijakan Privasi Aplikasi Koperasi',
              style: GoogleFonts.poppins(
                fontSize: 20,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Kebijakan privasi ini menjelaskan bagaimana kami mengumpulkan, '
              'menggunakan, dan melindungi data pribadi pengguna aplikasi koperasi ini.',
              style: GoogleFonts.poppins(fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 20),
            Text(
              '1. Pengumpulan Informasi',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'Kami mengumpulkan data yang diperlukan untuk keperluan identifikasi '
              'dan pelayanan, seperti nama, alamat email, dan nomor telepon.',
              style: GoogleFonts.poppins(fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 16),
            Text(
              '2. Penggunaan Data',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'Data Anda digunakan hanya untuk keperluan internal koperasi, '
              'seperti verifikasi akun dan peningkatan layanan.',
              style: GoogleFonts.poppins(fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 16),
            Text(
              '3. Keamanan Data',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'Kami menjaga keamanan data Anda menggunakan teknologi enkripsi '
              'dan kebijakan akses terbatas.',
              style: GoogleFonts.poppins(fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 16),
            Text(
              '4. Perubahan Kebijakan',
              style: GoogleFonts.poppins(
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'Kebijakan ini dapat diperbarui sewaktu-waktu. Perubahan akan '
              'diumumkan melalui aplikasi ini.',
              style: GoogleFonts.poppins(fontSize: 14, height: 1.6),
            ),
            const SizedBox(height: 32),
            Center(
              child: Text(
                '© 2025 PT. Gusti Global Group',
                style: GoogleFonts.poppins(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
