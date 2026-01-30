<?php
/**
 * Flutter API Database Connection
 * File ini menggunakan koneksi database terpusat dari config/database.php
 */

// Include centralized database configuration
require_once dirname(__DIR__) . '/config/database.php';

// Variabel $connect, $con, dan $koneksi sudah tersedia dari database.php

// Ensure PHP warnings/errors are not emitted as HTML that would break JSON APIs.
// Buffer any unexpected output and log it for debugging.
ini_set('display_errors', '0');
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
