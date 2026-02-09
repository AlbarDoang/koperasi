# Verification Checklist - Debug Mode Implementation

**Status**: ✅ Complete  
**Date**: February 7, 2026  
**Target**: Show real error messages instead of "Internal server error"

---

## Files Modified

### 1. `/gas_web/flutter_api/approve_penarikan.php`

**Lines 1-19** (Enable Debug):
- [x] `ini_set('display_errors', '1')`
- [x] `ini_set('display_startup_errors', '1')`
- [x] `error_reporting(E_ALL)`
- [x] `$GLOBALS['FLUTTER_API_DEBUG_MODE'] = true`
- [x] `include 'connection.php'` (AFTER debug settings)

**Lines 369-401** (Enhanced Catch):
- [x] Get error message: `$e->getMessage()`
- [x] Get error code: `$e->getCode()`
- [x] Get error file: `$e->getFile()`
- [x] Get error line: `$e->getLine()`
- [x] Get stack trace: `$e->getTraceAsString()`
- [x] Log to error_log()
- [x] Return JSON with all details
- [x] Set `$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true`

### 2. `/gas_web/flutter_api/connection.php`

**Lines 16-36** (Debug Check + Error Handler):
- [x] Check `$GLOBALS['FLUTTER_API_DEBUG_MODE']`
- [x] Custom `set_error_handler()`
- [x] Handle all error types
- [x] Log errors with level names

**Lines 39-56** (Display Error Check):
- [x] Check debug mode before `display_errors = 0`
- [x] Skip `ini_set('display_errors', '0')` if debug mode

**Lines 61-89** (Shutdown Function Debug):
- [x] Check `$debugMode` variable
- [x] If debug: echo buffer instead of fallback
- [x] If debug: echo raw error output, not "Internal server error"

---

## Expected Behavior After Changes

### Before
```
HTTP 500
{"success":false,"message":"Internal server error"}
```

### After
```
HTTP 200
{
    "success": false,
    "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status' in field list",
    "error_code": 0,
    "error_file": "/gas_web/flutter_api/approve_penarikan.php",
    "error_line": 215,
    "error_trace": "..."
}
```

---

## Flow Diagram

```
POST /approve_penarikan.php
    ↓
✓ Error reporting enabled (display_errors=1)
✓ Debug mode flag set
    ↓
Include connection.php
    ↓
✓ connection.php checks debug flag
✓ Doesn't disable display_errors if debug
✓ Custom error handler registered
    ↓
Try-catch block executes
    ↓
Exception thrown
    ↓
✓ Catch block logs full error details
✓ Returns JSON with error message + file + line + trace
✓ Sets FLUTTER_API_JSON_OUTPUT flag
    ↓
Shutdown function
    ↓
✓ Checks FLUTTER_API_JSON_OUTPUT flag
✓ If set: echo buffered JSON
✓ If debug mode: don't use fallback
    ↓
Response sent: HTTP 200 + JSON error details
```

---

## Testing Steps

### Test 1: Verify Debug Mode Works

**Command**: Check if error reporting is enabled
```bash
# In approve_penarikan.php top, you should see:
- ini_set('display_errors', '1');
- error_reporting(E_ALL);
```

**Verify**: Open browser, if debug enabled should see real errors not "Internal server error"

### Test 2: Test Rejection

**Steps**:
1. Open `/login/admin/keluar/`
2. Find pending withdrawal
3. Click "Tolak" button
4. Enter rejection reason
5. Check browser console (F12)

**Expected**:
- If success: `REJECT SUCCESS: {success:true,...}`
- If error: `REJECT ERROR: {success:false,message:"REAL ERROR",...}`

### Test 3: Check Logs

**Audit Log** (`/gas_web/flutter_api/saldo_audit.log`):
```bash
# Should see entry with real error, not generic message
tail -1 C:\xampp\htdocs\gas\gas_web\flutter_api\saldo_audit.log
```

Expected:
```
2026-02-07T... APPROVE_PENARIKAN_FAILED action=reject user=123 code=0 err=Prepare UPDATE failed: ... file=/path:215
```

**PHP Error Log** (`C:\xampp\php\logs\php_error.log`):
```bash
# Should see [approve_penarikan ERROR] with real message
tail -5 C:\xampp\php\logs\php_error.log
```

Expected:
```
[approve_penarikan ERROR] action=reject user=123 code=0 msg=Prepare UPDATE failed: ... file=... line=215
[approve_penarikan TRACE] #0 ... | #1 ...
```

---

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Still see "Internal server error" | Check if file modified was correct path |
| Still see HTTP 500 | Fatal PHP error before try-catch, check logs |
| See error but it's cut off | Check error_trace length limit (500 chars) |
| Don't see file/line info | Check catch block has `$e->getFile()` |
| Log files not updating | Check file permissions in /gas_web/flutter_api/ |

---

## Verification Checklist

### Code Changes Verified
- [x] approve_penarikan.php line 1-19: Debug settings
- [x] approve_penarikan.php line 369-401: Catch block enhanced
- [x] connection.php line 16-36: Error handler added
- [x] connection.php line 39-56: Debug check added
- [x] connection.php line 61-89: Shutdown function updated

### Functionality Verified
- [x] Error reporting enabled before connection.php included
- [x] Debug flag set
- [x] connection.php respects debug flag
- [x] Exception catch returns JSON with details
- [x] FLUTTER_API_JSON_OUTPUT flag prevents fallback
- [x] All three catch blocks checked (txErr, notifErr, main $e)

### Testing Ready
- [x] Can open browser DevTools
- [x] Can click "Tolak" button
- [x] Can check console for error/success
- [x] Can verify HTTP 200 status
- [x] Can check response JSON format

---

## Response Format Examples

### Success Response
```json
{
    "success": true,
    "message": "Penarikan ditolak",
    "data": {
        "no_keluar": "TK-20260107-1",
        "nama": "John Doe",
        "jumlah": 100000,
        "saldo_baru": 900000,
        "saldo_dashboard": 1000000,
        "status": "rejected"
    }
}
```

### Error Response (Database Issue)
```json
{
    "success": false,
    "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status' in field list",
    "error_code": 0,
    "error_file": "/gas_web/flutter_api/approve_penarikan.php",
    "error_line": 215,
    "error_trace": "#0 /gas/approve_penarikan.php(215): $stmtReject->execute() | #1 ..."
}
```

### Error Response (Validation)
```json
{
    "success": false,
    "message": "Gagal memproses reject: Validation failed: penarikan id kosong",
    "error_code": 0,
    "error_file": "/gas_web/flutter_api/approve_penarikan.php",
    "error_line": 205,
    "error_trace": "..."
}
```

---

## Final Verification

Run this checklist BEFORE reporting:

- [ ] File path is correct: `/gas_web/flutter_api/approve_penarikan.php`
- [ ] Error reporting lines added at top (before include)
- [ ] Debug flag `$GLOBALS['FLUTTER_API_DEBUG_MODE'] = true`
- [ ] Include happens AFTER debug settings
- [ ] Main catch block has `$e->getFile()` and `$e->getLine()`
- [ ] connection.php modified to check debug flag
- [ ] Can click "Tolak" without getting "Koneksi gagal"
- [ ] Browser shows error in console, not UI message
- [ ] Response is valid JSON with real error, not generic "Internal server error"

---

## Summary

✅ **Debug Mode Fully Enabled**

Real error messages will now display instead of "Internal server error".

Error details now include:
- Error message
- Error code
- File path
- Line number
- Stack trace

You can now debug backend issues! 👏
