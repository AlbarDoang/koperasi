<?php
/**
 * Simple schema-safe helper to insert a row into `transaksi`.
 * Usage: require_once 'transaksi_helper.php'; record_transaction($connect, $payload);
 * payload is an associative array with keys matching columns in `transaksi` (e.g., 'no_keluar','nama','id_tabungan','kegiatan','jumlah_masuk','jumlah_keluar','tanggal','petugas','jenis_transaksi','keterangan','saldo_sebelum','saldo_sesudah')
 * 
 * FLAG: IS_SETOR_MANUAL_ADMIN
 * - Jika flag ini true, helper akan SKIP kolom 'no_keluar' dari payload
 * - Ini untuk mencegah error "Unknown column no_keluar" saat setor manual admin
 */
function record_transaction($connect, $payload = []) {
    if (!is_array($payload) || empty($payload)) return false;
    
    // DEFENSIVE: Jika ini setor manual admin, SKIP no_keluar dari payload
    if (defined('IS_SETOR_MANUAL_ADMIN') && IS_SETOR_MANUAL_ADMIN) {
        unset($payload['no_keluar']);
    }
    
    // Discover which columns exist in transaksi table once
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        $res = $connect->query("SHOW COLUMNS FROM transaksi");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $cols[] = $r['Field'];
            }
        }
    }

    if (empty($cols)) return false; // transaksi table missing

    $insertCols = [];
    $insertVals = [];

    foreach ($payload as $k => $v) {
        if (in_array($k, $cols, true)) {
            $insertCols[] = $k;
            if (is_null($v)) {
                $insertVals[] = 'NULL';
            } else {
                // escape string values
                $insertVals[] = "'" . $connect->real_escape_string((string)$v) . "'";
            }
        }
    }

    if (empty($insertCols)) return false;

    $sql = "INSERT INTO transaksi (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertVals) . ")";
    $ok = $connect->query($sql);
    if ($ok) {
        $insert_id = $connect->insert_id;
        // Auto-generate no_transaksi
        $jenis = isset($payload['jenis_transaksi']) ? $payload['jenis_transaksi'] : '';
        if ($insert_id > 0 && !empty($jenis)) {
            require_once __DIR__ . '/no_transaksi_helper.php';
            generate_no_transaksi($connect, $insert_id, $jenis);
        }
        return $insert_id;
    }
    // log error
    @file_put_contents(__DIR__ . '/transaksi_helper.log', date('c') . " RECORD_TRANSACTION_FAILED sql=" . $sql . " err=" . $connect->error . "\n", FILE_APPEND);
    return false;
}

