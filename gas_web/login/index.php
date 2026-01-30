<?php
// koneksi ke database dan hak akses
session_start();

include 'koneksi/pengaturan.php';

// Only redirect to dashboard when user is authenticated and there is no
// active login error waiting to be shown. This ensures failed login attempts
// return the user to the login page so they can retry.
if (isset($_SESSION['akses']) && empty($_SESSION['error'])) {
  // Auto-detect current directory path
  $current_dir = dirname(__FILE__);
  $doc_root = $_SERVER['DOCUMENT_ROOT'];
  $relative_path = str_replace($doc_root, '', $current_dir);
  $relative_path = str_replace('\\', '/', $relative_path);
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
  $redirect_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $relative_path . '/' . $_SESSION['akses'] . '/dashboard/';
  header('Location: ' . $redirect_url);
  exit();
}

$error = '';
$success = '';
if (isset($_SESSION['error'])) {

  $error = $_SESSION['error']; // set error

  unset($_SESSION['error']);
}

// Map well-known success keys passed via query string to their full messages
$successKeys = array(
  'aktivasi' => 'Pengajuan aktivasi akun diterima, silakan tunggu persetujuan admin.',
  'set_pin' => 'PIN berhasil dibuat. Silakan login kembali menggunakan nomor HP dan password Anda.'
);

if (isset($_GET['success']) && isset($successKeys[$_GET['success']])) {
  $success = $successKeys[$_GET['success']];
}

if (isset($_SESSION['success'])) {
  // Session takes precedence if both are set
  $success = $_SESSION['success'];
  unset($_SESSION['success']);
} ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
  <script src="../assets/js/color-modes.js"></script>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="" />
  <meta
    name="author"
    content="Mark Otto, Jacob Thornton, and Bootstrap contributors" />
  <meta name="generator" content="Hugo 0.112.5" />
  <title>Login Petugas Tabungan</title>

  <!-- FAVICON -->
  <link rel="shortcut icon" type="image/png" href="../assets/brand/logo.png" />
  <link
    rel="canonical"
    href="https://getbootstrap.com/docs/5.3/examples/sign-in/" />
  <!-- INTERNAL Notifications  Css -->
  <link href="../assets/plugins/notify/css/jquery.growl.css" rel="stylesheet" />
  <link href="../assets/plugins/notify/css/notifIt.css" rel="stylesheet" />

  <!--- FONT-ICONS CSS -->
  <link href="../assets/css/icons.css" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/048d18a465.js" crossorigin="anonymous"></script>

  <link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet" />


  <?php include "stylelogin.php"; ?>

  <style>
    /* Icon-in-input style for password toggle (rounded pill with subtle border)
       Matches reference: small rounded box at right edge of input with eye icon. */
    /* Make the entire input-group look like a single bordered control so
       the left icon, input and right eye icon appear inside one box. */
    .login-box .input-group {
      border: 1px solid #ced4da;
      border-radius: 6px;
      overflow: hidden;
      display: flex;
      align-items: center;
      background: #fff;
    }

    .login-box .input-group .input-group-text,
    .login-box .input-group .form-control {
      border: none !important;
      background: transparent !important;
      box-shadow: none !important;
      height: 38px;
      display: flex;
      align-items: center;
      padding: 0.375rem 0.75rem;
    }

    .login-box .input-group .form-control::placeholder {
      color: #9aa0a6;
    }

    .btn-password-toggle {
      border: none !important;
      background: transparent !important;
      box-shadow: none !important;
      padding: 0 8px !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      height: 38px;
      min-width: 38px;
      border-radius: 0 !important;
      color: #6c757d;
    }

    .btn-password-toggle .fa {
      font-size: 16px;
    }
  </style>

  <!-- Custom styles for this template -->
  <link href="sign-in.css" rel="stylesheet" />
</head>

<body>

  <div class="login-container">
    <div class="login-left">
      <img src="../assets/brand/logo.png" alt="Logo Koperasi GAS" />
    </div>
    <div class="login-right">
      <div class="login-box">
        <form action="check-login.php" method="post" class="needs-validation" novalidate>
          <div class="mb-4">
            <div class="login-title">Selamat Datang Kembali</div>
            <div class="login-sub">Silakan masuk untuk melanjutkan</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa fa-user"></i></span>
              <input type="text" class="form-control" name="usernamemu" placeholder="Masukkan username Anda" required />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Kata Sandi</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa fa-lock"></i></span>
              <input id="passwordmu" type="password" class="form-control" name="passwordmu" placeholder="Masukkan kata sandi Anda" required aria-label="Kata Sandi" />
              <span class="input-group-text btn-password-toggle" id="togglePassword" role="button" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi"><i class="fa fa-eye" aria-hidden="true"></i></span>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input flexCheckDefault" type="checkbox" id="remember" />
              <label class="form-check-label remember" for="remember">Ingat saya</label>
            </div>
            <div><a href="#" class="forgot-link">Lupa kata sandi?</a></div>
          </div>

          <div class="d-grid">
            <button class="btn btn-login btn-lg" type="submit">Masuk &nbsp; <i class="fa fa-arrow-right"></i></button>
          </div>

          <p class="mt-4 text-muted small">&copy; <?php echo $nama_sekolah; ?> - <?php echo date('Y'); ?></p>
        </form>
      </div>
    </div>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.umd.min.js"
    integrity="sha384-gdQErvCNWvHQZj6XZM0dNsAoY4v+j5P1XDpNkcM3HJG1Yx04ecqIHk7+4VBOCHOG"
    crossorigin="anonymous"></script>

  <script src="../assets/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Load jQuery first, before plugins that depend on it -->
  <script src="../assets/js/jquery.min.js"></script>
  <!-- INTERNAL Notifications js (depends on jQuery) -->
  <script src="../assets/plugins/notify/js/rainbow.js"></script>
  <!-- <script src="../assets/plugins/notify/js/sample.js"></script> -->
  <script src="../assets/plugins/notify/js/jquery.growl.js"></script>
  <script src="../assets/plugins/notify/js/notifIt.js"></script>

  <!-- Memunculkan Notif Ceklis Wajib -->
  <script type="text/javascript">
    $('.flexCheckDefault').click(function() {
      notif({
        msg: "<b>Wajib Ceklis</b> Bila Data Sudah Benar!",
        type: "warning",
        position: "center",
      });
    });
  </script>

  <!-- Toggle password visibility -->
  <script type="text/javascript">
    $(document).on('click', '#togglePassword', function(e) {
      e.preventDefault();
      var $btn = $(this);
      var $icon = $btn.find('i');
      var $input = $('#passwordmu');
      if ($input.attr('type') === 'password') {
        $input.attr('type', 'text');
        $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        $btn.attr('aria-label', 'Sembunyikan kata sandi');
        $btn.attr('title', 'Sembunyikan kata sandi');
      } else {
        $input.attr('type', 'password');
        $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        $btn.attr('aria-label', 'Tampilkan kata sandi');
        $btn.attr('title', 'Tampilkan kata sandi');
      }
    });
  </script>

  <!-- Submit login via AJAX so browser doesn't navigate to check-login.php URL -->
  <script>
    (function($) {
      $(function() {
        var $form = $('form.needs-validation');
        var $btn = $form.find('.btn-login');

        $form.on('submit', function(e) {
          e.preventDefault();

          // basic HTML5 validation
          if (!this.checkValidity()) {
            $form.addClass('was-validated');
            return;
          }

          // show loading spinner
          $btn.addClass('loading');
          if ($btn.find('.btn-spinner').length === 0) {
            $btn.append('<span class="btn-spinner" aria-hidden="true"></span>');
          }
          // serialize form BEFORE disabling inputs so values are included
          var payload = $form.serialize() + '&ajax_login=1';
          $form.find('input,button').prop('disabled', true);
          $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: payload,
            dataType: 'text', // receive text and parse manually to avoid jQuery parseerror
            timeout: 15000,
          }).done(function(text) {
            var resp = null;
            try {
              resp = JSON.parse(text);
            } catch (e) {
              // If parsing fails, show detailed info and bail
              notif({
                msg: '<b>Server mengembalikan data tidak valid.</b>',
                type: 'error',
                position: 'center'
              });
              console.error('Invalid JSON from login:', text);
              $btn.removeClass('loading');
              $btn.find('.btn-spinner').remove();
              $form.find('input,button').prop('disabled', false);
              return;
            }
            if (resp && resp.success) {
              window.location.href = resp.redirect;
            } else {
              var msg = (resp && resp.error) ? resp.error : 'Login gagal';
              notif({
                msg: '<b>' + msg + '</b>',
                type: 'error',
                position: 'center'
              });
              $btn.removeClass('loading');
              $btn.find('.btn-spinner').remove();
              $form.find('input,button').prop('disabled', false);
            }
          }).fail(function(xhr, status, err) {
            // Try to extract useful info from the failed request to aid debugging
            var statusCode = xhr.status || 0;
            var statusText = xhr.statusText || status || err || '';
            var body = xhr.responseText || '';
            // Try to parse JSON error if any
            try {
              var parsed = JSON.parse(body);
              if (parsed && parsed.error) {
                body = parsed.error;
              }
            } catch (e) {
              // not JSON, keep raw body
            }
            var message = 'Terjadi kesalahan jaringan atau server (HTTP ' + statusCode + ')';
            // Show a brief notif and log detailed info to console for developer
            notif({
              msg: '<b>' + message + '</b>',
              type: 'error',
              position: 'center'
            });
            console.error('Login AJAX failed', {
              statusCode: statusCode,
              statusText: statusText,
              body: body
            });
            // also show a compact debug snippet in a second notification to help capture the response
            var snippet = (typeof body === 'string') ? body.substring(0, 500) : JSON.stringify(body).substring(0, 500);
            if (snippet) {
              notif({
                msg: '<b>Detail:</b> ' + snippet,
                type: 'error',
                position: 'center'
              });
            }
            $btn.removeClass('loading');
            $btn.find('.btn-spinner').remove();
            $form.find('input,button').prop('disabled', false);
          });
        });
      });
    })(jQuery);
  </script>

  <!-- (login animation removed to restore original behavior) -->

  <!-- Memunculkan Notif Eror (tampil hanya saat ada error) -->
  <?php if (!empty($error)): ?>
    <script>
      setTimeout(function() {
        notif({
          msg: "<b><?php echo htmlspecialchars($error, ENT_QUOTES); ?></b>",
          type: "error",
          position: "center",
        });
      }, 100);
    </script>
  <?php endif; ?>

  <!-- Memunculkan Notif Sukses (tampil hanya saat ada success) -->
  <?php if (!empty($success)): ?>
    <script>
      setTimeout(function() {
        notif({
          msg: "<b><?php echo htmlspecialchars($success, ENT_QUOTES); ?></b>",
          type: "success",
          position: "center",
        });
      }, 100);
    </script>
  <?php endif; ?>

</body>

</html>