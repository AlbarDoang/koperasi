import 'package:get/get.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/config/http_client.dart' as http_client;
import 'dart:async';
import 'dart:convert';

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
  Timer? _resendTimer;

  Future<void> requestOTP() async {
    final phoneNumber = noHp.value.trim();

    if (phoneNumber.isEmpty) {
      Get.snackbar('Error', 'Nomor HP wajib diisi', snackPosition: SnackPosition.BOTTOM);
      return;
    }

    isLoadingRequestOtp.value = true;

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.forgotPassword),
        body: {'no_hp': phoneNumber},
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        Get.snackbar('Sukses', message, snackPosition: SnackPosition.BOTTOM, duration: const Duration(seconds: 3));
        currentStep.value = 1;
        _startResendTimer(60);
      } else {
        Get.snackbar('Error', message, snackPosition: SnackPosition.BOTTOM);
      }
    } on TimeoutException {
      Get.snackbar('Error', 'Request timeout - Server tidak merespons', snackPosition: SnackPosition.BOTTOM);
    } catch (e) {
      Get.snackbar('Error', 'Gagal meminta OTP: $e', snackPosition: SnackPosition.BOTTOM);
    } finally {
      isLoadingRequestOtp.value = false;
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

  Future<void> verifyOTP() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();

    if (otpCode.isEmpty) {
      Get.snackbar('Error', 'Kode OTP wajib diisi', snackPosition: SnackPosition.BOTTOM);
      return;
    }

    if (otpCode.length != 6) {
      Get.snackbar('Error', 'OTP harus 6 digit', snackPosition: SnackPosition.BOTTOM);
      return;
    }

    if (phoneNumber.isEmpty) {
      Get.snackbar('Error', 'Nomor HP tidak ditemukan', snackPosition: SnackPosition.BOTTOM);
      return;
    }

    isLoadingVerifyOtp.value = true;

    try {
      final response = await http_client.HttpHelper.post(
        Uri.parse(Api.verifyOtpReset),
        body: {
          'no_hp': phoneNumber,
          'otp': otpCode,
        },
      );

      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final isSuccess = payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        Get.snackbar('Sukses', 'OTP terverifikasi', snackPosition: SnackPosition.BOTTOM);
        currentStep.value = 2;
      } else {
        Get.snackbar('Error', message, snackPosition: SnackPosition.BOTTOM);
      }
    } on TimeoutException {
      Get.snackbar('Error', 'Request timeout - Server tidak merespons', snackPosition: SnackPosition.BOTTOM);
    } catch (e) {
      Get.snackbar('Error', 'Gagal verifikasi OTP: $e', snackPosition: SnackPosition.BOTTOM);
    } finally {
      isLoadingVerifyOtp.value = false;
    }
  }

  Future<void> resetPin() async {
    final phoneNumber = noHp.value.trim();
    final otpCode = otp.value.trim();
    final newPin = pinBaru.value.trim();
    final confirmPin = pinKonfirmasi.value.trim();

    if (phoneNumber.isEmpty || otpCode.isEmpty || newPin.isEmpty || confirmPin.isEmpty) {
      Get.snackbar('Error', 'Semua field wajib diisi', snackPosition: SnackPosition.BOTTOM);
      return;
    }


    if (!RegExp(r'^\d{6}$').hasMatch(newPin)) {
      Get.snackbar('Error', 'PIN harus 6 digit angka', snackPosition: SnackPosition.BOTTOM);
      return;
    }

    isLoadingResetPin.value = true;

    try {
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
      final isSuccess = payload['status'] == true;
      final message = payload['message']?.toString() ?? 'Tidak ada pesan';

      if (isSuccess) {
        Get.snackbar('Sukses', message, snackPosition: SnackPosition.BOTTOM, duration: const Duration(seconds: 3));
        await Future.delayed(const Duration(seconds: 2));
        Get.offAllNamed('/login');
      } else {
        Get.snackbar('Error', message, snackPosition: SnackPosition.BOTTOM);
      }
    } on TimeoutException {
      Get.snackbar('Error', 'Request timeout - Server tidak merespons', snackPosition: SnackPosition.BOTTOM);
    } catch (e) {
      Get.snackbar('Error', 'Gagal reset PIN: $e', snackPosition: SnackPosition.BOTTOM);
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
  }

  @override
  void onClose() {
    _stopResendTimer();
    super.onClose();
  }
}
