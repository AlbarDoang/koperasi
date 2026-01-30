<?php
// login/admin/pinjaman_biasa/detail.php
require_once __DIR__ . '/../../../config/db.php';
// approval_helpers.php lives in login/, not in login/admin/. Include safely to avoid fatal errors.
$approvalHelpersPath = __DIR__ . '/../../approval_helpers.php';
if (file_exists($approvalHelpersPath)) {
    require_once $approvalHelpersPath;
} else {
    error_log('[pinjaman_biasa/detail.php] approval_helpers.php not found at ' . $approvalHelpersPath);
}
$ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if (!$ajax) {
  include '../dashboard/head.php';
  include '../dashboard/header.php';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: ../approval/index.php'); exit; }

// Find which table contains the record: prefer pinjaman_biasa then pinjaman
$tables = ['pinjaman_biasa', 'pinjaman'];
$row = null; $tblName = null;
foreach ($tables as $t) {
  $r = @mysqli_query($con, "SELECT * FROM `$t` WHERE id = " . intval($id) . " LIMIT 1");
  if ($r && mysqli_num_rows($r) > 0) { $row = mysqli_fetch_assoc($r); mysqli_free_result($r); $tblName = $t; break; }
}
if (!$row) { header('Location: ../approval/index.php'); exit; }

// Ensure we can fetch some user info if available
$userInfo = null;
if (!empty($row['id_pengguna'])) {
  $uid = intval($row['id_pengguna']);
  // Use `SELECT *` so we don't query columns that may not exist on every deployment (avoids unknown column errors)
  $uq = @mysqli_query($con, "SELECT * FROM pengguna WHERE id = $uid LIMIT 1");
  if ($uq && mysqli_num_rows($uq) > 0) { $userInfo = mysqli_fetch_assoc($uq); mysqli_free_result($uq); }
}

// Normalize status
$status_raw = trim((string)($row['status'] ?? ''));
$status = ($status_raw !== '') ? strtolower($status_raw) : 'pending';
$badge_map = ['approved'=>'bg-success','rejected'=>'bg-danger','pending'=>'bg-warning'];
$badge_class = $badge_map[$status] ?? 'bg-secondary';
// Localized labels for admin UI
$status_labels = ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'];
$status_label = $status_labels[$status] ?? ucfirst($status);

// Financials
$jumlah = (float)($row['jumlah_pinjaman'] ?? $row['jumlah'] ?? 0);
$tenor = intval($row['tenor'] ?? 0);

// Determine reject reason column if present (use approval helpers when available)
$rejectReason = null;
if (function_exists('approval_get_schema_for')) {
  $schemaRes = approval_get_schema_for($con, 'pinjaman_biasa');
  if (isset($schemaRes['schema']['columns']['reject_reason'])) {
    $col = $schemaRes['schema']['columns']['reject_reason'];
    if (isset($row[$col]) && trim((string)$row[$col]) !== '') $rejectReason = $row[$col];
  }
}
if (!$rejectReason) {
  foreach (['reject_reason','catatan_admin','keterangan','alasan'] as $c) {
    if (isset($row[$c]) && trim((string)$row[$c]) !== '') { $rejectReason = $row[$c]; break; }
  }
}

$cicilan = null;
if (isset($row['cicilan_per_bulan']) && $row['cicilan_per_bulan'] !== null) {
  $cicilan = (int)($row['cicilan_per_bulan']);
} elseif ($tenor > 0 && $jumlah > 0) {
  // Syariah flat calculation (floor rounding, remainder waived — all months equal)
  $total_int = (int)floor($jumlah);
  $base = intdiv($total_int, max(1, $tenor));
  $cicilan = (int)$base;
}

function rp($n){ return 'Rp ' . number_format($n ?? 0,0,',','.'); }

?>
<?php if (!$ajax): ?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Detail Pengajuan Pinjaman Biasa</h5>
      </div>
<?php else: ?>
<div class="px-3"><div class="pt-2"><h5>Detail Pengajuan Pinjaman Biasa</h5>
<?php endif; ?>

  <div class="card">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">ID</dt><dd class="col-sm-8"><?php echo htmlspecialchars($row['id']); ?></dd>
        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_label); ?></span></dd>
        <dt class="col-sm-4">Created At</dt><dd class="col-sm-8"><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></dd>
        <dt class="col-sm-4">ID Pengguna</dt><dd class="col-sm-8"><?php echo htmlspecialchars($row['id_pengguna'] ?? '-'); ?></dd>
        <dt class="col-sm-4">Nama Anggota</dt><dd class="col-sm-8"><?php echo htmlspecialchars($userInfo['nama_lengkap'] ?? $userInfo['nama'] ?? ($row['nama_anggota'] ?? '-')); ?></dd>
        <dt class="col-sm-4">No. HP</dt><dd class="col-sm-8"><?php echo htmlspecialchars($userInfo['no_hp'] ?? '-'); ?></dd>
        <dt class="col-sm-4">Alamat Domisili</dt><dd class="col-sm-8"><?php echo htmlspecialchars($userInfo['alamat_domisili'] ?? 'Tidak diisi'); ?></dd>
        <dt class="col-sm-4">Tanggal Lahir</dt><dd class="col-sm-8"><?php echo htmlspecialchars($userInfo['tanggal_lahir'] ?? '-'); ?></dd>
        <dt class="col-sm-4">Status Akun</dt><dd class="col-sm-8"><?php echo htmlspecialchars($userInfo['status_akun'] ?? '-'); ?></dd>
        <dt class="col-sm-4">Saldo Anggota</dt><dd class="col-sm-8"><?php echo isset($userInfo['saldo']) ? rp($userInfo['saldo']) : '-'; ?></dd>

        <dt class="col-sm-4">Jumlah Pinjaman</dt><dd class="col-sm-8"><?php echo rp($jumlah); ?></dd>
        <dt class="col-sm-4">Tenor</dt><dd class="col-sm-8"><?php echo ($tenor > 0) ? (intval($tenor) . ' bulan') : '-'; ?></dd>
        <dt class="col-sm-4">Cicilan / Bulan</dt><dd class="col-sm-8"><?php echo ($cicilan !== null) ? rp($cicilan) : '-'; ?></dd>

        <dt class="col-sm-4">Tujuan</dt><dd class="col-sm-8"><?php echo htmlspecialchars($row['tujuan_penggunaan'] ?? $row['tujuan'] ?? $row['keterangan'] ?? 'Tidak diisi'); ?></dd>

<?php if ($status === 'rejected' && $rejectReason): ?>
        <dt class="col-sm-4">Alasan Penolakan</dt><dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($rejectReason)); ?></dd>
<?php endif; ?>
      </dl>

      <div class="mt-3">
        <?php if ($status === 'pending'): ?>
        <?php else: ?>
          <div class="small text-muted">Status saat ini: <?php echo htmlspecialchars($status_label); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php if (!$ajax): ?>
    </main>
  </div>
</div>
<?php else: ?>
  </div></div>
<?php endif; ?>

<?php if (!$ajax) { include '../dashboard/js.php'; ?>
<script>
function adminAction(id, action){
  var reason = null; if (action === 'reject') reason = prompt('Alasan penolakan (opsional):');
  if (action === 'approve' && !confirm('Terima pengajuan pinjaman ini?')) return;
  // Using existing admin flow in approval page: this will use approve_process.php for pinjaman_biasa
  $.ajax({
    url: '/gas/gas_web/approve_process.php',
    method: 'POST',
    data: { id_pending: id, action: action, reason: reason, table: 'pinjaman_biasa' },
    dataType: 'json',
    timeout: 15000,
    success: function(data){ if (data && (data.status || data.success)) { alert(data.message || 'Sukses'); location.reload(); } else { alert('Error: ' + (data && data.message)); } },
    error: function(jqxhr, status, err){ var resp = jqxhr && jqxhr.responseText ? jqxhr.responseText : ''; console.error('AJAX fail', status, err, resp); var msg = 'Terjadi kesalahan saat menghubungi server.'; try{ var parsed = JSON.parse(resp); if (parsed && parsed.message) msg = 'Server: ' + parsed.message; } catch(e){ if (resp) msg += '\nRespon server: ' + (resp.length>300? resp.substr(0,300)+'...': resp); } alert(msg); }
  });
}
</script>

<script>

</script>
<?php } ?>