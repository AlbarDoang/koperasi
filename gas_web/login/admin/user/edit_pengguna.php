<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Only admin allowed
if (!isset($_SESSION['akses']) || $_SESSION['akses'] !== 'admin') { http_response_code(403); echo "<p>Akses ditolak.</p>"; exit; }
include "../dashboard/head.php";
include "../dashboard/icon.php";
include "../dashboard/header.php";
require_once __DIR__ . '/../../../koneksi/config.php';

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') {
    echo "<p>Parameter id tidak lengkap.</p>";
    exit;
}

// Load current data (needed so we don't accidentally overwrite status)
$sql = "SELECT * FROM pengguna WHERE id = ? LIMIT 1";
$u = null;
if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $u = $res->fetch_assoc();
    $stmt->close();
}

if (!$u) {
    echo "<p>Pengguna tidak ditemukan.</p>";
    exit;
}

// Handle POST update (status_akun is NOT editable here — we preserve existing status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $nama = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : '';
    $no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';
    $alamat = isset($_POST['alamat_domisili']) ? trim($_POST['alamat_domisili']) : '';
    $tgl = isset($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : null;

    // Basic validation
    $errors = [];
    if ($nama === '') $errors[] = 'Nama wajib diisi.';
    if ($no_hp === '') $errors[] = 'Nomor HP wajib diisi.';

    if (empty($errors)) {
        // Do not update status_akun here — keep whatever is in the DB
        $sql = "UPDATE pengguna SET nama_lengkap = ?, no_hp = ?, alamat_domisili = ?, tanggal_lahir = ? WHERE id = ?";
        if ($stmt = $con->prepare($sql)) {
            $stmt->bind_param('sssss', $nama, $no_hp, $alamat, $tgl, $id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Data pengguna berhasil diperbarui.';
                header('Location: ../approval/approval_users.php');
                exit;
            } else {
                $errors[] = 'Gagal menyimpan: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Prepare gagal: ' . $con->error;
        }
    }
}

// Load current data
$sql = "SELECT * FROM pengguna WHERE id = ? LIMIT 1";
$u = null;
if ($stmt = $con->prepare($sql)) {
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $u = $res->fetch_assoc();
    $stmt->close();
}

if (!$u) {
    echo "<p>Pengguna tidak ditemukan.</p>";
    exit;
}

?>
<div class="container-fluid">
  <div class="row">
    <?php include "../dashboard/menu.php"; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="pt-3 pb-2 mb-3 border-bottom">
        <h5>Edit Pengguna</h5>
      </div>

      <div class="card">
        <div class="card-body">
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $err) echo '<div>'.htmlspecialchars($err).'</div>'; ?>
            </div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label">Nama Lengkap</label>
              <input type="text" name="nama_lengkap" class="form-control" value="<?php echo htmlspecialchars($u['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Nomor HP</label>
              <input type="text" name="no_hp" class="form-control" value="<?php echo htmlspecialchars($u['no_hp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Alamat Domisili</label>
              <input type="text" name="alamat_domisili" class="form-control" value="<?php echo htmlspecialchars($u['alamat_domisili'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Tanggal Lahir</label>
              <input type="date" name="tanggal_lahir" class="form-control" value="<?php echo htmlspecialchars($u['tanggal_lahir'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Status Akun</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars(ucfirst($u['status_akun'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
              <div class="form-text">Status akun hanya ditampilkan di sini dan tidak dapat diubah oleh admin melalui form ini.</div>
            </div>

            <div style="display:flex; gap:10px; margin-top:10px;">
              <button type="submit" name="save_user" class="btn btn-success">Simpan</button>
              <a href="../approval/approval_users.php" class="btn btn-secondary">Batal</a>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>

<?php include "../dashboard/js.php"; ?>
</body>
</html>