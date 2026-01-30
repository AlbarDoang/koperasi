<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    <?php include "../dashboard/icon.php"; ?>
    <?php include "../dashboard/header.php"; ?>

    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['akses']) || $_SESSION['akses'] !== 'admin') {
        http_response_code(403);
        echo "<div class='container mt-4'><div class='alert alert-danger'>Akses ditolak.</div></div>";
        exit;
    }

    include '../../koneksi/config.php';

    // detect available columns on this installation
    $colNames = [];
    $has_setoran = $has_saldo = $has_biaya = $has_status = false;
    if ($rc = $con->query("SHOW COLUMNS FROM jenis_tabungan")) {
        while ($c = $rc->fetch_assoc()) $colNames[] = $c['Field'];
        $has_setoran = in_array('setoran_minimum', $colNames);
        $has_saldo = in_array('saldo_minimum', $colNames);
        $has_biaya = in_array('biaya_administrasi', $colNames);
        $has_status = in_array('status', $colNames);
    }

    // Handle POST actions: add, edit, delete
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $nama = trim($_POST['nama'] ?? '');
            $setoran_min = isset($_POST['setoran_min']) && $_POST['setoran_min'] !== '' ? floatval($_POST['setoran_min']) : 0.0;
            $saldo_min = isset($_POST['saldo_min']) && $_POST['saldo_min'] !== '' ? floatval($_POST['saldo_min']) : 0.0;
            $biaya_admin = isset($_POST['biaya_admin']) && $_POST['biaya_admin'] !== '' ? floatval($_POST['biaya_admin']) : 0.0;
            $status = (isset($_POST['status']) && $_POST['status'] === '1') ? 1 : 0;

            if ($nama === '') $errors[] = 'Nama jenis wajib diisi.';
            if (empty($errors)) {
                if ($has_setoran || $has_saldo || $has_biaya || $has_status) {
                    $ins = $con->prepare("INSERT INTO jenis_tabungan (nama_jenis, setoran_minimum, saldo_minimum, biaya_administrasi, status) VALUES (?, ?, ?, ?, ?)");
                    if ($ins) {
                        $ins->bind_param('sdddi', $nama, $setoran_min, $saldo_min, $biaya_admin, $status);
                        if (!$ins->execute()) {
                            $errors[] = 'Gagal menambahkan jenis: ' . htmlspecialchars($ins->error ?: $con->error);
                            error_log('[jenis.php] add execute failed: ' . ($ins->error ?: $con->error) . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                            $ins->close();
                        } else {
                            $ins->close();
                            header('Location: ../pengaturan/jenis.php'); exit;
                        }
                    } else {
                        $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                        error_log('[jenis.php] add prepare failed: ' . $con->error . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                    }
                } else {
                    // minimal table (only nama_jenis available)
                    $ins = $con->prepare("INSERT INTO jenis_tabungan (nama_jenis) VALUES (?)");
                    if ($ins) {
                        $ins->bind_param('s', $nama);
                        if (!$ins->execute()) {
                            $errors[] = 'Gagal menambahkan jenis: ' . htmlspecialchars($ins->error ?: $con->error);
                            error_log('[jenis.php] add minimal execute failed: ' . ($ins->error ?: $con->error) . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                            $ins->close();
                        } else {
                            $ins->close(); header('Location: ../pengaturan/jenis.php'); exit;
                        }
                    } else {
                        $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                        error_log('[jenis.php] add minimal prepare failed: ' . $con->error . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                    }
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $setoran_min = isset($_POST['setoran_min']) && $_POST['setoran_min'] !== '' ? floatval($_POST['setoran_min']) : 0.0;
            $saldo_min = isset($_POST['saldo_min']) && $_POST['saldo_min'] !== '' ? floatval($_POST['saldo_min']) : 0.0;
            $biaya_admin = isset($_POST['biaya_admin']) && $_POST['biaya_admin'] !== '' ? floatval($_POST['biaya_admin']) : 0.0;
            $status = (isset($_POST['status']) && $_POST['status'] === '1') ? 1 : 0;

            if ($id <= 0) $errors[] = 'ID tidak valid.';
            if ($nama === '') $errors[] = 'Nama jenis wajib diisi.';
            if (empty($errors)) {
                if ($has_setoran || $has_saldo || $has_biaya || $has_status) {
                    $upd = $con->prepare("UPDATE jenis_tabungan SET nama_jenis = ?, setoran_minimum = ?, saldo_minimum = ?, biaya_administrasi = ?, status = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param('sdddii', $nama, $setoran_min, $saldo_min, $biaya_admin, $status, $id);
                        if (!$upd->execute()) {
                            $errors[] = 'Gagal memperbarui jenis: ' . htmlspecialchars($upd->error ?: $con->error);
                            error_log('[jenis.php] edit execute failed: ' . ($upd->error ?: $con->error) . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                            $upd->close();
                        } else {
                            $upd->close(); header('Location: ../pengaturan/jenis.php'); exit;
                        }
                    } else {
                        $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                        error_log('[jenis.php] edit prepare failed: ' . $con->error . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                    }
                } else {
                    $upd = $con->prepare("UPDATE jenis_tabungan SET nama_jenis = ? WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param('si', $nama, $id);
                        if (!$upd->execute()) {
                            $errors[] = 'Gagal memperbarui jenis: ' . htmlspecialchars($upd->error ?: $con->error);
                            error_log('[jenis.php] edit minimal execute failed: ' . ($upd->error ?: $con->error) . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                            $upd->close();
                        } else {
                            $upd->close(); header('Location: ../pengaturan/jenis.php'); exit;
                        }
                    } else {
                        $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                        error_log('[jenis.php] edit minimal prepare failed: ' . $con->error . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                    }
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $del = $con->prepare("DELETE FROM jenis_tabungan WHERE id = ?");
                if ($del) {
                    $del->bind_param('i', $id);
                    if (!$del->execute()) {
                        $errors[] = 'Gagal menghapus jenis: ' . htmlspecialchars($del->error ?: $con->error);
                        error_log('[jenis.php] delete execute failed: ' . ($del->error ?: $con->error) . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                        $del->close();
                    } else {
                        $del->close(); header('Location: ../pengaturan/jenis.php'); exit;
                    }
                } else {
                    $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                    error_log('[jenis.php] delete prepare failed: ' . $con->error . ' POST=' . json_encode(array_filter($_POST, function($k){ return !in_array($k, ['password','file','logo']); }, ARRAY_FILTER_USE_KEY)) );
                }
            } else $errors[] = 'ID tidak valid.';
        }
    }

    // Fetch all jenis (select columns depending on schema)
    $selectCols = 'id, nama_jenis';
    if ($has_setoran) $selectCols .= ', COALESCE(setoran_minimum,0) AS setoran_minimum';
    if ($has_saldo) $selectCols .= ', COALESCE(saldo_minimum,0) AS saldo_minimum';
    if ($has_biaya) $selectCols .= ', COALESCE(biaya_administrasi,0) AS biaya_administrasi';
    if ($has_status) $selectCols .= ', COALESCE(status,0) AS status';

    $jenis = [];
    $res = $con->query("SELECT {$selectCols} FROM jenis_tabungan ORDER BY id ASC");
    if ($res) { while ($r = $res->fetch_assoc()) $jenis[] = $r; }
    ?>

    <div class="container-fluid">
      <div class="row">
        <?php include "../dashboard/menu.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h5>Pengaturan Tabungan - Jenis Tabungan</h5>
            <div>
              <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addJenis">Tambah Jenis</button>
            </div>
          </div>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
            </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nama</th>
                      <th>Setoran Min</th>
                      <th>Saldo Min</th>
                      <th>Biaya Admin</th>
                      <th>Status</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($jenis as $j): ?>
                    <tr>
                      <td><?php echo intval($j['id']); ?></td>
                      <td><?php echo htmlspecialchars($j['nama_jenis'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo number_format(floatval($j['setoran_minimum'] ?? 0),2); ?></td>
                      <td><?php echo number_format(floatval($j['saldo_minimum'] ?? 0),2); ?></td>
                      <td><?php echo number_format(floatval($j['biaya_administrasi'] ?? 0),2); ?></td>
                      <td><?php echo (isset($j['status']) && $j['status'] == '1') ? 'Aktif' : 'Nonaktif'; ?></td>
                      <td>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editJenis<?php echo intval($j['id']); ?>">Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Hapus jenis ini?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo intval($j['id']); ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                      </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editJenis<?php echo intval($j['id']); ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post">
                          <div class="modal-header"><h5 class="modal-title">Edit Jenis</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                          <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo intval($j['id']); ?>">
                            <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" value="<?php echo htmlspecialchars($j['nama_jenis'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <?php if ($has_setoran): ?><div class="mb-3"><label>Setoran Minimum</label><input name="setoran_min" type="number" step="0.01" class="form-control" value="<?php echo htmlspecialchars($j['setoran_minimum'] ?? 0); ?>"></div><?php endif; ?>
                            <?php if ($has_saldo): ?><div class="mb-3"><label>Saldo Minimum</label><input name="saldo_min" type="number" step="0.01" class="form-control" value="<?php echo htmlspecialchars($j['saldo_minimum'] ?? 0); ?>"></div><?php endif; ?>
                            <?php if ($has_biaya): ?><div class="mb-3"><label>Biaya Administrasi</label><input name="biaya_admin" type="number" step="0.01" class="form-control" value="<?php echo htmlspecialchars($j['biaya_administrasi'] ?? 0); ?>"></div><?php endif; ?>
                            <?php if ($has_status): ?><div class="mb-3"><label>Status</label><select name="status" class="form-control"><option value="1" <?php echo ($j['status'] ?? '0')=='1' ? 'selected' : ''; ?>>Aktif</option><option value="0" <?php echo ($j['status'] ?? '0')=='0' ? 'selected' : ''; ?>>Nonaktif</option></select></div><?php endif; ?>
                          </div>
                          <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Add Modal -->
          <div class="modal fade" id="addJenis" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post">
                <div class="modal-header"><h5 class="modal-title">Tambah Jenis Tabungan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <input type="hidden" name="action" value="add">
                  <div class="mb-3"><label class="form-label">Nama</label><input name="nama" class="form-control" required></div>
                  <?php if ($has_setoran): ?><div class="mb-3"><label>Setoran Minimum</label><input name="setoran_min" type="number" step="0.01" class="form-control"></div><?php endif; ?>
                  <?php if ($has_saldo): ?><div class="mb-3"><label>Saldo Minimum</label><input name="saldo_min" type="number" step="0.01" class="form-control"></div><?php endif; ?>
                  <?php if ($has_biaya): ?><div class="mb-3"><label>Biaya Administrasi</label><input name="biaya_admin" type="number" step="0.01" class="form-control"></div><?php endif; ?>
                  <?php if ($has_status): ?><div class="mb-3"><label>Status</label><select name="status" class="form-control"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div><?php endif; ?>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-dark">Tambah</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button></div>
                </form>
              </div>
            </div>
          </div>

        </main>
      </div>
    </div>

    <script>
      // Prevent double-submit and move modals to body to avoid nesting issues inside <table>
      document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('form').forEach(function(f){
          f.addEventListener('submit', function(){
            var btn = f.querySelector('button[type=submit]'); if (btn) { btn.disabled = true; btn.innerText = 'Memproses...'; }
          });
        });
        // Move modals to document.body so Bootstrap modal styles are consistent
        document.querySelectorAll('.modal').forEach(function(m){ document.body.appendChild(m); });
      });
    </script>
    <?php include "../dashboard/js.php"; ?>
  </body>
</html>