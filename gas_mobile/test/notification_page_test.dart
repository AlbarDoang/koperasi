import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'package:tabungan/page/notifikasi.dart';

void main() {
  testWidgets('Header appears and excluded items are filtered', (WidgetTester tester) async {
    // Prepare prefs with sample notifications including excluded 'Cashback' and valid transaksi
    SharedPreferences.setMockInitialValues({
      'notifications': '''[
        {"type":"transaksi","title":"Cashback Rp5.000 masuk","message":"Kamu mendapat cashback","created_at":"2025-12-26T00:00:00Z","read":false},
        {"type":"transaksi","title":"Pembayaran Rp25.000 berhasil","message":"Pembayaran berhasil","created_at":"2025-12-26T00:01:00Z","read":false},
        {"type":"topup","title":"Setoran Tabungan Disetujui","message":"Setoran tabungan Anda telah disetujui dan berhasil diproses.","created_at":"2025-12-26T00:02:00Z","read":true},
        {"type":"tabungan","title":"Pencairan Rp 10.000 dari Umroh berhasil","message":"Pencairan berhasil","created_at":"2025-12-26T00:03:00Z","read":false},
        {"type":"pinjaman_kredit","title":"Pengajuan kredit Anda sedang diverifikasi oleh admin.","message":"Pengajuan sedang diverifikasi","created_at":"2025-12-26T00:04:00Z","read":false}
      ]'''
    });

    await tester.pumpWidget(const MaterialApp(home: NotifikasiPage()));

    // Allow async work to complete
    await tester.pumpAndSettle();

    // The cashback notification should NOT be shown
    expect(find.textContaining('Cashback'), findsNothing);

    // The valid transaksi should be shown
    expect(find.textContaining('Pembayaran Rp25.000 berhasil'), findsOneWidget);

    // The legacy 'tabungan' notification (pencairan) should also be shown
    expect(find.textContaining('Pencairan Rp 10.000 dari Umroh berhasil'), findsOneWidget);

    // There should be at least one unread notification initially
    final beforeUnread = await NotifikasiHelper.getUnreadCount();
    expect(beforeUnread >= 1, true);

    // Tap the 'Pembayaran' notification to mark it read
    await tester.tap(find.textContaining('Pembayaran Rp25.000 berhasil'));
    await tester.pumpAndSettle();

    // After tap, the unread count should decrease by one
    final afterUnread = await NotifikasiHelper.getUnreadCount();
    expect(afterUnread, equals(beforeUnread - 1));
    // The pinjaman_kredit notification should be displayed as well (may be off-screen so scroll to it)
    await tester.scrollUntilVisible(
      find.textContaining('Pengajuan kredit Anda sedang diverifikasi oleh admin.'),
      200.0,
      scrollable: find.byType(Scrollable),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Pengajuan kredit Anda sedang diverifikasi oleh admin.'), findsOneWidget);


  });
}
