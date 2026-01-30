// ignore_for_file: library_private_types_in_public_api

import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/page/aktivasi/setpin.dart';
import 'package:tabungan/page/dashboard.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/utils/custom_toast.dart';

class AktivasiAkunPage extends StatefulWidget {
  const AktivasiAkunPage({Key? key}) : super(key: key);

  @override
  State<AktivasiAkunPage> createState() => _AktivasiAkunPageState();
}

class _AktivasiAkunPageState extends State<AktivasiAkunPage>
    with SingleTickerProviderStateMixin {
  final Color primaryOrange = const Color(0xFFFF4D00);
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _otpController = TextEditingController();
  final FocusNode _phoneFocusNode = FocusNode();

  bool _otpSent = false;
  bool _isSendingOtp = false;
  bool _isVerifying = false;
  int _resendCountdown = 0;
  int _otpValidityCountdown = 0;
  Timer? _timer;
  Timer? _otpValidityTimer;

  late AnimationController _fadeController;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
    _phoneFocusNode.addListener(() {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _fadeController.dispose();
    _timer?.cancel();
    _otpValidityTimer?.cancel();
    _phoneFocusNode.dispose();
    super.dispose();
  }

  void _startCountdown() {
    _resendCountdown = 60;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_resendCountdown == 0) {
        timer.cancel();
      } else {
        setState(() => _resendCountdown--);
      }
    });

    _otpValidityCountdown = 120;
    _otpValidityTimer?.cancel();
    _otpValidityTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_otpValidityCountdown == 0) {
        timer.cancel();
        if (mounted) {
          setState(() => _otpSent = false);
          _showCustomBanner('Kode OTP telah kadaluarsa. Silakan minta kode baru.', color: Colors.redAccent);
        }
      } else {
        setState(() => _otpValidityCountdown--);
      }
    });
  }

  String _getOtpValidityText() {
    if (_otpValidityCountdown <= 0) {
      return 'Kode OTP telah kadaluarsa';
    }
    int minutes = _otpValidityCountdown ~/ 60;
    int seconds = _otpValidityCountdown % 60;
    return 'Kode OTP berlaku: ${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  Color _getOtpValidityColor() {
    if (_otpValidityCountdown <= 30) {
      return Colors.red;
    }
    return Colors.orange;
  }

  void _showCustomBanner(String message, {Color color = Colors.orange}) async {
    // Use centralized CustomToast to show a non-stacking toast.
    CustomToast.show(context, message, baseColor: color);
  }

  Future<void> _sendOtp() async {
    final phone = _phoneController.text.trim();
    if (phone.isEmpty) {
      _showCustomBanner('Nomor HP wajib diisi!', color: Colors.redAccent);
      return;
    }

    setState(() {
      _isSendingOtp = true;
    });

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.aktivasiAkun),
        body: {
          'action': 'send_otp',
          'no_hp': phone,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        setState(() {
          _otpSent = true;
          _otpController.clear();
        });
        _startCountdown();
        _showCustomBanner(message, color: primaryOrange);

        if (kDebugMode && payload['otp'] != null) {
          debugPrint('OTP DEV PREVIEW: ${payload['otp']}');
        }
      } else {
        _showCustomBanner(message, color: Colors.redAccent);
      }
    } on TimeoutException {
      _showCustomBanner('Request timeout - Server tidak merespons', color: Colors.redAccent);
    } catch (e) {
      _showCustomBanner('Gagal mengirim OTP: $e', color: Colors.redAccent);
    } finally {
      if (mounted) {
        setState(() {
          _isSendingOtp = false;
        });
      }
    }
  }

  Future<void> _verifyOtp() async {
    final phone = _phoneController.text.trim();
    final otp = _otpController.text.trim();

    if (phone.isEmpty) {
      _showCustomBanner('Nomor HP wajib diisi!', color: Colors.redAccent);
      return;
    }

    if (otp.length != 6) {
      _showCustomBanner('Kode OTP harus 6 digit!', color: Colors.redAccent);
      return;
    }

    setState(() => _isVerifying = true);

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.aktivasiAkun),
        body: {
          'action': 'verify_otp',
          'no_hp': phone,
          'otp': otp,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        Map<String, dynamic>? userMap;
        if (payload['user'] != null) {
          userMap = Map<String, dynamic>.from(payload['user']);
          await EventPref.saveUser(User.fromJson(userMap));
        }

        _showCustomBanner(message, color: primaryOrange);

        if (!mounted) return;

        // Only navigate to SetPin if the account is approved.
        final status = (userMap != null && userMap['status_akun'] != null)
            ? userMap['status_akun'].toString().toLowerCase()
            : '';
        if (status == 'approved') {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (context) => const SetPinPage()),
          );

          if (result == true && mounted) {
            Navigator.pushAndRemoveUntil(
              context,
              MaterialPageRoute(builder: (context) => Dashboard()),
              (route) => false,
            );
          }
        } else {
          // For PENDING or other states, return to previous screen (usually login)
          await Future.delayed(const Duration(milliseconds: 300));
          if (mounted) Navigator.pop(context, false);
        }
      } else {
        _showCustomBanner(message, color: Colors.redAccent);
      }
    } on TimeoutException {
      _showCustomBanner('Request timeout - Server tidak merespons', color: Colors.redAccent);
    } catch (e) {
      _showCustomBanner('Gagal verifikasi OTP: $e', color: Colors.redAccent);
    } finally {
      if (mounted) {
        setState(() => _isVerifying = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: OrangeHeader(title: "Aktivasi Akun"),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Text(
              'Masukkan nomor HP kamu untuk menerima kode aktivasi melalui WhatsApp.',
              style: GoogleFonts.kanit(color: Colors.black87, fontSize: 14),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 30),

            TextField(
              controller: _phoneController,
              focusNode: _phoneFocusNode,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                hintText: _phoneFocusNode.hasFocus ? '08xxxxxxxxxx' : 'Nomor HP',
                labelStyle: GoogleFonts.kanit(),
                prefixIcon: const Icon(Icons.phone),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
            const SizedBox(height: 20),

            !_otpSent
                ? ElevatedButton(
                    onPressed: _isSendingOtp ? null : _sendOtp,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: primaryOrange,
                      minimumSize: const Size(double.infinity, 50),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: _isSendingOtp
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 2,
                            ),
                          )
                        : Text(
                            'Kirim Kode OTP via WhatsApp',
                            style: GoogleFonts.kanit(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                  )
                : Column(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        decoration: BoxDecoration(
                          color: _getOtpValidityColor().withOpacity(0.15),
                          border: Border.all(color: _getOtpValidityColor(), width: 1.5),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              _otpValidityCountdown <= 30 ? Icons.warning_rounded : Icons.schedule_rounded,
                              color: _getOtpValidityColor(),
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              _getOtpValidityText(),
                              style: GoogleFonts.kanit(
                                color: _getOtpValidityColor(),
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 15),
                      TextField(
                        controller: _otpController,
                        keyboardType: TextInputType.number,
                        maxLength: 6,
                        decoration: InputDecoration(
                          labelText: 'Masukkan Kode OTP',
                          labelStyle: GoogleFonts.kanit(),
                          prefixIcon: const Icon(Icons.lock_outline_rounded),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          counterText: '',
                        ),
                      ),
                      const SizedBox(height: 10),

                      ElevatedButton(
                        onPressed: _isVerifying ? null : _verifyOtp,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: primaryOrange,
                          minimumSize: const Size(double.infinity, 50),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        child: _isVerifying
                            ? const CircularProgressIndicator(
                                color: Colors.white,
                              )
                            : Text(
                                'Verifikasi',
                                style: GoogleFonts.kanit(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                      ),
                      const SizedBox(height: 10),

                      TextButton(
                        onPressed: _resendCountdown == 0 ? _sendOtp : null,
                        child: Text(
                          _resendCountdown == 0
                              ? 'Kirim Ulang Kode OTP'
                              : 'Kirim ulang dalam $_resendCountdown detik',
                          style: GoogleFonts.kanit(
                            color: _resendCountdown == 0
                                ? primaryOrange
                                : Colors.grey,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
          ],
        ),
      ),
    );
  }
}
