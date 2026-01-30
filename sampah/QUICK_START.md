# ⚡ QUICK START GUIDE - IP UPDATE 192.168.1.8

**Waktu baca:** 3 menit  
**Status:** ✅ Ready for Production

---

## 🎯 Yang Berubah?

### Before ❌
```
Flutter → http://192.168.1.5/gas/gas_web/flutter_api
        ↓
      Database (localhost)
```

### After ✅
```
Flutter → http://192.168.1.8/gas/gas_web/flutter_api
        ↓
      Database (localhost) [unchanged]
```

---

## ✅ 5-MENIT QUICK SETUP

### 1. Verify Files Changed ✓
```bash
cd c:\xampp\htdocs\gas
git status  # Should show 13 modified files
```

### 2. Build Flutter ✓
```bash
cd gas_mobile
flutter clean
flutter pub get
flutter build apk --release
```

### 3. Install to Device ✓
```bash
flutter install
# atau: adb install build/app/outputs/apk/release/app-release.apk
```

### 4. Test Connection ✓
```bash
# Dari command prompt:
ping 192.168.1.8
curl http://192.168.1.8/gas/gas_web/flutter_api/ping.php
```

### 5. Test on App ✓
- Buka app → Coba login
- Check debug console: Harus ada "BASE URL: 192.168.1.8"
- Tidak boleh ada "Connection refused"

---

## 📝 Files Modified (13 total)

```
FLUTTER:
  ✅ gas_mobile/.env
  ✅ gas_mobile/lib/config/api.dart

TESTS:
  ✅ tmp_test_tabungan_api.php
  ✅ tmp_post_summary.php
  ✅ scripts/run_cairkan_approval_test.php
  ✅ tests/integration/flow_test.php
  ✅ tests/integration/pinjaman_cicilan_test.php
  ✅ tests/integration/pinjaman_biasa_detail_test.php
  ✅ tests/integration/pinjaman_ajukan_notif_test.php
  ✅ tests/integration/manual_notif_check.php
  ✅ tests/integration/check_nonloan_processing.php

SCRIPTS:
  ✅ scripts/smoke_pinjaman.ps1
  ✅ scripts/smoke_pinjaman.sh
```

---

## 🔒 What's Safe (NOT Changed)

```
✅ Database: localhost (internal only)
✅ Credentials: unchanged
✅ Fonnte token: unchanged
✅ Schema: unchanged
✅ Data: unchanged
```

---

## 🚨 If Something Goes Wrong

### Problem: "Connection refused"
```bash
ping 192.168.1.9
# Make sure server is online
```

### Problem: "OTP not received"
```bash
# Check logs
tail -f /xampp/htdocs/gas/gas_web/flutter_api/log_otp_fonte.txt
```

### Problem: "App shows old IP"
```bash
# Uninstall and reinstall app
adb uninstall com.example.tabungan
flutter install
```

### Rollback (if needed)
```bash
git checkout gas_mobile/.env
git checkout gas_mobile/lib/config/api.dart
flutter clean && flutter build apk --release
```

---

## ✅ Verification Checklist

- [ ] `ping 192.168.1.8` works
- [ ] `curl` to API returns JSON
- [ ] Flutter app builds without errors
- [ ] App installs on device
- [ ] Login test successful
- [ ] OTP received in WhatsApp
- [ ] Debug console shows 192.168.1.8

---

## 📞 Need Details?

**Quick Audit Report:**  
📄 [AUDIT_REPORT_IP_UPDATE_2026_01_23.md](AUDIT_REPORT_IP_UPDATE_2026_01_23.md)

**Full Deployment Guide:**  
📋 [DEPLOYMENT_CHECKLIST_IP_UPDATE.md](DEPLOYMENT_CHECKLIST_IP_UPDATE.md)

**Files Changed Summary:**  
📝 [FILES_MODIFIED_SUMMARY.md](FILES_MODIFIED_SUMMARY.md)

**Full Overview:**  
📊 [README_IP_UPDATE_2026_01_23.md](README_IP_UPDATE_2026_01_23.md)

---

## ⚡ TL;DR

1. ✅ All old IPs (192.168.1.5) → 192.168.1.8
2. ✅ 13 files updated, nothing else changed
3. ✅ Database safe, credentials safe
4. ✅ Deploy → Build → Install → Test
5. ✅ Ready! 🚀

---

**Time to Deploy:** ~15 minutes  
**Risk Level:** ⚠️ LOW (no database/config changes)  
**Rollback Time:** ~5 minutes (if needed)  

---
