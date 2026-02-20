// ignore_for_file: library_private_types_in_public_api

import 'package:tabungan/src/file_io.dart';
import 'package:flutter/foundation.dart';
import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:tabungan/login.dart';
import 'package:tabungan/page/orange_header.dart';

import 'package:tabungan/services/notification_service.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:tabungan/config/api.dart';

class Register2Page extends StatefulWidget {
  const Register2Page({Key? key, this.reg1Data}) : super(key: key);
  final Map<String, dynamic>? reg1Data;

  @override
  State<Register2Page> createState() => _Register2PageState();
}

class _Register2PageState extends State<Register2Page>
    with SingleTickerProviderStateMixin {
  final Color primaryOrange = const Color(0xFFFF4D00);
  // final GlobalKey<FormState> _formKey = GlobalKey<FormState>(); // Unused

  File? _fotoKTP;
  File? _fotoSelfie;
  final ImagePicker _picker = ImagePicker();

  late AnimationController _fadeController;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );
  }

  @override
  void dispose() {
    _fadeController.dispose();
    super.dispose();
  }

  Future<void> _pickImage(bool isKTP) async {
    final XFile? image = await _picker.pickImage(source: ImageSource.camera);
    if (image != null) {
      setState(() {
        if (isKTP) {
          _fotoKTP = File(image.path);
        } else {
          _fotoSelfie = File(image.path);
        }
      });
    }
  }

  void _showCustomBanner(String message) {
    // Display success/error notification menggunakan NotificationService
    final isSuccess = message.toLowerCase().contains('berhasil');
    
    if (kDebugMode) {
      print('🎯 _showCustomBanner CALLED: isSuccess=$isSuccess');
      print('   Message: $message');
    }
    
    if (mounted) {
      if (isSuccess) {
        NotificationHelper.showSuccess(message);
      } else {
        NotificationHelper.showError(message);
      }
    }
  }

  /// Upload foto KTP dan Selfie via multipart/form-data
  /// Kirim ke: http://172.168.80.236/gas/gas_web/flutter_api/register_tahap2.php
  Future<bool> uploadAndSave({
    required File fotoKTP,
    required File fotoSelfie,
    required String idPengguna,
  }) async {
    final timestamp = DateTime.now().toIso8601String();
    
    try {
      if (kDebugMode) {
        print('\n' + ('='*80));
        print('[$timestamp] 📤 MULTIPART REQUEST (Register Tahap 2)');
        print('   URL: ${Api.registerTahap2}');
        print('   Method: POST (Multipart Form Data)');
        print('   Form Fields: id_pengguna=$idPengguna');
        print('   Files:');
        print('     - foto_ktp: ${fotoKTP.path}');
        print('     - foto_selfie: ${fotoSelfie.path}');
      }
      
      // Create multipart request
      var request = http.MultipartRequest(
        'POST',
        Uri.parse(Api.registerTahap2),
      );

      // Add form fields
      request.fields['id_pengguna'] = idPengguna;

      // Add file fields
      request.files.add(
        await http.MultipartFile.fromPath(
          'foto_ktp',
          fotoKTP.path,
        ),
      );

      request.files.add(
        await http.MultipartFile.fromPath(
          'foto_selfie',
          fotoSelfie.path,
        ),
      );

      if (kDebugMode) {
        print('   Timeout: 60s');
        print('   Sending...');
      }

      // Send request with timeout
      var response = await request.send().timeout(
        const Duration(seconds: 60),
        onTimeout: () {
          if (kDebugMode) {
            print('[$timestamp] ⏱️ TIMEOUT: Request timeout setelah 60s');
            print('='*80 + '\n');
          }
          throw TimeoutException('Request timeout - Server tidak merespons');
        },
      );

      if (kDebugMode) {
        print('\n📥 RESPONSE RECEIVED:');
        print('   Status Code: ${response.statusCode}');
        print('   Status Reason: ${response.reasonPhrase}');
        print('   Headers: ${response.headers}');
      }

      // Parse response
      var responseBody = await response.stream.bytesToString();
      
      if (kDebugMode) {
        print('   Body Length: ${responseBody.length} bytes');
        print('   Body (Raw): ${responseBody.substring(0, responseBody.length > 500 ? 500 : responseBody.length)}');
        if (responseBody.length > 500) {
          print('   ... (${responseBody.length - 500} more bytes)');
        }
      }
      
      // Try to parse JSON
      dynamic jsonResponse;
      try {
        jsonResponse = jsonDecode(responseBody);
        if (kDebugMode) {
          print('   Body (JSON): $jsonResponse');
        }
      } catch (e) {
        if (kDebugMode) {
          print('   ⚠️ JSON Parse Error: $e');
          print('   Raw response: $responseBody');
        }
        _showCustomBanner('❌ Server response bukan JSON valid');
        return false;
      }

      if (kDebugMode) {
        print('='*80 + '\n');
      }

      if (response.statusCode == 200 && jsonResponse['success'] == true) {
        return true;
      }

      // Handle error response
      final errorMessage = jsonResponse['message'] ?? 'Upload gagal';
      _showCustomBanner(errorMessage);
      return false;
    } on SocketException catch (e) {
      final errorMsg = '❌ SOCKET ERROR: ${e.message}';
      final details = 'code: ${e.osError?.errorCode ?? "unknown"}';
      if (kDebugMode) {
        print('[$timestamp] $errorMsg ($details)');
        print('='*80 + '\n');
      }
      _showCustomBanner('$errorMsg - Periksa koneksi jaringan Anda');
      return false;
    } on TimeoutException catch (e) {
      _showCustomBanner('⏱️ ${e.message}');
      return false;
    } catch (e) {
      if (kDebugMode) {
        print('[$timestamp] 💥 ERROR: ${e.runtimeType} - $e');
        print('='*80 + '\n');
      }
      _showCustomBanner('❌ Error: ${e.toString()}');
      return false;
    }
  }

  // Kirim data ke API register-tahap2 (upload foto KTP & selfie)
  Future<void> _validateAndNext() async {
    if (kDebugMode) print('🚀 _validateAndNext() STARTED');
    
    if (_fotoKTP == null) {
      if (kDebugMode) print('   ❌ Foto KTP null');
      _showCustomBanner('Foto KTP wajib diunggah!');
      return;
    }

    if (_fotoSelfie == null) {
      if (kDebugMode) print('   ❌ Foto Selfie null');
      _showCustomBanner('Foto selfie wajib diunggah!');
      return;
    }

    final reg1Data = widget.reg1Data ?? {};
    final idPengguna =
        reg1Data['id_pengguna']?.toString() ??
        reg1Data['pengguna_id']?.toString();

    if (idPengguna == null || idPengguna.isEmpty) {
      if (kDebugMode) print('   ❌ ID Pengguna null/empty');
      _showCustomBanner(
        'ID pengguna tidak ditemukan. Silakan kembali ke tahap sebelumnya.',
      );
      return;
    }

    if (kDebugMode) print('   ✅ Validasi lokal passed, ID: $idPengguna');
    if (mounted) setState(() => _isSubmitting = true);

    try {
      if (kDebugMode) print('   📤 Upload dimulai...');
      
      final uploadSuccess = await uploadAndSave(
        fotoKTP: _fotoKTP!,
        fotoSelfie: _fotoSelfie!,
        idPengguna: idPengguna,
      );

      if (kDebugMode) print('   📥 Upload selesai, success=$uploadSuccess');
      
      if (!uploadSuccess) {
        if (kDebugMode) print('   ❌ Upload failed');
        if (mounted) setState(() => _isSubmitting = false);
        return;
      }

      if (kDebugMode) print('   ✅ Upload SUCCESS! Menampilkan notifikasi...');
      
      if (mounted) {
        final message = 'Verifikasi indentitas berhasil, silahkan aktivasi akun anda';
        _showCustomBanner(message);
        
        // CustomToast default duration = 2s, jadi tunggu minimal 200ms render + 2s display
        if (kDebugMode) print('   ⏳ Waiting 200ms untuk overlay render...');
        await Future.delayed(const Duration(milliseconds: 200));
        
        if (kDebugMode) print('   ⏳ Waiting 2s untuk toast visible...');
        await Future.delayed(const Duration(seconds: 2));
        
        if (kDebugMode) print('   🔀 Navigate ke LoginPage...');
        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (context) => const LoginPage()),
          );
        }
      }
    } catch (e) {
      if (kDebugMode) print('   💥 Exception in _validateAndNext: $e');
      if (mounted) setState(() => _isSubmitting = false);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  Widget _imagePreview(File? file, String placeholder) {
    return Container(
      height: 180,
      width: double.infinity,
      decoration: BoxDecoration(
        border: Border.all(color: primaryOrange),
        borderRadius: BorderRadius.circular(10),
      ),
      alignment: Alignment.center,
      child: file != null
          ? ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: !kIsWeb
                  ? Image.file(
                      file,
                      fit: BoxFit.cover,
                      width: double.infinity,
                    )
                  : Text('Preview tidak tersedia pada web', style: GoogleFonts.kanit(color: Colors.grey)),
            )
          : Text(
              placeholder,
              style: GoogleFonts.kanit(color: Colors.grey),
              textAlign: TextAlign.center,
            ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: OrangeHeader(title: "Daftar"),
      body: SafeArea(
        child: Stack(
          children: [
            SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 120),
              child: Column(
                children: [
                  Center(
                    child: Text(
                      'Verifikasi Identitas',
                      style: GoogleFonts.poppins(
                        color: primaryOrange,
                        fontWeight: FontWeight.w600,
                        fontSize: 20,
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  _imagePreview(_fotoKTP, 'Belum ada foto KTP yang diunggah'),
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: ElevatedButton.icon(
                      onPressed: () => _pickImage(true),
                      icon: const Icon(Icons.photo_camera, color: Colors.white),
                      label: Text(
                        'Upload Foto KTP',
                        style: GoogleFonts.kanit(color: Colors.white),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: primaryOrange,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  _imagePreview(
                    _fotoSelfie,
                    'Belum ada foto selfie dengan KTP',
                  ),
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: ElevatedButton.icon(
                      onPressed: () => _pickImage(false),
                      icon: const Icon(
                        Icons.camera_alt_outlined,
                        color: Colors.white,
                      ),
                      label: Text(
                        'Upload Foto Selfie dengan KTP',
                        style: GoogleFonts.kanit(color: Colors.white),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: primaryOrange,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Tombol Lanjut
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                padding: const EdgeInsets.all(16),
                color: Colors.white,
                child: SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    onPressed: _validateAndNext,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: primaryOrange,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: Text(
                      'LANJUT',
                      style: GoogleFonts.poppins(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                  ),
                ),
              ),
            ),

            if (_isSubmitting)
              Container(
                color: Colors.black38,
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
