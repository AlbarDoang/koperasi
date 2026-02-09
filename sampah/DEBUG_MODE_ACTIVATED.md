# DEBUGGING MODE ACTIVATED - Error Details Now Visible

**Files Modified**:
- `/gas_web/flutter_api/approve_penarikan.php`
- `/gas_web/flutter_api/connection.php`

**Status**: ✅ DEBUG MODE ENABLED

---

## Changes Summary

### 1. approve_penarikan.php - Top of File
```php
// ENABLE DEBUGGING
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
$GLOBALS['FLUTTER_API_DEBUG_MODE'] = true;
```

### 2. connection.php - Error Handler
- Check `FLUTTER_API_DEBUG_MODE` flag
- If enabled: Show errors instead of "Internal server error"
- Custom error handler for PHP warnings/errors
- Shutdown function respects debug mode

### 3. approve_penarikan.php - Main Catch Block
Now returns:
```json
{
    "success": false,
    "message": "Gagal memproses reject: [REAL ERROR MESSAGE]",
    "error_code": 0,
    "error_file": "/gas_web/flutter_api/approve_penarikan.php",
    "error_line": 215,
    "error_trace": "[stack trace with spaces for JSON]"
}
```

---

## Testing Now

### Step 1: Test Rejection
```
1. Open browser
2. Go to /login/admin/keluar/
3. Click "Tolak" button
4. Enter reason
5. Check browser console (F12 → Console)
```

### Step 2: Check Console Output

**Expected Success**:
```javascript
REJECT SUCCESS: {
  success: true,
  message: "Penarikan ditolak",
  data: {...}
}
```

**Expected Error** (if database issue):
```javascript
REJECT ERROR - HTTP STATUS: 200
REJECT ERROR - RESPONSE: {
  "success": false,
  "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status' in field list",  ← REAL ERROR
  "error_code": 0,
  "error_file": "/gas_web/flutter_api/approve_penarikan.php",
  "error_line": 215,
  "error_trace": "..."
}
```

### Step 3: Check Logs

**Audit Log** (`/gas_web/flutter_api/saldo_audit.log`):
```
2026-02-07T10:15:32+07:00 APPROVE_PENARIKAN_FAILED action=reject user=123 code=0 err=Prepare UPDATE failed: ... file=/gas_web/flutter_api/approve_penarikan.php:215
```

**PHP Error Log** (`C:\xampp\php\logs\php_error.log`):
```
[07-Feb-2026 10:15:32] [approve_penarikan ERROR] action=reject user=123 code=0 msg=Prepare UPDATE failed: ... file=/gas_web/flutter_api/approve_penarikan.php line=215
[07-Feb-2026 10:15:32] [approve_penarikan TRACE] #0 /gas/approve_penarikan.php(215): ... | #1 ...
```

---

## What's Different Now?

| Before | After |
|--------|-------|
| HTTP 500 error | HTTP 200 always |
| "Internal server error" | Real error message |
| Hidden error details | Full error details (file, line, trace) |
| Hard to debug | Easy to debug |
| Output buffer swallows errors | Debug mode shows them |

---

## Error Locations to Check

If still having issues, check these in order:

1. **Browser Console** (F12 → Console)
   - Shows error from response
   - Easiest place to start

2. **API Debug Log** (`/gas_web/flutter_api/api_debug.log`)
   - Shows buffer handling
   - Tells you if fallback was triggered

3. **Audit Log** (`/gas_web/flutter_api/saldo_audit.log`)
   - Shows all approvals/rejections
   - Useful for tracking

4. **PHP Error Log** (`C:\xampp\php\logs\php_error.log`)
   - Shows PHP errors
   - Useful for syntax issues

5. **Network Tab** (F12 → Network)
   - See actual HTTP response
   - Should always be 200 in debug mode

---

## Debug Mode Flag Flow

```
approve_penarikan.php
  ↓
Set $GLOBALS['FLUTTER_API_DEBUG_MODE'] = true
  ↓
Include connection.php
  ↓
connection.php checks flag
  ↓
If DEBUG_MODE true:
  ├─ Enable display_errors
  ├─ Set custom error_handler
  ├─ Shutdown function shows errors instead of fallback
  └─ Bypass "Internal server error" response
```

---

## Important

⚠️ **DEBUG MODE is enabled!** 

This should only be for debugging. When done:
1. Test everything thoroughly
2. Fix any actual errors
3. Consider disabling debug mode for production
4. Or keep it for development convenience

To disable later: Remove or comment out debug setting in approve_penarikan.php

---

## Quick Test Checklist

- [ ] Browser opens DevTools without issue
- [ ] Can see Console tab
- [ ] Click "Tolak" button
- [ ] See error in console (if error occurs) or success message
- [ ] Error message is detailed, not generic
- [ ] HTTP status shown is 200
- [ ] Response is valid JSON

---

## Next: Test & Report

Please test now and report:
1. What error message you see in console
2. Full response JSON from browser console
3. Any file paths or line numbers mentioned
4. Whether it's different from "Internal server error"

This will help identify the actual problem!
