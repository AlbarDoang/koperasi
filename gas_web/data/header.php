
<?php
// Dynamic base path detection untuk portabilitas
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_url = $protocol . '://' . $host . $base_path;
?>

    <header data-bs-theme="dark">
        <nav class="navbar navbar-expand-md navbar-dark fixed-top navbar-custom">
            <div class="container-fluid">
            <a class="navbar-brand logo-brand" href="<?php echo $base_url; ?>/index.php">
                <img src="<?php echo $base_path; ?>/assets/brand/logo.png" class="navbar-logo-img">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav mx-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>/index.php">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo (basename($_SERVER['PHP_SELF']) == 'profil.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>/profil.php">Profil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-custom <?php echo (basename($_SERVER['PHP_SELF']) == 'info.php') ? 'active' : ''; ?>" href="<?php echo $base_url; ?>/info.php">Info</a>
                </li>
                </ul>
                
                <!-- TOMBOL UNDUH & LOGIN - Dynamic Path -->
                <?php if(!isset($_SESSION['akses'])): ?>
                <div class="d-flex gap-2 navbar-buttons">
                    <a href="#" class="btn btn-unduh">
                        UNDUH
                    </a>
                    <a href="<?php echo $base_url; ?>/login/" class="btn btn-login-custom">
                        LOGIN
                    </a>
                </div>
                <?php else: ?>
                <div class="d-flex gap-2 navbar-buttons">
                    <a href="#" class="btn btn-unduh">
                        UNDUH
                    </a>
                    <a href="<?php echo $base_url; ?>/logout.php" class="btn btn-login-custom">
                        LOGOUT
                    </a>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </nav>
    </header>