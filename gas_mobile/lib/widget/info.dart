import 'package:get/get.dart';

import 'package:tabungan/utils/custom_toast.dart';

class Info {
  static void snackbar(String message) {
    final ctx = Get.context;
    if (ctx != null) {
      CustomToast.success(ctx, message);
    }
  }
}
