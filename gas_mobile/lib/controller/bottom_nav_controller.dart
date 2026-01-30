import 'package:get/get.dart';

class BottomNavController extends GetxController {
  final index = 2.obs; // default center (home)

  void setIndex(int i) {
    index.value = i.clamp(0, 4);
  }

  void resetToHome() {
    index.value = 2;
  }
}
