<?php
// admin approval page for user activations
include "../dashboard/head.php";
include "../dashboard/icon.php";
include "../dashboard/header.php";
// Load config and helper safely using realpath() candidates
$configCandidates = [ realpath(__DIR__ . '/../koneksi/config.php'), realpath(__DIR__ . '/../../koneksi/config.php'), realpath(dirname(__DIR__,2) . '/koneksi/config.php') ];
$cfgOk = false; foreach ($configCandidates as $c) { if ($c && file_exists($c)) { require_once $c; $cfgOk = true; break; } }
if (!$cfgOk) { http_response_code(500); echo 'Server config not found. Checked: ' . json_encode($configCandidates); exit(); }

// fungsi_indotgl helper
$fi = realpath(__DIR__ . '/../koneksi/fungsi_indotgl.php') ?: realpath(__DIR__ . '/../../koneksi/fungsi_indotgl.php');
if ($fi && file_exists($fi)) { require_once $fi; } else { http_response_code(500); echo 'Server config error: fungsi_indotgl.php missing.'; exit(); }

// Show only users with exact status_akun = 'approved'. This excludes pending/rejected and avoids using legacy synonyms.
$sql = "SELECT id, no_hp, nama_lengkap, alamat_domisili, tanggal_lahir, created_at, status_akun, (NULL) AS status_verifikasi FROM pengguna WHERE LOWER(status_akun) = 'approved' ORDER BY created_at ASC";
 
$res = $con->query($sql);
$users = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $users[] = $r;
    }
}

// Helper to format phone for display: convert leading '62' to '0' (local format)
function displayLocalPhone($hp) {
    $hp = (string)($hp ?? '');
    $s = preg_replace('/[^0-9+]/', '', $hp);
    if ($s === '') return '';
    if ($s[0] === '+') $s = substr($s,1);
    if (strncmp($s, '62', 2) === 0) return '0' . substr($s,2);
    if ($s[0] === '0') return $s;
    return $s;
}

// Date formatting helper: returns DD/MM/YYYY or DD/MM/YYYY HH:MM:SS WIB when $withTime=true
function format_display_date($d, $withTime = true) {
    if (empty($d) || trim((string)$d) === '') return '-';
    $ts = strtotime($d);
    if ($ts === false || $ts <= 0) return $d;
    if ($withTime) {
        return date('d/m/Y H:i:s', $ts) . ' WIB';
    }
    return date('d/m/Y', $ts);
}
?>
<div class="container-fluid">
  <div class="row">
    <?php include "../dashboard/menu.php"; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <div class="btn-toolbar mb-2 mb-md-0"></div>
      </div>

      <div class="card">
        <div class="card-header" align="center">
          <h5 class="mb-0">Semua Pengguna</h5>
        </div>
        <div class="card-body">
          <style>
            /* Adopt Pencairan Tabungan table styles for users table */
            #users_table { font-size: 0.88rem; table-layout: auto; width: 100%; }
            #users_table th {
              padding: 10px 8px; white-space: nowrap; font-weight: 700; background-color: #f6f7fb; border: 1px solid #e8e9ed; color: #344054; font-size: 0.9rem; text-align: center !important;
            }
            /* Hide DataTables sort icons for cleaner header */
            #users_table thead th:after, #users_table thead th:before { display:none !important; }
            #users_table td { padding: 10px 8px; vertical-align: middle; overflow: hidden; border: 1px solid #eef0f4; color: #222; white-space: nowrap; text-overflow: ellipsis; }
            .table-responsive { overflow-x: auto; max-width: 100%; }
            .btn-sm { padding: 0.35rem 0.5rem; font-size: 0.78rem; margin: 2px; }
            .cell-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
            .cell-wrap { display: block; white-space: normal; word-wrap: break-word; overflow-wrap: anywhere; max-width: 320px; }
            /* Column sizing to mimic Pencairan layout (8 columns) */
            #users_table td:nth-child(1) { width: 4%; text-align: center; }
            #users_table td:nth-child(2) { width: 18%; text-align: center; }
            #users_table td:nth-child(3) { width: 14%; text-align: left; }
            #users_table td:nth-child(4) { width: 22%; text-align: left; }
            #users_table td:nth-child(5) { width: 16%; text-align: left; }
            #users_table td:nth-child(6) { width: 12%; text-align: center; }
            #users_table td:nth-child(7) { width: 10%; text-align: center; }
            #users_table td:nth-child(8) { width: 6%; text-align: center; }
            .table { margin-bottom: 0; border-radius: 8px; }
            #users_table tbody tr:hover { background: #fbfcff; }
          </style>

          <!-- Filter UI removed: status filter is deprecated and no longer used -->

          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100" id="users_table">
              <thead>
                <tr>
                  <th class="text-center">No</th>
                  <th class="text-center">Waktu</th>
                  <th class="text-center">Nomor HP</th>
                  <th class="text-center">Nama</th>
                  <th class="text-center">Alamat</th>
                  <th class="text-center">Tgl Lahir</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php $i=1; foreach ($users as $u): ?>
                <?php
                  // Prefer status_akun for display (we only list users with status_akun = 'approved')
                  $st_text = htmlspecialchars($u['status_akun'] ?? $u['status_verifikasi'] ?? '', ENT_QUOTES, 'UTF-8');
                  $st_uc = strtoupper(trim($st_text));
                  $badge = 'bg-secondary';
                  $statusSlug = 'other';
                  if (strpos($st_uc, 'PEND') !== false) { $badge = 'bg-warning'; $statusSlug = 'pending'; }
                  else if (strpos($st_uc, 'APPROV') !== false || strpos($st_uc, 'TER') !== false || strpos($st_uc, 'AKTIF') !== false) { $badge = 'bg-success'; $statusSlug = 'approved'; }
                  else if (strpos($st_uc, 'DITOLAK') !== false || strpos($st_uc, 'REJECT') !== false) { $badge = 'bg-danger'; $statusSlug = 'rejected'; }
                ?>
                <tr data-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>">
                  <td><?php echo $i++; ?></td>
                  <td data-order="<?php echo htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(format_display_date($u['created_at'], true), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(displayLocalPhone($u['no_hp']), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="ud_cell_nama"><?php echo htmlspecialchars($u['nama_lengkap'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="ud_cell_alamat"><?php echo htmlspecialchars($u['alamat_domisili'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="ud_cell_tgl" data-order="<?php echo htmlspecialchars($u['tanggal_lahir'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(format_display_date($u['tanggal_lahir'], false), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td class="ud_cell_status"><span class="badge <?php echo $badge; ?>"><?php echo $st_text; ?></span></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="actionMenu<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
                      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionMenu<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <li><a class="dropdown-item" href="#" onclick="showUserDetail(event,this)" data-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>">Detail</a></li>
                        <li><a class="dropdown-item" href="#" onclick="showUserDetail(event,this)" data-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>" data-action="edit">Edit</a></li>
                        <?php
                          $sv = strtoupper(trim($u['status_verifikasi'] ?? $u['status_akun'] ?? ''));
                          if (strpos($sv, 'PEND') !== false) {
                        ?>
                        <li><a class="dropdown-item" href="#" onclick="approveUser('<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>')">Terima Aktivasi</a></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="rejectUser('<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>')">Tolak Aktivasi</a></li>
                        <?php } ?>
                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser('<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>', false)">Hapus</a></li>
                      </ul>
                    </div>
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

<?php include "../dashboard/js.php"; ?>
<script>
function approveUser(id){
  if (!confirm('Terima aktivasi pengguna ini?')) return;
  fetch('approve_user_process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, action:'approve'})})
    .then(async r=>{
      const text = await r.text();
      try{
        const j = text ? JSON.parse(text) : {};
        if (j.success) { alert('User diaktifkan'); location.reload(); } else alert('Error: '+(j.message || text || 'unknown'));
      } catch(err){ console.error('approve parse error', err, text); alert('Error: Response parsing failed (server error)'); }
    }).catch(e=>{ console.error('approve error', e); alert('Network or server error: ' + (e.message || e)); });
}
function rejectUser(id){
  var reason = prompt('Alasan reject (opsional)');
  if (reason===null) return;
  fetch('approve_user_process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, action:'reject', reason:reason})})
    .then(async r=>{
      const text = await r.text();
      try{
        const j = text ? JSON.parse(text) : {};
        if (j.success) { alert('User direject'); location.reload(); } else alert('Error: '+(j.message || text || 'unknown'));
      } catch(err){ console.error('reject parse error', err, text); alert('Error: Response parsing failed (server error)'); }
    }).catch(e=>{ console.error('reject error', e); alert('Network or server error: ' + (e.message || e)); });
}

function deleteUser(id, soft = true){
  var msg = soft ? 'Nonaktifkan pengguna ini? Pengguna tidak akan bisa login lagi.' : 'Hapus pengguna ini? Tindakan tidak dapat dibatalkan.';
  if (!confirm(msg)) return;
  var form = new URLSearchParams();
  form.append('id_pengguna', id);
  if (soft) form.append('soft','1');
  fetch('../../../flutter_api/delete_user.php', {method:'POST', body: form})
    .then(r=>r.json()).then(j=>{
      if (j.success) { alert(soft ? 'Pengguna dinonaktifkan' : 'Pengguna dihapus'); location.reload(); } else alert('Error: '+(j.message||'')); 
    }).catch(e=>{ console.error('deleteUser error', e); alert('Error menghapus pengguna'); });
}
// format number to Indonesian Rupiah
function formatRp(v){
  var n = Number(v);
  if (!isFinite(n)) n = 0;
  return 'Rp ' + n.toLocaleString('id-ID');
}      function formatDateLocal(s){
        if (!s) return '-';
        // reuse the same logic as formatDateIndo: return DD/MM/YYYY HH:MM:SS WIB (or only date when time missing)
        var str = String(s).trim();
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d.getTime())) {
          var parts = str.split(' ');
          var dp = parts[0] ? parts[0].split('-') : [];
          var tp = parts[1] ? parts[1].split(':') : [];
          if (dp.length === 3) d = new Date(dp[0], dp[1]-1, dp[2], parseInt(tp[0]||0,10), parseInt(tp[1]||0,10), parseInt(tp[2]||0,10));
        }
        if (!d || isNaN(d.getTime())) return str;
        function pad(n){ return ('0'+n).slice(-2); }
        var day = pad(d.getDate()); var month = pad(d.getMonth()+1); var year = d.getFullYear();
        var hh = pad(d.getHours()); var mm = pad(d.getMinutes()); var ss = pad(d.getSeconds());
        // if time is 00:00:00 and original had no time part, show only date
        if (hh === '00' && mm === '00' && ss === '00' && String(s).indexOf(':') === -1) return day + '/' + month + '/' + year;
        return day + '/' + month + '/' + year + ' ' + hh + ':' + mm + ':' + ss + ' WIB';
      }
function showUserDetail(e, el){
  e.preventDefault();
  var a = el;
  var id = a.getAttribute('data-id') || '-';
  var editMode = (a.getAttribute('data-action') === 'edit' || a.dataset.action === 'edit');

  // set loading
  document.getElementById('ud_id').textContent = id;
  document.getElementById('ud_no').textContent = '...';
  document.getElementById('ud_nama').textContent = '...';
  document.getElementById('ud_alamat').textContent = '...';
  document.getElementById('ud_tgl').textContent = '...';
  document.getElementById('ud_status').textContent = '...';
  document.getElementById('ud_created').textContent = '...';

  // fetch fresh profile from server
  fetch('../../../flutter_api/get_profil.php?id_pengguna=' + encodeURIComponent(id))
    .then(async r=>{
      var text = await r.text();
      try {
        var j = text ? JSON.parse(text) : {};
      } catch(err){
        // response not JSON
        if (!r.ok) throw new Error('HTTP status ' + r.status);
        throw err;
      }
      if (!r.ok) throw new Error(j.message || ('HTTP status ' + r.status));
      return j;
    }).then(j=>{
      if (!j.success) { alert('Gagal mengambil detail: ' + (j.message||'')); return; }
      var data = j.data;
      var statusText = data.status_verifikasi || data.status_akun || '';
      document.getElementById('ud_id').textContent = data.id || '';
      document.getElementById('ud_no').textContent = data.no_hp || '';
      document.getElementById('ud_nama').textContent = data.nama_lengkap || '';
      document.getElementById('ud_alamat').textContent = data.alamat_domisili || '';
      document.getElementById('ud_tgl').textContent = formatDateLocal(data.tanggal_lahir || '');
      document.getElementById('ud_status').textContent = statusText;
      document.getElementById('ud_saldo').textContent = (typeof data.saldo !== 'undefined' ? formatRp(data.saldo) : formatRp(0));
      document.getElementById('ud_created').textContent = formatDateLocal(data.created_at || ''); 

      // populate hidden inputs for edit
      document.getElementById('ud_input_id').value = data.id || '';
      document.getElementById('ud_input_nama').value = data.nama_lengkap || '';
      document.getElementById('ud_input_no').value = data.no_hp || '';
      document.getElementById('ud_input_alamat').value = data.alamat_domisili || '';
      document.getElementById('ud_input_tgl').value = data.tanggal_lahir || '';
      // set status select to the current account status (status_akun)
      if (document.getElementById('ud_input_status_display')) document.getElementById('ud_input_status_display').value = data.status_akun || ''; if (document.getElementById('ud_input_status')) document.getElementById('ud_input_status').value = data.status_akun || ''; 

      // helper to open image preview (lightbox)
      function openImageLightbox(url, title){
        if (!url) { alert('Gambar tidak tersedia'); return; }
        var modalEl = document.getElementById('imagePreviewModal');
        var img = document.getElementById('imgPreview');
        var ttl = document.getElementById('imgPreviewTitle');
        var link = document.getElementById('imgPreviewLink');
        img.src = url;
        ttl.textContent = title || '';
        link.href = url;
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
      }

      // helper to set image or hide row if not present; also wire click handlers
      function setImage(idImg, idLink, url){
        var img = document.getElementById(idImg);
        var link = document.getElementById(idLink);
        // find surrounding <dd> and its preceding <dt>
        var dd = img ? img.closest('dd') : null;
        var dt = dd ? dd.previousElementSibling : null;
        // placeholder for Foto Profil (keep label visible even when no photo)
        var placeholder = null;
        if (idImg === 'ud_img_profil') placeholder = document.getElementById('ud_no_foto_profil');

        if (url && img){
          img.src = url;
          img.style.display = 'inline-block';
          img.onclick = function(){ openImageLightbox(url, (link && link.dataset ? link.dataset.title : '') || ''); };
          if (link){
            link.href = url;
            link.dataset.url = url;
            link.style.display = 'inline-block';
            link.onclick = function(e){ e.preventDefault(); openImageLightbox(url, (link.dataset ? link.dataset.title : '') || ''); };
          }
          // show the value area
          if (dd) dd.style.display = '';
          // hide placeholder if any
          if (placeholder) placeholder.style.display = 'none';
          // for non-profil images, ensure label shown (default)
          if (dt) dt.style.display = '';
        } else {
          if (img){ img.style.display = 'none'; img.onclick = null; }
          if (link){ link.style.display = 'none'; link.onclick = null; link.dataset.url = ''; }

          // If this is Foto Profil, show placeholder text but keep label visible
          if (placeholder){
            placeholder.style.display = 'inline-block';
            if (dd) dd.style.display = '';
            if (dt) dt.style.display = '';
          } else {
            // hide the whole row (label + value) for other image types
            if (dd) dd.style.display = 'none';
            if (dt) dt.style.display = 'none';
          }
        }
      }

      // Prefer a signed URL from API (data.foto_profil); if not present, do NOT show the profile row at all
      var proxy = data.foto_profil || null;
      setImage('ud_img_profil','ud_link_profil', proxy);
      // For KTP/selfie use an admin-only proxy and always pass the user id (id_pengguna) via user_id.
      var proxyBase = '../verification_image.php';
      var ktpUrl = null, selfieUrl = null;
      if (data.has_ktp) {
        ktpUrl = proxyBase + '?user_id=' + encodeURIComponent(data.id) + '&type=ktp';
      }
      if (data.has_selfie) {
        selfieUrl = proxyBase + '?user_id=' + encodeURIComponent(data.id) + '&type=selfie';
      }
      setImage('ud_img_ktp','ud_link_ktp', ktpUrl);
      setImage('ud_img_selfie','ud_link_selfie', selfieUrl);

      setEditMode(false);

      // use hard delete from modal to match "Hapus" intent
      var deleteBtn = document.getElementById('ud_delete');
      if (deleteBtn){
        deleteBtn.onclick = function(){
          var ok = confirm('Hapus pengguna ini? Tindakan ini tidak dapat dibatalkan.');
          if (!ok) return;
          var modalEl = document.getElementById('userDetailModal');
          var modal = bootstrap.Modal.getInstance(modalEl);
          if (modal) modal.hide();
          deleteUser(id, false);
        };
      }

      var modal = new bootstrap.Modal(document.getElementById('userDetailModal'));
      modal.show();

      if (editMode) setEditMode(true);
    }).catch(e=>{
      console.error('showUserDetail fetch error', e);
      alert('Error mengambil data pengguna: ' + (e.message || '')); 
    });
} 

function setEditMode(enabled){
  var body = document.getElementById('userDetailModalBody');
  var view = body.querySelector('.view-mode');
  var form = document.getElementById('ud_edit_form');
  var titleEl = document.getElementById('userDetailModalLabel');
  if (enabled){
    body.classList.add('editing');
    if (view) view.style.display = 'none';
    if (form) form.style.display = 'block';
    var btnEdit = document.getElementById('ud_edit');
    if (btnEdit){ btnEdit.textContent = 'Simpan'; btnEdit.style.display = 'inline-block'; }
    document.getElementById('ud_cancel').style.display = 'inline-block';
    // update title to indicate edit mode
    if (titleEl) titleEl.textContent = 'Edit Pengguna';
    // copy current display values into inputs (in case direct edits were made)
    var id = document.getElementById('ud_id').textContent;
    if (id) document.getElementById('ud_input_id').value = id;
  } else {
    body.classList.remove('editing');
    if (view) view.style.display = 'block';
    if (form) form.style.display = 'none';
    var btnEdit = document.getElementById('ud_edit');
    if (btnEdit){ btnEdit.textContent = 'Edit'; btnEdit.style.display = 'none'; }
    document.getElementById('ud_cancel').style.display = 'none';
    // restore title to detail mode
    if (titleEl) titleEl.textContent = 'Detail Pengguna';
  }
}

function toggleEdit(){
  var body = document.getElementById('userDetailModalBody');
  var isEditing = body.classList.contains('editing');
  if (!isEditing){
    setEditMode(true);
    return;
  }
  // if editing, perform save
  saveUser();
}

function saveUser(){
  var id = document.getElementById('ud_input_id').value;
  var nama = document.getElementById('ud_input_nama').value.trim();
  var nohp = document.getElementById('ud_input_no').value.trim();
  var alamat = document.getElementById('ud_input_alamat').value.trim();
  var tgl = document.getElementById('ud_input_tgl').value.trim();

  if (!id) { alert('ID pengguna tidak ditemukan'); return; }
  if (!nama || !nohp) { alert('Nama dan Nomor HP wajib diisi'); return; }

  var form = new URLSearchParams();
  form.append('id', id);
  form.append('nama_lengkap', nama);
  form.append('no_hp', nohp);
  form.append('alamat_domisili', alamat);
  form.append('tanggal_lahir', tgl);
  // DO NOT send status_akun from this modal; status is controlled by activation workflow

  fetch('../../../flutter_api/admin_update_user.php', {method:'POST', body: form})
    .then(r=>{
      if (!r.ok) throw new Error('HTTP status ' + r.status);
      return r.json();
    }).then(j=>{
      if (!j.success){ alert('Gagal menyimpan: ' + (j.message||'')); return; }
      alert('Perubahan disimpan');
      // update modal view
      document.getElementById('ud_nama').textContent = nama;
      document.getElementById('ud_no').textContent = nohp;
      document.getElementById('ud_alamat').textContent = alamat;
      document.getElementById('ud_tgl').textContent = formatDateLocal(tgl);
      // keep status readonly; reflect display-only field
      var statusDisplay = (document.getElementById('ud_input_status_display') && document.getElementById('ud_input_status_display').value) ? document.getElementById('ud_input_status_display').value : '';
      document.getElementById('ud_status').textContent = statusDisplay;
      setEditMode(false);

      // update table row if visible
      var row = document.querySelector('tr[data-id="' + id + '"]');
      if (row){
        var cellNama = row.querySelector('.ud_cell_nama'); if (cellNama) cellNama.textContent = nama;
        var cellAlamat = row.querySelector('.ud_cell_alamat'); if (cellAlamat) cellAlamat.textContent = alamat;
        var cellTgl = row.querySelector('.ud_cell_tgl'); if (cellTgl) cellTgl.textContent = formatDateLocal(tgl);
        var cellStatus = row.querySelector('.ud_cell_status'); if (cellStatus) {
          var stuc = (statusDisplay || '').toUpperCase();
          var badge = 'bg-secondary';
          if (stuc.indexOf('PEND') !== -1) badge = 'bg-warning';
          // include 'APPROV' so 'approved' (English) maps to green like 'TER'/'AKTIF'
          else if (stuc.indexOf('APPROV') !== -1 || stuc.indexOf('TER') !== -1 || stuc.indexOf('AKTIF') !== -1) badge = 'bg-success';
          else if (stuc.indexOf('DITOLAK') !== -1 || stuc.indexOf('REJECT') !== -1) badge = 'bg-danger';
          cellStatus.innerHTML = '<span class="badge ' + badge + '">' + statusDisplay + '</span>';
        }
      }

    }).catch(e=>{ alert('Error saat menyimpan'); });
}

function cancelEdit(){
  setEditMode(false);
}
</script>
<!-- DATA TABLE JS-->
<script src="../../../assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../../../assets/plugins/datatable/js/dataTables.bootstrap5.js"></script>
<script src="../../../assets/plugins/datatable/js/dataTables.buttons.min.js"></script>
<script src="../../../assets/plugins/datatable/js/buttons.bootstrap5.min.js"></script>
<script src="../../../assets/plugins/datatable/js/jszip.min.js"></script>
<script src="../../../assets/plugins/datatable/pdfmake/pdfmake.min.js"></script>
<script src="../../../assets/plugins/datatable/pdfmake/vfs_fonts.js"></script>
<script src="../../../assets/plugins/datatable/js/buttons.html5.min.js"></script>
<script src="../../../assets/plugins/datatable/js/buttons.print.min.js"></script>
<script src="../../../assets/plugins/datatable/js/buttons.colVis.min.js"></script>
<script src="../../../assets/plugins/datatable/dataTables.responsive.min.js"></script>
<script src="../../../assets/plugins/datatable/responsive.bootstrap5.min.js"></script>
<script src="../../../assets/js/table-data.js"></script>

<script>
  var usersTable = null; 

  $(document).ready(function(){
    if ($('#users_table').length) {
      usersTable = $('#users_table').DataTable({
        order: [[1, 'asc']],
        dom: 'rtip',
        searching: true,
        lengthChange: false,
        language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
      });


    }


  });
</script>

<!-- User Detail Modal -->
<div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userDetailModalLabel">Detail Pengguna</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div id="userDetailModalBody" class="modal-body">
        <dl class="row mb-0 view-mode">
          <dt class="col-sm-4">ID</dt><dd class="col-sm-8" id="ud_id"></dd>
          <dt class="col-sm-4">Nomor HP</dt><dd class="col-sm-8" id="ud_no"></dd>
          <dt class="col-sm-4">Nama</dt><dd class="col-sm-8" id="ud_nama"></dd>
          <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8" id="ud_alamat"></dd>
          <dt class="col-sm-4">Tgl Lahir</dt><dd class="col-sm-8" id="ud_tgl"></dd>
          <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="ud_status"></dd>
          <dt class="col-sm-4">Saldo</dt><dd class="col-sm-8" id="ud_saldo"></dd>
          <dt class="col-sm-4">Waktu Daftar</dt><dd class="col-sm-8" id="ud_created"></dd>

          <dt class="col-sm-4">Foto Profil</dt>
          <dd class="col-sm-8" id="ud_foto_profil">
            <img id="ud_img_profil" src="" alt="Foto Profil" loading="lazy" style="max-width:120px; display:none; cursor:pointer;" onclick="openImageLightbox(this.src, 'Foto Profil')" />
            <a id="ud_link_profil" href="#" data-title="Foto Profil" style="display:none; margin-left:8px;" onclick="event.preventDefault(); openImageLightbox(this.dataset.url||this.href, this.dataset.title||'Foto Profil')">Lihat</a>
            <span id="ud_no_foto_profil" class="text-muted" style="display:inline-block; margin-left:4px;">Tidak tersedia</span>
          </dd>

          <dt class="col-sm-4">Foto KTP</dt>
          <dd class="col-sm-8" id="ud_foto_ktp">
            <img id="ud_img_ktp" src="" alt="Foto KTP" loading="lazy" style="max-width:240px; display:none; cursor:pointer;" onclick="openImageLightbox(this.src, 'Foto KTP')" />
            <a id="ud_link_ktp" href="#" data-title="Foto KTP" style="display:none; margin-left:8px;" onclick="event.preventDefault(); openImageLightbox(this.dataset.url||this.href, this.dataset.title||'Foto KTP')">Lihat</a>
          </dd>

          <dt class="col-sm-4">Foto Selfie</dt>
          <dd class="col-sm-8" id="ud_foto_selfie">
            <img id="ud_img_selfie" src="" alt="Foto Selfie" loading="lazy" style="max-width:240px; display:none; cursor:pointer;" onclick="openImageLightbox(this.src, 'Foto Selfie')" />
            <a id="ud_link_selfie" href="#" data-title="Foto Selfie" style="display:none; margin-left:8px;" onclick="event.preventDefault(); openImageLightbox(this.dataset.url||this.href, this.dataset.title||'Foto Selfie')">Lihat</a>
          </dd>
        </dl>

        <!-- Edit form (hidden until Edit pressed) -->
        <form id="ud_edit_form" style="display:none; margin-top:10px;">
          <input type="hidden" id="ud_input_id" name="id">
          <div class="mb-2">
            <label class="form-label">Nama Lengkap</label>
            <input class="form-control" id="ud_input_nama" name="nama_lengkap" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Nomor HP</label>
            <input class="form-control" id="ud_input_no" name="no_hp" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Alamat</label>
            <input class="form-control" id="ud_input_alamat" name="alamat_domisili">
          </div>
          <div class="mb-2">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" class="form-control" id="ud_input_tgl" name="tanggal_lahir">
          </div>
          <div class="mb-2">
            <label class="form-label">Status Akun</label>
            <input class="form-control" id="ud_input_status_display" readonly>
            <input type="hidden" id="ud_input_status" name="status_akun" value="">
            <div class="form-text">Status akun hanya dapat diubah melalui proses aktivasi/penolakan; bukan melalui form ini.</div>
          </div> 
        </form>
      </div>
      <div class="modal-footer">
        <!-- Buttons moved to dropdown actions. Save/Cancel still available when editing. -->
        <button type="button" id="ud_edit" class="btn btn-primary" style="display:none;" onclick="toggleEdit()">Simpan</button>
        <button type="button" id="ud_cancel" class="btn btn-secondary" style="display:none;" onclick="cancelEdit()">Batal</button>
        <!-- Close button removed: header X is sufficient to dismiss the modal -->
      </div>
    </div>
  </div>
</div>

<!-- Image Preview / Lightbox Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body text-center">
        <h5 id="imgPreviewTitle" style="margin-bottom:8px;"></h5>
        <img id="imgPreview" src="" style="max-width:100%; height:auto;" alt="Preview" />
      </div>
      <div class="modal-footer">
        <a id="imgPreviewLink" href="#" target="_blank" class="btn btn-light">Buka di tab baru</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Fix: Ensure dropdown menus in the users table are not clipped by overflow and appear above other elements -->
<style>
  /* Allow the table wrapper to show overflow (so menus are not clipped) */
  #users_table_wrapper .table-responsive { overflow: visible !important; }
  /* Ensure dropdowns have a high z-index when appended to body */
  #users_table .dropdown-menu, body > .dropdown-menu { z-index: 3000 !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function(){
  // If Bootstrap's dropdown is clipped by table overflow, clone the menu into body on show and remove the clone on hide.
  document.querySelectorAll('#users_table .dropdown').forEach(function(dropdownEl){
    var toggle = dropdownEl.querySelector('.dropdown-toggle');
    var menu = dropdownEl.querySelector('.dropdown-menu');
    if (!toggle || !menu) return;

    // helper to create a clone and position it
    function showClone(){
      try {
        // avoid creating multiple clones
        if (menu.__clone && document.body.contains(menu.__clone)) return;

        var clone = menu.cloneNode(true);
        clone.classList.add('cloned-dropdown-menu');
        clone.style.position = 'absolute';
        clone.style.display = 'block';
        clone.style.zIndex = 3000;
        clone.setAttribute('data-cloned','1');

        document.body.appendChild(clone);
        menu.__clone = clone;

        // position menu relative to toggle
        var rect = toggle.getBoundingClientRect();
        var mw = clone.offsetWidth;
        var left = rect.left + window.scrollX; // default align left
        if (left + mw > window.scrollX + window.innerWidth) {
          left = rect.right + window.scrollX - mw;
        }
        if (left < window.scrollX) left = window.scrollX + 5;
        var top = rect.bottom + window.scrollY;

        clone.style.left = left + 'px';
        clone.style.top = top + 'px';

        // hide original menu visually to avoid duplicates on some browsers
        menu.style.visibility = 'hidden';
      } catch (err) { console.error('dropdown clone show error', err); }
    }

    function hideClone(){
      try {
        if (menu.__clone && document.body.contains(menu.__clone)) {
          document.body.removeChild(menu.__clone);
        }
        menu.__clone = null;
        menu.style.visibility = '';
      } catch (err) { console.error('dropdown clone hide error', err); }
    }

    dropdownEl.addEventListener('show.bs.dropdown', function(e){ showClone(); });
    dropdownEl.addEventListener('hide.bs.dropdown', function(e){ hideClone(); });

    // hide clone on scroll to avoid misplacement
    window.addEventListener('scroll', function(){
      if (menu.__clone) {
        var bsDropdown = bootstrap.Dropdown.getInstance(toggle);
        if (bsDropdown) bsDropdown.hide();
      }
    }, { passive: true });
  });

  // Failsafe: ensure existing dropdowns initialized
  document.querySelectorAll('#users_table .dropdown-toggle').forEach(function(btn){
    try{ new bootstrap.Dropdown(btn); }catch(e){}
  });
});
</script>
