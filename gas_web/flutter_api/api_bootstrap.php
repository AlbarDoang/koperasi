<?php
// Central bootstrap for Flutter API endpoints
// Applies consistent error/display settings, includes connection & helpers
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

// Include connection which sets up output buffering and fallback handling
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/helpers.php';
// Storage config (KYC outside document root)
require_once __DIR__ . '/storage_config.php';

// Ensure a shutdown handler that logs and emits safe JSON if needed
if (!defined('API_BOOTSTRAP_SHUTDOWN')) {
    define('API_BOOTSTRAP_SHUTDOWN', true);
    register_shutdown_function(function() {
        $err = error_get_last();
        if ($err) {
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [api_bootstrap] SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
            if (function_exists('sendJsonResponse') && empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
                $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
                sendJsonResponse(false, 'Internal server error');
            }
        }
    });
}
