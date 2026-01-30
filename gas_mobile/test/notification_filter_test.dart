import 'package:flutter_test/flutter_test.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';

void main() {
  test('Accept approval notification (Setoran Tabungan Disetujui)', () {
    final notif = {
      'type': 'mulai_nabung',
      'title': 'Setoran Tabungan Disetujui',
      'message': 'Setoran tabungan Anda telah diterima dan ditambahkan ke saldo.',
    };
    
    // Should NOT be excluded (return false means NOT excluded)
    final excluded = NotifikasiHelper.isExcludedNotification(notif);
    expect(excluded, false, reason: 'Setoran Disetujui should be shown');
  });

  test('Accept rejection notification (Setoran Tabungan Ditolak)', () {
    final notif = {
      'type': 'mulai_nabung',
      'title': 'Setoran Tabungan Ditolak',
      'message': 'Pengajuan setoran tabungan Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.',
    };
    
    // Should NOT be excluded
    final excluded = NotifikasiHelper.isExcludedNotification(notif);
    expect(excluded, false, reason: 'Setoran Ditolak should be shown');
  });

  test('Accept pending notification (Pengajuan Setoran Dikirim)', () {
    final notif = {
      'type': 'tabungan',
      'title': 'Pengajuan Setoran Dikirim',
      'message': 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
    };
    
    // Should NOT be excluded
    final excluded = NotifikasiHelper.isExcludedNotification(notif);
    expect(excluded, false, reason: 'Pengajuan Setoran Dikirim should be shown');
  });

  test('Exclude cashback notification', () {
    final notif = {
      'type': 'promo',
      'title': 'Cashback Bonus',
      'message': 'Anda mendapat cashback sebesar Rp50.000',
    };
    
    // Should be excluded
    final excluded = NotifikasiHelper.isExcludedNotification(notif);
    expect(excluded, true, reason: 'Cashback notifications should be filtered out');
  });

  test('Exclude generic processing message for non-loan/non-withdrawal', () {
    final notif = {
      'type': 'topup',
      'title': 'Sedang Diproses',
      'message': 'Transaksi Anda sedang diproses',
    };
    
    // Should be excluded (generic processing for topup)
    final excluded = NotifikasiHelper.isExcludedNotification(notif);
    expect(excluded, true, reason: 'Generic processing messages for topup should be filtered');
  });
}
