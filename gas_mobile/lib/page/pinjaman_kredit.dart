import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:tabungan/page/sk_gaspinjam.dart';
import 'package:tabungan/page/ajukan_pk.dart';
import 'package:tabungan/page/review_pk.dart';

class PinjamanKreditPage extends StatefulWidget {
  const PinjamanKreditPage({Key? key}) : super(key: key);

  @override
  State<PinjamanKreditPage> createState() => _PinjamanKreditPageState();
}

class _PinjamanKreditPageState extends State<PinjamanKreditPage> {
  bool acceptedTerms = false;

  // Controllers
  final TextEditingController _itemNameController = TextEditingController();
  final TextEditingController _priceController = TextEditingController();
  final TextEditingController _dpController = TextEditingController();
  // (interest/admin controllers removed)

  // Tenor options (1..12 months)
  final List<int> _tenors = List<int>.generate(12, (i) => i + 1);
  int _selectedTenor = 12;

  // (interest and admin removed)
  // (Admin and bunga removed)
  // Simulation results
  double _principal = 0.0;
  double _totalPayable = 0.0;
  double _monthlyInstallment = 0.0;

  @override
  void initState() {
    super.initState();
    // Listen to price/dp changes only to recalc simulation
    _priceController.addListener(_calculateSimulation);
    _dpController.addListener(_calculateSimulation);
    // Listen to form changes to trigger rebuild for button state
    _itemNameController.addListener(() => setState(() {}));
    _priceController.addListener(() => setState(() {}));
    _dpController.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _itemNameController.dispose();
    _priceController.dispose();
    _dpController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F6F6),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildHeaderCard(),
          const SizedBox(height: 14),
          _buildLoanAmountCard(),
          const SizedBox(height: 14),
          _buildFeatureGrid(),
          const SizedBox(height: 14),
          _buildCreditSpecsCard(),
          const SizedBox(height: 14),
          _buildBenefitsCard(),
          const SizedBox(height: 14),
          // Store loan form (cicilan barang)
          _buildStoreLoanForm(),
          const SizedBox(height: 14),
          _buildPromoCard(),
          const SizedBox(height: 14),
          _buildInfoCard(),
          const SizedBox(height: 16),
          _buildAgreementCheck(),
          const SizedBox(height: 18),
          _buildApplyButton(),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  // ---------- UI Building Blocks ----------

  Widget _buildHeaderCard() {
    return Card(
      elevation: 3,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              const Color(0xFFFF4C00).withOpacity(0.9),
              const Color(0xFFFF6B35),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'GAS Pembiayaan Barang',
              style: GoogleFonts.roboto(
                fontSize: 24,
                fontWeight: FontWeight.w900,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Ajukan pembelian barang — perusahaan membeli, Anda cicil',
              style: GoogleFonts.roboto(
                fontSize: 13,
                color: Colors.white70,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.2),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                '✓ Persetujuan Instan',
                style: GoogleFonts.roboto(
                  fontSize: 12,
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLoanAmountCard() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Container(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Maks Pembelian Barang',
                      style: GoogleFonts.roboto(
                        fontSize: 13,
                        color: Colors.black54,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Rp50.000.000',
                      style: GoogleFonts.roboto(
                        fontSize: 26,
                        color: const Color(0xFF0B6E4F),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0B6E4F).withOpacity(0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.shopping_bag,
                    color: Color(0xFF0B6E4F),
                    size: 28,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: 0.7,
                minHeight: 6,
                backgroundColor: Colors.grey.shade300,
                valueColor: AlwaysStoppedAnimation<Color>(Colors.blue.shade400),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Limit pembelian disesuaikan dengan histori dan verifikasi data Anda',
              style: GoogleFonts.roboto(
                fontSize: 11,
                color: Colors.black54,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFeatureGrid() {
    return GridView.count(
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: [
        _featureGridCard(
          'Perusahaan Beli Barang',
          'Kami membelikan dan langsung di kirim ke alamat Anda',
          Icons.storefront,
          Colors.teal,
        ),
        _featureGridCard(
          'Cicilan Fleksibel',
          '2-24 bulan',
          Icons.schedule,
          Colors.blue,
        ),
        _featureGridCard(
          'Pengiriman',
          'Pengiriman langsung ke alamat Anda',
          Icons.local_shipping,
          Colors.purple,
        ),
        _featureGridCard(
          'Garansi & Support',
          'Layanan Bantuan Setelah Pembelian',
          Icons.verified,
          Colors.orange,
        ),
      ],
    );
  }

  Widget _featureGridCard(
    String title,
    String value,
    IconData icon,
    Color color,
  ) {
    return Card(
      elevation: 1,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, color: color, size: 18),
                ),
              ],
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.roboto(
                    fontSize: 11,
                    color: Colors.black54,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: GoogleFonts.roboto(
                    fontSize: 14,
                    color: Colors.black,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCreditSpecsCard() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Spesifikasi Kredit',
              style: GoogleFonts.roboto(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: Colors.black,
              ),
            ),
            const SizedBox(height: 14),
            _specRow('Jenis Pinjaman', 'Pembelian Barang'),
            const Divider(height: 12),
            _specRow('Persyaratan Usia', '18 - 65 tahun'),
            const Divider(height: 12),
            _specRow('Dokumen Diperlukan', 'e-KTP (dari profil) + Foto Barang (wajib)'),
            const Divider(height: 12),
            _specRow('Durasi Persetujuan', '5 - 30 menit'),
            const Divider(height: 12),
            _specRow('Periode Cicilan', '2 - 24 bulan'),
          ],
        ),
      ),
    );
  }

  Widget _specRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.roboto(
            fontSize: 12,
            color: Colors.black54,
            fontWeight: FontWeight.w500,
          ),
        ),
        Text(
          value,
          style: GoogleFonts.roboto(
            fontSize: 12,
            color: Colors.black,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }

  Widget _buildBenefitsCard() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Keuntungan Pembiayaan Barang',
              style: GoogleFonts.roboto(
                fontSize: 15,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            _benefitRow(
              Icons.shopping_bag,
              'Barang Diberikan',
              'Perusahaan membeli dan menyerahkan barang',
            ),
            const SizedBox(height: 10),
            _benefitRow(
              Icons.payments,
              'Cicilan Terjadwal',
              'Bayar cicilan per bulan lewat aplikasi',
            ),
            const SizedBox(height: 10),
            _benefitRow(
              Icons.local_shipping,
              'Pengiriman Aman',
              'Dikirim aman ke alamat Anda',
            ),
            const SizedBox(height: 10),
            _benefitRow(
              Icons.lock,
              'Aman & Terpercaya',
              'Data terenkripsi dan dukungan setelah pembelian',
            ),
          ],
        ),
      ),
    );
  }

  Widget _benefitRow(IconData icon, String title, String subtitle) {
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
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Colors.black,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: GoogleFonts.roboto(
                  fontSize: 11,
                  color: Colors.black54,
                  fontWeight: FontWeight.w400,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildPromoCard() {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.amber.shade50, Colors.yellow.shade50],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.amber.shade200, width: 1),
      ),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.amber.withOpacity(0.2),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.local_offer, color: Colors.amber, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Promo Spesial',
                  style: GoogleFonts.roboto(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: Colors.amber.shade900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Diskon 5% cicilan untuk pinjaman pertama',
                  style: GoogleFonts.roboto(
                    fontSize: 11,
                    color: Colors.amber.shade800,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoCard() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.blue.shade50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.blue.shade200, width: 1),
      ),
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Icon(Icons.info, color: Colors.blue.shade600, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Proses: Anda pilih barang → perusahaan membeli → barang dikirim → Anda membayar cicilan sesuai jadwal.',
              style: GoogleFonts.roboto(
                fontSize: 11,
                color: Colors.blue.shade700,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---------- Store Loan Form (only inputs allowed by spec) ----------

  Widget _buildStoreLoanForm() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Cicilan Barang',
              style: GoogleFonts.roboto(
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _itemNameController,
              decoration: const InputDecoration(
                labelText: 'Nama Barang',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextField(
                    controller: _priceController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      _ThousandsSeparatorFormatter(),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Harga',
                      prefixText: 'Rp ',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: _dpController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      _ThousandsSeparatorFormatter(),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'DP',
                      prefixText: 'Rp ',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<int>(
                    value: _selectedTenor,
                    decoration: const InputDecoration(
                      labelText: 'Tenor (bulan)',
                      border: OutlineInputBorder(),
                    ),
                    items: _tenors
                        .map(
                          (t) => DropdownMenuItem(
                            value: t,
                            child: Text('$t bulan'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      setState(() {
                        _selectedTenor = v ?? _selectedTenor;
                      });
                      _calculateSimulation();
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Biaya Admin removed
            const SizedBox(height: 14),
            const Divider(),
            const SizedBox(height: 8),
            Text(
              'Simulasi Cicilan',
              style: GoogleFonts.roboto(
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            _buildSimulationRow(
              'Harga',
              _formatCurrency(_getParsed(_priceController.text)),
            ),
            _buildSimulationRow(
              'DP',
              _formatCurrency(_getParsed(_dpController.text)),
            ),
            _buildSimulationRow(
              'Pokok (Harga - DP)',
              _formatCurrency(_principal),
            ),
            const SizedBox(height: 6),
            _buildSimulationRow(
              'Total Bayar',
              _formatCurrency(_totalPayable),
              bold: true,
            ),
            _buildSimulationRow(
              'Per Bulan',
              _formatCurrency(_monthlyInstallment),
              bold: true,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSimulationRow(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.roboto(fontSize: 13, color: Colors.black54),
          ),
          Text(
            value,
            style: GoogleFonts.roboto(
              fontSize: 13,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // ---------- Logic ----------

  double _getParsed(String s) {
    if (s.isEmpty) return 0.0;
    final cleaned = s
        .replaceAll('.', '')
        .replaceAll(',', '')
        .replaceAll('Rp', '')
        .trim();
    return double.tryParse(cleaned) ?? 0.0;
  }

  // Admin and bunga removed — Syariah flat: floor division, no remainder adjustment

  void _calculateSimulation() {
    final price = _getParsed(_priceController.text);
    final dp = _getParsed(_dpController.text);
    final tenor = _selectedTenor;

    final principal = (price - dp) > 0 ? (price - dp) : 0.0;
    // Syariah flat: cicilan_per_bulan = floor(pokok / tenor), remainder ignored
    final monthlyBase = tenor > 0 ? (principal.toInt() ~/ tenor) : 0;
    final monthly = monthlyBase.toDouble();
    final totalPayable = dp + (monthly * tenor);

    setState(() {
      _principal = principal;
      _totalPayable = totalPayable;
      _monthlyInstallment = monthly;
    });
  }

  // ---------- Submission & Agreement ----------

  bool _isFormValid() {
    final name = _itemNameController.text.trim();
    final price = _getParsed(_priceController.text);
    final dp = _getParsed(_dpController.text);
    return name.isNotEmpty && price > 0 && dp >= 0;
  }

  // Unused method - kept for future reference
  // ignore: unused_element
  void _submitApplication() {
    final name = _itemNameController.text.trim();
    final price = _getParsed(_priceController.text);
    final dp = _getParsed(_dpController.text);
    final tenor = _selectedTenor;

    if (name.isEmpty || price <= 0 || tenor <= 0) {
      NotificationHelper.showError('Lengkapi nama barang, harga, dan tenor');
      return;
    }

    _calculateSimulation();

    showDialog(
      context: context,
      builder: (context) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Ringkasan Pengajuan',
                style: GoogleFonts.roboto(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'Nama Barang: $name',
                style: GoogleFonts.roboto(fontSize: 13),
              ),
              const SizedBox(height: 6),
              Text(
                'Harga Barang: ${_formatCurrency(price)}',
                style: GoogleFonts.roboto(fontSize: 13),
              ),
              const SizedBox(height: 6),
              Text(
                'DP: ${_formatCurrency(dp)}',
                style: GoogleFonts.roboto(fontSize: 13),
              ),
              const SizedBox(height: 6),
              Text(
                'Periode: ${tenor} bulan',
                style: GoogleFonts.roboto(fontSize: 13),
              ),
              // Bunga and Biaya Admin removed from summary
              const SizedBox(height: 6),
              Text(
                'Total Dibayar: ${_formatCurrency(_totalPayable)}',
                style: GoogleFonts.roboto(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Cicilan per bulan: ${_formatCurrency(_monthlyInstallment)}',
                style: GoogleFonts.roboto(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.pop(context),
                      child: const Text('Kembali'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.pop(context);
                        NotificationHelper.showSuccess(
                          'Pengajuan untuk $name berhasil dikirim',
                        );
                        // reset fields
                        _itemNameController.clear();
                        _priceController.clear();
                        _dpController.clear();
                        _calculateSimulation();
                      },
                      child: const Text('Ajukan'),
                    ),
                  ),
                ],
              ),
            ],
          ),
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

  Widget _buildApplyButton() {
    final isFormValid = _isFormValid();
    final isEnabled = acceptedTerms && isFormValid;

    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: isEnabled
            ? () {
                // Prepare values for review
                final name = _itemNameController.text.trim();
                final price = _getParsed(_priceController.text);
                final dp = _getParsed(_dpController.text);
                final tenor = _selectedTenor;
                if (dp > price) {
                  NotificationHelper.showWarningYellow(
                    'DP harus lebih kecil dari harga barang',
                  );
                  return;
                }
                // ensure simulation up to date
                _calculateSimulation();

                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => ReviewPkPage(
                      itemName: name,
                      price: price,
                      dp: dp,
                      tenor: tenor,
                      principal: _principal,
                      totalPayable: _totalPayable,
                      monthly: _monthlyInstallment,
                    ),
                  ),
                );
              }
            : null,
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFFF4C00),
          disabledBackgroundColor: Colors.grey.shade300,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(vertical: 16),
          elevation: 2,
        ),
        child: Text(
          'Ajukan Pinjaman Barang',
          style: GoogleFonts.roboto(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: isEnabled ? Colors.white : Colors.grey.shade600,
          ),
        ),
      ),
    );
  }

  // ---------- Helpers ----------

  String _formatCurrency(double value) {
    final intVal = value.round();
    final s = intVal.toString().replaceAllMapped(
      RegExp(r"\B(?=(\d{3})+(?!\d))"),
      (m) => '.',
    );
    return 'Rp$s';
  }
}

// Custom formatter for thousands separator
class _ThousandsSeparatorFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    if (newValue.text.isEmpty) {
      return newValue;
    }

    // Remove all dots first
    String digitsOnly = newValue.text.replaceAll('.', '');

    // Add dots for thousands
    StringBuffer result = StringBuffer();
    for (int i = 0; i < digitsOnly.length; i++) {
      if (i > 0 && (digitsOnly.length - i) % 3 == 0) {
        result.write('.');
      }
      result.write(digitsOnly[i]);
    }

    return TextEditingValue(
      text: result.toString(),
      selection: TextSelection.collapsed(offset: result.toString().length),
    );
  }
}
