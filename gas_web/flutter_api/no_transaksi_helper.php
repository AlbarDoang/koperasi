<?php
/**
 * Helper: Generate and set no_transaksi for a transaksi row.
 *
 * Format: KODE-YYYYMMDD-XXXXXX
 * Example: KRM-20260212-000452
 *
 * KODE mapping (based on jenis_transaksi ENUM):
 *   setoran           = SAV
 *   penarikan         = WDR
 *   transfer_keluar   = KRM
 *   transfer_masuk    = KRM
 *   pinjaman_biasa    = LON
 *   pinjaman_kredit   = CRD
 *
 * XXXXXX = id_transaksi padded to 6 digits.
 * Numbering does NOT reset daily.
 *
 * Usage:
 *   require_once __DIR__ . '/no_transaksi_helper.php';
 *   $no = generate_no_transaksi($connect, $id_transaksi, 'setoran');
 *   // returns 'SAV-20260212-000042' and updates the row
 *
 * For paired transfer (kirim uang):
 *   $no = generate_no_transaksi($connect, $id_keluar, 'transfer_keluar');
 *   set_no_transaksi($connect, $id_masuk, $no);
 */

/**
 * Map jenis_transaksi to KODE prefix.
 */
function _get_kode_transaksi($jenis_transaksi) {
    $map = [
        'setoran'           => 'SAV',
        'penarikan'         => 'WDR',
        'transfer_keluar'   => 'KRM',
        'transfer_masuk'    => 'KRM',
        'pinjaman_biasa'    => 'LON',
        'pinjaman_kredit'   => 'CRD',
    ];
    $jenis = strtolower(trim($jenis_transaksi));
    return isset($map[$jenis]) ? $map[$jenis] : 'TRX';
}

/**
 * Generate no_transaksi, UPDATE the row, and return the formatted string.
 *
 * @param mysqli $connect  Database connection
 * @param int    $id_transaksi  The auto-increment ID from INSERT
 * @param string $jenis_transaksi  e.g. 'setoran', 'transfer_keluar'
 * @return string|false  The generated no_transaksi or false on failure
 */
function generate_no_transaksi($connect, $id_transaksi, $jenis_transaksi) {
    if (!$connect || $id_transaksi <= 0 || empty($jenis_transaksi)) return false;

    $kode = _get_kode_transaksi($jenis_transaksi);
    $tanggal = date('Ymd');
    $padded_id = str_pad($id_transaksi, 6, '0', STR_PAD_LEFT);
    $no_transaksi = $kode . '-' . $tanggal . '-' . $padded_id;

    // Update the row
    $stmt = $connect->prepare("UPDATE transaksi SET no_transaksi = ? WHERE id_transaksi = ? AND (no_transaksi IS NULL OR no_transaksi = '')");
    if (!$stmt) {
        @file_put_contents(__DIR__ . '/no_transaksi_helper.log', date('c') . " PREPARE_FAILED err=" . $connect->error . "\n", FILE_APPEND);
        return false;
    }
    $stmt->bind_param('si', $no_transaksi, $id_transaksi);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        @file_put_contents(__DIR__ . '/no_transaksi_helper.log', date('c') . " UPDATE_FAILED id={$id_transaksi} no={$no_transaksi} err=" . $connect->error . "\n", FILE_APPEND);
        return false;
    }

    return $no_transaksi;
}

/**
 * Set a specific no_transaksi on a row (used for paired transfers).
 *
 * @param mysqli $connect
 * @param int    $id_transaksi
 * @param string $no_transaksi  The no_transaksi to assign (same as the paired row)
 * @return bool
 */
function set_no_transaksi($connect, $id_transaksi, $no_transaksi) {
    if (!$connect || $id_transaksi <= 0 || empty($no_transaksi)) return false;

    $stmt = $connect->prepare("UPDATE transaksi SET no_transaksi = ? WHERE id_transaksi = ? AND (no_transaksi IS NULL OR no_transaksi = '')");
    if (!$stmt) return false;
    $stmt->bind_param('si', $no_transaksi, $id_transaksi);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
