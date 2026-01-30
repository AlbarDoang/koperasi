# Quick Log Pattern Reference

Use this guide to quickly identify issues from console logs.

---

## ✅ SUCCESS Pattern (Status Code 200 + success=true)

```
📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Status Code: 200
   Body (JSON): {"success":true,"message":"OK","id_pengguna":"12345"}
```

**What to do:** User will see green toast notification "Pendaftaran tahap 1 berhasil" and proceed to next screen.

---

## ❌ Network Error Pattern (SocketException)

```
❌ SOCKET EXCEPTION during ping: Connection refused
   Error Code: ECONNREFUSED
```

OR

```
❌ SOCKET EXCEPTION: Network is unreachable
   Error Code: ENETUNREACH
```

**What to do:**
- Check if backend is running: `http://192.168.1.27` in browser
- Check if device is on same network as backend
- Check firewall settings
- Verify `AndroidManifest.xml` has `android:usesCleartextTraffic="true"`
- Verify `network_security_config.xml` exists and whitelists `192.168.1.27`

---

## ⏱️ Timeout Pattern (TimeoutException)

```
❌ TIMEOUT during ping: Timed out after 0:00:04.000000
```

OR (for main request)

```
❌ TIMEOUT EXCEPTION: TimeoutException after 0:00:30.000000
```

**What to do:**
- Backend is running but responding very slowly
- Check backend performance: Is PHP slow? Is database locked?
- Increase timeout value if backend legitimately needs more time
- Check server logs for errors

---

## 🚫 HTTP Error Pattern (Non-200 Status Code)

```
❌ STATUS CODE BUKAN 200: 500
   Response: Internal Server Error
```

OR

```
📥 RESPONSE RECEIVED:
   Status Code: 404
   Body (RAW - Not JSON): <!DOCTYPE html>
   <html>
   <head><title>404 Not Found</title></head>
```

**What to do:**
- 404 = endpoint doesn't exist (check URL)
- 500 = PHP error (check PHP logs at `C:\xampp\apache\logs`)
- Other codes = check HTTP status documentation

---

## 🔄 Backend Logic Error Pattern (Status 200 but success=false)

```
📥 RESPONSE RECEIVED:
   Status Code: 200
   Body (JSON): {"success":false,"message":"No HP sudah terdaftar"}
```

**What to do:**
- User will see error toast: "No HP sudah terdaftar"
- This is expected behavior - user needs to fix their input
- Check PHP logic to ensure error message is helpful

---

## 📄 JSON Parse Error Pattern (Response is HTML, not JSON)

```
📥 RESPONSE RECEIVED:
   Status Code: 200
   Body (RAW - Not JSON): <html><head><title>Error</title></head>...
```

**What to do:**
- Response is NOT valid JSON
- Likely an HTML error page from PHP
- Check PHP error logs for what went wrong
- Ensure response is valid JSON:
  ```php
  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'message' => 'OK']);
  ```

---

## 🔗 Wrong Endpoint Pattern (Wrong URL)

```
📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/flutter_api/register_tahap1
```

**What to do:**
- URL has "flutter_api" twice - dobel endpoint!
- Check `lib/config/api.dart` for base URL
- Ensure endpoint path doesn't include `flutter_api` prefix if base URL already has it
- Example:
  - ✅ Correct: `base_url/register_tahap1` (base_url = `.../flutter_api`)
  - ❌ Wrong: `base_url/flutter_api/register_tahap1`

---

## 📝 Request Field Validation Pattern

```
📤 REGISTER TAHAP 1 REQUEST
   Fields to send:
      no_hp: 081234567890
      kata_sandi: [HIDDEN]
      nama_lengkap: John Doe
      alamat_domisili: Jl. Main Street
      tanggal_lahir: 1990-05-15
      setuju_syarat: 1
```

**What to check:**
- Are all required fields present?
- Are field names spelled correctly (match PHP `$_POST['field_name']`)?
- Are values in correct format?
  - Date should be YYYY-MM-DD (not DD/MM/YYYY)
  - Phone should include country code if required
  - Checkbox should be "1" or "0" (not true/false)

---

## 🔐 Security Pattern (Password Hidden)

```
   Fields to send:
      no_hp: 081234567890
      kata_sandi: [HIDDEN]
```

**What to know:**
- Password is never logged (for security)
- If you need to debug password issues, add server-side logging:
  ```php
  // In register_tahap1.php
  error_log('Password received: ' . $_POST['kata_sandi']);
  ```

---

## 📍 Multi-Stage Request Pattern

```
[14:09:20] 🔍 PRE-FLIGHT: CHECKING SERVER REACHABILITY
   Status Code: 200

[14:09:25] 📤 REGISTER TAHAP 1 REQUEST
   Status Code: 200
   Body (JSON): {"success":true}
```

**What to know:**
1. Ping check succeeds first
2. Then main request is sent
3. If ping fails, main request never sent (optimization)

---

## 🛠️ Multipart Request Pattern (File Upload)

```
📤 REGISTER TAHAP 2 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap2
   Method: POST (Multipart)
   Files:
      photo: /data/user/0/com.example.app/cache/image_12345.jpg
   Fields:
      id_pengguna: 12345
      nama_lengkap: John Doe
```

**What to check:**
- Is file path valid?
- Is file size reasonable (< max upload size)?
- Are form fields present alongside file?
- Backend receiving multipart properly: `$_FILES['photo']` and `$_POST['nama_lengkap']`

---

## 🔍 Detailed Debug Examples

### Example 1: Registration Fails with "Network unreachable"
**Console shows:**
```
❌ SOCKET EXCEPTION during ping: Connection refused
   Error Code: ECONNREFUSED
```
**Solution:**
1. `ping 192.168.1.27` from device (ADB shell)
2. Check if Apache is running: `php artisan serve` or XAMPP control panel
3. Verify device is on same WiFi network

### Example 2: Registration Sends but Backend Doesn't Receive Data
**Console shows:**
```
📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Fields: {no_hp: 081234567890, kata_sandi: [HIDDEN], ...}
   Status Code: 200
   Body: {"success":false,"message":"Field no_hp is required"}
```
**Solution:**
1. Check PHP endpoint - is it reading `$_POST['no_hp']` or something else?
2. Add server-side logging:
   ```php
   error_log('Received POST: ' . print_r($_POST, true));
   ```
3. Verify field names match exactly (case-sensitive)

### Example 3: Registration Times Out
**Console shows:**
```
❌ TIMEOUT EXCEPTION: Timed out after 0:00:30.000000
```
**Solution:**
1. Backend is responding but very slowly
2. Check PHP error logs: `C:\xampp\apache\logs\error.log`
3. Check database performance (large queries?)
4. Check if PHP is stuck in a loop

### Example 4: JSON Parse Error (HTML Response)
**Console shows:**
```
📥 RESPONSE RECEIVED:
   Status Code: 200
   Body (RAW - Not JSON): <!DOCTYPE html>
   <html>
   <head><title>Parse error in register_tahap1.php on line 45</title>
```
**Solution:**
1. PHP has a syntax error
2. Fix the PHP file (line 45)
3. Ensure `header('Content-Type: application/json');` is set
4. Ensure response is valid JSON

---

## 📊 Decision Tree

```
Does log show "STATUS CODE: 200"?
├─ YES → Does log show "success: true"?
│  ├─ YES → ✅ WORKING! Registration successful
│  └─ NO → Check error message in response (backend logic issue)
└─ NO → Check status code:
   ├─ 404 → Wrong URL (check api.dart)
   ├─ 500 → PHP error (check error log)
   ├─ 403/401 → Permission denied
   └─ Other → Check HTTP status documentation

Does log show "SOCKET EXCEPTION"?
├─ YES → Network unreachable (check backend, network, firewall)
└─ NO

Does log show "TIMEOUT"?
├─ YES → Backend slow or down (check server performance)
└─ NO

Does log show "Body (RAW - Not JSON)"?
├─ YES → PHP error or wrong Content-Type header
└─ NO

If none of above: Check full response body for clues
```

---

## 🎯 Quick Actions

| Symptom | Quick Check |
|---------|-----------|
| "Network unreachable" | Is backend running? `http://192.168.1.27` in browser |
| "Request times out" | Is PHP hanging? Check `C:\xampp\apache\logs\error.log` |
| "Field not found" | Do field names in log match PHP `$_POST` keys? |
| "JSON parse error" | Is response valid JSON? Check with `jsonlint.com` |
| "Success but no update" | Is database commit happening? Check PHP logic |
| "File upload fails" | Check file size, path, permissions in log |

---

**This reference guide will help you quickly diagnose issues from the console logs!**
