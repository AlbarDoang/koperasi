<head>
  <?php
  // koneksi ke database dan hak akses
  session_start();

  //koneksi
  include "login/koneksi/config.php";
  //fungsi tanggal
  include "login/koneksi/fungsi_indotgl.php";
  //fungsi tanggal
  include "login/koneksi/fungsi_waktu.php";
  //fungsi timestamp
  include "login/koneksi/time_stamp.php";

  include 'login/koneksi/pengaturan.php';

  ?>
  <script src="assets/js/color-modes.js"></script>

  <!-- FAVICON -->
  <link rel="shortcut icon" type="image/png" href="assets/brand/logo.png" />

  <!-- [INFO] Meta tag standar HTML - tidak diubah karena tidak berisi teks siswa/sekolah -->
  <meta charset="utf-8">
  <!-- [INFO] Meta viewport untuk responsive design - tidak diubah -->
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- [INFO] Meta description kosong - tidak diubah -->
  <meta name="description" content="">
  <!-- [INFO] Meta author standar - tidak diubah -->
  <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
  <!-- [INFO] Meta generator - tidak diubah -->
  <meta name="generator" content="Hugo 0.112.5">
  <!-- [EDIT] Ganti 'Tabungan Siswa' → 'Koperasi Tabungan Gusti Artha Sejahtera' pada title halaman -->
  <title>Koperasi Tabungan Gusti Artha Sejahtera</title>

  <link rel="canonical" href="https://getbootstrap.com/docs/5.3/examples/carousel/">

  <link href="assets/dist/css/bootstrap.min.css" rel="stylesheet">

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
      background-color: rgba(0, 0, 0, .1);
      border: solid rgba(0, 0, 0, .15);
      border-width: 1px 0;
      box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
    }

    .b-example-vr {
      flex-shrink: 0;
      width: 1.5rem;
      height: 100vh;
    }

    .bi {
      vertical-align: -.125em;
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
      --bd-violet-bg: #FF4C00;
      --bd-violet-rgb: 255, 76, 0;

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

    /* ========================================================================
       CUSTOM CSS - FORCE WARNA KOPERASI #FF4C00 (ORANYE)
       Override semua warna Bootstrap primary dengan warna koperasi
       ======================================================================== */

    /* Override Bootstrap CSS Variables - PALING PENTING! */
    :root,
    [data-bs-theme="light"],
    [data-bs-theme="auto"] {
      --bs-primary: #FF4C00 !important;
      --bs-primary-rgb: 255, 76, 0 !important;
      --bs-link-color: #FF4C00 !important;
      --bs-link-color-rgb: 255, 76, 0 !important;
      --bs-link-hover-color: #dd4200 !important;
      --bs-btn-bg: #FF4C00 !important;
      --bs-btn-border-color: #FF4C00 !important;
      --bs-btn-hover-bg: #dd4200 !important;
      --bs-btn-active-bg: #bb3800 !important;
    }

    /* Button Primary - Semua state */
    .btn-primary,
    .btn-primary:link,
    .btn-primary:visited {
      background-color: #FF4C00 !important;
      border-color: #FF4C00 !important;
      color: #fff !important;
    }

    .btn-primary:hover,
    .btn-primary:focus,
    .btn-primary:active,
    .btn-primary.active,
    .btn-primary.show,
    .btn-check:checked+.btn-primary {
      background-color: #dd4200 !important;
      border-color: #dd4200 !important;
      color: #fff !important;
    }

    /* Background Primary */
    .bg-primary,
    .bg-primary-subtle {
      background-color: #FF4C00 !important;
    }

    /* Text Primary */
    .text-primary,
    .text-primary-emphasis,
    a.text-primary,
    a.text-primary:hover {
      color: #FF4C00 !important;
    }

    /* Border Primary */
    .border-primary,
    .border-primary-subtle {
      border-color: #FF4C00 !important;
    }

    /* Alert Primary */
    .alert-primary {
      background-color: rgba(255, 76, 0, 0.1) !important;
      border-color: #FF4C00 !important;
      color: #bb3800 !important;
    }

    /* Badge Primary */
    .badge.bg-primary,
    .badge.text-bg-primary {
      background-color: #FF4C00 !important;
    }

    /* Table Primary */
    .table-primary {
      background-color: rgba(255, 76, 0, 0.1) !important;
      border-color: #FF4C00 !important;
    }

    /* Progress Bar Primary */
    .progress-bar.bg-primary {
      background-color: #FF4C00 !important;
    }

    /* Form Control Focus */
    .form-control:focus,
    .form-select:focus {
      border-color: #FF4C00 !important;
      box-shadow: 0 0 0 0.25rem rgba(255, 76, 0, 0.25) !important;
    }

    /* Pagination Active */
    .pagination .page-item.active .page-link {
      background-color: #FF4C00 !important;
      border-color: #FF4C00 !important;
    }

    /* Nav Pills Active */
    .nav-pills .nav-link.active {
      background-color: #FF4C00 !important;
    }

    /* List Group Active */
    .list-group-item.active {
      background-color: #FF4C00 !important;
      border-color: #FF4C00 !important;
    }

    /* ========================================================================
       NAVBAR CUSTOM - UBAH NAVBAR JADI ORANYE #FF6B00 (SESUAI FIGMA)
       ======================================================================== */

    /* Navbar - FORCE warna oranye gradient sesuai Figma */
    header[data-bs-theme="dark"],
    .navbar-dark,
    .navbar[data-bs-theme="dark"],
    header nav,
    .navbar {
      background: linear-gradient(90deg, #FF6B00 0%, #FF8533 100%) !important;
    }

    /* Override semua class background navbar */
    .navbar.bg-light,
    .navbar.bg-dark,
    .navbar-expand-md,
    nav.navbar {
      background: linear-gradient(90deg, #FF6B00 0%, #FF8533 100%) !important;
    }

    /* --------------------------------------------------------------------
       User override: remove the orange gradient from the top navbar so the
       header is neutral/clean. This override is intentionally specific and
       placed after the gradient rules to take precedence.
       ------------------------------------------------------------------ */
    .navbar-custom,
    header[data-bs-theme="dark"] .navbar-custom,
    .navbar-custom.navbar,
    header nav.navbar-custom {
      background: #ffffff !important;
      background-image: none !important;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
    }

    /* Make nav links dark on the now-white navbar */
    .navbar-custom .nav-link,
    .navbar-custom .nav-link-custom,
    .navbar-custom .navbar-nav .nav-link {
      color: #1f2937 !important;
      background-color: transparent !important;
      font-weight: 600 !important;
    }

    .navbar-custom .nav-link:hover,
    .navbar-custom .nav-link.active,
    .navbar-custom .nav-link-custom:hover,
    .navbar-custom .nav-link-custom.active {
      color: #FF6B00 !important;
      background-color: rgba(255, 76, 0, 0.06) !important;
    }

    .navbar-custom .navbar-brand {
      color: #333 !important;
    }

    /* Link navbar default hanya untuk navbar non-custom (mode oranye) */
    header nav.navbar:not(.navbar-custom) .nav-link,
    header nav.navbar:not(.navbar-custom) .nav-link-custom {
      color: rgba(255, 255, 255, 0.95) !important;
    }

    header nav.navbar:not(.navbar-custom) .nav-link:hover,
    header nav.navbar:not(.navbar-custom) .nav-link.active,
    header nav.navbar:not(.navbar-custom) .nav-link-custom:hover,
    header nav.navbar:not(.navbar-custom) .nav-link-custom.active {
      color: #fff !important;
      background-color: rgba(255, 255, 255, 0.1) !important;
      border-radius: 4px;
    }

    /* Navbar brand (logo area) */
    .navbar-brand {
      color: #fff !important;
    }

    /* Navbar toggler untuk mobile */
    .navbar-toggler {
      border-color: rgba(255, 255, 255, 0.5) !important;
    }

    .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    /* ========================================================================
       NAVBAR REDESIGN - SESUAI FIGMA (MODIFIKASI BARU)
       ======================================================================== */

    /* 1. LOGO DI POJOK KIRI ATAS - SESUAI FIGMA */
    .navbar-custom {
      /* [INFO] Navbar dengan padding yang proporsional */
      padding-top: 0.75rem !important;
      padding-bottom: 0.75rem !important;
      padding-left: 2rem !important;
      padding-right: 2rem !important;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      /* Tambah shadow untuk depth */
      backdrop-filter: blur(10px);
      /* Efek blur modern */
    }

    .logo-brand {
      /* [INFO] Brand area untuk logo di pojok kiri */
      display: inline-flex;
      align-items: center;
      padding: 0 !important;
      margin-right: 20px !important;
    }

    .navbar-logo-img {
      /* [EDIT] Logo dengan ukuran yang sesuai desain Figma */
      height: 60px;
      /* PERBESAR dari 55px ke 60px */
      width: auto;
      object-fit: contain;
      transition: transform 0.3s ease, filter 0.3s ease;
      /* Tambah transition untuk filter */
    }

    .navbar-logo-img:hover {
      /* [INFO] Hover effect logo: scale + brightness */
      transform: scale(1.08);
      /* Lebih besar saat hover */
      filter: brightness(1.1) drop-shadow(0 0 8px rgba(255, 255, 255, 0.4));
      /* Efek glow */
    }

    /* 2. LINK MENU NAVIGASI - SESUAI FIGMA (TANPA KONTAINER KOTAK) */
    .nav-link-custom {
      color: #1f2937 !important;
      font-weight: 600 !important;
      font-size: 16px !important;
      padding: 10px 22px !important;
      border-radius: 999px !important;
      border: 1px solid transparent;
      background-color: transparent !important;
      transition: transform 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
      margin: 0 6px !important;
    }

    .nav-link-custom:hover {
      background-color: rgba(255, 107, 0, 0.12) !important;
      color: #FF6B00 !important;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(17, 24, 39, 0.08) !important;
    }

    .nav-link-custom.active {
      background-color: #FF6B00 !important;
      color: #ffffff !important;
      font-weight: 700 !important;
      box-shadow: 0 8px 24px rgba(255, 107, 0, 0.25) !important;
    }

    /* 4. TOMBOL UNDUH & LOGIN - SESUAI FIGMA */
    .navbar-buttons {
      /* [INFO] Container untuk kedua tombol */
      display: flex;
      gap: 10px;
      /* [EDIT] Jarak antar tombol 10px */
      align-items: center;
    }

    .btn-unduh {
      /* [EDIT] Tombol UNDUH: background putih solid, border orange, teks orange - SESUAI FIGMA */
      background-color: #ffffff !important;
      border: 2px solid #FF6B00 !important;
      /* [INFO] Border orange untuk contrast */
      color: #FF6B00 !important;
      font-weight: 700 !important;
      font-size: 14px !important;
      /* Perbesar font */
      text-transform: uppercase;
      letter-spacing: 0.8px;
      /* Lebih lebar spacing */
      padding: 12px 28px !important;
      /* Perbesar padding */
      border-radius: 30px !important;
      /* [EDIT] Pill shape */
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
      /* Smooth easing */
      text-decoration: none !important;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12) !important;
      /* Shadow lebih tegas */
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-login-custom {
      /* [EDIT] Tombol LOGIN: background orange solid terang, border putih, teks putih - SESUAI FIGMA */
      background-color: #FF8533 !important;
      /* Orange lebih terang untuk LOGIN */
      border: 2px solid #ffffff !important;
      color: #ffffff !important;
      font-weight: 700 !important;
      font-size: 14px !important;
      /* Perbesar font */
      text-transform: uppercase;
      letter-spacing: 0.8px;
      /* Lebih lebar spacing */
      padding: 12px 32px !important;
      /* [EDIT] Padding sedikit lebih besar dari UNDUH */
      border-radius: 30px !important;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
      /* Smooth easing */
      text-decoration: none !important;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15) !important;
      /* Shadow lebih tegas */
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-login-custom:hover {
      /* [EDIT] Hover LOGIN: background putih, teks orange, border orange */
      background-color: #ffffff !important;
      border-color: #FF6B00 !important;
      color: #FF6B00 !important;
      transform: translateY(-3px) scale(1.05);
      /* Lift & scale effect */
      box-shadow: 0 6px 16px rgba(255, 255, 255, 0.45) !important;
      /* Shadow putih saat hover */
    }

    /* ========================================================================
       RESPONSIVE DESIGN - NAVBAR FIGMA STYLE
       ======================================================================== */

    @media (max-width: 991px) {
      /* Tablet dan mobile adjustments */

      .navbar-custom {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
      }

      .navbar-logo-img {
        height: 45px;
      }

      .navbar-nav {
        margin-top: 10px !important;
      }

      .nav-link-custom {
        font-size: 14px !important;
        padding: 8px 18px !important;
        margin: 4px 0 !important;
        display: block;
        text-align: center;
      }

      .navbar-buttons {
        flex-direction: column;
        width: 100%;
        margin-top: 10px;
      }

      .btn-unduh,
      .btn-login-custom {
        width: 100% !important;
        justify-content: center;
        display: flex;
      }
    }

    @media (max-width: 576px) {
      /* Mobile kecil adjustments */

      .navbar-logo-img {
        height: 38px;
      }

      .nav-link-custom {
        font-size: 13px !important;
        padding: 7px 14px !important;
      }
    }

    /* ========================================================================
       FIX NAVBAR OVERLAP ISSUE
       ======================================================================== */

    body {
      /* [INFO] Padding top untuk navbar fixed */
      padding-top: 70px;
    }

    @media (max-width: 991px) {
      body {
        padding-top: 65px;
      }
    }

    /* ========================================================================
       HAPUS BACKGROUND PUTIH CONTAINER - MENYATU DENGAN DARK BACKGROUND
       ======================================================================== */

    .container.marketing {
      background-color: transparent !important;
      box-shadow: none !important;
    }

    /* ========================================================================
       FEATURETTE STYLING - LIGHT & DARK VARIANTS
       ======================================================================== */

    /* DEFAULT: Light backgrounds (index.php, info.php) */
    .featurette-heading {
      color: #2c3e50 !important;
      /* Dark color for visibility on light bg */
    }

    .lead {
      color: #555 !important;
      /* Dark paragraph text for readability on light bg */
    }

    .featurette-divider {
      border-color: rgba(0, 0, 0, 0.08) !important;
    }

    /* DARK VARIANT: Use .featurette-dark wrapper when section has dark background */
    .featurette-dark .featurette-heading {
      color: #ffffff !important;
      /* White text on dark background */
    }

    .featurette-dark .lead {
      color: #e8e8e8 !important;
      /* Light gray text on dark background */
    }

    .featurette-dark .featurette-divider {
      border-color: rgba(255, 255, 255, 0.1) !important;
      /* Light divider on dark */
    }

    /* UTILITY: Dark text variant for specific elements */
    .text-dark-contrast {
      color: #2c3e50 !important;
    }

    /* UTILITY: Light text variant for dark backgrounds */
    .text-light-contrast {
      color: #ffffff !important;
    }

    /* ========================================================================
       CAROUSEL HEADER - PERBESAR LOGO GOOGLE PLAY
       ======================================================================== */

    /* Tingkatkan tinggi carousel untuk logo yang lebih besar */
    #myCarousel .carousel-item {
      height: 38rem !important;
      /* Tinggi carousel lebih besar untuk logo yang lebih menonjol */
    }

    #myCarousel .carousel-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      /* Pastikan logo Google Play di kanan tetap terlihat */
    }

    /* Responsive carousel untuk mobile */
    @media (max-width: 991px) {
      #myCarousel .carousel-item {
        height: 28rem !important;
      }
    }

    @media (max-width: 768px) {
      #myCarousel .carousel-item {
        height: 22rem !important;
      }
    }

    @media (max-width: 576px) {
      #myCarousel .carousel-item {
        height: 18rem !important;
      }
    }

    /* Carousel indicators styling */
    #myCarousel .carousel-indicators button {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.6);
      border: 2px solid #ffffff;
      margin: 0 5px;
    }

    #myCarousel .carousel-indicators button.active {
      background-color: #FF6B00;
      border-color: #FF6B00;
      transform: scale(1.2);
    }

    /* Carousel controls (arrows) */
    #myCarousel .carousel-control-prev,
    #myCarousel .carousel-control-next {
      width: 60px;
      height: 60px;
      top: 50%;
      transform: translateY(-50%);
      background-color: rgba(255, 107, 0, 0.8);
      border-radius: 50%;
      opacity: 0.9;
    }

    #myCarousel .carousel-control-prev:hover,
    #myCarousel .carousel-control-next:hover {
      background-color: #FF6B00;
      opacity: 1;
    }

    #myCarousel .carousel-control-prev-icon,
    #myCarousel .carousel-control-next-icon {
      width: 30px;
      height: 30px;
    }
  </style>


  <!-- Custom styles for this template -->
  <link href="carousel.css" rel="stylesheet">

  <!--- FONT-ICONS CSS -->
  <link href="assets/css/icons.css" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/048d18a465.js" crossorigin="anonymous"></script>

  <!-- Slick CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" />
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css" />

  <style>
    .container {
      max-width: 900px;
      padding: 15px;
      background-color: transparent !important;
      /* HAPUS background putih agar menyatu dengan latar belakang */
      margin-left: auto;
      margin-right: auto;
    }

    /* 
      .slider .slick-slide {
        border: solid 1px #000;
      } */

    .slider .slick-slide img {
      width: 100%;
    }

    /* make button larger and change their positions */
    .slick-prev,
    .slick-next {
      width: 50px;
      height: 50px;
      z-index: 1;
    }

    .slick-prev {
      left: 5px;
    }

    .slick-next {
      right: 5px;
    }

    .slick-prev:before,
    .slick-next:before {
      font-size: 40px;
      text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    /* move dotted nav position */
    .slick-dots {
      bottom: 15px;
    }

    /* enlarge dots and change their colors */
    .slick-dots li button:before {
      font-size: 12px;
      color: #fff;
      text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
      opacity: 1;
    }

    .slick-dots li.slick-active button:before {
      color: #dedede;
    }

    /* hide dots and arrow buttons when slider is not hovered */
    .slider:not(:hover) .slick-arrow,
    .slider:not(:hover) .slick-dots {
      opacity: 0;
    }

    /* transition effects for opacity */
    .slick-arrow,
    .slick-dots {
      transition: opacity 0.5s ease-out;
    }
  </style>

</head>