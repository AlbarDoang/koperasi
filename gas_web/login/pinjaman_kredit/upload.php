<?php
// login/pinjaman_kredit/upload.php
// Step 3: show KTP preview from user data, accept required foto_barang and optional link, submit to API
include '../dashboard/head.php';
include '../dashboard/header.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
} else { header('Location: create.php'); exit; }

$id_pengguna = (int)($input['id_pengguna'] ?? 0);
if ($id_pengguna<=0) { header('Location: create.php'); exit; }

// Fetch user KTP if exists
$user = null;
$r = mysqli_query($con, "SELECT nama_lengkap, no_hp, nik, foto_profil FROM pengguna WHERE id = $id_pengguna LIMIT 1");
if ($r) { $user = mysqli_fetch_assoc($r); mysqli_free_result($r); }

?>
<div class="container-fluid">
  <div class="row">
    <?php include '../dashboard/menu.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h5>Upload Dokumen Kredit</h5>
      </div>

      <div class="card">
        <div class="card-body">
          <p>Data identitas diambil dari data pendaftaran. <strong>Silakan periksa, tidak perlu upload ulang e-KTP.</strong></p>
          <dl>
            <dt>Nama</dt><dd><?php echo htmlspecialchars($user['nama_lengkap'] ?? '-'); ?></dd>
            <dt>No HP</dt><dd><?php echo htmlspecialchars($user['no_hp'] ?? '-'); ?></dd>
            <dt>NIK</dt><dd><?php echo htmlspecialchars($user['nik'] ?? '-'); ?></dd>
            <dt>Preview e-KTP</dt><dd><?php if (!empty($user['foto_profil'])) { echo '<img src="/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($id_pengguna) . '" style="max-width:250px">'; } else echo '<span class="text-muted">Tidak tersedia</span>'; ?></dd>
          </dl>

          <form id="kreditUploadForm" action="/gas/gas_web/api/pinjaman_kredit/submit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_pengguna" value="<?php echo htmlspecialchars($id_pengguna); ?>">
            <input type="hidden" name="nama_barang" value="<?php echo htmlspecialchars($input['nama_barang']); ?>">
            <input type="hidden" name="harga" value="<?php echo htmlspecialchars($input['harga']); ?>">
            <input type="hidden" name="dp" value="<?php echo htmlspecialchars($input['dp']); ?>">
            <input type="hidden" name="tenor" value="<?php echo htmlspecialchars($input['tenor']); ?>">
            <input type="hidden" name="cicilan_per_bulan" value="<?php echo htmlspecialchars($input['cicilan_per_bulan']); ?>">
            <input type="hidden" name="total_bayar" value="<?php echo htmlspecialchars($input['total_bayar']); ?>">
            <input type="hidden" name="accepted_terms" value="1">

            <div class="mb-3">
              <label class="form-label">Foto barang (wajib)</label>
              <input class="form-control" type="file" name="foto_barang" accept="image/*" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Link toko / bukti harga (opsional)</label>
              <input class="form-control" type="url" name="link_bukti_harga">
            </div>
            <div class="mb-3">
              <button class="btn btn-primary" id="sendKreditBtn">Kirim Pengajuan</button>
            </div>
          </form>

          <script>
          $(function(){
            $('#kreditUploadForm').on('submit', function(e){
              e.preventDefault();
              var form = document.getElementById('kreditUploadForm');
              var fd = new FormData(form);
              $('#sendKreditBtn').attr('disabled', true).text('Mengirim...');
              $.ajax({
                url: $(form).attr('action'),
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
              }).done(function(data){
                if (data && data.status) {
                  alert(data.message || 'Pengajuan terkirim');
                  window.location.href = '/gas/gas_web/login/pinjaman_kredit/status.php';
                } else {
                  alert('Error: ' + (data && data.message ? data.message : 'Tidak diketahui'));
                  $('#sendKreditBtn').attr('disabled', false).text('Kirim Pengajuan');
                }
              }).fail(function(){
                alert('Terjadi kesalahan saat mengirim.');
                $('#sendKreditBtn').attr('disabled', false).text('Kirim Pengajuan');
              });
            });
          });
          </script>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include '../dashboard/js.php'; ?>