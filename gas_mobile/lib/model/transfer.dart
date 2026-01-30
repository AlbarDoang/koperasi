// ignore_for_file: non_constant_identifier_names

class Transfer {
  String? id_transfer;
  String? no_transfer;
  String? id_pengirim;
  String? nama_pengirim;
  String? kelas_pengirim;
  String? nominal;
  DateTime tanggal;
  String? id_penerima;
  String? nama_penerima;
  String? kelas_penerima;
  String? keterangan;
  DateTime waktu;
  String? created_at;

  Transfer({
    this.id_transfer,
    this.no_transfer,
    this.id_pengirim,
    this.nama_pengirim,
    this.kelas_pengirim,
    this.nominal,
    required this.tanggal,
    this.id_penerima,
    this.nama_penerima,
    this.kelas_penerima,
    this.keterangan,
    required this.waktu,
    this.created_at,
  });

  factory Transfer.fromJson(Map<String, dynamic> json) => Transfer(
    id_transfer: json['id_transfer']?.toString(),
    no_transfer: json['no_transfer']?.toString(),
    id_pengirim: json['id_pengirim']?.toString(),
    nama_pengirim: json['nama_pengirim']?.toString(),
    kelas_pengirim: json['kelas_pengirim']?.toString(),
    nominal: json['nominal']?.toString(),
    tanggal: json['tanggal'] != null ? DateTime.parse(json['tanggal'].toString()) : DateTime.now(),
    id_penerima: json['id_penerima']?.toString(),
    nama_penerima: json['nama_penerima']?.toString(),
    kelas_penerima: json['kelas_penerima']?.toString(),
    keterangan: json['keterangan']?.toString(),
    waktu: json['waktu'] != null ? DateTime.parse(json['waktu'].toString()) : DateTime.now(),
    created_at: json['created_at']?.toString(),
  );

  Map<String, dynamic> toJson() => {
    'id_transfer': id_transfer,
    'no_transfer': no_transfer,
    'id_pengirim': id_pengirim,
    'nama_pengirim': nama_pengirim,
    'kelas_pengirim': kelas_pengirim,
    'nominal': nominal,
    'id_penerima': id_penerima,
    'nama_penerima': nama_penerima,
    'kelas_penerima': kelas_penerima,
    'keterangan': keterangan,
    'created_at': created_at,
  };
}
