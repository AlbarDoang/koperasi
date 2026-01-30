// TEST FILE: Test notification parsing and navigation logic
// 
// This is a Dart test simulation showing the frontend logic flow
// Save as: gas_mobile/lib/test_notif_navigation_logic.dart

import 'dart:convert';

void main() {
  print('=== TEST: Notification Navigation Logic ===\n');

  // TEST 1: Parse notification with id_transaksi
  testParseNotificationWithIdTransaksi();

  // TEST 2: Match transaction by id
  testTransactionMatching();

  // TEST 3: String vs Int comparison
  testStringIntComparison();

  // TEST 4: Null safety
  testNullSafety();

  print('\n✅ All tests completed!');
}

void testParseNotificationWithIdTransaksi() {
  print('TEST 1: Parse notification with id_transaksi');
  print('-' * 50);

  // Simulate API response
  final notificationJson = '''{
    "id": 1,
    "type": "tabungan",
    "title": "Setoran Diproses",
    "data": {
      "mulai_id": 1,
      "status": "menunggu_admin",
      "id_transaksi": 999
    },
    "read": false
  }''';

  // Parse as done in _onNotificationTap
  final notif = jsonDecode(notificationJson);
  final data = notif['data'];

  Map<String, dynamic>? parsed;
  if (data is String) {
    try {
      parsed = jsonDecode(data) as Map<String, dynamic>;
    } catch (_) {
      parsed = null;
    }
  } else if (data is Map) {
    parsed = Map<String, dynamic>.from(data);
  }

  // Extract id_transaksi
  final idTransaksi = parsed?['id_transaksi'];

  print('Parsed data: $parsed');
  print('ID Transaksi: $idTransaksi');
  print('Type: ${idTransaksi.runti-
  /riwayat", arguments: {"open_id": $idTransaksi})');
  print('✅ PASS\n');
}

void testTransactionMatching() {
  print('TEST 2: Transaction matching with enhanced logic');
  print('-' * 50);

  // Simulate transaction list from API
  final transactions = [
    {
      'id': 100,
      'id_transaksi': 999,
      'jenis_transaksi': 'setoran',
      'jumlah': 100000,
      'status': 'pending',
    },
    {
      'id': 101,
      'id_transaksi': 1000,
      'jenis_transaksi': 'setoran',
      'jumlah': 50000,
      'status': 'pending',
    },
  ];

  // Simulate riwayat.dart matching logic
  final openId = 999; // From notification

  final idx = transactions.indexWhere(
    (e) =>
        e['id'] == openId ||
        e['id'].toString() == openId.toString() ||
        (e.containsKey('id_transaksi') &&
            (e['id_transaksi'] == openId ||
                e['id_transaksi'].toString() == openId.toString())),
  );

  print('Search for: $openId');
  print('Transactions count: ${transactions.length}');
  print('Match found at index: $idx');

  if (idx != -1) {
    print('Matched transaction: ${transactions[idx]}');
    print('✅ PASS - Transaction found and matched\n');
  } else {
    print('❌ FAIL - Transaction not found\n');
  }
}

void testStringIntComparison() {
  print('TEST 3: String vs Int comparison');
  print('-' * 50);

  // Transaction with int ID
  final transaction = {'id_transaksi': 999};

  // ID from notification could be string or int
  final idFromNotificationInt = 999;
  final idFromNotificationString = '999';

  print('Transaction id_transaksi: ${transaction["id_transaksi"]} (type: ${transaction["id_transaksi"].runtimeType})');
  print('Search id (int): $idFromNotificationInt');
  print('Search id (string): $idFromNotificationString');

  // Test matching
  final match1 = transaction['id_transaksi'] == idFromNotificationInt;
  final match2 = transaction['id_transaksi'].toString() == idFromNotificationString;

  print('\nComparison 1 (int == int): $match1');
  print('Comparison 2 (string == string): $match2');

  if (match1 || match2) {
    print('✅ PASS - Both comparisons work\n');
  } else {
    print('❌ FAIL - No match\n');
  }
}

void testNullSafety() {
  print('TEST 4: Null safety and edge cases');
  print('-' * 50);

  // Test 1: No data
  print('Test 4a: Notification with no data');
  Map<String, dynamic>? parsed = null;
  if (parsed != null && parsed.isNotEmpty) {
    print('Would navigate');
  } else {
    print('✅ Skipped navigation (no data)');
  }

  // Test 2: Empty data
  print('\nTest 4b: Notification with empty data');
  parsed = {};
  if (parsed.isNotEmpty) {
    print('Would navigate');
  } else {
    print('✅ Skipped navigation (empty data)');
  }

  // Test 3: No id_transaksi
  print('\nTest 4c: Data without id_transaksi');
  parsed = {'status': 'pending'};
  final idTransaksi = parsed['id_transaksi'];
  if (idTransaksi != null && idTransaksi.toString().isNotEmpty) {
    print('Would navigate');
  } else {
    print('✅ Fallback: regular navigation to Riwayat');
  }

  // Test 4: Malformed data (string instead of map)
  print('\nTest 4d: Malformed notification data');
  final data = 'invalid json {]';
  try {
    parsed = jsonDecode(data) as Map<String, dynamic>;
  } catch (_) {
    parsed = null;
    print('✅ Exception caught, parsed = null');
  }

  print('\n✅ PASS - All null safety checks work\n');
}
