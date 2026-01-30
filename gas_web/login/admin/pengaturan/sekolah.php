<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    
    <?php include "../dashboard/icon.php"; ?>
    
    <?php include "../dashboard/header.php"; ?>

    <?php       
    //koneksi
    include "../../koneksi/config.php";
    //fungsi tanggal
    include "../../koneksi/fungsi_indotgl.php";
    //fungsi tanggal
    include "../../koneksi/fungsi_waktu.php";

    // Only allow admin
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['akses']) || $_SESSION['akses'] !== 'admin') {
        http_response_code(403);
        echo "<div class='container mt-4'><div class='alert alert-danger'>Akses ditolak.</div></div>";
        exit;
    }

    $date        = date('Y-m-d');

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Pengaturan Koperasi</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row">
                <div class="col-lg-12 col-md-12">

                    <?php
                    // Create table if not exists
                    $createSQL = "CREATE TABLE IF NOT EXISTS pengaturan_koperasi (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        nama VARCHAR(255) DEFAULT '',
                        logo VARCHAR(255) DEFAULT '',
                        alamat TEXT,
                        email VARCHAR(255) DEFAULT '',
                        telepon VARCHAR(100) DEFAULT '',
                        penanggung VARCHAR(255) DEFAULT '',

                        -- membership rules
                        require_admin_verification TINYINT(1) DEFAULT 1,
                        prevent_pending_login TINYINT(1) DEFAULT 1,

                        -- operational hours/days
                        operational_days VARCHAR(255) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
                        operational_start TIME DEFAULT NULL,
                        operational_end TIME DEFAULT NULL,

                        -- notification

                        template_new_user TEXT,
                        template_withdrawal_pending TEXT,
                        template_withdrawal_approved TEXT,

                        -- branding
                        primary_color VARCHAR(7) DEFAULT '#0d6efd',
                        footer_text TEXT,

                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $con->query($createSQL);

                    // Migrate existing pengaturan (if no koperasi row exists yet)
                    $rcheck = $con->query("SELECT COUNT(*) as cnt FROM pengaturan_koperasi");
                    $hasKop = false;
                    if ($rcheck) {
                        $c = $rcheck->fetch_assoc(); if (intval($c['cnt']) > 0) $hasKop = true;
                    }
                    if (!$hasKop) {
                        $rold = $con->query("SELECT * FROM pengaturan LIMIT 1");
                        if ($rold && $rold->num_rows > 0) {
                            $old = $rold->fetch_assoc();
                            $ins = $con->prepare("INSERT INTO pengaturan_koperasi (nama, logo, alamat, email, telepon, penanggung, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                            if ($ins) {
                                $ins->bind_param('ssssss', $old['nama'], $old['logo'], $old['alamat'], $old['email'], $old['no_telepon'], $old['penaggung']);
                                $ins->execute(); $ins->close();
                            }
                        }
                    }

                    // Load current koperasi settings
                    $row = [
                        'id' => 0,
                        'nama' => '', 'logo' => '', 'alamat' => '', 'email' => 'koperasigas@gmail.com', 'telepon' => '', 'penanggung' => '',
                        'require_admin_verification' => 1, 'prevent_pending_login' => 1,
                        'operational_days' => 'Mon,Tue,Wed,Thu,Fri', 'operational_start' => '08:00', 'operational_end' => '16:00'
                    ];
                    $q = $con->query("SELECT * FROM pengaturan_koperasi LIMIT 1");
                    if ($q && $q->num_rows > 0) $row = $q->fetch_assoc();

                    // flash message captured from session (set after save)
                    $flash_to_show = null;
                    if (isset($_SESSION['flash'])) { $flash_to_show = $_SESSION['flash']; unset($_SESSION['flash']); }
                    ?>


                    <?php
                    // Proses Simpan Pengaturan Koperasi (handler dipindahkan ke atas agar pesan terlihat jelas)
                    if(isset($_POST['save_koperasi'])){
                        $id = isset($_POST['id_pengaturan_koperasi']) ? intval($_POST['id_pengaturan_koperasi']) : 0;
                        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
                        $penanggung = isset($_POST['penanggung']) ? trim($_POST['penanggung']) : '';
                        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
                        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                        $telepon = isset($_POST['telepon']) ? trim($_POST['telepon']) : '';

                        $require_admin_verification = isset($_POST['require_admin_verification']) && $_POST['require_admin_verification'] === '1' ? 1 : 0;
                        $prevent_pending_login = isset($_POST['prevent_pending_login']) && $_POST['prevent_pending_login'] === '1' ? 1 : 0;

                        $operational_days = isset($_POST['operational_days']) && is_array($_POST['operational_days']) ? implode(',', $_POST['operational_days']) : 'Mon,Tue,Wed,Thu,Fri';
            $operational_start = isset($_POST['operational_start']) && $_POST['operational_start'] !== '' ? $_POST['operational_start'] : '08:00';
            $operational_end = isset($_POST['operational_end']) && $_POST['operational_end'] !== '' ? $_POST['operational_end'] : '16:00';


                        if ($nama === '') $errors[] = 'Nama koperasi wajib diisi.';

                        // Handle logo upload
                        $existingLogo = isset($_POST['existing_logo']) ? trim($_POST['existing_logo']) : '';
                        $newLogo = $existingLogo;
                        if (isset($_FILES['logo_file']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
                            $file = $_FILES['logo_file'];
                            $allowed = ['jpg','jpeg','png'];
                            $parts = explode('.', $file['name']); $ext = strtolower(end($parts));
                            if (!in_array($ext, $allowed)) $errors[] = 'Format logo harus JPG atau PNG.';
                            if ($file['size'] > 2097152) $errors[] = 'Ukuran file maksimal 2MB.';
                            if (empty($errors)) {
                                $newLogo = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                                $dst = __DIR__ . '/../../../assets/images/' . $newLogo;
                                if (!move_uploaded_file($file['tmp_name'], $dst)) {
                                    $errors[] = 'Gagal mengunggah file logo.';
                                }
                            }
                        }

                        if (empty($errors)) {
                            if ($id > 0) {
                                $upd = $con->prepare("UPDATE pengaturan_koperasi SET nama = ?, logo = ?, alamat = ?, email = ?, telepon = ?, penanggung = ?, require_admin_verification = ?, prevent_pending_login = ?, operational_days = ?, operational_start = ?, operational_end = ?, updated_at = NOW() WHERE id = ?");
                                if ($upd) {
                                    $upd->bind_param('ssssssiisssi', $nama, $newLogo, $alamat, $email, $telepon, $penanggung, $require_admin_verification, $prevent_pending_login, $operational_days, $operational_start, $operational_end, $id);
                                    if ($upd->execute()) {
                                        $_SESSION['flash'] = ['type'=>'success','message'=>'Pengaturan berhasil diperbarui!'];
                                        echo '<script>setTimeout(function(){ window.location.replace("../pengaturan/sekolah"); }, 300);</script>';
                                        $upd->close();
                                    } else { $errors[] = 'Gagal menyimpan: ' . htmlspecialchars($upd->error); }
                                } else {
                                    $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                                }
                            } else {
                                $ins = $con->prepare("INSERT INTO pengaturan_koperasi (nama, logo, alamat, email, telepon, penanggung, require_admin_verification, prevent_pending_login, operational_days, operational_start, operational_end, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                if ($ins) {
                                    $ins->bind_param('ssssssiisss', $nama, $newLogo, $alamat, $email, $telepon, $penanggung, $require_admin_verification, $prevent_pending_login, $operational_days, $operational_start, $operational_end);
                                    if ($ins->execute()) {
                                        $_SESSION['flash'] = ['type'=>'success','message'=>'Pengaturan berhasil dibuat!'];
                                        echo '<script>setTimeout(function(){ window.location.replace("../pengaturan/sekolah"); }, 300);</script>';
                                        $ins->close();
                                    } else { $errors[] = 'Gagal menyimpan: ' . htmlspecialchars($ins->error); }
                                } else {
                                    $errors[] = 'Gagal mempersiapkan pernyataan: ' . htmlspecialchars($con->error);
                                }
                            }
                        }

                        if (!empty($errors)) {
                            echo '<div class="container mt-3">';
                            foreach($errors as $e) echo '<div class="alert alert-danger">'.htmlspecialchars($e).'</div>';
                            echo '</div>';
                        }
                    }
                    ?>

                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" class="card pengaturan-koperasi">
                        <input type="hidden" name="id_pengaturan_koperasi" class="form-control" value="<?php echo intval($row['id']); ?>" readonly>
                    <div class="card-body" >
                        <div class="form-group" align=center>
                        <?php if (!empty($row['logo'])): ?>
                          <img src="../../../assets/images/<?php echo htmlspecialchars($row['logo'] ?? ''); ?>" height="100" alt="Logo">
                        <?php endif; ?> 
                        </div>
                        <input type="hidden" name="existing_logo" class="form-control" value="<?php echo htmlspecialchars($row['logo'] ?? ''); ?>" readonly>
                    <br>
                        <div class="row align-items-end g-3">
                          <div class="col-md-8">
                            <div class="form-floating">
                              <input type="text" id="nama" name="nama" class="form-control form-control-lg" placeholder="Nama Koperasi" value="<?php echo htmlspecialchars($row['nama'] ?? ''); ?>" required maxlength="255">
                              <label for="nama">Nama Koperasi</label>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="form-floating">
                              <input type="text" id="penanggung" name="penanggung" class="form-control form-control-lg" placeholder="Penanggung Jawab" value="<?php echo htmlspecialchars($row['penanggung'] ?? ''); ?>" maxlength="255">
                              <label for="penanggung">Penanggung Jawab</label>
                            </div>
                          </div>
                        </div>
                    <br>
                        <div class="form-group">
                        <label class="form-label"> Alamat</label>
                        <textarea name="alamat" class="form-control"><?php echo htmlspecialchars($row['alamat'] ?? ''); ?></textarea>
                        </div>
                    <br>
                        <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"> Email Kontak</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($row['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"> No Telepon</label>
                            <input type="text" name="telepon" class="form-control" value="<?php echo htmlspecialchars($row['telepon'] ?? ''); ?>">
                        </div>
                        </div>
                    <br>
                        <div class="form-group">
                            <label class="form-label"> Logo Koperasi</label>
                            <input type="file" name="logo_file" class="form-control">
                        </div>
                        <small><font color="red">* Maks 2MB. Format JPG/PNG.</font></small>
                    <br>

                        <hr>
                        <h6>Aturan Keanggotaan</h6>
                        <div class="form-check mb-2">
                          <input class="form-check-input" type="checkbox" name="require_admin_verification" id="require_admin_verification" value="1" <?php echo (intval($row['require_admin_verification']) ? 'checked' : ''); ?>>
                          <label class="form-check-label" for="require_admin_verification">Wajib verifikasi admin untuk mengaktifkan akun</label>
                        </div>
                        <div class="form-check mb-2">
                          <input class="form-check-input" type="checkbox" name="prevent_pending_login" id="prevent_pending_login" value="1" <?php echo (intval($row['prevent_pending_login']) ? 'checked' : ''); ?>>
                          <label class="form-check-label" for="prevent_pending_login">Akun dengan status PENDING tidak boleh login</label>
                        </div>

                        <hr>
                        <h6>Aturan Operasional</h6>
                        <div class="row mb-2">
                          <div class="col-md-6">
                            <label class="form-label">Hari Operasional</label>
                            <?php $days = explode(',', $row['operational_days'] ?? 'Mon,Tue,Wed,Thu,Fri'); $allDays = ['Mon'=>'Senin','Tue'=>'Selasa','Wed'=>'Rabu','Thu'=>'Kamis','Fri'=>'Jumat','Sat'=>'Sabtu','Sun'=>'Minggu']; ?>
                            <div class="d-flex flex-wrap">
                            <?php foreach($allDays as $k=>$label): ?>
                              <div class="form-check me-3"><input class="form-check-input" type="checkbox" name="operational_days[]" value="<?php echo $k; ?>" <?php echo in_array($k,$days) ? 'checked' : ''; ?>> <label class="form-check-label"><?php echo $label; ?></label></div>
                            <?php endforeach; ?>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <label class="form-label">Jam Buka</label>
                            <input type="time" name="operational_start" class="form-control" value="<?php echo htmlspecialchars($row['operational_start'] ?? '08:00'); ?>">
                          </div>
                          <div class="col-md-3">
                            <label class="form-label">Jam Tutup</label>
                            <input type="time" name="operational_end" class="form-control" value="<?php echo htmlspecialchars($row['operational_end'] ?? '16:00'); ?>">
                          </div>
                        </div>






                    <div class="form-footer" align="center">
                        <input type="submit" name="save_koperasi" value="Simpan Pengaturan" class="btn btn-dark">
                   </div>
                    <br>
                    </form>

                </div>
            </div>
            <!-- End Row -->


        </main>


      </div>
    </div>

    <?php include "../dashboard/js.php"; ?>
    
        
    <!-- INTERNAL Notifications js -->
    <script src="../../../assets/plugins/notify/js/rainbow.js"></script>
    <!--script src="public/assets/plugins/notify/js/sample.js"></script-->
    <script src="../../../assets/plugins/notify/js/jquery.growl.js"></script>
    <script src="../../../assets/plugins/notify/js/notifIt.js"></script>

    <!-- FILE UPLOADES JS -->
    <script src="../../../assets/plugins/fileuploads/js/fileupload.js"></script>
    <script src="../../../assets/plugins/fileuploads/js/file-upload.js"></script>

    <!-- INTERNAL Bootstrap-Datepicker js-->
    <script src="../../../assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- INTERNAL File-Uploads Js-->
    <script src="../../../assets/plugins/fancyuploder/jquery.ui.widget.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.iframe-transport.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fancy-fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/fancy-uploader.js"></script>

    <!-- SELECT2 JS -->
    <!-- Minimal JS: Popper, Bootstrap and Notifications/file upload -->
    <script src="../../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../../assets/plugins/notify/js/jquery.growl.js"></script>
    <script src="../../../assets/plugins/notify/js/notifIt.js"></script>
    <script src="../../../assets/plugins/fileuploads/js/fileupload.js"></script>
    <script src="../../../assets/plugins/fileuploads/js/file-upload.js"></script>


    <?php if (!empty($flash_to_show)): $ft = $flash_to_show; ?>
    <script>
    $(function(){
        $.growl.<?= ($ft['type']=='success' ? 'notice' : 'error') ?>({ title: "<?= addslashes(ucfirst($ft['type'])) ?>", message: "<?= addslashes($ft['message']) ?>" });
    });
    </script>
    <?php endif; ?>
    <script>
    function hanyaAngka(evt) {
    var charCode = (evt.which) ? evt.which : event.keyCode
    if (charCode > 31 && (charCode < 48 || charCode > 57))

        return false;
    return true;
    }
    </script>


  </body>
</html>
