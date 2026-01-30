# Panduan Implementasi: Approval/Rejection Pencairan Tabungan

## Status Implementasi ✓ LENGKAP

Sistem approval dan rejection untuk pencairan tabungan (tabungan_keluar) **sudah diimplementasikan dengan lengkap** dengan fitur-fitur profesional:

---

## 📋 Fitur-Fitur Utama

### 1. **Approval Flow (Saat Status = "approved")**

Ketika admin menyetujui pencairan, sistem melakukan:

✓ **Update Status**
  - Mengubah `tabungan_keluar.status` dari 'pending' menjadi 'approved'
  - Menggunakan prepared statement untuk keamanan SQL Injection
  - Melakukan row lock (SELECT...FOR UPDATE) untuk mencegah double-processing

✓ **Deduct dari Tabungan Masuk**
  - Mengurangi saldo tabungan per jenis (`tabungan_masuk.jumlah`)
  - Menggunakan `withdrawal_deduct_saved_balance()` dari ledger_helpers.php
  - Deduction dilakukan dari record tertua (FIFO - First In First Out)
  - Memverifikasi saldo cukup sebelum deduction
  - Transaksi atomik: jika gagal, semua perubahan di-rollback

✓ **Credit ke Wallet (saldo_bebas)**
  - Menambahkan amount ke `pengguna.saldo` menggunakan `withdrawal_credit_wallet()`
  - Amount yang dikreditkan = nominal yang ditarik
  - Saldo wallet segera tersedia untuk penggunaan user

✓ **Insert Notification**
  ```
  judul: "Pencairan Disetujui"
  pesan: "Pencairan sebesar Rp {jumlah} dari {jenis_tabungan} telah disetujui 
          dan dikreditkan ke saldo bebas Anda. Saldo bebas saat ini: Rp {saldo_baru}."
  type: "withdrawal_approved"
  data: JSON dengan detail (tabungan_keluar_id, jenis_name, amount, new_saldo, status)
  ```

✓ **Garansi Deduplication**
  - Menggunakan `safe_create_notification()` yang cerdas
  - Mencegah duplikasi dengan memeriksa notifikasi yang sudah ada
  - Durasi check: 2 menit terakhir
  - Parameter pembeda: user_id, title, message, data

---

### 2. **Rejection Flow (Saat Status = "rejected")**

Ketika admin menolak pencairan, sistem melakukan:

✓ **Update Status HANYA**
  - Mengubah `tabungan_keluar.status` dari 'pending' menjadi 'rejected'
  - Menyimpan alasan penolakan di `tabungan_keluar.rejected_reason`
  - **TIDAK mengurangi saldo tabungan** - user tetap punya uang tersebut

✓ **Insert Rejection Notification**
  ```
  judul: "Pencairan Ditolak"
  pesan: "Pencairan sebesar Rp {jumlah} dari {jenis_tabungan} ditolak. 
          Alasan: {reason}"
  type: "withdrawal_rejected"
  data: JSON dengan detail (tabungan_keluar_id, jenis_name, amount, reason, status)
  ```

✓ **User Experience**
  - Notifikasi otomatis dikirim ke user
  - Alasan penolakan tercatat untuk transparency
  - User dapat mencoba permohonan baru tanpa kehilangan saldo

---

## 🔒 Fitur Keamanan

### Transaction Management (ACID Compliance)

```php
$connect->begin_transaction();

try {
    // 1. Lock row untuk mencegah race condition
    // SELECT...FOR UPDATE
    
    // 2. Deduct savings (if approve)
    // withdrawal_deduct_saved_balance()
    
    // 3. Update status
    // UPDATE tabungan_keluar SET status = ...
    
    // 4. Credit wallet (if approve)
    // withdrawal_credit_wallet()
    
    // 5. Create notification
    // create_withdrawal_approved/rejected_notification()
    
    $connect->commit();  // ✓ Semua succeed atau semua rollback
    
} catch (Exception $e) {
    $connect->rollback();  // ✗ Rollback semua perubahan jika error
}
```

### Prepared Statements
- Semua query menggunakan prepared statements
- Parameter binding mencegah SQL Injection
- Aman dari malicious input

### Race Condition Prevention
- `SELECT...FOR UPDATE` mengunci row selama transaction
- Mencegah double-approval
- Mencegah concurrent modifications

### Balance Validation
- Memverifikasi saldo cukup sebelum deduction
- Mencegah negative balance
- Atomic deduction dari multiple rows

---

## 📁 File-File Terkait

### 1. **approve_penarikan.php** (Main API)
**Lokasi:** `/gas_web/flutter_api/approve_penarikan.php`

**Request Format:**
```
POST /approve_penarikan.php

Parameter:
- no_keluar: ID withdrawal (integer) atau format TK-YYYYMMDDHHMMSS-ID
- action: 'approve' atau 'reject'
- approved_by: ID admin yang melakukan approval
- catatan: (Optional) Alasan jika reject
```

**Response Format:**
```json
{
  "success": true,
  "message": "Penarikan berhasil disetujui",
  "data": {
    "no_keluar": "42",
    "nama": "John Doe",
    "jumlah": 100000,
    "saldo_baru": 450000,
    "saldo_dashboard": 250000,
    "status": "approved"
  }
}
```

### 2. **notif_helper.php** (Notification Helpers)
**Lokasi:** `/gas_web/flutter_api/notif_helper.php`

**Fungsi Utama:**
```php
// Untuk Approval
create_withdrawal_approved_notification($connect, $user_id, $jenis_name, $amount, $new_saldo, $tab_keluar_id)

// Untuk Rejection
create_withdrawal_rejected_notification($connect, $user_id, $jenis_name, $amount, $reason, $tab_keluar_id)

// Untuk Pending
create_withdrawal_pending_notification($connect, $user_id, $jenis_name, $amount, $tab_keluar_id)
```

### 3. **ledger_helpers.php** (Ledger Operations)
**Lokasi:** `/gas_web/login/function/ledger_helpers.php`

**Fungsi Utama:**
```php
// Deduct dari tabungan masuk
withdrawal_deduct_saved_balance($con, $user_id, $jenis_id, $amount)
// Returns: float (new balance) atau false (error)

// Credit ke wallet
withdrawal_credit_wallet($con, $user_id, $amount, $note)
// Returns: float (new saldo) atau false (error)

// Create transaction record
create_withdrawal_transaction_record($con, $user_id, $jenis_id, $amount, $tab_keluar_id, $note)
// Returns: int (transaction ID) atau false (error)
```

---

## 🗄️ Database Schema

### Tabel: `tabungan_keluar`
```sql
CREATE TABLE `tabungan_keluar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna` INT NOT NULL,
  `id_jenis_tabungan` INT NOT NULL,
  `jumlah` DECIMAL(15,2) NOT NULL,
  `keterangan` VARCHAR(255),
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `rejected_reason` TEXT,  -- Alasan jika ditolak
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  KEY `idx_status` (`status`),
  KEY `idx_user` (`id_pengguna`),
  KEY `idx_jenis` (`id_jenis_tabungan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Tabel: `tabungan_masuk`
```sql
-- Digunakan untuk tracking saldo per jenis per user
-- Status: 'berhasil' = saldo aktif yang bisa dicairin
```

### Tabel: `pengguna`
```sql
-- Kolom: saldo (wallet/saldo_bebas)
-- Kolom: saldo akan ditambah saat approval
```

### Tabel: `notifikasi`
```sql
CREATE TABLE `notifikasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255),
  `message` TEXT,
  `data` JSON,  -- Structured data for client parsing
  `read_status` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  KEY `idx_user_type` (`id_pengguna`, `type`),
  KEY `idx_user_read` (`id_pengguna`, `read_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🧪 Testing

### Test File
**Lokasi:** `/gas_web/flutter_api/test_approve_reject_flow.php`

**Cara Menjalankan:**
```bash
php test_approve_reject_flow.php
```

**Test Cases:**
1. ✓ Setup - Create test withdrawal
2. ✓ Approve workflow - Verify deduction & credit
3. ✓ Verify notification created
4. ✓ Reject workflow - Verify no balance change
5. ✓ Final verification - Check saldo increased

---

## 📊 Audit Trail & Logging

Sistem mencatat setiap operasi di file log untuk audit:

### saldo_audit.log
```
2026-01-25T10:30:45+07:00 WITHDRAWAL_DEDUCT_SUCCESS user=1 jenis=1 amt=100000 new_balance=450000
2026-01-25T10:30:46+07:00 WITHDRAWAL_CREDIT_SUCCESS user=1 amt=100000 new_saldo=250000
2026-01-25T10:30:47+07:00 CREATE_WITHDRAWAL_APPROVED user=1 tab_keluar_id=42 nid=123 saldo=250000
```

### notification_filter.log
```
2026-01-25T10:30:47+07:00 CREATE_WITHDRAWAL_APPROVED user=1 tab_keluar_id=42 nid=123
```

---

## ⚠️ Error Handling

Semua error scenarios ditangani dengan proper:

| Scenario | Handling |
|----------|----------|
| Withdrawal not found | Return error, no changes |
| Already approved/rejected | Prevent double-processing with row lock |
| Insufficient balance | Return error, no changes |
| DB connection error | Rollback transaction |
| Notification creation fails | Log to file, but don't fail approval |
| User not found | Return error, no changes |

---

## 🚀 Deployment Checklist

- [x] Code implemented with transaction support
- [x] Prepared statements used throughout
- [x] Notification functions created
- [x] Ledger operations centralized
- [x] Row locking for race condition prevention
- [x] Error handling with try-catch
- [x] Audit logging implemented
- [x] Test suite created
- [x] No structural table changes needed
- [x] Backward compatible

---

## 📝 Example Usage

### Approve Withdrawal
```php
$_POST = [
    'no_keluar' => '42',
    'action' => 'approve',
    'approved_by' => 1,
    'catatan' => ''
];

include 'approve_penarikan.php';
// Response: {"success": true, "message": "Penarikan berhasil disetujui", ...}
```

### Reject Withdrawal
```php
$_POST = [
    'no_keluar' => '43',
    'action' => 'reject',
    'approved_by' => 1,
    'catatan' => 'Data tidak lengkap'
];

include 'approve_penarikan.php';
// Response: {"success": true, "message": "Penarikan ditolak", ...}
```

---

## 🎯 Key Points Summary

1. **Complete Transaction Support** - All operations in one atomic transaction
2. **Balance Safety** - Deductions only if balance sufficient
3. **User Notification** - Automatic notification with proper formatting
4. **No Duplicates** - Smart deduplication logic
5. **Audit Trail** - Complete logging for compliance
6. **Race Condition Safe** - Row locking prevents double-processing
7. **Error Resilient** - All errors rollback properly
8. **Backward Compatible** - No structure changes needed

---

## 📞 Support

Jika ada pertanyaan atau issue:
1. Check `saldo_audit.log` untuk operasi detail
2. Check `notification_filter.log` untuk notifikasi issue
3. Run `test_approve_reject_flow.php` untuk verify setup
4. Review logs di folder `/logs/` untuk connection issues
