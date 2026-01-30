import 'dart:async';
import 'dart:convert';
import 'package:tabungan/src/file_io.dart';
import 'package:flutter/material.dart';
import 'package:tabungan/model/transfer.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:tabungan/config/api.dart';
import 'package:tabungan/utils/custom_toast.dart';
import 'package:tabungan/event/event_pref.dart';
import 'package:tabungan/utils/currency_format.dart';
import 'package:tabungan/controller/c_user.dart';
import 'package:tabungan/model/user.dart';
import 'package:tabungan/model/tabungan.dart';
import 'package:tabungan/model/tabungan2.dart';
import 'package:tabungan/model/masuk.dart';
import 'package:tabungan/model/penarikan.dart';
import 'package:http/http.dart' as http;
import 'package:tabungan/login.dart';

// import 'package:tabungan/widget/info.dart'; // unused
class EventDB {
  // Safe notification helpers: prefer CustomToast when a BuildContext is available,
  // otherwise fall back to Get.snackbar so calls don't throw when Get.context is null.
  static void _showSuccess(String message, [BuildContext? ctx]) {
    final contextToUse = ctx ?? Get.context;
    if (contextToUse != null) {
      CustomToast.success(contextToUse, message);
    } else {
      try {
        Get.snackbar('Sukses', message, snackPosition: SnackPosition.BOTTOM);
      } catch (_) {
        // last resort: print to console in debug mode
        if (kDebugMode) debugPrint('SUCCESS: $message');
      }
    }
  }

  // Change password (user initiated)
  static Future<bool> changePassword(
    String idPengguna,
    String kataLama,
    String kataBaru,
    String konfirmasi, {
    bool showSuccessToast = true,
  }) async {
    try {
      var response = await http
          .post(
            Uri.parse('${Api.changePassword}'),
            body: {
              'id_pengguna': idPengguna,
              'kata_sandi_lama': kataLama,
              'kata_sandi_baru': kataBaru,
              'konfirmasi': konfirmasi,
            },
          )
          .timeout(const Duration(seconds: 30));

      // CRITICAL: Print RAW response body BEFORE jsonDecode
      if (kDebugMode) {
        debugPrint('📋 RAW RESPONSE (changePassword): ${response.body}');
      }

      var responseBody = jsonDecode(response.body);
      final ok =
          responseBody['status'] == true || responseBody['success'] == true;
      final message =
          responseBody['message']?.toString() ?? 'Tidak ada pesan dari server';

      if (ok) {
        if (showSuccessToast) _showSuccess(message);
        return true;
      } else {
        _showError(message);
        return false;
      }
    } catch (e) {
      if (e.toString().contains('timeout')) {
        _showError('⏱️ Request timeout - Server tidak merespons');
      } else {
        _showError('Error: ${e.toString()}');
      }
      if (kDebugMode) debugPrint(e.toString());
    }
    return false;
  }

  // Change PIN (user initiated)
  static Future<bool> changePin(
    String idPengguna,
    String pinLama,
    String pinBaru,
    String konfirmasi, {
    bool showSuccessToast = true,
    BuildContext? ctx,
  }) async {
    try {
      var response = await http
          .post(
            Uri.parse('${Api.changePin}'),
            body: {
              'id_pengguna': idPengguna,
              'pin_lama': pinLama,
              'pin_baru': pinBaru,
              'konfirmasi': konfirmasi,
            },
          )
          .timeout(const Duration(seconds: 30));

      if (kDebugMode) {
        debugPrint('📋 RAW RESPONSE (changePin): ${response.body}');
      }

      if (response.statusCode == 200) {
        final body = jsonDecode(response.body);
        final ok = body['success'] == true;
        final message =
            body['message']?.toString() ?? 'Tidak ada pesan dari server';
        if (ok) {
          if (showSuccessToast) _showSuccess(message, ctx);
          return true;
        } else {
          _showError(message, ctx);
          return false;
        }
      } else {
        // Try to parse JSON body even on non-200 so we can show server-provided message (e.g. 401 PIN salah)
        try {
          final body = jsonDecode(response.body);
          final message =
              body['message']?.toString() ??
              'Server Error: ${response.statusCode}';
          _showError(message, ctx);
        } catch (_) {
          _showError('Server Error: ${response.statusCode}', ctx);
        }
      }
    } catch (e) {
      if (e.toString().contains('timeout')) {
        _showError('⏱️ Request timeout - Server tidak merespons', ctx);
      } else {
        _showError('❌ Error: ${e.toString()}', ctx);
      }
      if (kDebugMode) debugPrint('Error: $e');
    }
    return false;
  }

  // Delete user (pengguna)
  static Future<bool> deleteUser(String idPengguna, {bool soft = false}) async {
    try {
      final body = <String, String>{'id_pengguna': idPengguna};
      if (soft) body['soft'] = '1';

      var response = await http
          .post(Uri.parse('${Api.deleteUser}'), body: body)
          .timeout(const Duration(seconds: 30));

      var responseBody = jsonDecode(response.body);
      final ok =
          responseBody['success'] == true || responseBody['status'] == true;
      final message =
          responseBody['message']?.toString() ?? 'Tidak ada pesan dari server';

      if (ok) {
        _showSuccess(message);
        return true;
      } else {
        _showError(message);
        return false;
      }
    } catch (e) {
      if (e.toString().contains('timeout')) {
        _showError('⏱️ Request timeout - Server tidak merespons');
      } else {
        _showError('Error: ${e.toString()}');
      }
      if (kDebugMode) debugPrint(e.toString());
    }
    return false;
  }

  static void _showError(String message, [BuildContext? ctx]) {
    final contextToUse = ctx ?? Get.context;
    if (contextToUse != null) {
      CustomToast.error(contextToUse, message);
    } else {
      try {
        Get.snackbar('Error', message, snackPosition: SnackPosition.BOTTOM);
      } catch (_) {
        if (kDebugMode) debugPrint('ERROR: $message');
      }
    }
  }

  static void _showInfo(String message, [BuildContext? ctx]) {
    final contextToUse = ctx ?? Get.context;
    if (contextToUse != null) {
      CustomToast.info(contextToUse, message);
    } else {
      try {
        Get.snackbar('Info', message, snackPosition: SnackPosition.BOTTOM);
      } catch (_) {
        if (kDebugMode) debugPrint('INFO: $message');
      }
    }
  }

  // Show a warning-style toast (orange) to indicate action required / attention
  static void _showWarning(String message, [BuildContext? ctx]) {
    final contextToUse = ctx ?? Get.context;
    if (contextToUse != null) {
      CustomToast.warning(contextToUse, message);
    } else {
      try {
        // use a neutral title so snackbar doesn't look like an error
        Get.snackbar('Perhatian', message, snackPosition: SnackPosition.BOTTOM);
      } catch (_) {
        if (kDebugMode) debugPrint('WARNING: $message');
      }
    }
  }

  // Helper: safely parse JSON response and avoid FormatException
  static dynamic _parseJsonSafeFromResponse(
    http.Response response, {
    String context = '',
    bool showToast = true,
  }) {
    try {
      if (response.body.isEmpty) return null;
      return jsonDecode(response.body);
    } catch (e) {
      if (kDebugMode)
        debugPrint('Invalid JSON response in $context: ${response.body}');
      if (showToast) _showError('Invalid response from server');
      return null;
    }
  }

  // Login
  // Login returns a Map with keys: 'user' (User), 'needs_set_pin' (bool), 'next_page' (string)
  static Future<Map<String, dynamic>?> login(
    String nohp,
    String pass, {
    bool showSuccessToast = true,
  }) async {
    Map<String, dynamic>? result;
    try {
      if (kDebugMode) {
        debugPrint('🔍 Login URL: ${Api.login}');
        debugPrint('📤 Request: nohp=$nohp, pass=$pass');
      }

      var response = await http
          .post(Uri.parse('${Api.login}'), body: {'nohp': nohp, 'pass': pass})
          .timeout(const Duration(seconds: 30));

      if (kDebugMode) {
        debugPrint('📥 Status Code: ${response.statusCode}');
        debugPrint('📥 Response Body: ${response.body}');
      }

      if (response.statusCode == 200) {
        // CRITICAL: Print RAW response BEFORE jsonDecode
        if (kDebugMode) {
          debugPrint('📋 RAW RESPONSE (login): ${response.body}');
        }

        final responseBody = jsonDecode(response.body);
        final success = responseBody['success'] == true;
        final message = responseBody['message']?.toString();

        if (success && responseBody['user'] != null) {
          final Map<String, dynamic> userJson = Map<String, dynamic>.from(
            responseBody['user'],
          );

          // Determine PIN status using the user's `pin` column when present.
          // If `user.pin` is present and non-empty -> PIN already set (needs_set_pin = false).
          // If `user.pin` is absent or empty -> PIN not set (needs_set_pin = true).
          bool needsSetPin;
          if (userJson.containsKey('pin')) {
            final pinVal = userJson['pin']?.toString() ?? '';
            needsSetPin = pinVal.isEmpty;
          } else {
            // Fall back to server-provided flag if `pin` is not included in response
            needsSetPin = responseBody['needs_set_pin'] == true;
          }

          final user = User.fromJson(userJson);
          await EventPref.saveUser(user);

          // NOTE: Success toasts are suppressed by callers when they need a single, contextual notification
          if (showSuccessToast) {
            _showSuccess(message ?? 'Login Berhasil');
          }

          result = {
            'user': user,
            'needs_set_pin': needsSetPin,
            'next_page': responseBody['next_page'] ?? 'dashboard',
          };
        } else {
          _showError(
            message ?? 'Login gagal. Pastikan nomor HP dan password benar.',
          );
        }
      } else {
        // Prefer server-provided JSON message (when available) and fall back to a generic error
        final parsed = _parseJsonSafeFromResponse(
          response,
          context: 'login',
          showToast: false,
        );
        String message;
        String? notifType;
        if (parsed != null && parsed is Map && parsed['message'] != null) {
          message = parsed['message'].toString();
          notifType = parsed['notif_type']?.toString(); // Get notification type from server
        } else {
          message =
              'Terjadi kesalahan pada server (kode ${response.statusCode}). Silakan coba lagi.';
        }
        // Differentiate notification types: use server hint if available, else determine by status code
        if (notifType == 'warning') {
          _showWarning(message);
        } else if (response.statusCode == 404) {
          _showWarning(message);
        } else {
          _showError(message);
        }
      }
    } catch (e) {
      if (e.toString().contains('timeout')) {
        _showError('⏱️ Request timeout - Server tidak merespons');
      } else {
        _showError('❌ Error: ${e.toString()}');
      }
      if (kDebugMode) debugPrint('Error: $e');
    }
    return result;
  }

  //Biodata Siswa Berdasarkan ID
  static Future<List<User>> getID(String id_tabungan) async {
    List<User> listID = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getUsers}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var users = responseBody['user'];
          users.forEach((user) {
            listID.add(User.fromJson(user));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listID;
  }

  // Check whether current user needs to set PIN or missing tabungan (used to guard dashboard)
  static Future<Map<String, dynamic>?> checkPin({
    String? id,
    String? nohp,
  }) async {
    try {
      final body = <String, String>{};
      if (id != null) body['id'] = id;
      if (nohp != null) body['no_hp'] = nohp;
      var response = await http
          .post(Uri.parse(Api.checkPin), body: body)
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final resp = jsonDecode(response.body);
        if (resp['success'] == true) {
          return {
            'needs_set_pin': resp['needs_set_pin'] == true,
            'next_page': resp['next_page'] ?? 'dashboard',
          };
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('checkPin error: $e');
    }
    return null;
  }

  //Tambah Siswa
  static Future<String> addSiswa(
    String id_tabungan,
    String nama,
    String no_wa,
    String role,
    String username,
    String password2,
  ) async {
    String reason;
    try {
      var response = await http.post(
        Uri.parse('${Api.addUser}'),
        body: {
          'id_tabungan': id_tabungan,
          'nama': nama,
          'no_wa': no_wa,
          'role': role,
          'username': username,
          'password2': password2,
        },
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          reason = 'Berhasil Tambah $role';
        } else {
          reason = responseBody['reason'];
        }
      } else {
        reason = 'Request Gagal';
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
      reason = e.toString();
    }
    return reason;
  }

  //Update Akun Berdasarkan ID
  static Future<void> updateUser(
    String id_tabungan,
    String username,
    String password2,
    String role,
  ) async {
    try {
      var response = await http.post(
        Uri.parse('${Api.updateUser}'),
        body: {
          'id_tabungan': id_tabungan,
          'username': username,
          'password2': password2,
          'role': role,
        },
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          _showSuccess('Berhasil Update Akun');
          EventPref.clear();
          Get.offAll(LoginPage());
        } else {
          _showError('Gagal Update Akun');
        }
      } else {
        _showError('Request Gagal');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
  }

  //Update Biodata Berdasarkan ID
  static Future<void> updateBiodata(
    String id_tabungan,
    String role,
    String nama,
    String jk,
    String tanggal_lahir,
    String tempat_lahir,
    String alamat,
    String no_wa,
    String kelas,
    String tanda_pengenal,
    String no_pengenal,
    String email,
    String nama_ibu,
    String nama_ayah,
    String no_ortu,
  ) async {
    try {
      var response = await http.post(
        Uri.parse('${Api.updateBiodata}'),
        body: {
          'id_tabungan': id_tabungan,
          'role': role,
          'nama': nama,
          'jk': jk,
          'tanggal_lahir': tanggal_lahir,
          'tempat_lahir': tempat_lahir,
          'alamat': alamat,
          'no_wa': no_wa,
          'kelas': kelas,
          'tanda_pengenal': tanda_pengenal,
          'no_pengenal': no_pengenal,
          'email': email,
          'nama_ibu': nama_ibu,
          'nama_ayah': nama_ayah,
          'no_ortu': no_ortu,
        },
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          _showSuccess('Berhasil Update Biodata');

          // Update stored user in SharedPreferences so app stays logged in
          try {
            final current = await EventPref.getUser();
            if (current != null) {
              final updated = User(
                id: current.id,
                no_hp: no_wa.isNotEmpty ? no_wa : current.no_hp,
                nama: nama.isNotEmpty ? nama : current.nama,
                nama_lengkap: nama.isNotEmpty ? nama : current.nama_lengkap,
                alamat: alamat.isNotEmpty ? alamat : current.alamat,
                tanggal_lahir: tanggal_lahir.isNotEmpty
                    ? tanggal_lahir
                    : current.tanggal_lahir,
                status_akun: current.status_akun,
                created_at: current.created_at,
                saldo: current.saldo,
              );
              await EventPref.saveUser(updated);
              try {
                // Update in-memory controller so UI updates immediately
                final c = Get.find<CUser>();
                c.setUser(updated);
              } catch (_) {}
            }
          } catch (e) {
            if (kDebugMode)
              debugPrint(
                'Failed to update local pref after biodata update: $e',
              );
          }
        } else {
          _showError('Gagal Update Biodata');
        }
      } else {
        _showError('Request Gagal');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
  }

  // Daftar Siswa
  static Future<List<User>> getUser(String role) async {
    List<User> listUser = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getUsers}'),
        body: {'role': role},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var users = responseBody['user'];
          users.forEach((user) {
            listUser.add(User.fromJson(user));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listUser;
  }

  //Daftar Tabungan Berdasarkan ID
  static Future<List<Tabungan>> getTabungan(String id_tabungan) async {
    List<Tabungan> listTabungan = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getTabungan}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabunganku = responseBody['tabungan'];
          tabunganku.forEach((tabungan) {
            listTabungan.add(Tabungan.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listTabungan;
  }

  //Daftar Tabungan Berdasarkan ID
  static Future<List<Tabungan2>> getTabungan2(String id_tabungan) async {
    List<Tabungan2> listTabungan2 = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getTabungan2}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabunganku2 = responseBody['tabungan2'];
          tabunganku2.forEach((tabungan2) {
            listTabungan2.add(Tabungan2.fromJson(tabungan2));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listTabungan2;
  }

  //Daftar Tabungan Harian Berdasarkan ID
  static Future<List<Tabungan>> getTabungantoday(String id_tabungan) async {
    List<Tabungan> listTabungantoday = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getTabunganToday}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listTabungantoday.add(Tabungan.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listTabungantoday;
  }

  //Daftar Tabungan Limit 5 Berdasarkan ID
  static Future<List<Tabungan>> getTabungan5(String id_tabungan) async {
    List<Tabungan> listTabungan5 = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getTabungan5}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listTabungan5.add(Tabungan.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listTabungan5;
  }

  //Daftar Tabungan Jumlah Harian Berdasarkan ID
  static Future<List<Tabungan2>> getjumTabungantoday(String id_tabungan) async {
    List<Tabungan2> listjumTabungan = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getJumlahTabunganToday}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listjumTabungan.add(Tabungan2.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listjumTabungan;
  }

  //Daftar Tabungan Jumlah Minggu Berdasarkan ID
  static Future<List<Tabungan2>> getjumTabunganminggu(
    String id_tabungan,
  ) async {
    List<Tabungan2> listjumTabunganweek = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getJumlahTabunganMinggu}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listjumTabunganweek.add(Tabungan2.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listjumTabunganweek;
  }

  //Daftar Tabungan Jumlah Bulan Berdasarkan ID
  static Future<List<Tabungan2>> getjumTabunganbulan(String id_tabungan) async {
    List<Tabungan2> listjumTabunganmon = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getJumlahTabunganBulan}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listjumTabunganmon.add(Tabungan2.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listjumTabunganmon;
  }

  //Daftar Tabungan Jumlah Bulan Berdasarkan ID
  static Future<List<Tabungan2>> getjumTabunganall(String id_tabungan) async {
    List<Tabungan2> listjumTabunganall = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getJumlahTabunganAll}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var tabungan = responseBody['tabungan'];
          tabungan.forEach((tabungan) {
            listjumTabunganall.add(Tabungan2.fromJson(tabungan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listjumTabunganall;
  }

  // New: Get balances grouped by jenis (returns list of maps {jenis, balance, total_masuk, total_keluar})
  static Future<List<Map<String, dynamic>>> getSummaryByJenis(
    String id_tabungan,
  ) async {
    // Backwards-compatible: returns just the list of jenis items
    List<Map<String, dynamic>> out = [];
    try {
      var response = await http
          .post(
            Uri.parse('${Api.getSummaryByJenis}'),
            body: {'id_tabungan': id_tabungan},
          )
          .timeout(const Duration(seconds: 10));
      if (kDebugMode)
        debugPrint(
          'getSummaryByJenis HTTP ${response.statusCode}: ${response.body}',
        );
      // Raw response debug
      print('DEBUG RAW SUMMARY RESPONSE: ${response.body}');
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getSummaryByJenis',
        showToast: false,
      );
      if (body != null && body['success'] == true && body['data'] != null) {
        for (var item in body['data']) {
          final m = Map<String, dynamic>.from(item);
          // normalize id into id_jenis_tabungan for client convenience
          m['id_jenis_tabungan'] = item['id_jenis_tabungan'] ?? item['id'];
          out.add(m);
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('getSummaryByJenis error: $e');
    }
    return out;
  }

  // New: returns summary plus metadata (total_tabungan and jenis_list)
  static Future<Map<String, dynamic>?> getSummaryWithMeta(
    String id_tabungan,
  ) async {
    try {
      var response = await http
          .post(
            Uri.parse('${Api.getSummaryByJenis}'),
            body: {'id_tabungan': id_tabungan},
          )
          .timeout(const Duration(seconds: 10));
      if (kDebugMode)
        debugPrint(
          'getSummaryWithMeta HTTP ${response.statusCode}: ${response.body}',
        );
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getSummaryWithMeta',
        showToast: false,
      );
      if (body != null && body['success'] == true) {
        // Prefer meta.total when provided by server, otherwise keep total_tabungan
        final meta = body['meta'] as Map<String, dynamic>?;
        final totalFromMeta = meta != null && meta['total'] != null
            ? int.tryParse(meta['total'].toString())
            : null;
        final totalFallback = body['total_tabungan'] ?? 0;
        return {
          'data': (body['data'] as List? ?? [])
              .map((e) => Map<String, dynamic>.from(e))
              .toList(),
          'total_tabungan':
              totalFromMeta ?? int.tryParse(totalFallback.toString()) ?? 0,
          'jenis_list': (body['jenis_list'] as List? ?? [])
              .map((e) => Map<String, dynamic>.from(e))
              .toList(),
          'meta':
              meta ??
              {
                'total':
                    totalFromMeta ??
                    int.tryParse(totalFallback.toString()) ??
                    0,
              },
        };
      }
    } catch (e) {
      if (kDebugMode) debugPrint('getSummaryWithMeta error: $e');
    }
    return null;
  }

  // New: Get transaction history filtered by jenis (jenis can be id or name). Returns list of maps {date, title, amount, type}
  static Future<List<Map<String, dynamic>>> getHistoryByJenis(
    String id_tabungan,
    String jenis, {
    String periode = '30',
    int limit = 200,
  }) async {
    List<Map<String, dynamic>> out = [];
    try {
      var bodyParams = {
        'id_tabungan': id_tabungan,
        'jenis': jenis,
        'periode': periode,
        'limit': limit.toString(),
      };
      var response = await http
          .post(Uri.parse('${Api.getHistoryByJenis}'), body: bodyParams)
          .timeout(const Duration(seconds: 10));
      if (kDebugMode)
        debugPrint(
          'getHistoryByJenis HTTP ${response.statusCode}: ${response.body}',
        );
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getHistoryByJenis',
        showToast: false,
      );
      if (body != null && body['success'] == true && body['data'] != null) {
        for (var item in body['data']) {
          out.add(Map<String, dynamic>.from(item));
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('getHistoryByJenis error: $e');
    }
    return out;
  }

  static Future<String?> uploadProfilePhoto(
    String id_tabungan,
    File imageFile,
  ) async {
    try {
      final uri = Uri.parse('${Api.updateFoto}');
      final request = http.MultipartRequest('POST', uri);
      request.fields['username'] = id_tabungan;
      // generate filename preserving extension
      final parts = imageFile.path.split('.');
      final ext = parts.length > 1 ? parts.last : 'jpg';
      final filename =
          'user_${id_tabungan}_${DateTime.now().millisecondsSinceEpoch}.$ext';
      request.files.add(
        await http.MultipartFile.fromPath(
          'image',
          imageFile.path,
          filename: filename,
        ),
      );

      final streamed = await request.send();
      final response = await http.Response.fromStream(streamed);

      if (kDebugMode) {
        debugPrint('📤 Upload URL: $uri');
        debugPrint(
          '📥 Upload response: ${response.statusCode} ${response.body}',
        );
      }

      if (response.statusCode == 200) {
        try {
          final body = jsonDecode(response.body);
          if (body != null &&
              (body['value'] == 'OK' || body['success'] == true)) {
            if (body.containsKey('url')) return body['url'].toString();
            return '';
          }
        } catch (e) {
          if (kDebugMode) debugPrint('Upload: failed parse response: $e');
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('Upload error: $e');
    }
    return null;
  }

  // Get notifications for a user from server
  static Future<List<Map<String, dynamic>>> getNotifications(
    String idPengguna,
  ) async {
    try {
      final url = '${Api.getNotifications}?id_pengguna=$idPengguna';
      if (kDebugMode) debugPrint('📬 getNotifications REQUEST URL: $url');
      
      var response = await http
          .get(
            Uri.parse(url),
          )
          .timeout(const Duration(seconds: 20));
      
      if (kDebugMode) debugPrint('📬 getNotifications RESPONSE CODE: ${response.statusCode}');
      if (kDebugMode) debugPrint('📬 getNotifications RESPONSE BODY: ${response.body}');
      
      if (response.statusCode == 200) {
        final body = _parseJsonSafeFromResponse(
          response,
          context: 'getNotifications',
          showToast: false,
        );
        if (body != null && (body['success'] == true) && body['data'] is List) {
          final List list = body['data'];
          if (kDebugMode) debugPrint('📬 getNotifications SUCCESS: ${list.length} items');
          return list.map((e) => Map<String, dynamic>.from(e)).toList();
        } else {
          if (kDebugMode) debugPrint('📬 getNotifications: success=false or data is not List');
        }
      } else {
        if (kDebugMode) debugPrint('📬 getNotifications: HTTP ${response.statusCode}');
      }
    } catch (e) {
      if (kDebugMode) debugPrint('📬 getNotifications ERROR: $e');
    }
    return [];
  }

  //Daftar Masuk Berdasarkan ID
  static Future<List<Masuk>> getSetoran(String id_tabungan) async {
    List<Masuk> listMasuk = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getSetoran}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var masukku = responseBody['setoran'];
          masukku.forEach((masuk) {
            listMasuk.add(Masuk.fromJson(masuk));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listMasuk;
  }

  //Daftar Penarikan Berdasarkan ID
  static Future<List<Penarikan>> getPenarikan(String id_tabungan) async {
    List<Penarikan> listPenarikan = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getPenarikan}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var penarikanku = responseBody['penarikan'];
          penarikanku.forEach((penarikan) {
            listPenarikan.add(Penarikan.fromJson(penarikan));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listPenarikan;
  }

  //Daftar Transfer Berdasarkan ID
  static Future<List<Transfer>> getTransfer(String id_tabungan) async {
    List<Transfer> listTransfer = [];
    try {
      var response = await http.post(
        Uri.parse('${Api.getTransfer}'),
        body: {'id_tabungan': id_tabungan},
      );
      if (response.statusCode == 200) {
        var responseBody = jsonDecode(response.body);
        if (responseBody['success']) {
          var transferku = responseBody['transfer'];
          transferku.forEach((transfer) {
            listTransfer.add(Transfer.fromJson(transfer));
          });
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint(e.toString());
      }
    }
    return listTransfer;
  }

  // Get frequently transferred recipients for the given user
  static Future<List<Map<String, dynamic>>> getFrequentRecipients(
    String idPengirim, {
    int limit = 10,
  }) async {
    try {
      var response = await http
          .post(
            Uri.parse('${Api.getFrequentRecipients}'),
            body: {'id_pengirim': idPengirim, 'limit': limit.toString()},
          )
          .timeout(const Duration(seconds: 30));

      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getFrequentRecipients',
        showToast: false,
      );
      if (body != null &&
          body['success'] == true &&
          body['recipients'] != null) {
        return List<Map<String, dynamic>>.from(body['recipients']);
      }
    } catch (e) {
      if (kDebugMode) debugPrint('getFrequentRecipients error: $e');
    }
    return [];
  }

  // Find which phone numbers correspond to registered users
  static Future<List<Map<String, dynamic>>> findContacts(
    List<String> phones,
  ) async {
    try {
      var payload = jsonEncode({'phones': phones});
      var response = await http
          .post(
            Uri.parse('${Api.findContacts}'),
            headers: {'Content-Type': 'application/json'},
            body: payload,
          )
          .timeout(const Duration(seconds: 30));

      final body = _parseJsonSafeFromResponse(
        response,
        context: 'findContacts',
        showToast: false,
      );
      if (body != null && body['success'] == true && body['matched'] != null) {
        return List<Map<String, dynamic>>.from(body['matched']);
      }
    } catch (e) {
      if (kDebugMode) debugPrint('findContacts error: $e');
    }
    return [];
  }

  //Tambah Transfer
  // static Future<String> addTransfer(String id_tabungan, String id_target,
  //     String password, String ket, String nominal) async {
  //   String reason;
  //   try {
  //     var response = await http.post(Uri.parse(Api.addsTransfer), body: {
  //       'id_tabungan': id_tabungan,
  //       'id_target': id_target,
  //       'password': password,
  //       'ket': ket,
  //       'nominal': nominal,
  //     });
  //     if (response.statusCode == 200) {
  //       var responseBody = jsonDecode(response.body);
  //       if (responseBody['success']) {
  //         reason = 'Transfer Berhasil';
  //         Get.to(const DaftarTransfer());
  //       } else {
  //         reason = responseBody['reason'];
  //       }
  //     } else {
  //       reason = 'Transfer Gagal';
  //     }
  //   } catch (e) {
  //     if (kDebugMode) {
  //       print(e);
  //     }
  //     reason = e.toString();
  //   }
  //   return reason;
  // }

  static Future<String> addTransfer(
    String id_tabungan,
    String id_target,
    String pin,
    String ket,
    String nominal,
  ) async {
    String reason = 'Transfer Gagal';

    try {
      var response = await http.post(
        Uri.parse('${Api.addTransfer}'),
        body: {
          'id_tabungan': id_tabungan,
          'id_target': id_target,
          'pin': pin,
          'ket': ket,
          'nominal': nominal,
        },
      );

      if (kDebugMode) {
        debugPrint('Status Code: ${response.statusCode}');
        debugPrint('Response Body: ${response.body}');
      }

      if (response.statusCode == 200) {
        try {
          var responseBody = jsonDecode(response.body);

          if (responseBody['success'] == true && responseBody['data'] != null) {
            // Server reports success
            reason = 'Transfer Berhasil';

            // Update local cached user saldo if server returns the new balance
            try {
              final data = responseBody['data'];
              if (data is Map && data.containsKey('saldo_baru')) {
                final sb =
                    double.tryParse(data['saldo_baru'].toString()) ?? 0.0;
                final localUser = await EventPref.getUser();
                if (localUser != null) {
                  localUser.saldo = sb;
                  await EventPref.saveUser(localUser);
                  try {
                    Get.find<CUser>().setUser(localUser);
                  } catch (_) {}
                }
              }
            } catch (_) {}
          } else if (responseBody.containsKey('message')) {
            // Backend uses 'message' for errors
            reason = responseBody['message'];
          } else if (responseBody.containsKey('reason')) {
            reason = responseBody['reason'];
          } else if (responseBody.containsKey('error')) {
            reason = responseBody['error'];
          }
        } catch (e) {
          reason = 'Gagal membaca respon dari server.';
          if (kDebugMode) {
            debugPrint('JSON parsing error: $e');
          }
        }
      } else {
        reason = 'Koneksi ke server gagal (${response.statusCode})';
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Exception: $e');
      }
      reason = 'Terjadi kesalahan: ${e.toString()}';
    }

    return reason;
  }

  /// =========================================================================
  /// FUNGSI: Upload Foto Profil ke Endpoint update_foto_profil.php
  /// =========================================================================
  ///
  /// Tujuan: Upload gambar profil pengguna ke server dengan validasi ketat
  ///
  /// Parameter:
  ///   - idPengguna: ID pengguna di database (required)
  ///   - imageFile: File gambar dari image_picker (required)
  ///
  /// Return:
  ///   - String (URL foto profil): Jika berhasil upload dan update database
  ///   - null: Jika gagal upload atau validasi
  ///
  /// Response Server Sukses (200):
  /// {
  ///   "status": true,
  ///   "message": "Foto profil berhasil diperbarui",
  ///   "foto_profil": "https://domain.com/uploads/foto_profil/123_1702000000.jpg"
  /// }
  ///
  /// Response Server Error (400/422/500):
  /// {
  ///   "status": false,
  ///   "message": "Deskripsi error"
  /// }
  ///
  /// =========================================================================
  static Future<String?> uploadFotoProfil(
    String idPengguna,
    File imageFile,
  ) async {
    try {
      // ===== STEP 1: VALIDASI INPUT =====
      // Cek ID pengguna tidak kosong
      if (idPengguna.isEmpty) {
        _showError('ID pengguna tidak valid');
        return null;
      }

      // Cek file gambar ada dan readable
      if (!await imageFile.exists()) {
        _showError('File gambar tidak ditemukan');
        return null;
      }

      // ===== STEP 2: BUAT MULTIPART REQUEST =====
      // Target endpoint
      final uploadUrl = '${Api.baseUrl}/update_foto_profil.php';

      // Buat instance MultipartRequest dengan method POST
      final request = http.MultipartRequest('POST', Uri.parse(uploadUrl));

      // Tambahkan field ID pengguna
      request.fields['id_pengguna'] = idPengguna;

      // Note: server will generate secure filename and return a signed proxy URL.
      final fileExt = imageFile.path.split('.').last.toLowerCase();
      final timestamp = DateTime.now().millisecondsSinceEpoch ~/ 1000;
      final tempFilename = '${idPengguna}_$timestamp.$fileExt';
      // Attach file using the original filesystem path; backend will generate filename
      final multipartFile = await http.MultipartFile.fromPath(
        'foto_profil',
        imageFile.path,
      );
      request.files.add(multipartFile);

      // ===== STEP 3: KIRIM REQUEST KE SERVER =====
      if (kDebugMode) {
        debugPrint('📤 Upload Foto Profil');
        debugPrint('   URL: $uploadUrl');
        debugPrint('   ID: $idPengguna');
        debugPrint('   File: ${imageFile.path}');
      }

      // Gunakan timeout 30 detik untuk request
      final streamedResponse = await request.send().timeout(
        const Duration(seconds: 30),
        onTimeout: () {
          throw TimeoutException('Upload timeout setelah 30 detik');
        },
      );

      // Convert streamed response ke regular response
      final response = await http.Response.fromStream(streamedResponse);

      if (kDebugMode) {
        debugPrint('📥 Response Status: ${response.statusCode}');
        debugPrint('📥 Response Body: ${response.body}');
      }

      // ===== STEP 4: PARSE RESPONSE JSON =====
      // Validasi status code adalah 200 (sukses)
      if (response.statusCode != 200) {
        try {
          final errorBody = jsonDecode(response.body);
          final errorMsg =
              errorBody['message'] ?? 'Upload gagal (${response.statusCode})';
          _showError(errorMsg);
        } catch (_) {
          _showError('Upload gagal dengan status ${response.statusCode}');
        }
        return null;
      }

      /// Inspect user by phone or id. (Moved out of upload method.)

      // Parse response JSON
      Map<String, dynamic> responseBody;
      try {
        responseBody = jsonDecode(response.body);
      } on FormatException catch (_) {
        if (kDebugMode)
          debugPrint('Invalid JSON from upload: ${response.body}');
        _showError('Respon server tidak valid');
        return null;
      }

      // Cek apakah response memiliki status true
      if (responseBody['status'] != true) {
        final errorMsg = responseBody['message'] ?? 'Upload gagal';
        _showError(errorMsg);
        return null;
      }

      // ===== STEP 5: AMBIL URL FOTO DARI RESPONSE =====
      // Server may return a short-lived signed proxy URL in 'foto_profil_url'.
      // Historically some responses used 'foto_profil' for the URL, but the
      // API now returns the filename in 'foto_profil' and the full URL in
      // 'foto_profil_url'. Prefer the signed URL when available.
      // Prefer signed proxy URL from server response. Also read updated_at (timestamp) when present.
      final tempFotoUrl =
          responseBody['foto_profil_url'] ?? responseBody['foto_profil'];
      final int? tempFotoUpdatedAt =
          responseBody['foto_profil_updated_at'] != null
          ? int.tryParse(responseBody['foto_profil_updated_at'].toString())
          : null;
      if (kDebugMode)
        debugPrint(
          '⚠️ tempFotoUrl chosen: $tempFotoUrl  (updated_at: $tempFotoUpdatedAt)',
        );

      // Try to refresh profile to get canonical signed URL and updated_at
      String? fotoProfilUrl = tempFotoUrl;
      int? fotoUpdatedAt = tempFotoUpdatedAt;
      try {
        final fresh = await getProfilLengkap(idPengguna);
        if (fresh != null) {
          if (fresh.foto != null && fresh.foto!.isNotEmpty)
            fotoProfilUrl = fresh.foto;
          if (fresh.fotoUpdatedAt != null) fotoUpdatedAt = fresh.fotoUpdatedAt;
        }
      } catch (e) {
        if (kDebugMode)
          debugPrint('⚠️ getProfilLengkap failed after upload: $e');
      }

      // Validate that we have a usable URL (must include host/scheme). If not, avoid passing an invalid value to Image.network
      Uri? parsed;
      try {
        parsed = fotoProfilUrl != null ? Uri.parse(fotoProfilUrl) : null;
      } catch (_) {
        parsed = null;
      }
      if (parsed == null || parsed.scheme == '' || parsed.host == '') {
        if (kDebugMode)
          debugPrint('❌ Invalid foto URL received: $fotoProfilUrl');
        fotoProfilUrl = null;
      }

      if (fotoProfilUrl == null || fotoProfilUrl.toString().isEmpty) {
        _showError('URL foto tidak diterima dari server');
        return null;
      }

      // ===== STEP 6: SIMPAN KE LOCAL STORAGE (SharedPreferences) =====
      // Ambil user data yang sedang login dari SharedPreferences
      final currentUser = await EventPref.getUser();

      if (currentUser != null) {
        // Create a new User object (avoid mutating fields) to ensure GetX reactivity works reliably
        final updatedUser = User(
          id: currentUser.id,
          no_hp: currentUser.no_hp,
          nama: currentUser.nama,
          nama_lengkap: currentUser.nama_lengkap,
          alamat: currentUser.alamat,
          tanggal_lahir: currentUser.tanggal_lahir,
          status_akun: currentUser.status_akun,
          created_at: currentUser.created_at,
          saldo: currentUser.saldo,
          foto: fotoProfilUrl,
          fotoUpdatedAt: fotoUpdatedAt,
        );

        // Simpan user data yang sudah diupdate kembali ke SharedPreferences
        await EventPref.saveUser(updatedUser);

        if (kDebugMode) {
          debugPrint('✅ User data updated di SharedPreferences (refreshed)');
        }

        // ===== STEP 7: UPDATE IN-MEMORY STATE (GetX Controller) =====
        try {
          final c = Get.find<CUser>();
          c.setUser(updatedUser);
        } catch (_) {
          if (kDebugMode) debugPrint('⚠️ CUser controller tidak ditemukan');
        }
      }
      // ===== STEP 8: TAMPILKAN PESAN SUKSES =====
      _showSuccess('Foto profil berhasil diperbarui');

      if (kDebugMode) {
        debugPrint('✅ Upload Foto Profil Sukses');
        debugPrint('   URL: $fotoProfilUrl');
      }

      // Return URL foto untuk digunakan di UI
      return fotoProfilUrl;
    } on TimeoutException catch (e) {
      // Handle timeout error
      if (kDebugMode) debugPrint('⏱️ Timeout: $e');
      _showError('Koneksi timeout. Periksa internet Anda');
      return null;
    } catch (e) {
      // Handle general error
      if (kDebugMode) debugPrint('❌ Upload Error: $e');
      _showError('Gagal upload foto: ${e.toString()}');
      return null;
    }
  }

  /// =========================================================================
  /// FUNGSI: Get Profil Lengkap Pengguna
  /// =========================================================================
  ///

  static Future<User?> getProfilLengkap(String idPengguna) async {
    try {
      final response = await http
          .get(Uri.parse(Api.getProfilLengkap + '?id_pengguna=$idPengguna'))
          .timeout(const Duration(seconds: 15));

      if (response.statusCode == 200) {
        final json = jsonDecode(response.body);
        if (json['success'] == true && json['data'] != null) {
          return User.fromJson(Map<String, dynamic>.from(json['data']));
        }
      }
      return null;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('getProfilLengkap error: $e');
      }
      return null;
    }
  }

  /// Inspect user by phone or id. Returns a Map with user details or null.
  static Future<Map<String, dynamic>?> inspectUser(String query) async {
    if (query.isEmpty) return null;
    try {
      final response = await http
          .post(Uri.parse(Api.inspectUser), body: {'phone': query})
          .timeout(const Duration(seconds: 10));
      if (response.statusCode == 200) {
        final body = jsonDecode(response.body);
        if (body['success'] == true && body['user'] != null) {
          return Map<String, dynamic>.from(body['user']);
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('inspectUser error: $e');
    }
    return null;
  }

  /// Get only the current saldo for a user (useful as fallback)
  static Future<double?> getSaldo(String idPengguna) async {
    try {
      if (idPengguna.isEmpty) return null;
      final url = '${Api.baseUrl}/get_saldo.php';
      final response = await http
          .post(Uri.parse(url), body: {'id_pengguna': idPengguna})
          .timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return null;
      final resp = _parseJsonSafeFromResponse(
        response,
        context: 'getSaldo',
        showToast: false,
      );
      if (resp == null || resp['success'] != true) return null;
      // Prefer top-level 'saldo' (new shape), fall back to data.* keys for compatibility
      double? found;
      if (resp.containsKey('saldo')) {
        final sTop = resp['saldo'];
        found = double.tryParse(sTop.toString());
      } else if (resp['data'] != null) {
        final data = resp['data'];
        final saldo =
            data['saldo'] ?? data['saldo_calculated'] ?? data['saldo_db'];
        if (saldo != null) found = double.tryParse(saldo.toString());
      }
      if (found == null) return null;
      return found;
    } catch (e) {
      if (kDebugMode) debugPrint('getSaldo error: $e');
      return null;
    }
  }

  /// Refresh only the stored saldo for the current user and update UI + prefs
  static Future<void> refreshSaldoForCurrentUser({String? idPengguna}) async {
    try {
      final local = await EventPref.getUser();
      final idToUse = idPengguna ?? local?.id ?? local?.no_hp ?? '';
      if (idToUse.isEmpty) return;
      final s = await getSaldo(idToUse);
      if (s == null) return;
      final c = Get.find<CUser>();
      // Update in-memory and pref
      final existing = c.user;
      final updated = User(
        id: existing.id,
        no_hp: existing.no_hp,
        nama: existing.nama,
        nama_lengkap: existing.nama_lengkap,
        alamat: existing.alamat,
        tanggal_lahir: existing.tanggal_lahir,
        status_akun: existing.status_akun,
        created_at: existing.created_at,
        saldo: s,
        foto: existing.foto,
      );
      c.setUser(updated);
      await EventPref.saveUser(updated);
    } catch (e) {
      if (kDebugMode) debugPrint('refreshSaldoForCurrentUser error: $e');
    }
  }

  /// Get saldo for a tabungan id (calls get_saldo_tabungan.php?id_tabungan=...)
  /// Returns parsed JSON Map or null on error
  static Future<Map<String, dynamic>?> getSaldoTabungan(
    String idTabungan,
  ) async {
    try {
      if (idTabungan.isEmpty) return null;
      final url =
          '${Api.getSaldoTabungan}?id_tabungan=${Uri.encodeComponent(idTabungan)}';
      final response = await http
          .get(Uri.parse(url))
          .timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return null;
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getSaldoTabungan',
        showToast: false,
      );
      if (body == null) return null;
      if (body is Map<String, dynamic>) return body;
      return Map<String, dynamic>.from(body);
    } catch (e) {
      if (kDebugMode) debugPrint('getSaldoTabungan error: $e');
      return null;
    }
  }

  /// Wrapper: return saldo int from get_saldo_tabungan.php
  static Future<int?> getSaldoUser(String idTabungan) async {
    try {
      final body = await getSaldoTabungan(idTabungan);
      if (body == null) return null;
      if (body['success'] != true) return null;
      if (body.containsKey('saldo'))
        return int.tryParse(body['saldo'].toString()) ?? 0;
      if (body['data'] != null && body['data']['saldo'] != null)
        return int.tryParse(body['data']['saldo'].toString()) ?? 0;
      return 0;
    } catch (e) {
      if (kDebugMode) debugPrint('getSaldoUser error: $e');
      return null;
    }
  }

  /// Get rincian (per-jenis totals) as List of maps with all static jenis
  static Future<List<Map<String, dynamic>>> getRincianTabungan(
    String idTabungan,
  ) async {
    // No-op here; method prints raw response and returns parsed list
    try {
      if (idTabungan.isEmpty) return [];
      final url = '${Api.getRincianTabungan}';
      final response = await http
          .post(Uri.parse(url), body: {'id_tabungan': idTabungan})
          .timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return [];
      // Raw response for debugging
      print("DEBUG RAW TABUNGAN RESPONSE: ${response.body}");

      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getRincianTabungan',
        showToast: false,
      );
      if (body == null || body['success'] != true) return [];
      final List<dynamic> data = List<dynamic>.from(body['data'] ?? []);
      final out = <Map<String, dynamic>>[];
      for (var r in data) {
        out.add({
          // Preserve server-provided jenis id so callers can send it to the backend
          // Prefer explicit 'id_jenis_tabungan' if backend provides it
          'id': r['id_jenis_tabungan'] ?? r['id'],
          'jenis': (r['jenis'] ?? '').toString(),
          'total': int.tryParse((r['total'] ?? 0).toString()) ?? 0,
        });
      }
      return out;
    } catch (e) {
      if (kDebugMode) debugPrint('getRincianTabungan error: $e');
      return [];
    }
  }

  /// Fetch master jenis tabungan (fallback) from tmp_list_jenis.php when server rincian lacks ids
  static Future<List<Map<String, dynamic>>> getJenisMaster() async {
    try {
      // tmp_list_jenis.php sits at project root; Api.baseUrl contains '/flutter_api' so remove that
      final base = Api.baseUrl.replaceFirst('/flutter_api', '');
      final url = '$base/tmp_list_jenis.php';
      final response = await http
          .get(Uri.parse(url))
          .timeout(const Duration(seconds: 10));

      // Raw response for debugging
      print("DEBUG RAW JENIS LIST RESPONSE: ${response.body}");

      if (response.statusCode != 200) return [];
      final List<dynamic> data = jsonDecode(response.body);
      final out = <Map<String, dynamic>>[];
      for (var r in data) {
        // prefer columns 'nama' or 'nama_jenis' for name
        final name = (r['nama'] ?? r['nama_jenis'] ?? r['jenis'] ?? '')
            .toString();
        final id = r['id'] != null ? int.tryParse(r['id'].toString()) : null;
        out.add({'id': id, 'nama': name});
      }
      return out;
    } catch (e) {
      if (kDebugMode) debugPrint('getJenisMaster error: $e');
      return [];
    }
  }

  /// Get total tabungan (sum of jumlah where status='berhasil')
  static Future<int?> getTotalTabungan(String idTabungan) async {
    try {
      if (idTabungan.isEmpty) return null;
      final url = '${Api.getTotalTabungan}';
      final response = await http
          .post(Uri.parse(url), body: {'id_tabungan': idTabungan})
          .timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return null;
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getTotalTabungan',
        showToast: false,
      );
      if (body == null || body['success'] != true) return null;
      return int.tryParse((body['total_tabungan'] ?? 0).toString()) ?? 0;
    } catch (e) {
      if (kDebugMode) debugPrint('getTotalTabungan error: $e');
      return null;
    }
  }

  /// Withdraw (cairkan) from tabungan.
  /// Returns true on success, shows toast and returns false on any failure or exception.
  static Future<bool> cairkanTabungan(
    String idTabungan,
    String jenis,
    int jumlah, {
    String keterangan = '',
    int? idJenis,
  }) async {
    try {
      if (idTabungan.isEmpty) {
        _showError('Parameter id_pengguna kosong');
        return false;
      }

      // Build request body. Prefer sending id_jenis_tabungan when available.
      final url = '${Api.cairkanTabungan}';
      final bodyMap = <String, String>{
        'id_pengguna': idTabungan,
        'nominal': jumlah.toString(),
      };
      if (keterangan.isNotEmpty) bodyMap['keterangan'] = keterangan;

      if (idJenis != null) {
        // If caller supplied an id, verify it against the server-side `rincian` mapping
        // to avoid stale/wrong ids being sent (which previously caused the wrong jenis
        // row to be decremented). If the mapping disagrees, prefer the server-resolved id.
        bodyMap['id_jenis_tabungan'] = idJenis.toString();
        try {
          final rinc = await getRincianTabungan(idTabungan);
          // Normalize both sides: remove 'Tabungan' prefix and trim for robust matching
          String norm(String s) => s
              .toString()
              .toLowerCase()
              .replaceAll(RegExp(r"\btabungan\b", caseSensitive: false), '')
              .trim();
          final found = rinc.firstWhere(
            (r) => norm((r['jenis'] ?? '').toString()) == norm(jenis),
            orElse: () => {},
          );
          if (found != null &&
              (found is Map<String, dynamic>) &&
              found.containsKey('id') &&
              found['id'] != null) {
            final matchedId =
                int.tryParse((found['id'] ?? '').toString()) ?? -1;
            if (matchedId > 0 && matchedId.toString() != idJenis.toString()) {
              if (kDebugMode)
                debugPrint(
                  'cairkanTabungan: provided idJenis=$idJenis does not match server-resolved id=$matchedId for jenis="$jenis"; using $matchedId',
                );
              bodyMap['id_jenis_tabungan'] = matchedId.toString();
            }
          }
        } catch (_) {
          // If verification fails for any reason, proceed with provided id (best-effort)
        }
      } else {
        // Try to resolve jenis name to id by querying rincian (which includes id when available)
        try {
          final rinc = await getRincianTabungan(idTabungan);
          final found = rinc.firstWhere(
            (r) =>
                (r['jenis'] ?? '').toString().toLowerCase() ==
                jenis.toLowerCase(),
            orElse: () => {},
          );
          if (found != null &&
              (found is Map<String, dynamic>) &&
              found.containsKey('id') &&
              found['id'] != null) {
            bodyMap['id_jenis_tabungan'] = (found['id'] ?? '').toString();
          } else {
            // If we cannot map to an id, show error and abort (server requires id_jenis_tabungan)
            _showError(
              'Jenis tabungan tidak valid atau id tidak ditemukan. Silakan refresh dan coba lagi.',
            );
            return false;
          }
        } catch (_) {
          _showError(
            'Gagal memetakan jenis ke id. Silakan refresh dan coba lagi.',
          );
          return false;
        }
      }

      final body = bodyMap; // preserve body keys/values as-is
      print("REQUEST BODY: ${jsonEncode(body)}");

      final response = await http
          .post(
            Uri.parse(url),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(body),
          )
          .timeout(const Duration(seconds: 15));

      print("RESPONSE BODY: ${response.body}");
      if (kDebugMode)
        debugPrint('cairkanTabungan RAW RESPONSE: ${response.body}');

      if (response.statusCode == 200) {
        try {
          final Map<String, dynamic> body = jsonDecode(response.body);
          final ok = body['status'] == true || body['success'] == true;
          final message =
              body['message']?.toString() ?? 'Tidak ada pesan dari server';

          if (ok) {
            _showSuccess(message);

            // If server provides a saldo value, immediately update local stored user and in-memory CUser
            try {
              final s = body['saldo'];
              if (s != null) {
                double newSaldo = 0.0;
                if (s is num) {
                  newSaldo = s.toDouble();
                } else {
                  newSaldo = double.tryParse(s.toString()) ?? 0.0;
                }
                final currentUser = await EventPref.getUser();
                if (currentUser != null) {
                  currentUser.saldo = newSaldo;
                  await EventPref.saveUser(currentUser);
                  try {
                    final c = Get.find<CUser>();
                    c.setUser(currentUser);
                  } catch (_) {
                    if (kDebugMode)
                      debugPrint(
                        '⚠️ CUser controller tidak ditemukan saat update saldo',
                      );
                  }
                }
              }
            } catch (e) {
              if (kDebugMode) debugPrint('Error updating local saldo: $e');
            }

            // Immediately refresh authoritative saldo and rincian BEFORE returning to caller.
            // IMPORTANT: Do not rely on static cached saldo values anywhere in the app — always fetch
            // latest values from the server and update controllers/preferences with set operations.
            try {
              await refreshSaldoForCurrentUser();
              // Warm up rincian so UI can re-render without using stale cache
              await getRincianTabungan(idTabungan);
              // Also call getTabungan (server's get_tabungan_user) so server-side cache/derived data is warmed;
              // do not persist in static variables here — caller/UI should fetch details when needed.
              await getTabungan(idTabungan);
            } catch (e) {
              if (kDebugMode) debugPrint('post-cairkan refresh error: $e');
            }

            return true;
          } else {
            // If server returns available amount, show it to user for clarity
            try {
              final av = body['available'];
              if (av != null) {
                final intAv = int.tryParse(av.toString()) ?? 0;
                _showError(
                  '$message (Tersedia: ${CurrencyFormat.toIdr(intAv)})',
                );
                return false;
              }
            } catch (_) {}

            _showError(message);
            return false;
          }
        } catch (e) {
          if (kDebugMode) debugPrint('cairkanTabungan parse error: $e');
          _showError('Invalid response dari server');
          return false;
        }
      } else {
        // Try to parse message from non-200
        try {
          final parsed = jsonDecode(response.body);
          if (parsed is Map<String, dynamic>) {
            final message =
                parsed['message']?.toString() ??
                'Server Error: ${response.statusCode}';
            _showError(message);
            return false;
          }
        } catch (_) {
          _showError('Server Error: ${response.statusCode}');
          return false;
        }
        return false;
      }
    } catch (e) {
      if (e.toString().contains('timeout')) {
        _showError('⏱️ Request timeout - Server tidak merespons');
      } else {
        _showError('Error: ${e.toString()}');
      }
      if (kDebugMode) debugPrint('cairkanTabungan error: $e');
      return false;
    }
  }

  /// Get riwayat (list of maps) for a jenis and periode (days)
  static Future<List<Map<String, dynamic>>> getRiwayatTabungan(
    String idTabungan,
    String jenis,
    String periode,
  ) async {
    try {
      if (idTabungan.isEmpty || jenis.isEmpty) return [];
      final uri = Uri.parse(
        '${Api.getRiwayatTabungan}?id_tabungan=$idTabungan&jenis_tabungan=$jenis&periode=$periode',
      );
      final response = await http.get(uri).timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return [];
      final body = _parseJsonSafeFromResponse(
        response,
        context: 'getRiwayatTabungan',
        showToast: false,
      );
      if (body == null || body['success'] != true) return [];
      final List<dynamic> data = List<dynamic>.from(body['data'] ?? []);
      final List<Map<String, dynamic>> out = [];
      for (var r in data) {
        out.add({
          'tanggal': r['tanggal'] ?? '',
          'jenis_tabungan': r['jenis'] ?? r['jenis_tabungan'] ?? '',
          'jumlah': int.tryParse((r['jumlah'] ?? 0).toString()) ?? 0,
        });
      }
      return out;
    } catch (e) {
      if (kDebugMode) debugPrint('getRiwayatTabungan error: $e');
      return [];
    }
  }

  // Get riwayat transaksi (setoran + pencairan) untuk seorang pengguna
  static Future<List<Map<String, dynamic>>> getRiwayatTransaksi(String idPengguna) async {
    try {
      final url = '${Api.getRiwayatTransaksi}?id_pengguna=$idPengguna';
      if (kDebugMode) debugPrint('📋 getRiwayatTransaksi REQUEST URL: $url');

      var response = await http
          .get(Uri.parse(url))
          .timeout(const Duration(seconds: 20));

      if (kDebugMode) debugPrint('📋 getRiwayatTransaksi RESPONSE CODE: ${response.statusCode}');

      if (response.statusCode == 200) {
        final body = _parseJsonSafeFromResponse(
          response,
          context: 'getRiwayatTransaksi',
          showToast: false,
        );

        if (body != null && (body['success'] == true) && body['data'] is List) {
          final List list = body['data'];
          if (kDebugMode) debugPrint('📋 getRiwayatTransaksi SUCCESS: ${list.length} items');
          return list.map((e) => Map<String, dynamic>.from(e)).toList();
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('📋 getRiwayatTransaksi ERROR: $e');
    }
    return [];
  }
}
