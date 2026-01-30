// ignore_for_file: non_constant_identifier_names

class Penarikan {
  String? id_keluar;
  String? id_tabungan;
  String? nama;
  DateTime tanggal;
  String? kelas;
  String? no_keluar;
  String? jumlah;
  String? keterangan;
  String? status;
  String? approved_by;
  String? approved_at;
  String? created_at;

  Penarikan({
    this.id_keluar,
    this.id_tabungan,
    this.nama,
    required this.tanggal,
    this.kelas,
    this.no_keluar,
    this.jumlah,
    this.keterangan,
    this.status,
    this.approved_by,
    this.approved_at,
    this.created_at,
  });

  factory Penarikan.fromJson(Map<String, dynamic> json) => Penarikan(
    id_keluar: json['id_keluar']?.toString(),
    id_tabungan: json['id_tabungan']?.toString(),
    nama: json['nama']?.toString(),
    kelas: json['kelas']?.toString(),
    tanggal: json['tanggal'] != null ? DateTime.parse(json['tanggal'].toString()) : DateTime.now(),
    jumlah: json['jumlah']?.toString(),
    no_keluar: json['no_keluar']?.toString(),
    keterangan: json['keterangan']?.toString(),
    status: json['status']?.toString(),
    approved_by: json['approved_by']?.toString(),
    approved_at: json['approved_at']?.toString(),
    created_at: json['created_at']?.toString(),
  );

  Map<String, dynamic> toJson() => {
    'id_keluar': id_keluar,
    'id_tabungan': id_tabungan,
    'nama': nama,
    'kelas': kelas,
    'no_keluar': no_keluar,
    'jumlah': jumlah,
    'keterangan': keterangan,
    'status': status,
    'approved_by': approved_by,
    'approved_at': approved_at,
    'created_at': created_at,
  };
}
