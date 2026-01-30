<?php
/**
 * QUICK REFERENCE: Fonnte Integration Configuration
 * 
 * Location: /gas/gas_web/config/fontte_constants.php
 * 
 * All Fonnte-related configuration is now centralized in a single file.
 * Include this file in any script that needs to send OTP or WhatsApp messages.
 */

// ============================================================================
// CURRENT CONFIGURATION
// ============================================================================

define('FONNTE_TOKEN', 'fS4eaEGMWVTXHanvnfUW');
define('FONNTE_API_ENDPOINT', 'https://api.fonnte.com/send');
define('FONNTE_ADMIN_WA', '6287822451601');

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

/*

// Example 1: Send OTP via Flutter API
require_once __DIR__ . '/../config/fontte_constants.php';
require_once __DIR__ . '/../otp_helper.php';

$no_hp = '081990608817';        // User's phone number (local format)
$otp = generateOTP();            // Generate 6-digit OTP
$result = sendOTPViaFonnte($no_hp, $otp, FONNTE_TOKEN);

if ($result['success']) {
    echo "OTP sent successfully!";
} else {
    echo "Error: " . $result['message'];
}

// Example 2: Send custom WhatsApp message
require_once __DIR__ . '/../config/fontte_constants.php';
require_once __DIR__ . '/../otp_helper.php';

$message = "Hello, your account has been approved!";
$result = sendWhatsAppMessage(FONNTE_ADMIN_WA, $message, FONNTE_TOKEN);

// Example 3: Access configuration
echo "Admin number: " . FONNTE_ADMIN_WA;
echo "API Endpoint: " . FONNTE_API_ENDPOINT;

*/

// ============================================================================
// FILES USING THIS CONFIG
// ============================================================================

/*
✓ gas_web/aktivasi_akun.php
✓ gas_web/flutter_api/aktivasi_akun.php
✓ gas_web/flutter_api/forgot_password.php
✓ gas_web/login/admin/aktivasi_akun/api_kirim_otp.php
✓ gas_web/login/admin/approval/approve_user_process.php
*/

// ============================================================================
// TROUBLESHOOTING
// ============================================================================

/*

Q: OTP tidak terkirim
A: Check:
   1. FONNTE_TOKEN valid di Fonnte dashboard
   2. FONNTE_API_ENDPOINT = https://api.fonnte.com/send (HTTPS!)
   3. Target phone number format = 62xx... (international)
   4. Fonnte quota masih tersedia

Q: Error "Could not resolve host: api.fonteapi.com"
A: Endpoint API SALAH. Gunakan:
   ✓ https://api.fonnte.com/send
   ✗ https://api.fonteapi.com/send (WRONG)

Q: Bagaimana mengubah token?
A: Edit file ini:
   gas_web/config/fontte_constants.php
   Kemudian restart web server.

Q: Bagaimana mengubah nomor admin?
A: Edit FONNTE_ADMIN_WA di file:
   gas_web/config/fontte_constants.php

*/

?>
