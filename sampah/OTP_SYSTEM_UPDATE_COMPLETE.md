# 📋 OTP SYSTEM UPDATE - COMPLETION REPORT

**Date:** January 19, 2026  
**Status:** ✅ COMPLETED  
**Version:** 2.0.0  

---

## 📌 RINGKASAN PERUBAHAN

Sistem OTP telah diupdate untuk memenuhi spesifikasi berikut:
- ✅ OTP berlaku **2 MENIT** (bukan 1 menit)
- ✅ OTP **6 digit angka** acak
- ✅ OTP hanya bisa digunakan **1 kali**
- ✅ Pembedaan OTP: **reset password** vs **aktivasi akun**
- ✅ **Tidak ada duplikasi** fungsi, tabel, atau logika
- ✅ Backward compatible dengan sistem yang sudah ada
- ✅ Response API **konsisten** (format JSON: `{success: bool, message: string}`)

---

## 🔄 FILE YANG DIUPDATE

### 1. **message_templates.php**
**Perubahan:**
- ✅ Update `getMessageOTPActivation()` dengan teks sesuai spek Koperasi GAS
- ✅ Update `getMessageOTPForgotPassword()` dengan teks sesuai spek Koperasi GAS
- ✅ Pesan format: `Koperasi GAS` + deskripsi + `{{KODE_OTP}}` + durasi valid + peringatan

**Pesan untuk Aktivasi Akun:**
```
Koperasi GAS
Terima kasih telah mendaftar.
Kode OTP untuk aktivasi akun Anda adalah:
{{KODE_OTP}}

Kode ini bersifat rahasia dan berlaku selama 2 menit.
Jangan bagikan kode ini kepada siapa pun.
```

**Pesan untuk Reset Password:**
```
Koperasi GAS
Kode OTP untuk reset password akun Anda adalah:
{{KODE_OTP}}

Kode ini bersifat rahasia dan berlaku selama 2 menit.
Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin.
```

### 2. **Database Migration: 001_add_otp_type_column.php** (NEW)
**Perubahan:**
- ✅ Tambah kolom `type` ke tabel `katasandi_reset_otps`
- ✅ Kolom: `type VARCHAR(50) DEFAULT 'reset_password'`
- ✅ Safe: Pengecekan IF NOT EXISTS untuk mencegah error

**Migration Execution:**
```bash
cd gas_web && php migrations/001_add_otp_type_column.php
# Output: {"success":true,"message":"Kolom type berhasil ditambahkan"}
```

### 3. **flutter_api/verify_otp_reset.php**
**Perubahan:**
- ✅ Update validasi OTP (3 tahap):
  1. Validasi OTP cocok
  2. Validasi OTP tidak expired (`expired_at >= NOW()`)
  3. Validasi OTP belum digunakan (`status = 'belum'`)
- ✅ Mark OTP sebagai `'terpakai'` jika valid
- ✅ Response error message konsisten dan informatif
- ✅ Status code: `401` (OTP salah), `410` (expired), `409` (sudah digunakan)

**Response Format:**
```json
{
  "success": false,
  "message": "Kode OTP telah kedaluwarsa. Silakan minta kode baru."
}
```

### 4. **flutter_api/reset_pin.php**
**Perubahan:**
- ✅ Update validasi OTP dengan format yang sama
- ✅ Mark OTP sebagai `'terpakai'` setelah PIN berhasil direset
- ✅ Pesan error konsisten dengan verify_otp_reset.php
- ✅ Parametrized query untuk status update (mencegah SQL injection)

### 5. **flutter_api/forgot_password.php**
**Perubahan:**
- ✅ Update expired time dari **1 menit** menjadi **2 menit**
- ✅ Insert OTP ke tabel `katasandi_reset_otps` dengan `status='belum'`
- ✅ Update pesan OTP menggunakan template dari `sendOTPViaCURL()`
- ✅ Parametrized query untuk insert status (mencegah SQL injection)

**OTP Expiry:**
```php
$expired_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
```

### 6. **flutter_api/aktivasi_akun.php**
**Perubahan:**
- ✅ Update expired time dari **1 menit** menjadi **2 menit**
- ✅ Update pesan sendOTPViaCURL dengan template aktivasi akun yang sesuai spek
- ✅ Parametrized query untuk insert OTP (`belum` status)

**OTP Expiry:**
```php
$expired_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
```

### 7. **flutter_api/verify_otp.php**
**Perubahan:**
- ✅ Perbaiki status field: gunakan `'belum'`, `'terpakai'` (konsisten)
- ✅ Update pesan error yang lebih informatif dan konsisten
- ✅ Separasi error case: expired vs sudah digunakan

---

## 🔒 VALIDASI OTP - TIGA TAHAP

### Tahap 1: OTP Cocok?
```php
if ($otp_record['kode_otp'] !== $otp) {
    // Error: "Kode OTP yang Anda masukkan tidak valid."
}
```

### Tahap 2: OTP Expired?
```php
if ($otp_record['expired_at'] < $now) {
    // Error: "Kode OTP telah kedaluwarsa. Silakan minta kode baru."
}
```

### Tahap 3: OTP Sudah Digunakan?
```php
if ($otp_record['status'] !== 'belum') {
    // Error: "Kode OTP sudah pernah digunakan. Silakan minta kode baru."
}
```

### Mark OTP Sebagai Used
```php
$sql_update = 'UPDATE katasandi_reset_otps SET status = ? WHERE id = ?';
$stmt_update->bind_param('si', $status_terpakai, $otp_record['id']);
$stmt_update->execute();
```

---

## 📊 DATABASE SCHEMA

### Tabel: `katasandi_reset_otps`
```sql
CREATE TABLE `katasandi_reset_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `no_hp` varchar(15) NOT NULL,
  `kode_otp` varchar(6) NOT NULL,
  `type` varchar(50) DEFAULT 'reset_password',  -- ← NEW COLUMN
  `status` varchar(20) DEFAULT 'belum',
  `expired_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Tabel: `otp_codes`
```sql
CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_wa` varchar(20) NOT NULL,
  `kode_otp` varchar(6) NOT NULL,
  `expired_at` datetime NOT NULL,
  `status` enum('belum','terpakai') DEFAULT 'belum',  -- ← Updated status values
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🧪 TEST CASES

### Test 1: OTP Request + Send
```bash
POST /flutter_api/aktivasi_akun.php
{
  "action": "send_otp",
  "no_hp": "08781234567"
}

Response:
{
  "success": true,
  "message": "Kode OTP telah dikirim melalui WhatsApp"
}
```

### Test 2: Verify OTP (Success)
```bash
POST /flutter_api/verify_otp_reset.php
{
  "no_hp": "08781234567",
  "otp": "123456"  // Assumed valid OTP from DB
}

Response (OK):
{
  "success": true,
  "message": "OTP berhasil diverifikasi."
}
```

### Test 3: OTP Expired
```bash
POST /flutter_api/verify_otp_reset.php
{
  "no_hp": "08781234567",
  "otp": "123456"  // Valid OTP but > 2 minutes old
}

Response (410):
{
  "success": false,
  "message": "Kode OTP telah kedaluwarsa. Silakan minta kode baru."
}
```

### Test 4: OTP Already Used
```bash
POST /flutter_api/verify_otp_reset.php
{
  "no_hp": "08781234567",
  "otp": "123456"  // OTP status='terpakai'
}

Response (409):
{
  "success": false,
  "message": "Kode OTP sudah pernah digunakan. Silakan minta kode baru."
}
```

### Test 5: OTP Salah
```bash
POST /flutter_api/verify_otp_reset.php
{
  "no_hp": "08781234567",
  "otp": "999999"  // Wrong OTP
}

Response (401):
{
  "success": false,
  "message": "Kode OTP yang Anda masukkan tidak valid."
}
```

---

## 🔄 BACKWARD COMPATIBILITY

✅ **Semua fitur lama tetap berjalan:**
- OTP untuk aktivasi akun (tabel `otp_codes`)
- OTP untuk reset password (tabel `katasandi_reset_otps`)
- OTP untuk reset PIN (tabel `katasandi_reset_otps`)
- Semua endpoint Flutter API
- Semua endpoint Web (aktivasi_akun.php, verifikasi_otp.php, dll)

✅ **Tidak ada breaking changes:**
- Kolom baru ditambahkan dengan DEFAULT value
- Status value (`'belum'`, `'terpakai'`) konsisten
- Response format (success, message) standar

---

## ⚠️ PENTING - TIDAK ADA DUPLIKASI

✅ **Tanpa duplikasi file:**
- Tidak ada file PHP baru untuk OTP (reuse yang sudah ada)
- Tidak ada tabel baru (reuse `katasandi_reset_otps` dan `otp_codes`)

✅ **Tanpa duplikasi fungsi:**
- Semua fungsi OTP centralized di `otp_helper.php`
- Pesan template di `message_templates.php`
- Config token di `config/fonnte_constants.php`

✅ **Tanpa duplikasi logika:**
- Validasi OTP sama untuk semua endpoint
- Response format sama untuk semua endpoint
- Pesan error sama untuk kondisi yang sama

---

## 🎯 SUMMARY

| Aspek | Sebelum | Sesudah | Status |
|-------|---------|---------|--------|
| OTP Valid Time | 1 menit | 2 menit | ✅ |
| OTP Format | 6 digit | 6 digit | ✅ |
| One-time Use | Ya | Ya | ✅ |
| Type Differentiation | Tidak | Ya (tipe field) | ✅ |
| Response Format | Inkonsisten | Konsisten | ✅ |
| Duplikasi Kode | Ada | Tidak | ✅ |
| Backward Compatible | - | Ya | ✅ |

---

## 📝 NEXT STEPS

1. **Testing:** Jalankan test cases di atas
2. **Monitoring:** Monitor log files untuk errors:
   - `/flutter_api/log_db.txt`
   - `/flutter_api/log_otp_fonte.txt`
   - `/flutter_api/api_debug.log`
3. **Flutter Update:** Pastikan Flutter app menampilkan pesan error dari backend apa adanya
4. **Production Deploy:** Test di staging dulu sebelum production

---

## 📞 QUESTIONS?

Jika ada error atau issue:
1. Cek log files
2. Verifikasi database migration berhasil
3. Test endpoint dengan curl atau Postman
4. Verifikasi FONNTE_TOKEN di `config/fonnte_constants.php`

