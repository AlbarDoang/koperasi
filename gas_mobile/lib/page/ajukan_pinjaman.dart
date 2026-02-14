import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter/foundation.dart';
import 'dart:convert';
import 'dart:async';
import 'dart:math';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/model/user.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/config/api.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:intl/intl.dart';
import 'package:tabungan/controller/notifikasi_helper.dart';

import 'package:tabungan/utils/custom_toast.dart';

class AjukanPinjamanPage extends StatefulWidget {
  const AjukanPinjamanPage({Key? key}) : super(key: key);

  @override
  State<AjukanPinjamanPage> createState() => _AjukanPinjamanPageState();
}

class _AjukanPinjamanPageState extends State<AjukanPinjamanPage> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _reasonController = TextEditingController();
  String? _tenor;
  bool _isUploading = false;

  @override
  void initState() {
    super.initState();
    // Add listener untuk format otomatis saat user mengetik
    _amountController.addListener(_formatCurrency);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  void _formatCurrency() {
    String input = _amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (input.isEmpty) {
      // Ensure UI updates when user clears the field so the preview hides
      setState(() {});
      return;
    }

    final int? value = int.tryParse(input);
    if (value == null) return;

    // Format dengan NumberFormat
    final formatted = NumberFormat.currency(
      locale: 'id_ID',
      symbol: 'Rp ',
      decimalDigits: 0,
    ).format(value);

    // Update text tanpa trigger listener lagi
    if (_amountController.text != formatted) {
      _amountController.value = _amountController.value.copyWith(
        text: formatted,
        selection: TextSelection.collapsed(offset: formatted.length),
      );
      // Trigger widget rebuild so the monthly preview updates while typing
      setState(() {});
    }
  }

  Future<void> _submitForm() async {
    if (_formKey.currentState?.validate() ?? false) {
      final cleaned = _amountController.text.replaceAll(RegExp(r'[^0-9]'), '');
      final int? cleanedInt = int.tryParse(cleaned);
      if (cleanedInt == null) {
        CustomToast.error(context, 'Nominal tidak valid');
        return;
      }

      if (cleanedInt < 500000) {
        CustomToast.error(context, 'Nominal minimal pinjaman Rp 500.000');
        return;
      }

      final formatted = NumberFormat.currency(
        locale: 'id_ID',
        symbol: 'Rp ',
        decimalDigits: 0,
      ).format(cleanedInt);

      Get.dialog(
        AlertDialog(
          title: Text(
            'Konfirmasi',
            style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
          ),
          content: Text(
            'Ajukan pinjaman sebesar $formatted untuk tenor $_tenor bulan?',
          ),
          actions: [
            TextButton(onPressed: () => Get.back(), child: const Text('Batal')),
            TextButton(
              onPressed: () async {
                Get.back();
                await _sendRequest(cleaned);
              },
              child: const Text('Ya, ajukan'),
            ),
          ],
        ),
      );
    }
  }

  Future<void> _sendRequest(String cleaned) async {
    setState(() => _isUploading = true);
    String? finalUrl;
    try {
      await Future.delayed(const Duration(milliseconds: 500));

      final User? user = await EventPref.getUser();
      final userId = user?.id;

      // If we have a logged-in numeric user id, POST to server; otherwise fall back to local save
      if (userId != null && int.tryParse(userId) != null) {
        final int uid = int.parse(userId);
        final int amount = int.tryParse(cleaned) ?? 0;
        final int tenorInt = int.tryParse(_tenor ?? '') ?? 0;
        final String tujuan = _reasonController.text;

        // Build endpoint from Api.baseUrl (remove /flutter_api segment)
        final apiBase = Api.baseUrl.replaceAll('/flutter_api', '');
        finalUrl = '$apiBase/api/pinjaman/submit.php';
        final url = Uri.parse(finalUrl!);

        // Quick sanity check: if running on web and base URL points to Android emulator host
        // (10.0.2.2) it will not be reachable from the browser. Give a helpful message.
        if (kIsWeb && apiBase.contains('10.0.2.2')) {
          CustomToast.error(
            context,
            'API base URL terset ke emulator (10.0.2.2) — tidak dapat dijangkau dari browser. Silakan set override ke LAN.',
          );
          setState(() => _isUploading = false);
          return;
        }

        // Log target for easier debugging
        debugPrint('Submitting loan to: ${finalUrl ?? 'unknown'}');

        final body = jsonEncode({
          'id_pengguna': uid,
          'jumlah_pinjaman': amount,
          'tenor': tenorInt,
          'tujuan_penggunaan': tujuan,
        });

        // Optional quick ping to give clearer diagnostics if the POST fails
        try {
          if (finalUrl != null) {
            final ping = await http
                .get(Uri.parse(finalUrl!))
                .timeout(const Duration(seconds: 5));
            debugPrint('Ping to $finalUrl returned ${ping.statusCode}');
          }
        } catch (e) {
          debugPrint('Ping failed: $e');
        }

        final resp = await http
            .post(
              url,
              headers: {'Content-Type': 'application/json'},
              body: body,
            )
            .timeout(const Duration(seconds: 15));

        // Accept any 2xx as success but verify body.status == true
        if (resp.statusCode >= 200 && resp.statusCode < 300) {
          try {
            final d = jsonDecode(resp.body);
            if (d is Map && d['status'] == true) {
              final insertedId = d['id'] ?? '';
              CustomToast.success(
                context,
                'Pengajuan Pinjaman Berhasil',
              );

              // NOTE: Server-side (submit.php) already creates the notification.
              // Do NOT create a local notification here to avoid duplicates.

              // Navigate user to Dashboard (per product request)
              Get.offAllNamed('/dashboard');
              return;
            }

            // 2xx but status not true: show server message if available
            final msg = (d is Map && d['message'] != null)
                ? d['message']
                : 'Respon server tidak menyatakan sukses';
            CustomToast.error(context, 'Pengajuan gagal: $msg');
          } catch (e) {
            CustomToast.error(
              context,
              'Response tidak dapat di-parse: ${e.toString()}',
            );
          }
        } else {
          String err = 'Status ${resp.statusCode}';
          try {
            final j = jsonDecode(resp.body);
            if (j is Map && j['error'] != null)
              err = j['error'];
            else if (j is Map && j['message'] != null)
              err = j['message'];
            else
              err = resp.body;
          } catch (_) {
            err = resp.body;
          }
          CustomToast.error(context, 'Gagal menghubungi server: $err');
        }
      } else {
        // Guest or no user id: fallback to local storage (existing behaviour)
        final pengajuan = {
          'id': DateTime.now().millisecondsSinceEpoch,
          'user_id': userId ?? 'guest_${DateTime.now().millisecondsSinceEpoch}',
          'nominal': cleaned,
          'tenor': _tenor,
          'reason': _reasonController.text,
          'status': 'pending',
          'created_at': DateTime.now().toIso8601String(),
        };

        final pref = await SharedPreferences.getInstance();
        String? existingData = pref.getString('pengajuan_list');
        List<dynamic> pengajuanList = existingData != null
            ? jsonDecode(existingData)
            : [];

        pengajuanList.add(pengajuan);
        await pref.setString('pengajuan_list', jsonEncode(pengajuanList));

        final nominalStr = pengajuan['nominal']?.toString() ?? cleaned;
        final nominalInt =
            int.tryParse(nominalStr.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0;
        final formatted = NumberFormat.currency(
          locale: 'id_ID',
          symbol: 'Rp ',
          decimalDigits: 0,
        ).format(nominalInt);

        await NotifikasiHelper.addLocalNotification(
          type: 'pinjaman',
          title: 'Pengajuan Pinjaman Diterima',
          message: 'Pengajuan pinjaman sebesar $formatted sedang diproses.',
        );

        CustomToast.success(context, 'Pengajuan berhasil disimpan (offline)');
        // Navigate user to Dashboard (per product request)
        Get.offAllNamed('/dashboard');
      }
    } on TimeoutException {
      CustomToast.error(
        context,
        'Gagal: Permintaan ke server timeout. Coba lagi.',
      );
    } on http.ClientException catch (e) {
      // Provide more actionable diagnostics for ClientException
      debugPrint('ClientException while calling ${finalUrl ?? 'unknown'}: $e');

      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Gagal terhubung ke server'),
          content: Text(
            'Tidak dapat menjangkau API pada ${finalUrl ?? 'API base URL'}. Periksa jaringan atau konfigurasi API base URL.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('OK'),
            ),
            TextButton(
              onPressed: () async {
                // Set override to LAN for convenience and guide user to retry
                await Api.setOverride(Api.overrideLan);
                Navigator.of(ctx).pop();
                CustomToast.success(
                  context,
                  'API base URL di-set ke LAN. Silakan coba lagi.',
                );
              },
              child: const Text('Set ke LAN'),
            ),
          ],
        ),
      );

      CustomToast.error(
        context,
        'Gagal terhubung ke server. Pastikan API base URL dapat dijangkau.',
      );
    } catch (e) {
      CustomToast.error(context, 'Gagal: $e');
    } finally {
      setState(() => _isUploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFFFF4C00),
        centerTitle: true,
        title: Text(
          'Ajukan Pinjaman',
          style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Jumlah Pinjaman',
                style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  hintText: '0',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty)
                    return 'Masukkan jumlah pinjaman';
                  final cleaned = value.replaceAll(RegExp(r'[^0-9]'), '');
                  if (int.tryParse(cleaned) == null)
                    return 'Masukkan nominal yang valid';
                  if (int.parse(cleaned) < 500000)
                    return 'Nominal minimal pinjaman Rp 500.000';
                  return null;
                },
              ),
              const SizedBox(height: 14),

              Text(
                'Tenor',
                style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: _tenor,
                decoration: const InputDecoration(border: OutlineInputBorder()),
                items: ['2', '3', '6', '9', '12']
                    .map(
                      (e) =>
                          DropdownMenuItem(value: e, child: Text('$e bulan')),
                    )
                    .toList(),
                onChanged: (v) => setState(() => _tenor = v),
                validator: (v) => v == null ? 'Pilih tenor' : null,
              ),

              const SizedBox(height: 12),

              // Show monthly payment when amount and tenor are available
              Builder(
                builder: (_) {
                  try {
                    final cleaned = _amountController.text.replaceAll(
                      RegExp(r'[^0-9]'),
                      '',
                    );
                    final int amount = int.tryParse(cleaned) ?? 0;
                    final int tenorInt = int.tryParse(_tenor ?? '') ?? 0;
                    if (amount > 0 && tenorInt > 0) {
                      // Syariah flat: equal monthly installments using floor division, no interest, no admin fees
                      final int base = amount ~/ tenorInt;
                      final monthlyInt = base;
                      final monthlyText = NumberFormat.currency(
                        locale: 'id_ID',
                        symbol: 'Rp ',
                        decimalDigits: 0,
                      ).format(monthlyInt);
                      return Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.grey[50],
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.grey[300]!),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Pembayaran Bulanan',
                                  style: GoogleFonts.roboto(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                Text(
                                  'Syariah: cicilan sama setiap bulan,tanpa bunga,tanpa biaya admin',
                                  style: GoogleFonts.roboto(
                                    fontSize: 11,
                                    color: Colors.grey,
                                  ),
                                ),
                              ],
                            ),
                            Text(
                              monthlyText,
                              style: GoogleFonts.roboto(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                                color: const Color(0xFFFF4C00),
                              ),
                            ),
                          ],
                        ),
                      );
                    }
                  } catch (_) {}
                  return const SizedBox.shrink();
                },
              ),

              const SizedBox(height: 14),

              Text(
                'Tujuan Penggunaan',
                style: GoogleFonts.roboto(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _reasonController,
                decoration: const InputDecoration(border: OutlineInputBorder()),
                maxLines: 3,
                validator: (v) => v == null || v.isEmpty
                    ? 'Masukkan tujuan penggunaan'
                    : null,
              ),

              const SizedBox(height: 18),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isUploading ? null : _submitForm,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF4C00),
                    padding: const EdgeInsets.all(14),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: Colors.grey,
                    disabledForegroundColor: Colors.white70,
                  ),
                  child: _isUploading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : Text(
                          'Ajukan Pinjaman',
                          style: GoogleFonts.roboto(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: Colors.white, // PUTIH
                          ),
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
