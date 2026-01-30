import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_pin_controller.dart';
import 'package:tabungan/page/forgot_pin_input_nomor_hp.dart';
import 'package:tabungan/page/forgot_pin_reset_pin.dart';

class ForgotPinPage extends GetView<ForgotPinController> {
  const ForgotPinPage({Key? key}) : super(key: key);

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
              return const ForgotPinInputNomorHp();
            case 2:
              return const ForgotPinResetPin();
            default:
              return const ForgotPinInputNomorHp();
          }
        },
      ),
    );
  }
}
