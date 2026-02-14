import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'package:tabungan/services/notification_service.dart';
import 'dart:async';
import 'dart:convert';

// Helper to show top-styled notifications safely
Future<void> _showSuccessMessage(String message, {Duration duration = const Duration(seconds: 2)}) async {
  NotificationService.showSuccess(message);
}

Future<void> _showErrorMessage(String message, {Duration duration = const Duration(seconds: 2)}) async {
  NotificationService.showError(message);
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
      await _showErrorMessage('Nomor HP wajib diisi');
      return;
    }

    isLoadingForgotPassword.value = true;

    try {
      if (kDebugMode) {
        print('📞 Requesting password reset OTP...');
      }
      
      final response = await http_client.HttpHelper.post(
        Uri.parse('${Api.baseUrl}/forgot_password.php'),
        body: {'no_hp': phoneNumber},
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['success'] == true || payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        if (kDebugMode) {
          print('✅ Password reset OTP requested successfully');
          print('   Message: $message');
        }
        
        await _showSuccessMessage(message, duration: const Duration(seconds: 3));
        currentStep.value = 1;
        // start resend countdown (60 seconds)
        _startResendTimer(60);
        // start OTP validity countdown (60 seconds / 1 menit)
        _startOtpValidityTimer(60);
      } else {
        if (kDebugMode) {
          print('❌ Password reset OTP request failed');
          print('   Message: $message');
        }
        
        await _showErrorMessage(message, duration: const Duration(seconds: 3));
      }
    } on TimeoutException {
      if (kDebugMode) print('⏱️ OTP request timeout');
      
      await _showErrorMessage('Request timeout - Server tidak merespons', duration: const Duration(seconds: 3));
    } catch (e) {
      if (kDebugMode) print('💥 OTP request exception: $e');
      
      await _showErrorMessage('Gagal meminta OTP: $e', duration: const Duration(seconds: 3));
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
        // Fire-and-forget: show error notification without awaiting
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

    // Client-side check: OTP sudah expired
    if (otpValiditySeconds.value <= 0) {
      await _showErrorMessage('Kode OTP yang anda masukkan tidak valid');
      return;
    }

    if (otpCode.isEmpty) {
      await _showErrorMessage('Kode OTP wajib diisi');
      return;
    }

    if (otpCode.length != 6) {
      await _showErrorMessage('OTP harus 6 digit');
      return;
    }

    if (phoneNumber.isEmpty) {
      await _showErrorMessage('Nomor HP tidak ditemukan');
      return;
    }

    isLoadingVerifyOtp.value = true;

    try {
      if (kDebugMode) {
        print('🔐 Verifying password reset OTP...');
      }
      
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
        if (kDebugMode) {
          print('✅ Password reset OTP verified successfully');
          print('   Message: $message');
        }
        
        // Stop OTP validity timer immediately on success
        _stopOtpValidityTimer();
        
        // Show success message
        await _showSuccessMessage(
          'Kode OTP berhasil diverifikasi. Silakan buat password baru Anda.',
          duration: const Duration(seconds: 3),
        );
        
        // Navigate after delay
        await Future.delayed(const Duration(milliseconds: 500));
        currentStep.value = 2;
      } else {
        if (kDebugMode) {
          print('❌ Password reset OTP verification failed');
          print('   Message: $message');
        }
        
        await _showErrorMessage(message, duration: const Duration(seconds: 3));
      }
    } on TimeoutException {
      if (kDebugMode) print('⏱️ OTP verification timeout');
      
      await _showErrorMessage('Request timeout - Server tidak merespons', duration: const Duration(seconds: 3));
    } catch (e) {
      if (kDebugMode) print('💥 OTP verification exception: $e');
      
      await _showErrorMessage('Gagal verifikasi OTP: $e', duration: const Duration(seconds: 3));
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
      await _showErrorMessage('Semua field wajib diisi');
      return;
    }

    if (newPassword.length < 6) {
      await _showErrorMessage('Password minimal 6 karakter');
      return;
    }

    if (newPassword != confirmPassword) {
      await _showErrorMessage('Password tidak cocok');
      return;
    }

    isLoadingResetPassword.value = true;

    try {
      if (kDebugMode) {
        print('📝 Resetting password...');
      }
      
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
        if (kDebugMode) {
          print('✅ Password reset SUCCESS');
          print('   Message: $message');
          print('   Showing notification...');
        }
        
        // Transform message for better UX
        final displayMessage = message.contains('Password') 
            ? message.replaceAll('Password', 'Kata Sandi')
            : (message.contains('password') 
                ? message.replaceAll('password', 'Kata Sandi')
                : message);
        
        // Show success message with extended duration
        await _showSuccessMessage(
          displayMessage,
          duration: const Duration(seconds: 5),
        );
        
        if (kDebugMode) {
          print('   ⏳ Waiting 5.5 seconds for notification to display...');
        }
        
        // Wait for notification to fully display before navigating
        // Total: 300ms animation + 5s display + 200ms buffer = 5.5 seconds
        await Future.delayed(const Duration(milliseconds: 5500));
        
        if (kDebugMode) {
          print('   📱 Navigating to login page...');
        }
        
        // Clear form before navigating
        resetForm();
        
        // Use offAllNamed for hard reset of navigation stack
        Get.offAllNamed('/login');
      } else {
        if (kDebugMode) {
          print('❌ Password reset FAILED');
          print('   Message: $message');
        }
        
        await _showErrorMessage(message, duration: const Duration(seconds: 3));
      }
    } on TimeoutException {
      if (kDebugMode) print('⏱️ Password reset timeout');
      
      await _showErrorMessage('Request timeout - Server tidak merespons', duration: const Duration(seconds: 3));
    } catch (e) {
      if (kDebugMode) {
        print('💥 Password reset exception: $e');
      }
      
      await _showErrorMessage('Gagal reset password: $e', duration: const Duration(seconds: 3));
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
