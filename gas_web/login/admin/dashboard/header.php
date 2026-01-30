    <nav class="navbar navbar-expand-lg navbar-dark border-bottom shadow-sm" style="background:var(--header-bg,#2b2740) !important; position:fixed; top:0; left:0; right:0; z-index:1050;">
      <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-start py-1" href="../dashboard/" style="padding-top:4px;">
          <img src="../../../assets/brand/logo.png" alt="logo" style="height:56px; width:auto; display:block; background:transparent;">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTop" aria-controls="navbarTop" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarTop">
          <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                <span class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center" style="width:32px;height:32px;font-weight:600;">
                  <?php echo strtoupper(substr($nama ?? 'A', 0, 1)); ?>
                </span>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($nama ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><a class="dropdown-item" href="../user/"><i class="fe fe-user me-2"></i>Profil</a></li>
                <li><a class="dropdown-item" href="../pengaturan/"><i class="fe fe-settings me-2"></i>Pengaturan</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../dashboard/logout"><i class="fe fe-log-out me-2"></i>Keluar</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </nav>