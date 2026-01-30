// ignore_for_file: non_constant_identifier_names

class Tabungan {
  String? id_transaksi;
  String? id_tabungan;
  String? nama;
  String? kelas;
  DateTime tanggal;
  String? kegiatan;
  String? no_masuk;
  String? no_keluar;
  String? nominal_m;
  String? nominal_k;
  String? namauser;
  String? created_at;

  Tabungan({
    this.id_transaksi,
    this.id_tabungan,
    this.nama,
    this.kelas,
    required this.tanggal,
    this.kegiatan,
    this.no_masuk,
    this.no_keluar,
    this.nominal_m,
    this.nominal_k,
    this.namauser,
    this.created_at,
  });

  factory Tabungan.fromJson(Map<String, dynamic> json) => Tabungan(
    id_transaksi: json['id_transaksi']?.toString(),
    id_tabungan: json['id_tabungan']?.toString(),
    nama: json['nama']?.toString(),
    kelas: json['kelas']?.toString(),
    kegiatan: json['kegiatan']?.toString(),
    tanggal: json['tanggal'] != null ? DateTime.parse(json['tanggal'].toString()) : DateTime.now(),
    no_masuk: json['no_masuk']?.toString(),
    no_keluar: json['no_keluar']?.toString(),
    nominal_m: json['nominal_m']?.toString(),
    nominal_k: json['nominal_k']?.toString(),
    namauser: json['namauser']?.toString(),
    created_at: json['created_at']?.toString(),
  );

  Map<String, dynamic> toJson() => {
    'id_transaksi': id_transaksi,
    'id_tabungan': id_tabungan,
    'nama': nama,
    'kelas': kelas,
    'kegiatan': kegiatan,
    'no_masuk': no_masuk,
    'no_keluar': no_keluar,
    'nominal_m': nominal_m,
    'nominal_k': nominal_k,
    'namauser': namauser,
    'created_at': created_at,
  };
}
