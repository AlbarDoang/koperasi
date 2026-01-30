import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'dart:convert';

void main() {
  test('addLocalNotification avoids duplicates and excludes cashback', () async {
    SharedPreferences.setMockInitialValues({});

    // Add a normal notification
    await NotifikasiHelper.addLocalNotification(type: 'transaksi', title: 'Test', message: 'OK');
    // Add duplicate within short time - should be ignored
    await NotifikasiHelper.addLocalNotification(type: 'transaksi', title: 'Test', message: 'OK');

    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString('notifications') ?? '[]';
    final list = jsonDecode(stored) as List<dynamic>;
    expect(list.length, 1);

    // getUnreadCount should be 1
    final unread = await NotifikasiHelper.getUnreadCount();
    expect(unread, 1);

    // Attempt to add excluded cashback notification
    await NotifikasiHelper.addLocalNotification(type: 'transaksi', title: 'Cashback Rp5.000 masuk', message: 'You got cashback');
    final stored2 = prefs.getString('notifications') ?? '[]';
    final list2 = jsonDecode(stored2) as List<dynamic>;
    // still length 1
    expect(list2.length, 1);

    // Now test that topup (setoran) notifications with different data are allowed
    await NotifikasiHelper.addLocalNotification(type: 'topup', title: 'Pengajuan Setoran Dikirim', message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.', data: {'mulai_id': 'a1'});
    await NotifikasiHelper.addLocalNotification(type: 'topup', title: 'Pengajuan Setoran Tabungan', message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.', data: {'id_mulai_nabung': 'a2'});
    final stored3 = prefs.getString('notifications') ?? '[]';
    final list3 = jsonDecode(stored3) as List<dynamic>;
    // should now be three (original test notification + 2 topups)
    expect(list3.length, 3);

    // adding duplicate with same data should be ignored
    await NotifikasiHelper.addLocalNotification(type: 'topup', title: 'Pengajuan Setoran Tabungan', message: 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.', data: {'id_mulai_nabung': 'a2'});
    final stored4 = prefs.getString('notifications') ?? '[]';
    final list4 = jsonDecode(stored4) as List<dynamic>;
    expect(list4.length, 3);

    // Mark as read via page flow simulation by updating prefs directly and checking unread count
    final parsed = jsonDecode(stored2) as List<dynamic>;
    parsed[0]['read'] = true;
    await prefs.setString('notifications', jsonEncode(parsed));
    final unread2 = await NotifikasiHelper.getUnreadCount();
    expect(unread2, 0);
  });

  test('mergeServerWithExisting removes local pending when server has final status for same mulai_id', () {
    final server = [
      {
        'type': 'tabungan',
        'title': 'Setoran Tabungan Disetujui',
        'message': 'Setoran tabungan Anda telah diterima dan ditambahkan ke saldo.',
        'created_at': '2026-01-12T12:00:00',
        'data': {'mulai_id': 123, 'status': 'berhasil'},
      }
    ];

    final existing = [
      {
        'type': 'tabungan',
        'title': 'Pengajuan Setoran Tabungan',
        'message': 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
        'created_at': '2026-01-12T11:59:00',
        'data': {'mulai_id': 123, 'status': 'menunggu_admin'},
      },
      {
        'type': 'tabungan',
        'title': 'Some Other',
        'message': 'Content',
        'created_at': '2026-01-11T09:00:00',
        'data': null,
      }
    ];

    final merged = NotifikasiHelper.mergeServerWithExisting(List<Map<String, dynamic>>.from(server), existing);

    // Expect the server item to be present
    expect(merged.any((e) => e['title'] == 'Setoran Tabungan Disetujui'), isTrue);
    // Expect the local pending item with same mulai_id to be removed
    expect(merged.any((e) => e['title'] == 'Pengajuan Setoran Tabungan'), isFalse);
    // Expect other existing items remain
    expect(merged.any((e) => e['title'] == 'Some Other'), isTrue);
  });

  test('sortNotificationsNewestFirst orders correctly', () {
    final items = [
      {'created_at': '2026-01-10T10:00:00'},
      {'created_at': '2026-01-12T12:00:00'},
      {'created_at': '2026-01-11T09:00:00'},
    ];

    final sorted = NotifikasiHelper.sortNotificationsNewestFirst(items.cast<Map<String, dynamic>>());

    expect(sorted[0]['created_at'], '2026-01-12T12:00:00');
    expect(sorted[1]['created_at'], '2026-01-11T09:00:00');
    expect(sorted[2]['created_at'], '2026-01-10T10:00:00');
  });
}

