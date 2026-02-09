<?php
/**
 * Flutter API Database Connection
 * File ini menggunakan koneksi database terpusat dari config/database.php
 */

// Set timezone ke Indonesia (UTC+7) - CRITICAL untuk waktu transaksi yang konsisten
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

// Include centralized database configuration
require_once dirname(__DIR__) . '/config/database.php';

// Variabel $connect, $con, dan $koneksi sudah tersedia dari database.php

// Setup error handler for debugging
if (!empty($GLOBALS['FLUTTER_API_DEBUG_MODE'])) {
    // Custom error handler to show errors instead of silencing them
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $errorLevel = '';
        switch($errno) {
            case E_ERROR: $errorLevel = 'ERROR'; break;
            case E_WARNING: $errorLevel = 'WARNING'; break;
            case E_PARSE: $errorLevel = 'PARSE'; break;
            case E_NOTICE: $errorLevel = 'NOTICE'; break;
            case E_CORE_ERROR: $errorLevel = 'CORE_ERROR'; break;
            case E_CORE_WARNING: $errorLevel = 'CORE_WARNING'; break;
            case E_COMPILE_ERROR: $errorLevel = 'COMPILE_ERROR'; break;
            case E_COMPILE_WARNING: $errorLevel = 'COMPILE_WARNING'; break;
            case E_USER_ERROR: $errorLevel = 'USER_ERROR'; break;
            case E_USER_WARNING: $errorLevel = 'USER_WARNING'; break;
            case E_USER_NOTICE: $errorLevel = 'USER_NOTICE'; break;
            case E_STRICT: $errorLevel = 'STRICT'; break;
            case E_RECOVERABLE_ERROR: $errorLevel = 'RECOVERABLE_ERROR'; break;
            case E_DEPRECATED: $errorLevel = 'DEPRECATED'; break;
            case E_USER_DEPRECATED: $errorLevel = 'USER_DEPRECATED'; break;
            default: $errorLevel = 'UNKNOWN('.$errno.')'; break;
        }
        
        @error_log("[connection.php] PHP {$errorLevel}: {$errstr} in {$errfile}:{$errline}");
        
        // Return false to let PHP handle it internally too
        return false;
    });
}
// Buffer any unexpected output and log it for debugging.
// BUT: If FLUTTER_API_DEBUG_MODE is true, show errors for debugging

// Check if debug mode is enabled BEFORE setting display_errors
$FLUTTER_API_DEBUG_ENABLED = !empty($GLOBALS['FLUTTER_API_DEBUG_MODE']);

if (!$FLUTTER_API_DEBUG_ENABLED) {
    ini_set('display_errors', '0');
}
if (!function_exists('flutter_api_setup_output_buffer')) {
    function flutter_api_setup_output_buffer() {
        if (ob_get_level() === 0) ob_start();
        register_shutdown_function(function() {
            $buf = '';
            if (ob_get_level()) {
                $buf = ob_get_clean();
            }

            $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Check if debug mode is enabled
            $debugMode = !empty($GLOBALS['FLUTTER_API_DEBUG_MODE']);

            // Truncate buffer for log to avoid huge lines
            $bufForLog = mb_substr(trim($buf), 0, 2000);

            // If a script explicitly set the JSON output flag, echo the buffer (expected JSON)
            if (!empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Output flag present, echoing buffered output for {$script} {$uri} {$ip}: " . $bufForLog . "\n", FILE_APPEND);
                if (trim($buf) !== '') echo $buf;
                return;
            }

            if (trim($buf) !== '') {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Unexpected output from {$script} {$uri} {$ip}: " . $bufForLog . "\n", FILE_APPEND);

                // In DEBUG MODE, always show the error output
                if ($debugMode) {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] DEBUG MODE ON - echoing buffered output instead of fallback for {$script} {$uri} {$ip}\n", FILE_APPEND);
                    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
                    echo $buf;
                    return;
                }

                // Remove potential leading BOM (UTF-8 or U+FEFF) which can make JSON invalid
                $bufStripped = preg_replace('/^\xEF\xBB\xBF/', '', $buf);
                $bufStripped = preg_replace('/^\x{FEFF}/u', '', $bufStripped);
                if ($bufStripped !== $buf) {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Stripped leading BOM from buffered output for {$script} {$uri} {$ip}\n", FILE_APPEND);
                }

                // If buffer looks like valid JSON, pass it through and set the flag
                $trimmed = trim($bufStripped);
                $isJson = false;
                if ($trimmed !== '' && in_array(substr($trimmed,0,1), ['{','['])) {
                    $dec = json_decode($trimmed);
                    if ($dec !== null) $isJson = true;
                }

                if ($isJson) {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Buffered output is valid JSON; echoing for {$script} {$uri} {$ip}\n", FILE_APPEND);
                    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
                    echo $bufStripped;
                    return;
                }

                // DO NOT echo HTML or raw output to API clients — replace with a safe JSON error
                $fallback = json_encode(["success" => false, "message" => "Internal server error"]);
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Replacing unexpected output with fallback JSON for {$script} {$uri} {$ip}: " . $fallback . "\n", FILE_APPEND);
                if (!headers_sent()) header('Content-Type: application/json');
                // Ensure flag so further shutdown handlers don't inject another fallback
                $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
                echo $fallback;
                return;
            }

            // Detect if headers were already sent or connection aborted; in such
            // cases it's safer to not override output
            if (headers_sent() || connection_aborted()) {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Headers already sent or connection aborted, skipping fallback for {$script} {$uri} {$ip}\n", FILE_APPEND);
                return;
            }

            // No output was produced: inject a safe JSON error so clients do not
            // receive an empty body which triggers JSON parse errors on mobile.
            $fallback = json_encode(["success" => false, "message" => "Internal server error"]);
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [connection.php] Empty response, injecting fallback for {$script} {$uri} {$ip}: " . $fallback . "\n", FILE_APPEND);
            if (!headers_sent()) header('Content-Type: application/json');
            echo $fallback;
        });
    }
}
flutter_api_setup_output_buffer();
?>
