# 📱 SCAN PROJECT FLUTTER - RIWAYAT TRANSAKSI

## ✅ HASIL SCAN LENGKAP

Saya telah menscan seluruh project Flutter untuk menemukan file yang memanggil endpoint riwayat transaksi.

---

## 📋 DAFTAR FUNCTION YANG DITEMUKAN

### 1️⃣ `getRiwayatTabungan()`

**📁 File:**
- [gas_mobile/lib/event/event_db.dart](gas_mobile/lib/event/event_db.dart#L1929)

**📍 Path Lengkap:**
```
c:\xampp\htdocs\gas\gas_mobile\lib\event\event_db.dart
```

**🔗 Endpoint yang Dipanggil:**
```
Api.getRiwayatTabungan
→ gas_web/flutter_api/get_riwayat_tabungan.php
```

**📌 Function Signature:**
```dart
static Future<List<Map<String, dynamic>>> getRiwayatTabungan(
    String idTabungan,
    String jenis,
    String periode,
) async
```

**Line 1929-1961:**
```dart
/// Get riwayat (list of maps) for a jenis dan periode (days)
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
```

**⚠️ PERHATIAN PENTING:**
- Function ini **MENERIMA parameter `jenis`** dari caller
- Jenis dikirim ke API sebagai query parameter `jenis_tabungan=$jenis`
- **Tidak ada filter client-side** untuk mengecualikan "keluar"
- **Tergantung 100% pada server** untuk mengembalikan data yang benar

**Digunakan di file:**
- [gas_mobile/lib/page/tabungan.dart](gas_mobile/lib/page/tabungan.dart#L191) (line 191, 266, 687, 772)

---

### 2️⃣ `getHistoryByJenis()`

**📁 File:**
- [gas_mobile/lib/event/event_db.dart](gas_mobile/lib/event/event_db.dart#L880)

**📍 Path Lengkap:**
```
c:\xampp\htdocs\gas\gas_mobile\lib\event\event_db.dart
```

**🔗 Endpoint yang Dipanggil:**
```
Api.getHistoryByJenis
→ gas_web/flutter_api/get_history_by_jenis.php
```

**📌 Function Signature:**
```dart
static Future<List<Map<String, dynamic>>> getHistoryByJenis(
    String id_tabungan,
    String jenis,
    {String periode = '30', int limit = 200}
) async
```

**Line 880-916:**
```dart
// New: Get transaction history filtered by jenis (jenis can be id or name).
// Returns list of maps {date, title, amount, type}
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
```

**⚠️ PERHATIAN PENTING:**
- Function ini **JUGA MENERIMA parameter `jenis`** dari caller
- Jenis dikirim ke API sebagai POST body parameter
- **Tidak ada filter client-side** untuk mengecualikan "keluar"
- **Tergantung 100% pada server** untuk mengembalikan data yang benar

**Digunakan di file:**
- [gas_mobile/lib/page/tabungan.dart](gas_mobile/lib/page/tabungan.dart) (banyak tempat)

---

### 3️⃣ `getRiwayatTransaksi()`

**📁 File:**
- [gas_mobile/lib/event/event_db.dart](gas_mobile/lib/event/event_db.dart#L1964)

**📍 Path Lengkap:**
```
c:\xampp\htdocs\gas\gas_mobile\lib\event\event_db.dart
```

**🔗 Endpoint yang Dipanggil:**
```
Api.getRiwayatTransaksi
→ gas_web/flutter_api/get_riwayat_transaksi.php
```

**📌 Function Signature:**
```dart
static Future<List<Map<String, dynamic>>> getRiwayatTransaksi(String idPengguna) async
```

**Line 1964-1991:**
```dart
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
```

**⚠️ PERHATIAN PENTING:**
- Function ini **TIDAK MENERIMA filter apapun** dari jenis
- Hanya menerima `idPengguna` sebagai parameter
- **Tidak ada filter client-side** untuk mengecualikan "keluar"
- **Tergantung 100% pada server** untuk mengembalikan data yang benar

**Digunakan di file:**
- [gas_mobile/lib/page/riwayat.dart](gas_mobile/lib/page/riwayat.dart) (data dari SharedPreferences)

---

## 🔑 API ENDPOINTS DI CONFIG

**📁 File:**
- [gas_mobile/lib/config/api.dart](gas_mobile/lib/config/api.dart#L150)

**Line 150-176:**
```dart
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
```

---

## 📱 HALAMAN YANG MENGGUNAKAN RIWAYAT TRANSAKSI

### Halaman 1: TabunganPage

**📁 File:**
- [gas_mobile/lib/page/tabungan.dart](gas_mobile/lib/page/tabungan.dart)

**Penggunaan:**
1. **Line 191** - Fetch history saat load awal
2. **Line 266** - Fetch history saat jenis/periode berubah
3. **Line 687** - Fetch history saat user klik "Lihat Detail"
4. **Line 772** - Fetch history (kemungkinan polling)

**Menerima parameter dari:**
- `_selectedJenis` (nama atau ID jenis tabungan)
- `_selectedPeriode` (period filter)

**Tidak ada filter keluar di UI** - semuanya tergantung server

---

### Halaman 2: RiwayatTransaksiPage

**📁 File:**
- [gas_mobile/lib/page/riwayat.dart](gas_mobile/lib/page/riwayat.dart#L26)

**Penggunaan:**
1. **Line 36** - Load data dari SharedPreferences (key: `'transactions'`)
2. **Line 37** - Load data dari SharedPreferences (key: `'pengajuan_list'`)

**Filter yang dilakukan di UI (Line 50-61):**
```dart
// MAP data dari tabel transaksi yang baru
// Jika field 'type' belum ada, generate dari jenis_transaksi
if (!item.containsKey('type') && item.containsKey('jenis_transaksi')) {
  final jenisTrans = (item['jenis_transaksi'] ?? '').toString().toLowerCase();
  
  if (jenisTrans == 'setoran') {
    item['type'] = 'topup';
    item['title'] = 'Setoran Tabungan';
  } else if (jenisTrans == 'penarikan' || jenisTrans == 'transfer_keluar') {
    item['type'] = 'transfer';
    item['title'] = 'Penarikan Tabungan';
  } else if (jenisTrans == 'transfer_masuk') {
    item['type'] = 'transfer_masuk';
    item['title'] = 'Transfer Masuk';
  } else {
    item['type'] = 'lainnya';
    item['title'] = jenisTrans;
  }
}
```

**⚠️ PENTING:**
- **TIDAK ada filter yang mengecualikan "keluar"**
- Withdrawal/penarikan **DITAMPILKAN DENGAN TYPE 'transfer'**
- Data datang dari SharedPreferences, bukan API langsung

---

## 🎯 KESIMPULAN FINAL

### ✅ APAKAH FLUTTER MEMFILTER WITHDRAWAL?

**JAWABAN: TIDAK**

### 📊 Detail Temuan:

| Fungsi | Memfilter? | Catatan |
|--------|-----------|---------|
| `getRiwayatTabungan()` | ❌ TIDAK | Menerima `jenis` dari caller, tidak filter sendiri |
| `getHistoryByJenis()` | ❌ TIDAK | Menerima `jenis` dari caller, tidak filter sendiri |
| `getRiwayatTransaksi()` | ❌ TIDAK | Tidak ada parameter filter, ambil semua dari server |
| RiwayatTransaksiPage UI | ❌ TIDAK | Menampilkan semua jenis termasuk 'penarikan' |

### ⚠️ MASALAH SEBENARNYA:

**Masalahnya BUKAN di Flutter, tetapi di SERVER-SIDE API:**

1. **`get_riwayat_tabungan.php`** 
   - ❌ Hanya query dari `mulai_nabung` + `tabungan_masuk`
   - ❌ TIDAK membaca `tabungan_keluar`
   - ✅ Withdrawal tidak muncul karena API server tidak mengirimnya

2. **`get_history_by_jenis.php`**
   - ✅ Query dari `tabungan_masuk` + `tabungan_keluar`
   - ✅ Withdrawal MUNCUL dengan benar
   - ✅ API ini sudah BENAR

3. **`get_riwayat_transaksi.php`**
   - ✅ Query dari tabel `transaksi`
   - ✅ Mencakup semua jenis transaksi
   - ✅ API ini sudah BENAR

### 🔍 Proof:

**Line 1943-1957 di event_db.dart (getRiwayatTabungan):**
```dart
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
```

**Flutter HANYA MENG-REMAP data dari server, tidak memfilter**

---

## 📝 REKOMENDASI

### Untuk Memastikan Withdrawal Muncul:

1. **Gunakan `get_history_by_jenis.php`** (sudah benar)
   - Sudah include `tabungan_keluar`
   - Sudah OK

2. **Atau FIX `get_riwayat_tabungan.php`**
   - Tambah query `tabungan_keluar`
   - Update untuk membaca withdrawal

3. **VERIFIKASI:**
   - Test API endpoint langsung:
     ```
     POST /gas_web/flutter_api/get_history_by_jenis.php
     Body: id_tabungan=X, jenis=Y, periode=30
     ```
   - Pastikan response include withdrawal

---

## 📂 SUMMARY FILE LOCATIONS

```
Gas Project Root: c:\xampp\htdocs\gas

Flutter Functions:
├─ getRiwayatTabungan()        → gas_mobile/lib/event/event_db.dart:1929
├─ getHistoryByJenis()          → gas_mobile/lib/event/event_db.dart:880
├─ getRiwayatTransaksi()        → gas_mobile/lib/event/event_db.dart:1964
└─ API Config                   → gas_mobile/lib/config/api.dart:150-176

UI Pages:
├─ TabunganPage                 → gas_mobile/lib/page/tabungan.dart
└─ RiwayatTransaksiPage         → gas_mobile/lib/page/riwayat.dart

API Endpoints:
├─ get_riwayat_tabungan.php     → gas_web/flutter_api/
├─ get_history_by_jenis.php     → gas_web/flutter_api/ ✅
└─ get_riwayat_transaksi.php    → gas_web/flutter_api/
```

---

**🔍 Scan Selesai: 25 Januari 2026**
