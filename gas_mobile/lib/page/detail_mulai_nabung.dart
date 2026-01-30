import 'package:tabungan/src/file_io.dart';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform, kDebugMode;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/rendering.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:tabungan/model/topup_request.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tabungan/event/event_db.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';
import 'package:get/get.dart';
import 'dart:convert';
import 'package:tabungan/utils/currency_format.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/utils/web_downloader.dart';
import 'package:tabungan/config/api.dart';
import 'package:http/http.dart' as http;

class DetailMulaiNabungPage extends StatefulWidget {
  final TopUpRequest request;

  const DetailMulaiNabungPage({Key? key, required this.request})
    : super(key: key);

  @override
  State<DetailMulaiNabungPage> createState() => _DetailMulaiNabungPageState();
}

class _DetailMulaiNabungPageState extends State<DetailMulaiNabungPage> {
  final ImagePicker _picker = ImagePicker();
  XFile? buktiPembayaran;
  Uint8List? buktiPreviewBytes; // used for web preview
  final GlobalKey _qrBoundaryKey = GlobalKey();
  String _paymentStatus = 'Belum Dibayar'; // default status for bank/ewallet
  bool _isUpdating = false; // loading flag for status update to server

  @override
  void initState() {
    super.initState();
    // default status: for Transfer Bank and E-Wallet start as 'Belum Dibayar'
    if (widget.request.metode == 'Transfer Bank' ||
        widget.request.metode == 'E-Wallet') {
      _paymentStatus = 'Belum Dibayar';
    }
    // For cash top-up, initial state should indicate waiting for user to hand over cash
    if (widget.request.metode == 'Uang Tunai') {
      _paymentStatus = 'Menunggu Penyerahan Uang';
    }
  }

  /// Create a cash top-up request on the server and update its status.
  ///
  /// This function:
  /// 1. Calls buat_mulai_nabung.php to create the request in the database
  /// 2. Calls update_status_mulai_nabung.php to change status to 'menunggu_admin'
  /// 3. Shows loading state, success/error toast
  /// - updates local UI state but DOES NOT add saldo to the user's balance
  Future<void> updateStatusTopup() async {
    setState(() => _isUpdating = true);
    try {
      final prefs = await SharedPreferences.getInstance();

      // Retrieve user data from stored User object (key: 'user')
      String? idTabungan, nomorHp, namaPengguna;
      final userJson = prefs.getString('user');
      if (userJson != null) {
        try {
          final userMap = jsonDecode(userJson) as Map<String, dynamic>;
          // Map from User model fields to required params
          idTabungan = userMap['id']?.toString(); // User.id maps to id_tabungan
          nomorHp = userMap['no_hp']?.toString();
          namaPengguna =
              userMap['nama_lengkap']?.toString() ??
              userMap['nama']?.toString();
        } catch (_) {
          // fallback if JSON parse fails
        }
      }

      if (idTabungan == null ||
          idTabungan.isEmpty ||
          nomorHp == null ||
          nomorHp.isEmpty ||
          namaPengguna == null ||
          namaPengguna.isEmpty) {
        CustomToast.error(
          context,
          'Data pengguna tidak lengkap. Silakan login kembali.',
        );
        setState(() => _isUpdating = false);
        return;
      }

      // Step 1: Create the top-up request in database
      final createUri = Uri.parse('${Api.baseUrl}/buat_mulai_nabung.php');
      final createBody = <String, String>{
        'id_tabungan': idTabungan,
        'nomor_hp': nomorHp,
        'nama_pengguna': namaPengguna,
        'jumlah': widget.request.nominal.toString(),
        'tanggal': DateTime.now().toIso8601String().split(
          'T',
        )[0], // YYYY-MM-DD format
        'jenis_tabungan': widget.request.purpose ?? 'Tabungan Reguler',
      };

      final createResp = await http
          .post(createUri, body: createBody)
          .timeout(const Duration(seconds: 30));
      final createJson = jsonDecode(createResp.body);

      if (createJson['success'] != true) {
        final msg =
            createJson['message']?.toString() ?? 'Gagal membuat permintaan';
        CustomToast.error(context, msg);
        setState(() => _isUpdating = false);
        return;
      }

      final idMulaiNabung = createJson['id_mulai_nabung'];
      if (idMulaiNabung == null) {
        CustomToast.error(context, 'Gagal mendapatkan ID permintaan');
        setState(() => _isUpdating = false);
        return;
      }

      // Step 2: Update status to 'menunggu_admin'
      final updateUri = Uri.parse(
        '${Api.baseUrl}/update_status_mulai_nabung.php',
      );
      final updateBody = <String, String>{
        'id_mulai_nabung': idMulaiNabung.toString(),
      };

      final updateResp = await http
          .post(updateUri, body: updateBody)
          .timeout(const Duration(seconds: 30));
      final updateJson = jsonDecode(updateResp.body);

      final ok = updateJson['success'] == true;
      final message =
          updateJson['message']?.toString() ?? 'Tidak ada pesan dari server';

      if (ok) {
        // Update local UI to reflect the new status
        setState(() {
          _paymentStatus = 'Menunggu Konfirmasi Admin';
        });

        // Add a clear, non-excluded local notification so it appears immediately in Notifikasi
        // NOTE: We NO LONGER save pending transactions to SharedPreferences
        // Only final transactions (approved/rejected) will be saved to Riwayat Transaksi
        try {
          // Build detailed notification message with jenis_tabungan and jumlah
          final jenisTabungan = widget.request.purpose ?? 'Tabungan Reguler';
          final jumlahSetoran = CurrencyFormat.toIdr(widget.request.nominal);
          final detailMessage =
              'Pengajuan setoran tabungan $jenisTabungan Anda sebesar $jumlahSetoran berhasil dikirim dan sedang menunggu verifikasi dari admin.';

          await NotifikasiHelper.addLocalNotification(
            type: 'topup',
            title: 'Pengajuan Setoran Dikirim',
            message: detailMessage,
            data: {'mulai_id': idMulaiNabung?.toString()},
          );
          // Ensure merged notifications are persisted and dashboard badge updates soon
          await NotifikasiHelper.initializeNotifications();
          if (kDebugMode) {
            debugPrint(
              '[DetailMulaiNabung] added local topup notification for id=$idMulaiNabung',
            );
          }
        } catch (e) {
          if (kDebugMode) {
            debugPrint('[DetailMulaiNabung] failed to add local notif: $e');
          }
        }

        CustomToast.success(
          context,
          'Status berhasil diperbarui, silakan tunggu verifikasi admin.',
        );
      } else {
        CustomToast.error(context, message);
      }
    } catch (e) {
      final err = e.toString();
      if (err.contains('timeout')) {
        CustomToast.error(
          context,
          '⏱️ Request timeout - Server tidak merespons',
        );
      } else {
        CustomToast.error(context, 'Gagal memperbarui status: ${err}');
      }
    } finally {
      setState(() => _isUpdating = false);
    }
  }

  Future<void> downloadQrCode() async {
    try {
      // capture the widget inside RepaintBoundary
      await Future.delayed(const Duration(milliseconds: 50));
      final boundary =
          _qrBoundaryKey.currentContext?.findRenderObject()
              as RenderRepaintBoundary?;
      if (boundary == null) {
        CustomToast.error(context, 'Gagal mengambil gambar QR Code');
        return;
      }

      final ui.Image image = await boundary.toImage(
        pixelRatio: ui.window.devicePixelRatio,
      );
      final ByteData? byteData = await image.toByteData(
        format: ui.ImageByteFormat.png,
      );
      if (byteData == null) {
        CustomToast.error(context, 'Gagal mengambil gambar QR Code');
        return;
      }
      final Uint8List bytes = byteData.buffer.asUint8List();

      final fileName = 'qr_topup_${DateTime.now().millisecondsSinceEpoch}.png';

      if (kIsWeb) {
        webDownload(bytes, fileName);
        CustomToast.success(context, 'QR Code berhasil di-download');
        return;
      }

      // Mobile platforms: try to save to gallery / pictures
      if (defaultTargetPlatform == TargetPlatform.android) {
        final status = await Permission.storage.request();
        if (!status.isGranted) {
          CustomToast.error(
            context,
            'Izin penyimpanan dibutuhkan untuk menyimpan gambar',
          );
          return;
        }
      } else if (defaultTargetPlatform == TargetPlatform.iOS) {
        final status = await Permission.photos.request();
        if (!status.isGranted) {
          CustomToast.error(
            context,
            'Izin Photo dibutuhkan untuk menyimpan gambar',
          );
          return;
        }
      }

      // Simpan ke folder Pictures menggunakan path_provider
      try {
        final dirs = await getExternalStorageDirectories(
          type: StorageDirectory.pictures,
        );
        final directory = (dirs != null && dirs.isNotEmpty)
            ? dirs.first
            : await getApplicationDocumentsDirectory();
        final path = '${directory.path}/$fileName';
        final file = File(path);
        await file.writeAsBytes(bytes);
        CustomToast.success(context, 'QR Code disimpan: $path');
      } catch (_) {
        CustomToast.error(context, 'Gagal menyimpan QR Code');
      }
    } catch (e) {
      CustomToast.error(context, 'Terjadi kesalahan saat menyimpan QR Code');
    }
  }

  Future<void> _pickImage() async {
    try {
      final XFile? file = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 80,
      );
      if (file == null) return; // user cancelled

      if (kIsWeb) {
        // Read bytes for web preview
        final bytes = await file.readAsBytes();
        setState(() {
          buktiPembayaran = file;
          buktiPreviewBytes = bytes;
        });
        // mark as processing in saved transactions
        _markTransactionProcessing();
      } else {
        setState(() {
          buktiPembayaran = file;
          buktiPreviewBytes = null;
        });
        // mark as processing in saved transactions
        _markTransactionProcessing();
      }

      CustomToast.success(context, 'Bukti pembayaran berhasil di upload');
    } catch (e) {
      CustomToast.error(context, 'Gagal memilih gambar');
    }
  }

  Future<void> _markTransactionProcessing() async {
    try {
      // update local UI
      setState(() {
        _paymentStatus = 'Proses';
      });

      final prefs = await SharedPreferences.getInstance();
      final txns = prefs.getString('transactions');
      final list = txns != null
          ? (jsonDecode(txns) as List).cast<Map<String, dynamic>>()
          : <Map<String, dynamic>>[];

      // try to find an existing matching topup transaction
      final matchIdx = list.indexWhere((m) {
        if ((m['type'] ?? '') != 'topup') return false;
        final nom = m['nominal'] ?? m['price'] ?? m['amount'];
        final metode = (m['metode'] ?? '').toString();
        final bank = (m['bank'] ?? '').toString();
        final ewallet = (m['ewallet'] ?? '').toString();
        if (nom == null) return false;
        if (int.tryParse(nom.toString()) != widget.request.nominal)
          return false;
        if (metode.isNotEmpty && metode != widget.request.metode) return false;
        if (widget.request.metode == 'Transfer Bank' &&
            bank.isNotEmpty &&
            bank != (widget.request.bank ?? ''))
          return false;
        if (widget.request.metode == 'E-Wallet' &&
            ewallet.isNotEmpty &&
            ewallet != (widget.request.ewallet ?? ''))
          return false;
        return true;
      });

      if (matchIdx != -1) {
        // update existing
        final m = Map<String, dynamic>.from(list[matchIdx]);
        m['status'] = 'pending';
        m['processing'] = true;
        m['keterangan'] = 'Bukti pembayaran diunggah';
        m['updated_at'] = DateTime.now().toIso8601String();
        list[matchIdx] = m;
      } else {
        // append new topup transaction
        final newTxn = {
          'id': DateTime.now().millisecondsSinceEpoch,
          'type': 'topup',
          'metode': widget.request.metode,
          'bank': widget.request.bank,
          'ewallet': widget.request.ewallet,
          'nominal': widget.request.nominal,
          'status': 'pending',
          'processing': true,
          'keterangan': 'Bukti pembayaran diunggah',
          'created_at': DateTime.now().toIso8601String(),
        };
        list.add(newTxn);
      }

      await prefs.setString('transactions', jsonEncode(list));
    } catch (e) {
      // ignore errors but show toast for debugging
      CustomToast.error(context, 'Gagal menyimpan status transaksi');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text('Detail Top-up', style: GoogleFonts.roboto()),
        backgroundColor: const Color(0xFFFF4C00),
        centerTitle: true,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: _buildBody(context, theme),
      ),
    );
  }

  Widget _buildBody(BuildContext context, ThemeData theme) {
    final nominalText = CurrencyFormat.toIdr(widget.request.nominal);
    if (widget.request.metode == 'Transfer Bank') {
      final bankName = widget.request.bank ?? '-';
      final accountNumber = '1234 5678 9012 3456'; // dummy
      final accountName = 'Deni Sumargo (Admin)';
      return ListView(
        children: [
          Text(
            bankName,
            style: GoogleFonts.roboto(
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Nomor Rekening',
                    style: GoogleFonts.roboto(fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        accountNumber,
                        style: GoogleFonts.roboto(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: accountNumber));
                          CustomToast.success(
                            context,
                            'Nomor rekening disalin ke clipboard',
                          );
                        },
                        icon: const Icon(Icons.copy),
                        label: const Text('Salin Nomor Rekening'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('Atas nama: $accountName', style: GoogleFonts.roboto()),
                  const SizedBox(height: 12),
                  Text(
                    'Nominal: $nominalText',
                    style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Instruksi:',
                    style: GoogleFonts.roboto(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '1. Transfer nominal di atas ke rekening tujuan.\n2. Setelah transfer, unggah bukti pembayaran.',
                    style: GoogleFonts.roboto(fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  // preview or placeholder
                  if (buktiPembayaran == null) ...[
                    const Text('Belum ada bukti pembayaran'),
                    const SizedBox(height: 8),
                  ] else ...[
                    SizedBox(
                      height: 80,
                      child: !kIsWeb
                          ? Image.file(File(buktiPembayaran!.path))
                          : (buktiPreviewBytes != null
                                ? Image.memory(buktiPreviewBytes!)
                                : const Text('Preview tidak tersedia')),
                    ),
                    const SizedBox(height: 8),
                  ],
                  Row(
                    children: [
                      ElevatedButton(
                        onPressed: _pickImage,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFF4C00),
                        ),
                        child: Text(
                          'Upload Bukti Pembayaran',
                          style: GoogleFonts.roboto(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const Spacer(),
                      _buildStatusBox(_paymentStatus),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Quick action: go back to Dashboard
                  _buildQuickActionButton(),
                ],
              ),
            ),
          ),
        ],
      );
    }

    if (widget.request.metode == 'E-Wallet') {
      final ewalletName = widget.request.ewallet ?? '-';
      final ewalletNumber = '0898 7654 3210'; // dummy
      return ListView(
        children: [
          Text(
            ewalletName,
            style: GoogleFonts.roboto(
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Nomor E-Wallet',
                    style: GoogleFonts.roboto(fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        ewalletNumber,
                        style: GoogleFonts.roboto(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      TextButton.icon(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: ewalletNumber));
                          CustomToast.success(
                            context,
                            'Nomor e-wallet disalin',
                          );
                        },
                        icon: const Icon(Icons.copy),
                        label: const Text('Copy Nomor'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  RepaintBoundary(
                    key: _qrBoundaryKey,
                    child: Container(
                      height: 150,
                      color: Colors.grey.shade300,
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.qr_code_2,
                              size: 80,
                              color: Colors.grey[600],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'QR tidak tersedia',
                              style: GoogleFonts.roboto(color: Colors.black54),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: downloadQrCode,
                    child: Text(
                      "Download QR Code",
                      style: GoogleFonts.roboto(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFF4C00),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Nominal: $nominalText',
                    style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  // preview or placeholder
                  if (buktiPembayaran == null) ...[
                    const Text('Belum ada bukti pembayaran'),
                    const SizedBox(height: 8),
                  ] else ...[
                    SizedBox(
                      height: 80,
                      child: !kIsWeb
                          ? Image.file(File(buktiPembayaran!.path))
                          : (buktiPreviewBytes != null
                                ? Image.memory(buktiPreviewBytes!)
                                : const Text('Preview tidak tersedia')),
                    ),
                    const SizedBox(height: 8),
                  ],
                  Row(
                    children: [
                      ElevatedButton(
                        onPressed: _pickImage,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFFF4C00),
                        ),
                        child: Text(
                          'Upload Bukti Pembayaran',
                          style: GoogleFonts.roboto(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const Spacer(),
                      _buildStatusBox(_paymentStatus),
                    ],
                  ),
                  const SizedBox(height: 12),
                  // Quick action: go back to Dashboard
                  _buildQuickActionButton(),
                ],
              ),
            ),
          ),
        ],
      );
    }

    // Uang Tunai
    return ListView(
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(12.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Uang Tunai',
                  style: GoogleFonts.roboto(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Silahkan serahkan uang sebesar:',
                  style: GoogleFonts.roboto(),
                ),
                const SizedBox(height: 8),
                Text(
                  nominalText,
                  style: GoogleFonts.roboto(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Jam operasional admin: 08:00 - 16:00',
                  style: GoogleFonts.roboto(),
                ),
                const SizedBox(height: 12),
                // Action area: show button only when user still needs to hand over cash.
                // Otherwise show a waiting message that admin will verify the top-up.
                if (_paymentStatus == 'Menunggu Penyerahan Uang') ...[
                  ElevatedButton(
                    onPressed: _isUpdating
                        ? null
                        : () async {
                            // User confirms they handed over cash. Call server to update status.
                            await updateStatusTopup();
                          },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFFF4C00),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        vertical: 12.0,
                        horizontal: 8.0,
                      ),
                      child: _isUpdating
                          ? SizedBox(
                              height: 18,
                              width: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.0,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  Colors.white,
                                ),
                              ),
                            )
                          : Text(
                              'Saya sudah menyerahkan uang',
                              style: GoogleFonts.roboto(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  // Quick action: go back to Dashboard
                  _buildQuickActionButton(),
                ] else ...[
                  // Inform the user that admin verification is pending.
                  Text(
                    'Menunggu admin memverifikasi top-up Anda.',
                    style: GoogleFonts.roboto(fontStyle: FontStyle.italic),
                  ),
                  const SizedBox(height: 12),
                  // Quick action: go back to Dashboard
                  _buildQuickActionButton(),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildQuickActionButton() {
    return Align(
      alignment: Alignment.centerRight,
      child: ElevatedButton.icon(
        onPressed: () {
          // Navigate back to Dashboard in one step
          Get.offAllNamed('/dashboard');
        },
        icon: const Icon(Icons.dashboard, color: Colors.white),
        label: Text(
          'Kembali ke Dashboard',
          style: GoogleFonts.roboto(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
        ),
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFFFF4C00),
          foregroundColor: Colors.white,
        ),
      ),
    );
  }

  Widget _buildStatusBox(String status) {
    Color color;
    switch (status) {
      case 'Selesai':
        color = Colors.green;
        break;
      case 'Proses':
        color = Colors.amber;
        break;
      case 'Belum Dibayar':
      default:
        color = Colors.red;
    }

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 10),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color),
      ),
      child: Text(
        status,
        style: GoogleFonts.roboto(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
