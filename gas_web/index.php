<!doctype html>
<html lang="en" data-bs-theme="auto">
    <?php include 'data/head.php'; ?>

  <body>
    <?php include 'data/svg.php'; ?>    

    <?php include 'data/header.php'; ?>    
<main>

  <div id="myCarousel" class="carousel slide mb-6" data-bs-ride="carousel" data-bs-theme="light">
    <div class="carousel-indicators">
      <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
      <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
      <button type="button" data-bs-target="#myCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner">
      <div class="carousel-item active">
        <img src="assets/header/header.png" height="100%" />
      </div>
      <div class="carousel-item">
        <img src="assets/header/header1.png" height="100%" />
        <!-- <div class="container">
          <div class="carousel-caption text-start">
            <h1>Example headline.</h1>
            <p class="opacity-75">Some representative placeholder content for the first slide of the carousel.</p>
            <p><a class="btn btn-lg btn-primary" href="#">Sign up today</a></p>
          </div>
        </div> -->
      </div>
      <div class="carousel-item">
        <img src="assets/header/header2.png" height="100%" />
      </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#myCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#myCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

  <!-- ========================================================================
       SECTION HERO KOPERASI - PERFECTLY CENTERED
       ======================================================================== -->
  <section class="hero-koperasi-section">
    <div class="container h-100">
      <div class="row h-100 justify-content-center align-items-center">
        <!-- Kolom konten teks - CENTERED -->
        <div class="col-lg-8 col-md-10 col-12 text-center">
          <!-- Badge kategori "Tabungan Koperasi" -->
          <div class="hero-badge mb-3">
            <span class="badge-text">TABUNGAN KOPERASI</span>
          </div>
          
          <!-- Judul utama section -->
          <h1 class="hero-title mb-4">
            Tabungan yang bisa ngelola keuangan mu,<br>
            <span class="hero-subtitle">Bersama Gusti Artha Sejahtera</span>
          </h1>
          
          <!-- Deskripsi singkat -->
          <p class="hero-description mb-4">
            Bersama GAS bikin hidup kamu jadi ringkas dan mudah, ayo cobain 
            sekarang keuangan mu auto GAS GAS GAS ngebut!
          </p>
          
          <!-- Tombol Call-to-Action - Link ke Admin (Dynamic Path) -->
          <?php
          $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'];
          $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
          $base_url = $protocol . '://' . $host . $base_path;
          ?>
          <a href="<?php echo $base_url; ?>/login/" class="btn-hero-cta">
            Login Admin/Petugas
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-arrow-right ms-2" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- [INFO] CSS Khusus untuk Section Hero Koperasi -->
  <style>
    /* ====================================================================
       HERO KOPERASI SECTION - CUSTOM STYLES
       ==================================================================== */
    .hero-koperasi-section {
      background: linear-gradient(180deg, #ffffff 0%, #fff9f5 100%);
      min-height: calc(100vh - 70px);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 40px;
    }
    .hero-koperasi-section .container {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .hero-koperasi-section .row {
      width: 100%;
      justify-content: center;
      align-items: center;
    }
    .hero-title, .hero-subtitle {
      text-align: center;
      margin-left: auto;
      margin-right: auto;
      width: 100%;
    }
    .hero-badge {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 18px;
    }
    .btn-hero-cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 14px 32px;
      font-size: 1rem;
      font-weight: 600;
      color: #FF4C00;
      background-color: transparent;
      border: 2px solid #FF4C00;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      margin-left: auto;
      margin-right: auto;
      margin-top: 18px;
    }
    .btn-hero-cta:hover {
      background-color: #FF4C00;
      color: #ffffff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 76, 0, 0.3);
    }
    .btn-hero-cta svg {
      transition: transform 0.3s ease;
    }
    .btn-hero-cta:hover svg {
      transform: translateX(5px);
    }
    /* Navbar custom - tombol rata tengah dan padding konsisten */
    .navbar-nav {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 18px;
      width: 100%;
    }
    .nav-link-custom {
      padding: 10px 28px !important;
      border-radius: 20px !important;
      font-size: 16px !important;
      font-weight: 600 !important;
      margin: 0 2px !important;
      min-width: 90px;
      text-align: center;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .navbar-buttons {
      gap: 12px !important;
      align-items: center;
      justify-content: center;
    }
    .btn-unduh, .btn-login-custom {
      border-radius: 20px !important;
      padding: 10px 28px !important;
      font-size: 15px !important;
      font-weight: 600 !important;
      min-width: 90px;
      text-align: center;
      margin: 0 2px !important;
      box-shadow: 0 2px 8px rgba(255,76,0,0.08);
    }
    /* Responsive adjustments */
    @media (max-width: 991px) {
      .navbar-nav { gap: 8px; }
      .nav-link-custom, .btn-unduh, .btn-login-custom { padding: 8px 16px !important; min-width: 70px; font-size: 14px !important; }
    }
    @media (max-width: 576px) {
      .navbar-nav { gap: 4px; }
      .nav-link-custom, .btn-unduh, .btn-login-custom { padding: 7px 10px !important; min-width: 50px; font-size: 13px !important; }
    }
    
    .hero-koperasi-section .row {
      height: 100%;
    }
    
    /* Badge "Tabungan Koperasi" */
    .hero-badge {
      display: inline-block;
    }
    
    .badge-text {
      /* [INFO] Badge dengan warna oranye */
      background-color: #FF4C00;
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      padding: 8px 20px;
      border-radius: 20px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    /* Judul Utama */
    .hero-title {
      /* [INFO] Judul besar dengan font tebal */
      font-size: 2.8rem;
      font-weight: 700;
      color: #2c3e50;
      line-height: 1.3;
      margin-bottom: 20px;
    }
    
    .hero-subtitle {
      /* [INFO] Subtitle dengan warna hitam untuk emphasis */
      color: #1a1a1a;
      font-weight: 800;
    }
    
    /* Deskripsi */
    .hero-description {
      /* [INFO] Teks deskripsi dengan warna abu-abu */
      font-size: 1.1rem;
      color: #6c757d;
      line-height: 1.6;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }
    
    /* Tombol CTA */
    .btn-hero-cta {
      /* [INFO] Tombol dengan border oranye dan teks oranye */
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 14px 32px;
      font-size: 1rem;
      font-weight: 600;
      color: #FF4C00;
      background-color: transparent;
      border: 2px solid #FF4C00;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    
    .btn-hero-cta:hover {
      /* [INFO] Hover effect: background oranye, teks putih */
      background-color: #FF4C00;
      color: #ffffff;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 76, 0, 0.3);
    }
    
    .btn-hero-cta svg {
      /* [INFO] Icon panah di tombol */
      transition: transform 0.3s ease;
    }
    
    .btn-hero-cta:hover svg {
      /* [INFO] Animasi panah bergerak ke kanan saat hover */
      transform: translateX(5px);
    }
    
    /* Ilustrasi Smartphone Google Play - SESUAI FIGMA */
    .hero-illustration-phone {
      position: relative;
      max-width: 400px;
      margin: 0 auto;
    }
    
    .phone-mockup {
      width: 100%;
      height: auto;
      max-width: 350px;
      object-fit: contain;
      filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.15));
    }
    
    /* ====================================================================
       RESPONSIVE DESIGN - MOBILE
       ==================================================================== */
    
    @media (max-width: 991px) {
      /* [INFO] Adjust untuk tablet dan mobile */
      .hero-koperasi-section {
        min-height: calc(100vh - 60px);
        padding: 20px 0;
      }
      
      .hero-title {
        font-size: 2rem;
      }
      
      .hero-description {
        font-size: 1rem;
      }
      
      .phone-mockup {
        max-width: 280px;
      }
    }
    
    @media (max-width: 576px) {
      /* [INFO] Adjust untuk mobile kecil */
      .hero-title {
        font-size: 1.6rem;
      }
      
      .btn-hero-cta {
        padding: 12px 24px;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
      }
    }
    
    /* ====================================================================
       TEXT ALIGNMENT - Desktop vs Mobile
       ==================================================================== */
    
    @media (min-width: 992px) {
      /* [INFO] Di desktop, teks rata kiri */
      .text-lg-start .hero-description {
        margin-left: 0;
      }
    }
  </style>
  <!-- [INFO] End of Hero Koperasi Section Custom CSS -->

  <!-- START THE FEATURETTES -->
  <div class="container marketing">

    <hr class="featurette-divider">

        <div class="row featurette">
        <div class="col-md-7">
            <!-- [EDIT] Ganti 'Daftar Akun Tabungan' → tetap relevan untuk koperasi -->
            <h2 class="featurette-heading fw-normal lh-1">Daftar Akun Tabungan</h2>
            <!-- [EDIT] Ganti 'Tabungan Siswa' → 'Koperasi Tabungan' untuk mencerminkan sistem koperasi -->
            <p class="lead">Anda dapat membuat akun tabungan bisa melalui Aplikasi Tabungan Koperasi Gusti Artha Sejahtera</p>
        </div>
        <div class="col-md-5">
          <img src="assets/header/slide1.png" width="75%"/>
        </div>
        </div>

    <hr class="featurette-divider">

        <div class="row featurette">
        <div class="col-md-7 order-md-2">
            <!-- [INFO] Judul ini tidak mengandung kata siswa/sekolah - tetap dipertahankan -->
            <h2 class="featurette-heading fw-normal lh-1">Menabung Menjadi Mudah</h2>
            <!-- [EDIT] Ganti 'Tabungan Siswa di Sekolah' → 'Koperasi Tabungan Gusti Artha Sejahtera' untuk mencerminkan sistem koperasi perusahaan -->
            <p class="lead">Menabung dengan mendatangi Ruangan Tabungan Koperasi di PT. Gusti Business Distrik untuk bertemu dengan Petugas.</p>
        </div>
        <div class="col-md-5 order-md-1">
          <img src="assets/header/slide2.png" width="75%"/>
        </div>
        </div>

    <hr class="featurette-divider">

        <div class="row featurette">
        <div class="col-md-7">
            <!-- [INFO] Judul tidak mengandung kata siswa/sekolah - tetap dipertahankan -->
            <h2 class="featurette-heading fw-normal lh-1">Keamanan Tabungan</h2>
            <!-- [EDIT] Ganti 'Tabungan Sekolah' → 'Koperasi' untuk mencerminkan bahwa tabungan dikelola oleh koperasi -->
            <p class="lead">Uang Tabungan anda akan tersimpan aman di Tabungan Koperasi jadi tidakperlu khawatir untuk kehilangan uang di tabungan anda.</p>
        </div>
        <div class="col-md-5">
          <img src="assets/header/slide3.png" width="75%"/>
        </div>
        </div>

    <hr class="featurette-divider">

        <div class="row featurette">
        <div class="col-md-7 order-md-2">
            <!-- [INFO] Judul tidak mengandung kata siswa/sekolah - tetap dipertahankan -->
            <h2 class="featurette-heading fw-normal lh-1">Bisa Transfer Saldo</h2>
            <!-- [EDIT] Ganti 'Tabungan Siswa' → 'Koperasi' untuk mencerminkan bahwa transfer antar anggota koperasi -->
            <p class="lead">Transfer Saldo Tabungan ke sesama pengguna Tabungan Koperasi sangat mudah dan cepat.</p>
        </div>
        <div class="col-md-5 order-md-1">
          <img src="assets/header/slide4.png" width="75%"/>
        </div>
        </div>

    <hr class="featurette-divider">

    <!-- /END THE FEATURETTES -->

  </div><!-- /.container -->


</main>

    <?php include 'data/footer.php'; ?> 

    <?php include 'data/js.php'; ?> 

    </body>
</html>
