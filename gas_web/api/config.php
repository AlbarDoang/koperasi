<?php
/**
 * config.php - Konfigurasi Koneksi Database
 * Database: tabungan
 * Host: localhost
 * User: root
 */

// Database credentials
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'tabungan';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // Jangan tampilkan error detail di production
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'Koneksi database gagal'
    ]);
    exit;
}

// Set charset
$conn->set_charset('utf8mb4');

// Disable auto-commit untuk transaction safety
$conn->autocommit(false);

/**
 * Helper function: Send JSON response
 */
function send_json($status, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'status' => (bool)$status,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helper function: Validate required input
 */
function validate_required($fields) {
    foreach ($fields as $field) {
        if (!isset($_REQUEST[$field]) || $_REQUEST[$field] === '') {
            send_json(false, "Parameter $field harus diisi");
        }
    }
}

/**
 * Helper function: Sanitize integer
 */
function sanitize_int($value) {
    return intval($value);
}
