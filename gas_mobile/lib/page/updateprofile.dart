import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:tabungan/src/file_io.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:tabungan/page/orange_header.dart';

import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:get/get.dart';

class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  State<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  final _formKey = GlobalKey<FormState>();

  final _namaController = TextEditingController(text: 'Azmi');
  final _noHpController = TextEditingController(text: '089654334345');
  final _alamatController = TextEditingController(
    text: 'Jl. Mawar No. 45, Bandung',
  );
  final _tanggalLahirController = TextEditingController(
    text: '10 Februari 2002',
  );

  bool _loading = true;
  User? _user;

  File? _imageFile;

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: ImageSource.gallery);
    if (pickedFile != null) {
      setState(() {
        _imageFile = File(pickedFile.path);
      });
    }
  }

  Future<void> _uploadPhoto() async {
    if (_imageFile == null || _user == null) {
      return;
    }

    try {
      final uploadedUrl = await EventDB.uploadFotoProfil(_user!.id ?? '', _imageFile!);
      if (uploadedUrl != null) {
        // Refresh profile from server to get fotoUpdatedAt and fresh URL
        try {
          final fresh = await EventDB.getProfilLengkap(_user!.id ?? '');
          if (fresh != null) {
            _user = fresh;
            await EventPref.saveUser(_user!);
            try { Get.find<CUser>().setUser(_user!); } catch (_) {}
          } else {
            // Fallback: set url only
            _user = User(
              id: _user!.id,
              no_hp: _user!.no_hp,
              nama: _user!.nama,
              nama_lengkap: _user!.nama_lengkap,
              alamat: _user!.alamat,
              tanggal_lahir: _user!.tanggal_lahir,
              status_akun: _user!.status_akun,
              created_at: _user!.created_at,
              saldo: _user!.saldo,
              foto: uploadedUrl.isNotEmpty ? uploadedUrl : null,
            );
            await EventPref.saveUser(_user!);
            try { Get.find<CUser>().setUser(_user!); } catch (_) {}
          }
        } catch (e) {
          if (mounted) CustomToast.error(context, 'Gagal menyimpan data lokal');
        }

        CustomToast.success(context, 'Foto profil berhasil diunggah');
      } else {
        CustomToast.error(context, 'Gagal mengunggah foto profil');
      }
    } catch (e) {
      CustomToast.error(context, 'Gagal mengunggah foto profil');
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final pickedDate = await showDatePicker(
      context: context,
      initialDate: DateTime(2000),
      firstDate: DateTime(1950),
      lastDate: DateTime(now.year, now.month, now.day),
      helpText: 'Pilih Tanggal Lahir',
      cancelText: 'Batal',
      confirmText: 'Pilih',
    );

    if (pickedDate != null) {
      setState(() {
        _tanggalLahirController.text =
            "${pickedDate.day}-${pickedDate.month}-${pickedDate.year}";
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    final user = await EventPref.getUser();
    if (user != null) {
      setState(() {
        _user = user;
        _namaController.text = user.nama ?? user.nama_lengkap ?? '';
        // email is not collected at register tahap 1; skip
        _noHpController.text = user.no_hp ?? '';
        _alamatController.text = user.alamat ?? '';
        _tanggalLahirController.text = user.tanggal_lahir ?? '';
        _loading = false;
      });
    } else {
      setState(() {
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: OrangeHeader(title: "Edit Profil"),
      backgroundColor: theme.scaffoldBackgroundColor,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              GestureDetector(
                onTap: _pickImage,
                child: SizedBox(
                  width: 100,
                  height: 100,
                  child: ClipOval(
                    child: Builder(builder: (context) {
                      if (_imageFile != null) {
                        return Image.file(_imageFile!, fit: BoxFit.cover);
                      }
                      if (_user != null && _user!.foto != null && _user!.foto!.isNotEmpty) {
                        final ts = _user!.fotoUpdatedAt ?? (DateTime.now().millisecondsSinceEpoch ~/ 1000);
                        final imageUrl = (_user!.foto!.contains('?') ? '${_user!.foto!}&t=$ts' : '${_user!.foto!}?t=$ts');
                        return Image.network(
                          imageUrl,
                          key: ValueKey(imageUrl),
                          fit: BoxFit.cover,
                          gaplessPlayback: true,
                          errorBuilder: (_, __, ___) => Container(color: Colors.grey[300], child: const Icon(Icons.person, size: 40, color: Colors.grey)),
                        );
                      }
                      return Container(
                        color: Colors.grey[300],
                        child: const Center(
                          child: Icon(Icons.camera_alt, color: Colors.grey, size: 40),
                        ),
                      );
                    }),
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // NAMA
              TextFormField(
                controller: _namaController,
                decoration: const InputDecoration(
                  labelText: 'Nama Lengkap',
                  border: OutlineInputBorder(),
                ),
                validator: (value) =>
                    value!.isEmpty ? 'Nama tidak boleh kosong' : null,
              ),
              const SizedBox(height: 15),

              // Email field removed (not collected during register tahap 1)

              // NOMOR HP
              TextFormField(
                controller: _noHpController,
                decoration: const InputDecoration(
                  labelText: 'Nomor HP',
                  border: OutlineInputBorder(),
                ),
                validator: (value) =>
                    value!.isEmpty ? 'Nomor HP tidak boleh kosong' : null,
              ),
              const SizedBox(height: 15),

              // ALAMAT DOMISILI
              TextFormField(
                controller: _alamatController,
                decoration: const InputDecoration(
                  labelText: 'Alamat Domisili',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
                validator: (value) =>
                    value!.isEmpty ? 'Alamat tidak boleh kosong' : null,
              ),
              const SizedBox(height: 15),

              // TANGGAL LAHIR
              TextFormField(
                controller: _tanggalLahirController,
                readOnly: true,
                decoration: InputDecoration(
                  labelText: 'Tanggal Lahir',
                  border: const OutlineInputBorder(),
                  suffixIcon: IconButton(
                    icon: const Icon(Icons.calendar_today, color: Colors.grey),
                    onPressed: _pickDate,
                  ),
                ),
                validator: (value) =>
                    value!.isEmpty ? 'Tanggal lahir tidak boleh kosong' : null,
              ),
              const SizedBox(height: 25),

              // SIMPAN BUTTON
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF5F0A),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onPressed: () async {
                    if (!_formKey.currentState!.validate()) return;
                    if (_user == null) {
                      CustomToast.error(context, 'User tidak ditemukan');
                      return;
                    }

                    setState(() {
                      _loading = true;
                    });

                    try {
                      // Upload photo if selected
                      if (_imageFile != null) {
                        await _uploadPhoto();
                      }

                      await EventDB.updateBiodata(
                        _user!.id ?? '',
                        '', // role (not required here)
                        _namaController.text.trim(),
                        '', // jk
                        _tanggalLahirController.text.trim(),
                        '', // tempat lahir
                        _alamatController.text.trim(),
                        _noHpController.text.trim(),
                        '', // kelas
                        '', // tanda_pengenal
                        '', // no_pengenal
                        '', // email - omitted
                        '', // nama_ibu
                        '', // nama_ayah
                        '', // no_ortu
                      );

                      // Reload user data from database to ensure UI updates
                      final updatedUser = await EventDB.getProfilLengkap(_user!.id ?? '');
                      if (updatedUser != null) {
                        await EventPref.saveUser(updatedUser);
                        try {
                          Get.find<CUser>().setUser(updatedUser);
                        } catch (_) {}
                      }

                      // Show success toast and return to profile page
                      CustomToast.success(context, 'Profil berhasil diperbarui');
                      
                      // Wait a moment for toast to show, then navigate back
                      await Future.delayed(const Duration(milliseconds: 500));
                      if (mounted) {
                        Navigator.of(context).pop();
                      }
                    } catch (e) {
                      CustomToast.error(context, 'Gagal memperbarui profil');
                    } finally {
                      setState(() {
                        _loading = false;
                      });
                    }
                  },
                  child: const Text(
                    'Simpan Perubahan',
                    style: TextStyle(color: Colors.white, fontSize: 16),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
