// ignore_for_file: library_private_types_in_public_api
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:tabungan/page/daftar/register2.dart';
import 'package:tabungan/page/orange_header.dart';

import 'package:tabungan/utils/custom_toast.dart';
import 'dart:convert';
import 'dart:io';
import 'dart:async';
import 'package:http/http.dart' as http;
import 'package:tabungan/config/api.dart';

// Top-level DateInputFormatter to format as DD/MM/YYYY while typing.
// - inserts '/' after 2 and 4 digits
// - limits to 8 digits (DDMMYYYY)
// - preserves cursor at end
class DateInputFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    // Keep only digits
    String digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');

    if (digits.length > 8) digits = digits.substring(0, 8);

    String formatted = '';

    if (digits.isEmpty) {
      formatted = '';
    } else if (digits.length <= 2) {
      // when exactly 2 digits, append slash as requested
      formatted = digits;
      if (digits.length == 2) formatted += '/';
    } else if (digits.length <= 4) {
      formatted = digits.substring(0, 2) + '/' + digits.substring(2);
      if (digits.length == 4) formatted += '/';
    } else {
      // 5..8 digits -> dd/mm/yyyy (year part may be shorter than 4)
      final day = digits.substring(0, 2);
      final month = digits.substring(2, 4);
      final year = digits.substring(4);
      formatted = '$day/$month/$year';
    }

    // Ensure selection is at the end
    return TextEditingValue(
      text: formatted,
      selection: TextSelection.collapsed(offset: formatted.length),
    );
  }
}

// Helper: convert DD/MM/YYYY (or D/M/YYYY) into YYYY-MM-DD
String convertToYMD(String input) {
  try {
    final parts = input.split('/');
    final day = parts[0].padLeft(2, '0');
    final month = parts[1].padLeft(2, '0');
    final year = parts[2];
    return "$year-$month-$day";
  } catch (e) {
    return "";
  }
}

class Register1Page extends StatefulWidget {
  const Register1Page({Key? key}) : super(key: key);

  @override
  _Register1PageState createState() => _Register1PageState();
}

class _Register1PageState extends State<Register1Page> {
  final Color primaryOrange = const Color(0xFFFF4D00);

  final _formKey = GlobalKey<FormState>();
  final TextEditingController _phoneCtrl = TextEditingController();
  final TextEditingController _passCtrl = TextEditingController();
  final TextEditingController _confirmPassCtrl = TextEditingController();
  final TextEditingController _nameCtrl = TextEditingController();
  final TextEditingController _addressCtrl = TextEditingController();
  final TextEditingController _birthDateCtrl = TextEditingController();

  // use controller _birthDateCtrl to keep entered date; no separate DateTime stored

  bool _obscurePass = true;
  bool _obscureConfirm = true;
  bool _agreeTerms = false;
  bool _isLoading = false;

  // ================================
  // AUTO INPUT FORMATTER "dd/MM/yyyy"
  // ================================
  // (formatter is implemented as a top-level class above)

  // =======================
  // INPUT DECORATION GLOBAL
  // =======================
  InputDecoration _inputDecoration(String label, IconData icon) {
    return InputDecoration(
      labelText: label,
      labelStyle: GoogleFonts.kanit(color: primaryOrange),
      prefixIcon: Icon(icon, color: primaryOrange),
      contentPadding: const EdgeInsets.symmetric(vertical: 14, horizontal: 12),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: primaryOrange),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: primaryOrange),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: primaryOrange, width: 1.5),
      ),
    );
  }

  // =======================
  // DATE PICKER FIX FORMAT
  // =======================
  Future<void> _pickDate() async {
    // If a keyboard is open, close it before opening the date picker.
    FocusScope.of(context).unfocus();

    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 20),
      firstDate: DateTime(1900),
      lastDate: now,
    );

    if (picked != null) {
      // Always format as dd/MM/yyyy regardless of locale
      _birthDateCtrl.text = DateFormat('dd/MM/yyyy').format(picked);
      setState(() {});
    }
  }

  // =======================
  // NEXT BUTTON LOGIC (kirim ke API register-tahap1)
  // =======================
  Future<void> _submitRegisterTahap1() async {
    // Validate form fields first
    if (!_formKey.currentState!.validate()) return;

    // Ensure agreement checkbox is checked
    if (!_agreeTerms) {
      CustomToast.error(
        context,
        'Harap centang "Saya setuju dengan ketentuan perusahaan"',
      );
      return;
    }

    // Prepare trimmed field values
    final phone = _phoneCtrl.text.trim();
    final password = _passCtrl.text;
    final name = _nameCtrl.text.trim();
    final address = _addressCtrl.text.trim();
    final birthInput = _birthDateCtrl.text.trim();

    // Do not send empty values
    if (phone.isEmpty ||
        password.isEmpty ||
        name.isEmpty ||
        address.isEmpty ||
        birthInput.isEmpty) {
      CustomToast.error(context, 'Semua field wajib diisi');
      return;
    }

    setState(() => _isLoading = true);

    // Convert tanggal lahir to YYYY-MM-DD expected by backend using helper
    final tanggalLahirForBackend = convertToYMD(_birthDateCtrl.text.trim());
    if (tanggalLahirForBackend.isEmpty) {
      setState(() => _isLoading = false);
      CustomToast.error(
        context,
        'Format tanggal tidak valid. Gunakan dd/MM/yyyy.',
      );
      return;
    }

    // Build body using field names required by backend
    final Map<String, String> body = {
      'no_hp': phone,
      'kata_sandi': password,
      'nama_lengkap': name,
      'alamat_domisili': address,
      'tanggal_lahir': tanggalLahirForBackend,
      'setuju_syarat': _agreeTerms ? '1' : '0',
    };

    try {
      final uri = Uri.parse(Api.registerTahap1);

      // Log detailed request information
      print('================================================================================');
      print('[${DateTime.now().toIso8601String()}] 📤 REGISTER TAHAP 1 REQUEST');
      print('   URL: ${uri.toString()}');
      print('   Method: POST (MultipartRequest)');
      print('   Fields to send:');
      for (var key in body.keys) {
        final value = body[key];
        // Hide password in logs
        if (key.contains('sandi') || key.contains('password')) {
          print('      $key: [HIDDEN]');
        } else {
          print('      $key: $value');
        }
      }
      print('   Timeout: 30s');

      final request = http.MultipartRequest('POST', uri);
      request.fields.addAll(body);

      final streamed = await request.send().timeout(
        const Duration(seconds: 30),
      );
      final respStr = await streamed.stream.bytesToString();

      // Log response details
      print('\n📥 RESPONSE RECEIVED:');
      print('   Status Code: ${streamed.statusCode}');
      print('   Status Reason: ${streamed.reasonPhrase}');
      print('   Response Headers: ${streamed.headers}');
      print('   Body Length: ${respStr.length} bytes');
      
      // Try to parse and display response
      try {
        final parsed = jsonDecode(respStr);
        print('   Body (JSON): $parsed');
      } catch (e) {
        print('   Body (RAW - Not JSON): ${respStr.substring(0, (respStr.length > 500) ? 500 : respStr.length)}${respStr.length > 500 ? '...' : ''}');
      }
      print('================================================================================\n');

      // CRITICAL: Print RAW response BEFORE jsonDecode
      print('📋 RAW RESPONSE BODY (BEFORE JSON PARSE):');
      print('$respStr');
      print('================================================================================\n');

      if (streamed.statusCode == 200) {
        dynamic resp;
        try {
          resp = jsonDecode(respStr);
        } catch (jsonError) {
          print('❌ JSON DECODE ERROR: $jsonError');
          print('Response is not valid JSON!');
          print('================================================================================\n');
          if (mounted) CustomToast.error(context, 'Server response tidak valid JSON: $respStr');
          setState(() => _isLoading = false);
          return;
        }

        final success =
            (resp is Map &&
            (resp['success'] == true || resp['status'] == 'success'));

        if (success) {
          // Extract id_pengguna if provided
          String? idPengguna;
          if (resp.containsKey('id_pengguna'))
            idPengguna = resp['id_pengguna']?.toString();
          if (idPengguna == null &&
              resp.containsKey('data') &&
              resp['data'] is Map) {
            idPengguna =
                resp['data']['id_pengguna']?.toString() ??
                resp['data']['id']?.toString();
          }

          final Map<String, dynamic> reg1Data = {
            'no_hp': phone,
            'kata_sandi': password,
            'nama_lengkap': name,
            'alamat_domisili': address,
            'tanggal_lahir': tanggalLahirForBackend,
          };
          if (idPengguna != null) {
            reg1Data['id_pengguna'] = idPengguna;
            reg1Data['pengguna_id'] = idPengguna; // legacy key expected by tahap 2
          }

          // ignore: avoid_print
          print('✅✅✅ REGISTER TAHAP 1 SUKSES! Akan tampil notifikasi HIJAU');
          CustomToast.success(context, 'Pendaftaran tahap 1 berhasil');

          // Navigate to tahap 2 and pass reg1Data
          if (mounted) {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => Register2Page(reg1Data: reg1Data),
              ),
            );
          }
        } else {
          // Show backend error message if provided
          print('❌ REGISTER TAHAP 1 GAGAL! Response tidak success:');
          print('   Full Response: $resp');
          String message = 'Pendaftaran gagal';
          if (resp is Map) {
            message =
                resp['message']?.toString() ??
                resp['error']?.toString() ??
                jsonEncode(resp);
          } else {
            message = respStr;
          }
          if (mounted) CustomToast.error(context, message);
        }
      } else {
        // Non-200: show full response for debug
        print('❌ STATUS CODE BUKAN 200: ${streamed.statusCode}');
        print('   Response: $respStr');
        print('================================================================================\n');
        final msg = 'Server Error: ${streamed.statusCode}\n$respStr';
        if (mounted) CustomToast.error(context, msg);
      }
    } on SocketException catch (e) {
      print('❌ SOCKET EXCEPTION: ${e.message}');
      print('   Error Code: ${e.osError?.errorCode ?? "N/A"}');
      print('   Address: ${e.address?.address ?? "N/A"}');
      print('   Port: ${e.port ?? "N/A"}');
      print('================================================================================\n');
      if (mounted) CustomToast.error(context, 'Tidak dapat menjangkau server: ${e.message}. Pastikan server API berjalan dan perangkat terhubung ke jaringan yang sama.');
    } on TimeoutException catch (e) {
      print('❌ TIMEOUT EXCEPTION: ${e.toString()}');
      print('================================================================================\n');
      if (mounted) CustomToast.error(context, '⏱️ Request timeout - Server tidak merespons dalam 30 detik');
    } on HttpException catch (e) {
      print('❌ HTTP EXCEPTION: ${e.message}');
      print('================================================================================\n');
      if (mounted) CustomToast.error(context, 'HTTP error: ${e.message}');
    } catch (e) {
      print('❌ EXCEPTION: ${e.toString()}');
      print('   Type: ${e.runtimeType}');
      print('================================================================================\n');
      if (mounted) CustomToast.error(context, '❌ Error: ${e.toString()}');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _onNext() {
    _submitRegisterTahap1();
  }

  // =======================
  // WIDGET UTAMA
  // =======================
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: OrangeHeader(title: "Daftar"),
      resizeToAvoidBottomInset: true,

      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 30),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              Text(
                'Lengkapi Data Akun',
                style: GoogleFonts.poppins(
                  color: primaryOrange,
                  fontWeight: FontWeight.w600,
                  fontSize: 20,
                ),
              ),
              const SizedBox(height: 20),

              // NOMOR HP
              TextFormField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: _inputDecoration('Nomor HP', Icons.phone_android),
                validator: (v) {
                  if (v == null || v.isEmpty) return 'Nomor HP wajib diisi';
                  if (v.length < 9) return 'Minimal 9 digit';
                  return null;
                },
              ),
              const SizedBox(height: 12),

              // PASSWORD
              TextFormField(
                controller: _passCtrl,
                obscureText: _obscurePass,
                decoration: _inputDecoration('Kata Sandi', Icons.lock_outline)
                    .copyWith(
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePass
                              ? Icons.visibility_off
                              : Icons.visibility,
                          color: primaryOrange,
                        ),
                        onPressed: () =>
                            setState(() => _obscurePass = !_obscurePass),
                      ),
                    ),
                validator: (v) {
                  if (v == null || v.isEmpty) return 'Kata Sandi wajib diisi';
                  if (v.length < 6) return 'Minimal 6 karakter';
                  return null;
                },
              ),
              const SizedBox(height: 12),

              // KONFIRMASI PASSWORD
              TextFormField(
                controller: _confirmPassCtrl,
                obscureText: _obscureConfirm,
                decoration:
                    _inputDecoration(
                      'Konfirmasi Kata Sandi',
                      Icons.lock,
                    ).copyWith(
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscureConfirm
                              ? Icons.visibility_off
                              : Icons.visibility,
                          color: primaryOrange,
                        ),
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
                      ),
                    ),
                validator: (v) {
                  if (v == null || v.isEmpty) return 'Konfirmasi wajib diisi';
                  if (v != _passCtrl.text) return 'Kata Sandi tidak cocok';
                  return null;
                },
              ),
              const SizedBox(height: 12),

              // NAMA LENGKAP
              TextFormField(
                controller: _nameCtrl,
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[a-zA-Z\s]')),
                ],
                decoration: _inputDecoration('Nama Lengkap', Icons.person),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'Nama wajib diisi' : null,
              ),
              const SizedBox(height: 12),

              // ALAMAT
              TextFormField(
                controller: _addressCtrl,
                maxLines: 3,
                decoration: _inputDecoration('Alamat Domisili', Icons.home),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'Alamat wajib diisi' : null,
              ),
              const SizedBox(height: 12),

              // ==========================
              // FIX TANGGAL LAHIR + FORMAT
              // - allow typing digits (e.g. 07112007 -> 07/11/2007)
              // - provide a calendar icon to open the date picker
              // ==========================
              TextFormField(
                controller: _birthDateCtrl,
                keyboardType: TextInputType.number,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  DateInputFormatter(),
                ],
                decoration:
                    _inputDecoration(
                      'Tanggal Lahir',
                      Icons.calendar_today,
                    ).copyWith(
                      suffixIcon: IconButton(
                        icon: Icon(Icons.calendar_today, color: primaryOrange),
                        onPressed: _pickDate,
                      ),
                    ),
                // Do NOT set onTap so user can place cursor and type.
                validator: (v) {
                  if (v == null || v.isEmpty)
                    return 'Tanggal lahir wajib diisi';
                  if (!RegExp(r'^\d{2}/\d{2}/\d{4}$').hasMatch(v)) {
                    return 'Format tanggal harus dd/MM/yyyy';
                  }
                  return null;
                },
              ),

              const SizedBox(height: 20),

              Row(
                children: [
                  Checkbox(
                    activeColor: primaryOrange,
                    value: _agreeTerms,
                    onChanged: (v) => setState(() => _agreeTerms = v ?? false),
                  ),
                  Expanded(
                    child: Text(
                      'Saya setuju dengan ketentuan perusahaan',
                      style: GoogleFonts.kanit(fontSize: 14),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),

      bottomNavigationBar: Container(
        padding: const EdgeInsets.all(16),
        child: SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton(
            onPressed: _isLoading ? null : _onNext,
            style: ElevatedButton.styleFrom(
              backgroundColor: primaryOrange,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            child: Text(
              _isLoading ? 'MENGIRIM...' : 'LANJUT',
              style: GoogleFonts.poppins(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
