# 🎯 CONSOLE LOG GUIDE - What to Look For

**FOKUS:** Raw response body printing SEBELUM jsonDecode

---

## 📋 Console Output Format

```
================================================================================
[TIMESTAMP] 📤 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Method: POST
   Body: {fields...}

================================================================================

📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
YOUR_SERVER_RESPONSE_HERE

================================================================================

[Next action based on response]
```

---

## ✅ SUCCESSFUL REGISTRATION (Contoh Nyata)

**Console log:**
```
================================================================================
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
{"success":true,"id_pengguna":"12345","message":"Registration successful"}

✅ Body (Parsed JSON): {success: true, id_pengguna: 12345, message: Registration successful}
================================================================================
```

**App shows:**
- ✅ Green toast: "Pendaftaran tahap 1 berhasil"
- ✅ Moves to next screen

---

## ❌ DUPLICATE PHONE NUMBER (Contoh Nyata)

**Console log:**
```
================================================================================
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
{"success":false,"message":"No HP sudah terdaftar"}

✅ Body (Parsed JSON): {success: false, message: No HP sudah terdaftar}
================================================================================
```

**App shows:**
- ❌ Red toast: "No HP sudah terdaftar"
- ❌ Stays on registration form
- ✅ **Shows ACTUAL error from server** (not generic message!)

---

## 🚫 PHP ERROR - NOT VALID JSON (Contoh Nyata)

**Console log:**
```
================================================================================
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
<html>
<head><title>Parse error</title></head>
<body>
Parse error: syntax error, unexpected '}' in /xampp/htdocs/gas/gas_web/flutter_api/register_tahap1.php on line 45
</body>
</html>

⚠️ Not valid JSON: FormatException: Unexpected character '<' at position 0
================================================================================
```

**App shows:**
- ❌ Red toast: "Server response tidak valid JSON"
- ❌ **You can see PHP error** in the raw response
- ✅ Go fix PHP syntax error on line 45

---

## ⏱️ TIMEOUT (Contoh Nyata)

**Console log:**
```
================================================================================
[2026-01-18T14:09:55.123456] 📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   ...
   Timeout: 30s

[After 30 seconds...]

❌ TIMEOUT EXCEPTION: Timed out after 0:00:30.000000
================================================================================
```

**App shows:**
- ❌ Red toast: "⏱️ Request timeout - Server tidak merespons dalam 30 detik"
- ❌ **You know backend is slow or not responding**

---

## 🔌 NETWORK ERROR (Contoh Nyata)

**Console log:**
```
================================================================================
❌ SOCKET EXCEPTION: Connection refused
   Error Code: ECONNREFUSED
================================================================================
```

**App shows:**
- ❌ Red toast: "Tidak dapat menjangkau server..."
- ❌ **Backend not running or IP is wrong**

---

## 📊 QUICK DECISION TREE

```
Look at console for: 📋 RAW RESPONSE BODY
                            ↓
                     Does it exist?
                     ├─ YES ─→ Read what server sent
                     │         ├─ Starts with "{"? → Probably JSON
                     │         ├─ Starts with "<"? → PHP error (HTML)
                     │         └─ Something else? → Check format
                     │
                     └─ NO ──→ Look for:
                              ├─ "TIMEOUT"? → Server slow
                              ├─ "SOCKET"? → Network issue
                              └─ "ERROR"? → Something else
```

---

## 🔍 WHAT EACH PART MEANS

### Line: `📋 RAW RESPONSE BODY (BEFORE JSON PARSE):`
- **Meaning:** Next lines are EXACTLY what server sent
- **Action:** Read it carefully
- **Format:** Could be JSON, HTML, plain text

### Line: `{"success":true, ...}`
- **Meaning:** Valid JSON response from PHP
- **Check:** Look for `"success": true` or `"success": false`
- **Action:** If false, read the `"message"` field

### Line: `<html><head>...</head>`
- **Meaning:** Server returned HTML error (PHP syntax/runtime error)
- **Action:** Read the error message, fix PHP code
- **Location:** Usually shows file path and line number

### Line: `⚠️ Not valid JSON:`
- **Meaning:** Response is not valid JSON format
- **Reason:** Usually PHP error page (HTML) instead of JSON
- **Action:** Check PHP code for syntax errors

---

## 🎯 CHECKLIST - What to Verify

### Before You Run App
- [ ] `flutter clean` done
- [ ] `flutter pub get` done  
- [ ] Backend running (http://192.168.1.27 accessible)
- [ ] PHP file exists at `/gas/gas_web/flutter_api/register_tahap1.php`

### While Running App
- [ ] Console visible (VS Code / Android Studio terminal)
- [ ] Form filled correctly
- [ ] Submit button clicked
- [ ] Console logs appear

### After Submission
- [ ] Look for `📋 RAW RESPONSE BODY` in console
- [ ] Read what server sent
- [ ] Check if response is valid JSON or HTML error
- [ ] If error, check `"message"` field
- [ ] If HTML error, read PHP error message
- [ ] Fix issue accordingly

---

## 💡 COMMON FIXES

### Problem: See `<html>...</html>` in raw response
**Solution:** 
1. Open PHP file: `/gas/gas_web/flutter_api/register_tahap1.php`
2. Check syntax errors (mismatched braces, semicolons)
3. Add error reporting: `error_reporting(E_ALL); ini_set('display_errors', 1);`
4. Save and reload

### Problem: See "No HTTP sudah terdaftar" error
**Solution:**
1. This is expected - user needs to use different phone number
2. Or clear database and try again
3. Or fix backend logic if it's wrong

### Problem: See `⏱️ Request timeout`
**Solution:**
1. Backend is slow
2. Check PHP for long-running queries
3. Check database for locks
4. Increase timeout in code if needed

### Problem: See `SOCKET EXCEPTION: Connection refused`
**Solution:**
1. Backend not running
2. Start Apache/XAMPP
3. Check IP address (should be 192.168.1.27)
4. Check firewall settings

### Problem: No `📋 RAW RESPONSE BODY` line appears
**Solution:**
1. Running in Release mode? Switch to Debug
2. Console not visible? Check terminal output
3. App not sending request? Check form validation
4. Network completely down? Check backend

---

## 🚀 STEP-BY-STEP TESTING

1. **Open VS Code terminal** (or Android Studio logcat)
2. **Clear console** (scroll to top)
3. **Run app:** `flutter run`
4. **Wait for app to load** (5-10 seconds)
5. **Fill registration form:**
   - Nomor HP: `08199060817`
   - Kata Sandi: `7u7u7u`
   - Konfirmasi: `7u7u7u`
   - Nama Lengkap: `Test User`
   - Alamat: `Jl Test`
   - Tanggal Lahir: `07/11/2007`
   - Agree terms: ✓ Check
6. **Click "LANJUT"** button
7. **Watch console** for `📋 RAW RESPONSE BODY`
8. **Read the response** carefully
9. **Check if JSON or HTML error**
10. **Take action** based on what you see

---

## 📸 EXPECTED CONSOLE OUTPUT

```
================================================================================
[2026-01-18T14:09:25.123456] 📤 REGISTER TAHAP 1 REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/register_tahap1
   Method: POST (MultipartRequest)
   Fields to send:
      no_hp: 08199060817
      kata_sandi: [HIDDEN]
      nama_lengkap: Test User
      alamat_domisili: Jl Test
      tanggal_lahir: 2007-11-07
      setuju_syarat: 1
   Timeout: 30s

📥 RESPONSE RECEIVED:
   Status Code: 200
   Status Reason: OK
   Body Length: 120 bytes
   📋 RAW BODY: {"success":true,"id_pengguna":"123","message":"OK"}
   ✅ Body (Parsed JSON): {success: true, id_pengguna: 123, message: OK}
================================================================================

✅✅✅ REGISTER TAHAP 1 SUKSES!
```

---

## ⚠️ IMPORTANT NOTES

- **Password is HIDDEN** - `[HIDDEN]` is shown, not actual password
- **Timestamps are ISO8601** - Exact time each request was made
- **All request details logged** - URL, method, headers, body
- **All response details logged** - Status, headers, raw body
- **Error types categorized** - Socket, Timeout, HTTP, JSON parse

---

**Key Point:** If you see `📋 RAW RESPONSE BODY`, you now know EXACTLY what the server sent. No more guessing!
