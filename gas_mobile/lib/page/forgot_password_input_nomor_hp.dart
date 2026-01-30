import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_password_controller.dart';

class ForgotPasswordInputNomorHp extends GetView<ForgotPasswordController> {
  const ForgotPasswordInputNomorHp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Lupa Password'),
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
                'Masukkan Nomor HP',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 10),
              Text(
                'Kami akan mengirimkan kode OTP ke nomor HP yang terdaftar',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.grey[600],
                    ),
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
                    onPressed: (controller.isLoadingForgotPassword.value || controller.resendSeconds.value > 0)
                        ? null
                        : () => controller.requestOTP(),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      backgroundColor: const Color(0xFFFF4D00),
                      disabledBackgroundColor: Colors.grey[300],
                    ),
                    child: controller.isLoadingForgotPassword.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
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
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
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
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                            child: TextField(
                              onChanged: (value) => controller.otp.value = value,
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
                                  backgroundColor: const Color(0xFFFF4D00),
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
                                          valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
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
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: Colors.grey,
                                    ),
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
                            style: TextStyle(fontSize: 16, color: Color(0xFFFF4D00)),
                          ),
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
