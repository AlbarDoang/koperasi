# Analisis Alur Data Sistem Tabungan

**Status:** ✅ PERBAIKAN SELESAI  
**Tanggal:** 22 Januari 2026  
**Masalah:** Error "FormatException: Unexpected character" saat klik tombol "Saya sudah menyerahkan uang"

---

## 🔴 Masalah yang Ditemukan dan DIPERBAIKI

### Error: FormatException pada `update_status_mulai_nabung.php`

**Penyebab:**
- File PHP mengeluarkan PHP Warnings sebelum JSON response
- Warning: `Undefined array key "REQUEST_METHOD"` (akses `$_SERVER['REQUEST_METHOD']` langsung tanpa null coalescing)
- Flutter app mencoba parse HTML warning sebagai JSON → FormatException

**Solusi yang Diterapkan:**

| File | Perubahan |
|------|----------|
| `update_status_mulai_nabung.php` | ✅ Tambah error_reporting + gunakan `($_SERVER['REQUEST_METHOD'] ?? '')` |
| `buat_mulai_nabung.php` | ✅ Tambah error_reporting di awal file |
| `admin_verifikasi_mulai_nabung.php` | ✅ Tambah error_reporting di awal file |
| `get_mulai_nabung.php` | ✅ Tambah error_reporting + gunakan null coalescing operator |

---

## 📊 Struktur Database Tabungan

### 1. **Tabel: `mulai_nabung`** (Permintaan Top-up Tunai)
```
Kolom: id_mulai_nabung, id_tabungan, nomor_hp, nama_pengguna, 
       tanggal, jumlah, jenis_tabungan, status, created_at, updated_at

Status Flow:
  - 'menunggu_penyerahan' → user belum serah tunai
  - 'menunggu_admin'      → user klik "Saya sudah menyerahkan uang" → API: update_status_mulai_nabung.php
  - 'berhasil'            → admin approve → API: admin_verifikasi_mulai_nabung.php
  - 'ditolak'             → admin reject
```

### 2. **Tabel: `tabungan_masuk`** (Setoran Tabungan - Per Jenis)
```
Kolom: id, id_pengguna, id_jenis_tabungan, jumlah, 
       keterangan, status, created_at, updated_at

GUNANYA:
  ✅ Menyimpan SALDO SETORAN per jenis untuk user (ledger)
  ✅ Diisi ketika admin approve 'mulai_nabung' dengan status='berhasil'
  ✅ Dipotong ketika pencairan tabungan disetujui

PENTING: Ini hanya untuk SALDO TERSIMPAN per jenis, BUKAN untuk log transfer
```

### 3. **Tabel: `tabungan_keluar`** (Permintaan Pencairan)
```
Kolom: id, id_pengguna, id_jenis_tabungan, jumlah, 
       status, keterangan, created_at, updated_at

Status Flow:
  - 'pending'    → user minta pencairan
  - 'approved'   → admin approve
  - 'rejected'   → admin tolak
  - 'completed'  → dana sudah dicairkan

GUNANYA: Menyimpan PERMINTAAN dan LOG PENCAIRAN tabungan user
```

---

## 🔄 Alur Data Sistem

### **ALUR 1: Mulai Nabung (Top-up Tunai)**

```
User click "Mulai Nabung" (Halaman Tabungan)
    ↓
[Flutter] Detail Page menampilkan form top-up
    ↓
User isi jumlah & klik "Saya sudah menyerahkan uang"
    ↓
[API] buat_mulai_nabung.php
      - INSERT ke tabel mulai_nabung (status='menunggu_penyerahan')
      - return id_mulai_nabung
    ↓
[API] update_status_mulai_nabung.php ✅ SUDAH DIPERBAIKI
      - UPDATE mulai_nabung SET status='menunggu_admin'
      - Create notifikasi untuk user: "Setoran sedang diproses"
      - JANGAN ubah saldo user di sini (tunggu admin approve)
    ↓
[Admin Dashboard] Approval Page
    ↓
Admin verify bukti pembayaran & klik "Setujui"
    ↓
[API] admin_verifikasi_mulai_nabung.php
      - Cek saldo user
      - UPDATE mulai_nabung SET status='berhasil'
      - ADD/UPDATE tabungan_masuk dengan jumlah setoran
      - TAMBAH saldo user di pengguna.saldo
      - Create notifikasi untuk user: "Setoran berhasil ditambahkan"
    ↓
[Flutter] User lihat di "Halaman Tabungan": saldo bertambah
```

### **ALUR 2: Pencairan Tabungan**

```
User buka "Halaman Tabungan" → lihat saldo
    ↓
User klik tombol "Cairkan" (di Halaman Tabungan)
    ↓
[Flutter] Tampilkan form pencairan (pilih jenis, jumlah)
    ↓
User isi form & klik "Cairkan"
    ↓
[API] cairkan_tabungan.php
      - Validasi: saldo >= jumlah cairkan
      - INSERT ke tabel tabungan_keluar (status='pending')
      - KURANG tabungan_masuk dengan jumlah cairkan
      - KURANG saldo user di pengguna.saldo
      - Create notifikasi: "Permintaan pencairan sedang diproses"
    ↓
[Admin Dashboard] Approval Page → lihat request pencairan
    ↓
Admin review & klik "Setujui" / "Tolak"
    ↓
IF SETUJUI:
  [API] approve_penarikan.php
      - UPDATE tabungan_keluar SET status='approved'
      - Notifikasi: "Pencairan disetujui"
    ↓
IF TOLAK:
  [API] approve_penarikan.php (dengan action='reject')
      - UPDATE tabungan_keluar SET status='rejected'
      - RESTORE tabungan_masuk & saldo user (REVERSAL)
      - Notifikasi: "Pencairan ditolak"
    ↓
[Flutter] User lihat status pencairan di history
```

### **ALUR 3: Transfer Saldo Bebas (Dashboard)**

```
User buka "Dashboard" → lihat "Saldo Bebas"
    ↓
User buka menu "Minta" atau "Pindai"
    ↓
User pilih penerima & nominal transfer
    ↓
[API] pay_payment_request.php atau transfer.php
      - KURANG saldo pemilik di pengguna.saldo
      - INSERT ke tabel transfer (atau log_transfer)
      - JANGAN insert ke tabungan_masuk!
    ↓
Saldo penerima:
  - Jika transfer diterima:
    - ADD saldo penerima di pengguna.saldo
    - JANGAN insert ke tabungan_masuk
    
  - EXCEPTION: Jika ada saldo "Diterima dari transfer" yang perlu tracking:
    - Mungkin perlu tabungan_masuk dengan jenis khusus
    - Tapi ini BUKAN standar flow sekarang
```

---

## ⚠️ PENTING: Kapan Data Masuk Ke Tabel Mana?

| Aksi User | Tabel Penyimpanan | Catatan |
|-----------|-------------------|---------|
| **Mulai Nabung** (top-up tunai) | → `mulai_nabung` (pending) → `tabungan_masuk` (saat admin approve) | ✅ Benar |
| **Pencairan Tabungan** | → `tabungan_keluar` | ✅ Benar |
| **Transfer Saldo Bebas** | → (tabel transfer/log, BUKAN tabungan_masuk) | ⚠️ Perlu klarifikasi |
| **Menerima Transfer** | → pengguna.saldo (BUKAN tabungan_masuk) | ⚠️ Perlu klarifikasi |

---

## 🔧 API Files yang Sudah Diperbaiki

### ✅ Update 22 Januari 2026

```php
// File: gas_web/flutter_api/update_status_mulai_nabung.php
// Line: 1-10
<?php
// Suppress PHP warnings/notices that would break JSON output
error_reporting(E_ERROR | E_PARSE);  // ← TAMBAHAN
ini_set('display_errors', '0');      // ← TAMBAHAN

header('Access-Control-Allow-Origin: *');
// ... rest of code

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {  // ← CHANGED (null coalescing)
```

---

## 📝 Next Steps untuk User

### Untuk Testing:

1. **Bersihkan app & rebuild:**
   ```bash
   flutter clean
   flutter pub get
   flutter run --release
   ```

2. **Test flow "Mulai Nabung":**
   - Buka "Halaman Tabungan"
   - Klik "Mulai Nabung"
   - Isi form, klik "Saya sudah menyerahkan uang"
   - ✅ Seharusnya TIDAK ada error "FormatException" lagi

3. **Verifikasi di Database:**
   ```sql
   SELECT * FROM mulai_nabung WHERE id_mulai_nabung = (SELECT MAX(id_mulai_nabung) FROM mulai_nabung);
   ```
   - Status harus berubah dari 'menunggu_penyerahan' → 'menunggu_admin'

### Untuk Admin:

- Buka Admin Dashboard → "Approval Mulai Nabung"
- Verifikasi & approve request
- Cek bahwa `tabungan_masuk` bertambah
- Cek bahwa user's `saldo` bertambah

---

## 🎯 Kesimpulan

✅ **Error sudah diperbaiki** - API sekarang mengembalikan JSON yang clean  
✅ **Data flow sudah benar** - Sesuai dengan business logic user  
⚠️ **Pending clarification** - Transfer saldo bebas dan tabungan_masuk relationship  

Sistem siap di-test kembali! 🚀
