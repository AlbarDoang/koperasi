# ✅ PERBAIKAN: Setor Manual Admin Sekarang Muncul di "Tabungan Masuk"

**Status:** SELESAI  
**Tanggal:** 25 Januari 2026  
**Masalah:** Ketika admin melakukan setor manual untuk user, history/riwayat tidak muncul di halaman "Tabungan Masuk"

---

## 🔴 PENYEBAB MASALAH

API file `get_riwayat_tabungan.php` yang menampilkan halaman "Tabungan Masuk" di Flutter **hanya mengambil data dari tabel `mulai_nabung`** (setoran yang di-submit oleh user dan disetujui admin).

Setor manual admin disimpan ke tabel **`tabungan_masuk`**, sehingga tidak muncul di halaman "Tabungan Masuk".

---

## ✅ SOLUSI YANG DITERAPKAN

### 1. **Update `get_riwayat_tabungan.php`** ✨
   - Sekarang mengambil data dari **BOTH** tabel:
     - `mulai_nabung` (legacy/setor biasa yang sudah diapprove)
     - `tabungan_masuk` (setor manual admin + transaksi lainnya)
   - Menggabungkan hasil dari kedua tabel
   - Sorting otomatis berdasarkan tanggal DESC (terbaru dulu)
   - Include field `sumber` untuk debugging (identifikasi dari tabel mana data berasal)

### 2. **Update `setor_manual_admin.php`** ✨
   - Fetch nama jenis tabungan dari database saat setor
   - Simpan ke kolom `jenis_tabungan` (string name, bukan ID)
   - Ini memastikan API `get_riwayat_tabungan.php` bisa filter berdasarkan jenis dengan benar

### 3. **File SQL Schema** (OPSIONAL - jika kolom belum ada)
   - File: `add_columns_tabungan_masuk.sql`
   - Menambahkan kolom yang mungkin belum ada di tabel `tabungan_masuk`:
     - `tanggal` (DATE) - untuk menyimpan tanggal setor
     - `jenis_tabungan` (VARCHAR 100) - nama jenis tabungan (string)
     - `sumber` (VARCHAR 50) - tracking sumber (admin_manual, etc)
     - `status` (VARCHAR 50) - status transaksi (approved, berhasil, etc)
     - `admin_id` (BIGINT) - ID admin yang melakukan setor

---

## 📋 STEP UNTUK APPLY PERBAIKAN

### Option A: MANUAL (Recommended)
```bash
# 1. Execute SQL schema untuk add missing columns (jika belum ada)
mysql -u root tabungan < /path/to/add_columns_tabungan_masuk.sql

# 2. Verifikasi tabel structure
mysql -u root -e "DESCRIBE tabungan_masuk;" tabungan
```

### Option B: Via PHP Admin Tool
- Upload file `add_columns_tabungan_masuk.sql` ke PHPMyAdmin
- Execute SQL

---

## 🧪 TEST UNTUK VERIFY PERBAIKAN

### Test Case 1: Setor Manual Baru
1. Buka halaman Admin → Tabungan Masuk → Setor Manual
2. Isi form dengan:
   - Pengguna: Pilih salah satu user
   - Jenis Tabungan: Pilih salah satu (mis: "Tabungan Reguler")
   - Nominal: Rp 100.000
   - Tanggal: Hari ini
3. Klik "Simpan Setor"
4. ✅ SUCCESS: Message "Setor saldo manual berhasil"
5. Login di Mobile App dengan user tersebut
6. Buka halaman "Tabungan Masuk"
7. ✅ VERIFY: Riwayat setor manual harus muncul di list dengan:
   - Tanggal: hari ini
   - Nominal: Rp 100.000
   - Jenis: Tabungan Reguler (atau jenis yang dipilih)

### Test Case 2: Filter per Jenis
1. Setelah test case 1 selesai
2. Di halaman "Tabungan Masuk", ada dropdown "Jenis Tabungan"
3. Pilih "Tabungan Reguler"
4. ✅ VERIFY: Setor manual harus tetap muncul

### Test Case 3: Combined with Regular Top-up
1. Setor manual Rp 100.000 (via admin)
2. User juga submit regular top-up Rp 50.000 dan approve via admin
3. Buka "Tabungan Masuk"
4. ✅ VERIFY: BOTH riwayat harus muncul (Rp 100.000 + Rp 50.000)

---

## 📝 FILE YANG DIUBAH

| File | Perubahan |
|------|-----------|
| [get_riwayat_tabungan.php](get_riwayat_tabungan.php) | ✅ Updated - sekarang query BOTH tabel mulai_nabung + tabungan_masuk |
| [setor_manual_admin.php](setor_manual_admin.php) | ✅ Updated - fetch dan simpan nama jenis_tabungan |
| [add_columns_tabungan_masuk.sql](add_columns_tabungan_masuk.sql) | ✅ Created - SQL schema untuk add missing columns |

---

## 🎯 NEXT STEPS

1. **WAJIB:** Run SQL schema jika belum (untuk add missing columns)
2. Lakukan TEST CASES di atas untuk verify
3. Deploy ke production
4. Monitor error logs di `flutter_api/api_debug.log` untuk memastikan tidak ada error

---

## 💡 TECHNICAL NOTES

- **Backward Compatibility:** Perbaikan ini tetap kompatibel dengan setor manual lama
- **Performance:** Query sudah optimized dengan prepared statements
- **Error Handling:** Non-fatal errors di salah satu tabel tidak akan break aplikasi
- **Logging:** Debug info disimpan ke `api_debug.log` untuk troubleshooting

---

## ❓ FAQ

**Q: Apakah saldo user di `pengguna.saldo` otomatis bertambah?**  
A: Tidak! Setor manual HANYA menambah saldo **tabungan spesifik** (di tabel `tabungan_masuk`), bukan saldo bebas/umum user.

**Q: Kenapa ada field `sumber` di response?**  
A: Untuk debugging - memudahkan track apakah data dari `mulai_nabung` atau `tabungan_masuk`.

**Q: Bagaimana jika kolom `jenis_tabungan` belum ada di tabel?**  
A: Query akan fallback ke COALESCE, tapi sebaiknya add kolom via SQL schema untuk consistency.

---

**Created by:** System  
**Last Updated:** 2026-01-25
