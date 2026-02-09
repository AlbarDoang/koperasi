# Comprehensive Notification Detail Debug Guide

**Status**: Logging added across all layers to trace notification flow end-to-end.

## Summary
Added extensive debug logging to trace why notification messages may not include `jenis_tabungan` and `amount` details:

### 1. Backend Approval Handler
**File**: `admin_verifikasi_mulai_nabung.php` (line 108-111)
**What it logs**: When admin approves/rejects, extracts `jenis_tabungan` from database
```
[admin_verifikasi_mulai_nabung] EXTRACTED: id={id} jenis_tabungan=({jenis_tabungan}) jumlah={jumlah} status={action}
WARNING: jenis_tabungan is EMPTY for id={id}, fallback to Tabungan Reguler
```
**File location**: `gas_web/api_debug.log`

### 2. Transaction API Response
**File**: `get_riwayat_transaksi.php` (lines 94-134)
**What it logs**: For each API response, traces:
- ID extraction from keterangan field regex
- Database lookup of jenis_tabungan
- Final value before returning to Flutter
```
[DEBUG] TX {id}: Extracted id_mulai_nabung={id} from: {keterangan}
[DEBUG] TX {id} (mulai_id={id}): Found jenis_tabungan={jenis_tabungan}
[DEBUG] FINAL TX {id}: jenis_tabungan="{jenis_tabungan}" status={status}
```
**File location**: `gas_web/flutter_api/api_debug.log`

### 3. Message Construction
**File**: `notif_helper.php` (lines 141-177)
**What it logs**: During notification message construction:
```
[create_mulai_nabung_notification] CALLED: user={uid} mulai_id={id} status={status} jumlah={amount} jenis_tabungan={type}
[create_mulai_nabung_notification] BUILT: jenis_display='{type}' amount_text='{amount_text}'
[create_mulai_nabung_notification] FINAL MESSAGE[{status}]: '{message}' (jenis='{type}' amount='{amount_text}')
```
**File location**: `gas_web/flutter_api/api_debug.log`

### 4. Database Insert
**File**: `notif_helper.php` (line 111)
**What it logs**: Confirms what was actually stored in database:
```
[notif_helper] NOTIF_CREATED id={notif_id} user={uid} title={title} msg='{message}' data={json_data}
```

### 5. Database Retrieval
**File**: `get_notifications.php` (lines 88-99)
**What it logs**: When API returns notifications to Flutter:
```
[get_notifications] TX_NOTIF: id={notif_id} title={title} msg={message} jenis={data.jenis_tabungan} amount={data.amount}
```

### 6. Flutter API Response Processing
**File**: `riwayat.dart` - `_checkPendingTopups()` method (lines 347-379)
**What it logs**: 
```
[_checkPendingTopups] API response received: {meta}
[_checkPendingTopups] First TX from API: id={id} jenis_tabungan="{type}" jumlah={amount}
[_checkPendingTopups] NEW TX from API: id={id} status={status}
[_checkPendingTopups] API TX keys: {all_keys}
[_checkPendingTopups] jenis_tabungan from API: "{type}"
```

### 7. Flutter Notification Creation
**File**: `riwayat.dart` - Lines 428-437
**What it logs**: Final notification before saving to SharedPreferences:
```
[Riwayat] TX ID={id}: jenis_tabungan="{type}" (type: {dartType})
[Riwayat] Full entry keys: {all_keys}
[Riwayat] FINAL MESSAGE: {message}
[_checkPendingTopups] ✓ Notifikasi created. Data: {jenis_tabungan: {type}, amount: {amount}, status: {status}}
```

### 8. Local Storage
**File**: `notifikasi_helper.dart` - `addLocalNotification()` (lines 461-464)
**What it logs**:
```
[NotifikasiHelper] addLocalNotification stored: {title}
[NotifikasiHelper] last_local_notif: {json}
```

---

## Testing Steps

### STEP 1: Clear All Old Notifications
```bash
# Option A: Uninstall app from device/emulator
# Option B: Clear app data/cache
# Option C: Delete SharedPreferences entry via database
```

### STEP 2: Submit New Setoran
1. Open Flutter app
2. Go to Setoran page
3. Submit NEW setoran (different from previous tests):
   - **Jenis Tabungan**: Select specific type (e.g., "Tabungan Pelajar", "Tabungan Qurban")
   - **Amount**: Use specific number (e.g., Rp 250.000)
4. Note the `id_mulai_nabung` from REST response (if visible in developer console)

### STEP 3: Monitor Logs While Approving
Open terminal and watch logs while admin approves:
```bash
# Terminal 1: Watch backend logs
tail -f c:\xampp\htdocs\gas\gas_web\flutter_api\api_debug.log

# Terminal 2: Watch error log
tail -f c:\xampp\htdocs\gas\gas_web\flutter_api\admin_verifikasi_error.log

# Terminal 3: Watch filter log
tail -f c:\xampp\htdocs\gas\gas_web\flutter_api\notification_filter.log
```

### STEP 4: Check Backend Logs
After admin approval, check log for sequence:

**Example sequence to look for:**
```
[admin_verifikasi_mulai_nabung] EXTRACTED: id=123 jenis_tabungan=(Tabungan Pelajar) jumlah=250000 status=berhasil
[create_mulai_nabung_notification] CALLED: user=456 mulai_id=123 status=berhasil jumlah=250000 jenis_tabungan=Tabungan Pelajar
[create_mulai_nabung_notification] BUILT: jenis_display='Tabungan Pelajar' amount_text=' sebesar Rp 250.000'
[create_mulai_nabung_notification] FINAL MESSAGE[berhasil]: 'Pengajuan Setoran Tabungan Pelajar Anda sebesar Rp 250.000 disetujui, silahkan cek saldo di halaman Tabungan'
[notif_helper] NOTIF_CREATED id=789 user=456 title=Setoran Tabungan Disetujui msg='Pengajuan Setoran Tabungan Pelajar Anda sebesar Rp 250.000 disetujui...'
```

### STEP 5: Check What API Returns
```bash
curl -X POST http://localhost/gas/gas_web/flutter_api/get_notifications.php \
  -d "id_pengguna=456" | jq '.data[] | select(.title | contains("Setoran"))'
```

Expected JSON in response:
```json
{
  "message": "Pengajuan Setoran Tabungan Pelajar Anda sebesar Rp 250.000 disetujui, silahkan cek saldo di halaman Tabungan",
  "data": {
    "jenis_tabungan": "Tabungan Pelajar",
    "amount": 250000,
    "status": "berhasil"
  }
}
```

### STEP 6: Check Flutter Logs
Watch Flutter console output:
```
[_checkPendingTopups] API response received: ...
[_checkPendingTopups] First TX from API: id=123 jenis_tabungan="Tabungan Pelajar" jumlah=250000
[Riwayat] TX ID=123: jenis_tabungan="Tabungan Pelajar" (type: String)
[Riwayat] FINAL MESSAGE: Pengajuan Setoran Tabungan Pelajar Anda sebesar Rp 250.000 disetujui, silahkan cek saldo di halaman Tabungan
[_checkPendingTopups] ✓ Notifikasi created. Data: {jenis_tabungan: Tabungan Pelajar, amount: 250000, status: berhasil}
```

### STEP 7: Check Notification Display
1. Open app notification area
2. Find "Setoran Tabungan Disetujui" notification
3. **Expected**: Message should be complete: "Pengajuan Setoran Tabungan Pelajar Anda sebesar Rp 250.000 disetujui, silahkan cek saldo di halaman Tabungan"
4. **NOT**: "Pengajuan Setoran Anda disetujui..."

---

## Troubleshooting

### Issue: `jenis_tabungan` Empty in Backend Log
**Log**: `WARNING: jenis_tabungan is EMPTY for id=123`
**Cause**: Data not in `mulai_nabung` table
**Fix**: 
```sql
SELECT * FROM mulai_nabung WHERE id_mulai_nabung = 123;
```
Check if `jenis_tabungan` column has data.

### Issue: `jenis_tabungan` Not in API Response
**Check**: `get_riwayat_transaksi.php` log
**Expected log**: `[DEBUG] FINAL TX 123: jenis_tabungan="Tabungan Pelajar"`
**If missing**: 
- Regex extraction failed: Check `keterangan` field format
- Database lookup failed: Tables may not be synced

### Issue: Notification Shows Old Message
**Cause**: Old notification cached in SharedPreferences
**Fix**: 
```dart
// Clear in app code or database:
final prefs = await SharedPreferences.getInstance();
await prefs.remove('notifications');
await prefs.remove('last_local_notif');
```
Then restart app and test with brand new approval.

### Issue: API Response Has jenis_tabungan But Flutter Shows Generic Message
**Cause**: FrontEnd not extracting from API response
**Check**: `riwayat.dart` - `_checkPendingTopups()` log for:
```
[Riwayat] TX ID=123: jenis_tabungan="Tabungan Pelajar"
```
If missing: Entry keys don't have `jenis_tabungan` - check API response structure.

---

## Key Files to Monitor

| File | Purpose | Log File |
|------|---------|----------|
| `admin_verifikasi_mulai_nabung.php` | Extract jenis_tabungan | `api_debug.log` |
| `notif_helper.php` | Build message | `api_debug.log` |
| `get_riwayat_transaksi.php` | API response | `api_debug.log` |
| `get_notifications.php` | Retrieve from DB | PHP error_log |
| `riwayat.dart` | Process API in Flutter | Console (debugPrint) |
| `notifikasi_helper.dart` | Save to SharedPrefs | Console (debugPrint) |

---

## Expected Final Outcome
After test with NEW approval:
1. ✅ Backend logs show complete jenis_tabungan extraction
2. ✅ `get_riwayat_transaksi.php` returns jenis_tabungan in JSON
3. ✅ `get_notifications.php` logs transak values
4. ✅ Flutter riwayat logs show data received from API
5. ✅ Notification displays: "Pengajuan Setoran [Jenis] Anda sebesar Rp [Amount] [status]"
6. ✅ Transaction detail page shows jenis_tabungan and amount with correct formatting

**All logs should trace the complete flow and show where (if any) data is lost.**
