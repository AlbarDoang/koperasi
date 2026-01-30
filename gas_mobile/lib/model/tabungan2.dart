// ignore_for_file: non_constant_identifier_names

class Tabungan2 {
  String? id_transaksi;
  String? id_tabungan;
  String? nama;
  String? kelas;
  String? tanggal;
  String? keterangan;
  String? no_masuk;
  String? no_keluar;
  String? kegiatan;
  String? nominal_m;
  String? nominal_k;
  String? created_at;

  Tabungan2({
    this.id_transaksi,
    this.id_tabungan,
    this.nama,
    this.kelas,
    required this.tanggal,
    this.keterangan,
    this.no_masuk,
    this.no_keluar,
    this.kegiatan,
    this.nominal_m,
    this.nominal_k,
    this.created_at,
  });

  factory Tabungan2.fromJson(Map<String, dynamic> json) => Tabungan2(
    id_transaksi: json['id_transaksi']?.toString(),
    id_tabungan: json['id_tabungan']?.toString(),
    nama: json['nama']?.toString(),
    kelas: json['kelas']?.toString(),
    tanggal: json['tanggal']?.toString(),
    keterangan: json['keterangan']?.toString(),
    no_masuk: json['no_masuk']?.toString(),
    no_keluar: json['no_keluar']?.toString(),
    kegiatan: json['kegiatan']?.toString(),
    nominal_m: json['nominal_m']?.toString(),
    nominal_k: json['nominal_k']?.toString(),
    created_at: json['created_at']?.toString(),
  );

  Map<String, dynamic> toJson() => {
    'id_transaksi': id_transaksi,
    'id_tabungan': id_tabungan,
    'nama': nama,
    'kelas': kelas,
    'tanggal': tanggal,
    'keterangan': keterangan,
    'no_masuk': no_masuk,
    'no_keluar': no_keluar,
    'kegiatan': kegiatan,
    'nominal_m': nominal_m,
    'nominal_k': nominal_k,
    'created_at': created_at,
  };
}
