<div
  class="sidebar border border-right col-md-3 col-lg-2 p-0 bg-body-tertiary">
  <div
    class="offcanvas-lg offcanvas-end bg-body-tertiary"
    tabindex="-1"
    id="sidebarMenu"
    aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header">
      <!-- <h5 class="offcanvas-title" id="sidebarMenuLabel">
                Company name
              </h5> -->
      <button
        type="button"
        class="btn-close"
        data-bs-dismiss="offcanvas"
        data-bs-target="#sidebarMenu"
        aria-label="Close"></button>
    </div>
    <div
      class="offcanvas-body d-md-flex flex-column p-0 pt-lg-3 overflow-y-auto" role="navigation" aria-label="Admin sidebar">
      <?php
      if (session_status() === PHP_SESSION_NONE) session_start();
      // Determine the current page segment to highlight active menu item
      $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
      $request_path = trim($request_path, "/");
      $parts = explode('/', $request_path);
      $current = end($parts);
      // use full request path for more robust matching (handles index.php and deeper paths)
      $current_path = $request_path;

      // Access and feature detection
      $akses = $_SESSION['akses'] ?? '';
      $show_petugas = false; // only show if multi-admin setup detected
      $has_transaksi = false; // only show if transaksi table exists
      // get display name and avatar (fallbacks)
      $displayName = (isset($user) && isset($user['nama'])) ? $user['nama'] : ($_SESSION['nama'] ?? 'Administrator');
      // Temporarily force a known logo from GAS/gas_mobile assets so avatar always loads
      $avatarSrc = '/gas/gas_mobile/assets/logo_gusti.png';
      // If you'd rather use the user's uploaded photo when available, uncomment below and remove the forced path above
      // $avatarSrc = (isset($user) && isset($user['foto']) && $user['foto']) ? $user['foto'] : '/gas/gas_mobile/assets/logo_gusti.png';
      if (isset($con)) {
          try {
              $res = $con->query("SELECT COUNT(*) AS total FROM user WHERE hak_akses='admin'");
              $row = $res ? $res->fetch_assoc() : null;
              if ($row && intval($row['total']) > 1) $show_petugas = true;

              $res2 = $con->query("SHOW TABLES LIKE 'transaksi'");
              if ($res2 && $res2->num_rows > 0) $has_transaksi = true;
          } catch (Throwable $e) {
              // safe defaults: hide optional items on error
          }
      }
      ?>

      <!-- User panel -->
      <div class="sidebar-user d-flex align-items-center p-3">
        <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Avatar" class="rounded-circle me-2" width="44" height="44" />
        <div class="sidebar-user-info">
          <div class="fw-semibold"><?php echo htmlspecialchars($displayName); ?></div>
          <small class="text-muted">Administrator</small>
        </div>
      </div>

      <hr class="my-2" />

      <ul class="nav flex-column">
        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'dashboard') !== false || $current === '') ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" aria-current="page" href="../dashboard/">
            <i class="fe fe-home"></i>
            Dashboard
          </a>
        </li>

        <hr class="my-3" />

        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'masuk') !== false) ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../masuk/">
            <i class="zmdi zmdi-money"></i>
            Tabungan Masuk
          </a>
        </li>
        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'keluar') !== false) ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../keluar/">
            <i class="zmdi zmdi-money-off"></i>
            Pencairan Tabungan
          </a>
        </li>
        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'approval') !== false) ? 'active' : ''; ?>
          <?php
            $approvalSchemaText = 'Approval Pinjaman';
            if (isset($con)) {
                if (function_exists('approval_get_schema')) {
                    try {
                        $schemaRes = approval_get_schema($con);
                        if (isset($schemaRes['schema']) && isset($schemaRes['schema']['table']) && $schemaRes['schema']['table'] === 'pinjaman') {
                            $approvalSchemaText = 'Approval Pinjaman';
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
            }
          ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../approval/">
            <i class="fe fe-check-circle"></i>
            <?php echo htmlspecialchars($approvalSchemaText, ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </li>
        <li class="nav-item">
          <?php $cls2 = (strpos($current_path, 'approval_users') !== false) ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls2; ?>" href="../approval/approval_users.php">
            <i class="fe fe-user"></i>
            Semua Pengguna
          </a>
        </li>

        <hr class="my-3" />

        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'rekap') !== false) ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../rekap/">
            <i class="fe fe-user-check"></i>
            Pengajuan Aktivasi
          </a>
        </li>

        <li class="nav-item">
          <?php $cls = (strpos($current_path, 'transfer') !== false) ? 'active' : ''; ?>
          <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../transfer/">
            <i class="zmdi zmdi-money-box"></i>
            Rekap Transfer
          </a>
        </li>

        <hr class="my-3" />

        <!-- Pengaturan Tabungan removed: feature deprecated and page deleted -->

        <?php if ($akses === 'admin'): ?>
        <ul class="nav flex-column mb-auto">
          <li class="nav-item">
            <?php $cls = (strpos($current_path, 'sekolah') !== false) ? 'active' : ''; ?>
            <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../pengaturan/sekolah">
              <i class="fe fe-home"></i>
              Pengaturan Koperasi
            </a>
          </li>
        </ul>
        <?php endif; ?>

        <!-- WhatsApp Blast removed — archived per request -->

        <hr class="my-3" />

        <!-- Histori Login removed — archived per request -->

        <!-- Histori Transaksi removed — archived per request -->

        <hr class="my-3" />

        <?php if ($show_petugas): ?>
        <ul class="nav flex-column mb-auto">
          <li class="nav-item">
            <?php $cls = (strpos($current_path, 'petugas') !== false) ? 'active' : ''; ?>
            <a class="nav-link d-flex align-items-center gap-2 <?php echo $cls; ?>" href="../user/petugas">
              <i class="fe fe-users"></i>
              Petugas
            </a>
          </li>
          <!-- Profil, Pengaturan, and Keluar moved to header dropdown -->
        </ul>
        <?php endif; ?>
    </div>
  </div>
</div>