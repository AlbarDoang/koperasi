// ignore_for_file: non_constant_identifier_names

class User {
  String? id;
  String? no_hp;
  String? nama;
  String? nama_lengkap;
  String? alamat;
  String? tanggal_lahir;
  String? status_akun;
  String? created_at;
  double? saldo;
  String? foto;
  int? fotoUpdatedAt; // unix timestamp (seconds)

  User({
    this.id,
    this.no_hp,
    this.nama,
    this.nama_lengkap,
    this.alamat,
    this.tanggal_lahir,
    this.status_akun,
    this.created_at,
    this.saldo,
    this.foto,
    this.fotoUpdatedAt,
  });

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: json['id'] != null ? json['id'].toString() : null,
    no_hp: json['no_hp'] != null ? json['no_hp'].toString() : null,
    nama: json['nama'] != null ? json['nama'].toString() : json['nama_lengkap'],
    nama_lengkap: json['nama_lengkap'] != null ? json['nama_lengkap'].toString() : null,
    // Accept either 'alamat' or 'alamat_domisili' from API responses
    alamat: json['alamat'] != null ? json['alamat'].toString() : (json['alamat_domisili'] != null ? json['alamat_domisili'].toString() : null),
    tanggal_lahir: json['tanggal_lahir'] != null ? json['tanggal_lahir'].toString() : null,
    status_akun: json['status_akun'] != null ? json['status_akun'].toString() : null,
    created_at: json['created_at'] != null ? json['created_at'].toString() : null,
    saldo: json['saldo'] != null ? double.tryParse(json['saldo'].toString()) ?? 0.0 : 0.0,
    foto: json['foto'] != null ? json['foto'].toString() : (json['url'] != null ? json['url'].toString() : null),
    fotoUpdatedAt: json['foto_profil_updated_at'] != null ? int.tryParse(json['foto_profil_updated_at'].toString()) ?? null : null,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'no_hp': no_hp,
    'nama': nama,
    'nama_lengkap': nama_lengkap,
    'alamat': alamat,
    'tanggal_lahir': tanggal_lahir,
    'status_akun': status_akun,
    'created_at': created_at,
    'saldo': saldo,
    'foto': foto,
    'foto_profil_updated_at': fotoUpdatedAt,
  };
}
