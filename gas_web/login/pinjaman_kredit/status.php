<?php
// login/pinjaman_kredit/status.php
include '../dashboard/head.php';
include '../dashboard/header.php';
require_once __DIR__ . '/../../config/db.php';

$id_pengguna = isset($_GET['id_pengguna']) ? (int)$_GET['id_pengguna'] : ($_SESSION['id_user'] ?? 0);
if ($id_pengguna <= 0) { header('Location: /'); exit; }

$r = mysqli_query($con, "SELECT id, nama_barang, harga, dp, pokok, tenor, cicilan_per_bulan, total_bayar, status, catatan_admin, approved_at, updated_at, created_at FROM pinjaman_kredit WHERE id_pengguna = $id_pengguna ORDER BY created_at DESC");
$apps = [];
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) $apps[] = $row;
    mysqli_free_result($r);
}

function render_timeline($status, $date, $note) {
    $label = ucfirst($status);
    echo '<div class="mb-3">';
    echo '<div><strong>'.htmlspecialchars($label).'</strong> <span class="text-muted">'.htmlspecialchars($date).'</span></div>';
    if ($note) echo '<div class="small">'.htmlspecialchars($note).'</div>';
    echo '</div>';
}

?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Status Pengajuan Kredit</h5>
      </div>

      <div class="row">
        <div class="col-lg-8">
          <?php if (empty($apps)): ?>
            <div class="alert alert-info">Belum ada pengajuan kredit.</div>
          <?php else: foreach ($apps as $a): ?>
            <div class="card mb-3">
              <div class="card-body">
                <h6><?php echo htmlspecialchars($a['nama_barang']); ?> <small class="text-muted">(<?php echo htmlspecialchars($a['status']); ?>)</small></h6>
                <dl class="row">
                  <dt class="col-4">Harga</dt><dd class="col-8"><?php echo number_format($a['harga'],0,',','.'); ?></dd>
                  <dt class="col-4">DP</dt><dd class="col-8"><?php echo number_format($a['dp'],0,',','.'); ?></dd>
                  <dt class="col-4">Tenor</dt><dd class="col-8"><?php echo intval($a['tenor']); ?> bulan</dd>
                  <dt class="col-4">Cicilan / bulan</dt><dd class="col-8"><?php echo number_format($a['cicilan_per_bulan'],0,',','.'); ?></dd>
                  <dt class="col-4">Total bayar</dt><dd class="col-8"><?php echo number_format($a['total_bayar'],0,',','.'); ?></dd>
                </dl>
                <div class="mt-3">
                  <?php
                    // Render timeline based on available fields and logs
                    echo '<div class="small text-muted">Timeline</div>'; 
                    $id = (int)$a['id'];
                    $lr = mysqli_query($con, "SELECT previous_status, new_status, changed_by, reason, note, created_at FROM pinjaman_kredit_log WHERE pinjaman_id = $id ORDER BY created_at ASC");
                    while ($l = mysqli_fetch_assoc($lr)) {
                        render_timeline($l['new_status'], $l['created_at'], $l['note'] ?: $l['reason']);
                    }
                    mysqli_free_result($lr);
                  ?>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include '../dashboard/js.php'; ?>