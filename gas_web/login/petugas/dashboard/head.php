  <head>
    <?php
    // MIDDLEWARE: Hanya Petugas yang bisa akses
    require_once __DIR__ . '/../../middleware/PetugasMiddleware.php';
    PetugasMiddleware::handle();

    // Load user info
    $user = Auth::user();
    $id   = $user['id'];
    $foto = $user['foto'];
    $nama = $user['nama'];

    // Koneksi (jika belum ada)
    if (!isset($con)) {
      include "../../koneksi/config.php";
    }

    // Load pengaturan
    include_once "../../koneksi/pengaturan.php";

    ?>
    <script src="../../../assets/js/color-modes.js"></script>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors" />
    <meta name="generator" content="Hugo 0.112.5" />
    <title>Petugas GAS - Sistem Koperasi</title>

    <!-- FAVICON -->
    <link rel="shortcut icon" type="image/png" href="../../../assets/brand/logo.png" />
    <link rel="apple-touch-icon" href="../../../assets/brand/logo.png" />

    <link rel="canonical" href="https://getbootstrap.com/docs/5.3/examples/dashboard/" />

    <link href="../../../assets/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
      .bd-placeholder-img {
        font-size: 1.125rem;
        text-anchor: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
      }

      @media (min-width: 768px) {
        .bd-placeholder-img-lg {
          font-size: 3.5rem;
        }
      }

      .b-example-divider {
        width: 100%;
        height: 3rem;
        background-color: rgba(0, 0, 0, 0.1);
        border: solid rgba(0, 0, 0, 0.15);
        border-width: 1px 0;
        box-shadow: inset 0 0.5em 1.5em rgba(0, 0, 0, 0.1), inset 0 0.125em 0.5em rgba(0, 0, 0, 0.15);
      }

      .b-example-vr {
        flex-shrink: 0;
        width: 1.5rem;
        height: 100vh;
      }

      .bi {
        vertical-align: -0.125em;
        fill: currentColor;
      }

      .nav-scroller {
        position: relative;
        z-index: 2;
        height: 2.75rem;
        overflow-y: hidden;
      }

      .nav-scroller .nav {
        display: flex;
        flex-wrap: nowrap;
        padding-bottom: 1rem;
        margin-top: -1px;
        overflow-x: auto;
        text-align: center;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
      }

      .btn-bd-primary {
        --bd-violet-bg: #FF6B00;
        --bd-violet-rgb: 255, 107, 0;
        --bs-btn-font-weight: 600;
        --bs-btn-color: var(--bs-white);
        --bs-btn-bg: var(--bd-violet-bg);
        --bs-btn-border-color: var(--bd-violet-bg);
        --bs-btn-hover-color: var(--bs-white);
        --bs-btn-hover-bg: #dd4200;
        --bs-btn-hover-border-color: #dd4200;
        --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
        --bs-btn-active-color: var(--bs-btn-hover-color);
        --bs-btn-active-bg: #bb3800;
        --bs-btn-active-border-color: #bb3800;
      }

      .bd-mode-toggle {
        z-index: 1500;
      }

      :root {
        --gas-primary: #FF6B00;
        --gas-secondary: #10b981;
        --gas-accent: #FF8533;
        --gas-danger: #ef4444;
        --gas-dark: #1f2937;
        --gas-light: #f3f4f6;
        --sidebar-width: 260px;
      }

      body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #ffffff;
        min-height: 100vh;
        padding-top: 68px !important;
        margin: 0 !important;
      }

      .sidebar {
        background: #ffffff;
        border-right: 1px solid #e5e7eb;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.05);
        width: var(--sidebar-width);
        transition: width 0.3s ease;
      }

      .sidebar .nav-link {
        color: #94a3b8 !important;
        padding: 10px 16px;
        margin: 6px 12px;
        border-radius: 10px;
        transition: background 0.18s ease, color 0.18s ease;
        font-weight: 500;
        font-size: 0.95rem;
        line-height: 1.2;
      }

      .sidebar .nav-link i,
      .sidebar .nav-link .fe,
      .sidebar .nav-link .zmdi {
        font-size: 1.05rem;
        width: 20px;
        text-align: center;
        opacity: 0.95;
        margin-right: 8px;
        color: inherit;
        display: inline-block;
        visibility: visible;
        transition: color 0.12s ease, opacity 0.12s ease, transform 0.12s ease;
      }

      .sidebar .nav-link:hover {
        background: rgba(255, 107, 0, 0.08);
        color: #FF6B00;
        transform: none;
      }

      .sidebar .nav-link:active i,
      .sidebar .nav-link:focus i,
      .sidebar .nav-link:active .fe,
      .sidebar .nav-link:focus .fe,
      .sidebar .nav-link:active .zmdi,
      .sidebar .nav-link:focus .zmdi {
        opacity: 1 !important;
        visibility: visible !important;
        color: inherit !important;
        transform: none !important;
      }

      .sidebar .nav-link.active {
        background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(255, 107, 0, 0.35) !important;
        transform: none !important;
        font-weight: 600;
      }

      .sidebar .nav-link.active i,
      .sidebar .nav-link.active .fe,
      .sidebar .nav-link.active .zmdi,
      .sidebar .nav-link.active i:before,
      .sidebar .nav-link.active .fe:before,
      .sidebar .nav-link.active .zmdi:before,
      .sidebar .nav-link.active::before {
        color: #ffffff !important;
        opacity: 1 !important;
        visibility: visible !important;
        filter: none !important;
        -webkit-text-fill-color: #ffffff !important;
        -webkit-text-stroke: 0 !important;
      }

      .sidebar .nav-link.active svg {
        fill: #ffffff !important;
        stroke: #ffffff !important;
        opacity: 1 !important;
        visibility: visible !important;
      }

      .navbar {
        background: var(--header-bg, #2b2740) !important;
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.12);
        backdrop-filter: blur(6px);
      }

      /* Profile Dropdown Styling */
      .navbar .dropdown-toggle {
        color: #ffffff !important;
        cursor: pointer;
      }

      .navbar .dropdown-toggle:hover {
        opacity: 0.85;
      }

      .navbar .dropdown-menu {
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        border: 1px solid #e5e7eb;
        min-width: 200px;
        z-index: 1060;
        display: none;
      }

      .navbar .dropdown-menu.show {
        display: block;
      }

      .navbar .dropdown-item {
        padding: 10px 20px !important;
        transition: background 0.2s ease, color 0.2s ease;
        display: block !important;
        width: 100% !important;
        clear: both !important;
        font-weight: 400 !important;
        color: #212529 !important;
        text-align: inherit !important;
        white-space: nowrap !important;
        background-color: transparent !important;
        border: 0 !important;
        text-decoration: none !important;
        visibility: visible !important;
        opacity: 1 !important;
      }

      .navbar .dropdown-item:first-child {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
      }

      .navbar .dropdown-item:hover {
        background: rgba(255, 107, 0, 0.08);
        color: #FF6B00;
      }

      .navbar .dropdown-item i {
        margin-right: 8px;
      }

      .navbar-brand img {
        background: transparent !important;
        padding: 0 !important;
        display: block !important;
        height: 56px !important;
        width: auto !important;
      }

      .navbar-brand,
      .navbar-brand img {
        transition: none !important;
        outline: none !important;
        box-shadow: none !important;
      }

      .navbar-brand:focus,
      .navbar-brand:active {
        outline: none !important;
        box-shadow: none !important;
      }

      .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
      }

      .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
      }

      .card-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-bottom: 2px solid #e5e7eb;
        font-weight: 600;
        padding: 16px 24px;
      }

      .btn-primary {
        background: linear-gradient(135deg, var(--gas-primary) 0%, var(--gas-accent) 100%);
        border: none;
        border-radius: 10px;
        padding: 10px 24px;
        font-weight: 600;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
      }

      .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 107, 0, 0.4);
      }

      .btn-success {
        background: linear-gradient(135deg, var(--gas-secondary) 0%, #059669 100%);
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
      }

      .btn-danger {
        background: linear-gradient(135deg, var(--gas-danger) 0%, #dc2626 100%);
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
      }

      .badge {
        padding: 6px 12px;
        font-weight: 600;
        border-radius: 8px;
      }

      .table {
        border-radius: 12px;
        overflow: hidden;
      }

      .table thead th {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border: none;
        padding: 16px;
      }

      .table tbody tr {
        transition: background 0.2s ease, transform 0.2s ease;
      }

      .table tbody tr:hover {
        background: #f8fafc;
        transform: scale(1.01);
      }

      .form-control,
      .form-select {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        padding: 10px 16px;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
      }

      .form-control:focus,
      .form-select:focus {
        border-color: var(--gas-primary);
        box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.15);
      }

      .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      }

      .modal-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 16px 16px 0 0;
        border-bottom: 2px solid #e5e7eb;
      }

      .stat-card {
        background: #ffffff;
        color: #1f2937;
        border-radius: 16px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        border: 1px solid #e5e7eb;
      }

      .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: pulse 3s ease-in-out infinite;
      }

      @keyframes pulse {

        0%,
        100% {
          transform: scale(1);
          opacity: 0.5;
        }

        50% {
          transform: scale(1.1);
          opacity: 0.8;
        }
      }

      .stat-card h3 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
      }

      .stat-card p {
        opacity: 0.9;
        margin: 8px 0 0 0;
        font-size: 0.9rem;
      }

      .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 107, 0, 0.2);
        border-top-color: #FF6B00;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }

      /* Smooth transitions removed from global wildcard to prevent glitches */

      ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
      }

      ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
      }

      ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
        border-radius: 10px;
      }

      ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #dd4200 0%, #FF6B00 100%);
      }
    </style>

    <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet" />

    <link href="/gas/gas_web/login/admin/dashboard/dashboard.css" rel="stylesheet" />
    <link href="/gas/gas_web/login/petugas/dashboard/professional-ui.css" rel="stylesheet" />
    <link href="../../../assets/css/icons.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/048d18a465.js" crossorigin="anonymous"></script>

    <!-- DATA TABLE CSS -->
    <link href="../../../assets/plugins/datatable/css/dataTables.bootstrap5.css" rel="stylesheet" />
    <link href="../../../assets/plugins/datatable/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="../../../assets/plugins/datatable/responsive.bootstrap5.css" rel="stylesheet" />

    <!-- SELECT2 CSS -->
    <link href="../../../assets/plugins/select2/select2.min.css" rel="stylesheet" />

    <!-- INTERNAL Notifications  Css -->
    <link href="../../../assets/plugins/notify/css/jquery.growl.css" rel="stylesheet" />
    <link href="../../../assets/plugins/notify/css/notifIt.css" rel="stylesheet" />

    <!-- WYSIWYG EDITOR CSS -->
    <link href="../../../assets/plugins/wysiwyag/richtext.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../../assets/plugins/summernote/summernote-bs4.css">
    <link href="../../../assets/plugins/quill/quill.snow.css" rel="stylesheet">
    <link href="../../../assets/plugins/quill/quill.bubble.css" rel="stylesheet">

    <script>
      // Ensure only one sidebar item is active — fixes cases where server-side detection misses
      document.addEventListener('DOMContentLoaded', function() {
        try {
          var links = Array.from(document.querySelectorAll('.sidebar .nav-link'));
          if (!links.length) return;
          var currentPath = window.location.pathname.replace(/\/+$/, '');
          var best = {
            score: -1,
            el: null
          };
          links.forEach(function(a) {
            var href = a.getAttribute('href') || '';
            var linkPath = href;
            try {
              linkPath = new URL(href, window.location.href).pathname.replace(/\/+$/, '');
            } catch (e) {
              linkPath = href.replace(/\/+$/, '');
            }
            if (!linkPath) return;
            if (currentPath === linkPath) {
              best = {
                score: linkPath.length,
                el: a
              };
              return;
            }
            if (currentPath.indexOf(linkPath) === 0) {
              var score = linkPath.length;
              if (score > best.score) best = {
                score: score,
                el: a
              };
            }
          });
          links.forEach(function(a) {
            a.classList.remove('active');
          });
          if (best.el) best.el.classList.add('active');
        } catch (err) {
          console && console.debug && console.debug('sidebar active script error', err);
        }
      });
    </script>
  </head>