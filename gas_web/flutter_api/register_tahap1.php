<?php
/**
 * API: Register Tahap 1
 * Menyimpan data awal pengguna ke tabel `pengguna`
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';
include 'helpers.php';

// Ensure no PHP error HTML is emitted to clients
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

// Register shutdown handler to capture shutdown errors and ensure JSON output
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
        if (function_exists('sendJsonResponse') && empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
            $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
            sendJsonResponse(false, 'Internal server error');
        }
    }
});

@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] START from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed. Use POST');
}

// Ambil data (mendukung form-data atau raw JSON)
$no_hp = getPostData('no_hp', getPostData('nohp'));
$kata_sandi = getPostData('kata_sandi', getPostData('password'));
$nama_lengkap = getPostData('nama_lengkap', getPostData('nama'));
$alamat_domisili = getPostData('alamat_domisili', getPostData('alamat'));
$tanggal_lahir = getPostData('tanggal_lahir', getPostData('tgl_lahir'));
$setuju_syarat = getPostData('setuju_syarat', 0);

// Validasi wajib: no_hp dan kata_sandi
if (empty($no_hp)) {
    sendJsonResponse(false, 'Nomor HP wajib diisi');
}

if (empty($kata_sandi)) {
    sendJsonResponse(false, 'Kata sandi wajib diisi');
}

// Sanitasi nomor hp: trim and normalize to local national format (08...)
$no_hp = trim($no_hp);
$no_hp = sanitizePhone($no_hp);
if (empty($no_hp)) {
    sendJsonResponse(false, 'Format nomor HP tidak valid. Masukkan 08... atau 62...');
}

// Pastikan koneksi tersedia
if (!isset($connect) || !$connect) {
    sendJsonResponse(false, 'Database connection error');
}

// Cek unik no_hp
$checkStmt = $connect->prepare('SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1');
if ($checkStmt) {
    $checkStmt->bind_param('s', $no_hp);
    $checkStmt->execute();
    $res = $checkStmt->get_result();
    if ($res && $res->num_rows > 0) {
        // Nomor sudah terdaftar - log for debugging
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Duplicate phone detected: {$no_hp} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);
        sendJsonResponse(false, 'Nomor HP sudah terdaftar');
        $checkStmt->close();
    }
    $checkStmt->close();
} else {
    // Prepare gagal
    sendJsonResponse(false, 'Query error');
}

// Hash password
$hashed = password_hash($kata_sandi, PASSWORD_DEFAULT);

// Normalize setuju_syarat ke 0/1
$setuju_syarat = (int) filter_var($setuju_syarat, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($setuju_syarat === null) {
    $setuju_syarat = 0;
}

// Siapkan insert. status_akun default 'draft'
$insertSql = "INSERT INTO pengguna (no_hp, kata_sandi, nama_lengkap, alamat_domisili, tanggal_lahir, setuju_syarat, status_akun, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'draft', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP())";
$stmt = $connect->prepare($insertSql);
if (!$stmt) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] prepare insert failed: " . ($connect->error ?? '') . "\n", FILE_APPEND);
    sendJsonResponse(false, 'Gagal menyiapkan query insert');
}

$recovered_from_exception = false;
$recovered_id = 0;
try {
    $stmt->bind_param('sssssi', $no_hp, $hashed, $nama_lengkap, $alamat_domisili, $tanggal_lahir, $setuju_syarat);
    $exec = $stmt->execute();
    if (!$exec) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] execute failed: " . $stmt->error . "\n", FILE_APPEND);
        $stmt_errno = $stmt->errno ?? 0;
        $stmt->close();
        if ($stmt_errno == 1062) {
            sendJsonResponse(false, 'Nomor HP sudah terdaftar (konflik)');
        }
        sendJsonResponse(false, 'Gagal menyimpan data pengguna: ' . $stmt->error);
    }
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] execute exception: " . $e->getMessage() . "\n", FILE_APPEND);
    $stmt_errno = $stmt->errno ?? 0;
    $stmt_error = $stmt->error ?? $e->getMessage();
    // Attempt recovery for duplicate primary / duplicate entry
    if (strpos($e->getMessage(), 'Duplicate entry') !== false || $stmt_errno == 1062) {
        $safe_no = $connect->real_escape_string($no_hp);
        $resLookup = $connect->query("SELECT id FROM pengguna WHERE no_hp='" . $safe_no . "' ORDER BY created_at DESC LIMIT 1");
        if ($resLookup && $rowLookup = $resLookup->fetch_assoc()) {
            $found_id = intval($rowLookup['id']);
            if ($found_id > 0) {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Recovered id={$found_id} after duplicate key\n", FILE_APPEND);
                $recovered_from_exception = true;
                $recovered_id = $found_id;
            }
        }
        if (!$recovered_from_exception) {
            sendJsonResponse(false, 'Nomor HP sudah terdaftar (konflik)');
        }
    } else {
        $stmt->close();
        sendJsonResponse(false, 'Gagal menyimpan data pengguna: ' . $stmt_error);
    }
}
if (!$recovered_from_exception) {
    $id = intval($connect->insert_id);
    $affected = $stmt->affected_rows;
    $stmt->close();
} else {
    $id = $recovered_id;
    $affected = 1;
}

// If insert_id is zero, try to recover:
if ($id <= 0) {
    // Log unexpected insert_id
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] insert_id=0; affected_rows={$affected}; attempting recovery by searching row by no_hp\n", FILE_APPEND);

    // Try to find the row by phone number (newest first)
    $safe_no = $connect->real_escape_string($no_hp);
    $res = $connect->query("SELECT id FROM pengguna WHERE no_hp='" . $safe_no . "' ORDER BY created_at DESC LIMIT 1");
    try {
        if ($res && $row = $res->fetch_assoc()) {
            $found_id = intval($row['id']);
            if ($found_id > 0) {
                $id = $found_id;
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Recovered id={$id} by lookup\n", FILE_APPEND);
            } else {
                // found id == 0: possible schema problem (id not AUTO_INCREMENT or NO_AUTO_VALUE_ON_ZERO)
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Found id=0 for phone {$no_hp}. Will attempt schema fix.\n", FILE_APPEND);

                // Attempt to assign a proper id to the 0 row specifically for this phone
                $resMax = $connect->query("SELECT COALESCE(MAX(id),0) as m FROM pengguna");
                $maxId = 0;
                if ($resMax && $rmax = $resMax->fetch_assoc()) $maxId = intval($rmax['m']);
                $newId = $maxId + 1;

                // Try targeted update by phone to avoid touching unrelated rows
                $safe_no = $connect->real_escape_string($no_hp);
                $upd = $connect->query("UPDATE pengguna SET id='" . $newId . "' WHERE id=0 AND no_hp='" . $safe_no . "' LIMIT 1");
                if ($upd && $connect->affected_rows > 0) {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Updated id 0 -> {$newId} for phone {$safe_no}\n", FILE_APPEND);
                    $id = $newId;
                } else {
                    // Targeted update didn't work; attempt to locate the exact row and try safer update by created_at
                    $selRow = $connect->query("SELECT id, created_at FROM pengguna WHERE no_hp='" . $safe_no . "' ORDER BY created_at DESC LIMIT 1");
                    if ($selRow && ($rowSel = $selRow->fetch_assoc())) {
                        $rowId = intval($rowSel['id']);
                        if ($rowId === 0) {
                            $created_at_val = $connect->real_escape_string($rowSel['created_at'] ?? '');
                            if ($created_at_val) {
                                $upd2 = $connect->query("UPDATE pengguna SET id='" . $newId . "' WHERE no_hp='" . $safe_no . "' AND created_at='" . $created_at_val . "' LIMIT 1");
                                if ($upd2 && $connect->affected_rows > 0) {
                                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Updated id 0 -> {$newId} (by created_at) for phone {$safe_no}\n", FILE_APPEND);
                                    $id = $newId;
                                } else {
                                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Failed to update id 0 (by created_at): " . $connect->error . "\n", FILE_APPEND);
                                }
                            } else {
                                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Row for phone {$safe_no} has id=0 but no created_at, cannot target update\n", FILE_APPEND);
                            }
                        } else {
                            // Another non-zero row exists - use that id
                            $id = $rowId;
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Using existing non-zero id {$id} from lookup for phone {$safe_no}\n", FILE_APPEND);
                        }
                    } else {
                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Failed to locate row to fix id for phone {$safe_no}\n", FILE_APPEND);
                    }
                }

                // Ensure id column is AUTO_INCREMENT when possible to prevent recurrence
                $colRes = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id'");
                $colInfo = $colRes ? $colRes->fetch_assoc() : null;
                $extra = $colInfo['Extra'] ?? '';
                if (strpos($extra, 'auto_increment') === false) {
                    // Try to modify the column to AUTO_INCREMENT (best-effort; may fail due to privileges)
                    try {
                        $pkRes = $connect->query("SHOW INDEX FROM pengguna WHERE Key_name = 'PRIMARY'");
                        $hasPrimary = ($pkRes && $pkRes->num_rows > 0);
                    } catch (Throwable $e) {
                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Failed to check PRIMARY KEY: " . $e->getMessage() . "\n", FILE_APPEND);
                        $hasPrimary = false;
                    }

                    if (!$hasPrimary) {
                        try {
                            $dbNameRes = $connect->query("SELECT DATABASE() as db");
                            $dbNameRow = $dbNameRes ? $dbNameRes->fetch_assoc() : null;
                            $dbName = $dbNameRow['db'] ?? '';
                            $dbEsc = $connect->real_escape_string($dbName);
                            $pkCountRes = $connect->query("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '" . $dbEsc . "' AND TABLE_NAME = 'pengguna' AND CONSTRAINT_TYPE = 'PRIMARY KEY'");
                            $pkCountRow = $pkCountRes ? $pkCountRes->fetch_assoc() : null;
                            $pkCount = intval($pkCountRow['c'] ?? 0);
                            if ($pkCount > 0) $hasPrimary = true;
                        } catch (Throwable $e) {
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] INFORMATION_SCHEMA check failed: " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    }

                    try {
                        if ($hasPrimary) {
                            $alterSql = "ALTER TABLE pengguna MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT";
                        } else {
                            $alterSql = "ALTER TABLE pengguna MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)";
                        }

                        if ($connect->query($alterSql)) {
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Successfully set id column AUTO_INCREMENT\n", FILE_APPEND);
                        } else {
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Failed to set AUTO_INCREMENT: " . $connect->error . "\n", FILE_APPEND);
                        }
                    } catch (Throwable $e) {
                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Exception while setting AUTO_INCREMENT: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
        } else {
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] No row found by phone lookup for recovery\n", FILE_APPEND);
        }
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Recovery block exception: " . $e->getMessage() . "\n", FILE_APPEND);
        // Ensure we return a JSON error instead of letting the exception bubble up and cause a fatal
        sendJsonResponse(false, 'Internal server error (recovery failed)');
    }
}

if ($id <= 0) {
    // If still not recovered, return an error with guidance for admins
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] Final recovery failed, id remains <= 0 for phone {$no_hp}\n", FILE_APPEND);
    sendJsonResponse(false, 'ID pengguna tidak ditemukan atau kosong. Minta admin periksa kolom id (AUTO_INCREMENT) pada tabel pengguna.');
}

// Sukses
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap1] success id_pengguna: " . $id . "\n", FILE_APPEND);
sendJsonResponse(true, 'Register Tahap 1 berhasil', array('id_pengguna' => $id));
