import 'package:get/get.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:tabungan/services/notification_service.dart';
import 'dart:async';
import 'dart:convert';

// Toast notification model
class ToastNotification {
  final String message;
  final Color color;
  final Duration duration;
  final IconData? icon;
  ToastNotification({
    required this.message,
    this.color = const Color(0xFF4CAF50),
    this.duration = const Duration(seconds: 3),
    this.icon,
  });
}

class ForgotPinController extends GetxController {
  final RxString noHp = ''.obs;
  final RxString otp = ''.obs;
  final RxString pinBaru = ''.obs;
  final RxString pinKonfirmasi = ''.obs;

  final RxBool isLoadingRequestOtp = false.obs;
  final RxBool isLoadingVerifyOtp = false.obs;
  final RxBool isLoadingResetPin = false.obs;

  final RxInt currentStep = 0.obs;
  final RxInt resendSeconds = 0.obs;

  final RxInt otpValiditySeconds = 0.obs;

  // Toast notification observable
  final Rx<ToastNotification?> toastNotification = Rx<ToastNotification?>(null);

  
  // Toast notification observable
  final Rx<ToastNotification?> toastNotification = Rx<ToastNotification?>(null);

  // Dialog state - untuk ditampilkan dari widget
  final RxString errorMessage = ''.obs;
  final RxString successMessage = ''.obs;
  final RxBool showErrorDialog = false.obs;
  final RxBool showSuccessDialog = false.obs;

  Timer? _resendTimer;
  Timer? _toastTimer;
  Timer? _otpValidityTimer;
  
  Timer? _resendTimer;
  Timer? _toastTimer;

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
        _showToast(
          'Kode OTP telah kadaluarsa. Silakan minta OTP baru.',
          color: Colors.red,
          duration: const Duration(seconds: 3),
        );
        _stopResendTimer();
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

  // Helper to show error dialog - set state yang dialamati widget
  void _showErrorDialog(String message) {
    if (kDebugMode) {
      print('\n${'=' * 80}');
      print('❌ [_showErrorDialog] SETTING ERROR STATE');
      print('   Message: $message');
      print('   Dialog akan ditampilkan dari widget');
      print('${'=' * 80}\n');
  // Helper to show error dialog - set state yang dialamati widget
  void _showErrorDialog(String message) {
    if (kDebugMode) {
      print('\n${'='*80}');
      print('❌ [_showErrorDialog] SETTING ERROR STATE');
      print('   Message: $message');
      print('   Dialog akan ditampilkan dari widget');
      print('${'='*80}\n');
    }
    errorMessage.value = message;
    showErrorDialog.value = true;
  }

  // Helper to show success dialog - set state yang dialamati widget
  void _showSuccessDialog(String message) {
    if (kDebugMode) {
      print('\n${'=' * 80}');
      print('✅ [_showSuccessDialog] SETTING SUCCESS STATE');
      print('   Message: $message');
      print('   Dialog akan ditampilkan dari widget');
      print('${'=' * 80}\n');
      print('\n${'='*80}');
      print('✅ [_showSuccessDialog] SETTING SUCCESS STATE');
      print('   Message: $message');
      print('   Dialog akan ditampilkan dari widget');
      print('${'='*80}\n');
    }
    successMessage.value = message;
    showSuccessDialog.value = true;
  }

  // Helper to show toast notification (emit event + global NotificationService)
  void _showToast(
    String message, {
    Color color = const Color(0xFF4CAF50),
    Duration duration = const Duration(seconds: 3),
  }) {
    if (kDebugMode) {
      print(
        '📢 [Controller] Toast: "$message" (color: $color, duration: ${duration.inSeconds}s)',
      );
  void _showToast(String message, {Color color = const Color(0xFF4CAF50), Duration duration = const Duration(seconds: 3)}) {
    if (kDebugMode) {
      print('📢 [Controller] Toast: "$message" (color: $color, duration: ${duration.inSeconds}s)');
    }
    _toastTimer?.cancel();
    final notification = ToastNotification(
      message: message,
      color: color,
      duration: duration,
    );
    // Emit event untuk page listener
    toastNotification.value = notification;
    _toastTimer = Timer(duration, () {
      if (toastNotification.value == notification) {
        toastNotification.value = null;
      }
    });
    // Show via global NotificationService (no context / overlay needed)
    if (color == Colors.red || color == Colors.redAccent) {
      NotificationService.showError(message);
    } else if (color == Colors.orange) {
      NotificationService.showWarning(message);
    } else {
      NotificationService.showSuccess(message);
    }
  }

  Future<void> requestOTP() async {
    final phoneNumber = noHp.value.trim();

    if (phoneNumber.isEmpty) {
      _showToast(
        'Nomor HP wajib diisi',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    isLoadingRequestOtp.value = true;

    try {
      if (kDebugMode) print('🔄 [requestOTP] START - Phone: $phoneNumber');

      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.forgotPassword),
        body: {'no_hp': phoneNumber, 'type': 'pin'},
        body: {'no_hp': phoneNumber},
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['status'] == true || payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (kDebugMode) {
        print('✅ [requestOTP] Response: Success=$isSuccess, Message=$message');
      }

      if (isSuccess) {
        _showToast(
          message,
          color: const Color(0xFF4CAF50),
          duration: const Duration(seconds: 3),
        );
        currentStep.value = 1;
        _startResendTimer(60);
        // start OTP validity countdown (60 seconds / 1 menit)
        _startOtpValidityTimer(60);
      } else {
        _showToast(
          message,
          color: Colors.red,
          duration: const Duration(seconds: 3),
        );
      }
    } on TimeoutException {
      if (kDebugMode) print('⏱️ [requestOTP] TIMEOUT');
      _showToast(
        'Request timeout - Server tidak merespons',
        color: Colors.red,
        duration: const Duration(seconds: 3),
      );
    } catch (e) {
      if (kDebugMode) print('❌ [requestOTP] ERROR: $e');
      _showToast(
        'Gagal meminta OTP: $e',
        color: Colors.red,
        duration: const Duration(seconds: 3),
      );
    } finally {
      isLoadingRequestOtp.value = false;
    }
  }

  Future<void> verifyOTP() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();

    // Client-side check: OTP sudah expired
    if (otpValiditySeconds.value <= 0) {
      _showErrorDialog('Kode OTP yang anda masukkan tidak valid');
      return;
    }

    if (otpCode.isEmpty) {
      _showToast(
        'Kode OTP wajib diisi',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    if (otpCode.length != 6) {
      _showToast(
        'OTP harus 6 digit',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    if (phoneNumber.isEmpty) {
      _showToast(
        'Nomor HP tidak ditemukan',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    isLoadingVerifyOtp.value = true;

    try {
      if (kDebugMode) {
        print('\n${'=' * 80}');
        print('\n${'='*80}');
        print('🔄 [verifyOTP] STARTING OTP VERIFICATION');
        print('   Phone: $phoneNumber');
        print('   OTP Code: $otpCode');
        print('   Endpoint: ${Api.verifyOtpReset}');
      }

      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.verifyOtpReset),
        body: {'no_hp': phoneNumber, 'otp': otpCode},
      );

      if (kDebugMode) {
        print('📥 [verifyOTP] RESPONSE RECEIVED');
        print('   Status Code: ${response.statusCode}');
        print('   Body: ${response.body}');
      }

      // Parse response
      Map<String, dynamic> payload;
      try {
        payload = jsonDecode(response.body) as Map<String, dynamic>;
      } catch (parseError) {
        if (kDebugMode) {
          print('❌ [verifyOTP] JSON PARSE ERROR: $parseError');
          print('   Raw Body: ${response.body}');
        }
        _showErrorDialog('Respons server tidak valid. Silakan coba lagi.');
        return;
      }

      final isSuccess = payload['status'] == true || payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (kDebugMode) {
        print('✅ [verifyOTP] PARSED RESPONSE');
        print('   Status/Success: $isSuccess');
        print('   Message: $message');
        print('   Full Payload: $payload');
        print('${'=' * 80}\n');
        print('${'='*80}\n');
      }

      if (isSuccess) {
        if (kDebugMode) {
          print('✅ [verifyOTP] OTP SUCCESS - Showing toast and navigating');
        }
        _stopOtpValidityTimer();
        _showToast(
          'Kode OTP yang anda masukan benar',
          color: const Color(0xFF4CAF50),
          duration: const Duration(seconds: 2),
        );
        // Auto navigate to Reset PIN page after short delay
        await Future.delayed(const Duration(milliseconds: 800));
        currentStep.value = 2;
          print('✅ [verifyOTP] OTP SUCCESS - Setting dialog state');
        }
        _showSuccessDialog('Kode OTP yang anda masukan benar');
      } else {
        if (kDebugMode) {
          print('❌ [verifyOTP] OTP VERIFICATION FAILED');
          print('   Setting error dialog with message: $message');
        }
        _showErrorDialog(message);
      }
    } on TimeoutException {
      if (kDebugMode) print('⏱️ [verifyOTP] TIMEOUT');
      _showToast(
        'Request timeout - Server tidak merespons',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
    } catch (e) {
      if (kDebugMode) print('❌ [verifyOTP] ERROR: $e');
      _showToast(
        'Gagal verifikasi OTP: $e',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
    } finally {
      isLoadingVerifyOtp.value = false;
    }
  }

  Future<void> resetPin() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();
    final newPin = pinBaru.value.trim();
    final confirmPin = pinKonfirmasi.value.trim();
    if (phoneNumber.isEmpty ||
        otpCode.isEmpty ||
        newPin.isEmpty ||
        confirmPin.isEmpty) {
    if (phoneNumber.isEmpty || otpCode.isEmpty || newPin.isEmpty || confirmPin.isEmpty) {
      _showToast(
        'Semua field wajib diisi',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    if (!RegExp(r'^\d{6}$').hasMatch(newPin)) {
      _showToast(
        'PIN harus 6 digit angka',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
      return;
    }

    isLoadingResetPin.value = true;

    try {
      if (kDebugMode) print('\n🔄 [resetPin] START');

      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.resetPin),
        body: {
          'no_hp': phoneNumber,
          'otp': otpCode,
          'pin_baru': newPin,
          'pin_confirm': confirmPin,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['status'] == true || payload['success'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (kDebugMode) {
        print('✅ [resetPin] Response: Success=$isSuccess');
      }

      if (isSuccess) {
        _showToast(
          'PIN berhasil diganti',
          color: const Color(0xFF4CAF50),
          duration: const Duration(seconds: 3),
        );
        await Future.delayed(const Duration(seconds: 2));
        // Kembali ke halaman Pengaturan Akun (pop ForgotPinPage + UbahPin)
        Get.close(2);
          message,
          color: const Color(0xFF4CAF50),
          duration: const Duration(seconds: 3),
        );
        await Future.delayed(const Duration(seconds: 1));
        Get.offAllNamed('/login');
      } else {
        _showToast(
          message,
          color: Colors.red,
          duration: const Duration(seconds: 2),
        );
      }
    } on TimeoutException {
      _showToast(
        'Request timeout - Server tidak merespons',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
    } catch (e) {
      if (kDebugMode) print('❌ [resetPin] ERROR: $e');
      _showToast(
        'Gagal reset PIN: $e',
        color: Colors.red,
        duration: const Duration(seconds: 2),
      );
    } finally {
      isLoadingResetPin.value = false;
    }
  }

  void resetForm() {
    noHp.value = '';
    otp.value = '';
    pinBaru.value = '';
    pinKonfirmasi.value = '';
    currentStep.value = 0;
    _stopResendTimer();
    _stopOtpValidityTimer();
  }

  @override
  void onClose() {
    _stopResendTimer();
    _stopOtpValidityTimer();
    _toastTimer?.cancel();
    super.onClose();
  }
}
