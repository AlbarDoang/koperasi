import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_password_controller.dart';

class ForgotPasswordResetPassword extends GetView<ForgotPasswordController> {
  const ForgotPasswordResetPassword({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final RxBool isPasswordVisible = false.obs;
    final RxBool isConfirmPasswordVisible = false.obs;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reset Password'),
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
                'Buat Password Baru',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'Password minimal 6 karakter',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.grey[600],
                ),
              ),
              const SizedBox(height: 30),
              Obx(
                () => TextField(
                  onChanged: (value) => controller.passwordBaru.value = value,
                  obscureText: !isPasswordVisible.value,
                  decoration: InputDecoration(
                    labelText: 'Password Baru',
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        isPasswordVisible.value
                            ? Icons.visibility
                            : Icons.visibility_off,
                      ),
                      onPressed: () {
                        isPasswordVisible.value = !isPasswordVisible.value;
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: BorderSide(color: Colors.grey[300]!),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Obx(
                () => TextField(
                  onChanged: (value) => controller.passwordKonfirmasi.value = value,
                  obscureText: !isConfirmPasswordVisible.value,
                  decoration: InputDecoration(
                    labelText: 'Konfirmasi Password',
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        isConfirmPasswordVisible.value
                            ? Icons.visibility
                            : Icons.visibility_off,
                      ),
                      onPressed: () {
                        isConfirmPasswordVisible.value = !isConfirmPasswordVisible.value;
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: BorderSide(color: Colors.grey[300]!),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                child: Obx(
                  () => ElevatedButton(
                    onPressed: controller.isLoadingResetPassword.value
                        ? null
                        : () => controller.resetPassword(),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      backgroundColor: const Color(0xFFFF4D00),
                      disabledBackgroundColor: Colors.grey[300],
                    ),
                    child: controller.isLoadingResetPassword.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text(
                            'Reset Password',
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
                // ...existing code...
              ),
            ],
          ),
        ),
      ),
    );
  }
}
