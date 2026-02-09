# PERUBAHAN KODE - PERBAIKAN REJECTION ERROR

## File yang Dimodifikasi
- `/gas_web/flutter_api/approve_penarikan.php` (Lines 265-326)

## Perubahan: Dari Tidak Robust → Robust dengan Error Handling

### Sebelum (Lama - BERMASALAH):
```php
} else { // reject
    $stmtReject = $connect->prepare("UPDATE tabungan_keluar SET status = 'rejected', rejected_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
    if (!$stmtReject) throw new Exception('DB prepare failed for reject: ' . $connect->error);
    $stmtReject->bind_param('si', $catatan, $penarikan['id']);  // ← Tidak dicek!
    $stmtReject->execute();  // ← Tidak dicek! MASALAH UTAMA!
    $ar = $stmtReject->affected_rows;
    $stmtReject->close();
    
    if ($ar <= 0) {
        throw new Exception('Data penarikan tidak ditemukan atau sudah diproses');
    }

    $rsn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_saldo FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
    if ($rsn) {
        $rsn->bind_param('ii', $id_tabungan, $id_jenis_tabungan);  // ← Tidak dicek!
        $rsn->execute();  // ← Tidak dicek!
        $rr = $rsn->get_result();
        $nr = $rr->fetch_assoc();
        $new_saldo = floatval($nr['total_saldo'] ?? 0);
        $rsn->close();
    } else {
        $new_saldo = null;
    }
    
    // ... transaction record creation ...
    $message = "Penarikan ditolak";
    $new_peng_saldo = $saldo_current;
}
```

---

### Sesudah (Baru - DIPERBAIKI):
```php
} else { // reject
    // REJECTION: Update tabungan_keluar status to 'rejected' ENUM value + save rejection reason
    
    // Step 1: Prepare UPDATE statement
    $sql_reject = "UPDATE tabungan_keluar SET status = 'rejected', rejected_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'";
    $stmtReject = $connect->prepare($sql_reject);
    if (!$stmtReject) {
        throw new Exception('Prepare reject statement failed: ' . $connect->error . ' | SQL: ' . $sql_reject);
    }
    
    // Step 2: Bind parameters ('s' = string reason, 'i' = integer id)
    if (!$stmtReject->bind_param('si', $catatan, $penarikan['id'])) {  // ✅ DICEK!
        throw new Exception('Bind param failed for reject: ' . $stmtReject->error);
    }
    
    // Step 3: Execute UPDATE with error check
    if (!$stmtReject->execute()) {  // ✅ DICEK! KUNCI!
        $error_msg = 'Execute reject failed: ' . $stmtReject->error;
        error_log('[approve_penarikan REJECT] ' . $error_msg . ' | id=' . $penarikan['id'] . ' | reason=' . $catatan);
        throw new Exception($error_msg);
    }
    
    $affected_rows = $stmtReject->affected_rows;
    $stmtReject->close();
    
    // Step 4: Verify update was successful
    if ($affected_rows <= 0) {
        throw new Exception('Rejection update failed: no rows affected (status may not be pending or record not found)');
    }
    
    // Step 5: Get current balance for rejected withdrawal
    $sql_balance = "SELECT COALESCE(SUM(jumlah),0) AS total_saldo FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?";
    $stmtBalance = $connect->prepare($sql_balance);
    if (!$stmtBalance) {
        throw new Exception('Prepare balance query failed: ' . $connect->error . ' | SQL: ' . $sql_balance);
    }
    
    if (!$stmtBalance->bind_param('ii', $id_tabungan, $id_jenis_tabungan)) {  // ✅ DICEK!
        throw new Exception('Bind param failed for balance query: ' . $stmtBalance->error);
    }
    
    if (!$stmtBalance->execute()) {  // ✅ DICEK!
        throw new Exception('Execute balance query failed: ' . $stmtBalance->error);
    }
    
    $resultBalance = $stmtBalance->get_result();
    $rowBalance = $resultBalance->fetch_assoc();
    $new_saldo = floatval($rowBalance['total_saldo'] ?? 0);
    $stmtBalance->close();

    // Step 6: Create transaction record for audit trail
    $rejectionNote = "Withdrawal rejected: " . ($catatan ?: 'Admin decision');
    $txId = create_withdrawal_transaction_record($connect, $id_tabungan, $id_jenis_tabungan, $jumlah, $penarikan['id'], $rejectionNote);
    if ($txId === false) {
        error_log('[approve_penarikan REJECT] Transaction record creation failed for id=' . $penarikan['id']);
        @file_put_contents(__DIR__ . '/saldo_audit.log', date('c') . " REJECT_PENARIKAN_TX_FAILED user={$id_tabungan} tab_keluar_id={$penarikan['id']}\n", FILE_APPEND);
    }

    $message = "Penarikan ditolak";
    $new_peng_saldo = $saldo_current;
}
```

---

## 🎯 Perubahan Kunci

### 1. **Error Check pada bind_param()**
```php
// Lama:
$stmtReject->bind_param('si', $catatan, $penarikan['id']);

// Baru:
if (!$stmtReject->bind_param('si', $catatan, $penarikan['id'])) {
    throw new Exception('Bind param failed for reject: ' . $stmtReject->error);
}
```

### 2. **Error Check pada execute() - KRITIS!**
```php
// Lama:
$stmtReject->execute();

// Baru:
if (!$stmtReject->execute()) {
    $error_msg = 'Execute reject failed: ' . $stmtReject->error;
    error_log('[approve_penarikan REJECT] ' . $error_msg);
    throw new Exception($error_msg);
}
```

### 3. **Error Check pada SELECT execute()**
```php
// Lama:
$rsn->execute();

// Baru:
if (!$stmtBalance->execute()) {
    throw new Exception('Execute balance query failed: ' . $stmtBalance->error);
}
```

### 4. **Better Error Messages**
```php
// Lama:
throw new Exception('DB prepare failed for reject: ' . $connect->error);

// Baru:
throw new Exception('Prepare reject statement failed: ' . $connect->error . ' | SQL: ' . $sql_reject);
```

### 5. **Structured Code**
- Step-by-step comments untuk clarity
- Setiap error point memiliki specific handling
- SQL queries disimpan di variable untuk debugging

---

## ✅ Hasil yang Diharapkan

### Sebelum Perbaikan:
```
Admin klik "Tolak"
→ Query gagal (error tersembunyi)
→ API response invalid/kosong
→ jQuery .fail() callback
→ UI: "Gagal - Koneksi gagal" (merah)
```

### Setelah Perbaikan:
```
Admin klik "Tolak"
→ Query sukses
→ API response: {"success":true, "message":"Penarikan ditolak", ...}
→ jQuery success callback
→ UI: "Sukses - Penarikan ditolak" (hijau)
→ Database: status = 'rejected', rejected_reason = '...'
```

### Jika Ada Error:
```
Admin klik "Tolak"
→ Query gagal (error terdeteksi)
→ Exception thrown dengan detail error
→ API response: {"success":false, "message":"Execute reject failed: Enum value 'rejected' not found"}
→ UI: "Gagal memproses: Execute reject failed: ..." (merah)
→ Log file: Error detail untuk debugging
```

---

## 🔧 Testing Checklist

- [ ] Clear browser cache (Ctrl+Shift+Delete)
- [ ] Reload halaman admin: `/login/admin/keluar/`
- [ ] Tab "Menunggu" → Findpending withdrawal
- [ ] Klik "Tolak" button
- [ ] Masukkan alasan: "Test rejection"
- [ ] Klik OK
- [ ] Expected: ✅ Green notification "Sukses - Penarikan ditolak"
- [ ] Check database: `SELECT status, rejected_reason FROM tabungan_keluar WHERE id = ...`
- [ ] Verify: status = 'rejected', rejected_reason = 'Test rejection'

---

## 📝 Nota

- **Kode approval (approve):** Tidak berubah
- **Database schema:** Tidak berubah
- **Halaman lain:** Tidak berubah
- **API response format:** Compatibility maintained
- **Transaction handling:** Masih menggunakan begin_transaction() dan commit/rollback

---

**Status:** ✅ READY FOR TESTING
