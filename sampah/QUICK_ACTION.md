# ⚡ QUICK ACTION - RUN & TEST NOW!

**Status:** ✅ Ready to deploy  
**Time needed:** 5 minutes to test  
**Result:** See ACTUAL server response in console

---

## 🚀 3-STEP PROCESS

### STEP 1: Deploy App
```bash
cd c:\xampp\htdocs\gas\gas_mobile
flutter clean
flutter pub get
flutter run
```
**Time:** ~2 minutes

### STEP 2: Test Registration
1. Fill form with test data:
   - Nomor HP: `08199060817`
   - Kata Sandi: `test123`
   - Konfirmasi: `test123`
   - Nama Lengkap: `Test User`
   - Alamat: `Jl Test`
   - Tanggal Lahir: `07/11/2007`
   - ✓ Agree terms

2. Click **"LANJUT"** button

**Time:** ~30 seconds

### STEP 3: Check Console
Look for this in the console:
```
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
```

Then read what comes after it.

**Time:** ~1 minute

---

## 🎯 What to Expect

### ✅ Success Response
```
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
{"success":true,"id_pengguna":"12345","message":"OK"}

Green Toast: "Pendaftaran tahap 1 berhasil"
Next screen appears
```

### ❌ Error Response (Valid JSON)
```
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
{"success":false,"message":"No HP sudah terdaftar"}

Red Toast: "No HP sudah terdaftar"
Stay on form
```

### 🚫 Error Response (Invalid JSON - PHP Error)
```
📋 RAW RESPONSE BODY (BEFORE JSON PARSE):
<html><head><title>Parse error</title></head>...

Red Toast: "Server response tidak valid JSON"
Check PHP error in raw response
```

### ⏱️ Timeout
```
❌ TIMEOUT EXCEPTION: Timed out after 0:00:30

Red Toast: "⏱️ Request timeout - Server tidak merespons..."
```

### 🔌 Network Error
```
❌ SOCKET EXCEPTION: Connection refused

Red Toast: "Tidak dapat menjangkau server..."
Check if backend running
```

---

## 💡 Key Improvement

### BEFORE (Old Generic Error):
```
Toast: "Tidak dapat menjangkau server. Pastikan perangkat 
        dan komputer dev berada di jaringan yang sama..."
```
❌ Too generic  
❌ Doesn't tell you what's really wrong  
❌ Hard to debug

### AFTER (Actual Error):
```
📋 RAW RESPONSE BODY:
{"success":false,"message":"No HP sudah terdaftar"}

Toast: "No HP sudah terdaftar"
```
✅ Exact error from server  
✅ Know EXACTLY what's wrong  
✅ Easy to debug and fix

---

## 🔍 Console Output Locations

### VS Code
```
Terminal → Output → Flutter
Look for: 📋 RAW RESPONSE BODY
```

### Android Studio
```
Logcat → Filter by 'flutter'
Look for: 📋 RAW RESPONSE BODY
```

### Terminal (Direct)
```
flutter run -v
(verbose mode shows all logs)
```

---

## 📋 Quick Checklist

- [ ] Backend running? (Test: http://192.168.1.27)
- [ ] App deployed? (`flutter run` succeeded)
- [ ] Console visible? (Terminal open & showing)
- [ ] Registration form filled? (All fields)
- [ ] Form submitted? ("LANJUT" clicked)
- [ ] Console checked? (Look for "📋 RAW RESPONSE")
- [ ] Response read? (See actual message)

---

## 🎯 What to Do With Each Error

### Error: "No HTTP sudah terdaftar"
→ Use different phone number

### Error: "Parse error" (in HTML)
→ Go fix PHP syntax error (check file path in error)

### Error: "Timeout"
→ Backend is slow or not responding (check server)

### Error: "Connection refused"
→ Backend not running (start Apache/XAMPP)

### Error: "JSON parse error"
→ Response is not valid JSON (check PHP for echo/print statements before json_encode)

---

## 📸 Expected Console Example

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

✅✅✅ REGISTER TAHAP 1 SUKSES! Akan tampil notifikasi HIJAU
```

---

## ⚡ Fastest Way to Test

1. Open TWO windows:
   - Window 1: VS Code terminal
   - Window 2: Flutter app on device

2. Run in terminal:
   ```bash
   flutter run
   ```

3. App starts (5-10 seconds)

4. On device:
   - Fill form
   - Click LANJUT

5. Back to terminal:
   - Look for `📋 RAW RESPONSE BODY`
   - Read the response
   - You now know EXACTLY what's wrong!

**Total time: 5 minutes**

---

## 🆘 Troubleshooting

### Can't see logs?
- [ ] Use `flutter run -v` for verbose mode
- [ ] Check Android Studio Logcat
- [ ] Filter for "flutter" or "API"

### No "📋 RAW RESPONSE BODY" line?
- [ ] App not making request? (Check form validation)
- [ ] Running in Release mode? (Switch to Debug)
- [ ] Network completely down? (Check backend)

### Backend not responding?
- [ ] Is Apache/XAMPP running?
- [ ] Is http://192.168.1.27 accessible from device?
- [ ] Check firewall (port 80)

---

## 🎉 READY!

```bash
flutter run
```

Then test registration and **watch the console for `📋 RAW RESPONSE BODY`**

**You'll see the EXACT error from the server - no more guessing!**
