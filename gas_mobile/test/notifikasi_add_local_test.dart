import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'dart:convert';

void main() {
  test('addLocalNotification should persist and update notifier and allow distinct events', () async {
    SharedPreferences.setMockInitialValues({});

    final before = NotifikasiHelper.onNotificationsChanged.value;

    // Add two distinct topup notifications with different id_mulai_nabung - both should persist
    await NotifikasiHelper.addLocalNotification(
      type: 'topup',
      title: 'Pengajuan Setoran Dikirim',
      message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
      data: {'mulai_id': 'a1'},
    );

    await NotifikasiHelper.addLocalNotification(
      type: 'topup',
      title: 'Pengajuan Setoran Tabungan',
      message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
      data: {'id_mulai_nabung': '1002'},
    );

    final prefs = await SharedPreferences.getInstance();
    final notificationsRaw = prefs.getString('notifications') ?? '[]';
    final lastRaw = prefs.getString('last_local_notif') ?? '';

    final List<dynamic> list = jsonDecode(notificationsRaw);
    expect(list.length, 2);

    expect(lastRaw.isNotEmpty, true);

    // adding again with same data should be ignored (duplicate)
    await NotifikasiHelper.addLocalNotification(
      type: 'topup',
      title: 'Pengajuan Setoran Tabungan',
      message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
      data: {'id_mulai_nabung': '1002'},
    );

    final notificationsRaw2 = prefs.getString('notifications') ?? '[]';
    final List<dynamic> list2 = jsonDecode(notificationsRaw2);
    expect(list2.length, 2);

    final after = NotifikasiHelper.onNotificationsChanged.value;
    expect(after > before, true);
  });
}
