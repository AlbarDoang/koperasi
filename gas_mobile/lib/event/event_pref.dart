import 'dart:convert';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/model/tabungan.dart';
import 'package:tabungan/model/tabungan2.dart';
import 'package:tabungan/model/masuk.dart';
import 'package:tabungan/model/penarikan.dart';
import 'package:tabungan/model/transfer.dart';
import 'package:shared_preferences/shared_preferences.dart';

class EventPref {
  // SIMPAN USER
  static Future<void> saveUser(User user) async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    await pref.setString('user', jsonEncode(user.toJson()));
  }

  // CEK LOGIN
  static Future<bool> isLogin() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    return pref.getString('user') != null;
  }

  // AMBIL DATA USER
  static Future<User?> getUser() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? stringUser = pref.getString('user');

    if (stringUser != null) {
      Map<String, dynamic> mapUser = jsonDecode(stringUser);
      return User.fromJson(mapUser);
    }
    return null;
  }

  // AMBIL DATA TABUNGAN
  static Future<Tabungan?> getTabungan() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? data = pref.getString('tabungan');

    if (data != null) {
      return Tabungan.fromJson(jsonDecode(data));
    }
    return null;
  }

  // AMBIL DATA TABUNGAN 2
  static Future<Tabungan2?> getTabungan2() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? data = pref.getString('tabungan2');

    if (data != null) {
      return Tabungan2.fromJson(jsonDecode(data));
    }
    return null;
  }

  // AMBIL DATA SETORAN HARI INI
  static Future<Masuk?> getSetoran() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? data = pref.getString('masuk');

    if (data != null) {
      return Masuk.fromJson(jsonDecode(data));
    }
    return null;
  }

  // AMBIL DATA PENARIKAN
  static Future<Penarikan?> getPenarikan() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? data = pref.getString('penarikan');

    if (data != null) {
      return Penarikan.fromJson(jsonDecode(data));
    }
    return null;
  }

  // AMBIL DATA TRANSFER
  static Future<Transfer?> getTransfer() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    String? data = pref.getString('transfer');

    if (data != null) {
      return Transfer.fromJson(jsonDecode(data));
    }
    return null;
  }

  // CLEAR DATA SESI (hanya data login/user, BUKAN notifikasi & riwayat transaksi)
  static Future<void> clear() async {
    SharedPreferences pref = await SharedPreferences.getInstance();
    // Hanya hapus key sesi/user, jangan hapus notifikasi & transaksi
    await pref.remove('user');
    await pref.remove('tabungan');
    await pref.remove('tabungan2');
    await pref.remove('masuk');
    await pref.remove('penarikan');
    await pref.remove('transfer');
    // Key berikut TIDAK dihapus agar data tetap ada setelah logout:
    // - 'notifications', 'notifications_blacklist', 'last_local_notif'
    // - 'transactions', 'pengajuan_list'
    // - 'ON_BOARDING', 'isDarkMode', 'API_BASE_OVERRIDE'
  }

  static Future loadUser() async {}
}
