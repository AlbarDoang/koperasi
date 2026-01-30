# Quick Reference: Approval/Rejection API

## 📌 One-Page Quick Guide

### Endpoint
```
POST /flutter_api/approve_penarikan.php
```

---

## 👍 Approve Withdrawal (Setujui Pencairan)

### Request
```php
$_POST = [
    'no_keluar'   => '42',              // ID dari tabungan_keluar
    'action'      => 'approve',         // Action: approve
    'approved_by' => 1,                 // ID admin
    'catatan'     => ''                 // Optional
];
```

### What Happens
1. ✅ Lock row (prevent double-approval)
2. ✅ Deduct from `tabungan_masuk` (savings)
3. ✅ Update `tabungan_keluar.status = 'approved'`
4. ✅ Credit to `pengguna.saldo` (wallet)
5. ✅ Insert notification: "Pencairan Disetujui"

### Response
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

### User Gets
- Notification: "Pencairan Disetujui"
- Message: "Pencairan sebesar Rp 100.000 dari Tabungan Qurban telah disetujui dan dikreditkan ke saldo bebas Anda. Saldo bebas saat ini: Rp 250.000."
- Wallet saldo increased: Rp 250.000 (was Rp 150.000)

---

## 👎 Reject Withdrawal (Tolak Pencairan)

### Request
```php
$_POST = [
    'no_keluar'   => '43',              // ID dari tabungan_keluar
    'action'      => 'reject',          // Action: reject
    'approved_by' => 1,                 // ID admin
    'catatan'     => 'Data tidak lengkap'  // Rejection reason
];
```

### What Happens
1. ✅ Update `tabungan_keluar.status = 'rejected'`
2. ✅ Save reason in `tabungan_keluar.rejected_reason`
3. ✅ **NO changes to savings** (user keeps their money)
4. ✅ Insert notification: "Pencairan Ditolak"

### Response
```json
{
  "success": true,
  "message": "Penarikan ditolak",
  "data": {
    "no_keluar": "43",
    "nama": "John Doe",
    "jumlah": 100000,
    "saldo_baru": 550000,
    "saldo_dashboard": 150000,
    "status": "rejected"
  }
}
```

### User Gets
- Notification: "Pencairan Ditolak"
- Message: "Pencairan sebesar Rp 100.000 dari Tabungan Qurban ditolak. Alasan: Data tidak lengkap"
- Wallet saldo: **Unchanged** (still Rp 150.000)
- Savings: **Unchanged** (still Rp 550.000) - can try again!

---

## ⚠️ Error Responses

### Insufficient Balance
```json
{
  "success": false,
  "message": "Gagal memproses approval: Saldo tabungan tidak mencukupi atau data berubah"
}
```

### Withdrawal Already Processed
```json
{
  "success": false,
  "message": "Data penarikan tidak ditemukan atau sudah diproses"
}
```

### User Not Found
```json
{
  "success": false,
  "message": "Data anggota tidak ditemukan"
}
```

### Invalid Input
```json
{
  "success": false,
  "message": "Field [field_name] wajib diisi"
}
```

---

## 🗄️ Database Flow

### APPROVE Flow
```
START TRANSACTION
├─ Lock tabungan_keluar row
├─ SELECT tabungan_masuk WHERE user_id AND jenis_id
│  └─ SUM(jumlah) >= requested_amount ✓
├─ UPDATE tabungan_masuk SET jumlah = jumlah - amount
│  └─ Deduct oldest first (FIFO)
├─ UPDATE tabungan_keluar SET status='approved'
├─ UPDATE pengguna SET saldo = saldo + amount
├─ INSERT notifikasi (type='withdrawal_approved')
└─ COMMIT
```

### REJECT Flow
```
START TRANSACTION
├─ Lock tabungan_keluar row
├─ UPDATE tabungan_keluar SET status='rejected', rejected_reason=?
├─ INSERT notifikasi (type='withdrawal_rejected')
└─ COMMIT
```

---

## 🔒 Safety Features

| Feature | Benefit |
|---------|---------|
| **Row Locking** | Prevents double-approval |
| **Prepared Statements** | SQL Injection protection |
| **Transaction/Rollback** | All-or-nothing consistency |
| **Balance Validation** | Never overdraw savings |
| **Deduplication** | No duplicate notifications |
| **Audit Logging** | Full compliance trail |

---

## 📋 Notification Details

### Approval Notification
```
Type: withdrawal_approved
Title: Pencairan Disetujui
Message: Pencairan sebesar Rp {amount} dari {jenis_name} telah 
         disetujui dan dikreditkan ke saldo bebas Anda. 
         Saldo bebas saat ini: Rp {new_saldo}.
Data: {
  "tabungan_keluar_id": 42,
  "jenis_name": "Tabungan Qurban",
  "amount": 100000,
  "new_saldo": 250000,
  "status": "approved"
}
```

### Rejection Notification
```
Type: withdrawal_rejected
Title: Pencairan Ditolak
Message: Pencairan sebesar Rp {amount} dari {jenis_name} ditolak. 
         Alasan: {reason}
Data: {
  "tabungan_keluar_id": 43,
  "jenis_name": "Tabungan Qurban",
  "amount": 100000,
  "reason": "Data tidak lengkap",
  "status": "rejected"
}
```

---

## 🧪 Testing Command

```bash
# Run comprehensive test
php /flutter_api/test_approve_reject_flow.php

# Check audit logs
tail -f /flutter_api/saldo_audit.log
tail -f /flutter_api/notification_filter.log
```

---

## 📱 Mobile Integration Example

### Swift (iOS)
```swift
let request = URLRequest(url: URL(string: "https://api.example.com/approve_penarikan.php")!)
request.httpMethod = "POST"
request.httpBody = "no_keluar=42&action=approve&approved_by=1".data(using: .utf8)

URLSession.shared.dataTask(with: request) { data, response, error in
    if let data = data {
        if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
           let success = json["success"] as? Bool, success {
            // Show success notification
            DispatchQueue.main.async {
                print("Approval successful")
                // Update UI
            }
        }
    }
}.resume()
```

### JavaScript/React
```javascript
async function approveWithdrawal(no_keluar, adminId) {
  const formData = new FormData();
  formData.append('no_keluar', no_keluar);
  formData.append('action', 'approve');
  formData.append('approved_by', adminId);
  
  const response = await fetch('/flutter_api/approve_penarikan.php', {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  
  if (result.success) {
    console.log('Approved:', result.data);
    // Update UI with new saldo
  } else {
    console.error('Approval failed:', result.message);
  }
}
```

---

## 🎯 Key Takeaways

1. **Approve:** Deducts savings → Credits wallet → Sends notification
2. **Reject:** Only updates status → Saves reason → Sends notification  
3. **Safe:** Transaction rollback on any error
4. **Fast:** Lock only on specific row, not whole table
5. **Logged:** Every operation auditable

---

## 📞 Support

- **Implementation Guide:** `APPROVAL_IMPLEMENTATION_GUIDE.md`
- **Verification Report:** `APPROVAL_VERIFICATION_REPORT.md`
- **Test Suite:** `test_approve_reject_flow.php`
- **API File:** `approve_penarikan.php`
- **Helpers:** `notif_helper.php`, `ledger_helpers.php`

---

**Version:** 1.0  
**Status:** ✅ Production Ready  
**Last Updated:** 2026-01-25
