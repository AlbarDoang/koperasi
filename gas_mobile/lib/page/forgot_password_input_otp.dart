import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_password_controller.dart';

class ForgotPasswordInputOTP extends GetView<ForgotPasswordController> {
  const ForgotPasswordInputOTP({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Verifikasi OTP'),
        centerTitle: true,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 20),
              Text(
                'Masukkan Kode OTP',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 8),
              Obx(
                () {
                  int seconds = controller.otpValiditySeconds.value;
                  int minutes = seconds ~/ 60;
                  int secs = seconds % 60;
                  String timeText =
                      '${minutes.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';
                  bool isExpired = seconds <= 0;
                  bool isWarning = seconds <= 30;

                  return Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    decoration: BoxDecoration(
                      color: (isExpired ? Colors.red : isWarning ? Colors.orange : Colors.blue)
                          .withOpacity(0.1),
                      border: Border.all(
                        color: isExpired ? Colors.red : isWarning ? Colors.orange : Colors.blue,
                        width: 1,
                      ),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isExpired
                              ? Icons.error_rounded
                              : isWarning
                                  ? Icons.warning_amber_rounded
                                  : Icons.schedule_rounded,
                          color: isExpired ? Colors.red : isWarning ? Colors.orange : Colors.blue,
                          size: 20,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            isExpired
                                ? 'Kode OTP telah kadaluarsa. Silakan minta OTP baru.'
                                : 'Kode OTP berlaku: $timeText',
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: isExpired
                                      ? Colors.red
                                      : isWarning
                                          ? Colors.orange
                                          : Colors.blue,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
              const SizedBox(height: 30),
              TextField(
                onChanged: (value) => controller.otp.value = value,
                decoration: InputDecoration(
                  labelText: 'Kode OTP',
                  hintText: '000000',
                  prefixIcon: const Icon(Icons.security),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: BorderSide(color: Colors.grey[300]!),
                  ),
                ),
                keyboardType: TextInputType.number,
                maxLength: 6,
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                child: Obx(
                  () => ElevatedButton(
                    onPressed: controller.isLoadingVerifyOtp.value
                        ? null
                        : () => controller.verifyOTP(),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      backgroundColor: const Color(0xFFFF4D00),
                      disabledBackgroundColor: Colors.grey[300],
                    ),
                    child: controller.isLoadingVerifyOtp.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text(
                            'Verifikasi OTP',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () {
                    controller.currentStep.value = 0;
                    controller.otp.value = '';
                  },
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                  child: const Text(
                    'Kembali',
                    style: TextStyle(fontSize: 16),
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
