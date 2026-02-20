import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;
import 'tentukan_jumlah_page.dart';

class MintaPage extends StatefulWidget {
  const MintaPage({super.key});

  @override
  State<MintaPage> createState() => _MintaPageState();
}

class _MintaPageState extends State<MintaPage> {
  int? _selectedAmount;
  String? _selectedNotes;
  final GlobalKey _qrKey = GlobalKey();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFF4C00),
      appBar: AppBar(
        elevation: 0,
        backgroundColor: const Color(0xFFFF4C00),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Minta dari QRIS',
          style: GoogleFonts.poppins(
            fontSize: 18,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
        centerTitle: true,
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFFF4C00), Color(0xFFFF6B2C)],
          ),
        ),
        child: Column(
          children: [
            // Scrollable Content
            Expanded(
              child: ListView(
                padding: EdgeInsets.zero,
                physics: const ClampingScrollPhysics(),
                children: [
                  const SizedBox(height: 32),
                  // QR Code Section - Compact Size
                  Center(
                    child: RepaintBoundary(
                      key: _qrKey,
                      child: Container(
                        width: 260,
                        height: 260,
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.15),
                              blurRadius: 15,
                              offset: const Offset(0, 8),
                              spreadRadius: 2,
                            ),
                          ],
                        ),
                        child: Center(
                          child: QrImageView(
                            data: 'https://qris.pertamina.com/user123',
                            version: QrVersions.auto,
                            size: 220,
                          gapless: false,
                          errorStateBuilder: (cxt, err) {
                            return Center(
                              child: Text(
                                'Error Generate QR',
                                style: GoogleFonts.roboto(color: Colors.red),
                              ),
                            );
                          },
                        ),
                      ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  // QRIS Image - Bigger & Professional
                  Center(
                    child: Image.asset(
                      'assets/qris.png',
                      height: 70,
                      fit: BoxFit.contain,
                    ),
                  ),
                  const SizedBox(height: 8),
                  // Amount Display (if set)
                  if (_selectedAmount != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 20,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: Colors.white.withOpacity(0.3),
                            width: 2,
                          ),
                        ),
                        child: Column(
                          children: [
                            // Amount Row with Close Button
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        'Jumlah Minta',
                                        style: GoogleFonts.roboto(
                                          fontSize: 13,
                                          color: Colors.white.withOpacity(0.85),
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        'Rp ${_formatCurrency(_selectedAmount ?? 0)}',
                                        style: GoogleFonts.poppins(
                                          fontSize: 28,
                                          fontWeight: FontWeight.bold,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                // Close Button
                                GestureDetector(
                                  onTap: _clearSelection,
                                  child: Container(
                                    width: 28,
                                    height: 28,
                                    decoration: BoxDecoration(
                                      color: Colors.white.withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(4),
                                      border: Border.all(
                                        color: Colors.white.withOpacity(0.4),
                                        width: 1.5,
                                      ),
                                    ),
                                    child: Icon(
                                      Icons.close,
                                      color: Colors.white,
                                      size: 16,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            // Notes Display (if exists)
                            if (_selectedNotes != null && _selectedNotes!.isNotEmpty) ...[
                              const SizedBox(height: 16),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                  vertical: 12,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.18),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: Colors.white.withOpacity(0.45),
                                    width: 1.5,
                                  ),
                                ),
                                child: Text(
                                  _selectedNotes ?? '',
                                  style: GoogleFonts.roboto(
                                    fontSize: 13,
                                    color: Colors.white.withOpacity(0.95),
                                    fontWeight: FontWeight.w500,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  if (_selectedAmount != null)
                    const SizedBox(height: 48)
                  else
                    const SizedBox(height: 8),
                  // Primary Button: Tentukan Jumlah - Show only if no amount selected
                  if (_selectedAmount == null)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: SizedBox(
                        width: double.infinity,
                        height: 56,
                        child: ElevatedButton(
                          onPressed: _navigateToTentukanjumlah,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            elevation: 0,
                            shadowColor: Colors.transparent,
                          ),
                          child: Text(
                            'TENTUKAN JUMLAH',
                            style: GoogleFonts.poppins(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: const Color(0xFFFF4C00),
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                      ),
                    ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
            // Secondary Button: Bagikan Kode QR - STICKY AT BOTTOM
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
              child: SizedBox(
                width: double.infinity,
                height: 56,
                child: OutlinedButton.icon(
                  onPressed: _shareQRCode,
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(
                      color: Colors.white,
                      width: 2.5,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    backgroundColor: Colors.transparent,
                  ),
                  icon: const Icon(
                    Icons.share,
                    color: Colors.white,
                    size: 20,
                  ),
                  label: Text(
                    'BAGIKAN KODE QR',
                    style: GoogleFonts.poppins(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatCurrency(int amount) {
    if (amount == 0) return '0';
    return amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]}.',
        );
  }

  void _shareQRCode() async {
    try {
      // Show loading indicator
      _showLoadingDialog();

      // Capture the QR code widget as an image
      final RenderRepaintBoundary boundary =
          _qrKey.currentContext!.findRenderObject() as RenderRepaintBoundary;
      final ui.Image image = await boundary.toImage(pixelRatio: 3.0);
      final ByteData? byteData =
          await image.toByteData(format: ui.ImageByteFormat.png);
      final Uint8List pngBytes = byteData!.buffer.asUint8List();

      // Save the image to temporary directory
      final Directory tempDir = await getTemporaryDirectory();
      final String fileName =
          'qr_code_${DateTime.now().millisecondsSinceEpoch}.png';
      final File file = File('${tempDir.path}/$fileName');
      await file.writeAsBytes(pngBytes);

      // Close loading dialog
      if (mounted) {
        Navigator.of(context).pop();
      }

      // Share the file
      await Share.shareXFiles(
        [XFile(file.path)],
        subject:
            'Minta Pembayaran via QRIS${_selectedAmount != null ? ' - Rp ${_formatCurrency(_selectedAmount!)}' : ' - Jumlah Bebas'}',
        text:
            'Minta Pembayaran${_selectedAmount != null ? ' Rp ${_formatCurrency(_selectedAmount!)}' : ''}${_selectedNotes != null && _selectedNotes!.isNotEmpty ? '\n\nCatatan: $_selectedNotes' : ''}',
      );
    } catch (e) {
      // Close loading dialog if still open
      if (mounted && Navigator.canPop(context)) {
        Navigator.of(context).pop();
      }

      // Show error message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Gagal membagikan QR code: $e',
              style: GoogleFonts.roboto(color: Colors.white),
            ),
            backgroundColor: Colors.red,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }

  void _showLoadingDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return AlertDialog(
          content: Row(
            children: [
              const CircularProgressIndicator(),
              const SizedBox(width: 16),
              Text(
                'Menyiapkan QR code...',
                style: GoogleFonts.roboto(fontSize: 14),
              ),
            ],
          ),
        );
      },
    );
  }

  void _navigateToTentukanjumlah() async {
    final result = await Get.to(
      () => const TentukanjumlahPage(),
      transition: Transition.rightToLeft,
    );

    if (result != null && result is Map) {
      setState(() {
        _selectedAmount = result['amount'] as int?;
        _selectedNotes = result['notes'] as String?;
      });
    }
  }

  void _clearSelection() {
    setState(() {
      _selectedAmount = null;
      _selectedNotes = null;
    });
  }
}