// ignore_for_file: library_private_types_in_public_api

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:get/get.dart';
import 'package:flutter/services.dart';

import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/page/daftar/register1.dart';
import 'package:tabungan/page/aktivasi/aktivasiakun.dart';
import 'package:tabungan/page/forgot_password_page.dart';
import 'package:tabungan/controller/forgot_password_controller.dart';
import 'package:tabungan/page/privacy_policy_page.dart';
import 'package:tabungan/page/dashboard.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({Key? key}) : super(key: key);

  @override
  _LoginPageState createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage>
    with SingleTickerProviderStateMixin {
  final Color primaryOrange = const Color(0xFFFF4D00);
  final Color whiteColor = const Color(0xFFFFFFFF);

  final TextEditingController _controllerNohp = TextEditingController();
  final TextEditingController _controllerPass = TextEditingController();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  bool _isHidePassword = true;
  final CUser _cUser = Get.put(CUser());
  bool _isLoading = false;

  late AnimationController _animController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    getUser();

    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _fadeAnimation = CurvedAnimation(
      parent: _animController,
      curve: Curves.easeIn,
    );
    _animController.forward();
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  void getUser() async {
    User? user = await EventPref.getUser();
    if (user != null) {
      _cUser.setUser(user);
      _controllerNohp.text = user.no_hp ?? '';
    }
  }

  void _togglePasswordVisibility() {
    setState(() {
      _isHidePassword = !_isHidePassword;
    });
  }

  // =============================================================
  // LOGIN VIA BACKEND API
  // =============================================================
  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();
    setState(() => _isLoading = true);

    final inputHp = _controllerNohp.text.trim();
    final inputPass = _controllerPass.text.trim();

    final loginResult = await EventDB.login(inputHp, inputPass, showSuccessToast: false);

    if (!mounted) return;

    setState(() => _isLoading = false);

    if (loginResult != null && loginResult['user'] != null) {
      final user = loginResult['user'] as User;
      _cUser.setUser(user);

      // Exclusive decision based on pengguna.pin (expressed as needs_set_pin)
      final needsPin = loginResult['needs_set_pin'] == true;

      if (needsPin) {
        // PIN transaksi BELUM diset: notifikasi formal lalu arahkan ke Set PIN, lalu hentikan eksekusi
        CustomToast.success(
          context,
          'Login berhasil. Silakan atur PIN transaksi untuk keamanan akun Anda.',
        );
        Get.offAllNamed('/setpin');
        return;
      } else {
        // PIN transaksi SUDAH diset: notifikasi formal lalu arahkan ke Dashboard, lalu hentikan eksekusi
        CustomToast.success(context, 'Login berhasil. Selamat datang kembali.');
        Get.offAll(() => const Dashboard());
        return;
      }
    }
  }

  void _showKebijakan() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const PrivacyPolicyPage()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final double screenHeight = MediaQuery.of(context).size.height;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: whiteColor,
      body: FadeTransition(
        opacity: _fadeAnimation,
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: screenHeight),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Padding(
                      padding: EdgeInsets.only(
                        top: screenHeight * 0.03,
                        bottom: screenHeight * 0.03,
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Image.asset('assets/logo.png', width: 200),
                          const SizedBox(height: 25),

                          // FORM LOGIN
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 20),
                            child: Container(
                              padding: const EdgeInsets.all(22),
                              decoration: BoxDecoration(
                                color: whiteColor,
                                borderRadius: BorderRadius.circular(14),
                                boxShadow: const [
                                  BoxShadow(
                                    color: Colors.black12,
                                    blurRadius: 10,
                                    offset: Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: Form(
                                key: _formKey,
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      'Masuk',
                                      style: GoogleFonts.kanit(
                                        fontSize: 26,
                                        fontWeight: FontWeight.bold,
                                        color: primaryOrange,
                                      ),
                                    ),
                                    const SizedBox(height: 20),

                                    // Nomor HP
                                    TextFormField(
                                      controller: _controllerNohp,
                                      keyboardType: TextInputType.phone,
                                      inputFormatters: [
                                        FilteringTextInputFormatter.digitsOnly,
                                      ],
                                      validator: (value) {
                                        final v = value?.trim() ?? '';
                                        if (v.isEmpty) return 'Nomor Ponsel wajib diisi';
                                        final digitsOnly = RegExp(r'^\d+$');
                                        if (!digitsOnly.hasMatch(v)) return 'Nomor Ponsel hanya boleh angka';
                                        return null;
                                      },
                                      decoration: InputDecoration(
                                        labelText: 'Nomor Ponsel',
                                        hintText: 'Masukkan Nomor Ponsel',
                                        labelStyle: TextStyle(
                                          color: primaryOrange,
                                        ),
                                        focusedBorder: OutlineInputBorder(
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                          borderSide: BorderSide(
                                            color: primaryOrange,
                                          ),
                                        ),
                                        enabledBorder: OutlineInputBorder(
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                          borderSide: BorderSide(
                                            color: primaryOrange,
                                          ),
                                        ),
                                        prefixIcon: Icon(
                                          Icons.phone_android,
                                          color: primaryOrange,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 15),

                                    // Password
                                    TextFormField(
                                      controller: _controllerPass,
                                      validator: (value) => value == ''
                                          ? 'Kata Sandi wajib diisi'
                                          : null,
                                      obscureText: _isHidePassword,
                                      decoration: InputDecoration(
                                        labelText: 'Kata Sandi',
                                        hintText: 'Masukkan Kata Sandi',
                                        labelStyle: TextStyle(
                                          color: primaryOrange,
                                        ),
                                        focusedBorder: OutlineInputBorder(
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                          borderSide: BorderSide(
                                            color: primaryOrange,
                                          ),
                                        ),
                                        enabledBorder: OutlineInputBorder(
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                          borderSide: BorderSide(
                                            color: primaryOrange,
                                          ),
                                        ),
                                        suffixIcon: GestureDetector(
                                          onTap: _togglePasswordVisibility,
                                          child: Icon(
                                            _isHidePassword
                                                ? Icons.visibility_off
                                                : Icons.visibility,
                                            color: primaryOrange,
                                          ),
                                        ),
                                        prefixIcon: Icon(
                                          Icons.lock_outline,
                                          color: primaryOrange,
                                        ),
                                      ),
                                    ),

                                    // =============================
                                    // LUPA PASSWORD
                                    // =============================
                                    const SizedBox(height: 8),
                                    Align(
                                      alignment: Alignment.centerRight,
                                      child: GestureDetector(
                                        onTap: () {
                                          Get.lazyPut(() => ForgotPasswordController());
                                          Get.to(() => const ForgotPasswordPage());
                                        },
                                        child: Text(
                                          'Lupa Kata Sandi?',
                                          style: GoogleFonts.kanit(
                                            color: primaryOrange,
                                            fontSize: 13,
                                            fontWeight: FontWeight.w600,
                                            decoration:
                                                TextDecoration.underline,
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 18),

                                    // Tombol Login
                                    SizedBox(
                                      width: double.infinity,
                                      child: ElevatedButton(
                                        onPressed: _isLoading
                                            ? null
                                            : _handleLogin,
                                        style: ElevatedButton.styleFrom(
                                          backgroundColor: primaryOrange,
                                          padding: const EdgeInsets.symmetric(
                                            vertical: 14,
                                          ),
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(
                                              10,
                                            ),
                                          ),
                                        ),
                                        child: Text(
                                          'MASUK',
                                          style: GoogleFonts.kanit(
                                            color: whiteColor,
                                            fontSize: 16,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ),

                                    const SizedBox(height: 18),
                                    Divider(
                                      color: primaryOrange.withOpacity(0.4),
                                    ),
                                    const SizedBox(height: 10),

                                    GestureDetector(
                                      onTap: _showKebijakan,
                                      child: Text(
                                        'Kebijakan Privasi',
                                        style: GoogleFonts.kanit(
                                          color: primaryOrange,
                                          fontSize: 13,
                                          decoration: TextDecoration.underline,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 12),

                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.center,
                                      children: [
                                        GestureDetector(
                                          onTap: () => Get.to(
                                            () => const Register1Page(),
                                          ),
                                          child: Row(
                                            children: [
                                              Icon(
                                                Icons.person_add,
                                                color: primaryOrange,
                                                size: 22,
                                              ),
                                              const SizedBox(width: 6),
                                              Text(
                                                'Daftar',
                                                style: GoogleFonts.kanit(
                                                  color: primaryOrange,
                                                  fontSize: 14,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                        Container(
                                          width: 1.2,
                                          height: 22,
                                          margin: const EdgeInsets.symmetric(
                                            horizontal: 16,
                                          ),
                                          color: primaryOrange.withOpacity(0.7),
                                        ),
                                        GestureDetector(
                                          onTap: () => Get.to(
                                            () => const AktivasiAkunPage(),
                                          ),
                                          child: Row(
                                            children: [
                                              Icon(
                                                Icons.verified_user,
                                                color: primaryOrange,
                                                size: 22,
                                              ),
                                              const SizedBox(width: 5),
                                              Text(
                                                'Aktivasi',
                                                style: GoogleFonts.kanit(
                                                  color: primaryOrange,
                                                  fontSize: 14,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),

                                    const SizedBox(height: 10),

                                    Text(
                                      'Versi 1.0.1',
                                      style: GoogleFonts.kanit(
                                        fontSize: 13,
                                        color: primaryOrange,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    Container(
                      height: 70,
                      width: double.infinity,
                      decoration: const BoxDecoration(
                        color: Color(0xFFFF4D00),
                        borderRadius: BorderRadius.only(
                          topLeft: Radius.circular(300),
                          topRight: Radius.circular(300),
                        ),
                      ),
                      alignment: Alignment.center,
                      child: const Text(
                        '© PT. Gusti Global Group',
                        style: TextStyle(color: Colors.white, fontSize: 14),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            if (_isLoading)
              Container(
                color: Colors.black54,
                child: const Center(
                  child: CircularProgressIndicator(color: Colors.white),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
