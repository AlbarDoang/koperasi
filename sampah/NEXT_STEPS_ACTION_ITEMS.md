# ✅ LANGKAH SELANJUTNYA - ACTION ITEMS

**Audit Date:** 2026-01-23  
**Status:** ✅ AUDIT COMPLETE  
**Next:** IMMEDIATE ACTION REQUIRED

---

## 🎯 WHAT WAS COMPLETED

✅ **Audit selesai 100%**
- Scan semua file PHP, Flutter, config
- Identifikasi 13 file dengan IP lama
- Update semua referensi ke 192.168.1.8
- Verifikasi API response format (all JSON)
- Verifikasi timeout config (30 seconds)
- Database & credentials tetap aman

✅ **Documentation selesai**
- 5 laporan komprehensif dibuat
- Deployment guide step-by-step
- Troubleshooting guide lengkap
- Rollback plan tersedia

---

## 📋 YOUR ACTION ITEMS (WAJIB DILAKUKAN)

### **STEP 1: VERIFY FILES CHANGED** ⚠️ (HARUS DILAKUKAN DULU!)
```bash
cd c:\xampp\htdocs\gas

# Lihat daftar 13 files yang diubah:
git status

# Verify 13 files terlihat:
# - gas_mobile/.env
# - gas_mobile/lib/config/api.dart
# - tmp_test_tabungan_api.php
# - tmp_post_summary.php
# - scripts/run_cairkan_approval_test.php
# - tests/integration/flow_test.php
# - tests/integration/pinjaman_cicilan_test.php
# - tests/integration/pinjaman_biasa_detail_test.php
# - tests/integration/pinjaman_ajukan_notif_test.php
# - tests/integration/manual_notif_check.php
# - tests/integration/check_nonloan_processing.php
# - scripts/smoke_pinjaman.ps1
# - scripts/smoke_pinjaman.sh

echo "✅ Semua files terlihat? Lanjut ke STEP 2"
```

---

### **STEP 2: VERIFY NEW IP IS CORRECT** 
```bash
# Buka dan verify 2 config files terpenting:

# 1. Check .env file
cat gas_mobile/.env | grep API_BASE_URL
# Expected: API_BASE_URL=http://192.168.1.8/gas/gas_web/flutter_api

# 2. Check api.dart file
grep "_defaultLan" gas_mobile/lib/config/api.dart
# Expected: static const String _defaultLan = 'http://192.168.1.8/gas/gas_web/flutter_api';

echo "✅ IP sudah correct? Lanjut ke STEP 3"
```

---

### **STEP 3: TEST NETWORK CONNECTIVITY**
```bash
# Test ping ke server baru
ping -c 4 192.168.1.8

# Expected output:
# 4 packets transmitted, 4 received, 0% packet loss

# Test HTTP connectivity
curl http://192.168.1.8/gas/gas_web/flutter_api/ping.php

# Expected:
# JSON response atau "pong"

echo "✅ Network OK? Lanjut ke STEP 4"
```

---

### **STEP 4: BUILD FLUTTER APK**
```bash
cd c:\xampp\htdocs\gas\gas_mobile

# Clean build
flutter clean

# Get dependencies
flutter pub get

# Verify no errors
flutter analyze

# Build release APK
flutter build apk --release

# Expected: 
# Build successful, APK created at:
# build/app/outputs/apk/release/app-release.apk

echo "✅ Build berhasil? Lanjut ke STEP 5"
```

---

### **STEP 5: INSTALL TO DEVICE/EMULATOR**
```bash
# Option A: Gunakan flutter install
flutter install

# Option B: Gunakan adb manual
adb install build/app/outputs/apk/release/app-release.apk

# Expected: 
# Success or reinstall notification

echo "✅ Install berhasil? Lanjut ke STEP 6"
```

---

### **STEP 6: TEST LOGIN ON APP**
```
Manual test di device/emulator:

1. Buka Flutter app
2. Coba login dengan akun test
3. Lihat di debug console/logcat:
   - Harus ada: "BASE URL: 192.168.1.8"
   - Jangan ada: "Connection refused", "TimeoutException"
4. Verifikasi login response (success atau error message)

✅ Login works? Lanjut ke STEP 7
```

---

### **STEP 7: TEST OTP FLOW (OPTIONAL - IF REGISTRATION NEEDED)**
```
Manual test di device/emulator:

1. Trigger registration atau "Lupa Password"
2. Monitor server logs: tail -f log_otp_fonte.txt
3. Verifikasi OTP diterima di WhatsApp dalam 5-10 detik
4. Check server log shows: http_code=200

✅ OTP received? Lanjut ke STEP 8
```

---

### **STEP 8: COMMIT & DEPLOY**
```bash
cd c:\xampp\htdocs\gas

# Review changes
git diff gas_mobile/.env
git diff gas_mobile/lib/config/api.dart
# ... review all 13 files

# Commit changes
git add .
git commit -m "Update API IP: 192.168.1.5 → 192.168.1.8"

# Push ke repository
git push origin main  # atau branch sesuai your convention

# Deploy APK ke production device/store
# Langkah ini tergantung distribution method Anda

echo "✅ Everything deployed? You're done!"
```

---

## 📝 QUICK REFERENCE

### Files Changed
```
13 files total:
- 2 Flutter config files
- 3 Test PHP files  
- 6 Integration test files
- 2 Shell/PowerShell scripts
```

### What Changed
```
FROM: 192.168.1.5 or localhost
TO:   192.168.1.8
```

### What's Safe
```
✅ Database: unchanged
✅ Credentials: unchanged
✅ Fonnte token: unchanged
✅ Schema: unchanged
```

---

## ⏱️ TIME ESTIMATE

| Step | Time | Notes |
|------|------|-------|
| Step 1 (Verify) | 2 min | Just listing files |
| Step 2 (Verify IP) | 1 min | Quick check |
| Step 3 (Network) | 2 min | Ping test |
| Step 4 (Build) | 10 min | flutter build |
| Step 5 (Install) | 2 min | adb install |
| Step 6 (Test) | 5 min | Manual test |
| Step 7 (OTP) | 5 min | Optional |
| Step 8 (Commit) | 2 min | Git push |
| **TOTAL** | **~30 min** | All steps |

---

## 🚨 IF SOMETHING GOES WRONG

### Problem: Files tidak terlihat di `git status`
**Solution:** 
```bash
git diff gas_mobile/.env | head -20
# Verify perubahan sudah ada
git status --porcelain | wc -l
# Harus show 13
```

### Problem: Network ping fails
**Solution:**
```bash
# Pastikan IP benar
# Restart server/Apache
sudo systemctl restart apache2
# Atau hubungi network admin
```

### Problem: Flutter build fails
**Solution:**
```bash
flutter clean
rm -rf pubspec.lock
flutter pub get
flutter build apk --release
```

### Problem: Login shows old IP in logs
**Solution:**
```bash
# Clear device cache
adb shell pm clear com.example.tabungan
# Reinstall app
flutter install
```

### Problem: Need to rollback
**Solution:**
```bash
git checkout gas_mobile/.env
git checkout gas_mobile/lib/config/api.dart
# ... repeat for other files if needed
flutter clean && flutter build apk --release
```

---

## 📚 DOCUMENTATION REFERENCE

**Need more info?** Baca files ini:

1. **⚡ Quick overview:** [QUICK_START.md](QUICK_START.md)
2. **📋 Full audit:** [AUDIT_REPORT_IP_UPDATE_2026_01_23.md](AUDIT_REPORT_IP_UPDATE_2026_01_23.md)
3. **✅ Deployment steps:** [DEPLOYMENT_CHECKLIST_IP_UPDATE.md](DEPLOYMENT_CHECKLIST_IP_UPDATE.md)
4. **📝 Code changes:** [FILES_MODIFIED_SUMMARY.md](FILES_MODIFIED_SUMMARY.md)
5. **📄 Overview:** [README_IP_UPDATE_2026_01_23.md](README_IP_UPDATE_2026_01_23.md)
6. **📚 Documentation index:** [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)

---

## ✅ BEFORE YOU START

- [ ] Read [QUICK_START.md](QUICK_START.md) first (2 min)
- [ ] Backup database (if not already backed up)
- [ ] Have device/emulator ready
- [ ] Have terminal/command prompt open
- [ ] Make sure 192.168.1.8 is reachable

---

## 🎯 SUCCESS CRITERIA

After completing all steps, you should have:

✅ 13 files updated with 192.168.1.8  
✅ Flask app builds without errors  
✅ APK installed on device  
✅ Login test successful  
✅ OTP received in WhatsApp (if tested)  
✅ No timeout errors  
✅ Changes committed to git  
✅ Production ready!  

---

## ❓ QUESTIONS?

### Technical Questions?
→ Check [AUDIT_REPORT_IP_UPDATE_2026_01_23.md](AUDIT_REPORT_IP_UPDATE_2026_01_23.md) - Troubleshooting section

### Deployment Questions?
→ Check [DEPLOYMENT_CHECKLIST_IP_UPDATE.md](DEPLOYMENT_CHECKLIST_IP_UPDATE.md) - Step-by-step guide

### Need full context?
→ Check [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) - Navigation map

---

**START NOW:** Go to Step 1 above! 👆

---

**Document Created:** 2026-01-23  
**Status:** Ready for action  
**Estimated Time to Complete:** 30 minutes  

💪 **You've got this! Let's do this! 🚀**
