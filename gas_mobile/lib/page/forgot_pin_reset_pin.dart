import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_pin_controller.dart';

class ForgotPinResetPin extends GetView<ForgotPinController> {
  const ForgotPinResetPin({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final RxBool isPinVisible = false.obs;
    final RxBool isConfirmPinVisible = false.obs;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Reset PIN'),
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
                'Buat PIN Baru',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                'PIN harus berupa 6 digit angka',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.grey[600],
                ),
              ),
              const SizedBox(height: 30),
              Obx(
                () => TextField(
                  onChanged: (value) => controller.pinBaru.value = value,
                  obscureText: !isPinVisible.value,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: InputDecoration(
                    labelText: 'PIN Baru',
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        isPinVisible.value ? Icons.visibility : Icons.visibility_off,
                      ),
                      onPressed: () {
                        isPinVisible.value = !isPinVisible.value;
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
                  onChanged: (value) => controller.pinKonfirmasi.value = value,
                  obscureText: !isConfirmPinVisible.value,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: InputDecoration(
                    labelText: 'Konfirmasi PIN',
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(
                        isConfirmPinVisible.value ? Icons.visibility : Icons.visibility_off,
                      ),
                      onPressed: () {
                        isConfirmPinVisible.value = !isConfirmPinVisible.value;
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
                    onPressed: controller.isLoadingResetPin.value ? null : () => controller.resetPin(),
                    style: ElevatedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      backgroundColor: Colors.orange,
                      disabledBackgroundColor: Colors.grey[300],
                    ),
                    child: controller.isLoadingResetPin.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text(
                            'Reset PIN',
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
                    controller.currentStep.value = 1;
                    controller.pinBaru.value = '';
                    controller.pinKonfirmasi.value = '';
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
