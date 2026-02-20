import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter/foundation.dart';

class Api {
// =============================
// DEBUG OVERRIDE (DUMMY)
// =============================
static String? _override;

static const String overrideAuto = "auto";
static const String overrideLan = "lan";
static const String overrideEmulator = "emulator";

// DEFAULT NGROK URL - sudah dikonfigurasi dengan benar
// Ganti dengan ngrok URL kamu jika berubah
static const String _defaultLan = "https://tetrapodic-riotous-rosario.ngrok-free.dev/gas/gas_web/flutter_api";

static Future<void> init() async {}

static Future<void> setOverride(String? value) async {
  _override = value;
}

static Future<String?> getOverride() async {
  return _override;
}

  static String _resolveBaseUrl() {
    final envUrl = dotenv.env['API_BASE_URL'];
    if (envUrl != null && envUrl.trim().isNotEmpty) {
      final resolved = _sanitizeBaseUrl(envUrl);
      if (kDebugMode) print('🌍 Base URL from .env: $resolved');
      return resolved;
    }
    // Default to ngrok URL or your configured server
    if (kDebugMode) print('🌍 Using default Base URL: $_defaultLan');
    return _defaultLan;
  }

  static String _sanitizeBaseUrl(String url) {
    var sanitized = url.trim();
    while (sanitized.endsWith('/')) {
      sanitized = sanitized.substring(0, sanitized.length - 1);
    }
    return sanitized;
  }

  // Base URL property - resolve once and cache the value
  static late final String baseUrl = _resolveBaseUrl();

  static String _endpoint(String file) => '$baseUrl/$file';

  // Helper for API routes placed under /api (not /flutter_api)
  static String _apiPath(String file) {
    var root = baseUrl;
    if (root.endsWith('/flutter_api')) root = root.replaceAll('/flutter_api', '');
    // ensure no trailing slash
    while (root.endsWith('/')) root = root.substring(0, root.length - 1);
    return '$root/api/$file';
  }

  // Pinjaman Kredit endpoints
  static String get pinjamanKreditSubmit => _apiPath('pinjaman_kredit/submit.php');

  // Auth Endpoints
  static String get login => _endpoint('login.php');
  static String get register => _endpoint('register_tahap1.php');
  static String get registerTahap1 => _endpoint('register_tahap1.php');
  static String get registerTahap2 => _endpoint('register_tahap2.php');
  static String get aktivasiAkun => _endpoint('aktivasi_akun.php');
  static String get setPin => _endpoint('set_pin.php');
  static String get forgotPassword => _endpoint('forgot_password.php');
  static String get verifyOtpReset => _endpoint('verify_otp_reset.php');
  static String get resetPassword => _endpoint('reset_password.php');
  static String get resetPin => _endpoint('reset_pin.php');

  // User password change (user-initiated)
  static String get changePassword => _endpoint('change_password.php');
  static String get changePin => _endpoint('change_pin.php');

  // User Endpoints
  static String get getUsers => _endpoint('get_users.php');
  static String get addUser => _endpoint('add_user.php');
  static String get deleteUser => _endpoint('delete_user.php');
  static String get updateUser => _endpoint('update_user.php');
  static String get updateBiodata => _endpoint('update_biodata.php');
  static String get updateFoto => _endpoint('update_foto.php');
  static String get uploadFoto => _endpoint('upload_foto.php');
  // Detailed profile fetch
  static String get getProfilLengkap => _endpoint('get_profil.php');
  // Check PIN and tabungan status (guards dashboard access)
  static String get checkPin => _endpoint('check_pin.php');

  // Saldo Endpoints
  static String get getSaldo => _endpoint('get_saldo.php');

  // Payment endpoints for Minta/Pindai flow
  // Uses POST (JSON body): payer_user_id, amount, request_code (optional)
  // Configure base URL using .env (API_BASE_URL) or change defaults in _resolveBaseUrl().
  static String get payPaymentRequest => _endpoint('pay_payment_request.php');

  // Tabungan Endpoints
  static String get getTabungan => _endpoint('get_tabungan.php');
  static String get getTabungan2 => _endpoint('get_tabungan2.php');
  static String get getTabungan5 => _endpoint('get_tabungan5.php');
  static String get getTabunganToday => _endpoint('get_tabungantoday.php');
  static String get searchTabungan => _endpoint('search_tabungan.php');
  static String get getStabungan => _endpoint('get_stabungan.php');

  // Summary Endpoints
  static String get getJumlahTabunganAll =>
      _endpoint('get_jumlahtabunganall.php');
  static String get getJumlahTabunganBulan =>
      _endpoint('get_jumlahtabunganbulan.php');
  static String get getJumlahTabunganMinggu =>
      _endpoint('get_jumlahtabunganminggu.php');
  static String get getJumlahTabunganToday =>
      _endpoint('get_jumlahtabungantoday.php');

  // New: per-jenis summary and history
  static String get getSummaryByJenis => _endpoint('get_summary_by_jenis.php');
  static String get getHistoryByJenis => _endpoint('get_history_by_jenis.php');

  // Tabungan-specific endpoints
  static String get getSaldoTabungan => _endpoint('get_saldo_tabungan.php');
  static String get getRincianTabungan => _endpoint('get_rincian_tabungan.php');
  static String get getRiwayatTabungan => _endpoint('get_riwayat_tabungan.php');
  static String get getTotalTabungan => _endpoint('get_total_tabungan.php');
  static String get cairkanTabungan => _endpoint('cairkan_tabungan.php');
  static String get getRiwayatTransaksi => _endpoint('get_riwayat_transaksi.php');

  // Transaction Endpoints
  static String get addSetoran => _endpoint('add_setoran.php');
  static String get getSetoran => _endpoint('get_setoran.php');
  static String get addPenarikan => _endpoint('add_penarikan.php');
  static String get getPenarikan => _endpoint('get_penarikan.php');
  static String get approvePenarikan => _endpoint('approve_penarikan.php');
  static String get addTransfer => _endpoint('add_transfer.php');
  static String get getTransfer => _endpoint('get_transfer.php');
  static String get inspectUser => _endpoint('inspect_user.php');
  static String get submitTransaction => _endpoint('submit_transaction.php');

  // Notifications
  static String get getNotifications => _endpoint('get_notifications.php');

  // Notification actions
  static String get updateNotifikasiRead => _endpoint('update_notifikasi_read.php');

  // Transfer helpers
  static String get getFrequentRecipients => _endpoint('frequent_recipients.php');
  static String get findContacts => _endpoint('find_contacts.php');

  // Assets URL (untuk gambar, dll)
  static String get assetsUrl {
    final base = baseUrl.replaceAll('/flutter_api', '/assets');
    return _sanitizeBaseUrl(base);
  }

  // Health-check endpoint used by the mobile client to verify reachability
  static String get ping => _endpoint('ping.php');
}