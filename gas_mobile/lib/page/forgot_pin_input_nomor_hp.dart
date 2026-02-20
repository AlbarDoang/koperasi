import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_pin_controller.dart';

class ForgotPinInputNomorHp extends StatefulWidget {
  const ForgotPinInputNomorHp({Key? key}) : super(key: key);

  @override
  State<ForgotPinInputNomorHp> createState() => _ForgotPinInputNomorHpState();
}

class _ForgotPinInputNomorHpState extends State<ForgotPinInputNomorHp> {
  late ForgotPinController controller;

  @override
  void initState() {
    super.initState();

    // Ensure controller is initialized
    if (!Get.isRegistered<ForgotPinController>()) {
      if (kDebugMode) {
        print('🔧 [Page] Initializing ForgotPinController...');
      }
      Get.put(ForgotPinController());
    }

    controller = Get.find<ForgotPinController>();

    if (kDebugMode) {
      print('✅ [Page] Controller found/registered');
      print('   Controller ID: ${controller.hashCode}');
    }

    // Listen to error dialog state dari controller
    ever(controller.showErrorDialog, (showError) {
      if (showError && mounted) {
        _showErrorDialogFromWidget(controller.errorMessage.value);
      }
    });

    // Success OTP now handled via toast + auto-navigate in controller
  }

  @override
  void dispose() {
    super.dispose();
  }

  // Show error dialog dari widget dengan proper BuildContext
  void _showErrorDialogFromWidget(String message) {
    if (kDebugMode) {
      print('\n${'=' * 80}');
      print('📱 [Page] SHOWING ERROR DIALOG FROM WIDGET');
      print('   Message: $message');
      print('   Context: Available');
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text(
            '❌ Verifikasi OTP Gagal',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Colors.redAccent,
            ),
          ),
          content: Text(
            message,
            style: const TextStyle(fontSize: 16, height: 1.5),
          ),
          actions: [
            TextButton(
              onPressed: () {
                if (kDebugMode) {
                  print('✅ [Page] User clicked Coba Lagi');
                }
                Navigator.of(dialogContext).pop();
                controller.showErrorDialog.value = false;
              },
              style: TextButton.styleFrom(foregroundColor: Colors.redAccent),
              child: const Text(
                'Coba Lagi',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
            ),
          ],
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          elevation: 8,
        );
      },
    ).then((_) {
      if (kDebugMode) {
        print('✅ [Page] Error dialog closed');
        print('${'=' * 80}\n');
      }
    });
  }



  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Lupa PIN'),
        centerTitle: true,
        elevation: 0,
      ),
      body: Stack(
        children: [
          SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 20),
                  Text(
                    'Masukkan Nomor HP',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Kami akan mengirimkan kode OTP ke nomor HP yang terdaftar',
                    style: Theme.of(
                      context,
                    ).textTheme.bodyMedium?.copyWith(color: Colors.grey[600]),
                  ),
                  const SizedBox(height: 30),

                  TextField(
                    onChanged: (value) => controller.noHp.value = value,
                    decoration: InputDecoration(
                      labelText: 'Nomor HP',
                      hintText: '08xxxxxxxxxx',
                      prefixIcon: const Icon(Icons.phone),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(8),
                        borderSide: BorderSide(color: Colors.grey[300]!),
                      ),
                    ),
                    keyboardType: TextInputType.phone,
                  ),
                  const SizedBox(height: 30),

                  SizedBox(
                    width: double.infinity,
                    child: Obx(
                      () => ElevatedButton(
                        onPressed:
                            (controller.isLoadingRequestOtp.value ||
                                controller.resendSeconds.value > 0)
                            ? null
                            : () => controller.requestOTP(),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          backgroundColor: const Color(0xFFFF4C00),
                          disabledBackgroundColor: Colors.grey[300],
                        ),
                        child: controller.isLoadingRequestOtp.value
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                    Colors.white,
                                  ),
                                ),
                              )
                            : Text(
                                controller.resendSeconds.value > 0
                                    ? 'Kirim OTP (${controller.resendSeconds.value}s)'
                                    : 'Kirim OTP',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  Obx(
                    () => controller.currentStep.value >= 1
                        ? Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Center(
                                child: Obx(
                                  () => Text(
                                    'Kode OTP berlaku selama ${controller.otpValiditySeconds.value} detik',
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodyMedium
                                        ?.copyWith(
                                          color: Colors.redAccent,
                                          fontWeight: FontWeight.w600,
                                        ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: 18),
                              Container(
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(14),
                                  border: Border.all(
                                    color: Colors.grey.shade300,
                                  ),
                                  color: Colors.white,
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.03),
                                      blurRadius: 6,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                  vertical: 4,
                                ),
                                child: TextField(
                                  onChanged: (value) =>
                                      controller.otp.value = value,
                                  decoration: const InputDecoration(
                                    border: InputBorder.none,
                                    hintText: 'Masukkan Kode OTP',
                                    prefixIcon: Icon(Icons.lock_outline),
                                    counterText: '',
                                  ),
                                  keyboardType: TextInputType.number,
                                  maxLength: 6,
                                  style: const TextStyle(fontSize: 16),
                                ),
                              ),
                              const SizedBox(height: 22),
                              SizedBox(
                                width: double.infinity,
                                height: 56,
                                child: Obx(
                                  () => ElevatedButton(
                                    onPressed:
                                        controller.isLoadingVerifyOtp.value
                                        ? null
                                        : () => controller.verifyOTP(),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFFFF4C00),
                                      shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                    ),
                                    child: controller.isLoadingVerifyOtp.value
                                        ? const SizedBox(
                                            height: 20,
                                            width: 20,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                              valueColor:
                                                  AlwaysStoppedAnimation<Color>(
                                                    Colors.white,
                                                  ),
                                            ),
                                          )
                                        : const Text(
                                            'Lanjut',
                                            style: TextStyle(
                                              color: Colors.white,
                                              fontSize: 16,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                  ),
                                ),
                              ),
                            ],
                          )
                        : SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              onPressed: () => Get.offAllNamed('/dashboard'),
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                foregroundColor: const Color(0xFFFF4C00),
                                side: const BorderSide(color: Color(0xFFFF4C00)),
                              ),
                              child: const Text(
                                'Kembali ke Dashboard',
                                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
