import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'dart:async';
import 'dart:convert';

// Helper to show top-styled notifications consistently
void _showSuccessMessage(String message) {
  Get.snackbar(
    'Sukses',
    message,
    snackPosition: SnackPosition.TOP,
    backgroundColor: const Color(0xFF4CAF50),
    colorText: Colors.white,
    duration: const Duration(seconds: 2),
  );
}

void _showErrorMessage(String message) {
  Get.snackbar(
    'Error',
    message,
    snackPosition: SnackPosition.TOP,
    backgroundColor: Colors.redAccent,
    colorText: Colors.white,
    duration: const Duration(seconds: 2),
  );
}

class ForgotPasswordController extends GetxController {
  final RxString noHp = ''.obs;
  final RxString otp = ''.obs;
  final RxString passwordBaru = ''.obs;
  final RxString passwordKonfirmasi = ''.obs;
  
  final RxBool isLoadingForgotPassword = false.obs;
  final RxBool isLoadingVerifyOtp = false.obs;
  final RxBool isLoadingResetPassword = false.obs;
  
  final RxInt currentStep = 0.obs;
  final RxInt resendSeconds = 0.obs;
  final RxInt otpValiditySeconds = 0.obs;
  Timer? _resendTimer;
  Timer? _otpValidityTimer;

  Future<void> requestOTP() async {
    final phoneNumber = noHp.value.trim();
    
    if (phoneNumber.isEmpty) {
      _showErrorMessage('Nomor HP wajib diisi');
      return;
    }

    isLoadingForgotPassword.value = true;

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse('${Api.baseUrl}/forgot_password.php'),
        body: {'no_hp': phoneNumber},
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true || payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        _showSuccessMessage(message);
        currentStep.value = 1;
        // start resend countdown (60 seconds)
        _startResendTimer(60);
        // start OTP validity countdown (120 seconds / 2 minutes)
        _startOtpValidityTimer(120);
      } else {
        _showErrorMessage(message);
      }
    } on TimeoutException {
      _showErrorMessage('Request timeout - Server tidak merespons');
    } catch (e) {
      _showErrorMessage('Gagal meminta OTP: $e');
    } finally {
      isLoadingForgotPassword.value = false;
    }
  }

  void _startResendTimer(int seconds) {
    _resendTimer?.cancel();
    resendSeconds.value = seconds;
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (resendSeconds.value <= 0) {
        timer.cancel();
        resendSeconds.value = 0;
      } else {
        resendSeconds.value -= 1;
      }
    });
  }

  void _stopResendTimer() {
    _resendTimer?.cancel();
    _resendTimer = null;
    resendSeconds.value = 0;
  }

  void _startOtpValidityTimer(int seconds) {
    _otpValidityTimer?.cancel();
    otpValiditySeconds.value = seconds;
    _otpValidityTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (otpValiditySeconds.value <= 0) {
        timer.cancel();
        otpValiditySeconds.value = 0;
        _showErrorMessage('Kode OTP telah kadaluarsa. Silakan minta OTP baru.');
        // Reset back to step 0 (request OTP page)
        currentStep.value = 0;
        otp.value = '';
      } else {
        otpValiditySeconds.value -= 1;
      }
    });
  }

  void _stopOtpValidityTimer() {
    _otpValidityTimer?.cancel();
    _otpValidityTimer = null;
    otpValiditySeconds.value = 0;
  }

  Future<void> verifyOTP() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();

    if (otpCode.isEmpty) {
      _showErrorMessage('Kode OTP wajib diisi');
      return;
    }

    if (otpCode.length != 6) {
      _showErrorMessage('OTP harus 6 digit');
      return;
    }

    if (phoneNumber.isEmpty) {
      _showErrorMessage('Nomor HP tidak ditemukan');
      return;
    }

    isLoadingVerifyOtp.value = true;

    try {
      // Call backend to verify OTP without resetting password yet
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.verifyOtpReset),
        body: {
          'no_hp': phoneNumber,
          'otp': otpCode,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true || payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        // Stop OTP validity timer immediately on success
        _stopOtpValidityTimer();
        
        // Show success message using reliable Get.snackbar (overlay-safe)
        Get.snackbar(
          'Sukses',
          'Kode OTP berhasil diverifikasi. Silakan buat password baru Anda.',
          snackPosition: SnackPosition.TOP,
          backgroundColor: const Color(0xFF4CAF50),
          colorText: Colors.white,
          duration: const Duration(seconds: 2),
        );
        
        // Navigate after delay to let snackbar show
        await Future.delayed(const Duration(milliseconds: 500));
        currentStep.value = 2;
      } else {
        _showErrorMessage(message);
      }
    } on TimeoutException {
      _showErrorMessage('Request timeout - Server tidak merespons');
    } catch (e) {
      _showErrorMessage('Gagal verifikasi OTP: $e');
    } finally {
      isLoadingVerifyOtp.value = false;
    }
  }

  Future<void> resetPassword() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();
    final newPassword = passwordBaru.value.trim();
    final confirmPassword = passwordKonfirmasi.value.trim();

    if (phoneNumber.isEmpty || otpCode.isEmpty || newPassword.isEmpty || confirmPassword.isEmpty) {
      _showErrorMessage('Semua field wajib diisi');
      return;
    }

    if (newPassword.length < 6) {
      _showErrorMessage('Password minimal 6 karakter');
      return;
    }

    if (newPassword != confirmPassword) {
      _showErrorMessage('Password tidak cocok');
      return;
    }

    isLoadingResetPassword.value = true;

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.resetPassword),
        body: {
          'no_hp': phoneNumber,
          'otp': otpCode,
          'password_baru': newPassword,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true || payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        // Show success message using reliable Get.snackbar
        Get.snackbar(
          'Sukses',
          '✅ Password berhasil direset! Silakan login dengan password baru Anda.',
          snackPosition: SnackPosition.TOP,
          backgroundColor: const Color(0xFF4CAF50),
          colorText: Colors.white,
          duration: const Duration(seconds: 3),
        );
        await Future.delayed(const Duration(seconds: 2));
        Get.offAllNamed('/login');
      } else {
        _showErrorMessage(message);
      }
    } on TimeoutException {
      _showErrorMessage('Request timeout - Server tidak merespons');
    } catch (e) {
      _showErrorMessage('Gagal reset password: $e');
    } finally {
      isLoadingResetPassword.value = false;
    }
  }

  void resetForm() {
    noHp.value = '';
    otp.value = '';
    passwordBaru.value = '';
    passwordKonfirmasi.value = '';
    currentStep.value = 0;
    _stopResendTimer();
  }

  @override
  void onClose() {
    _stopResendTimer();
    _stopOtpValidityTimer();
    super.onClose();
  }
}
