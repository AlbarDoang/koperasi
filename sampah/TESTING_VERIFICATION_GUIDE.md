# Testing & Verification Guide - Error Debugging

**Status**: ✅ Ready for testing  
**Goal**: Verify error messages now visible and debugging works

---

## Quick Summary of Changes

**File**: `/gas_web/flutter_api/approve_penarikan.php`

**What Changed**:
1. ✅ Error catch block now shows real error (not "Internal server error")
2. ✅ Added `error_log()` PHP logging
3. ✅ Added `error_code` to JSON response
4. ✅ Added `FLUTTER_API_JSON_OUTPUT` flag to prevent fallback

**What You'll See Now**: 
```json
Before: {"success":false,"message":"Internal server error"}
After:  {"success":false,"message":"Gagal memproses reject: [REAL ERROR]","error_code":0}
```

---

## Testing Steps

### Test 1: Normal Rejection (Should Work)

**Setup**:
- Admin is logged in
- Have a pending withdrawal to test

**Steps**:
1. Open `/login/admin/keluar/` (Withdrawal page)
2. Find a pending withdrawal
3. Click "Tolak" button (dropdown menu)
4. Enter reason for rejection
5. Click confirm

**Expected Result**:
- Green notification: "Sukses: Penarikan ditolak"
- Table refreshes
- Status shows "Ditolak"
- Browser console:
  ```
  REJECT SUCCESS: {success:true, message:"Penarikan ditolak", data:{...}}
  ```

**Verify**:
- ✅ AJAX success handler triggered
- ✅ No error message shown
- ✅ Status updated in table

---

### Test 2: Check Debug Logs (When Error Occurs)

**Setup**:
- Same as Test 1, but capture an error

**Steps**:
1. Open DevTools (F12)
2. Go to Console tab
3. Click "Tolak" button
4. Look for console output

**Expected Console Output** (Success):
```javascript
REJECT SUCCESS: {
  success: true,
  message: "Penarikan ditolak",
  data: {
    no_keluar: "TK-20260107101532-1",
    nama: "John Doe",
    jumlah: 100000,
    status: "rejected"
  }
}
```

**Verify**:
- ✅ Console shows full response object
- ✅ Can inspect response.data properties

---

### Test 3: Force an Error (For Debugging Verification)

**Goal**: Verify error messages are now visible

**Step 1: Temporarily break the code**
- Edit `/gas_web/flutter_api/approve_penarikan.php`
- Find rejection logic around line 215
- Change: `$sql_reject = "UPDATE tabungan_keluar SET status = 'rejected'..."`
- To: `$sql_reject = "UPDATE tabungan_keluar SET status_wrong = 'rejected'..."`
- Save file

**Step 2: Test rejection**
1. Click "Tolak" button
2. Enter reason
3. Confirm

**Expected Console Error**:
```javascript
REJECT ERROR - HTTP STATUS: 200
REJECT ERROR - RESPONSE: {
  "success": false,
  "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status_wrong' in 'field list'",
  "error_code": 0
}
```

**Verify**:
- ✅ Shows REAL error, not "Koneksi gagal"
- ✅ Shows REAL error, not "Internal server error"
- ✅ Shows specific column name error
- ✅ Can see error_code
- ✅ HTTP status is 200 (not 500)
- ✅ Response is valid JSON

**Step 3: Fix the code**
- Undo the intentional mistake
- Change back: `$sql_reject = "UPDATE tabungan_keluar SET status = 'rejected'..."`
- Save file

**Step 4: Verify normal operation**
- Click "Tolak" button again
- Should now work normally

---

### Test 4: Check Audit Log

**Goal**: Verify logging works

**Steps**:
1. Trigger an error (follow Test 3 with intentional mistake)
2. Open file: `/gas_web/flutter_api/saldo_audit.log`
3. Look at last line

**Expected Content** (if error occurred):
```
2026-02-07T10:15:32+07:00 APPROVE_PENARIKAN_FAILED action=reject user=123 code=0 err=Prepare UPDATE failed: Unknown column 'status_wrong' in 'field list'
```

**Verify**:
- ✅ Audit log has entry
- ✅ Shows action, user, error
- ✅ Timestamp is current

---

### Test 5: Check PHP Error Log

**Goal**: Verify PHP error logging works

**Steps**:
1. Trigger an error (follow Test 3 with intentional mistake)
2. Open XAMPP logs folder:
   - **Windows**: `C:\xampp\php\logs\php_error.log`
   - **macOS/Linux**: Check XAMPP installation directory
3. Look at last 10 lines

**Expected Content** (if error occurred):
```
[07-Feb-2026 10:15:32 UTC] [approve_penarikan ERROR] action=reject user=123 code=0 msg=Prepare UPDATE failed: Unknown column 'status_wrong' in 'field list'
[07-Feb-2026 10:15:32 UTC] [approve_penarikan TRACE] #0 /xampp/htdocs/gas/gas_web/flutter_api/approve_penarikan.php(215): ... | #1 ...
```

**Verify**:
- ✅ Error logged with [approve_penarikan ERROR] tag
- ✅ Stack trace logged with [approve_penarikan TRACE] tag
- ✅ Timestamp matches when error occurred

---

### Test 6: Check Network Tab

**Goal**: Verify HTTP response is always 200

**Steps**:
1. Open DevTools (F12)
2. Go to Network tab
3. Click "Tolak" button
4. Find `approve_penarikan.php` request
5. Click on it to inspect

**Expected** (Success case):
```
Status: 200 OK
Response:
{
  "success": true,
  "message": "Penarikan ditolak",
  "data": {...}
}
```

**Expected** (Error case):
```
Status: 200 OK
Response:
{
  "success": false,
  "message": "Gagal memproses reject: Prepare UPDATE failed: ...",
  "error_code": 0
}
```

**Verify**:
- ✅ Status is ALWAYS 200 (not 500)
- ✅ Response is valid JSON both success and error cases
- ✅ Content-Type: application/json in headers

---

### Test 7: Approval Button (For Consistency)

**Goal**: Verify approval also has improved error handling

**Steps**:
1. Have a pending withdrawal
2. Click "Setujui" button
3. Check DevTools console

**Expected** (Success):
```javascript
APPROVE SUCCESS: {success:true, message:"Penarikan berhasil disetujui", data:{...}}
```

**Verify**:
- ✅ Approval also has enhanced logging
- ✅ Same error handling pattern as rejection

---

## Debugging Checklist

When troubleshooting errors:

- [ ] Open DevTools (F12)
- [ ] Go to Console tab
- [ ] Click "Tolak" or "Setujui"
- [ ] Look for `REJECT SUCCESS:` or `REJECT ERROR:` message
- [ ] If error, read the message carefully (not generic "Koneksi gagal")
- [ ] Note the exact error text
- [ ] Check `/gas_web/flutter_api/saldo_audit.log` for audit trail
- [ ] Check `C:\xampp\php\logs\php_error.log` for PHP errors
- [ ] Fix the issue based on error message
- [ ] Test again

---

## Error Categories

### Category 1: Validation Error
```json
{
  "success": false,
  "message": "Gagal memproses reject: Validation failed: penarikan id kosong",
  "error_code": 0
}
```
**Fix**: Ensure frontend sends all required parameters

### Category 2: Database Column Error
```json
{
  "success": false,
  "message": "Gagal memproses reject: Prepare UPDATE failed: Unknown column 'status' in field list",
  "error_code": 0
}
```
**Fix**: Verify database table has required columns

### Category 3: SQL Syntax Error
```json
{
  "success": false,
  "message": "Gagal memproses reject: Execute UPDATE failed: You have an error in your SQL syntax",
  "error_code": 0
}
```
**Fix**: Check SQL query for syntax issues

### Category 4: Row Not Found
```json
{
  "success": false,
  "message": "Gagal memproses reject: Rejection update failed: no rows affected",
  "error_code": 0
}
```
**Fix**: Verify withdrawal exists and status is 'pending'

---

## Success Verification Checklist

After changes, verify:

- [ ] Normal rejection works
- [ ] "Tolak" button shows green success notification
- [ ] Browser console shows detailed response object
- [ ] Error case shows real error message (not "Koneksi gagal")
- [ ] Error case shows real error message (not "Internal server error")
- [ ] Error case shows error_code in response
- [ ] HTTP status is always 200 (success and error)
- [ ] Response is always valid JSON
- [ ] Audit log has entries
- [ ] PHP error log shows stack traces
- [ ] Approval button also works (bonus test)

---

## Before vs After Comparison

| Scenario | Before | After |
|----------|--------|-------|
| **Normal Rejection** | Works | ✅ Works |
| **Console Message** | Generic | ✅ Detailed |
| **Error Case** | "Koneksi gagal" | ✅ Real error shown |
| **HTTP Status** | Maybe 500 | ✅ Always 200 |
| **Debug Info** | Hard to find | ✅ Easy to find |
| **Audit Log** | Sometimes logged | ✅ Always logged |
| **PHP Error Log** | Maybe logged | ✅ Always logged with trace |
| **error_code** | Not present | ✅ Included in response |

---

## Quick Start Testing

**Fastest way to test**:

1. Open browser DevTools (F12)
2. Go to Console tab
3. Go to `/login/admin/keluar/`
4. Click "Tolak" button on any pending
5. Enter reason
6. Check console output:
   - Success: `REJECT SUCCESS: {...}`
   - Error: `REJECT ERROR: {...}` with message

**That's it!** 🎉

---

## Rollback (If needed)

To revert changes:
1. Edit `/gas_web/flutter_api/approve_penarikan.php`
2. Restore catch block to original (remove error_log, error_code, etc.)
3. Remove FLUTTER_API_JSON_OUTPUT flag

**But you shouldn't need to** - changes are fully backward compatible!

---

## Support

If tests fail:

1. **No console output appears**:
   - Check browser console is actually showing (F12 → Console)
   - Try hard refresh (Ctrl+Shift+R)

2. **Still seeing "Koneksi gagal"**:
   - Check AJAX code was updated (verify file changed)
   - Check backend error logs

3. **Still seeing "Internal server error"**:
   - Check FLUTTER_API_JSON_OUTPUT flag is set
   - Check no unwanted output before JSON

4. **HTTP 500 error**:
   - Fatal PHP error somewhere
   - Check php_error.log for clues
   - Reload page or restart Apache

---

## Summary

You now have **3-layer debugging** ready:

1. **Frontend Console** - Quick debugging
2. **Audit Log** - Application history
3. **PHP Error Log** - Deep technical details

All errors are **visible** (not hidden with fallback).

All responses are **valid JSON** with **HTTP 200**.

You can now **debug issues quickly** with real error messages!

Ready to test? 🚀
