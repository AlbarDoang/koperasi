/**
 * ============================================================================
 * CONTOH IMPLEMENTASI: Upload Foto Profil di Update Profile Page
 * ============================================================================
 * 
 * File: lib/page/update_profile_example.dart
 * 
 * Deskripsi:
 *   Halaman untuk edit profil pengguna dengan fitur upload foto profil.
 *   Mendemonstrasikan integrasi lengkap dengan event_db.dart dan SharedPreferences.
 * 
 * Fitur:
 *   1. Load data profil pengguna dari SharedPreferences saat init
 *   2. Edit field profil (nama, alamat, tanggal lahir, dll)
 *   3. Pilih gambar dari gallery menggunakan image_picker
 *   4. Upload gambar ke server endpoint update_foto_profil.php
 *   5. Update UI secara real-time setelah upload berhasil
 *   6. Tampilkan preview gambar lokal atau URL dari server
 *   7. Save perubahan profil ke database
 * 
 * ============================================================================
 */

import 'package:tabungan/src/file_io.dart';
import 'package:flutter/foundation.dart' show kIsWeb, kDebugMode;
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/utils/custom_toast.dart';

class UpdateProfileExamplePage extends StatefulWidget {
  const UpdateProfileExamplePage({Key? key}) : super(key: key);

  @override
  State<UpdateProfileExamplePage> createState() => _UpdateProfileExamplePageState();
}

class _UpdateProfileExamplePageState extends State<UpdateProfileExamplePage> {
  // ============================================================================
  // 1. DEKLARASI VARIABEL DAN CONTROLLER
  // ============================================================================

  /// User model yang akan diedit
  User? _user;

  /// File image yang dipilih dari gallery (local storage)
  File? _selectedImageFile;

  /// Flag untuk menandai sedang loading/uploading
  bool _isLoading = false;

  /// TextEditingController untuk form fields
  late TextEditingController _namaController;
  late TextEditingController _alamatController;
  late TextEditingController _noHpController;
  late TextEditingController _tglLahirController;

  /// ImagePicker instance
  final ImagePicker _imagePicker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _initializeForm();
  }

  // ============================================================================
  // 2. FUNGSI INISIALISASI FORM
  // ============================================================================

  /// Fungsi untuk inisialisasi form dengan data user dari SharedPreferences
  Future<void> _initializeForm() async {
    try {
      setState(() => _isLoading = true);

      // Load user data dari SharedPreferences
      final userJson = await EventPref.loadUser();
      if (userJson == null) {
        _showError('Data user tidak ditemukan');
        return;
      }

      // Parse JSON string menjadi User object
      _user = User.fromJson(Map<String, dynamic>.from(
        Map.from(userJson as Map)
      ));

      if (_user == null) {
        _showError('Gagal parse data user');
        return;
      }

      // Refresh profile from server to get a fresh signed foto URL (signed URLs are short-lived)
      try {
        final fresh = await EventDB.getProfilLengkap(_user!.id ?? '');
        if (fresh != null) {
          _user = fresh;
        }
      } catch (e) {
        if (kDebugMode) debugPrint('⚠️ getProfilLengkap failed during init: $e');
      }

      // Inisialisasi controller dengan data user
      _namaController = TextEditingController(text: _user?.nama_lengkap ?? '');
      _alamatController = TextEditingController(text: _user?.alamat ?? '');
      _noHpController = TextEditingController(text: _user?.no_hp ?? '');
      _tglLahirController = TextEditingController(text: _user?.tanggal_lahir ?? '');

      setState(() => _isLoading = false);
    } catch (e) {
      _showError('Error inisialisasi: $e');
      setState(() => _isLoading = false);
    }
  }

  @override
  void dispose() {
    // Hapus controller saat widget di-dispose
    _namaController.dispose();
    _alamatController.dispose();
    _noHpController.dispose();
    _tglLahirController.dispose();
    super.dispose();
  }

  // ============================================================================
  // 3. FUNGSI PEMILIHAN GAMBAR DARI GALLERY
  // ============================================================================

  /// Fungsi untuk membuka gallery dan memilih gambar
  /// Dipanggil ketika user tap tombol "Ganti Foto"
  Future<void> _pickImageFromGallery() async {
    try {
      // Buka gallery picker
      final XFile? pickedFile = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 80, // Compress quality untuk mengurangi ukuran
      );

      if (pickedFile == null) {
        // User membatalkan pemilihan
        return;
      }

      // Convert XFile ke File
      final File imageFile = File(pickedFile.path);

      // Validasi ukuran file
      final fileSize = await imageFile.length();
      const maxSize = 2097152; // 2MB dalam bytes

      if (fileSize > maxSize) {
        _showError('Ukuran file terlalu besar. Maksimal 2MB');
        return;
      }

      // Update state dengan file yang dipilih
      setState(() {
        _selectedImageFile = imageFile;
      });

      // Langsung upload ke server
      await _uploadFotoToServer();

    } catch (e) {
      _showError('Error memilih gambar: $e');
    }
  }

  // ============================================================================
  // 4. FUNGSI UPLOAD FOTO KE SERVER
  // ============================================================================

  /// Fungsi untuk upload foto profil ke server
  /// Memanggil EventDB.uploadFotoProfil() yang sudah diimplementasikan
  Future<void> _uploadFotoToServer() async {
    if (_user?.id == null || _selectedImageFile == null) {
      _showError('Data tidak lengkap');
      return;
    }

    try {
      setState(() => _isLoading = true);

      // Panggil fungsi upload dari event_db.dart
      final uploadedUrl = await EventDB.uploadFotoProfil(
        _user!.id!,
        _selectedImageFile!,
      );

      if (uploadedUrl != null) {
        // Upload sukses, refresh profil dari server to get fotoUpdatedAt and fresh URL
        try {
          final fresh = await EventDB.getProfilLengkap(_user!.id ?? '');
          if (fresh != null) {
            setState(() {
              _user = fresh;
            });
          } else {
            setState(() {
              _user!.foto = uploadedUrl; // fallback
            });
          }
        } catch (_) {
          setState(() {
            _user!.foto = uploadedUrl; // fallback
          });
        }

        // Clear selected temp image preview
        setState(() {
          _selectedImageFile = null;
        });

        // Semua pembaruan ke SharedPreferences dan controller
        // sudah dilakukan oleh EventDB.uploadFotoProfil()
      } else {
        _showError('Gagal upload foto');
        setState(() {
          _selectedImageFile = null;
        });
      }

    } catch (e) {
      _showError('Error upload: $e');
      setState(() {
        _selectedImageFile = null;
      });
    } finally {
      setState(() => _isLoading = false);
    }
  }

  // ============================================================================
  // 5. FUNGSI SIMPAN PERUBAHAN PROFIL
  // ============================================================================

  /// Fungsi untuk simpan perubahan data profil (selain foto)
  /// Bisa memanggil API update_biodata.php
  Future<void> _saveProfileChanges() async {
    try {
      setState(() => _isLoading = true);

      // Update user object dengan data dari form
      _user!.nama_lengkap = _namaController.text;
      _user!.alamat = _alamatController.text;
      _user!.no_hp = _noHpController.text;
      _user!.tanggal_lahir = _tglLahirController.text;

      // Simpan ke SharedPreferences
      await EventPref.saveUser(_user!);

      // Update in-memory controller
      try {
        Get.find<CUser>().setUser(_user!);
      } catch (_) {}

      _showSuccess('Profil berhasil diperbarui');

      setState(() => _isLoading = false);

    } catch (e) {
      _showError('Error menyimpan: $e');
      setState(() => _isLoading = false);
    }
  }

  // ============================================================================
  // 6. FUNGSI HELPER UNTUK TOAST
  // ============================================================================

  void _showSuccess(String message) {
    if (mounted) {
      CustomToast.success(context, message);
    }
  }

  void _showError(String message) {
    if (mounted) {
      CustomToast.error(context, message);
    }
  }

  // ============================================================================
  // 7. BUILD WIDGET - MAIN UI
  // ============================================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Edit Profil',
          style: GoogleFonts.poppins(fontWeight: FontWeight.w600),
        ),
        centerTitle: true,
        elevation: 0,
      ),
      body: _isLoading && _user == null
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // ===== BAGIAN 1: AVATAR DAN TOMBOL GANTI FOTO =====
                  _buildPhotoSection(),

                  const SizedBox(height: 32),

                  // ===== BAGIAN 2: FORM FIELDS =====
                  _buildFormFields(),

                  const SizedBox(height: 24),

                  // ===== BAGIAN 3: TOMBOL SIMPAN =====
                  _buildSaveButton(),

                  const SizedBox(height: 24),
                ],
              ),
            ),
    );
  }

  // ============================================================================
  // 8. BUILD PHOTO SECTION (AVATAR + TOMBOL GANTI)
  // ============================================================================

  Widget _buildPhotoSection() {
    return Column(
      children: [
        // Avatar/Profile Picture
        GestureDetector(
          onTap: _pickImageFromGallery,
          child: Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.grey[200],
              border: Border.all(color: Colors.grey[300]!, width: 2),
            ),
            child: _buildAvatarContent(),
          ),
        ),

        const SizedBox(height: 16),

        // Tombol Ganti Foto
        ElevatedButton.icon(
          onPressed: _isLoading ? null : _pickImageFromGallery,
          icon: const Icon(Icons.photo_library),
          label: const Text('Pilih Foto Baru'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.blue[600],
            foregroundColor: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          ),
        ),

        const SizedBox(height: 8),

        // Info text
        Text(
          'Format: JPG, JPEG, PNG (Max 2MB)',
          style: GoogleFonts.poppins(
            fontSize: 12,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }

  /// Build konten avatar berdasarkan kondisi foto
  Widget _buildAvatarContent() {
    // Jika ada file yang baru dipilih dari local
    if (_selectedImageFile != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(60),
        child: !kIsWeb
            ? Image.file(
                _selectedImageFile!,
                fit: BoxFit.cover,
              )
            : const Center(child: Text('Preview tidak tersedia pada web')),
      );
    }

    // Jika ada URL foto dari server
    if (_user?.foto != null && _user!.foto!.isNotEmpty) {
      final ts = _user?.fotoUpdatedAt ?? (DateTime.now().millisecondsSinceEpoch ~/ 1000);
      final imageUrl = (_user!.foto!.contains('?') ? '${_user!.foto!}&t=$ts' : '${_user!.foto!}?t=$ts');
      return ClipRRect(
        borderRadius: BorderRadius.circular(60),
        child: Image.network(
          imageUrl,
          key: ValueKey(imageUrl),
          fit: BoxFit.cover,
          gaplessPlayback: true,
          errorBuilder: (context, error, stackTrace) {
            return Container(color: Colors.grey[300], child: const Icon(Icons.person, size: 60, color: Colors.grey));
          },

          loadingBuilder: (context, child, loadingProgress) {
            if (loadingProgress == null) return child;
            return Center(
              child: CircularProgressIndicator(
                value: loadingProgress.expectedTotalBytes != null
                    ? loadingProgress.cumulativeBytesLoaded /
                        loadingProgress.expectedTotalBytes!
                    : null,
              ),
            );
          },
        ),
      );
    }

    // Default icon jika tidak ada foto
    return Center(
      child: Icon(
        Icons.person,
        size: 60,
        color: Colors.grey[400],
      ),
    );
  }

  // ============================================================================
  // 9. BUILD FORM FIELDS
  // ============================================================================

  Widget _buildFormFields() {
    return Column(
      children: [
        // Nama Lengkap Field
        _buildTextField(
          label: 'Nama Lengkap',
          controller: _namaController,
          icon: Icons.person,
        ),

        const SizedBox(height: 16),

        // Nomor HP Field
        _buildTextField(
          label: 'Nomor HP',
          controller: _noHpController,
          icon: Icons.phone,
          readOnly: true, // Nomor HP biasanya tidak bisa diubah
        ),

        const SizedBox(height: 16),

        // Alamat Field
        _buildTextField(
          label: 'Alamat',
          controller: _alamatController,
          icon: Icons.location_on,
          maxLines: 3,
        ),

        const SizedBox(height: 16),

        // Tanggal Lahir Field
        _buildTextField(
          label: 'Tanggal Lahir',
          controller: _tglLahirController,
          icon: Icons.calendar_today,
          readOnly: true,
        ),
      ],
    );
  }

  /// Widget helper untuk membuat text field dengan design yang konsisten
  Widget _buildTextField({
    required String label,
    required TextEditingController controller,
    required IconData icon,
    int maxLines = 1,
    bool readOnly = false,
  }) {
    return TextField(
      controller: controller,
      readOnly: readOnly,
      maxLines: maxLines,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: Colors.blue[600]),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: BorderSide(color: Colors.blue[600]!, width: 2),
        ),
      ),
    );
  }

  // ============================================================================
  // 10. BUILD SAVE BUTTON
  // ============================================================================

  Widget _buildSaveButton() {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: _isLoading ? null : _saveProfileChanges,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.green[600],
          foregroundColor: Colors.white,
          disabledBackgroundColor: Colors.grey[300],
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
        child: _isLoading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  strokeWidth: 2,
                ),
              )
            : Text(
                'Simpan Perubahan',
                style: GoogleFonts.poppins(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),
      ),
    );
  }
}

