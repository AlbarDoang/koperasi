import 'package:flutter_test/flutter_test.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';

void main() {
  test('mergeServerWithExisting preserves local notifications and respects data', () {
    final server = [
      {
        'type': 'topup',
        'title': 'Pengajuan Setoran Tabungan',
        'message': 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
        'created_at': DateTime.now().toIso8601String(),
        // server returned without data for this example
      }
    ];

    final existing = [
      {
        'type': 'topup',
        'title': 'Pengajuan Setoran Tabungan',
        'message': 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
        'created_at': DateTime.now().toIso8601String(),
        'data': {'id_mulai_nabung': 'loc-1'}
      }
    ];

    // Because server item lacks data and local one has distinct data, local one should be preserved
    final merged = NotifikasiHelper.mergeServerWithExisting(server, existing);

    expect(merged.length, 2);
    expect(merged.any((e) => e['data'] != null), true);

    // If server includes the same data, it should deduplicate
    final serverWithSameData = [
      {
        'type': 'topup',
        'title': 'Pengajuan Setoran Tabungan',
        'message': 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.',
        'created_at': DateTime.now().toIso8601String(),
        'data': {'id_mulai_nabung': 'loc-1'}
      }
    ];

    final merged2 = NotifikasiHelper.mergeServerWithExisting(serverWithSameData, existing);
    // server already contains the same data => local shouldn't be duplicated
    expect(merged2.length, 1);
  });
}
