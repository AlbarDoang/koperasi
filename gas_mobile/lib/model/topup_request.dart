class TopUpRequest {
  final int nominal;
  final String metode;
  final String? bank;
  final String? ewallet;
  final String? purpose; // jenis tabungan

  TopUpRequest({
    required this.nominal,
    required this.metode,
    this.bank,
    this.ewallet,
    this.purpose,
  });
}
