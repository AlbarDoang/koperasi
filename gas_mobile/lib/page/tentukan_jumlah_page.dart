import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';

class TentukanjumlahPage extends StatefulWidget {
  const TentukanjumlahPage({super.key});

  @override
  State<TentukanjumlahPage> createState() => _TentukanjumlahPageState();
}

class _TentukanjumlahPageState extends State<TentukanjumlahPage> {
  String _displayAmount = '0';
  int _amount = 0;
  final TextEditingController _notesController = TextEditingController();

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  void _addNumber(String number) {
    setState(() {
      if (_displayAmount == '0') {
        _displayAmount = number;
      } else {
        _displayAmount += number;
      }
      _amount = int.tryParse(_displayAmount) ?? 0;
    });
  }

  void _deleteNumber() {
    setState(() {
      if (_displayAmount.isNotEmpty && _displayAmount.length > 1) {
        _displayAmount = _displayAmount.substring(0, _displayAmount.length - 1);
      } else {
        _displayAmount = '0';
      }
      _amount = int.tryParse(_displayAmount) ?? 0;
    });
  }

  String _formatCurrency(int amount) {
    final formatter = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    );
    return formatter.format(amount);
  }

  void _confirm() {
    if (_amount > 0) {
      Navigator.pop(context, {
        'amount': _amount,
        'notes': _notesController.text,
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Masukkan jumlah lebih dari 0',
            style: GoogleFonts.roboto(color: Colors.white),
          ),
          backgroundColor: Colors.red,
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: false,
      backgroundColor: const Color(0xFFFF4C00),
      appBar: AppBar(
        elevation: 0,
        backgroundColor: const Color(0xFFFF4C00),
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Tentukan Jumlah',
          style: GoogleFonts.poppins(
            fontSize: 18,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
        centerTitle: true,
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              // Amount Display - PREMIUM SPACING
              Expanded(
                flex: 2,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _formatCurrency(_amount),
                      style: GoogleFonts.poppins(
                        fontSize: 52,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                        letterSpacing: -1,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
              // Notes Section - CONTAINER WITH BACKGROUND
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 24),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: Colors.black.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: Colors.white.withOpacity(0.2),
                    width: 1.5,
                  ),
                ),
                child: TextField(
                  controller: _notesController,
                  style: GoogleFonts.roboto(
                    fontSize: 14,
                    color: Colors.white,
                    fontWeight: FontWeight.w500,
                  ),
                  cursorColor: Colors.white,
                  decoration: InputDecoration(
                    hintText: 'Tulis catatan',
                    hintStyle: GoogleFonts.roboto(
                      fontSize: 14,
                      color: Colors.white.withOpacity(0.65),
                      fontWeight: FontWeight.w400,
                    ),
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              // Numpad Section - CONTAINER WITH WHITE BACKGROUND
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 20),
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.95),
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.2),
                      blurRadius: 15,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    // Row 1: 1 2 3
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildNumpadButton('1'),
                        _buildNumpadButton('2'),
                        _buildNumpadButton('3'),
                      ],
                    ),
                    const SizedBox(height: 14),
                    // Row 2: 4 5 6
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildNumpadButton('4'),
                        _buildNumpadButton('5'),
                        _buildNumpadButton('6'),
                      ],
                    ),
                    const SizedBox(height: 14),
                    // Row 3: 7 8 9
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildNumpadButton('7'),
                        _buildNumpadButton('8'),
                        _buildNumpadButton('9'),
                      ],
                    ),
                    const SizedBox(height: 14),
                    // Row 4: 0 000 DEL
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _buildNumpadButton('0'),
                        _buildNumpadButton('000'),
                        _buildDeleteButton(),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              // Confirm Button - FULL WIDTH
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: ElevatedButton(
                    onPressed: _confirm,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 8,
                      shadowColor: Colors.black.withOpacity(0.25),
                    ),
                    child: Text(
                      'KONFIRMASI',
                      style: GoogleFonts.poppins(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Colors.black,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildNumpadButton(String number) {
    return SizedBox(
      width: 80,
      height: 56,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _addNumber(number),
          borderRadius: BorderRadius.circular(10),
          splashColor: const Color(0xFFFF4C00).withOpacity(0.1),
          highlightColor: const Color(0xFFFF4C00).withOpacity(0.05),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: Colors.grey.withOpacity(0.2),
                width: 1,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Center(
              child: Text(
                number,
                style: GoogleFonts.poppins(
                  fontSize: 24,
                  fontWeight: FontWeight.w600,
                  color: Colors.black,
                  letterSpacing: 0.3,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildDeleteButton() {
    return SizedBox(
      width: 80,
      height: 56,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: _deleteNumber,
          borderRadius: BorderRadius.circular(10),
          splashColor: const Color(0xFFFF4C00).withOpacity(0.1),
          highlightColor: const Color(0xFFFF4C00).withOpacity(0.05),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: Colors.grey.withOpacity(0.2),
                width: 1,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.08),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Center(
              child: Icon(
                Icons.backspace_outlined,
                color: Colors.black,
                size: 24,
              ),
            ),
          ),
        ),
      ),
    );
  }
}