import 'package:intl/intl.dart';

class CurrencyFormat {
  /// Convert integer to Indonesian Rupiah format
  static String toIdr(int number) {
    final formatter = NumberFormat.currency(
      locale: 'id',
      symbol: 'Rp ',
      decimalDigits: 0,
    );
    return formatter.format(number);
  }
}
