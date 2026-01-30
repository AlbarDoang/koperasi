import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/page/simulasi_pinjaman.dart';
import 'package:tabungan/page/sk_gaspinjam.dart';
import 'package:tabungan/page/HalamanPenjelasanPromo.dart';

class PinjamanBiasaPage extends StatefulWidget {
  const PinjamanBiasaPage({Key? key}) : super(key: key);

  @override
  State<PinjamanBiasaPage> createState() => _PinjamanBiasaPageState();
}

class _PinjamanBiasaPageState extends State<PinjamanBiasaPage> {
  bool acceptedTerms = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F6F6),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildTopCard(),
          const SizedBox(height: 12),
          _buildFeatureCards(),
          const SizedBox(height: 12),
          _buildPromoCard(),
          const SizedBox(height: 12),
          _buildWhyChooseCard(),
          const SizedBox(height: 12),
          _buildFooterInfo(),
          const SizedBox(height: 16),
          _buildAgreementCheck(),
          const SizedBox(height: 18),
          _buildContinueButton(),
        ],
      ),
    );
  }

  Widget _buildTopCard() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Container(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Cairkan dana tunai hingga',
              style: GoogleFonts.roboto(fontSize: 14, color: Colors.black54),
            ),
            const SizedBox(height: 8),
            Text(
              'Rp50.000.000',
              style: GoogleFonts.roboto(
                fontSize: 28,
                color: Colors.green,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            GestureDetector(
              onTap: () {
                showDialog(
                  context: context,
                  builder: (context) => const SimulasiPinjamanDialog(),
                );
              },
              child: Row(
                children: [
                  const Icon(
                    Icons.calculate_outlined,
                    size: 18,
                    color: Colors.grey,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Cek Simulasinya di sini',
                    style: GoogleFonts.roboto(color: Colors.grey[700]),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '>',
                    style: GoogleFonts.roboto(
                      color: Colors.grey[700],
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Container(
              margin: const EdgeInsets.only(top: 4),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.warning_amber_outlined,
                    color: Colors.orange,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Terus transaksi pakai GAS buat tingkatkan limitmu s.d 100 juta!',
                      style: GoogleFonts.roboto(fontSize: 11),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFeatureCards() {
    return Row(
      children: [
        Expanded(
          child: _featureCard('Informasi Pembiayaan', '-', Icons.pie_chart),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _featureCard('Fleksibel', '2-12\nbulan tenor', Icons.schedule),
        ),
      ],
    );
  }

  Widget _featureCard(String title, String subtitle, IconData icon) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 2,
      child: Container(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(8),
                    color: Colors.orange.withOpacity(0.1),
                  ),
                  child: Icon(icon, color: Colors.orange),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              subtitle,
              style: GoogleFonts.roboto(color: Colors.black54, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPromoCard() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '10 Ribu Diskon SUPER untuk pengguna baru !!!',
                    style: GoogleFonts.roboto(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Diskon biaya cicilan Rp.80.000 berlaku untuk tenor 2,3,6 bulan',
                    style: GoogleFonts.roboto(
                      fontSize: 12,
                      color: Colors.black54,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: () => _showPromoExplanation(context),
              child: Container(
                alignment: Alignment.center,
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 6),
                child: const Icon(
                  Icons.info_outline,
                  color: Colors.grey,
                  size: 22,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showPromoExplanation(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => const HalamanPenjelasanPromo(),
    );
  }

  Widget _buildWhyChooseCard() {
    return Card(
      clipBehavior: Clip.hardEdge,
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Kenapa pilih GAS Pinjam?',
                style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(height: 12),
            _whyRow(Icons.money_off, 'Pembayaran minim', 'Rp50.000'),
            const SizedBox(height: 8),
            _whyRow(
              Icons.privacy_tip,
              'Privasi kamu, prioritas kami',
              'Kerahasiaan data itu penting',
            ),
            const SizedBox(height: 8),
            _whyRow(
              Icons.flash_on,
              'Aktivasi cepat, cuma pake e-KTP',
              'Selesai 5 menit',
            ),
            const SizedBox(height: 8),
            _whyRow(
              Icons.help,
              'Gak ada biaya tersembunyi',
              'Semua biaya transparan',
            ),
          ],
        ),
      ),
    );
  }

  Widget _whyRow(IconData icon, String title, String subtitle) {
    return Row(
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
                style: GoogleFonts.roboto(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: GoogleFonts.roboto(fontSize: 12, color: Colors.black54),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildFooterInfo() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            const Icon(Icons.info_outline, color: Colors.grey),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'GAS Pinjam perlu mengakses nama, email dan nomor telepon kamu',
                style: GoogleFonts.roboto(fontSize: 12, color: Colors.black54),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAgreementCheck() {
    return Row(
      children: [
        Checkbox(
          value: acceptedTerms,
          onChanged: (v) {
            setState(() {
              acceptedTerms = v ?? false;
            });
          },
        ),
        Expanded(
          child: Wrap(
            children: [
              Text(
                'Saya telah membaca dan menyetujui ',
                style: GoogleFonts.roboto(fontSize: 12),
              ),
              GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const SkGaspinjamPage(),
                    ),
                  );
                },
                child: Text(
                  'Syarat & Ketentuan GAS Pinjam',
                  style: GoogleFonts.roboto(
                    fontSize: 12,
                    color: Colors.orange,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildContinueButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: acceptedTerms
            ? () {
                // Navigate to application form
                Get.toNamed('/ajukan_pinjaman');
              }
            : null,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFFF4C00),
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        child: Text(
          'Lanjut',
          style: GoogleFonts.roboto(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
      ),
    );
  }
}
