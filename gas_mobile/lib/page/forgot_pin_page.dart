import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:tabungan/controller/forgot_pin_controller.dart';
import 'package:tabungan/page/forgot_pin_input_nomor_hp.dart';
import 'package:tabungan/page/forgot_pin_reset_pin.dart';
import 'package:tabungan/utils/custom_toast.dart';

class ForgotPinPage extends StatefulWidget {
  const ForgotPinPage({Key? key}) : super(key: key);

  @override
  State<ForgotPinPage> createState() => _ForgotPinPageState();
}

class _ForgotPinPageState extends State<ForgotPinPage> {
  late ForgotPinController controller;
  Worker? _toastWorker;

  @override
  void initState() {
    super.initState();

    if (!Get.isRegistered<ForgotPinController>()) {
      if (kDebugMode) {
        print('🔧 [ForgotPinPage] Registering ForgotPinController...');
      }
      Get.put(ForgotPinController());
    }

    controller = Get.find<ForgotPinController>();

    _toastWorker = ever<ToastNotification?>(
      controller.toastNotification,
      (notification) {
        if (!mounted || notification == null) return;

        if (kDebugMode) {
          print('📱 [ForgotPinPage] Showing toast: "${notification.message}"');
        }

        CustomToast.show(
          context,
          notification.message,
          baseColor: notification.color,
          duration: notification.duration,
          icon: notification.icon,
        );
      },
    );
  }

  @override
  void dispose() {
    _toastWorker?.dispose();
    super.dispose();
  }

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
