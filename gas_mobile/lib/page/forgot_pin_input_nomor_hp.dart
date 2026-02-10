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

  Widget _buildToastBanner() {
    return Obx(() {
      final notification = controller.toastNotification.value;
      if (notification == null) {
        return const SizedBox.shrink();
      }

      return AnimatedSwitcher(
        duration: const Duration(milliseconds: 200),
        child: Material(
          key: ValueKey('${notification.message}-${notification.color.value}'),
          elevation: 4,
          shadowColor: notification.color.withAlpha(60),
          borderRadius: BorderRadius.circular(12),
          color: notification.color,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Icon(
                  notification.icon ?? Icons.info_outline,
                  color: Colors.white,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    notification.message,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w500,
                      height: 1.3,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    });
  }

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

    // Listen to success dialog state dari controller
    ever(controller.showSuccessDialog, (showSuccess) {
      if (showSuccess && mounted) {
        _showSuccessDialogFromWidget(controller.successMessage.value);
      }
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  // Show error dialog dari widget dengan proper BuildContext
  void _showErrorDialogFromWidget(String message) {
    if (kDebugMode) {
      print('\n${'='*80}');
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
            style: const TextStyle(
              fontSize: 16,
              height: 1.5,
            ),
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
              style: TextButton.styleFrom(
                foregroundColor: Colors.redAccent,
              ),
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
        print('${'='*80}\n');
      }
    });
  }

  // Show success dialog dari widget dengan proper BuildContext  
  void _showSuccessDialogFromWidget(String message) {
    if (kDebugMode) {
      print('\n${'='*80}');
      print('📱 [Page] SHOWING SUCCESS DIALOG FROM WIDGET');
      print('   Message: $message');
      print('   Context: Available');
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          title: const Text(
            '✅ Sukses',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
              color: Color(0xFF4CAF50),
            ),
          ),
          content: Text(
            message,
            style: const TextStyle(
              fontSize: 16,
              height: 1.5,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () {
                if (kDebugMode) {
                  print('✅ [Page] User clicked Lanjutkan');
                  print('   Closing dialog and moving to step 2');
                }
                Navigator.of(dialogContext).pop();
                controller.showSuccessDialog.value = false;
                controller.currentStep.value = 2;
                
                if (kDebugMode) {
                  print('✅ [Page] Step changed to 2');
                  print('${'='*80}\n');
                }
              },
              style: TextButton.styleFrom(
                foregroundColor: const Color(0xFF4CAF50),
              ),
              child: const Text(
                'Lanjutkan',
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
        print('✅ [Page] Success dialog closed');
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
                      backgroundColor: Colors.orange,
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
                            child: Text(
                              'Kode OTP berlaku selama 1 menit',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
                                    color: Colors.redAccent,
                                    fontWeight: FontWeight.w600,
                                  ),
                            ),
                          ),
                          const SizedBox(height: 18),
                          Container(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: Colors.grey.shade300),
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
                                onPressed: controller.isLoadingVerifyOtp.value
                                    ? null
                                    : () => controller.verifyOTP(),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.orange,
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
                                        'Verifikasi',
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 16,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Center(
                            child: Obx(
                              () => Text(
                                controller.resendSeconds.value > 0
                                    ? 'Kirim ulang dalam ${controller.resendSeconds.value} detik'
                                    : 'Belum menerima kode?',
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(color: Colors.grey),
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Center(
                            child: Obx(
                              () => TextButton(
                                onPressed: controller.resendSeconds.value > 0
                                    ? null
                                    : () {
                                        controller.requestOTP();
                                      },
                                child: const Text('Kirim Ulang'),
                              ),
                            ),
                          ),
                        ],
                      )
                    : SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: () => Get.back(),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                          child: const Text(
                            'Kembali ke Login',
                            style: TextStyle(fontSize: 16),
                          ),
                        ),
                      ),
              ),
                ],
              ),
            ),
          ),
          Positioned(
            top: 12,
            left: 16,
            right: 16,
            child: _buildToastBanner(),
          ),
        ],
      ),
    );
  }
}
