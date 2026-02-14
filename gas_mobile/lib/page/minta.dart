import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:tabungan/services/notification_service.dart';

class MintaPage extends StatefulWidget {
  const MintaPage({Key? key}) : super(key: key);

  @override
  State<MintaPage> createState() => _MintaPageState();
}

class _MintaPageState extends State<MintaPage> {
  final NumberFormat _idr = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp ',
    decimalDigits: 0,
  );
  int _amount = 0;
  // New boolean controls whether the amount was confirmed by user.
  // QR is only displayed when `_isAmountConfirmed == true && _amount > 0`.
  bool _isAmountConfirmed = false;

  String get _formattedAmount => _amount == 0 ? '' : _idr.format(_amount);

  // Build QR payload string. Currently a simple format; in production
  // replace this with a call to your backend to obtain a signed QRIS payload
  // (e.g., via an API that returns a QR string or ISO 20022/QRIS formatted data).
  String _buildQrData() {
    // Simple temporary payload: 'REQUEST|AMOUNT=xxxxx'
    // Example integration point: call backend here to generate a proper QRIS payload
    return 'REQUEST|AMOUNT=$_amount';
  }

  // NOTE: The old server-based QR fetch was removed for this flow.
  // New UX: QR is generated locally from a deterministic payload string
  // (see `_buildQrData`) after the user confirms the amount.
  // If you want server-signed QR payloads (QRIS) in the future, implement a
  // backend endpoint and call it from here, then replace `_buildQrData()`'s
  // usage with the server payload. Keep server-side signing & expiry validation
  // as authoritative (do not trust client-side timestamps/signatures).

  void _openKeypad() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AmountKeypad(
        initial: _amount,
        onConfirmed: (value) {
          setState(() {
            _amount = value;
            // Mark the amount as confirmed so QR can be shown.
            _isAmountConfirmed = value > 0;
          });
          NotificationService.showSuccess('Jumlah ditetapkan: ${_idr.format(value)}');
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFF4C00),
      body: SafeArea(
        child: Column(
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => Get.back(),
                    child: const Icon(Icons.arrow_back, color: Colors.white),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Center(
                      child: Text(
                        'Minta dari qris',
                        style: GoogleFonts.roboto(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 20,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 24),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Initial UX: before user confirms amount, show only a short message & the
            // action button. After confirmation we reveal the full QR card.
            if (!_isAmountConfirmed) ...[
              Expanded(
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Silakan tentukan jumlah terlebih dahulu',
                        style: GoogleFonts.roboto(
                          color: Colors.white,
                          fontSize: 16,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: 260,
                        child: OutlinedButton(
                          onPressed: _openKeypad,
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Color(0xFFFF6B2C)),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(6),
                            ),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            backgroundColor: Colors.white,
                          ),
                          child: Text(
                            'TENTUKAN JUMLAH',
                            style: GoogleFonts.roboto(
                              color: const Color(0xFFFF6B2C),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ] else ...[
              Expanded(
                child: Center(
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 20),
                    width: double.infinity,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 20,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // QR image box (shows only after confirmation)
                        Container(
                          height: 320,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.06),
                                blurRadius: 10,
                                offset: const Offset(0, 6),
                              ),
                            ],
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(18.0),
                            child: Center(
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(8),
                                child: Container(
                                  color: Colors.white,
                                  padding: const EdgeInsets.all(8),
                                  child: QrImageView(
                                    data: _buildQrData(),
                                    version: QrVersions.auto,
                                    size: 260,
                                    gapless: false,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),

                        const SizedBox(height: 16),

                        // Amount card when user set an amount
                        Container(
                          margin: const EdgeInsets.symmetric(vertical: 8),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(10),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.06),
                                blurRadius: 8,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  _formattedAmount,
                                  style: GoogleFonts.roboto(
                                    fontSize: 20,
                                    fontWeight: FontWeight.w700,
                                    color: Colors.black87,
                                  ),
                                ),
                              ),
                              IconButton(
                                onPressed: () {
                                  setState(() {
                                    // Reset amount confirmation and clear amount
                                    _amount = 0;
                                    _isAmountConfirmed = false;
                                  });
                                },
                                icon: Icon(
                                  Icons.clear,
                                  color: Colors.grey[600],
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 18),

                        // Text is always shown
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.grey[100],
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            'Minta teman untuk pindai QR atau kamu pun bisa tambahkan dulu jumlahnya',
                            style: GoogleFonts.roboto(
                              fontSize: 13,
                              color: Colors.black87,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _AmountKeypad extends StatefulWidget {
  final int initial;
  final void Function(int) onConfirmed;
  const _AmountKeypad({
    Key? key,
    required this.initial,
    required this.onConfirmed,
  }) : super(key: key);

  @override
  State<_AmountKeypad> createState() => _AmountKeypadState();
}

class _AmountKeypadState extends State<_AmountKeypad> {
  int _value = 0;
  final NumberFormat _idr = NumberFormat.currency(
    locale: 'id_ID',
    symbol: 'Rp ',
    decimalDigits: 0,
  );

  @override
  void initState() {
    super.initState();
    _value = widget.initial;
  }

  void _onDigit(String d) {
    setState(() {
      if (d == '000') {
        _value = _value * 1000;
      } else {
        final dig = int.tryParse(d) ?? 0;
        // prevent leading zeros
        if (_value == 0) {
          _value = dig;
        } else {
          final s = '$_value$dig';
          // cap length to avoid overflow
          if (s.length <= 12) _value = int.parse(s);
        }
      }
    });
  }

  void _backspace() {
    setState(() {
      if (_value < 10) {
        _value = 0;
      } else {
        final s = _value.toString();
        final cut = s.substring(0, s.length - 1);
        _value = int.tryParse(cut) ?? 0;
      }
    });
  }

  Widget _key(String label, {double radius = 8}) {
    return GestureDetector(
      onTap: () {
        if (label == 'DEL') {
          _backspace();
        } else {
          _onDigit(label);
        }
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(radius),
        ),
        alignment: Alignment.center,
        child: label == 'DEL'
            ? const Icon(Icons.backspace_outlined, color: Colors.black87)
            : Text(
                label,
                style: GoogleFonts.roboto(
                  fontSize: 20,
                  fontWeight: FontWeight.w600,
                ),
              ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.6,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      builder: (context, sc) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(12),
            topRight: Radius.circular(12),
          ),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        child: Column(
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Papan isi nomor',
                style: GoogleFonts.roboto(
                  fontWeight: FontWeight.w600,
                  color: Colors.black54,
                ),
              ),
            ),
            const SizedBox(height: 8),
            // display
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey[300]!),
                borderRadius: BorderRadius.circular(6),
                color: const Color(0xFFF8F8F8),
              ),
              child: Row(
                children: [
                  Text(
                    'Rp',
                    style: GoogleFonts.roboto(
                      fontSize: 16,
                      color: Colors.black54,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _value == 0 ? '0' : _idr.format(_value),
                      style: GoogleFonts.roboto(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _backspace,
                    icon: const Icon(Icons.backspace_outlined),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Expanded(
              child: GridView.count(
                controller: sc,
                crossAxisCount: 3,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 2,
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  _key('1'),
                  _key('2'),
                  _key('3'),
                  _key('4'),
                  _key('5'),
                  _key('6'),
                  _key('7'),
                  _key('8'),
                  _key('9'),
                  _key('0'),
                  _key('000'),
                  _key('DEL'),
                ],
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(context).pop();
                  widget.onConfirmed(_value);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFFF6B2C),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: Text(
                  'TENTUKAN JUMLAH',
                  style: GoogleFonts.roboto(
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}
