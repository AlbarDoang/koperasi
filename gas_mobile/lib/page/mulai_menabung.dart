import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
// removed unused imports
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/model/topup_request.dart';
import 'package:tabungan/page/detail_mulai_nabung.dart';
import 'package:tabungan/utils/currency_format.dart';

class MulaiMenabungPage extends StatefulWidget {
  const MulaiMenabungPage({Key? key}) : super(key: key);

  @override
  State<MulaiMenabungPage> createState() => _MulaiMenabungPageState();
}

class _MulaiMenabungPageState extends State<MulaiMenabungPage> {
  final TextEditingController _amountCtrl = TextEditingController();
  int? _selectedPreset;
  String _paymentMethod = 'Uang Tunai';
  bool _isFormatting = false;

  String? _selectedBank;
  String? _selectedEwallet;
  String? _selectedPurpose;

  final List<String> _purposes = [
    'Tabungan Reguler',
    'Tabungan Pelajar',
    'Tabungan Lebaran',
    'Tabungan Qurban',
    'Tabungan Aqiqah',
    'Tabungan Umroh',
    'Tabungan Investasi',
  ];

  final List<String> _banks = [
    'BCA',
    'BNI',
    'BRI',
    'Mandiri',
    'CIMB Niaga',
    'BTN',
    'Danamon',
    'Permata Bank',
    'Bank Syariah Indonesia (BSI)',
  ];

  final List<String> _ewallets = [
    'Dana',
    'OVO',
    'GoPay',
    'ShopeePay',
    'LinkAja',
  ];

  // Only methods that are actually available for selection in the UI.
  // Keep this list in sync with backend capabilities. Currently only cash (Uang Tunai) is supported.
  final List<String> _availablePaymentMethods = [
    'Uang Tunai',
  ];

  final List<int> _presets = [20000, 50000, 100000, 200000, 500000];

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  // Currency formatting is handled by `CurrencyFormat.toIdr` in `controller/fungsi.dart`.

  void _selectPreset(int index) {
    setState(() {
      _selectedPreset = index;
      // set formatted text with currency symbol
      _amountCtrl.text = CurrencyFormat.toIdr(_presets[index]);
      // move cursor to the end
      _amountCtrl.selection = TextSelection.collapsed(offset: _amountCtrl.text.length);
    });
  }

  int _currentAmount() {
    final text = _amountCtrl.text.trim();
    if (text.isEmpty) return 0;
    final digits = text.replaceAll(RegExp(r'[^0-9]'), '');
    return int.tryParse(digits) ?? 0;
  }

  bool get _isFormValid => _currentAmount() > 0 && _selectedPurpose != null;

  Future<void> _confirmTopup() async {
    final text = _amountCtrl.text.trim();
    if (text.isEmpty) {
      Get.snackbar(
        'Error',
        'Masukkan jumlah top-up terlebih dahulu',
        backgroundColor: Colors.red,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        margin: const EdgeInsets.all(16),
      );
      return;
    }
    // strip non-numeric characters before parsing
    final digits = text.replaceAll(RegExp(r'[^0-9]'), '');
    final amount = int.tryParse(digits);
    if (amount == null || amount <= 0) {
      Get.snackbar(
        'Error',
        'Jumlah tidak valid',
        backgroundColor: Colors.red,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
        margin: const EdgeInsets.all(16),
      );
      return;
    }

    final ok = await Get.dialog<bool>(
      AlertDialog(
        title: Text(
          'Konfirmasi Top-up',
          style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Metode: $_paymentMethod',
              style: GoogleFonts.roboto(fontSize: 13),
            ),
            const SizedBox(height: 6),
            Text(
              'Tujuan: ${_selectedPurpose ?? '-'}',
              style: GoogleFonts.roboto(fontSize: 13),
            ),
            const SizedBox(height: 8),
            Text(
              'Jumlah: ${CurrencyFormat.toIdr(amount)}',
              style: GoogleFonts.roboto(
                fontSize: 14,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFFF4C00),
            ),
            onPressed: () => Get.back(result: true),
            child: Text(
              'Top-up',
              style: GoogleFonts.roboto(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );

    if (ok != true) return;
    // validate based on metode
        // Only allow 'Uang Tunai'
        if (_paymentMethod != 'Uang Tunai') {
          Get.snackbar(
            'Info',
            'Metode pembayaran ini belum tersedia',
            backgroundColor: Colors.orange.shade700,
            colorText: Colors.white,
            snackPosition: SnackPosition.TOP,
            margin: const EdgeInsets.all(16),
          );
          return;
        }

    final topup = TopUpRequest(
      nominal: amount,
      metode: _paymentMethod,
      bank: _paymentMethod == 'Transfer Bank' ? _selectedBank : null,
      ewallet: _paymentMethod == 'E-Wallet' ? _selectedEwallet : null,
      purpose: _selectedPurpose,
    );

    // Navigate to detail page with the request
    Get.to(() => DetailMulaiNabungPage(request: topup));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      // Prevent the scaffold from resizing when the keyboard appears so the
      // action button can stay pinned to the bottom and won't be pushed up.
      resizeToAvoidBottomInset: false,
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: OrangeHeader(
        title: 'Mulai Menabung',
        onBackPressed: () => Get.back(),
      ),
      // Make the content scrollable so inputs are still reachable when the
      // keyboard is shown and no overflow occurs.
      body: SingleChildScrollView(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 96),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              color: theme.cardColor,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Isi Saldo Aplikasi',
                    style: GoogleFonts.roboto(
                      fontSize: 12,
                      color: Theme.of(context).textTheme.bodySmall?.color,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _amountCtrl,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      hintText: 'Masukkan jumlah top-up (contoh: 50000)',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                    ),
                    onChanged: (value) {
                      if (_isFormatting) return;
                      _isFormatting = true;
                      // keep only digits
                      final digits = value.replaceAll(RegExp(r'[^0-9]'), '');
                      if (digits.isEmpty) {
                        setState(() {
                          _amountCtrl.text = '';
                          _amountCtrl.selection = const TextSelection.collapsed(offset: 0);
                        });
                        _isFormatting = false;
                        return;
                      }
                      final parsed = int.tryParse(digits) ?? 0;
                      final formatted = CurrencyFormat.toIdr(parsed);
                      setState(() {
                        _amountCtrl.text = formatted;
                        _amountCtrl.selection = TextSelection.collapsed(offset: _amountCtrl.text.length);
                      });
                      _isFormatting = false;
                    },
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Pilih Nominal Cepat',
                    style: GoogleFonts.roboto(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: List.generate(_presets.length, (i) {
                      final val = _presets[i];
                      final selected = _selectedPreset == i;
                      return ChoiceChip(
                        label: Text(
                          CurrencyFormat.toIdr(val),
                          style: GoogleFonts.roboto(
                            color: selected
                                ? Colors.white
                                : theme.textTheme.bodyLarge?.color,
                          ),
                        ),
                        selected: selected,
                        onSelected: (_) => _selectPreset(i),
                        selectedColor: const Color(0xFFFF4C00),
                        backgroundColor: theme.cardColor,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Pilih Tujuan Menabung',
                    style: GoogleFonts.roboto(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _selectedPurpose,
                    items: _purposes
                        .map((p) => DropdownMenuItem(value: p, child: Text(p)))
                        .toList(),
                    onChanged: (v) => setState(() => _selectedPurpose = v),
                    decoration: InputDecoration(
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Metode Pembayaran',
                    style: GoogleFonts.roboto(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          // Limit the dropdown to methods that are actually available.
                          value: _paymentMethod,
                          items: _availablePaymentMethods
                              .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                              .toList(),
                          onChanged: (v) => setState(() {
                            // Since only available methods are shown, just set it directly.
                            _paymentMethod = v ?? _paymentMethod;
                            // reset selections when changing method
                            _selectedBank = null;
                            _selectedEwallet = null;
                          }),
                          decoration: InputDecoration(
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),

                  // Conditional dropdown for bank
                  if (_paymentMethod == 'Transfer Bank') ...[
                    const SizedBox(height: 12),
                    Text(
                      'Pilih Bank',
                      style: GoogleFonts.roboto(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      value: _selectedBank,
                      items: _banks
                          .map((b) => DropdownMenuItem(value: b, child: Text(b)))
                          .toList(),
                      onChanged: (v) => setState(() => _selectedBank = v),
                      decoration: InputDecoration(
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],

                  // Conditional dropdown for e-wallet
                  if (_paymentMethod == 'E-Wallet') ...[
                    const SizedBox(height: 12),
                    Text(
                      'Pilih E-Wallet',
                      style: GoogleFonts.roboto(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      value: _selectedEwallet,
                      items: _ewallets
                          .map((e) => DropdownMenuItem(value: e, child: Text(e)))
                          .toList(),
                      onChanged: (v) => setState(() => _selectedEwallet = v),
                      decoration: InputDecoration(
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
      // Pin the primary action so it does not move when the keyboard opens.
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.all(16),
          color: theme.cardColor,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ButtonStyle(
                    backgroundColor: MaterialStateProperty.resolveWith<Color?>(
                      (states) => states.contains(MaterialState.disabled)
                          ? Colors.grey.shade400
                          : const Color(0xFFFF4C00),
                    ),
                    padding: MaterialStateProperty.all(const EdgeInsets.symmetric(vertical: 14)),
                  ),
                  onPressed: _isFormValid ? _confirmTopup : null,
                  child: Text(
                    'Top-up Sekarang',
                    style: GoogleFonts.roboto(
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                      fontSize: 16,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Visibility(
                visible: !_isFormValid,
                child: Text(
                  'Isi nominal dan pilih tujuan terlebih dahulu',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.roboto(
                    fontSize: 12,
                    color: Colors.grey,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
