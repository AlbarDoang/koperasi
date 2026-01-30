import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/page/orange_header.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/event/event_pref.dart';

class GantiPwPage extends StatefulWidget {
  const GantiPwPage({super.key});

  @override
  State<GantiPwPage> createState() => _GantiPwPageState();
}

class _GantiPwPageState extends State<GantiPwPage> {
  final _formKey = GlobalKey<FormState>();
  final _passwordLamaController = TextEditingController();
  final _passwordBaruController = TextEditingController();
  final _konfirmasiPasswordController = TextEditingController();

  bool _isObscure = true;
  bool _isLoading = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: OrangeHeader(title: "Ganti Password"),
      backgroundColor: const Color(0xFFF9F9F9),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Masukkan Password Lama'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _passwordLamaController,
                obscureText: _isObscure,
                decoration: InputDecoration(
                  hintText: '********',
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
                    return 'Masukkan password lama kamu';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),
              const Text('Masukkan Password Baru'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _passwordBaruController,
                obscureText: _isObscure,
                decoration: const InputDecoration(
                  hintText: '********',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Masukkan password baru';
                  }
                  if (value.length < 6) {
                    return 'Password minimal 6 karakter';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 20),
              const Text('Konfirmasi Password Baru'),
              const SizedBox(height: 8),
              TextFormField(
                controller: _konfirmasiPasswordController,
                obscureText: _isObscure,
                decoration: const InputDecoration(
                  hintText: '********',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value != _passwordBaruController.text) {
                    return 'Password tidak cocok';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _isLoading 
                      ? const Color(0xFFFF5F0A).withOpacity(0.6)
                      : const Color(0xFFFF5F0A),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onPressed: _isLoading ? null : () async {
                    if (_formKey.currentState!.validate()) {
                      setState(() => _isLoading = true);
                      
                      try {
                        final user = await EventPref.getUser();
                        if (user == null || user.id == null) {
                          CustomToast.error(context, 'User tidak ditemukan');
                          return;
                        }

                        final ok = await EventDB.changePassword(
                          user.id!,
                          _passwordLamaController.text.trim(),
                          _passwordBaruController.text.trim(),
                          _konfirmasiPasswordController.text.trim(),
                          showSuccessToast: false,
                        );

                        if (ok) {
                          // Show an explicit success toast here so user definitely
                          // sees the confirmation before we navigate away.
                          CustomToast.success(context, 'Kata sandi berhasil diubah');

                          // Wait a short moment so the toast is visible, then pop.
                          await Future.delayed(const Duration(milliseconds: 900));
                          Get.back();
                        }
                      } finally {
                        setState(() => _isLoading = false);
                      }
                    }
                  },
                  child: _isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                        ),
                      )
                    : const Text(
                        'Simpan Password Baru',
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
