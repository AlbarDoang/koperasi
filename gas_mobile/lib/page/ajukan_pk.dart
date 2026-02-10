import 'package:tabungan/src/file_io.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:tabungan/services/notification_service.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:tabungan/page/dashboard.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/config/api.dart';

// Notification types used by top notifications
enum _NotifType { success, error, warning }

class AjukanPkPage extends StatefulWidget {
  final String? itemName;
  final double? price;
  final double? dp;
  final int? tenor;

  const AjukanPkPage({Key? key, this.itemName, this.price, this.dp, this.tenor}) : super(key: key);

  @override
  State<AjukanPkPage> createState() => _AjukanPkPageState();
}

class _AjukanPkPageState extends State<AjukanPkPage> {
  final ImagePicker _picker = ImagePicker();
  XFile? ktpImage;
  XFile? barangImage;
  bool _loading = false;

  String _formatCurrency(double v) {
    final intVal = v.round();
    final s = intVal.toString().replaceAllMapped(RegExp(r"\B(?=(\d{3})+(?!\d))"), (m) => '.');
    return 'Rp$s';
  }

  Future<void> _pickImage(bool isKtp, ImageSource source) async {
    try {
      final picked = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 85,
      );
      if (picked == null) return;

      setState(() {
        if (isKtp) {
          ktpImage = picked;
        } else {
          barangImage = picked;
        }
      });
    } catch (e) {
      _showTopSnackBar('Gagal memilih gambar');
    }
  }

  void _showImageSourceSheet(bool isKtp) {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Ambil Foto'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(isKtp, ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Pilih dari Galeri'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(isKtp, ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.close),
              title: const Text('Batal'),
              onTap: () => Navigator.pop(context),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildUploadBox({
    required String label,
    XFile? image,
    required VoidCallback onTap,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        GestureDetector(
          onTap: onTap,
          child: Container(
            width: 165,
            height: 165,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              color: Colors.white,
              border: Border.all(color: Colors.grey.shade200, width: 1.5),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            clipBehavior: Clip.hardEdge,
            child: image == null
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.camera_alt_outlined,
                          size: 40,
                          color: Colors.grey.shade400,
                        ),
                        const SizedBox(height: 10),
                        Text(
                          'Upload Foto',
                          style: GoogleFonts.roboto(
                            fontSize: 12,
                            color: Colors.grey.shade500,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  )
                : (!kIsWeb
                    ? Image.file(
                        File(image.path),
                        fit: BoxFit.cover,
                        width: double.infinity,
                        height: double.infinity,
                      )
                    : const Center(child: Text('Preview tidak tersedia pada web'))),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          label,
          style: GoogleFonts.roboto(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: Colors.black87,
          ),
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Future<void> _submit() async {
    // Only foto barang is required here; e-KTP is expected to be present in user profile.
    if (barangImage == null) {
      _showTopSnackBar(
        'Harap upload foto barang terlebih dahulu.',
        type: _NotifType.warning,
      );
      return;
    }

    setState(() => _loading = true);

    try {
      // Prepare fields (either from widget params or leave blank)
      final itemName = widget.itemName ?? '';
      final price = widget.price != null ? widget.price!.round().toString() : '';
      final dp = widget.dp != null ? widget.dp!.round().toString() : '';
      final tenor = widget.tenor != null ? widget.tenor!.toString() : '';

      // NOTE: for emulator use 10.0.2.2 to reach host localhost. Change if using a device.
      // Use configured API endpoint (resolves between emulator and LAN automatically)
      final uri = Uri.parse(Api.pinjamanKreditSubmit);
      final req = http.MultipartRequest('POST', uri);

      final user = await EventPref.getUser();
      if (user == null || user.id == null || user.id!.isEmpty) {
        _showTopSnackBar('Gagal mengambil identitas pengguna. Silakan login kembali.', type: _NotifType.error);
        setState(() => _loading = false);
        return;
      }
      req.fields['id_pengguna'] = user.id!;
      req.fields['nama_barang'] = itemName;
      if (price.isNotEmpty) req.fields['harga'] = price;
      if (dp.isNotEmpty) req.fields['dp'] = dp;
      if (tenor.isNotEmpty) req.fields['tenor'] = tenor;
      // accepted_terms may be required by server-side; set to 1
      req.fields['accepted_terms'] = '1';

      // Attach barang image
      final fileBytes = await barangImage!.readAsBytes();
      final filename = barangImage!.path.split('/').last;
      final ext = filename.contains('.') ? filename.split('.').last.toLowerCase() : 'jpg';
      final contentType = (ext == 'png') ? MediaType('image', 'png') : (ext == 'webp') ? MediaType('image', 'webp') : MediaType('image', 'jpeg');
      req.files.add(http.MultipartFile.fromBytes('foto_barang', fileBytes, filename: filename, contentType: contentType));

      final streamed = await req.send();
      final resp = await http.Response.fromStream(streamed);

      Map<String, dynamic> data = {};
      try {
        data = json.decode(resp.body) as Map<String, dynamic>;
      } catch (_) {
        data = {};
      }

      if (resp.statusCode >= 200 && resp.statusCode < 300) {
        if (data['status'] == true || data['success'] == true) {
          // Success: navigate to status or show banner
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => const Dashboard(bannerMessage: 'Pengajuan berhasil dikirim!'),
            ),
          );
          return;
        } else {
          _showTopSnackBar('Server: ${data['message'] ?? data['error'] ?? 'Gagal mengirim'}', type: _NotifType.error);
        }
      } else if (resp.statusCode >= 400 && resp.statusCode < 500) {
        _showTopSnackBar('Request error: ${data['message'] ?? data['error'] ?? 'Bad request (HTTP ${resp.statusCode})'}', type: _NotifType.error);
      } else {
        _showTopSnackBar('Terjadi kesalahan server (HTTP ${resp.statusCode}). Silakan coba lagi nanti.', type: _NotifType.error);
      }
    } on Exception catch (e) {
      debugPrint('Exception when submitting kredit: $e');
      // Show helpful retry dialog offering to switch base URL mode
      _showNetworkRetryDialog();
    } finally {
      setState(() => _loading = false);
    }
  }

  void _showTopSnackBar(
    String message, {
    _NotifType type = _NotifType.warning,
  }) {
    switch (type) {
      case _NotifType.success:
        NotificationService.showSuccess(message);
        break;
      case _NotifType.error:
        NotificationService.showError(message);
        break;
      case _NotifType.warning:
        NotificationService.showWarning(message);
        break;
    }
  }

  // Show a dialog with options to retry using emulator/LAN base URL
  void _showNetworkRetryDialog() {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Gagal Menghubungi Server', style: GoogleFonts.roboto()),
        content: Text(
          'Perangkat tidak dapat terhubung ke server. Anda dapat mencoba mode koneksi alternatif atau periksa bahwa XAMPP/Apache berjalan di mesin host.',
          style: GoogleFonts.roboto(fontSize: 14),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              // Try emulator mode (10.0.2.2)
              await Api.setOverride(Api.overrideEmulator);
              Navigator.pop(ctx);
              _showTopSnackBar('Mode emulator diaktifkan. Silakan coba kirim lagi.', type: _NotifType.success);
            },
            child: const Text('Gunakan Emulator'),
          ),
          TextButton(
            onPressed: () async {
              // Try LAN mode (use default LAN in Api)
              await Api.setOverride(Api.overrideLan);
              Navigator.pop(ctx);
              _showTopSnackBar('Mode LAN diaktifkan. Silakan coba kirim lagi.', type: _NotifType.success);
            },
            child: const Text('Gunakan LAN'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
            },
            child: const Text('Batal'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Pengajuan Kredit',
          style: GoogleFonts.roboto(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: Colors.white,
          ),
        ),
        backgroundColor: const Color(0xFFFF4C00),
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      backgroundColor: const Color(0xFFF5F5F5),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // If arrived with data, show a read-only summary card
              if (widget.itemName != null) ...[
                Card(
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Ringkasan Pengajuan', style: GoogleFonts.roboto(fontSize: 15, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 8),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Nama Barang', style: GoogleFonts.roboto(color: Colors.black54)), Text(widget.itemName ?? '-', style: GoogleFonts.roboto(fontWeight: FontWeight.w700))]),
                        const SizedBox(height: 6),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Harga', style: GoogleFonts.roboto(color: Colors.black54)), Text(_formatCurrency(widget.price ?? 0), style: GoogleFonts.roboto(fontWeight: FontWeight.w700))]),
                        const SizedBox(height: 6),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('DP', style: GoogleFonts.roboto(color: Colors.black54)), Text(_formatCurrency(widget.dp ?? 0), style: GoogleFonts.roboto(fontWeight: FontWeight.w700))]),
                        const SizedBox(height: 6),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Tenor', style: GoogleFonts.roboto(color: Colors.black54)), Text('${widget.tenor ?? 0} bulan', style: GoogleFonts.roboto(fontWeight: FontWeight.w700))]),
                        const SizedBox(height: 8),
                        Divider(),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 18),
              ],
              // Section: Unggah Dokumen
              Card(
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                color: Colors.white,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Unggah Dokumen',
                        style: GoogleFonts.roboto(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: Colors.black87,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Silakan unggah dokumen yang dibutuhkan untuk memproses pengajuan kredit Anda.',
                        style: GoogleFonts.roboto(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 24),
                      // Upload boxes row: show KTP preview (do not require re-upload) + required Foto Barang
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          // KTP preview - non tappable. If app has stored KTP, show it here. Otherwise show info text.
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.center,
                            children: [
                              Container(
                                width: 165,
                                height: 165,
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(16),
                                  color: Colors.white,
                                  border: Border.all(color: Colors.grey.shade200, width: 1.5),
                                  boxShadow: [
                                    BoxShadow(
                                      color: Colors.black.withOpacity(0.05),
                                      blurRadius: 8,
                                      offset: const Offset(0, 2),
                                    ),
                                  ],
                                ),
                                clipBehavior: Clip.hardEdge,
                                child: ktpImage == null
                                    ? Center(
                                        child: Padding(
                                          padding: const EdgeInsets.all(12.0),
                                          child: Text(
                                            'Preview e-KTP\n(terambil dari profil). Tidak perlu upload ulang',
                                            style: GoogleFonts.roboto(
                                              fontSize: 12,
                                              color: Colors.grey.shade600,
                                              fontWeight: FontWeight.w500,
                                            ),
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      )
                                    : (!kIsWeb
                                    ? Image.file(
                                        File(ktpImage!.path),
                                        fit: BoxFit.cover,
                                        width: double.infinity,
                                        height: double.infinity,
                                      )
                                    : const Center(child: Text('Preview tidak tersedia pada web'))),
                              ),
                              const SizedBox(height: 12),
                              Text(
                                'Preview e-KTP',
                                style: GoogleFonts.roboto(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.black87,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ),

                          // Foto barang - required
                          _buildUploadBox(
                            label: 'Upload Foto Barang',
                            image: barangImage,
                            onTap: () => _showImageSourceSheet(false),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 28),
              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    backgroundColor: const Color(0xFFFF4C00),
                    disabledBackgroundColor: Colors.grey.shade300,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 2,
                  ),
                  child: _loading
                      ? const SizedBox(
                          height: 24,
                          width: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          'Kirim Pengajuan',
                          style: GoogleFonts.roboto(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                ),
              ),
              const SizedBox(height: 20),
              // Helper text
              Center(
                child: Text(
                  'Pastikan foto jelas dan dokumen lengkap',
                  style: GoogleFonts.roboto(
                    fontSize: 12,
                    color: Colors.grey.shade500,
                    fontWeight: FontWeight.w400,
                  ),
                ),
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }
}
