import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:convert';
import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/config/api.dart';

class PindaiPage extends StatefulWidget {
  const PindaiPage({Key? key}) : super(key: key);

  @override
  State<PindaiPage> createState() => _PindaiPageState();
}

class _PindaiPageState extends State<PindaiPage> {
  final MobileScannerController _controller = MobileScannerController();
  final TextEditingController _manualController = TextEditingController();
  final ImagePicker _picker = ImagePicker();

  // Prevent multiple concurrent processing of the same scan
  bool _isProcessing = false;
  // Prevent double submission while calling the payment API
  bool _isSubmitting = false;
  // Debug: show API base on page open (debug only)
  bool _apiInfoShown = false;

  // When a QR is detected we return its raw string value (no image decoding here).
  // This keeps the scanner lightweight and consistent across platforms.
  void _onDetect(BarcodeCapture capture) {
    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;
    final raw = barcodes.first.rawValue;
    if (raw != null && raw.isNotEmpty && !_isProcessing) {
      // Prevent duplicate processing and stop camera while we process
      _isProcessing = true;
      _controller.stop();

      // Process QR as a payment request (parses 'REQUEST|AMOUNT=xxxx' and optional CODE)
      _processScannedQr(raw).whenComplete(() {
        // Ensure we clear processing flag and resume scanning when done
        _isProcessing = false;
        if (mounted) _controller.start();
      });
    }
  }

  @override
  void initState() {
    super.initState();
    // Show debug toast with current API base on first build (debug only)
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!kReleaseMode && !_apiInfoShown) {
        final base = Api.baseUrl;
        NotificationService.showInfo('API base: $base');
        _apiInfoShown = true;
      }
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _manualController.dispose();
    super.dispose();
  }

  // Process a scanned QR payload as a payment request.
  // Expected payload format examples:
  //  - REQUEST|AMOUNT=5000
  //  - REQUEST|CODE=abc123|AMOUNT=5000
  // The method parses `amount` and optional `request_code`, then calls the
  // backend endpoint `pay_payment_request.php` sending: payer_user_id,
  // request_code (nullable), amount. It shows a success or error dialog
  // depending on the API response.
  Future<void> _processScannedQr(String raw) async {
    try {
      // Parse raw payload
      String? requestCode;
      int? amount;
      final parts = raw.split('|');
      for (var p in parts) {
        final segment = p.trim();
        if (segment.toUpperCase().startsWith('AMOUNT=')) {
          final val = segment.substring(segment.indexOf('=') + 1);
          amount = int.tryParse(val.replaceAll(RegExp(r'[^0-9]'), ''));
        }
        if (segment.toUpperCase().startsWith('CODE=')) {
          requestCode = segment.substring(segment.indexOf('=') + 1);
        }
        // also accept REQUEST_CODE=
        if (segment.toUpperCase().startsWith('REQUEST_CODE=')) {
          requestCode = segment.substring(segment.indexOf('=') + 1);
        }
      }

      if (amount == null || amount <= 0) {
        // Invalid QR for payment
        await showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('QR tidak valid'),
            content: const Text('Data jumlah tidak ditemukan pada QR.'),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('OK'),
              ),
            ],
          ),
        );
        return;
      }

      // Get current authenticated user (payer)
      final currentUser = await EventPref.getUser();
      if (currentUser == null || currentUser.id == null) {
        NotificationService.showError('User tidak ditemukan. Silakan login ulang.');
        return;
      }

      // Ask user to confirm the payment before calling backend
      final bool confirmed = await showDialog<bool>(
            context: context,
            barrierDismissible: false,
            builder: (_) => AlertDialog(
              title: const Text('Konfirmasi pembayaran?'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Jumlah: Rp $amount'),
                  if (requestCode != null) ...[
                    const SizedBox(height: 8),
                    Text('Kode permintaan: $requestCode'),
                  ],
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  child: const Text('BATAL'),
                ),
                ElevatedButton(
                  onPressed: () => Navigator.of(context).pop(true),
                  child: const Text('BAYAR'),
                ),
              ],
            ),
          ) ??
          false;

      if (!confirmed) {
        // user cancelled; simply return and scanning will resume
        return;
      }

      // Prevent double-submit if already calling API
      if (_isSubmitting) return;
      _isSubmitting = true;

      try {
        // Show progress dialog while calling backend
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (_) => const AlertDialog(
            content: SizedBox(
              height: 80,
              child: Center(child: CircularProgressIndicator()),
            ),
          ),
        );

        // Build JSON payload according to backend contract
        final uri = Uri.parse(Api.payPaymentRequest);
        final payload = <String, dynamic>{
          'payer_user_id': currentUser.id,
          'amount': amount,
        };
        if (requestCode != null && requestCode.isNotEmpty) {
          payload['request_code'] = requestCode;
        }

        // Why POST? Payment actions change state and must not be done via GET.
        // We send JSON with Content-Type: application/json to keep payload explicit and avoid URL-size limits.
        // Why 10.0.2.2 vs LAN IP: the emulator maps host loopback to 10.0.2.2; physical devices need the dev machine LAN IP.
        // Change base URL in `lib/config/api.dart` or set `API_BASE_URL` in your .env to override.

        final resp = await http
            .post(uri, headers: {'Content-Type': 'application/json'}, body: jsonEncode(payload))
            .timeout(const Duration(seconds: 15));

        Navigator.of(context).pop(); // close progress dialog

        if (resp.statusCode == 200) {
          final Map<String, dynamic> jsonResp = jsonDecode(resp.body);
          if (jsonResp['success'] == true) {
            await showDialog(
              context: context,
              builder: (_) => AlertDialog(
                title: const Text('Pembayaran berhasil'),
                content: Text(jsonResp['message'] ?? 'Pembayaran telah diproses.'),
                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.of(context).pop();
                      // Optionally return a result to caller
                      Get.back(result: {'status': 'paid', 'amount': amount, 'request_code': requestCode});
                    },
                    child: const Text('OK'),
                  ),
                ],
              ),
            );
          } else {
            // API returned success=false with message
            await showDialog(
              context: context,
              builder: (_) => AlertDialog(
                title: const Text('Transaksi gagal'),
                content: Text(jsonResp['message'] ?? 'Pembayaran gagal.'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(),
                    child: const Text('OK'),
                  ),
                ],
              ),
            );
          }
        } else {
          // Non-200 HTTP response
          await showDialog(
            context: context,
            builder: (_) => AlertDialog(
              title: const Text('Kesalahan jaringan'),
              content: Text('Gagal menghubungi server (status ${resp.statusCode})'),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('OK'),
                ),
              ],
            ),
          );
        }
      } on TimeoutException catch (_) {
        try {
          Navigator.of(context).pop();
        } catch (_) {}
        await showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Timeout'),
            content: const Text('Permintaan melebihi waktu tunggu. Silakan coba lagi.'),
            actions: [
              TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK')),
            ],
          ),
        );
      } on Exception catch (e) {
        // Network or other exceptions
        try {
          Navigator.of(context).pop();
        } catch (_) {}
        await showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Kesalahan jaringan'),
            content: Text('Tidak dapat terhubung ke server. Periksa koneksi jaringan.\n${e.toString()}'),
            actions: [
              TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK')),
            ],
          ),
        );
      } catch (e) {
        try {
          Navigator.of(context).pop();
        } catch (_) {}
        await showDialog(
          context: context,
          builder: (_) => AlertDialog(
            title: const Text('Error'),
            content: Text('Terjadi kesalahan: $e'),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('OK'),
              ),
            ],
          ),
        );
      } finally {
        _isSubmitting = false;
      }
    } catch (e) {
      // Generic error
      await showDialog(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Error'),
          content: Text('Terjadi kesalahan: $e'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('OK'),
            ),
          ],
        ),
      );
    }
  }

  Future<void> _showApiOverrideDialog() async {
    if (kReleaseMode) return; // hidden in production builds

    String? current = await Api.getOverride();
    String selected = current ?? 'auto';

    await showDialog<void>(
      context: context,
      builder: (_) => StatefulBuilder(
        builder: (context, setState) {
          return AlertDialog(
            title: const Text('Debug: API Base URL'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                RadioListTile<String>(
                  title: const Text('Auto (env or default LAN)'),
                  value: 'auto',
                  groupValue: selected,
                  onChanged: (v) => setState(() => selected = v ?? 'auto'),
                ),
                RadioListTile<String>(
                  title: const Text('Emulator (10.0.2.2)'),
                  value: 'emulator',
                  groupValue: selected,
                  onChanged: (v) => setState(() => selected = v ?? 'emulator'),
                ),
                RadioListTile<String>(
                  title: const Text('LAN (172.168.80.236)'),
                  value: 'lan',
                  groupValue: selected,
                  onChanged: (v) => setState(() => selected = v ?? 'lan'),
                ),
                const SizedBox(height: 8),
                Text('Current effective base: ${Api.baseUrl}', style: const TextStyle(fontSize: 12)),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('BATAL'),
              ),
              ElevatedButton(
                onPressed: () async {
                  await Api.setOverride(selected == 'auto' ? Api.overrideAuto : selected);
                  if (mounted) {
                    Navigator.of(context).pop();
                    NotificationService.showSuccess('API base set to: ${Api.baseUrl}');
                    setState(() {});
                  }
                },
                child: const Text('SIMPAN'),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PreferredSize(
        preferredSize: const Size.fromHeight(kToolbarHeight),
        child: Container(
          padding: EdgeInsets.fromLTRB(
            0,
            MediaQuery.of(context).padding.top,
            0,
            0,
          ),
          color: const Color(0xFFFF4C00),
          child: SizedBox(
            height: kToolbarHeight,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    icon: const Icon(Icons.arrow_back, color: Colors.white),
                    onPressed: () => Get.back(),
                  ),
                ),
                Center(
                  child: GestureDetector(
                    onLongPress: kReleaseMode ? null : _showApiOverrideDialog,
                    child: Text(
                      'Pindai kode QR',
                      style: GoogleFonts.roboto(
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      body: Stack(
        children: [
          // Camera feed
          Expanded(
            child: MobileScanner(controller: _controller, onDetect: _onDetect),
          ),
          // QR frame overlay (center of screen)
          Center(
            child: Container(
              width: 280,
              height: 280,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white, width: 3),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Stack(
                children: [
                  // Corner markers (top-left, top-right, bottom-left, bottom-right)
                  Positioned(
                    top: 0,
                    left: 0,
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(color: Colors.white, width: 3),
                          left: BorderSide(color: Colors.white, width: 3),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    top: 0,
                    right: 0,
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: const BoxDecoration(
                        border: Border(
                          top: BorderSide(color: Colors.white, width: 3),
                          right: BorderSide(color: Colors.white, width: 3),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 0,
                    left: 0,
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(color: Colors.white, width: 3),
                          left: BorderSide(color: Colors.white, width: 3),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 0,
                    right: 0,
                    child: Container(
                      width: 30,
                      height: 30,
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(color: Colors.white, width: 3),
                          right: BorderSide(color: Colors.white, width: 3),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          // Bottom control bar
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
              color: Colors.black87,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  IconButton(
                    icon: const Icon(
                      Icons.photo_library_outlined,
                      color: Colors.white,
                      size: 28,
                    ),
                    onPressed: () async {
                      // Note: decoding QR from images via `qr_code_tools` was removed.
                      // To avoid using deprecated plugins we no longer decode selected
                      // images here. Please use the camera scanner to scan QR codes.
                      NotificationService.showInfo('Pilih gambar tidak didukung. Gunakan kamera untuk memindai QR.');
                    },
                  ),
                  // Removed central orange button — scanner membaca otomatis
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(
                      Icons.flash_on_outlined,
                      color: Colors.white,
                      size: 28,
                    ),
                    onPressed: () async {
                      await _controller.toggleTorch();
                      setState(() {});
                    },
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
