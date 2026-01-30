# API Debugging Guide - Flutter Mobile App

## Overview
This document describes the comprehensive API logging enhancements made to help debug the "Tidak dapat menjangkau server" error.

---

## 📊 Changes Made

### 1. **lib/config/http_client.dart** ✅ ENHANCED
**Purpose:** Central HTTP request/response wrapper with detailed logging

**Enhancements:**
- Added ISO8601 timestamps to all log entries
- Full request details: URL, method (GET/POST), headers, complete request body
- Full response details: status code, reason phrase, headers, body length
- Intelligent response parsing:
  - Attempts JSON parsing
  - Falls back to raw body display (first 500 chars + indicator if truncated)
- Enhanced error messages with error codes
- Visual separators (80-char lines) for console readability

**Key Features:**
```
================================================================================
[2026-01-18T14:09:30.123456] 📤 POST REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Method: POST
   Headers: {Content-Type: application/x-www-form-urlencoded}
   Body: {id_pengguna: 12345, name: John}
   Timeout: 30s

📥 RESPONSE RECEIVED:
   Status Code: 200
   Status Reason: OK
   Headers: {...}
   Body Length: 150 bytes
   Body (JSON): {"success":true,"message":"OK"}
================================================================================
```

---

### 2. **lib/page/daftar/register1.dart** ✅ ENHANCED
**Purpose:** Registration tahap 1 with detailed request/response logging

**Enhancements:**

#### Pre-flight Server Check (Lines 200-232)
- Log ping URL being checked
- Log status code and timeout for ping request
- Detailed error messages for network issues:
  - `SocketException`: Network unreachable (errno/errorCode logged)
  - `TimeoutException`: Server offline or slow
  - Other exceptions: Type and message logged

#### Main Register API Call (Lines 247-290)
- Log exact URL being called
- Log all form fields (with password hidden for security)
- Log timeout setting (30s)
- Log complete response: status code, headers, body length
- JSON parsing with fallback to raw body if not JSON
- Success/failure categorization with full response data

#### Error Handling (Lines 335-376)
- Separate catch blocks for each exception type:
  - `SocketException`: Address, port, error code
  - `TimeoutException`: Explicit timeout notification
  - `HttpException`: HTTP protocol errors
  - Generic exceptions: Type and message

---

### 3. **lib/page/daftar/register2.dart** ✅ ENHANCED
**Purpose:** Registration tahap 2 with file upload and comprehensive logging

**Enhancements:**
- Added `dart:io` import for SocketException support
- Multipart request logging:
  - File path logging for uploads
  - Form fields logging
  - Complete request details before sending
- Response handling:
  - JSON parsing attempt
  - Raw body fallback if parse fails
  - Status code validation
- Detailed error handling:
  - SocketException with error codes
  - Timeout exceptions
  - HTTP status errors
  - JSON parsing errors with raw body display

---

## 🔧 How to Use This Debugging Info

### Step 1: Deploy App with Enhanced Logging
```bash
cd c:\xampp\htdocs\gas\gas_mobile
flutter clean
flutter pub get
flutter run
```

### Step 2: Monitor Console Output
When using the app (Register, Activate, etc.), the console will show:
1. **Pre-flight ping**: Reachability check to API
2. **Main request**: Exact data being sent to server
3. **Response**: Exact data received from server or error details

### Step 3: Analyze Logs
Look for:
- ✅ **Correct URL?** Should be `http://192.168.1.27/gas/gas_web/flutter_api/...`
- ✅ **Correct method?** POST or GET (check your endpoint)
- ✅ **Correct body format?** PHP expects `$_POST['field']` access
- ✅ **Status code?** 200 = success, others = error
- ✅ **Response type?** JSON or raw text?
- ✅ **Error type?** Socket, Timeout, HTTP, JSON parsing?

---

## 🎯 Expected Console Output Examples

### ✅ Successful Registration (TAHAP 1)
```
================================================================================
[2026-01-18T14:09:20.456789] 🔍 PRE-FLIGHT: CHECKING SERVER REACHABILITY
   Ping URL: http://192.168.1.27/gas/gas_web/flutter_api/ping
   Timeout: 4s
   Status Code: 200
   Status Reason: OK
   Response Length: 4 bytes
================================================================================

================================================================================
[2026-01-18T14:09:25.123456] 📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Method: POST (MultipartRequest)
   Fields to send:
      no_hp: 081234567890
      kata_sandi: [HIDDEN]
      nama_lengkap: John Doe
      alamat_domisili: Jl. Main Street
      tanggal_lahir: 1990-05-15
      setuju_syarat: 1
   Timeout: 30s

📥 RESPONSE RECEIVED:
   Status Code: 200
   Status Reason: OK
   Response Headers: {content-type: application/json}
   Body Length: 85 bytes
   Body (JSON): {"success":true,"id_pengguna":"12345","message":"Registration successful"}
================================================================================

✅✅✅ REGISTER TAHAP 1 SUKSES! Akan tampil notifikasi HIJAU
```

### ❌ Server Unreachable (SOCKET ERROR)
```
================================================================================
[2026-01-18T14:09:30.789012] 🔍 PRE-FLIGHT: CHECKING SERVER REACHABILITY
   Ping URL: http://192.168.1.27/gas/gas_web/flutter_api/ping
   Timeout: 4s

❌ SOCKET EXCEPTION during ping: Connection refused
   Error Code: ECONNREFUSED
================================================================================

Toast Message: "Tidak dapat menjangkau server. Pastikan perangkat dan komputer dev 
              berada di jaringan yang sama dan server API (Apache) sedang berjalan."
```

### ⏱️ Timeout (SERVER TOO SLOW)
```
================================================================================
[2026-01-18T14:09:30.456789] 🔍 PRE-FLIGHT: CHECKING SERVER REACHABILITY
   Ping URL: http://192.168.1.27/gas/gas_web/flutter_api/ping
   Timeout: 4s

❌ TIMEOUT during ping: Timed out after 0:00:04.000000
================================================================================

Toast Message: "Timeout saat menghubungi server. Server mungkin sedang offline 
              atau terlalu lambat."
```

### 🚫 HTTP Error (e.g., 500 Server Error)
```
================================================================================
[2026-01-18T14:09:25.123456] 📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Method: POST (MultipartRequest)
   Fields to send: [...]
   Timeout: 30s

📥 RESPONSE RECEIVED:
   Status Code: 500
   Status Reason: Internal Server Error
   Response Headers: {content-type: text/html}
   Body Length: 1245 bytes
   Body (RAW - Not JSON): <html><head><title>500 Internal Server Error</title></head>...
================================================================================

Toast Message: "Server Error: 500
               <html><head>..."
```

### 🔄 Backend Logic Error (status 200 but success=false)
```
[2026-01-18T14:09:25.123456] 📤 REGISTER TAHAP 1 REQUEST
   ...
   
📥 RESPONSE RECEIVED:
   Status Code: 200
   Status Reason: OK
   Response Headers: {...}
   Body Length: 150 bytes
   Body (JSON): {"success":false,"message":"No HP sudah terdaftar"}
================================================================================

❌ REGISTER TAHAP 1 GAGAL! Response tidak success:
   Full Response: {success: false, message: "No HP sudah terdaftar"}

Toast Message: "No HP sudah terdaftar"
```

---

## 🔍 Debugging Checklist

### Network Configuration ✓
- [ ] Backend running: `http://192.168.1.27` (verify in your network)
- [ ] Android device on same network as backend
- [ ] `AndroidManifest.xml` has `android:usesCleartextTraffic="true"`
- [ ] `network_security_config.xml` whitelists `192.168.1.27`
- [ ] INTERNET permission in `AndroidManifest.xml`

### API Endpoint Configuration ✓
- [ ] Base URL in `lib/config/api.dart` is correct: `http://192.168.1.27/gas/gas_web/flutter_api`
- [ ] Endpoint paths don't have dobel "flutter_api" (e.g., `flutter_api/flutter_api/register`)
- [ ] PHP endpoints expect POST for registration (check `$_POST` usage)

### Request Format ✓
- [ ] Request body matches PHP `$_POST` expectations
- [ ] Content-Type header: `application/x-www-form-urlencoded` or `multipart/form-data`
- [ ] Required fields all present (no null/empty)
- [ ] Date format correct (YYYY-MM-DD for backend)

### Response Handling ✓
- [ ] Backend returns valid JSON (not HTML error page)
- [ ] Success response has `success: true` or `status: success`
- [ ] Error response has meaningful `message` field
- [ ] No unexpected HTML in response (check for errors logged on server)

---

## 📝 Server-Side Debugging

### Check PHP Endpoint
```php
// Add to top of register_tahap1.php for logging
error_log('[' . date('Y-m-d H:i:s') . '] REQUEST: ' . print_r($_REQUEST, true));
error_log('[' . date('Y-m-d H:i:s') . '] POST: ' . print_r($_POST, true));
error_log('[' . date('Y-m-d H:i:s') . '] FILES: ' . print_r($_FILES, true));
```

### Check Response Format
```php
// Ensure response is JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Registration successful',
    'id_pengguna' => $id,
]);
```

---

## 🚀 Testing Flow

1. **Test Connectivity First**
   - Open `http://192.168.1.27` in browser (device + dev computer)
   - Should see XAMPP/Apache landing page

2. **Test Ping Endpoint**
   - Open `http://192.168.1.27/gas/gas_web/flutter_api/ping`
   - Should return status 200 with "pong" or similar

3. **Test Registration (Manual)**
   - Use Postman/Insomnia or curl
   - POST to `http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1`
   - Include fields: `no_hp`, `kata_sandi`, `nama_lengkap`, `alamat_domisili`, `tanggal_lahir`, `setuju_syarat`

4. **Test Registration (App)**
   - Run app with `flutter run`
   - Check console logs
   - Compare actual request vs. manual test

5. **Analyze Differences**
   - If manual works but app doesn't: Check request format differences
   - If both fail: Check server-side (PHP errors, database issues)
   - If ping fails: Check network connectivity

---

## 📞 Common Issues & Solutions

| Issue | Symptom | Solution |
|-------|---------|----------|
| Network unreachable | `SocketException` with `ECONNREFUSED` | Check backend running, same network |
| Wrong URL | Correct error but wrong endpoint | Check `api.dart` base URL |
| Cleartext disabled | Connection refused even with correct IP | Add `network_security_config.xml` |
| Timeout | `⏱️ Request timeout` | Server is slow or down, increase timeout |
| JSON parse error | "Body (RAW - Not JSON)" | Backend returning HTML error page |
| 500 error | Status code 500 | Check PHP logs for backend errors |
| Field mismatch | Backend error "field not found" | Check field names in request vs. PHP `$_POST` |

---

## ✅ Ready to Test!

All logging enhancements are in place. The next step is:

1. Run `flutter run` on your Android device
2. Perform a registration or activation action
3. Check the console output for detailed request/response information
4. Share console logs if issues persist

**The logs will show exactly what's being sent and received, making debugging much easier!**
