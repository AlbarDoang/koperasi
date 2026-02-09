# Rejection Flow Diagram & Error Handling Architecture

## Complete Error Handling Flow

```
HTTP POST /approve_penarikan.php
│
├─ Validate POST method
├─ Validate required parameters (no_keluar, action)
├─ Fetch withdrawal record (tabungan_keluar)
├─ Fetch user data (pengguna)
│
└─ TRY BLOCK (Line 154)
   │
   ├─ START TRANSACTION (Line 151)
   │
   ├─ LOCK tabungan_keluar
   ├─ Fetch penarikan data
   ├─ Validate user status (active)
   │
   ├─ IF action == 'approve'
   │  └─ [APPROVE LOGIC - 36 lines]
   │     ├─ Check balance sufficient
   │     ├─ UPDATE tabungan_keluar status='approved'
   │     ├─ UPDATE tabungan_masuk (add debit record)
   │     └─ Calculate new balance
   │
   ├─ ELSE (action == 'reject') ✓ ROBUST
   │  └─ [REJECTION LOGIC - 87 lines with 9 error checks]
   │     │
   │     ├─ STEP 1: Validate Variables
   │     │  ├─ Check penarikan['id'] not empty
   │     │  ├─ Check id_tabungan not empty
   │     │  └─ Check id_jenis_tabungan not empty
   │     │    └─ ✗ FAIL → throw Exception
   │     │
   │     ├─ STEP 2: Prepare UPDATE Statement
   │     │  ├─ $stmtReject = prepare(...)
   │     │  └─ IF stmt === false
   │     │     └─ ✗ FAIL → throw Exception('Prepare UPDATE failed')
   │     │
   │     ├─ STEP 3: Bind UPDATE Parameters
   │     │  ├─ bind_param('si', $catatan, id)
   │     │  └─ IF bind === false
   │     │     ├─ close() statement cleanup
   │     │     └─ ✗ FAIL → throw Exception('Bind parameter failed')
   │     │
   │     ├─ STEP 4: Execute UPDATE
   │     │  ├─ execute()
   │     │  └─ IF execute === false
   │     │     ├─ store error message
   │     │     ├─ close() statement cleanup
   │     │     └─ ✗ FAIL → throw Exception('Execute UPDATE failed')
   │     │
   │     ├─ STEP 5: Verify Affected Rows
   │     │  ├─ affected_rows = get count
   │     │  └─ IF affected_rows <= 0
   │     │     └─ ✗ FAIL → throw Exception('no rows affected')
   │     │
   │     ├─ STEP 6: Get Current Balance (SELECT)
   │     │  ├─ Prepare SELECT query
   │     │  │  └─ IF prepare === false
   │     │  │     └─ ✗ FAIL → throw Exception
   │     │  │
   │     │  ├─ Bind SELECT parameters
   │     │  │  ├─ close() on fail
   │     │  │  └─ IF bind === false
   │     │  │     └─ ✗ FAIL → throw Exception
   │     │  │
   │     │  ├─ Execute SELECT
   │     │  │  ├─ close() on fail
   │     │  │  └─ IF execute === false
   │     │  │     └─ ✗ FAIL → throw Exception
   │     │  │
   │     │  ├─ Get Result
   │     │  │  ├─ close() on fail
   │     │  │  └─ IF get_result === false
   │     │  │     └─ ✗ FAIL → throw Exception
   │     │  │
   │     │  └─ Fetch Row
   │     │     ├─ close() on fail
   │     │     └─ IF fetch_assoc === false
   │     │        └─ ✗ FAIL → throw Exception
   │     │
   │     └─ STEP 7: Create Transaction Record (Optional)
   │        └─ TRY-CATCH (Non-fatal)
   │           ├─ Call create_withdrawal_transaction_record()
   │           │  └─ IF false → error_log (don't throw)
   │           └─ CATCH Exception → error_log (don't throw)
   │
   ├─ COMMIT Transaction (Line 337)
   │
   ├─ Send Notification (Lines 339-361)
   │  └─ TRY-CATCH (Separate, doesn't fail main transaction)
   │
   └─ Return Success JSON (Lines 363-373)
      └─ echo json_encode({
            "success": true,
            "message": "Penarikan ditolak",
            "data": {...}
         })
      
CATCH Exception $e (Line 375)
└─ ROLLBACK Transaction
└─ LOG error to saldo_audit.log
└─ echo json_encode({
      "success": false,
      "message": "Gagal memproses approval: " . $e->getMessage()
   })

RESPONSE (HTTP 200 for both success and error)
├─ Success: {"success": true, "message": "Penarikan ditolak", ...}
└─ Error: {"success": false, "message": "Gagal memproses approval: ..."}

AJAX Handler (.success() called always due to HTTP 200)
├─ Check response.success
├─ If true → Show "Penarikan ditolak" message
└─ If false → Show response.message (error details)
```

---

## Critical Error Handling Points

### 1. **Validation Layer**
```
Purpose: Prevent invalid data processing
Position: Start of rejection block
Variables: penarikan['id'], id_tabungan, id_jenis_tabungan
Response: Exception → JSON error
```

### 2. **Database Operation 1: UPDATE Status**
```
prepare() → Check === false → throw
bind_param() → Check === false → cleanup + throw
execute() → Check === false → cleanup + throw
affected_rows → Check > 0 → throw if 0
```

### 3. **Database Operation 2: SELECT Balance**
```
prepare() → Check === false → throw
bind_param() → Check === false → cleanup + throw
execute() → Check === false → cleanup + throw
get_result() → Check === false → cleanup + throw
fetch_assoc() → Check === false → cleanup + throw
```

### 4. **Non-Critical: Transaction Record**
```
try {
    create_withdrawal_transaction_record()
    if false → error_log
} catch (Exception $e) {
    error_log
}
// Do NOT throw - already committed
```

### 5. **Global Error Catch**
```
ANY Exception → caught at line 375
→ rollback() transaction
→ echo JSON error response
→ Always HTTP 200 (AJAX receives response)
```

---

## Error Response Templates

### Validation Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Validation failed: penarikan id kosong"
}
```

### Prepare Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Prepare UPDATE failed: [SQL Error]"
}
```

### Bind Parameter Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Bind parameter failed: [Bind Error]"
}
```

### Execute Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Execute UPDATE failed: [Execute Error]"
}
```

### No Rows Affected Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Rejection update failed: no rows affected. Status mungkin bukan pending atau record tidak ditemukan."
}
```

### Get Result Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Get result for balance query failed: [Result Error]"
}
```

### Fetch Error
```json
{
    "success": false,
    "message": "Gagal memproses approval: Fetch balance result failed: [Fetch Error]"
}
```

---

## Why This Prevents "Koneksi gagal"

### Before (Broken):
```
Error in rejection → Exception thrown → NOT caught properly
→ No JSON output → PHP outputs error page (HTML)
→ AJAX receives non-JSON response
→ AJAX .success() handler fails to parse
→ Falls through to .error() or connection error
→ User sees "Koneksi gagal"
```

### After (Fixed):
```
Error in rejection → Exception thrown → Caught at line 375
→ Transaction rolled back
→ JSON error response echoed with HTTP 200
→ AJAX .success() handler called (HTTP 200 received)
→ JavaScript checks response.success === false
→ Frontend displays error message from response.message
→ No AJAX error, no "Koneksi gagal"
```

---

## Resource Cleanup Pattern

All prepared statements are properly closed:

```php
$stmt = prepare();        // Resource 1: Statement handle
if (error) close();       // Cleanup on prepare fail

bind_param();
if (error) close();       // Cleanup on bind fail

execute();
if (error) close();       // Cleanup on execute fail

get_result();
if (error) close();       // Cleanup on result fail

$stmt->close();           // Always close after use
```

This prevents:
- Memory leaks
- Connection exhaustion
- Prepared statement cache overflow

---

## Summary Table

| Component | Status | Check Type | Fail Behavior |
|-----------|--------|-----------|---------------|
| Variable Validation | ✅ NEW | explicit | throw Exception |
| prepare() UPDATE | ✅ strict | `=== false` | throw + log |
| bind_param() UPDATE | ✅ strict | `=== false` + close | throw + log |
| execute() UPDATE | ✅ strict | `=== false` + close | throw + log |
| Affected Rows | ✅ NEW | `<= 0` | throw + log |
| prepare() SELECT | ✅ strict | `=== false` | throw + log |
| bind_param() SELECT | ✅ strict | `=== false` + close | throw + log |
| execute() SELECT | ✅ strict | `=== false` + close | throw + log |
| get_result() | ✅ NEW | `=== false` + close | throw + log |
| fetch_assoc() | ✅ NEW | `=== false` + close | throw + log |
| TxRecord Create | ✅ try-catch | Non-fatal | error_log only |
| Global Exception | ✅ exists | top-level | rollback + JSON |
