<?php
// Halaman konfirmasi/topup manual oleh admin
// Ensure session is started first
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include "../../koneksi/config.php";

// ambil id dari GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo "<p>Parameter tidak lengkap.</p>";
    exit;
}

// ambil data mulai_nabung
$q = mysqli_query($con, "SELECT * FROM mulai_nabung WHERE id_mulai_nabung = $id");
if (!$q) {
  echo "<p>Gagal mengeksekusi query: " . htmlspecialchars(mysqli_error($con)) . "</p>";
  exit;
}
$row = mysqli_fetch_assoc($q);
if (!$row) {
  echo "<p>Data top-up tidak ditemukan.</p>";
  exit;
}

// Prepare safe display variables to avoid undefined index and deprecated htmlspecialchars(null)
$display_id = htmlspecialchars($row['id_mulai_nabung'] ?? '', ENT_QUOTES, 'UTF-8');
$display_nama = htmlspecialchars($row['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8');
$display_id_tabungan = htmlspecialchars($row['id_tabungan'] ?? '', ENT_QUOTES, 'UTF-8');
$jumlah_topup = floatval($row['jumlah'] ?? 0);
$display_jumlah = htmlspecialchars((string)$jumlah_topup, ENT_QUOTES, 'UTF-8');
$display_tanggal = htmlspecialchars($row['tanggal'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8');

// Ambil saldo pengguna saat ini untuk preview
$saldo_sebelum = 0;
$id_tabungan_raw = $row['id_tabungan'] ?? '';

// Deteksi kolom id_tabungan dengan caching sederhana
static $has_id_tabungan = null;
if ($has_id_tabungan === null) {
    $col_check = mysqli_query($con, "SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
    $has_id_tabungan = ($col_check && mysqli_num_rows($col_check) > 0);
    error_log("DEBUG: pengguna table has id_tabungan column: " . ($has_id_tabungan ? 'YES' : 'NO'));
}

// Cari saldo berdasarkan kolom yang tersedia
if ($has_id_tabungan) {
    $qsaldo = mysqli_query($con, "SELECT saldo FROM pengguna WHERE id_tabungan = '" . mysqli_real_escape_string($con, $id_tabungan_raw) . "' LIMIT 1");
    error_log("DEBUG: Looking up saldo by id_tabungan = '{$id_tabungan_raw}'");
} else {
    // Fallback ke numeric id
    $qsaldo = mysqli_query($con, "SELECT saldo FROM pengguna WHERE id = " . intval($id_tabungan_raw) . " LIMIT 1");
    error_log("DEBUG: Looking up saldo by numeric id = " . intval($id_tabungan_raw));
}

if ($qsaldo && mysqli_num_rows($qsaldo) > 0) {
  $rsaldo = mysqli_fetch_assoc($qsaldo);
  $saldo_sebelum = floatval($rsaldo['saldo'] ?? 0);
  error_log("DEBUG: Saldo sebelum = {$saldo_sebelum}");
} else {
  error_log("DEBUG: WARNING - pengguna tidak ditemukan!");
}
$saldo_sesudah = $saldo_sebelum + $jumlah_topup;

// handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_topup'])) {
    $id_mulai = $id;
    // For security: always use original pending data from DB instead of trusting POST values
    $id_tabungan_raw = $row['id_tabungan'] ?? '';
    $id_tabungan_str = mysqli_real_escape_string($con, $id_tabungan_raw);
    $jumlah = floatval($row['jumlah'] ?? 0);
    $nama_pengguna = mysqli_real_escape_string($con, $row['nama_pengguna'] ?? '');
    $tanggal = $row['tanggal'] ?: date('Y-m-d');
    $user = $_POST['admin_user'] ?: '';

    // Audit: detect attempted changes to fields (log for review)
    $attempted_id_tabungan = $_POST['id_tabungan'] ?? '';
    $attempted_jumlah = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : null;
    if ($attempted_id_tabungan !== '' && $attempted_id_tabungan != $id_tabungan_raw) {
        error_log("SECURITY WARNING: admin attempted to modify id_tabungan for id_mulai={$id_mulai}: attempted={$attempted_id_tabungan} original={$id_tabungan_raw}");
    }
    if ($attempted_jumlah !== null && $attempted_jumlah != $jumlah) {
        error_log("SECURITY WARNING: admin attempted to modify jumlah for id_mulai={$id_mulai}: attempted={$attempted_jumlah} original={$jumlah}");
    }

    error_log("DEBUG: id_mulai=$id_mulai, id_tabungan_raw={$id_tabungan_raw}, jumlah=$jumlah");

    // lakukan transaksi DB: update mulai_nabung status, update pengguna saldo
    mysqli_begin_transaction($con);
    try {
      // 1. Update status mulai_nabung menjadi 'berhasil'
      $stmt_status = mysqli_prepare($con, "UPDATE mulai_nabung SET status = 'berhasil', updated_at = NOW() WHERE id_mulai_nabung = ?");
      if (!$stmt_status) {
        throw new Exception('Prepared statement error (status): ' . mysqli_error($con));
      }
      mysqli_stmt_bind_param($stmt_status, 'i', $id_mulai);
      if (!mysqli_stmt_execute($stmt_status)) {
        throw new Exception('Execute error (status): ' . mysqli_stmt_error($stmt_status));
      }
      $affected_rows_status = mysqli_stmt_affected_rows($stmt_status);
      error_log("DEBUG: Status update affected rows: $affected_rows_status");
      mysqli_stmt_close($stmt_status);

      // CRITICAL FIX: Also update the corresponding transaksi record to mark it as 'approved'
      // When user creates mulai_nabung, a pending transaksi is created in buat_mulai_nabung.php
      // We must update that transaksi record to 'approved' so it appears in Riwayat Transaksi
      try {
          // First resolve numeric user id
          if ($has_id_tabungan) {
              $user_q_tmp = mysqli_query($con, "SELECT id FROM pengguna WHERE id_tabungan = '" . mysqli_real_escape_string($con, $id_tabungan_str) . "' LIMIT 1");
          } else {
              $user_q_tmp = mysqli_query($con, "SELECT id FROM pengguna WHERE id = " . intval($id_tabungan_raw) . " LIMIT 1");
          }
          if ($user_q_tmp && mysqli_num_rows($user_q_tmp) > 0) {
              $user_row_tmp = mysqli_fetch_assoc($user_q_tmp);
              $user_id_tmp = intval($user_row_tmp['id']);
              
              // Now update transaksi record
              $trans_update_stmt = mysqli_prepare($con, "UPDATE transaksi SET status = 'approved' WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND keterangan LIKE ?");
              if ($trans_update_stmt) {
                  $search_pattern = '%mulai_nabung ' . intval($id_mulai) . '%';
                  mysqli_stmt_bind_param($trans_update_stmt, 'is', $user_id_tmp, $search_pattern);
                  if (@mysqli_stmt_execute($trans_update_stmt)) {
                      error_log("DEBUG: Transaksi status update SUCCESS for mulai_nabung={$id_mulai} user={$user_id_tmp}");
                  } else {
                      error_log("DEBUG: Transaksi status update WARNING (non-fatal): " . mysqli_stmt_error($trans_update_stmt));
                  }
                  mysqli_stmt_close($trans_update_stmt);
              }
          }
      } catch (Exception $e) {
          error_log("DEBUG: Exception updating transaksi (non-fatal): " . $e->getMessage());
          // Non-fatal: don't stop the approval if transaksi update fails
      }

      // 2. Update saldo pengguna dengan prepared statement
      error_log("DEBUG: Siap update saldo dengan id_tabungan_raw={$id_tabungan_raw}, jumlah={$jumlah}");
      
            // Use ledger helper to insert a 'masuk' ledger and update saldo atomically.
            include_once __DIR__ . '/../../function/ledger_helpers.php';
            // Resolve numeric user id
            if ($has_id_tabungan) {
              $user_q = mysqli_query($con, "SELECT id FROM pengguna WHERE id_tabungan = '" . mysqli_real_escape_string($con, $id_tabungan_str) . "' LIMIT 1");
            } else {
              $user_q = mysqli_query($con, "SELECT id FROM pengguna WHERE id = " . intval($id_tabungan_raw) . " LIMIT 1");
            }
            if (!$user_q || mysqli_num_rows($user_q) == 0) {
              throw new Exception('Pengguna tidak ditemukan untuk id_tabungan: ' . $id_tabungan_raw);
            }
            $user_row = mysqli_fetch_assoc($user_q);
            $user_id_numeric = intval($user_row['id']);

            // --- Begin: Validate and resolve jenis_tabungan to id_jenis_tabungan ---
            $validated_jenis = 1;
            $jn = $row['jenis_tabungan'] ?? '';
            if (!empty($jn)) {
                if (ctype_digit((string)$jn)) {
                    $id_jenis = intval($jn);
                    $chk = mysqli_query($con, "SELECT id FROM jenis_tabungan WHERE id = " . intval($id_jenis) . " LIMIT 1");
                    if ($chk && mysqli_num_rows($chk) > 0) {
                        $validated_jenis = $id_jenis;
                    }
                } else {
                    $name = mysqli_real_escape_string($con, $jn);
                    $norm = preg_replace('/\\btabungan\\b/i', '', $name);
                    $norm = trim($norm);
                    $n1 = mysqli_real_escape_string($con, $norm);
                    $jr = mysqli_query($con, "SELECT id FROM jenis_tabungan WHERE (nama_jenis = '$name' OR nama_jenis = '$n1' OR nama_jenis LIKE '%$n1%') LIMIT 1");
                    if ($jr && mysqli_num_rows($jr) > 0) {
                        $rrow = mysqli_fetch_assoc($jr);
                        $validated_jenis = intval($rrow['id']);
                    }
                }
            }
            // --- End validate ---

            $keterangan = 'Topup Admin (id_mulai:' . $id_mulai . ')';
            $ok = insert_ledger_masuk($con, $user_id_numeric, $jumlah, $keterangan, $validated_jenis);
            if (!$ok) {
              throw new Exception('Gagal menulis ledger masuk untuk id ' . $user_id_numeric);
            }
            @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [confirm_topup] INSERT_TABUNGAN_MASUK user={$user_id_numeric} jenis={$validated_jenis} amt={$jumlah} mulai_nabung={$id_mulai}\n", FILE_APPEND);

            // Create the 'Mulai Nabung' approval notification via shared helper so UI admin and API share behavior
            try {
                require_once __DIR__ . '/../../../flutter_api/notif_helper.php';
                $created_ts = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
                $nid = create_mulai_nabung_notification($con, $user_id_numeric, $id_mulai, null, $created_ts, 'berhasil');
                if ($nid !== false) {
                    @file_put_contents(__DIR__ . '/../../../flutter_api/api_debug.log', date('c') . " [confirm_topup] NOTIF_CREATED id={$nid} user={$user_id_numeric} mulai_id={$id_mulai} created_ts={$created_ts}\n", FILE_APPEND);
                } else {
                    @file_put_contents(__DIR__ . '/../../../flutter_api/api_debug.log', date('c') . " [confirm_topup] NOTIF_SKIPPED user={$user_id_numeric} mulai_id={$id_mulai} created_ts={$created_ts}\n", FILE_APPEND);
                }
            } catch (Exception $e) {
                @file_put_contents(__DIR__ . '/../../../flutter_api/api_debug.log', date('c') . " [confirm_topup] NOTIF_EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            }

      // 3. Verify saldo was updated
      if ($has_id_tabungan) {
        $verify_q = mysqli_query($con, "SELECT id, saldo FROM pengguna WHERE id_tabungan = '" . $id_tabungan_str . "' LIMIT 1");
      } else {
        $verify_q = mysqli_query($con, "SELECT id, saldo FROM pengguna WHERE id = " . intval($id_tabungan_raw) . " LIMIT 1");
      }
      if ($verify_q && mysqli_num_rows($verify_q) > 0) {
        $verify_row = mysqli_fetch_assoc($verify_q);
        error_log("DEBUG: Saldo SETELAH update: {$verify_row['saldo']} (sebelumnya: {$saldo_sebelum}, penambahan: {$jumlah})");
      }

      mysqli_commit($con);
      error_log("DEBUG: Transaction committed successfully!");
      // Redirect dengan session message untuk ditampilkan di halaman berikutnya
      $_SESSION['success_message'] = "Top-up Rp " . number_format($jumlah, 0, ',', '.') . " untuk " . htmlspecialchars($nama_pengguna) . " telah berhasil ditambahkan ke saldo!";
      header('Location: index.php');
      exit;
    } catch (Exception $e) {
      mysqli_rollback($con);
      error_log("DEBUG: Exception caught - " . $e->getMessage());
      echo "<div class=\"alert alert-danger\">Gagal memproses: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<?php include "../dashboard/head.php"; ?>
<body>
<?php include "../dashboard/icon.php"; ?>
<?php include "../dashboard/header.php"; ?>
<div class="container-fluid">
  <div class="row">
    <?php include "../dashboard/menu.php"; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="card mt-3">
        <div class="card-header" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
          <h5 style="margin: 0; color: #333;">Konfirmasi Top-up (ID: <?php echo $display_id; ?>)</h5>
          <small style="color: #666;">Review saldo sebelum mengkonfirmasi</small>
        </div>
        <div class="card-body">
          <!-- Form Konfirmasi (visible inputs) -->
          <form method="post">
            <div class="alert alert-info">Form tidak dapat diubah — data mengikuti permintaan pengguna.</div>

            <div class="mb-3">
              <label class="form-label">Nama Pengguna</label>
              <input type="text" name="nama_pengguna" class="form-control" value="<?php echo $display_nama; ?>" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">ID Tabungan</label>
              <input type="text" name="id_tabungan" class="form-control" value="<?php echo $display_id_tabungan; ?>" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Jumlah (Rp)</label>
              <input type="text" name="jumlah" class="form-control" value="<?php echo number_format((int)$jumlah_topup, 0, ',', '.'); ?>" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">Tanggal</label>
              <input type="date" name="tanggal" class="form-control" value="<?php echo htmlspecialchars($display_tanggal, ENT_QUOTES, 'UTF-8'); ?>" readonly>
            </div>

            <input type="hidden" name="admin_user" value="<?php echo htmlspecialchars($_SESSION['nama'] ?? 'admin', ENT_QUOTES, 'UTF-8'); ?>">

            <div style="display: flex; gap: 10px; margin-top: 10px;">
              <button type="submit" name="confirm_topup" class="btn btn-success">
                ✓ Konfirmasi & Tambah Saldo
              </button>
              <a href="index.php" class="btn btn-secondary">
                ✕ Batal
              </a>
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
