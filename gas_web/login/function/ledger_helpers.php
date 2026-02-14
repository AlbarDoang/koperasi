<?php
// ledger_helpers.php - helper functions to insert ledger rows (masuk/keluar) and update pengguna.saldo
// Usage: include 'login/function/ledger_helpers.php'; then call insert_ledger_masuk($con, $user_id, $jumlah, $keterangan); or insert_ledger_keluar(...)

function has_table($con, $table) {
    $r = $con->query("SHOW TABLES LIKE '" . $con->real_escape_string($table) . "'");
    return ($r && $r->num_rows > 0);
}

// Wallet helpers: credit/debit wallet (pengguna.saldo) and move wallet -> tabungan
// These are intentionally separated from insert_ledger_* to preserve backward compatibility with callers.

function wallet_credit($con, $user_id, $jumlah, $keterangan = '') {
    if (!$con) return false;

    // safely add to pengguna.saldo
    $stmt = $con->prepare("UPDATE pengguna SET saldo = saldo + ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('di', $jumlah, $user_id);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($ok) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WALLET_CREDIT user={$user_id} amt={$jumlah} affected={$affected} note={$keterangan}\n", FILE_APPEND);
        if (has_table($con, 'saldo_audit')) {
            $stmt2 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'wallet_credit', ?, ?)");
            if ($stmt2) {
                $msg = $keterangan;
                $stmt2->bind_param('isd', $user_id, $msg, $jumlah);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }
    return $ok;
}

function wallet_debit($con, $user_id, $jumlah, $keterangan = '') {
    if (!$con) return false;
    // ensure saldo cukup
    $r = $con->query("SELECT saldo FROM pengguna WHERE id = " . intval($user_id) . " LIMIT 1");
    if (!$r || $r->num_rows == 0) return false;
    $row = $r->fetch_assoc();
    $saldo = floatval($row['saldo']);
    if ($saldo < $jumlah) {
        // insufficient funds
        return false;
    }
    $stmt = $con->prepare("UPDATE pengguna SET saldo = saldo - ? WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param('di', $jumlah, $user_id);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($ok) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WALLET_DEBIT user={$user_id} amt={$jumlah} affected={$affected} note={$keterangan}\n", FILE_APPEND);
        if (has_table($con, 'saldo_audit')) {
            $stmt2 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'wallet_debit', ?, ?)");
            if ($stmt2) {
                $msg = $keterangan;
                $stmt2->bind_param('isd', $user_id, $msg, $jumlah);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }
    return $ok;
}

function move_wallet_to_tabungan($con, $user_id, $jumlah, $jenis_tabungan = 1, $keterangan = '') {
    if (!$con) return false;
    // Assumes caller manages transaction boundaries. This function will debit wallet and insert into tabungan_masuk (or fallback) atomically if called inside a transaction.
    // 1) debit wallet
    if (!wallet_debit($con, $user_id, $jumlah, 'Move to tabungan: ' . $keterangan)) {
        return false;
    }
    // 2) insert into tabungan_masuk (preferred), fallback insert into transaksi
    $created = date('Y-m-d H:i:s');
    $ok = false;
    // Prefer the newer `tabungan_masuk` schema which is what the mobile app and
    // `saldo_per_jenis.php`/dashboard expect. Legacy `tabungan` table is kept as
    // a fallback for old installations, but it must not be preferred when
    // `tabungan_masuk` exists because that causes balances not to appear in the
    // modern APIs/UX (this was the source of the reported bug).
    if (has_table($con, 'tabungan_masuk')) {
        // If $jenis_tabungan is numeric, use it; if string, try to map to id
        $id_jenis = intval($jenis_tabungan);
        // If $jenis_tabungan is string (non-numeric) try to map
        if (!ctype_digit((string)$jenis_tabungan)) {
            $name = $con->real_escape_string($jenis_tabungan);
            // Normalize kinds like 'Tabungan Aqiqah' -> 'Aqiqah' and try multiple match strategies
            $norm = preg_replace('/\\btabungan\\b/i', '', $name);
            $norm = trim($norm);
            $n1 = $con->real_escape_string($norm);
            // Try exact on both possible columns, then loosen with LIKE
            $jr = $con->query("SELECT id FROM jenis_tabungan WHERE (nama = '$name' OR nama_jenis = '$name' OR nama = '$n1' OR nama_jenis = '$n1' OR nama LIKE '%$n1%' OR nama_jenis LIKE '%$n1%') LIMIT 1");
            if ($jr && $jr->num_rows > 0) {
                $rrow = $jr->fetch_assoc();
                $id_jenis = intval($rrow['id']);
            }
            if ($id_jenis <= 0) $id_jenis = 1;
        }
        $stmt = $con->prepare("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iissss', $user_id, $id_jenis, $jumlah, $keterangan, $created, $created);
            $ok = $stmt->execute();
            $stmt->close();
        }
    } else if (has_table($con, 'tabungan')) {
        // Legacy fallback for old DBs that still use `tabungan` table
        $stmt = $con->prepare("INSERT INTO tabungan (id_pengguna, tanggal, jenis, jumlah, keterangan, id_petugas, created_at) VALUES (?, ?, 'masuk', ?, ?, NULL, ?)");
        if ($stmt) {
            $tanggal = date('Y-m-d');
            $stmt->bind_param('isdss', $user_id, $tanggal, $jumlah, $keterangan, $created);
            $ok = $stmt->execute();
            $stmt->close();
        }
    } else {
        // fallback: insert to transaksi as a record (best-effort)
        $stmt = $con->prepare("INSERT INTO transaksi (id_tabungan, nama, id_tabungan, kelas, kegiatan, jumlah_masuk, tanggal, petugas, created_at) VALUES (?, '', '', '', 'tabungan_setoran', ?, '', 'System', ?)");
        if ($stmt) {
            $stmt->bind_param('dss', $user_id, $jumlah, $created);
            $ok = $stmt->execute();
            $stmt->close();
        }
    }
    if ($ok) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " MOVE_WALLET_TO_TABUNGAN user={$user_id} amt={$jumlah} note={$keterangan} ok=1\n", FILE_APPEND);
        // optionally write to DB audit table if exists
        if (has_table($con, 'saldo_audit')) {
            $stmt2 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, id_jenis_tabungan, event_type, message, amount, meta) VALUES (?, ?, ?, ?, ?, NULL)");
            if ($stmt2) {
                $et = 'move_wallet_to_tabungan';
                $msg = $keterangan;
                $stmt2->bind_param('iissd', $user_id, $id_jenis, $et, $msg, $jumlah);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    } else {
        // If insert failed, try to rollback caller's transaction (caller should handle transaction)
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " MOVE_WALLET_TO_TABUNGAN user={$user_id} amt={$jumlah} note={$keterangan} ok=0\n", FILE_APPEND);
    }
    return $ok;
}

function insert_ledger_masuk($con, $user_id, $jumlah, $keterangan = '', $id_jenis_tabungan = 1, $id_petugas = null) {
    if (!$con) return false;
    $created = date('Y-m-d H:i:s');
    // prefer old "tabungan" table if exists
    if (has_table($con, 'tabungan')) {
        $stmt = $con->prepare("INSERT INTO tabungan (id_pengguna, tanggal, jenis, jumlah, keterangan, id_petugas, created_at) VALUES (?, ?, 'masuk', ?, ?, ?, ?)");
        if (!$stmt) return false;
        $tanggal = date('Y-m-d');
        $stmt->bind_param('isdiss', $user_id, $tanggal, $jumlah, $keterangan, $id_petugas, $created);
        $ok = $stmt->execute();
        $stmt->close();
    } else if (has_table($con, 'tabungan_masuk')) {
        $stmt = $con->prepare("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        $stmt->bind_param('iissss', $user_id, $id_jenis_tabungan, $jumlah, $keterangan, $created, $created);
        $ok = $stmt->execute();
        $stmt->close();
    } else {
        // fallback: insert to transaksi as a record (best-effort)
        $stmt = $con->prepare("INSERT INTO transaksi (id_tabungan, nama, id_tabungan, kelas, kegiatan, jumlah_masuk, tanggal, petugas, created_at) VALUES (?, '', '', '', 'masuk', ?, '', 'System', ?)");
        if (!$stmt) return false;
        $tanggal = date('Y-m-d');
        $stmt->bind_param('dss', $user_id, $jumlah, $created);
        $ok = $stmt->execute();
        $stmt->close();
    }

    if ($ok) {
        // NOTE: For modern schema we insert into `tabungan_masuk` (or `tabungan`) and
        // those are per-jenis savings ledger entries and MUST NOT be treated as
        // wallet (pengguna.saldo) changes. Only fallback insert into `transaksi`
        // represents a generic wallet-style record in older installs.
        // Therefore: update pengguna.saldo ONLY when we fell back to inserting
        // into `transaksi` (legacy / best-effort case).
        if (!has_table($con, 'tabungan_masuk') && !has_table($con, 'tabungan')) {
            // Legacy fallback previously attempted to update `pengguna.saldo` when modern
            // `tabungan_masuk`/`tabungan` were unavailable. To enforce single-source-of-truth
            // balance mutations (only via explicit wallet_credit/wallet_debit or admin flows),
            // we will NOT mutate `pengguna.saldo` here anymore. Keep an audit entry and return ok
            // allowing callers to explicitly decide how to handle legacy installs.
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " INSERT_MASUK_LEGACY_NO_WALLET_CHANGE user={$user_id} amt={$jumlah} note={$keterangan}\n", FILE_APPEND);
            if (has_table($con, 'saldo_audit')) {
                $stmt3 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'insert_masuk_legacy_no_wallet_change', ?, ?)");
                if ($stmt3) { $stmt3->bind_param('isd', $user_id, $keterangan, $jumlah); $stmt3->execute(); $stmt3->close(); }
            }
        } else {
            // For modern DBs we intentionally do NOT touch pengguna.saldo here.
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " INSERT_MASUK_TO_TABUNGAN user={$user_id} amt={$jumlah} note={$keterangan}\n", FILE_APPEND);
            if (has_table($con, 'saldo_audit')) {
                $stmt3 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'insert_masuk_to_tabungan', ?, ?)");
                if ($stmt3) { $stmt3->bind_param('isd', $user_id, $keterangan, $jumlah); $stmt3->execute(); $stmt3->close(); }
            }
        }
    }
    return $ok;
}

function insert_ledger_keluar($con, $user_id, $jumlah, $keterangan = '', $id_jenis_tabungan = 1, $id_petugas = null) {
    if (!$con) return false;
    $created = date('Y-m-d H:i:s');
    if (has_table($con, 'tabungan')) {
        $stmt = $con->prepare("INSERT INTO tabungan (id_pengguna, tanggal, jenis, jumlah, keterangan, id_petugas, created_at) VALUES (?, ?, 'keluar', ?, ?, ?, ?)");
        if (!$stmt) return false;
        $tanggal = date('Y-m-d');
        $stmt->bind_param('isdiss', $user_id, $tanggal, $jumlah, $keterangan, $id_petugas, $created);
        $ok = $stmt->execute();
        $stmt->close();
    } else if (has_table($con, 'tabungan_keluar')) {
        $stmt = $con->prepare("INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        $stmt->bind_param('iissss', $user_id, $id_jenis_tabungan, $jumlah, $keterangan, $created, $created);
        $ok = $stmt->execute();
        // record insert id when available for debugging
        if ($ok) {
            $insId = intval($con->insert_id);
            @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [ledger_helpers] INSERT tabungan_keluar id={$insId} user={$user_id} jenis={$id_jenis_tabungan} amt={$jumlah}\n", FILE_APPEND);
        }
        $stmt->close();
    } else {
        $stmt = $con->prepare("INSERT INTO transaksi (id_tabungan, nama, id_tabungan, kelas, kegiatan, jumlah_keluar, tanggal, petugas, created_at) VALUES (?, '', '', '', 'keluar', ?, '', 'System', ?)");
        if (!$stmt) return false;
        $tanggal = date('Y-m-d');
        $stmt->bind_param('dss', $user_id, $jumlah, $created);
        $ok = $stmt->execute();
        $stmt->close();
    }

    if ($ok) {
        // Mirror deposit behaviour: do NOT touch pengguna.saldo when this was
        // recorded into per-jenis savings tables. Only update saldo for legacy
        // fallback where we inserted into `transaksi`.
        if (!has_table($con, 'tabungan') && !has_table($con, 'tabungan_keluar')) {
            // Legacy fallback previously updated `pengguna.saldo` for withdrawal records
            // when modern tables were missing. To follow the rule "only admin approval
            // mutates wallet balance" we avoid any implicit wallet mutations here.
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " INSERT_KELUAR_LEGACY_NO_WALLET_CHANGE user={$user_id} amt={$jumlah} note={$keterangan}\n", FILE_APPEND);
            if (has_table($con, 'saldo_audit')) {
                $stmt3 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'insert_keluar_legacy_no_wallet_change', ?, ?)");
                if ($stmt3) { $stmt3->bind_param('isd', $user_id, $keterangan, $jumlah); $stmt3->execute(); $stmt3->close(); }
            }
        } else {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " INSERT_KELUAR_TO_TABUNGAN user={$user_id} amt={$jumlah} note={$keterangan}\n", FILE_APPEND);
            if (has_table($con, 'saldo_audit')) {
                $stmt3 = $con->prepare("INSERT INTO saldo_audit (id_pengguna, event_type, message, amount) VALUES (?, 'insert_keluar_to_tabungan', ?, ?)");
                if ($stmt3) { $stmt3->bind_param('isd', $user_id, $keterangan, $jumlah); $stmt3->execute(); $stmt3->close(); }
            }
        }
    }
    return $ok;
}

// ============================================================================
// WITHDRAWAL HELPERS - Professional Fintech Standard
// ============================================================================
// These helpers provide centralized, reusable logic for the withdrawal/pencairan
// system. They ensure consistent behavior, proper logging, and clean separation
// of concerns (deduction, credit, transaction recording).

/**
 * Atomically deduct withdrawal amount from saved balance (tabungan_masuk).
 * 
 * Deducts from multiple rows (oldest first) to ensure consistent behavior
 * even when savings are split across different deposit dates.
 * 
 * Usage:
 *   $new_balance = withdrawal_deduct_saved_balance($con, $user_id, $jenis_id, $amount);
 *   if $new_balance is false: error insufficient balance or DB failure
 * 
 * @param mysqli $con Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param int $jenis_id Savings type ID (jenis_tabungan.id)
 * @param float $amount Amount to deduct
 * @return float|false New remaining balance per-jenis, or false on error
 */
function withdrawal_deduct_saved_balance($con, $user_id, $jenis_id, $amount) {
    if (!$con) return false;
    if ($amount <= 0) return false;

    $user_id = intval($user_id);
    $jenis_id = intval($jenis_id);
    $amount = floatval($amount);

    // Check if tabungan_masuk table exists (modern schema)
    if (!has_table($con, 'tabungan_masuk')) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_DEDUCT_NO_TABLE user={$user_id} jenis={$jenis_id} amt={$amount} err=tabungan_masuk_missing\n", FILE_APPEND);
        return false;
    }

    try {
        // 1) Check if we have required status column
        $has_status = false;
        $chk = $con->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'status'");
        if ($chk && $chk->num_rows > 0) $has_status = true;
        $statusClause = $has_status ? " AND status = 'berhasil'" : "";

        // 2) Verify sufficient balance
        $stmtSum = $con->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause);
        if (!$stmtSum) return false;
        $stmtSum->bind_param('ii', $user_id, $jenis_id);
        $stmtSum->execute();
        $rSum = $stmtSum->get_result();
        $sumRow = $rSum->fetch_assoc();
        $totalAvail = floatval($sumRow['total'] ?? 0);
        $stmtSum->close();

        if ($totalAvail < $amount) {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_DEDUCT_INSUFFICIENT user={$user_id} jenis={$jenis_id} amt={$amount} available={$totalAvail}\n", FILE_APPEND);
            return false;
        }

        // 3) Deduct from rows (oldest first) - atomic operation
        $stmtRows = $con->prepare("SELECT id, jumlah FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause . " ORDER BY created_at ASC FOR UPDATE");
        if (!$stmtRows) return false;
        $stmtRows->bind_param('ii', $user_id, $jenis_id);
        $stmtRows->execute();
        $rRows = $stmtRows->get_result();

        $remaining = $amount;
        while ($row = $rRows->fetch_assoc()) {
            if ($remaining <= 0) break;
            $rowId = intval($row['id']);
            $rowAmt = floatval($row['jumlah']);

            if ($rowAmt > $remaining) {
                // Partial deduction from this row
                $upd = $con->prepare("UPDATE tabungan_masuk SET jumlah = jumlah - ? WHERE id = ? AND jumlah >= ?");
                if (!$upd) {
                    $stmtRows->close();
                    return false;
                }
                $upd->bind_param('did', $remaining, $rowId, $remaining);
                $upd->execute();
                $aff = $upd->affected_rows;
                $upd->close();
                if ($aff <= 0) {
                    $stmtRows->close();
                    return false;
                }
                $remaining = 0;
            } else if ($rowAmt > 0) {
                // Full consumption of this row
                $toTake = $rowAmt;
                $upd = $con->prepare("UPDATE tabungan_masuk SET jumlah = 0 WHERE id = ? AND jumlah >= ?");
                if (!$upd) {
                    $stmtRows->close();
                    return false;
                }
                $upd->bind_param('id', $rowId, $toTake);
                $upd->execute();
                $aff = $upd->affected_rows;
                $upd->close();
                if ($aff <= 0) {
                    $stmtRows->close();
                    return false;
                }
                $remaining -= $toTake;
            }
        }
        $stmtRows->close();

        if ($remaining > 0) {
            // Somehow we couldn't deduct the full amount (race condition)
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_DEDUCT_INCOMPLETE user={$user_id} jenis={$jenis_id} requested={$amount} deducted=" . ($amount - $remaining) . "\n", FILE_APPEND);
            return false;
        }

        // 4) Get new balance for response
        $stmtNew = $con->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause);
        if (!$stmtNew) return false;
        $stmtNew->bind_param('ii', $user_id, $jenis_id);
        $stmtNew->execute();
        $rNew = $stmtNew->get_result();
        $newRow = $rNew->fetch_assoc();
        $newBalance = floatval($newRow['total'] ?? 0);
        $stmtNew->close();

        // Log to audit trail
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_DEDUCT_SUCCESS user={$user_id} jenis={$jenis_id} amt={$amount} new_balance={$newBalance}\n", FILE_APPEND);
        if (has_table($con, 'saldo_audit')) {
            $et = 'withdrawal_deduct';
            $note = "Deducted from saved balance";
            $stmtAudit = $con->prepare("INSERT INTO saldo_audit (id_pengguna, id_jenis_tabungan, event_type, message, amount) VALUES (?, ?, ?, ?, ?)");
            if ($stmtAudit) {
                $stmtAudit->bind_param('iissd', $user_id, $jenis_id, $et, $note, $amount);
                $stmtAudit->execute();
                $stmtAudit->close();
            }
        }

        return $newBalance;

    } catch (Exception $e) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_DEDUCT_ERROR user={$user_id} jenis={$jenis_id} amt={$amount} error=" . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Credit amount to user's wallet (pengguna.saldo) with proper logging.
 * 
 * Wrapper around wallet_credit() with additional audit trail for withdrawal approvals.
 * 
 * Usage:
 *   $new_saldo = withdrawal_credit_wallet($con, $user_id, $amount, 'Approval for withdrawal ID 123');
 *   if $new_saldo is false: error insufficient DB or user not found
 * 
 * @param mysqli $con Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param float $amount Amount to credit
 * @param string $note Description/note for audit
 * @return float|false New wallet balance, or false on error
 */
function withdrawal_credit_wallet($con, $user_id, $amount, $note = '') {
    if (!$con) return false;
    if ($amount <= 0) return false;

    $user_id = intval($user_id);
    $amount = floatval($amount);

    try {
        // Use existing wallet_credit helper which handles UPDATE and logging
        $ok = wallet_credit($con, $user_id, $amount, $note);
        if (!$ok) {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_CREDIT_FAILED user={$user_id} amt={$amount} note={$note}\n", FILE_APPEND);
            return false;
        }

        // Fetch new saldo for response
        $stmtSaldo = $con->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
        if (!$stmtSaldo) return false;
        $stmtSaldo->bind_param('i', $user_id);
        $stmtSaldo->execute();
        $rSaldo = $stmtSaldo->get_result();
        if (!($rSaldo && $rSaldo->num_rows > 0)) {
            $stmtSaldo->close();
            return false;
        }
        $saldoRow = $rSaldo->fetch_assoc();
        $newSaldo = floatval($saldoRow['saldo'] ?? 0);
        $stmtSaldo->close();

        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_CREDIT_SUCCESS user={$user_id} amt={$amount} new_saldo={$newSaldo}\n", FILE_APPEND);
        return $newSaldo;

    } catch (Exception $e) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " WITHDRAWAL_CREDIT_ERROR user={$user_id} amt={$amount} error=" . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Create transaction record for approved withdrawal in the ledger/history.
 * 
 * This records a withdrawal approval event in the transaksi table for audit
 * and user history purposes. It uses schema-safe pattern to handle different
 * DB schema variants.
 * 
 * IMPORTANT: Only call this when withdrawal is APPROVED and saldo has been changed.
 * Do NOT call this for pending requests or rejections.
 * 
 * Usage:
 *   $tx_id = create_withdrawal_transaction_record($con, $user_id, $jenis_id, $amount, $tab_keluar_id, 'Withdrawal approved');
 *   if $tx_id is false: error DB or schema issue
 * 
 * @param mysqli $con Database connection
 * @param int $user_id User ID (pengguna.id)
 * @param int $jenis_id Savings type ID (jenis_tabungan.id)
 * @param float $amount Withdrawal amount
 * @param int $tab_keluar_id tabungan_keluar.id for reference
 * @param string $note Description/keterangan
 * @return int|false Transaction ID (insert_id) or false on error
 */
function create_withdrawal_transaction_record($con, $user_id, $jenis_id, $amount, $tab_keluar_id, $note = '') {
    if (!$con) return false;
    if ($amount <= 0) return false;

    $user_id = intval($user_id);
    $jenis_id = intval($jenis_id);
    $amount = floatval($amount);
    $tab_keluar_id = intval($tab_keluar_id);

    try {
        // Check if transaksi table exists
        if (!has_table($con, 'transaksi')) {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " CREATE_WITHDRAWAL_TX_NO_TABLE user={$user_id} jenis={$jenis_id} amt={$amount}\n", FILE_APPEND);
            return false;
        }

        // Determine if this is for approved or rejected withdrawal based on note
        $isRejected = strpos(strtolower($note), 'rejected') !== false;
        $statusForTx = $isRejected ? 'rejected' : 'approved';
        $kegiatanStatus = $isRejected ? 'rejected' : 'approved';

        // For display: format keterangan to be user-friendly
        $ketDisplay = $isRejected ? 'Pencairan Tabungan Ditolak' : 'Pencairan Tabungan';

        // Build payload with common transaction fields
        $txPayload = [
            'id_tabungan' => $user_id,  // Often used in legacy schemas
            'id_pengguna' => $user_id,  // Modern variant
            'jenis_transaksi' => 'penarikan',  // Use standard 'penarikan' for all withdrawal transactions
            'type' => 'pencairan_tabungan',  // For clarity
            'jumlah_keluar' => $amount,
            'jumlah' => $amount,
            'status' => $statusForTx,  // Add explicit status for riwayat filter
            'keterangan' => $ketDisplay . ' [tabungan_keluar_id=' . $tab_keluar_id . ']: ' . $note,  // Include tabungan_keluar_id for accurate jenis lookup
            'tanggal' => date('Y-m-d H:i:s'),  // Include time in Indonesia timezone (UTC+7)
            'created_at' => date('Y-m-d H:i:s'),
            'petugas' => 'admin_approval',
            'kegiatan' => 'pencairan_tabungan',
            'kegiatan2' => $kegiatanStatus,
            'no_keluar' => "TK-{$tab_keluar_id}"
        ];

        // Discover which columns actually exist
        $discoveredCols = [];
        $rCols = $con->query("SHOW COLUMNS FROM transaksi");
        if ($rCols) {
            while ($colRow = $rCols->fetch_assoc()) {
                $discoveredCols[] = $colRow['Field'];
            }
        }

        // Build INSERT statement with only valid columns
        $insertCols = [];
        $insertVals = [];
        foreach ($txPayload as $col => $val) {
            if (in_array($col, $discoveredCols, true)) {
                $insertCols[] = $col;
                if (is_null($val)) {
                    $insertVals[] = 'NULL';
                } else {
                    $insertVals[] = "'" . $con->real_escape_string((string)$val) . "'";
                }
            }
        }

        if (empty($insertCols)) {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " CREATE_WITHDRAWAL_TX_NO_MATCHING_COLS user={$user_id} jenis={$jenis_id}\n", FILE_APPEND);
            return false;
        }

        $sql = "INSERT INTO transaksi (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
        $ok = $con->query($sql);

        if (!$ok) {
            @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " CREATE_WITHDRAWAL_TX_FAILED user={$user_id} jenis={$jenis_id} amt={$amount} err=" . $con->error . "\n", FILE_APPEND);
            return false;
        }

        $txId = intval($con->insert_id);
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " CREATE_WITHDRAWAL_TX_SUCCESS user={$user_id} jenis={$jenis_id} amt={$amount} tx_id={$txId}\n", FILE_APPEND);

        // Generate no_transaksi
        if ($txId > 0) {
            require_once __DIR__ . '/../../flutter_api/no_transaksi_helper.php';
            generate_no_transaksi($con, $txId, 'penarikan');
        }

        return $txId;

    } catch (Exception $e) {
        @file_put_contents(__DIR__ . '/../../flutter_api/saldo_audit.log', date('c') . " CREATE_WITHDRAWAL_TX_ERROR user={$user_id} jenis={$jenis_id} amt={$amount} error=" . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

?>