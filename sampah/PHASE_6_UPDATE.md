# PHASE 6 UPDATE: API Debugging - Comprehensive Request/Response Logging

**Date:** January 18, 2026  
**Status:** ✅ COMPLETE  
**Focus:** Add detailed logging to debug "Tidak dapat menjangkau server" error

---

## 🎯 Objective
Enable complete visibility into API requests and responses to identify exactly where the "Tidak dapat menjangkau server" connection error originates.

---

## 📝 Files Modified

### 1. **lib/config/http_client.dart**
- **Before:** Basic logging (1-2 lines per request)
- **After:** Comprehensive logging with:
  - ISO8601 timestamps
  - Full request details (URL, method, headers, body)
  - Full response details (status, headers, body)
  - JSON parsing with fallback to raw body
  - Detailed error codes
  - Visual separators for readability

### 2. **lib/page/daftar/register1.dart**
- **Added import:** `import 'dart:async';` (for TimeoutException)
- **Enhanced ping check:** (Lines 200-232)
  - Logs ping URL and timeout
  - Logs response status code
  - Separate SocketException, TimeoutException, and generic exception handlers
  - Each logs error code/details
- **Enhanced register API call:** (Lines 247-290)
  - Logs exact URL being called
  - Logs all request fields (with password hidden)
  - Logs complete response with JSON parsing attempt
  - Visual separators for readability
- **Enhanced error handling:** (Lines 335-376)
  - Separate catch blocks for SocketException, TimeoutException, HttpException
  - Each logs detailed error information

### 3. **lib/page/daftar/register2.dart**
- **Added import:** `import 'dart:io';` (for SocketException)
- **Enhanced uploadAndSave method:**
  - Detailed multipart request logging
  - File path logging
  - Response body with JSON parsing
  - SocketException with error codes
  - Raw response body display if JSON parse fails
  - Timestamp tracking

---

## 📊 Logging Details

### Console Output Format

#### Pre-flight Check (Ping)
```
================================================================================
[2026-01-18T14:09:20.456789] 🔍 PRE-FLIGHT: CHECKING SERVER REACHABILITY
   Ping URL: http://192.168.1.27/gas/gas_web/flutter_api/ping
   Timeout: 4s
   Status Code: 200
   Status Reason: OK
   Response Length: 4 bytes
================================================================================
```

#### Main Request
```
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
   Body (JSON): {"success":true,"id_pengguna":"12345","message":"OK"}
================================================================================
```

#### Error Scenarios

**SocketException:**
```
❌ SOCKET EXCEPTION during ping: Connection refused
   Error Code: ECONNREFUSED
```

**TimeoutException:**
```
❌ TIMEOUT during ping: Timed out after 0:00:04.000000
```

**HTTP Error:**
```
❌ STATUS CODE BUKAN 200: 500
   Response: <html><head>Internal Server Error</head>...
```

---

## ✅ Benefits

1. **Exact URL Visibility:** See exactly which URL is being called
   - Identifies dobel endpoints (e.g., `flutter_api/flutter_api/register`)
   - Verifies base URL is correct

2. **Request Format Verification:** See exact data being sent
   - Field names and values
   - Request method (POST vs GET)
   - Content-Type header

3. **Response Handling:** See exact response received
   - Status code (200 vs 500, etc.)
   - Response headers
   - Response body (JSON or raw)

4. **Error Classification:** Different error types logged separately
   - SocketException: Network/firewall issue
   - TimeoutException: Backend slow/offline
   - HTTP status codes: Backend response issue
   - JSON parsing errors: Response format issue

5. **Debugging Information:** Error codes and details
   - `errno`/`errorCode` for network errors
   - Response body for HTTP errors
   - Stack traces in catch blocks

---

## 🔄 Testing Instructions

### 1. Clean and Build
```bash
cd c:\xampp\htdocs\gas\gas_mobile
flutter clean
flutter pub get
```

### 2. Run on Device
```bash
flutter run
```

### 3. Perform Action
- Navigate to Registration or Activation page
- Fill in form
- Submit

### 4. Check Console
- Look for request logging with timestamps
- Identify error type (Socket, Timeout, HTTP, JSON parse)
- Trace exact point of failure

### 5. Share Logs
- Copy console output
- Include the detailed logging to help debug

---

## 📋 Files Created/Modified Summary

| File | Change | Lines Changed |
|------|--------|----------------|
| lib/config/http_client.dart | Enhanced logging | ~50 → ~170 |
| lib/page/daftar/register1.dart | Added logging, fixed imports | Added ~100+ lines |
| lib/page/daftar/register2.dart | Added dart:io import | +1 line |
| API_DEBUGGING_GUIDE.md | NEW - Comprehensive guide | Created |

---

## 🚀 Next Steps

1. **Deploy App:** `flutter run` on physical device
2. **Test Flows:** Try registration, activation, forgot password
3. **Monitor Logs:** Watch console for request/response details
4. **Identify Issue:** Exact error type and location will be visible
5. **Fix Backend:** Based on logged information, adjust PHP code or network config

---

## ✨ Quality Checklist

- ✅ All imports correct (dart:io, dart:async, dart:convert)
- ✅ No compilation errors
- ✅ Console logging uses ISO8601 timestamps
- ✅ Request body shows all fields (password hidden)
- ✅ Response shows status, headers, body
- ✅ JSON parsing attempts with fallback to raw
- ✅ Error handling differentiates exception types
- ✅ Error codes/details logged
- ✅ Visual separators for readability
- ✅ `flutter clean; flutter pub get` succeeds
- ✅ No breaking changes to existing functionality

---

## 📞 Support

If you see errors in the console logs, refer to `API_DEBUGGING_GUIDE.md` for:
- Explanation of each error type
- Common causes
- Solutions for each scenario

**Total Time Saved:** With this detailed logging, you'll identify the exact issue in minutes instead of hours!
