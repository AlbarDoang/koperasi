import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_password_controller.dart';
import 'package:tabungan/page/forgot_password_input_nomor_hp.dart';
import 'package:tabungan/page/forgot_password_reset_password.dart';

class ForgotPasswordPage extends GetView<ForgotPasswordController> {
  const ForgotPasswordPage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: () async {
        if (controller.currentStep.value > 0) {
          controller.currentStep.value -= 1;
          return false;
        }
        return true;
      },
      child: Obx(
        () {
          switch (controller.currentStep.value) {
            case 0:
            case 1:
              // Keep the phone input and inline OTP on the same page for steps 0 and 1
              return const ForgotPasswordInputNomorHp();
            case 2:
              return const ForgotPasswordResetPassword();
            default:
              return const ForgotPasswordInputNomorHp();
          }
        },
      ),
    );
  }
}
