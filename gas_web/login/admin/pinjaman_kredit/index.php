<?php
// login/admin/pinjaman_kredit/index.php
include '../dashboard/head.php';
include '../dashboard/header.php';
require_once __DIR__ . '/../../../config/db.php';

// Basic admin listing with filter pills and simple table
$rows = [];
$r = mysqli_query($con, "SELECT id, id_pengguna, nama_barang, harga, dp, tenor, cicilan_per_bulan, total_bayar, status, created_at FROM pinjaman_kredit ORDER BY created_at DESC LIMIT 200");
if ($r) { while ($ro = mysqli_fetch_assoc($r)) $rows[] = $ro; mysqli_free_result($r); }

?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Approval Pinjaman Kredit</h5>
      </div>

      <div class="card">
        <div class="card-header" align="center">
          <div class="btn-group" role="group">
            <a href="?status=pending" class="btn btn-outline-secondary">Pending</a>
            <a href="?status=approved" class="btn btn-outline-secondary">Approved</a>
            <a href="?status=rejected" class="btn btn-outline-secondary">Rejected</a>
            <a href="?status=all" class="btn btn-outline-secondary">Semua</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100" id="kredit_table">
              <thead>
                <tr>
                  <th class="text-center">No</th>
                  <th class="text-center">Tanggal</th>
                  <th>Nama</th>
                  <th class="text-center">Harga</th>
                  <th class="text-center">DP</th>
                  <th class="text-center">Cicilan</th>
                  <th class="text-center">Total</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php $no=1; foreach ($rows as $row): ?>
                <tr>
                  <td class="text-center"><?php echo $no++; ?></td>
                  <td class="text-center"><?php echo htmlspecialchars($row['created_at']); ?></td>
                  <td><?php
                    $u = mysqli_query($con, "SELECT nama_lengkap FROM pengguna WHERE id = " . intval($row['id_pengguna']) . " LIMIT 1");
                    $un = $u ? mysqli_fetch_assoc($u)['nama_lengkap'] : '-'; if ($u) mysqli_free_result($u);
                    echo htmlspecialchars($un);
                  ?></td>
                  <td class="text-end"><?php echo number_format($row['harga'],0,',','.'); ?></td>
                  <td class="text-end"><?php echo number_format($row['dp'],0,',','.'); ?></td>
                  <td class="text-end"><?php echo number_format($row['cicilan_per_bulan'],0,',','.'); ?></td>
                  <td class="text-end"><?php echo number_format($row['total_bayar'],0,',','.'); ?></td>
                  <td class="text-center"><?php echo htmlspecialchars($row['status']); ?></td>
                  <td class="text-center">
                    <?php if ($row['status'] === 'pending'): ?>
                      <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-light dropdown-toggle" id="kreditMenu<?php echo intval($row['id']); ?>" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="kreditMenu<?php echo intval($row['id']); ?>">
                          <li><a class="dropdown-item" href="detail.php?id=<?php echo intval($row['id']); ?>" target="_blank">Detail</a></li>
                          <li><a class="dropdown-item" href="#" onclick="adminAction(<?php echo intval($row['id']); ?>,'approve')">Setuju</a></li>
                          <li><a class="dropdown-item text-danger" href="#" onclick="adminAction(<?php echo intval($row['id']); ?>,'reject')">Tolak</a></li>
                        </ul>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include '../dashboard/js.php'; ?>
<script>
function adminAction(id, action){
  var reason = null;
  if (action === 'reject') reason = prompt('Alasan penolakan:');
  if (action === 'approve' && !confirm('Terima pengajuan pinjaman ini?')) return;
  $.ajax({
    url: '/gas/gas_web/api/pinjaman_kredit/admin_action.php',
    method: 'POST',
    data: {id:id, action:action, reason:reason},
    dataType: 'json',
    timeout: 15000,
    success: function(data){
      if (data && data.status) { alert('Sukses'); location.reload(); } else { alert('Error: ' + (data && data.message)); }
    },
    error: function(jqxhr, status, err){
      var resp = jqxhr && jqxhr.responseText ? jqxhr.responseText : '';
      console.error('AJAX fail', status, err, resp);
      var msg = 'Terjadi kesalahan saat menghubungi server.';
      try{ var parsed = JSON.parse(resp); if (parsed && parsed.message) msg = 'Server: ' + parsed.message; } catch(e){ if (resp) msg += '\nRespon server: ' + (resp.length>300? resp.substr(0,300)+'...': resp); }
      alert(msg);
    }
  });
}

// Fix: ensure dropdowns appended to body to avoid clipping
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('table#kredit_table .dropdown').forEach(function(dropdownEl){
    var toggle = dropdownEl.querySelector('.dropdown-toggle');
    var menu = dropdownEl.querySelector('.dropdown-menu');
    if (!toggle || !menu) return;
    dropdownEl.addEventListener('show.bs.dropdown', function(){
      try{ menu.__orig_parent = menu.parentNode; document.body.appendChild(menu); menu.style.position='absolute'; menu.style.zIndex=3000; var rect = toggle.getBoundingClientRect(); menu.style.left = (rect.left + window.scrollX) + 'px'; menu.style.top = (rect.bottom + window.scrollY) + 'px'; menu.setAttribute('data-appended-to-body','1'); }catch(e){}
    });
    dropdownEl.addEventListener('hide.bs.dropdown', function(){ try{ if (menu.getAttribute('data-appended-to-body')==='1'){ menu.removeAttribute('data-appended-to-body'); menu.style.position=''; menu.style.left=''; menu.style.top=''; menu.style.zIndex=''; if (menu.__orig_parent) menu.__orig_parent.appendChild(menu); } }catch(e){} });
  });
});
</script>