import 'package:get/get.dart';
import 'package:tabungan/model/user.dart';

class CUser extends GetxController {
  final Rx<User> _user = User().obs;

  User get user => _user.value;

  void setUser(User dataUser) {
    _user.value = dataUser;
    // Ensure GetBuilder and other listeners are updated
    update();
  }
}
