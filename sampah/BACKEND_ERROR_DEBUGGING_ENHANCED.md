# Backend Error Debugging - Enhanced Error Handling

**File**: `/gas_web/flutter_api/approve_penarikan.php`  
**Status**: ✅ UPDATED - Error messages now visible for debugging

---

## Changes Made

### 1. Enhanced Error Catch Block (Lines 358-380)

**Before**:
```php
} catch (Exception $e) {
    $connect->rollback();
    @file_put_contents(__DIR__ . '/saldo_audit.log', 
        date('c') . " APPROVE_PENARIKAN_FAILED user={$id_tabungan} err=" . $e->getMessage() . "\n", 
        FILE_APPEND);
    echo json_encode(array(
        "success" => false,
        "message" => "Gagal memproses approval: " . $e->getMessage()
    ));
}
```

**After**:
```php
} catch (Exception $e) {
    $connect->rollback();
    
    // Multiple logging for debugging
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $errorTrace = $e->getTraceAsString();
    
    // PHP error log (visible in php_error.log)
    error_log("[approve_penarikan ERROR] action={$action} user={$id_tabungan} code={$errorCode} msg={$errorMsg}");
    error_log("[approve_penarikan TRACE] " . str_replace("\n", " | ", $errorTrace));
    
    // Audit log file (local logging)
    @file_put_contents(__DIR__ . '/saldo_audit.log', 
        date('c') . " APPROVE_PENARIKAN_FAILED action={$action} user={$id_tabungan} code={$errorCode} err={$errorMsg}\n", 
        FILE_APPEND);
    
    // Signal to connection.php that we're outputting JSON (prevents fallback)
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
    
    // API response - now shows actual error
    echo json_encode(array(
        "success" => false,
        "message" => "Gagal memproses {$action}: " . $errorMsg,
        "error_code" => $errorCode
    ));
}
```

### 2. Success Response Flag (Line 343)

Added signal to prevent unwanted output buffer fallback:
```php
// Signal to connection.php that we're outputting JSON
$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;

echo json_encode(array(
    "success" => true,
    ...
));
```

---

## Error Response Examples

### Before (Hidden Error):
```json
{
    "success": false,
    "message": "Internal server error"
}
```
**Problem**: No idea what actually failed

### After (Real Error):
```json
{
    "success": false,
    "message": "Gagal memproses reject: Prepare UPDATE failed: You have an error in your SQL syntax",
    "error_code": 0
}
```
**Better**: Clear error message for debugging

---

## Debugging Locations

### 1. **Frontend Console** (DevTools → Console)
AJAX error handler shows error message:
```javascript
console.log("REJECT ERROR - RESPONSE:", xhr.responseText);
// Output: {"success":false,"message":"Gagal memproses reject: Prepare UPDATE failed: ..."}
```

### 2. **PHP Error Log** (php_error.log)
Located in XAMPP logs folder:
```
[07-Feb-2026 10:15:32 UTC] [approve_penarikan ERROR] action=reject user=123 code=0 msg=Prepare UPDATE failed: syntax error in WHERE clause
[07-Feb-2026 10:15:32 UTC] [approve_penarikan TRACE] #0 /gas/approve_penarikan.php(215): throw new Exception | #1 /gas/approve_penarikan.php(205): prepare() ...
```

### 3. **Audit Log** (`/gas_web/flutter_api/saldo_audit.log`)
Local debug log in API folder:
```
2026-02-07T10:15:32+07:00 APPROVE_PENARIKAN_FAILED action=reject user=123 code=0 err=Prepare UPDATE failed: syntax error in WHERE clause
```

### 4. **API Debug Log** (`/gas_web/flutter_api/api_debug.log`)
Connection buffer debug info (from connection.php):
```
2026-02-07T10:15:32+07:00 [connection.php] Output flag present, echoing buffered output for approve_penarikan.php POST /gas/gas_web/flutter_api/approve_penarikan.php 192.168.1.100: ...
```

---

## Three-Layer Logging

Now all errors are logged in **3 places**:

### Layer 1: Frontend (Browser Console)
- **Visibility**: Developer + Admin
- **Detail**: Error message in JSON response
- **Use**: First place to check errors

### Layer 2: PHP Error Log (php_error.log)
- **Visibility**: Server admin only
- **Detail**: Full stack trace + error code
- **Use**: Deep debugging, system-level issues

### Layer 3: Audit Log (saldo_audit.log)
- **Visibility**: Application log
- **Detail**: Action, user, error message
- **Use**: Business logic debugging

---

## Response Format

### Success:
```json
HTTP 200
{
    "success": true,
    "message": "Penarikan ditolak",
    "data": {
        "no_keluar": "TK-20260107101532-1",
        "nama": "John Doe",
        "jumlah": 100000,
        "saldo_baru": 900000,
        "saldo_dashboard": 1000000,
        "status": "rejected"
    }
}
```

### Error (with detailed message):
```json
HTTP 200
{
    "success": false,
    "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status' in 'field list'",
    "error_code": 0
}
```

---

## Debugging Workflow

When "Koneksi gagal" or error occurs:

### Step 1: Check Frontend Console
```javascript
// F12 → Console tab
// Look for:
REJECT ERROR - HTTP STATUS: 200
REJECT ERROR - RESPONSE: {"success":false,"message":"Gagal memproses reject: [REAL ERROR]"}
```

### Step 2: Copy Error Message
Example error from response:
```
"Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status_lama' in field list"
```

### Step 3: Check Audit Log
```bash
# SSH to server or open file manager
# Go to: /gas_web/flutter_api/saldo_audit.log
tail -20 saldo_audit.log

# Look for:
2026-02-07T10:15:32+07:00 APPROVE_PENARIKAN_FAILED action=reject user=123 code=0 err=Prepare UPDATE failed: Unknown column...
```

### Step 4: Check PHP Error Log
```bash
# On XAMPP Windows:
# C:\xampp\apache\logs\error.log
# or
# C:\xampp\php\logs\php_error.log

tail -30 C:\xampp\php\logs\php_error.log

# Look for:
[approve_penarikan ERROR] action=reject user=123 code=0 msg=Prepare UPDATE failed: ...
[approve_penarikan TRACE] #0 /gas/approve_penarikan.php(215): throw new Exception | ...
```

### Step 5: Fix Database/Code Issue
Based on error message, fix:
- Missing database columns
- Wrong SQL syntax
- Type mismatches
- etc.

---

## Common Errors & Solutions

### Error: "Prepare UPDATE failed: Unknown column 'rejected_reason'"
**Cause**: Column doesn't exist in `tabungan_keluar` table  
**Solution**: Run migration to add column
```sql
ALTER TABLE tabungan_keluar ADD COLUMN rejected_reason VARCHAR(255);
```

### Error: "Prepare UPDATE failed: ENUM value invalid"
**Cause**: ENUM value mismatch (e.g., 'reject' vs 'rejected')  
**Solution**: Fix ENUM in code or database
```sql
ALTER TABLE tabungan_keluar MODIFY COLUMN status ENUM('pending','approved','rejected');
```

### Error: "Validation failed: penarikan id kosong"
**Cause**: Validation caught missing variable  
**Solution**: Ensure frontend sends all required parameters

### Error: "Rejection update failed: no rows affected"
**Cause**: Withdrawal already processed or doesn't exist  
**Solution**: Verify withdrawal exists and status is 'pending'

---

## Flag Explanation: FLUTTER_API_JSON_OUTPUT

The `$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;` flag:

**Purpose**: Signal to connection.php output buffer handler
**Effect**: Prevents fallback "Internal server error" response
**Location**: Set just before `echo json_encode()`
**Why needed**: connection.php has safety fallback that replaces unexpected output with "Internal server error"

**Without flag**:
- If any unexpected output exists → fallback triggered
- User sees "Internal server error" (generic, unhelpful)
- Can't see real error

**With flag**:
- Connection.php knows we're outputting JSON
- Doesn't apply fallback
- User sees real error message

---

## Testing Error Debugging

### Test 1: Force Database Error
Temporarily modify SQL in reject block:
```php
// Rename column to cause error
$sql_reject = "UPDATE tabungan_keluar SET status_wrong = 'rejected' ...";
```
Expected response:
```json
{
    "success": false,
    "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status_wrong' in field list",
    "error_code": 0
}
```

### Test 2: Check All Logs
After triggering an error:
1. Browser console → See error message ✓
2. saldo_audit.log → See audit trail ✓
3. php_error.log → See stack trace ✓
4. api_debug.log → See buffer info ✓

### Test 3: Verify HTTP 200 Always
Click "Tolak" and check:
```
Network tab → approve_penarikan.php
Status: 200 (not 500) ✓
Response: Valid JSON ✓
```

---

## Summary

✅ **Enhanced error debugging with 3-layer logging**:
- Frontend shows detailed error message
- PHP error log shows stack trace
- Audit log shows action history

✅ **FLUTTER_API_JSON_OUTPUT flag prevents fallback**:
- connection.php won't hide real errors
- User always sees JSON response
- No more generic "Internal server error"

✅ **Error code included for categorization**:
- `{"error_code": 0}` for exception errors
- Can extend for specific error categories

✅ **Action specified in error message**:
- "Gagal memproses reject: ..." vs
- "Gagal memproses approve: ..."
- Helps identify which operation failed

---

## Files Modified

- `/gas_web/flutter_api/approve_penarikan.php` (Lines 340-380)
  - Added error logging (3 locations)
  - Added FLUTTER_API_JSON_OUTPUT flag
  - Added error_code to response
  - Added action specification to messages

---

## Next Steps for User

1. **Click "Tolak" button** to trigger rejection
2. **Check browser console** (F12) for error message
3. **Report error message** from console
4. **Fix issue** based on error details
5. **Test again** - should now see proper error or success

Now you can see **real errors** instead of "Internal server error"! 🎯
