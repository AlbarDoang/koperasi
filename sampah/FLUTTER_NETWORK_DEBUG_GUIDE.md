# FLUTTER NETWORK DEBUGGING - COMPREHENSIVE FIX GUIDE

## 🔴 CRITICAL: FLUTTER CLEAN REQUIRED

After adding `network_security_config.xml`, you **MUST** run:
```bash
flutter clean
flutter pub get
flutter run
```

Tidak cukup hanya hot reload! File konfigurasi Android perlu di-rebuild sepenuhnya.

---

## ✅ FIXES YANG SUDAH DITERAPKAN

### 1. **network_security_config.xml** (PALING PENTING!)
**File:** `gas_mobile/android/app/src/main/res/xml/network_security_config.xml`

Android 9+ STRICT mengharuskan file ini untuk allow cleartext HTTP traffic. 
- Sebelumnya: File TIDAK ADA ❌ (Root cause!)
- Sekarang: File ADA dan allow cleartext untuk 192.168.1.27 ✅

**Konten:**
```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <!-- Allow cleartext traffic for local development IP (192.168.1.27) -->
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">192.168.1.27</domain>
        <domain includeSubdomains="true">localhost</domain>
        <domain includeSubdomains="true">127.0.0.1</domain>
        <!-- Allow 10.x.x.x range untuk emulator -->
        <domain includeSubdomains="true">10.0.0.0/8</domain>
    </domain-config>
    
    <!-- Block cleartext untuk semua domain lain (security best practice) -->
    <domain-config cleartextTrafficPermitted="false">
        <domain includeSubdomains="true">example.com</domain>
    </domain-config>
</network-security-config>
```

**Linked di AndroidManifest.xml:**
```xml
<application
    android:usesCleartextTraffic="true"
    android:networkSecurityConfig="@xml/network_security_config"
    ...>
```

### 2. **Enhanced HTTP Client Logging**
**File:** `gas_mobile/lib/config/http_client.dart`

Sekarang menampilkan:
- ✅ Request URL, method, body
- ✅ Response status code
- ✅ Actual exception types: `SocketException`, `TimeoutException`, `HandshakeException`
- ✅ Detailed error messages dengan errno jika ada

**Sample Console Output:**
```
[2025-01-14 10:30:45.123] 📤 POST REQUEST
   URL: http://192.168.1.27/gas/gas_web/flutter_api/login
   Body: {nohp: 082xxx, pass: ***}
   
[2025-01-14 10:30:45.456] ✅ POST SUCCESS: 200
   Response: {"success":true,"user":{...}}

[Atau jika error:]

[2025-01-14 10:30:50.123] ❌ SOCKET ERROR: Connection refused (errno: 111)
[2025-01-14 10:30:50.124] 🔐 HANDSHAKE ERROR: ...
[2025-01-14 10:31:00.123] ⏱️ TIMEOUT: Request timeout setelah 30 detik
```

### 3. **Network Test Service at Startup**
**File:** `gas_mobile/lib/services/network_test_service.dart`

Otomatis jalan di debug mode pada app startup:
1. **DNS Resolution Test** - Apakah bisa resolve 192.168.1.27?
2. **Basic Connection Test** - Apakah bisa reach ping.php?
3. **Login Endpoint Test** - Apakah bisa reach login endpoint?

**Sample Output:**
```
🌐 NetworkTestService: Memulai diagnostik koneksi network...
🌐 NetworkTestService: Base URL = http://192.168.1.27/gas/gas_web/flutter_api
🌐 NetworkTestService: [1/3] Testing DNS resolution...
   Host: 192.168.1.27
   Port: 80
   ✅ DNS Resolved: 192.168.1.27
🌐 NetworkTestService: [2/3] Testing basic HTTP connection...
   Target: http://192.168.1.27/gas/gas_web/flutter_api/ping.php
   Status Code: 200
   ✅ Basic Connection Success
🌐 NetworkTestService: [3/3] Testing login endpoint...
   Target: http://192.168.1.27/gas/gas_web/flutter_api/login
   Status Code: 200
   ✅ Login Endpoint Reachable
```

Jika ada error, akan terlihat jelas di console dan Anda bisa tahu exactly apa yang salah.

### 4. **Centralized API Configuration** ✅ (SUDAH SEBELUMNYA)
**File:** `gas_mobile/lib/config/api.dart`

- Base URL: `http://192.168.1.27/gas/gas_web/flutter_api`
- Semua endpoint reference dari file ini
- Ada fallback untuk emulator: `http://10.0.2.2:80/gas/gas_web/flutter_api`

### 5. **AndroidManifest.xml dengan Cleartext Support** ✅ (SUDAH SEBELUMNYA)
**File:** `gas_mobile/android/app/src/main/AndroidManifest.xml`

```xml
<uses-permission android:name="android.permission.INTERNET" />

<application
    android:usesCleartextTraffic="true"
    android:networkSecurityConfig="@xml/network_security_config"
    ...>
```

---

## 🚀 STEP-BY-STEP REBUILD & TEST

### Langkah 1: Clean & Rebuild
```bash
# Navigate to project root
cd gas_mobile

# CRITICAL: Clean old build artifacts
flutter clean

# Get fresh dependencies
flutter pub get

# Connect device via ADB, then:
flutter run
```

**Output yang diharapkan di console:**
```
🌐 NetworkTestService: Memulai diagnostik...
[Diagnostic tests output]
```

### Langkah 2: Check Console Logs
Buka Android Studio atau terminal, lihat Logcat/console:
- Jika "DNS Resolved" ✅ → Network layer OK
- Jika "Basic Connection Success" ✅ → HTTP OK
- Jika "Login Endpoint Reachable" ✅ → Backend responsif

### Langkah 3: Test Login Manually
1. Jangan tutup app
2. Di login screen, masukkan credentials
3. Lihat console logs untuk detail request/response
4. Jika error, error message akan JELAS bukan generic "tidak bisa reach server"

---

## 🔍 TROUBLESHOOTING CHECKLIST

| Issue | Check |
|-------|-------|
| "DNS Resolved: [IP]" ✅ | Tapi "Connection refused"? | Backend tidak running atau port salah |
| "Connection refused (errno: 111)" | Firewall? Port 80 accessible? |
| "Host unreachable" | Network cable? WiFi connected? |
| "HANDSHAKE ERROR" | HTTPS enforcement? Non-standard port? |
| "TIMEOUT: 30 detik" | Backend down? Too slow? |
| Network logs tidak muncul | `kDebugMode` false? Rebuild dengan debug |

---

## 📋 FILES MODIFIED/CREATED

1. ✅ `gas_mobile/lib/config/http_client.dart` - Enhanced logging
2. ✅ `gas_mobile/lib/services/network_test_service.dart` - Network tests
3. ✅ `gas_mobile/lib/main.dart` - Added NetworkTestService call
4. ✅ `gas_mobile/android/app/src/main/AndroidManifest.xml` - Added networkSecurityConfig link
5. ✅ `gas_mobile/android/app/src/main/res/xml/network_security_config.xml` - CRITICAL: Allow cleartext

**Backend files (no changes needed):**
- `gas_web/flutter_api/ping.php` - Exists & ready

---

## 🎯 EXPECTED BEHAVIOR AFTER FIX

### Scenario 1: Backend Running (Normal Case)
```
App starts → Network tests pass ✅
Login attempt → Request logged in console ✅
Response received → Success or specific error ✅
```

### Scenario 2: Backend Not Running
```
App starts → Network tests fail clearly:
   ❌ SOCKET ERROR: Connection refused (errno: 111)
   User knows: Backend is down, not a connectivity issue
```

### Scenario 3: Wrong IP in api.dart
```
App starts → Network tests fail with:
   ❌ DNS Error: Name or service not known
   User knows: IP configuration wrong
```

---

## ℹ️ IMPORTANT NOTES

1. **Android 9+ (API 28+) Strict:**
   - Just `android:usesCleartextTraffic="true"` is NOT ENOUGH
   - MUST have `network_security_config.xml` AND reference it
   - This was the MISSING PIECE causing your issues!

2. **network_security_config.xml Location:**
   - Path: `android/app/src/main/res/xml/network_security_config.xml`
   - NOT in `AndroidManifest.xml` directly
   - Separate XML file with proper domain config

3. **Emulator vs Physical Device:**
   - Emulator: Use `10.0.2.2` or `10.0.0.0/8` in config
   - Physical device: Use actual IP `192.168.1.27`
   - Config handles both automatically

4. **Production Deployment:**
   - Remove `android:usesCleartextTraffic="true"` for HTTPS
   - Adjust `network_security_config.xml` to use proper domains
   - Add proper certificate validation

---

## ✨ TESTING API ENDPOINT

**Test from Android device terminal (via adb shell):**
```bash
# SSH ke device via ADB
adb shell

# Test dengan curl (if available)
curl http://192.168.1.27/gas/gas_web/flutter_api/ping.php

# Or dari PC (ping API):
curl http://192.168.1.27/gas/gas_web/flutter_api/ping.php
```

**Expected Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-01-14T10:30:45+07:00",
  "server": "gas_web/flutter_api",
  "message": "✅ Backend API is reachable and responding correctly"
}
```

---

## 📞 NEXT STEPS

1. ✅ Run `flutter clean` (CRITICAL!)
2. ✅ Run `flutter pub get`
3. ✅ Connect physical device via ADB
4. ✅ Run `flutter run`
5. ✅ Watch console for network diagnostics
6. ✅ If tests pass but login fails → Check backend logs
7. ✅ If tests fail → Use console error to identify exact issue

---

## 🐛 DEBUGGING TIPS

**In terminal, watch live logs:**
```bash
# Get device logs in real-time
adb logcat | grep "🌐\|📤\|✅\|❌"

# Or just run and watch Flutter output:
flutter run
# Console shows all [datetime] prefixed logs
```

**Common Console Patterns:**
- `❌ SOCKET ERROR` → Network/firewall issue
- `🔐 HANDSHAKE ERROR` → SSL/HTTPS issue (shouldn't happen with http://)
- `⏱️ TIMEOUT` → Backend slow or not responding
- `✅` → Success, check response body for actual data

---

Generated: 2025-01-14  
Last Updated: After network_security_config.xml creation
