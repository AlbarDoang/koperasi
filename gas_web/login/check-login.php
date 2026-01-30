<?php
session_start();

// Quick debug: dump raw incoming request so we can see what the browser actually sent
$dbgLog = __DIR__ . '/logs/raw_request_browser.log';
$dbg = [
    'time' => date('c'),
    'post' => $_POST,
    'input' => @file_get_contents('php://input'),
    'server' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? '',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
        'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? '',
    ],
];
if (isset($dbg['post']['passwordmu'])) $dbg['post']['passwordmu'] = '***';
@file_put_contents($dbgLog, json_encode($dbg, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

# check apakah ada akse post dari halaman login?, jika tidak kembali kehalaman depan
if (!isset($_POST['usernamemu'])) {
    exit();
}

// Start output buffering to prevent "headers already sent" problems
ob_start();
// detect ajax requests (header) or explicit ajax flag from client
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_POST['ajax_login']));

// Ensure logs directory exists
$logdir = __DIR__ . '/logs';
if (!is_dir($logdir)) @mkdir($logdir, 0755, true);

// Install a simple error handler and shutdown logger so PHP warnings/fatals
// are captured to a file for debugging AJAX failures.
set_error_handler(function ($severity, $message, $file, $line) use ($logdir) {
    $msg = date('c') . " - PHP ERROR: [$severity] $message in $file on line $line\n";
    @file_put_contents($logdir . '/check_login_php_errors.log', $msg, FILE_APPEND);
});

register_shutdown_function(function () use ($logdir) {
    $err = error_get_last();
    if ($err !== null) {
        $msg = date('c') . " - SHUTDOWN: {$err['message']} in {$err['file']} on line {$err['line']}\n";
        @file_put_contents($logdir . '/check_login_php_errors.log', $msg, FILE_APPEND);
    }
});

# set nilai default dari error,
$error = '';

require_once __DIR__ . '/koneksi/config.php';

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}
$hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);

$username = trim($_POST['usernamemu']);
$password = trim($_POST['passwordmu']);
$host = $hostname;
$ipa = $ip;

$username = trim($_POST['usernamemu']);
$password = trim($_POST['passwordmu']);

if (strlen($username) < 2) {
    # jika ada error dari kolom username yang kosong
    $error = 'Username tidak boleh kosong';
} else if (strlen($password) < 2) {
    # jika ada error dari kolom password yang kosong
    $error = 'Password Tidak boleh kosong';
} else {

    # Escape String, ubah semua karakter ke bentuk string
    $username = $koneksi->escape_string($username);
    $password = $koneksi->escape_string($password);

    # hash dengan md5
    $password = sha1($password);

    # SQL command untuk memilih data berdasarkan parameter $username dan $password yang 
    # di inputkan
    $sql = "SELECT id, nama, hak_akses, foto, last_login FROM user
            WHERE username='$username' 
            AND password='$password' LIMIT 1";

    $update = "UPDATE user SET last_login = CURRENT_TIMESTAMP() WHERE username='$username'";
    $insert = "INSERT INTO riwayat_login VALUES(NULL, '$username',CURRENT_TIMESTAMP(),'$host','$ipa')";
    $finish  = mysqli_query($con, $update);
    $finish1  = mysqli_query($con, $insert);

    # melakukan perintah
    $query = $koneksi->query($sql);

    # check query
    if (!$query) {
        die('Oops!! Database gagal ' . $koneksi->error);
    }

    # check hasil perintah
    if ($query->num_rows == 1) {
        # jika data yang dimaksud ada
        # maka ditampilkan
        $row = $query->fetch_assoc();

        # data nama disimpan di session browser
        $_SESSION['id_user']     = $row['id'];
        $_SESSION['nama_user'] = $row['nama'];
        $_SESSION['akses']     = $row['hak_akses'];
        $_SESSION['nama_foto'] = $row['foto'];

        if ($row['hak_akses'] == 'admin') {
            # data hak Admin di set
            $_SESSION['saya_admin'] = 'TRUE';
        } else if ($row['hak_akses'] == 'petugas') {
            # data hak operator di set
            $_SESSION['saya_petugas'] = 'TRUE';
        }

        # menuju halaman sesuai hak akses (auto-detect path)
        $redirect_url = $url . '/' . $_SESSION['akses'] . '/dashboard/';
        // ensure logs dir exists and write debug info to help trace redirect issues
        $logdir = __DIR__ . '/logs';
        if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
        @file_put_contents($logdir . '/check_login_debug.log', date('c') . " - redirect to: $redirect_url | headers_sent=" . (headers_sent() ? '1' : '0') . "\n", FILE_APPEND);
        // If request is AJAX, return JSON with redirect URL so client can navigate
        if ($isAjax) {
            // Capture any buffered output (stray whitespace/BOM/HTML) for debugging
            $prebuf = '';
            if (ob_get_length()) {
                $prebuf = ob_get_contents();
            }
            if (!empty($prebuf)) {
                @file_put_contents($logdir . '/prebuffer_success_' . time() . '.txt', $prebuf, FILE_APPEND);
            }
            // Discard any buffered output to guarantee clean JSON
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $resp = ['success' => true, 'redirect' => $redirect_url];
            // Log the request (avoid logging raw password) and response for debugging
            $safePost = $_POST;
            if (isset($safePost['passwordmu'])) $safePost['passwordmu'] = '***';
            @file_put_contents($logdir . '/check_login_requests.log', date('c') . " - REQUEST: " . json_encode($safePost) . "\nRESPONSE: " . json_encode($resp) . "\n---\n", FILE_APPEND);
            echo json_encode($resp);
            exit();
        }

        // Prefer header redirect for normal requests; add JS/meta fallback if headers already sent
        if (!headers_sent()) {
            header('Location: ' . $redirect_url);
            // flush buffer and exit
            ob_end_flush();
            exit();
        } else {
            // log headers-sent debug
            $log = __DIR__ . '/logs/check_login_error.log';
            @file_put_contents($log, date('c') . " - headers already sent when redirecting to $redirect_url\n", FILE_APPEND);
            echo '<script>window.location.href = ' . json_encode($redirect_url) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect_url, ENT_QUOTES) . '" /></noscript>';
            ob_end_flush();
            exit();
        }
    } else {
        # jika data yang dimaksud tidak ada
        $error = 'Username Atau Password Salah';

        // Hapus seluruh sesi yang mungkin tersisa (lebih aman)
        // lalu buat sesi baru untuk menyimpan pesan error (flash)
        $_error = $error; // simpan sementara
        session_unset();
        session_destroy();
        // Mulai sesi baru untuk menyimpan pesan error
        session_start();
        $_SESSION['error'] = $_error;
        // If AJAX request, return JSON error immediately
        if ($isAjax) {
            // Capture any buffered output (stray whitespace/BOM/HTML) for debugging
            $prebuf = '';
            if (ob_get_length()) {
                $prebuf = ob_get_contents();
            }
            if (!empty($prebuf)) {
                @file_put_contents($logdir . '/prebuffer_error_' . time() . '.txt', $prebuf, FILE_APPEND);
            }
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $resp = ['success' => false, 'error' => $_error];
            $safePost = $_POST;
            if (isset($safePost['passwordmu'])) $safePost['passwordmu'] = '***';
            @file_put_contents($logdir . '/check_login_requests.log', date('c') . " - REQUEST: " . json_encode($safePost) . "\nRESPONSE: " . json_encode($resp) . "\n---\n", FILE_APPEND);
            echo json_encode($resp);
            exit();
        }
    }
}

// Jika ada error yang belum di-redirect (biasanya sudah diset di atas),
// redirect kembali ke halaman login. Jika sesi sudah di-restart di blok
// error, ini hanya melakukan redirect tanpa menimpa sesi error.
if (!empty($error)) {
    if (empty($_SESSION['error'])) {
        $_SESSION['error'] = $error;
    }
    $redirect_url = $url . '/index.php';
    $logdir = __DIR__ . '/logs';
    if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
    @file_put_contents($logdir . '/check_login_debug.log', date('c') . " - error redirect to: $redirect_url | headers_sent=" . (headers_sent() ? '1' : '0') . " | error=" . $error . "\n", FILE_APPEND);
    if (!headers_sent()) {
        header('Location: ' . $redirect_url);
        ob_end_flush();
        exit();
    } else {
        $log = __DIR__ . '/logs/check_login_error.log';
        @file_put_contents($log, date('c') . " - headers already sent when redirecting to login index ($redirect_url)\n", FILE_APPEND);
        echo '<script>window.location.href = ' . json_encode($redirect_url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirect_url, ENT_QUOTES) . '" /></noscript>';
        ob_end_flush();
        exit();
    }
}
