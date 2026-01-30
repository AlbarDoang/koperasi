<?php
// login/admin/pinjaman_kredit/detail.php
require_once __DIR__ . '/../../../config/db.php';
$ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if (!$ajax) {
    include '../dashboard/head.php';
    include '../dashboard/header.php';
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

// Defensive: only select pengguna columns that actually exist in the schema
$userDesired = ['nama_lengkap','no_hp','nik','foto_profil'];
$userCols = [];
$cr = mysqli_query($con, "SHOW COLUMNS FROM pengguna");
if ($cr) {
  while ($c = mysqli_fetch_assoc($cr)) {
    if (in_array($c['Field'], $userDesired)) $userCols[] = 'p.`' . $c['Field'] . '`';
  }
  mysqli_free_result($cr);
}
$userColsSql = $userCols ? ', ' . implode(',', $userCols) : '';

$q = "SELECT pk.*" . $userColsSql . " FROM pinjaman_kredit pk LEFT JOIN pengguna p ON p.id = pk.id_pengguna WHERE pk.id = $id LIMIT 1";
$r = mysqli_query($con, $q);
if (!$r) { header('Location: index.php'); exit; }
$row = mysqli_fetch_assoc($r); mysqli_free_result($r);

// resolve approved_by name if available
$approvedByName = '';
if (!empty($row['approved_by'])) {
  $abid = intval($row['approved_by']);
  $qar = mysqli_query($con, "SELECT nama_lengkap FROM pengguna WHERE id = $abid LIMIT 1");
  if ($qar && mysqli_num_rows($qar) > 0) { $approvedByName = mysqli_fetch_assoc($qar)['nama_lengkap']; mysqli_free_result($qar); }
}

// resolve image URLs to absolute paths so they load correctly in modal partials
$foto_path = '';
if (!empty($row['foto_barang'])) {
  $p = $row['foto_barang'];
  // Only serve images that are stored using the new storage format (DB should contain filename only)
  // If the DB contains a path or URL, we do NOT serve it here (migration required)
  if (strpos($p, '/') === false && strpos($p, '\\') === false && strpos($p, 'http://') !== 0 && strpos($p, 'https://') !== 0) {
    $foto_path = '/gas/gas_web/login/admin/pinjaman_kredit/foto_barang_image.php?id=' . urlencode($row['id']);
  } else {
    // legacy or external path: do not serve directly (require migration). Leave blank so UI shows '-'
    $foto_path = '';
  }
}
$foto_profil_path = '';
if (!empty($row['foto_profil'])) {
  // Serve via secure proxy endpoint (admin session will allow access)
  $foto_profil_path = '/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($row['id_pengguna']);
}

// fetch logs
$logs = [];
$lr = mysqli_query($con, "SELECT previous_status, new_status, changed_by, reason, note, created_at FROM pinjaman_kredit_log WHERE pinjaman_id = $id ORDER BY created_at ASC");
if ($lr) { while ($l = mysqli_fetch_assoc($lr)) $logs[] = $l; mysqli_free_result($lr); }

?>
<?php if (!$ajax): ?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Detail Pengajuan Kredit</h5>
      </div>
<?php else: ?>
<div class="px-3">
  <div class="pt-2">
    <h5>Detail Pengajuan Kredit</h5>
<?php endif; ?>

      <div class="card">
        <div class="card-body">
          <style>
            /* Make labels bold and space compact to match user detail modal */
            .pk-detail dl dt { font-weight:700; }
            .pk-detail dl dd { margin-bottom: .6rem; }
            .pk-actions { margin-top: 12px; }
          </style>

          <?php
            // keep status normalization
            $status_raw = trim((string)($row['status'] ?? ''));
            $status_norm = ($status_raw !== '') ? strtolower($status_raw) : 'pending';
            $badge_map = ['approved'=>'bg-success','rejected'=>'bg-danger','berjalan'=>'bg-primary','pending'=>'bg-warning','cancelled'=>'bg-secondary','lunas'=>'bg-success'];
            $badge_class = $badge_map[$status_norm] ?? 'bg-secondary';
            // Localized labels for admin UI
            $status_labels = ['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','berjalan'=>'Berjalan','cancelled'=>'Dibatalkan','lunas'=>'Lunas'];
            $status_label = $status_labels[$status_norm] ?? ucfirst($status_norm);
          ?>

          <div class="pk-detail">
            <dl class="row mb-0 view-mode">
              <dt class="col-sm-4">ID</dt>
              <dd class="col-sm-8"><?php echo htmlspecialchars($row['id']); ?></dd>

              <dt class="col-sm-4">Status</dt>
              <dd class="col-sm-8"><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status_label); ?></span></dd>

              <dt class="col-sm-4">Created At</dt>
              <dd class="col-sm-8"><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></dd>

              <?php if (!empty($row['updated_at'])): ?>
                <dt class="col-sm-4">Updated At</dt>
                <dd class="col-sm-8"><?php echo htmlspecialchars($row['updated_at']); ?></dd>
              <?php endif; ?>

              <dt class="col-sm-4">ID Pengguna</dt>
              <dd class="col-sm-8"><?php echo htmlspecialchars($row['id_pengguna']); ?></dd>

              <dt class="col-sm-4">Nama Barang</dt>
              <dd class="col-sm-8"><?php echo htmlspecialchars($row['nama_barang']); ?></dd>

              <dt class="col-sm-4">Foto Barang</dt>
              <dd class="col-sm-8">
                <?php if (!empty($foto_path)): ?>
                  <a href="<?php echo htmlspecialchars($foto_path, ENT_QUOTES); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($foto_path, ENT_QUOTES); ?>" alt="Foto Barang" class="img-thumbnail" style="max-width:160px;" />
                  </a>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </dd>

              <dt class="col-sm-4">Harga</dt>
              <dd class="col-sm-8"><?php echo 'Rp ' . number_format($row['harga'] ?? 0,0,',','.'); ?></dd>

              <dt class="col-sm-4">DP</dt>
              <dd class="col-sm-8"><?php echo 'Rp ' . number_format($row['dp'] ?? 0,0,',','.'); ?></dd>

              <dt class="col-sm-4">Pokok</dt>
              <dd class="col-sm-8"><?php echo 'Rp ' . number_format($row['pokok'] ?? 0,0,',','.'); ?></dd>

              <dt class="col-sm-4">Tenor</dt>
              <dd class="col-sm-8"><?php echo intval($row['tenor'] ?? 0); ?> bulan</dd>

              <dt class="col-sm-4">Cicilan / bulan</dt>
              <dd class="col-sm-8"><?php echo 'Rp ' . number_format($row['cicilan_per_bulan'] ?? 0,0,',','.'); ?></dd>

              <dt class="col-sm-4">Total Bayar</dt>
              <dd class="col-sm-8"><?php echo 'Rp ' . number_format($row['total_bayar'] ?? 0,0,',','.'); ?></dd>
            </dl>

            <div class="pk-actions">
              <div class="small text-muted">Status saat ini: <?php echo htmlspecialchars($status_label); ?></div>
            </div>
          </div>
        </div>
      </div>

<?php if (!$ajax): ?>
    </main>
  </div>
</div>
<?php else: ?>
  </div>
</div>
<?php endif; ?>
<?php if (!$ajax) { include '../dashboard/js.php'; ?>
<script>
function adminAction(id, action){
  var reason = null; if (action === 'reject') reason = prompt('Alasan penolakan:');
  if (action === 'approve' && !confirm('Terima pengajuan pinjaman ini?')) return;
  $.ajax({
    url: '/gas/gas_web/api/pinjaman_kredit/admin_action.php',
    method: 'POST',
    data: {id:id, action:action, reason:reason},
    dataType: 'json',
    timeout: 15000,
    success: function(data){ if (data && data.status) { alert('Sukses'); location.reload(); } else { alert('Error: ' + (data && data.message)); } },
    error: function(jqxhr, status, err){ var resp = jqxhr && jqxhr.responseText ? jqxhr.responseText : ''; console.error('AJAX fail', status, err, resp); var msg = 'Terjadi kesalahan saat menghubungi server.'; try{ var parsed = JSON.parse(resp); if (parsed && parsed.message) msg = 'Server: ' + parsed.message; } catch(e){ if (resp) msg += '\nRespon server: ' + (resp.length>300? resp.substr(0,300)+'...': resp); } alert(msg); }
  });
}

function adminEvent(id, event){
  var note = prompt('Tambah catatan (opsional):');
  if (!confirm('Yakin menerapkan event "' + event + '"?')) return;
  $.ajax({
    url: '/gas/gas_web/api/pinjaman_kredit/admin_event.php',
    method: 'POST',
    data: {id:id, event:event, note: note},
    dataType: 'json',
    timeout: 15000,
    success: function(data){ if (data && data.status) { alert('Sukses: ' + (data.message||'')); location.reload(); } else { alert('Error: ' + (data && data.message)); } },
    error: function(jqxhr, status, err){ var resp = jqxhr && jqxhr.responseText ? jqxhr.responseText : ''; console.error('AJAX fail', status, err, resp); var msg = 'Terjadi kesalahan saat menghubungi server.'; try{ var parsed = JSON.parse(resp); if (parsed && parsed.message) msg = 'Server: ' + parsed.message; } catch(e){ if (resp) msg += '\nRespon server: ' + (resp.length>300? resp.substr(0,300)+'...': resp); } alert(msg); }
  });
}
</script>
<?php } ?>