import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class HalamanPenjelasanPromo extends StatelessWidget {
  const HalamanPenjelasanPromo({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Container(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header handle
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            // Title
            Text(
              'Penjelasan Promo Pengguna Baru',
              style: GoogleFonts.roboto(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: Colors.black87,
              ),
            ),
            const SizedBox(height: 16),
            // Main description
            _buildInfoRow(
              icon: Icons.info,
              title: 'Apa itu promo ini?',
              description:
                  'Promo memberikan potongan biaya layanan sebesar Rp 80.000.',
            ),
            const SizedBox(height: 12),
            _buildInfoRow(
              icon: Icons.savings,
              title: 'Cara kerja diskon',
              description:
                  'Promo tidak mengurangi pokok pinjaman, hanya mengurangi biaya layanan.',
            ),
            const SizedBox(height: 12),
            _buildInfoRow(
              icon: Icons.calendar_today,
              title: 'Berlaku untuk',
              description: 'Tenor 2, 3, dan 6 bulan.',
            ),
            const SizedBox(height: 12),
            _buildInfoRow(
              icon: Icons.new_releases,
              title: 'Syarat khusus',
              description: 'Eksklusif untuk pengguna baru.',
            ),
            const SizedBox(height: 20),
            // Example section
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.orange.withOpacity(0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: Colors.orange.withOpacity(0.2),
                  width: 1,
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Contoh Perhitungan',
                    style: GoogleFonts.roboto(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Colors.orange,
                    ),
                  ),
                  const SizedBox(height: 10),
                  _buildExampleLine('Jika meminjam', 'Rp 1.000.000'),
                  const SizedBox(height: 6),
                  _buildExampleLine('Dengan tenor', '3 bulan'),
                  const SizedBox(height: 6),
                  _buildExampleLine('Diskon biaya', 'Rp 80.000'),
                  const SizedBox(height: 6),
                  Container(
                    height: 1,
                    color: Colors.orange.withOpacity(0.2),
                    margin: const EdgeInsets.symmetric(vertical: 8),
                  ),
                  _buildExampleLine(
                    'Pokok pinjaman',
                    'Rp 1.000.000',
                    isBold: true,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '(Tetap sama, tidak berkurang)',
                    style: GoogleFonts.roboto(
                      fontSize: 11,
                      color: Colors.black54,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            // Footer note
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue.withOpacity(0.08),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.check_circle_outline,
                    color: Colors.blue,
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Promo ini hanya berlaku sekali untuk pengguna baru. Nikmati kesempatan terbaik Anda sekarang!',
                      style: GoogleFonts.roboto(
                        fontSize: 12,
                        color: Colors.black87,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow({
    required IconData icon,
    required String title,
    required String description,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.orange.withOpacity(0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(icon, color: Colors.orange, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.roboto(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Colors.black87,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                description,
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  color: Colors.black54,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildExampleLine(String label, String value, {bool isBold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.roboto(
            fontSize: 12,
            color: Colors.black87,
            fontWeight: isBold ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
        Text(
          value,
          style: GoogleFonts.roboto(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: Colors.orange,
          ),
        ),
      ],
    );
  }
}
