<?php
/**
 * Backfill: Insert existing approved/rejected pinjaman_biasa and pinjaman_kredit
 * records into the transaksi table so they get proper sequential id_transaksi.
 * 
 * This script is safe to run multiple times - it checks for existing records.
 * Date: 2026-02-11
 */

date_default_timezone_set('Asia/Jakarta');

$con = new mysqli('localhost', 'root', '', 'tabungan');
if ($con->connect_error) { echo 'Connection failed: ' . $con->connect_error . "\n"; exit(1); }
$con->query("SET time_zone = '+07:00'");

$inserted = 0;
$skipped = 0;

// --- Backfill pinjaman_biasa ---
echo "=== Backfilling pinjaman_biasa ===\n";
$res = $con->query("SELECT id, id_pengguna, jumlah_pinjaman AS jumlah, tenor, status, created_at FROM pinjaman_biasa WHERE status IN ('approved', 'rejected') ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id_pengguna = (int)$row['id_pengguna'];
        $jumlah = (float)$row['jumlah'];
        $status = strtolower($row['status']);
        $created_at = $row['created_at'];
        $tenor = (int)($row['tenor'] ?? 0);
        
        // Check if already exists in transaksi
        $check = $con->prepare("SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'pinjaman_biasa' AND jumlah = ? AND status = ? LIMIT 1");
        $check->bind_param('ids', $id_pengguna, $jumlah, $status);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            $skipped++;
            $check->close();
            continue;
        }
        $check->close();
        
        // Build keterangan
        $amountStr = 'Rp ' . number_format($jumlah, 0, ',', '.');
        $tenorStr = $tenor > 0 ? ' untuk tenor ' . $tenor . ' bulan' : '';
        if ($status === 'approved') {
            $keterangan = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr . $tenorStr . ' disetujui, silahkan cek saldo anda di halaman dashboard.';
        } else {
            $keterangan = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr . $tenorStr . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
        }
        
        $stmt = $con->prepare("INSERT INTO transaksi (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'pinjaman_biasa', ?, 0, 0, ?, ?, ?)");
        $stmt->bind_param('idsss', $id_pengguna, $jumlah, $keterangan, $created_at, $status);
        if ($stmt->execute()) {
            $new_id = $con->insert_id;
            echo "  Inserted pinjaman_biasa #{$row['id']} -> transaksi id_transaksi={$new_id}\n";
            $inserted++;
        } else {
            echo "  ERROR inserting pinjaman_biasa #{$row['id']}: " . $con->error . "\n";
        }
        $stmt->close();
    }
}

// --- Backfill pinjaman_kredit ---
echo "\n=== Backfilling pinjaman_kredit ===\n";
$res2 = $con->query("SELECT id, id_pengguna, harga AS jumlah, nama_barang, tenor, pokok, status, created_at FROM pinjaman_kredit WHERE status IN ('approved', 'rejected') ORDER BY id ASC");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $id_pengguna = (int)$row['id_pengguna'];
        $jumlah = (float)$row['jumlah'];
        $status = strtolower($row['status']);
        $created_at = $row['created_at'];
        $tenor = (int)($row['tenor'] ?? 0);
        $namaBarang = $row['nama_barang'] ?? '';
        
        // Check if already exists in transaksi
        $check = $con->prepare("SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'pinjaman_kredit' AND jumlah = ? AND status = ? LIMIT 1");
        $check->bind_param('ids', $id_pengguna, $jumlah, $status);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            $skipped++;
            $check->close();
            continue;
        }
        $check->close();
        
        // Build keterangan
        $amountStr = 'Rp ' . number_format($jumlah, 0, ',', '.');
        $tenorStr = $tenor > 0 ? ' untuk tenor ' . $tenor . ' bulan' : '';
        if ($status === 'approved') {
            $keterangan = 'Pengajuan Pinjaman Kredit' . (!empty($namaBarang) ? ' (' . $namaBarang . ')' : '') . ' sebesar ' . $amountStr . $tenorStr . ' disetujui.';
        } else {
            $keterangan = 'Pengajuan Pinjaman Kredit' . (!empty($namaBarang) ? ' (' . $namaBarang . ')' : '') . ' sebesar ' . $amountStr . $tenorStr . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
        }
        
        $stmt = $con->prepare("INSERT INTO transaksi (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'pinjaman_kredit', ?, 0, 0, ?, ?, ?)");
        $stmt->bind_param('idsss', $id_pengguna, $jumlah, $keterangan, $created_at, $status);
        if ($stmt->execute()) {
            $new_id = $con->insert_id;
            echo "  Inserted pinjaman_kredit #{$row['id']} -> transaksi id_transaksi={$new_id}\n";
            $inserted++;
        } else {
            echo "  ERROR inserting pinjaman_kredit #{$row['id']}: " . $con->error . "\n";
        }
        $stmt->close();
    }
}

echo "\n=== Summary ===\n";
echo "Inserted: {$inserted}\n";
echo "Skipped (already exists): {$skipped}\n";

$con->close();
