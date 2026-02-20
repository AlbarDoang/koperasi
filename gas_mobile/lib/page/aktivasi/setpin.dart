// ignore_for_file: library_private_types_in_public_api

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/login.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/page/dashboard.dart';

class SetPinPage extends StatefulWidget {
  const SetPinPage({Key? key}) : super(key: key);

  @override
  State<SetPinPage> createState() => _SetPinPageState();
}

class _SetPinPageState extends State<SetPinPage> {
  final Color primaryOrange = const Color(0xFFFF4D00);
  final TextEditingController _pinController = TextEditingController();
  final TextEditingController _confirmPinController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _pinController.dispose();
    _confirmPinController.dispose();
    super.dispose();
  }

  Future<void> _submitPin() async {
    final pin = _pinController.text.trim();
    final pinConfirm = _confirmPinController.text.trim();

    // Validasi PIN
    if (pin.length != 6 || pinConfirm.length != 6) {
      NotificationHelper.showError('PIN harus 6 digit!');
      return;
    }

    if (pin != pinConfirm) {
      NotificationHelper.showError('PIN tidak cocok. Silakan coba lagi.');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      // Get user data dari preferences
      final user = await EventPref.getUser();
      if (user == null || user.id == null) {
        NotificationHelper.showError('Data user tidak ditemukan. Silakan login kembali.');
        return;
      }

      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.setPin),
        body: {
          'user_id': user.id!,
          'pin': pin,
          'pin_confirm': pinConfirm,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        NotificationHelper.showSuccess(message);
        
        if (!mounted) return;
        Future.delayed(const Duration(seconds: 2), () async {
          if (mounted) {
            // Refresh profile and navigate to dashboard
            final user = await EventPref.getUser();
            if (user != null) {
              // Attempt to fetch fresh profile (if endpoint available)
              final fresh = await EventDB.getProfilLengkap(user.id ?? '');
              if (fresh != null) {
                await EventPref.saveUser(fresh);
              }
            }
            // Navigate to dashboard
            Navigator.pushAndRemoveUntil(
              context,
              MaterialPageRoute(builder: (context) => const Dashboard()),
              (route) => false,
            );
          }
        });
      } else {
        NotificationHelper.showError(message);
      }
    } on TimeoutException {
      NotificationHelper.showError('Request timeout - Server tidak merespons');
    } catch (e) {
      NotificationHelper.showError('Gagal menyimpan PIN: $e');
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  Widget _buildPinField(TextEditingController controller, String label) {
    return TextField(
      controller: controller,
      obscureText: true,
      keyboardType: TextInputType.number,
      maxLength: 6,
      decoration: InputDecoration(
        labelText: label,
        labelStyle: GoogleFonts.kanit(),
        prefixIcon: const Icon(Icons.lock_outline),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        counterText: '',
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: Text(
          'Atur PIN Transaksi',
          style: GoogleFonts.kanit(
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
        backgroundColor: primaryOrange,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(
          color: Colors.white, // ⬅️ Ubah warna ikon back jadi putih
        ),
      ),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Text(
              'Buat PIN untuk keamanan akun kamu. Gunakan 6 digit angka.',
              style: GoogleFonts.kanit(color: Colors.black87, fontSize: 14),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 30),
            _buildPinField(_pinController, 'Masukkan PIN (6 digit)'),
            const SizedBox(height: 20),
            _buildPinField(_confirmPinController, 'Konfirmasi PIN'),
            const SizedBox(height: 30),
            ElevatedButton(
              onPressed: _isSubmitting ? null : _submitPin,
              style: ElevatedButton.styleFrom(
                backgroundColor: primaryOrange,
                minimumSize: const Size(double.infinity, 50),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : Text(
                      'LANJUT',
                      style: GoogleFonts.kanit(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
