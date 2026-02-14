import 'package:flutter/material.dart';
import 'package:get/get.dart';

import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/page/forgot_pin_page.dart';
import 'package:tabungan/controller/forgot_pin_controller.dart';
import 'package:tabungan/page/orange_header.dart';

class UbahPinPage extends StatefulWidget {
  const UbahPinPage({super.key});

  @override
  State<UbahPinPage> createState() => _UbahPinPageState();
}

class _UbahPinPageState extends State<UbahPinPage> {
  final _formKey = GlobalKey<FormState>();
  final _pinLamaController = TextEditingController();
  final _pinBaruController = TextEditingController();
  final _konfirmasiPinController = TextEditingController();

  bool _isObscure = true;
  bool _isProcessing = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: OrangeHeader(title: "Ubah PIN Transaksi"),
      backgroundColor: theme.scaffoldBackgroundColor,
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Masukkan PIN lama'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _pinLamaController,
                keyboardType: TextInputType.number,
                obscureText: _isObscure,
                maxLength: 6,
                decoration: InputDecoration(
                  hintText: '••••••',
                  counterText: '',
                  suffixIcon: IconButton(
                    icon: Icon(
                      _isObscure ? Icons.visibility_off : Icons.visibility,
                    ),
                    onPressed: () {
                      setState(() => _isObscure = !_isObscure);
                    },
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Masukkan PIN lama kamu';
                  }
                  if (value.length < 6) {
                    return 'PIN minimal 6 digit';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),
              const Text('Masukkan PIN baru'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _pinBaruController,
                keyboardType: TextInputType.number,
                obscureText: _isObscure,
                maxLength: 6,
                decoration: const InputDecoration(
                  hintText: '••••••',
                  counterText: '',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Masukkan PIN baru';
                  }
                  if (value.length < 6) {
                    return 'PIN minimal 6 digit';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),
              const Text('Konfirmasi PIN baru'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _konfirmasiPinController,
                keyboardType: TextInputType.number,
                obscureText: _isObscure,
                maxLength: 6,
                decoration: const InputDecoration(
                  hintText: '••••••',
                  counterText: '',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value != _pinBaruController.text) {
                    return 'PIN tidak cocok';
                  }
                  return null;
                },
              ),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () {
                    // Route to Forgot Password flow so user can reset via OTP
                    Get.lazyPut(() => ForgotPinController());
                    Get.to(() => const ForgotPinPage());
                  },
                  child: const Text('Lupa PIN?', style: TextStyle(color: Color(0xFFFF5F0A))),
                ),
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF5F0A),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onPressed: _isProcessing
                      ? null
                      : () async {
                          if (!_formKey.currentState!.validate()) return;
                          setState(() => _isProcessing = true);
                          final user = await EventPref.getUser();
                          if (user == null || (user.id ?? '').isEmpty) {
                            CustomToast.error(context, 'Pengguna belum login');
                            setState(() => _isProcessing = false);
                            return;
                          }

                          final ok = await EventDB.changePin(
                            user.id!,
                            _pinLamaController.text.trim(),
                            _pinBaruController.text.trim(),
                            _konfirmasiPinController.text.trim(),
                            showSuccessToast: false,
                            ctx: context,
                          );

                          setState(() => _isProcessing = false);

                          if (ok) {
                            // Show toast with local context and then navigate back so user sees it
                            CustomToast.success(context, 'PIN berhasil diubah');
                            await Future.delayed(const Duration(milliseconds: 700));
                            Get.back();
                          }
                        },

                  child: const Text(
                    'Simpan PIN Baru',
                    style: TextStyle(color: Colors.white, fontSize: 16),
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
