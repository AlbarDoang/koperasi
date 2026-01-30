<?php
// koneksi
include "../../koneksi/config.php";

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="text-danger">Permintaan tidak valid.</div>';
    exit;
}

if (empty($_POST['no_masuk'])) {
    echo '<div class="text-danger">Parameter <strong>no_masuk</strong> dibutuhkan.</div>';
    exit;
}

$no_masuk = intval($_POST['no_masuk']);

// Ambil data mulai_nabung sesuai id_mulai_nabung
$stmt = $con->prepare("SELECT id_mulai_nabung, tanggal, nama_pengguna, nomor_hp, jenis_tabungan, jumlah, keterangan, status FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1");
if (!$stmt) {
    echo '<div class="text-danger">Gagal menyiapkan query: ' . htmlspecialchars($con->error) . '</div>';
    exit;
}
$stmt->bind_param('i', $no_masuk);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo '<div class="text-warning">Data tidak ditemukan.</div>';
    $stmt->close();
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

// Format tanggal dan jumlah
$tanggal = (!empty($row['tanggal']) && $row['tanggal'] !== '0000-00-00') ? date('d-m-Y', strtotime($row['tanggal'])) : '-';
$jumlah = 'Rp ' . number_format(floatval($row['jumlah']), 0, ',', '.');
$status = htmlspecialchars($row['status'] ?? '-');
$keterangan = htmlspecialchars($row['keterangan'] ?? '-');
?>

<!-- Detail Modal Content -->
<form>
    <div class="form-group">
        <label>No Transaksi</label>
        <input class="form-control" value="<?php echo htmlspecialchars($row['id_mulai_nabung']); ?>" readonly>
    </div>

    <div class="form-group">
        <label>Tanggal</label>
        <input class="form-control" value="<?php echo $tanggal; ?>" readonly>
    </div>

    <div class="form-group">
        <label>Nama Pengguna</label>
        <input class="form-control" value="<?php echo htmlspecialchars($row['nama_pengguna']); ?>" readonly>
    </div>

    <div class="form-group">
        <label>No. HP</label>
        <input class="form-control" value="<?php echo htmlspecialchars($row['nomor_hp']); ?>" readonly>
    </div>

    <div class="form-group">
        <label>Jenis Tabungan</label>
        <input class="form-control" value="<?php echo htmlspecialchars($row['jenis_tabungan']); ?>" readonly>
    </div>

    <div class="form-group">
        <label>Jumlah</label>
        <input class="form-control" value="<?php echo $jumlah; ?>" readonly>
    </div>

    <div class="form-group">
        <label>Status</label>
        <input class="form-control" value="<?php echo $status; ?>" readonly>
    </div>

    <div class="form-group">
        <label>Keterangan</label>
        <textarea class="form-control" rows="3" readonly><?php echo $keterangan; ?></textarea>
    </div>
</form>