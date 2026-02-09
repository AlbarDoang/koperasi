<?php

// Set timezone ke Indonesia (UTC+7) untuk consistency
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

/**
 * Helper to safely create notifications with filtering and dedupe.
 */
function safe_create_notification($connect, $id_pengguna, $type, $title, $message, $data_json = null) {
    // CRITICAL LOGGING - Entry point
    error_log("[safe_create_notification] ENTRY user={$id_pengguna} type={$type} title={$title}");
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [safe_create_notification] ENTRY user={$id_pengguna} type={$type} title={$title} msg_len=" . strlen($message) . "\n", FILE_APPEND);
    
    $lower_title = mb_strtolower($title);
    $lower_msg = mb_strtolower($message);

    // Exclusion keywords - notifications containing these will be skipped
    // NOTE: Do NOT include legitimate business messages like "sedang diproses"
    // as they are used in withdrawal and other valid notification types
    $exclude_keywords = ['cashback'];  // Only exclude actual spam/irrelevant notifications
    foreach ($exclude_keywords as $kw) {
        if (strpos($lower_title, $kw) !== false || strpos($lower_msg, $kw) !== false) {
            @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " SKIPPED (excluded) user={$id_pengguna} title={$title} msg={$message}\n", FILE_APPEND);
            error_log("[notif_helper] SKIPPED_EXCLUDED keyword={$kw} user={$id_pengguna} type={$type}");
            return false;
        }
    }

    // Block legacy notification titles that should no longer be emitted by the system
    // e.g., old legacy "Pengajuan Setoran Tabungan" which causes ghost/duplicate entries
    if (stripos($title, 'Pengajuan Setoran Tabungan') !== false) {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " SKIPPED (legacy_blocked) user={$id_pengguna} title={$title} msg={$message}\n", FILE_APPEND);
        error_log("[notif_helper] SKIPPED_LEGACY title={$title} user={$id_pengguna}");
        return false;
    }

    // EXCEPTION: Always allow withdrawal result notifications (approval/rejection)
    // These are critical transaction updates that users MUST see immediately
    if ($type === 'withdrawal_approved' || $type === 'withdrawal_rejected') {
        error_log("[safe_create_notification] WITHDRAWAL_EXCEPTION TRIGGERED user={$id_pengguna} type={$type}");
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " [safe_create_notification] WITHDRAWAL_EXCEPTION type={$type} user={$id_pengguna} title={$title}\n", FILE_APPEND);
        
        // Skip duplicate/mulai_id checking for withdrawal results - create them immediately
        $stmt_ins = $connect->prepare("INSERT INTO notifikasi (id_pengguna, type, title, message, data, read_status, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        if ($stmt_ins) {
            error_log("[safe_create_notification] WITHDRAWAL_STMT_PREPARED user={$id_pengguna}");
            $stmt_ins->bind_param('issss', $id_pengguna, $type, $title, $message, $data_json);
            $ok = $stmt_ins->execute();
            if ($ok) {
                $nid = $connect->insert_id;
                error_log("[safe_create_notification] WITHDRAWAL_NOTIF_CREATED SUCCESS nid={$nid} user={$id_pengguna} type={$type}");
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [safe_create_notification] WITHDRAWAL_CREATED nid={$nid} user={$id_pengguna} type={$type} title={$title}\n", FILE_APPEND);
                @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " [safe_create_notification] WITHDRAWAL_SUCCESS nid={$nid} user={$id_pengguna}\n", FILE_APPEND);
                $stmt_ins->close();
                return $nid;
            } else {
                error_log("[safe_create_notification] WITHDRAWAL_NOTIF_INSERT_FAILED user={$id_pengguna} stmt_err={$stmt_ins->error}");
                @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " [safe_create_notification] WITHDRAWAL_INSERT_FAILED user={$id_pengguna} err={$stmt_ins->error}\n", FILE_APPEND);
                $stmt_ins->close();
                return false;
            }
        } else {
            error_log("[safe_create_notification] WITHDRAWAL_NOTIF_PREPARE_FAILED user={$id_pengguna} db_err={$connect->error}");
            @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " [safe_create_notification] WITHDRAWAL_PREPARE_FAILED user={$id_pengguna} err={$connect->error}\n", FILE_APPEND);
            return false;
        }
    }

    // Prevent duplicate notifications within the same "mulai_nabung" context.
    // If caller provided $data_json and it contains a 'mulai_id', prefer a strict check
    // looking for existing notifications with the same user, type, title and same
    // JSON value $.mulai_id (and same status when provided). This ensures a single
    // notification per action regardless of timing or other JSON fields.
    $mulai_id = null;
    $notif_status = null;
    if (!empty($data_json)) {
        $decoded = json_decode($data_json, true);
        if (is_array($decoded)) {
            if (isset($decoded['mulai_id'])) $mulai_id = (string)$decoded['mulai_id'];
            if (isset($decoded['status'])) $notif_status = (string)$decoded['status'];
        }
    }

    if ($mulai_id !== null) {
        // Dedupe: check if EXACT SAME message exists for same mulai_id
        // This allows updates to the message (e.g., from "Pengajuan Setoran Anda..." to "Pengajuan Setoran Qurban Anda...")
        $q = "SELECT id, created_at, message FROM notifikasi WHERE id_pengguna = ? AND type = ? AND title = ? AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.mulai_id')) = ? ORDER BY created_at DESC LIMIT 1";
        $s = $connect->prepare($q);
        if ($s) {
            $s->bind_param('isss', $id_pengguna, $type, $title, $mulai_id);
            $s->execute();
            $r = $s->get_result();
            if ($row = $r->fetch_assoc()) {
                // If message is DIFFERENT from existing, allow new notification (permit updates)
                // Only skip if EXACT message already exists
                if ($row['message'] === $message) {
                    @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " SKIPPED (dup:exact_msg) user={$id_pengguna} type={$type} title={$title} mulai_id={$mulai_id} last={$row['created_at']}\n", FILE_APPEND);
                    $s->close();
                    return false;
                }
                // Message is different - allow creation (this is an update)
                @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " ALLOWED (msg_update) user={$id_pengguna} title={$title} old_msg={$row['message']} new_msg={$message}\n", FILE_APPEND);
            }
            $s->close();
        }
    }

    // Existing fallback duplicate logic: same title+message for the same user within 2 minutes
    // FIX: Use database time functions to avoid timezone issues
    // Also include type in the check to distinguish different notification types
    if (!empty($data_json)) {
        $stmt = $connect->prepare("SELECT id, created_at FROM notifikasi WHERE id_pengguna = ? AND type = ? AND title = ? AND message = ? AND COALESCE(data,'') = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('issss', $id_pengguna, $type, $title, $message, $data_json);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $created = $row['created_at'];
                @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " SKIPPED (duplicate:data) user={$id_pengguna} type={$type} title={$title} msg={$message} data={$data_json} last={$created}\n", FILE_APPEND);
                $stmt->close();
                return false;
            }
            $stmt->close();
        }
    } else {
        $stmt = $connect->prepare("SELECT id, created_at FROM notifikasi WHERE id_pengguna = ? AND type = ? AND title = ? AND message = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE) ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('isss', $id_pengguna, $type, $title, $message);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $created = $row['created_at'];
                @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " SKIPPED (duplicate) user={$id_pengguna} type={$type} title={$title} msg={$message} last={$created}\n", FILE_APPEND);
                // Debug: log duplicate skip to error_log for visibility during debug mode
                error_log("[notif_helper] SKIPPED_DUPLICATE user={$id_pengguna} type={$type} title={$title} last={$created}");
                $stmt->close();
                return false;
            }
            $stmt->close();
        }
    }

    // Insert notification
    $stmt_ins = $connect->prepare("INSERT INTO notifikasi (id_pengguna, type, title, message, data, read_status, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    if ($stmt_ins) {
        $stmt_ins->bind_param('issss', $id_pengguna, $type, $title, $message, $data_json);
        $ok = $stmt_ins->execute();
        if ($ok) {
            $nid = $connect->insert_id;
            error_log("[safe_create_notification] NOTIF_CREATED nid={$nid} user={$id_pengguna} type={$type} title={$title}");
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [safe_create_notification] NOTIF_CREATED id={$nid} user={$id_pengguna} title={$title} msg='{$message}' data={$data_json}\n", FILE_APPEND);
            $stmt_ins->close();
            return $nid;
        } else {
            error_log("[safe_create_notification] ERROR_INSERT user={$id_pengguna} title={$title} stmt_err={$stmt_ins->error}");
            @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " ERROR_INSERT user={$id_pengguna} title={$title} err={$stmt_ins->error}\n", FILE_APPEND);
            // Debug: write to PHP error log so failures are visible
            error_log("[safe_create_notification] ERROR_INSERT user={$id_pengguna} title={$title} stmt_err={$stmt_ins->error} connect_err={$connect->error}");
            $stmt_ins->close();
            return false;
        }
    } else {
        error_log("[safe_create_notification] PREPARE_FAILED user={$id_pengguna} connect_err={$connect->error}");
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " PREPARE_FAILED user={$id_pengguna} err={$connect->error}\n", FILE_APPEND);
        // Debug: write to PHP error log so failures are visible
        error_log("[safe_create_notification] PREPARE_FAILED user={$id_pengguna} connect_err={$connect->error}");
        return false;
    }
}

// Lookup helper removed: performing pengguna lookup inline in callers to avoid cross-file signature issues.

/**
 * Create a standardized notification for a "Mulai Nabung" transaction.
 * `status` may be 'berhasil' or 'ditolak' and determines the title/message.
 * Uses `safe_create_notification` for filtering and dedupe. If a created timestamp
 * is provided, it will update the `notifikasi.created_at` to match the transaction time.
 *
 * Returns the notification id on success, or false on failure/skipped.
 */
function create_mulai_nabung_notification($connect, $id_pengguna, $mulai_id, $tabungan_masuk_id = null, $created_at = null, $status = 'berhasil', $jumlah = null, $jenis_tabungan = null) {
    // Debug logging
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [create_mulai_nabung_notification] CALLED: user={$id_pengguna} mulai_id={$mulai_id} status={$status} jumlah={$jumlah} jenis_tabungan={$jenis_tabungan}\n", FILE_APPEND);
    
    // Build amount text
    $amount_text = '';
    if ($jumlah !== null && $jumlah !== '' && $jumlah != 0) {
        $amt = number_format((float)$jumlah, 0, ',', '.');
        $amount_text = ' sebesar Rp ' . $amt;
    }

    // Build jenis_tabungan text - just the name without prefix
    $jenis_display = '';
    if (!empty($jenis_tabungan) && $jenis_tabungan !== '0') {
        $jenis_display = $jenis_tabungan;
    }

    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [create_mulai_nabung_notification] BUILT: jenis_display='{$jenis_display}' amount_text='{$amount_text}'\n", FILE_APPEND);

    if ($status === 'berhasil') {
        // Format: "Pengajuan Setoran [Jenis] Anda sebesar Rp [Amount] disetujui, silahkan cek saldo di halaman Tabungan"
        $title = 'Setoran Tabungan Disetujui';
        $message = 'Pengajuan Setoran' . ($jenis_display ? ' ' . $jenis_display : '') . ' Anda' . $amount_text . ' disetujui, silahkan cek saldo di halaman Tabungan';
    } else {
        // Format: "Pengajuan Setoran [Jenis] Anda sebesar Rp [Amount] ditolak, silahkan hubungi admin untuk informasi lebih lanjut."
        $title = 'Setoran Tabungan Ditolak';
        $message = 'Pengajuan Setoran' . ($jenis_display ? ' ' . $jenis_display : '') . ' Anda' . $amount_text . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
    }

    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [create_mulai_nabung_notification] FINAL MESSAGE[{$status}]: '{$message}' (jenis='{$jenis_display}' amount='{$amount_text}')\n", FILE_APPEND);

    $data = json_encode([
        'mulai_id' => intval($mulai_id),
        'tabungan_masuk_id' => ($tabungan_masuk_id !== null ? intval($tabungan_masuk_id) : null),
        'status' => $status,
        'amount' => $jumlah !== null ? floatval($jumlah) : null,
        'jenis_tabungan' => $jenis_tabungan !== null ? $jenis_tabungan : null,
    ]);

    // Use specific type 'mulai_nabung' so approval notifications are tied to the mulai_nabung flow
    $nid = safe_create_notification($connect, $id_pengguna, 'mulai_nabung', $title, $message, $data);

    if ($nid !== false && $created_at) {
        $upd = $connect->prepare("UPDATE notifikasi SET created_at = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $created_at, $nid);
            $upd->execute();
            $upd->close();
        }
    }

    if ($nid === false) {
        // Debug: ensure failures are visible in PHP error log
        error_log("[notif_helper] create_mulai_nabung_notification FAILED user={$id_pengguna} mulai_id={$mulai_id} tabungan_masuk_id={$tabungan_masuk_id} status={$status} data={$data} last_connect_err={$connect->error}");
    }

    return $nid;
}

/**
 * Resolve a pengguna.id from mulai_nabung fields (id_tabungan, nomor_hp, nama_pengguna)
 * Returns integer id or null if not found.
 */
function resolve_pengguna_id_from_mulai($connect, $id_tabungan, $nomor_hp = null, $nama_pengguna = null) {
    // 1) Try pengguna.id_tabungan if available
    try {
        $col_check = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
        $has_id_tabungan = ($col_check && $col_check->num_rows > 0);
    } catch (Exception $e) {
        $has_id_tabungan = false;
    }

    if ($has_id_tabungan && $id_tabungan !== null && $id_tabungan !== '') {
        $s = $connect->prepare("SELECT id FROM pengguna WHERE id_tabungan = ? LIMIT 1");
        if ($s) {
            $s->bind_param('s', $id_tabungan);
            $s->execute();
            $r = $s->get_result();
            if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id']); $s->close(); return $id; }
            $s->close();
        }
    }

    // 2) Try numeric match to pengguna.id
    $id_int = intval($id_tabungan);
    if ($id_int > 0) {
        $s = $connect->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
        if ($s) {
            $s->bind_param('i', $id_int);
            $s->execute();
            $r = $s->get_result();
            if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id']); $s->close(); return $id; }
            $s->close();
        }
    }

    // 3) Try matching by phone number
    if (!empty($nomor_hp)) {
        $s = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
        if ($s) {
            $s->bind_param('s', $nomor_hp);
            $s->execute();
            $r = $s->get_result();
            if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id']); $s->close(); return $id; }
            $s->close();
        }
    }

    // 4) Try matching by exact or like name
    if (!empty($nama_pengguna)) {
        $name = trim($nama_pengguna);
        $s = $connect->prepare("SELECT id FROM pengguna WHERE nama_lengkap = ? LIMIT 1");
        if ($s) {
            $s->bind_param('s', $name);
            $s->execute();
            $r = $s->get_result();
            if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id']); $s->close(); return $id; }
            $s->close();
        }
        // Fallback to LIKE
        $like = "%" . $connect->real_escape_string($name) . "%";
        $q = "SELECT id FROM pengguna WHERE nama_lengkap LIKE ? LIMIT 1";
        $s2 = $connect->prepare($q);
        if ($s2) {
            $s2->bind_param('s', $like);
            $s2->execute();
            $r = $s2->get_result();
            if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id']); $s2->close(); return $id; }
            $s2->close();
        }
    }

    // 5) If a tabungan table exists, try to resolve via tabungan.id -> id_pengguna
    try {
        $chk = $connect->query("SHOW TABLES LIKE 'tabungan'");
        if ($chk && $chk->num_rows > 0) {
            $s = $connect->prepare("SELECT id_pengguna FROM tabungan WHERE id = ? LIMIT 1");
            if ($s) {
                $s->bind_param('i', $id_int);
                $s->execute();
                $r = $s->get_result();
                if ($r && $r->num_rows > 0) { $id = intval($r->fetch_assoc()['id_pengguna']); $s->close(); return $id; }
                $s->close();
            }
        }
    } catch (Exception $e) {
        // ignore
    }

    return null;
}

/**
 * Create a 'Setoran Diproses' notification for the user when they submit cash.
 * Returns notification id or false on skip/failure.
 */
function create_setoran_diproses_notification($connect, $id_pengguna, $mulai_id = null, $created_at = null, $jumlah = null) {
    // Formal, consistent notification for the initial submission
    $title = 'Pengajuan Setoran Dikirim';
    $message = 'Pengajuan Setoran Anda berhasil dikirim dan sedang menunggu persetujuan dari admin.';

    // Match data structure used by create_mulai_nabung_notification so mobile filters recognize it
    $data = json_encode([
        'mulai_id' => $mulai_id !== null ? intval($mulai_id) : null,
        'tabungan_masuk_id' => null,
        'status' => 'menunggu_admin',
        'amount' => $jumlah !== null ? floatval($jumlah) : null,
    ]);

    // Use same normalized type 'tabungan'
    $nid = safe_create_notification($connect, $id_pengguna, 'tabungan', $title, $message, $data);

    if ($nid !== false && $created_at) {
        $upd = $connect->prepare("UPDATE notifikasi SET created_at = ? WHERE id = ?");
        if ($upd) {
            $upd->bind_param('si', $created_at, $nid);
            $upd->execute();
            $upd->close();
        }
    }

    if ($nid === false) {
        error_log("[notif_helper] create_setoran_diproses_notification FAILED user={$id_pengguna} mulai_id={$mulai_id} last_connect_err={$connect->error}");
    }

    return $nid;
}

// ============================================================================
// WITHDRAWAL NOTIFICATION HELPERS - Professional Fintech Standard
// ============================================================================
// These helpers provide centralized notification creation for the withdrawal
// system (pencairan tabungan). They ensure consistent notification types,
// proper deduplication, and clear messaging.

/**
 * Create notification when user requests a withdrawal (pending state).
 * 
 * This notification informs the user that their withdrawal request has been
 * submitted and is awaiting admin approval.
 * 
 * Usage:
 *   $nid = create_withdrawal_pending_notification($connect, $user_id, 'Tabungan Qurban', 100000, 42);
 * 
 * @param mysqli $connect Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param string $jenis_name Name of the savings type (for display)
 * @param float $amount Withdrawal amount
 * @param int $tab_keluar_id tabungan_keluar.id (for reference/dedupe)
 * @return int|false Notification ID or false on skip/error
 */
function create_withdrawal_pending_notification($connect, $user_id, $jenis_name, $amount, $tab_keluar_id) {
    error_log("[create_withdrawal_pending_notification] CALLED user={$user_id} jenis={$jenis_name} amount={$amount} tab_keluar_id={$tab_keluar_id}");
    if (!$connect) return false;

    $user_id = intval($user_id);
    $amount = floatval($amount);
    $tab_keluar_id = intval($tab_keluar_id);

    $title = 'Pengajuan Pencairan Dikirim';
    $amountFormatted = number_format($amount, 0, ',', '.');
    $message = "Pengajuan pencairan sebesar Rp {$amountFormatted} dari Tabungan {$jenis_name} sedang menunggu persetujuan dari admin.";

    $data = json_encode([
        'tabungan_keluar_id' => $tab_keluar_id,
        'jenis_name' => $jenis_name,
        'amount' => $amount,
        'status' => 'pending',
    ]);

    // Create notification with type 'withdrawal_pending' for proper classification
    $nid = safe_create_notification($connect, $user_id, 'withdrawal_pending', $title, $message, $data);

    if ($nid === false) {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_PENDING_FAILED user={$user_id} tab_keluar_id={$tab_keluar_id} err=" . ($connect->error ?? 'unknown') . "\n", FILE_APPEND);
    } else {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_PENDING user={$user_id} tab_keluar_id={$tab_keluar_id} nid={$nid}\n", FILE_APPEND);
    }

    return $nid;
}

/**
 * Create notification when withdrawal is approved by admin.
 * 
 * This notification informs the user that their withdrawal has been approved
 * and the amount has been credited to their wallet (saldo_bebas).
 * 
 * Usage:
 *   $nid = create_withdrawal_approved_notification($connect, $user_id, 'Tabungan Qurban', 100000, 250000, 42);
 * 
 * @param mysqli $connect Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param string $jenis_name Name of the savings type (for display)
 * @param float $amount Withdrawal amount
 * @param float $new_saldo New saldo_bebas (pengguna.saldo) after credit
 * @param int $tab_keluar_id tabungan_keluar.id (for reference/dedupe)
 * @return int|false Notification ID or false on skip/error
 */
function create_withdrawal_approved_notification($connect, $user_id, $jenis_name, $amount, $new_saldo, $tab_keluar_id) {
    error_log("[create_withdrawal_approved_notification] CALLED user={$user_id} jenis={$jenis_name} amount={$amount} new_saldo={$new_saldo} tab_keluar_id={$tab_keluar_id}");
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [create_withdrawal_approved_notification] CALLED user={$user_id}\n", FILE_APPEND);
    if (!$connect) return false;

    $user_id = intval($user_id);
    $amount = floatval($amount);
    $new_saldo = floatval($new_saldo);
    $tab_keluar_id = intval($tab_keluar_id);

    $title = 'Pencairan Disetujui';
    $amountFormatted = number_format($amount, 0, ',', '.');
    $saldoFormatted = number_format($new_saldo, 0, ',', '.');
    $message = "Pencairan sebesar Rp {$amountFormatted} dari Tabungan {$jenis_name} telah disetujui dan sudah ditambahkan ke saldo bebas Anda. Saldo bebas saat ini Rp {$saldoFormatted}";

    $data = json_encode([
        'tabungan_keluar_id' => $tab_keluar_id,
        'jenis_name' => $jenis_name,
        'amount' => $amount,
        'new_saldo' => $new_saldo,
        'status' => 'approved',
    ]);

    // Use safe_create_notification to prevent duplicate notifications for same approval
    $nid = safe_create_notification($connect, $user_id, 'withdrawal_approved', $title, $message, $data);

    if ($nid === false) {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_APPROVED_FAILED user={$user_id} tab_keluar_id={$tab_keluar_id} err=" . ($connect->error ?? 'unknown') . "\n", FILE_APPEND);
    } else {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_APPROVED user={$user_id} tab_keluar_id={$tab_keluar_id} nid={$nid} saldo={$new_saldo}\n", FILE_APPEND);
    }

    return $nid;
}

/**
 * Create notification when withdrawal is rejected by admin.
 * 
 * This notification informs the user that their withdrawal request has been
 * rejected and explains the reason. No saldo change occurs.
 * 
 * Usage:
 *   $nid = create_withdrawal_rejected_notification($connect, $user_id, 'Tabungan Qurban', 100000, 'Data tidak lengkap', 42);
 * 
 * @param mysqli $connect Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param string $jenis_name Name of the savings type (for display)
 * @param float $amount Withdrawal amount
 * @param string $reason Rejection reason
 * @param int $tab_keluar_id tabungan_keluar.id (for reference/dedupe)
 * @return int|false Notification ID or false on skip/error
 */
function create_withdrawal_rejected_notification($connect, $user_id, $jenis_name, $amount, $reason, $tab_keluar_id) {
    error_log("[create_withdrawal_rejected_notification] CALLED user={$user_id} jenis={$jenis_name} amount={$amount} reason={$reason} tab_keluar_id={$tab_keluar_id}");
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [create_withdrawal_rejected_notification] CALLED user={$user_id}\n", FILE_APPEND);
    if (!$connect) return false;

    $user_id = intval($user_id);
    $amount = floatval($amount);
    $tab_keluar_id = intval($tab_keluar_id);
    $reason = trim($reason) ?: 'Tidak ada keterangan alasan penolakan';

    $title = 'Pencairan Ditolak';
    $amountFormatted = number_format($amount, 0, ',', '.');
    $message = "Pencairan sebesar Rp {$amountFormatted} dari Tabungan {$jenis_name} ditolak, Silahkan hubungi admin untuk informasi lebih lanjut. Alasan: {$reason}";

    $data = json_encode([
        'tabungan_keluar_id' => $tab_keluar_id,
        'jenis_name' => $jenis_name,
        'amount' => $amount,
        'reason' => $reason,
        'status' => 'rejected',
    ]);

    // Use safe_create_notification to prevent duplicate rejection notifications
    $nid = safe_create_notification($connect, $user_id, 'withdrawal_rejected', $title, $message, $data);

    if ($nid === false) {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_REJECTED_FAILED user={$user_id} tab_keluar_id={$tab_keluar_id} err=" . ($connect->error ?? 'unknown') . "\n", FILE_APPEND);
    } else {
        @file_put_contents(__DIR__ . '/notification_filter.log', date('c') . " CREATE_WITHDRAWAL_REJECTED user={$user_id} tab_keluar_id={$tab_keluar_id} nid={$nid} reason={$reason}\n", FILE_APPEND);
    }

    return $nid;
}

?>
