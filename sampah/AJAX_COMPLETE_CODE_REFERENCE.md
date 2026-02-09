# AJAX Complete Code - Approval & Rejection Handlers

**File**: `/gas_web/login/admin/keluar/index.php` (Lines 234-287)  
**Status**: ✅ IMPLEMENTED - Ready for testing

---

## Complete AJAX Code - Approval Handler

```javascript
// Approve (supports both legacy .approve-btn and new .action-approve dropdown item)
$(document).on('click', '.approve-btn, .action-approve', function(e){
  e.preventDefault();
  var no = $(this).data('no') || $(this).data('id');
  if(!no) return;
  if(!confirm('Setujui penarikan ' + no + ' ?')) return;
  
  $.ajax({
    url: '/gas/gas_web/flutter_api/approve_penarikan.php',
    type: 'POST',
    data: { no_keluar: no, action: 'approve', approved_by: ADMIN_ID },
    dataType: 'json',
    success: function(response) {
      console.log('APPROVE SUCCESS:', response);
      if(response && response.success){
        $.growl.notice({ title: 'Sukses', message: response.message });
        dataTable.ajax.reload(null, false);
      } else {
        $.growl.error({ title: 'Gagal', message: (response && response.message) ? response.message : 'Gagal memproses' });
      }
    },
    error: function(xhr) {
      console.log("APPROVE ERROR - HTTP STATUS:", xhr.status);
      console.log("APPROVE ERROR - RESPONSE:", xhr.responseText);
      $.growl.error({ title: 'Gagal', message: 'Server Error: ' + xhr.status });
    }
  });
});
```

---

## Complete AJAX Code - Rejection Handler

```javascript
// Reject (supports both legacy .reject-btn and new .action-reject dropdown item)
$(document).on('click', '.reject-btn, .action-reject', function(e){
  e.preventDefault();
  var no = $(this).data('no') || $(this).data('id');
  if(!no) return;
  var reason = prompt('Alasan penolakan (opsional):');
  if (reason === null) return; // user cancelled
  
  $.ajax({
    url: '/gas/gas_web/flutter_api/approve_penarikan.php',
    type: 'POST',
    data: { no_keluar: no, action: 'reject', approved_by: ADMIN_ID, catatan: reason },
    dataType: 'json',
    success: function(response) {
      console.log('REJECT SUCCESS:', response);
      if(response && response.success){
        $.growl.notice({ title: 'Sukses', message: response.message });
        dataTable.ajax.reload(null, false);
      } else {
        $.growl.error({ title: 'Gagal', message: (response && response.message) ? response.message : 'Gagal memproses' });
      }
    },
    error: function(xhr) {
      console.log("REJECT ERROR - HTTP STATUS:", xhr.status);
      console.log("REJECT ERROR - RESPONSE:", xhr.responseText);
      $.growl.error({ title: 'Gagal', message: 'Server Error: ' + xhr.status });
    }
  });
});
```

---

## Console Output - Debugging Examples

### Success Case (Rejection Approved):
```
REJECT SUCCESS: {
  success: true,
  message: "Penarikan ditolak",
  data: {
    no_keluar: "TAB-202601-001",
    nama: "John Doe",
    jumlah: 100000,
    saldo_baru: 900000,
    saldo_dashboard: 1000000,
    status: "rejected"
  }
}
```

### Error Case (Validation Failed):
```
REJECT ERROR - HTTP STATUS: 200
REJECT ERROR - RESPONSE: {
  "success": false,
  "message": "Gagal memproses approval: Validation failed: penarikan id kosong"
}
```

### Backend Error (Prepare Failed):
```
REJECT ERROR - HTTP STATUS: 200
REJECT ERROR - RESPONSE: {
  "success": false,
  "message": "Gagal memproses approval: Prepare UPDATE failed: Unknown column 'status' in 'field list'"
}
```

### Network Error (HTTP 500):
```
REJECT ERROR - HTTP STATUS: 500
REJECT ERROR - RESPONSE: (Connection refused OR PHP Fatal Error HTML)
```

---

## AJAX Parameter Details

### Approval Request:
```javascript
{
  no_keluar: "TAB-202601-001",           // Withdrawal ID from data-id
  action: "approve",                      // Action type
  approved_by: ADMIN_ID                   // Current admin user ID
}
```

### Rejection Request:
```javascript
{
  no_keluar: "TAB-202601-001",           // Withdrawal ID from data-id
  action: "reject",                       // Action type
  approved_by: ADMIN_ID,                  // Current admin user ID
  catatan: "Alasan penolakan dari admin"  // Rejection reason (optional)
}
```

---

## Handler Flow Diagram

### Success Handler (approval):
```
success(response)
  ↓
console.log('APPROVE SUCCESS:', response)
  ↓
if (response && response.success) ?
  ├─ YES (true):
  │   ├─ $.growl.notice({ Sukses })
  │   └─ dataTable.ajax.reload()
  │
  └─ NO (false):
      └─ $.growl.error({ response.message })
```

### Error Handler (approval):
```
error(xhr)
  ↓
console.log("HTTP STATUS:", xhr.status)
console.log("RESPONSE:", xhr.responseText)
  ↓
$.growl.error({ 'Server Error: ' + xhr.status })
```

---

## Key Features

### 1. Explicit Configuration
```javascript
url: '/gas/gas_web/flutter_api/approve_penarikan.php',  // Clear URL
type: 'POST',                                             // Explicit method
data: { ... },                                            // Explicit params
dataType: 'json'                                          // JSON auto-parse
```

### 2. Debug Logging
```javascript
console.log('REJECT SUCCESS:', response);        // Show response object
console.log("HTTP STATUS:", xhr.status);         // Show HTTP code
console.log("REJECT ERROR - RESPONSE:", ...);    // Show response text
```

### 3. Response Handling
```javascript
// Check success flag
if(response && response.success){
  // Action succeeded
  $.growl.notice({ message: response.message });
  dataTable.ajax.reload();
} else {
  // Action failed but HTTP 200
  $.growl.error({ message: response.message });
}
```

### 4. Error Handling
```javascript
error: function(xhr) {
  // Shows HTTP status + response text
  // Helps identify real problem (not generic "Koneksi gagal")
}
```

---

## No Manual JSON.parse() Needed

**Important**: Since `dataType: 'json'` is used:

✅ **Correct** - jQuery auto-parses:
```javascript
success: function(response) {
  console.log(response);  // Already parsed JavaScript object
  console.log(response.success);  // Can access properties directly
}
```

❌ **Wrong** - Manual parse not needed:
```javascript
success: function(response) {
  var obj = JSON.parse(response);  // Don't do this! Already parsed
}
```

---

## Testing Scenarios

### Scenario 1: Successful Rejection
```
1. Click "Tolak" button
2. Enter reason: "Not meeting requirements"
3. Backend processes successfully
4. Console shows:
   REJECT SUCCESS: {success: true, message: "Penarikan ditolak", ...}
5. Green notification appears
6. Table refreshes
7. Status column shows "Ditolak"
```

### Scenario 2: Rejection with Empty Reason
```
1. Click "Tolak" button
2. Press Enter (or just accept empty)
3. catatan parameter = "" (empty string sent)
4. Backend receives it as empty rejection reason
5. Still processes successfully
6. Same success flow as Scenario 1
```

### Scenario 3: Backend Validation Error
```
1. Click "Tolak" button
2. Backend validation fails (e.g., duplicate rejection)
3. Backend returns HTTP 200 + JSON error:
   {success: false, message: "Rejection update failed: no rows affected"}
4. Console shows:
   REJECT SUCCESS: {success: false, message: "..."}
5. Red notification: "Gagal: Rejection update failed: ..."
6. Table does NOT refresh
7. User can retry
```

### Scenario 4: Backend Error (HTTP 500)
```
1. Click "Tolak" button
2. Backend throws unhandled exception
3. Returns HTTP 500 or malformed JSON
4. AJAX error() handler triggered
5. Console shows:
   REJECT ERROR - HTTP STATUS: 500
   REJECT ERROR - RESPONSE: (error HTML or text)
6. Red notification: "Gagal: Server Error: 500"
7. User knows to contact admin
```

### Scenario 5: Network/Connection Error
```
1. Click "Tolak" button
2. Network disconnected during request
3. AJAX error() handler triggered
4. xhr.status might be 0
5. Console shows:
   REJECT ERROR - HTTP STATUS: 0
   REJECT ERROR - RESPONSE: (empty or error message)
6. Red notification: "Gagal: Server Error: 0"
```

---

## Browser DevTools Debugging

### Console Tab:
- Klik tombol "Tolak"
- Lihat `REJECT SUCCESS:` atau `REJECT ERROR -` message
- Shows exact response

### Network Tab:
1. Open F12 → Network
2. Klik tombol "Tolak"
3. Find `approve_penarikan.php` request
4. Check:
   - **Status**: Should be 200
   - **Response tab**: Should show valid JSON
   - **Headers**: Content-Type should be application/json

### Example Network Response:
```json
{
  "success": true,
  "message": "Penarikan ditolak",
  "data": {
    "no_keluar": "TAB-202601-001",
    "status": "rejected"
  }
}
```

---

## Backward Compatibility

| Item | Old Code | New Code | Compatible |
|------|----------|----------|-----------|
| API Endpoint | Same | Same | ✅ Yes |
| Parameters | Same | Same | ✅ Yes |
| Response Format | Same | Same | ✅ Yes |
| HTTP Status | Same | Same | ✅ Yes |
| JavaScript Framework | jQuery | jQuery | ✅ Yes |
| Browser Support | All | All | ✅ Yes |

---

## Comparison: Old vs New

| Aspect | Old ($.post) | New ($.ajax) |
|--------|------------|-----------|
| **Lines of Code** | 9 lines | 15 lines |
| **Debugging Easy** | ❌ Hard | ✅ Easy |
| **Error Details** | ❌ None | ✅ HTTP + Response |
| **Console Output** | ❌ None | ✅ Detailed logs |
| **maintainability** | ❌ Generic | ✅ Explicit |
| **Error Message** | "Koneksi gagal" | "Server Error: [code]" |
| **Production Ready** | ❌ Poor errors | ✅ Good errors |

---

## Summary

✅ **Approval & Rejection AJAX updated to $.ajax() format**
- Explicit URL, method, dataType configuration
- Debug console.log in success() and error()
- HTTP status and response text visible in error case
- No manual JSON.parse() needed (auto via dataType)
- Backend compatible (no changes needed)
- User gets detailed error messages instead of generic "Koneksi gagal"

✅ **Ready to test and deploy**
