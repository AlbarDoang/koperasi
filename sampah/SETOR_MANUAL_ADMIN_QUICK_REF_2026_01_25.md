# QUICK REFERENCE: Setor Manual Admin - Perbaikan Data Flow

## 🚀 IMPLEMENTASI SELESAI

Setoran manual admin **sekarang** muncul di:
- ✅ Saldo Tabungan
- ✅ Riwayat Transaksi
- ✅ Notifikasi
- ✅ **Tabungan Masuk** (FIX UTAMA)

---

## 📋 LANGKAH SETUP (1x saja)

### Step 1: Run Database Migration
```
GET /gas/gas_web/flutter_api/db_ensure_mulai_nabung_columns.php
```

**Response:**
```json
{
  "success": true,
  "message": "Kolom sumber berhasil ditambahkan ke tabel mulai_nabung",
  "action": "ALTER TABLE - ADD COLUMN sumber"
}
```

---

### Step 2: Verify Setup
```
GET /gas/gas_web/flutter_api/verify_setor_manual_fix.php
```

**Expected Output:**
```
✓ mulai_nabung_exists: true
✓ sumber_column_exists: true (setelah migration)
✓ Recommendation: READY - Perbaikan sudah diterapkan
```

---

## 🔧 MENGGUNAKAN SETOR MANUAL ADMIN

### Endpoint
```
POST /gas/gas_web/flutter_api/setor_manual_admin.php
```

### Parameters
```
id_pengguna       : integer (required) - ID user target
id_jenis_tabungan : integer (required) - ID jenis tabungan (1, 2, etc)
jumlah            : decimal (required) - Nominal setoran (bisa "500000" atau "500.000")
tanggal_setor     : date (optional)    - Format YYYY-MM-DD, default hari ini
keterangan        : string (optional)  - Catatan setoran
admin_id          : integer (required) - ID admin yang melakukan
```

### Example cURL
```bash
curl -X POST http://localhost/gas/gas_web/flutter_api/setor_manual_admin.php \
  -d "id_pengguna=123" \
  -d "id_jenis_tabungan=1" \
  -d "jumlah=500000" \
  -d "tanggal_setor=2026-01-25" \
  -d "keterangan=Setoran tambahan" \
  -d "admin_id=999"
```

### Success Response
```json
{
  "success": true,
  "message": "Setor saldo manual berhasil disimpan ke tabungan",
  "data": {
    "id": 42,
    "id_pengguna": 123,
    "id_jenis_tabungan": 1,
    "jumlah": 500000,
    "tanggal": "2026-01-25",
    "status": "approved",
    "sumber": "admin_manual",
    "admin_id": 999,
    "notif_id": 72
  }
}
```

---

## 🔍 DATABASE DATA

Setelah setor manual berhasil, data tersimpan di:

### 1. `tabungan_masuk` (Saldo Tabungan)
```sql
SELECT * FROM tabungan_masuk 
WHERE id_pengguna = 123 AND sumber = 'admin_manual'
ORDER BY created_at DESC;
```

### 2. `mulai_nabung` (Halaman "Tabungan Masuk" - FIX UTAMA)
```sql
SELECT * FROM mulai_nabung 
WHERE id_tabungan = 123 AND sumber = 'admin'
ORDER BY tanggal DESC;
```

### 3. `transaksi` (Riwayat Transaksi)
```sql
SELECT * FROM transaksi 
WHERE id_anggota = 123 AND jenis_transaksi = 'setoran'
ORDER BY tanggal DESC;
```

### 4. `notifikasi` (Notifikasi User)
```sql
SELECT * FROM notifikasi 
WHERE id_pengguna = 123 AND type = 'tabungan'
ORDER BY created_at DESC;
```

---

## 🧪 TEST

### Run Test INSERT
```bash
curl -X POST http://localhost/gas/gas_web/flutter_api/verify_setor_manual_fix.php \
  -d "id_pengguna=123" \
  -d "id_jenis_tabungan=1" \
  -d "jumlah=50000" \
  -d "admin_id=999"
```

**Response:**
```json
{
  "success": true,
  "message": "Test INSERT mulai_nabung berhasil",
  "data": {
    "test_type": "INSERT mulai_nabung",
    "id_pengguna": 123,
    "inserted_id": 145,
    "has_sumber_column": true,
    "sumber": "admin"
  }
}
```

---

## 📊 FILES YANG DIUBAH

### 1. **setor_manual_admin.php** (MAIN FIX)
- ✅ STEP 4 baru: INSERT ke `mulai_nabung`
- ✅ Cek kolom `sumber` (conditional)
- ✅ Non-fatal error handling
- ✅ Transaction safety

### 2. **db_ensure_mulai_nabung_columns.php** (HELPER)
- ✅ Tambah kolom `sumber` ke `mulai_nabung`
- ✅ Backward compatible
- ✅ Idempotent (bisa dijalankan berkali-kali)

### 3. **verify_setor_manual_fix.php** (VERIFICATION)
- ✅ Check status setup
- ✅ Run test INSERT
- ✅ Verify table structure

### 4. **get_riwayat_tabungan.php** (BUGFIX)
- ✅ Fix typo: `WHERE0` → `WHERE`

---

## 🎯 HASIL AKHIR

| Halaman | Before | After | Query Source |
|---------|--------|-------|-------------|
| Saldo Tabungan | ✅ | ✅ | `tabungan_masuk` SUM |
| Riwayat Transaksi | ✅ | ✅ | `transaksi` table |
| Notifikasi | ✅ | ✅ | `notifikasi` table |
| **Tabungan Masuk** | ❌ | **✅** | **`mulai_nabung`** |

---

## ⚠️ CATATAN PENTING

1. **WAJIB** jalankan `db_ensure_mulai_nabung_columns.php` sekali
2. Setelah itu, setor manual admin otomatis insert ke `mulai_nabung`
3. Error di `mulai_nabung` insert adalah **non-fatal** (tidak rollback transaction)
4. Existing data tetap valid (backward compatible)
5. **Tidak ada perubahan struktur tabel** (hanya ADD COLUMN)
6. **Tidak ada perubahan Flutter**

---

## 🆘 TROUBLESHOOTING

### Status belum READY
```
→ Run db_ensure_mulai_nabung_columns.php
→ Check error di response
```

### Setor gagal
```
→ Check id_pengguna ada di pengguna table
→ Check id_jenis_tabungan ada di jenis_tabungan table
→ Check parameter format (jumlah = numeric)
```

### Data tidak muncul di "Tabungan Masuk"
```
→ Verify di mulai_nabung table: SELECT * FROM mulai_nabung WHERE id_tabungan = 123
→ Check status = 'berhasil' (uppercase berhasil)
→ Check jenis_tabungan sesuai dengan yang di tampilkan user
```

---

## 📞 SUPPORT

**Log files untuk debugging:**
- `/gas_web/flutter_api/api_debug.log` - Debug logs

**Check latest insert:**
```sql
SELECT * FROM mulai_nabung ORDER BY created_at DESC LIMIT 1;
SELECT * FROM tabungan_masuk WHERE sumber = 'admin_manual' ORDER BY created_at DESC LIMIT 1;
```

---

**DONE!** ✅ Perbaikan siap digunakan.
