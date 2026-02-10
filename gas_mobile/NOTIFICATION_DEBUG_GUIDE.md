# OTP Notification System - Complete Debugging Guide

## Overview
This document explains all the debugging checkpoints throughout the OTP notification system so you can trace exactly where the notification flow may break.

---

## Debug Output Flow

When you request an OTP, you should see debug output in this order:

### 1. **Controller Emission** (in `forgot_pin_controller.dart`)
```
🚀 [Controller] ForgotPinController initialized
📤 [Controller] Emit Toast: Kode OTP telah dikirim ke WhatsApp Anda
```

**What it means:**
- Controller successfully created Toast event
- Toast fired to any listening observers
- If you DON'T see this: Controller issue or API call failed

### 2. **Page Reception** (in `forgot_pin_input_nomor_hp.dart`)
```
📱 [Page] NOTIFICATION RECEIVED: Kode OTP telah dikirim ke WhatsApp Anda
🎨 [Page] Toast color: Color(0xff4caf50), Duration: 3s
```

**What it means:**
- Page's `ever()` listener successfully caught the event
- Notification object received with all properties
- If you DON'T see this: Listener not attached or controller not registered

Also look for at page init:
```
✅ [Page] ForgotPinController registered
🔄 [Page] Setting up ever() listener for toastNotification
```

**What it means:**
- Controller was auto-bound in page
- Listener is now active and waiting for events
- If you DON'T see this: Page initState not running properly

### 3. **Toast Display** (in `custom_toast.dart`)
```
✅ [CustomToast] Overlay found from context
🎨 [CustomToast] Creating OverlayEntry...
✅ [CustomToast] Overlay.insert() successful, auto-removing in 3s
⏱️ [CustomToast] Duration expired, removing overlay...
✅ [CustomToast] Overlay removed
```

**What it means:**
- BuildContext had valid Overlay widget
- Toast widget successfully inserted into widget tree
- Toast displayed for specified duration
- Toast automatically removed after duration expires

**If Overlay not found, you'll see:**
```
⚠️ [CustomToast] Overlay.of(context) failed: (error details)
🔄 [CustomToast] Trying Get.context fallback...
✅ [CustomToast] Overlay found from Get.context
```

Or fallback to:
```
🔄 [CustomToast] Using Get.snackbar fallback...
✅ [CustomToast] Get.snackbar shown
```

Or final fallback:
```
✅ [CustomToast] ScaffoldMessenger shown
```

---

## Testing Checklist

### Step 1: Start the App
```bash
flutter run
```

Watch the console for initialization messages. You should see:
```
🚀 [Controller] ForgotPinController initialized
✅ [Page] ForgotPinController registered
🔄 [Page] Setting up ever() listener for toastNotification
```

### Step 2: Navigate to "Lupa PIN"
Click the "Lupa PIN" button and wait for ForgotPinInputNomorHp page to load.

### Step 3: Request OTP
1. Enter a phone number (e.g., 628123456789)
2. Click "Kirim OTP" button
3. **WATCH THE CONSOLE** for debug output

### Expected Console Output
```
[Controller] Sending HTTP request to get OTP...
[HttpHelper] POST /api/otp/request_otp
[HttpHelper] Status: 200
[HttpHelper] Response: {success: true, message: "OTP telah dikirim ke WhatsApp Anda"}
📤 [Controller] Emit Toast: Kode OTP telah dikirim ke WhatsApp Anda
📱 [Page] NOTIFICATION RECEIVED: Kode OTP telah dikirim ke WhatsApp Anda
🎨 [Page] Toast color: Color(0xff4caf50), Duration: 3s
✅ [CustomToast] Overlay found from context
🎨 [CustomToast] Creating OverlayEntry...
✅ [CustomToast] Overlay.insert() successful, auto-removing in 3s
```

### Step 4: Verify Visual Behavior
**You should see:**
- Green notification appears at top of screen with message about OTP
- Notification stays for 3 seconds
- Notification disappears automatically

**You should NOT see:**
- "No Overlay widget found" error
- Any red error messages in console

### Step 5: Continue OTP Flow
1. Enter OTP code if you have it (for actual testing)
2. Watch for next notification when verifying
3. If PIN reset, watch for success notification before navigation

---

## Common Issues & Solutions

### Issue 1: No Debug Output at All
**Symptom:** None of the print statements appear in console

**Causes to check:**
- App not actually running (check flutter run is still active)
- Console output is being filtered (look for log level filters)
- Controller method not being called

**Solution:**
- Ensure `flutter run` is active
- Check VS Code console filter at top right - don't filter out prints
- Add manual print in button click handler to verify code runs

### Issue 2: Controller Emit Visible, But No Page Receive
**Symptom:** See "📤 [Controller] Emit Toast" but NOT "📱 [Page] NOTIFICATION RECEIVED"

**Causes:**
- Page listener not properly attached
- Controller not registered before page tries to access it
- Migration to different page before listener attached

**Solution:**
- Check if page init shows: "✅ [Page] ForgotPinController registered"
- If NOT: Controller auto-binding failed, check Get.put() code
- If YES but still no receive: Listener is not running, check `ever()` syntax

### Issue 3: Page Receive Visible, But Toast Not Displaying
**Symptom:** See "📱 [Page] NOTIFICATION RECEIVED" but notification doesn't appear on screen

**Causes:**
- Overlay access failed and landed on fallback (Get.snackbar or ScaffoldMessenger)
- Get.snackbar failed and used ScaffoldMessenger  
- Custom toast created but insert() failed
- Wrong BuildContext passed to CustomToast

**Solution:**
- Look for "⚠️ [CustomToast] Overlay.of(context) failed"
- If no Overlay, should see one of the fallbacks
- If using fallback but still not visible: That fallback may not work on this device
- Try updating CustomToast fallback strategies

### Issue 4: Notification Flickers or Shows Multiple Times
**Symptom:** Toast appears multiple times for single OTP request

**Causes:**
- Listener receiving duplicate emissions
- Listener not property debounced
- Page re-initializing and creating multiple listeners

**Solution:**
- Page has duplicate prevention logic - should show: "⏭️ [Page] Skipping duplicate"
- If not skipping: Check `lastNotificationMessage` tracking
- Check if page initState running multiple times

### Issue 5: "No Overlay widget found" Error Still Appears
**Symptom:** Error message in red even with new code

**Causes:**
- Old code still running (hot reload issue)
- Calling Get.snackbar() from controller (should be event-based)
- Trying to show notification before widget tree ready

**Solution:**
- Full hot restart: `flutter run --restart`
- Not hot reload - full restart
- Check controller code doesn't have direct Get.snackbar() calls
- Ensure all notifications go through event system, not direct calls

---

## Debug Output Reference

### Debug Prefixes
- 🚀 = Initialization
- 📤 = Controller emitting
- 📱 = Page receiving  
- 🎨 = Toast display
- ✅ = Success
- ⚠️ = Warning/Fallback
- ❌ = Error
- 🔄 = Retrying alternative
- ⏱️ = Timing/Duration related
- ⏭️ = Skipping

### Where to Find Each Debug Statement

**In Controller (`forgot_pin_controller.dart`):**
- Line ~117: `📤 [Controller] Emit Toast` - when emitting notification
- Line ~119: Toast details (message, color, duration)

**In Page (`forgot_pin_input_nomor_hp.dart`):**
- Line ~19: `✅ [Page] ForgotPinController registered` - in initState
- Line ~22: `🔄 [Page] Setting up ever() listener` - listener setup
- Line ~28: `📱 [Page] NOTIFICATION RECEIVED` - when listener fires
- Line ~35: `⏭️ [Page] Skipping duplicate` - duplicate prevention
- Line ~44: `🎨 [Page] Toast.show() called` - before CustomToast.show()

**In CustomToast (`custom_toast.dart`):**
- Line ~13: `✅ [CustomToast] Overlay found from context` - primary strategy
- Line ~17: `⚠️ [CustomToast] Overlay.of(context) failed` - failed primary
- Line ~23: `🔄 [CustomToast] Trying Get.context fallback` - fallback 1
- Line ~32: `🔄 [CustomToast] Using Get.snackbar fallback` - fallback 2
- Line ~37: `✅ [CustomToast] Get.snackbar shown` - fallback 2 success
- Line ~41: `✅ [CustomToast] ScaffoldMessenger shown` - fallback 3
- Line ~50: `🎨 [CustomToast] Creating OverlayEntry` - widget creation
- Line ~126: `✅ [CustomToast] Overlay.insert() successful` - insert success
- Line ~159: `⏱️ [CustomToast] Duration expired` - auto-remove timer
- Line ~164: `✅ [CustomToast] Overlay removed` - removal confirmed

---

## What to Report If Still Broken

If notification still doesn't appear after all fixes, provide:

1. **Full console output** from "flutter run" to when you click Kirim OTP
2. **Exact error message** if any red text appears
3. **Screenshots** showing:
   - What you see on the screen
   - Which notifications appeared
   - Any error dialogs
4. **Console output around this point:**
   ```
   📤 [Controller] Emit Toast: ...
   [LOOK HERE - What messages appear next?]
   ```

---

## Quick Testing Command

If you want to isolate just the OTP flow:

```bash
# Full restart to ensure latest code
flutter run --no-fast-start

# Then:
# 1. Navigate to Lupa PIN
# 2. Enter phone number
# 3. Click Kirim OTP
# 4. Watch console and screen carefully
```

---

## Summary Checklist

- [ ] See "🚀 [Controller] ForgotPinController initialized"
- [ ] See "✅ [Page] ForgotPinController registered" when opening page
- [ ] See "📤 [Controller] Emit Toast" when clicking Kirim OTP
- [ ] See "📱 [Page] NOTIFICATION RECEIVED" in console
- [ ] See notification appear on screen as green bar at top
- [ ] See "✅ [CustomToast] Overlay.insert() successful"
- [ ] Notification disappears after 3 seconds
- [ ] No red error messages in console

If all above boxes checked ✓, notification system is working correctly!
