<?php
// login/pinjaman_kredit/review.php
include '../dashboard/head.php';
include '../dashboard/header.php';
require_once __DIR__ . '/../../config/db.php';

// Accept POST from create.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: create.php'); exit; }

$input = $_POST;
$id_pengguna = (int)($input['id_pengguna'] ?? 0);
$nama_barang = trim($input['nama_barang'] ?? '');
$harga = floatval($input['harga'] ?? 0);
$dp = floatval($input['dp'] ?? 0);
$tenor = intval($input['tenor'] ?? 0);

if ($id_pengguna <=0 || $nama_barang=='' || $harga<=0) { header('Location: create.php'); exit; }

$errors = [];
if ($dp < 0) $errors[] = 'DP tidak boleh negatif.';
if ($dp >= $harga) $errors[] = 'DP harus lebih kecil dari harga barang.';
if ($tenor <= 0 || $tenor > 12) $errors[] = 'Pilih tenor antara 1 dan 12 bulan.';
if (!empty($errors)) {
    include '../dashboard/menu.php';
    ?>
    <div class="container mt-4">
      <div class="alert alert-danger">
        <strong>Terjadi kesalahan:</strong>
        <ul>
          <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e, ENT_QUOTES) . '</li>'; ?>
        </ul>
        <a href="create.php" class="btn btn-secondary">Kembali</a>
      </div>
    </div>
    <?php
    include '../dashboard/js.php';
    exit;
}

// Syariah flat calc: equal monthly installments with floor rounding, ignore remainder (waived)
$dp_int = (int)floor($dp);
$pokok = (int)floor(max(0.0, $harga - $dp));
$base = intdiv($pokok, max(1, $tenor));
$cicilan_per_bulan = (int)$base;
// total bayar = DP + cicilan_per_bulan * tenor (difference between pokok and cicilan*tenor is waived)
$total_bayar = ($cicilan_per_bulan * max(1, $tenor)) + $dp_int;
if ($cicilan_per_bulan < 0) { $cicilan_per_bulan = 0; }

?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Review Pengajuan Kredit</h5>
      </div>

      <div class="card">
        <div class="card-body">
          <dl>
            <dt>Nama Barang</dt><dd><?php echo htmlspecialchars($nama_barang); ?></dd>
            <dt>Harga</dt><dd><?php echo number_format($harga,0,',','.'); ?></dd>
            <dt>DP</dt><dd><?php echo number_format($dp,0,',','.'); ?></dd>
            <dt>Tenor</dt><dd><?php echo intval($tenor); ?> bulan</dd>
            <dt>Cicilan per bulan</dt><dd><?php echo number_format($cicilan_per_bulan,0,',','.'); ?></dd>
            <dt>Total bayar</dt><dd><?php echo number_format($total_bayar,0,',','.'); ?></dd>
          </dl>
          <form action="upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_pengguna" value="<?php echo htmlspecialchars($id_pengguna); ?>">
            <input type="hidden" name="nama_barang" value="<?php echo htmlspecialchars($nama_barang); ?>">
            <input type="hidden" name="harga" value="<?php echo htmlspecialchars($harga); ?>">
            <input type="hidden" name="dp" value="<?php echo htmlspecialchars($dp); ?>">
            <input type="hidden" name="tenor" value="<?php echo htmlspecialchars($tenor); ?>">
            <input type="hidden" name="cicilan_per_bulan" value="<?php echo htmlspecialchars($cicilan_per_bulan); ?>">
            <input type="hidden" name="total_bayar" value="<?php echo htmlspecialchars($total_bayar); ?>">
            <input type="hidden" name="accepted_terms" value="1">
            <button class="btn btn-primary">Lanjut ke Upload</button>
          </form>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include '../dashboard/js.php'; ?>