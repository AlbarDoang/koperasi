// ignore_for_file: non_constant_identifier_names

class Masuk {
  String? id_masuk;
  String? id_tabungan;
  String? nama;
  DateTime tanggal;
  String? kelas;
  String? no_masuk;
  String? jumlah;
  String? keterangan;
  String? created_at;

  Masuk({
    this.id_masuk,
    this.id_tabungan,
    this.nama,
    required this.tanggal,
    this.kelas,
    this.no_masuk,
    this.jumlah,
    this.keterangan,
    this.created_at,
  });

  factory Masuk.fromJson(Map<String, dynamic> json) => Masuk(
    id_masuk: json['id_masuk']?.toString(),
    id_tabungan: json['id_tabungan']?.toString(),
    nama: json['nama']?.toString(),
    kelas: json['kelas']?.toString(),
    tanggal: json['tanggal'] != null ? DateTime.parse(json['tanggal'].toString()) : DateTime.now(),
    jumlah: json['jumlah']?.toString(),
    no_masuk: json['no_masuk']?.toString(),
    keterangan: json['keterangan']?.toString(),
    created_at: json['created_at']?.toString(),
  );

  Map<String, dynamic> toJson() => {
    'id_masuk': id_masuk,
    'id_tabungan': id_tabungan,
    'nama': nama,
    'kelas': kelas,
    'no_masuk': no_masuk,
    'jumlah': jumlah,
    'keterangan': keterangan,
    'created_at': created_at,
  };
}
