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
    box-shadow: inset 0 0.5em 1.5em rgba(0, 0, 0, 0.1),
      inset 0 0.125em 0.5em rgba(0, 0, 0, 0.15);
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

  /* [INFO] Class CSS untuk tombol utama dengan warna tema */
  .btn-bd-primary {
    /* [EDIT] Ganti warna ungu #712cf9 → #FF4C00 (warna koperasi) */
    --bd-violet-bg: #FF4C00;
    /* [INFO] RGB value untuk shadow - tidak diubah karena tidak terlihat */
    --bd-violet-rgb: 112.520718, 44.062154, 249.437846;

    /* [INFO] Font weight tombol - tidak diubah */
    --bs-btn-font-weight: 600;
    /* [INFO] Warna teks tombol putih - tidak diubah */
    --bs-btn-color: var(--bs-white);
    /* [INFO] Background tombol menggunakan variabel di atas - tidak diubah */
    --bs-btn-bg: var(--bd-violet-bg);
    /* [INFO] Border tombol menggunakan variabel di atas - tidak diubah */
    --bs-btn-border-color: var(--bd-violet-bg);
    /* [INFO] Warna teks saat hover putih - tidak diubah */
    --bs-btn-hover-color: var(--bs-white);
    /* [EDIT] Ganti warna hover #6528e0 → #FF4C00 (warna koperasi) */
    --bs-btn-hover-bg: #FF4C00;
    /* [EDIT] Ganti warna border hover #6528e0 → #FF4C00 (warna koperasi) */
    --bs-btn-hover-border-color: #FF4C00;
    /* [INFO] Shadow RGB saat focus - tidak diubah karena tidak terlihat */
    --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
    /* [INFO] Warna teks saat active - tidak diubah */
    --bs-btn-active-color: var(--bs-btn-hover-color);
    /* [EDIT] Ganti warna saat active #5a23c8 → #FF4C00 (warna koperasi) */
    --bs-btn-active-bg: #FF4C00;
    /* [EDIT] Ganti warna border saat active #5a23c8 → #FF4C00 (warna koperasi) */
    --bs-btn-active-border-color: #FF4C00;
  }

  .bd-mode-toggle {
    z-index: 1500;
  }

  /* New layout for split-screen login */
  html,
  body {
    height: 100%;
  }

  .login-container {
    display: flex;
    min-height: 100vh;
  }

  .login-left {
    flex: 1 1 60%;
    background: linear-gradient(180deg, #ff7a00 0%, #ff6a00 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
  }

  .login-left img {
    max-width: 60%;
    height: auto;
  }

  .login-right {
    flex: 1 1 40%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 30px;
  }

  .login-box {
    width: 100%;
    max-width: 420px;
  }

  .login-title {
    font-size: 28px;
    font-weight: 700;
    color: #22343d;
  }

  .login-sub {
    color: #6b7a80;
    margin-bottom: 18px;
  }

  .form-control:focus {
    box-shadow: none;
    border-color: #ff7a00;
  }

  .btn-login {
    background: linear-gradient(90deg, #ff7a00, #ff6a00);
    border: none;
    color: #fff;
    font-weight: 700;
    padding: 12px 18px;
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(255, 106, 0, 0.18);
    transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-login:active {
    transform: translateY(1px);
  }

  /* ensure icon and text inside the button are white */
  .btn-login,
  .btn-login * {
    color: #ffffff !important;
  }

  /* spinner shown when submitting */
  .btn-spinner {
    width: 18px;
    height: 18px;
    border: 3px solid rgba(255, 255, 255, 0.25);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: btn-spin 0.8s linear infinite;
    display: inline-block;
  }

  @keyframes btn-spin {
    to {
      transform: rotate(360deg);
    }
  }

  /* hide original arrow icon when loading */
  .btn-login.loading i.fa {
    display: none;
  }

  .forgot-link {
    color: #ff6a00;
    font-weight: 600;
  }

  .remember {
    color: #6b7a80;
  }

  @media (max-width: 900px) {
    .login-container {
      flex-direction: column;
    }

    .login-left,
    .login-right {
      flex: none;
      width: 100%;
      min-height: 40vh;
    }

    .login-left img {
      max-width: 40%;
    }
  }

  /* Enhanced left panel: smaller decorative corner, subtle logo float, and glow */
  /* Make page non-scrollable and background match left panel */
  html,
  body {
    margin: 0 !important;
    padding: 0 !important;
    height: 100%;
    background: linear-gradient(180deg, #ff7a00 0%, #ff6a00 100%) !important;
    overflow: hidden;
    /* prevent scrolling */
  }

  .login-container {
    min-height: 100vh;
    margin: 0;
    padding: 0;
    height: 100vh;
    overflow: hidden;
  }

  /* Use fixed panels so each side reaches the top and covers the background */
  .login-left {
    position: fixed !important;
    left: 0;
    top: 0;
    bottom: 0;
    width: 60% !important;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
    margin: 0 !important;
    z-index: 1;
  }

  /* smaller, subtler decorative corner */
  .login-left::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 0;
    height: 0;
    border-left: 0 solid transparent;
    border-right: 110px solid transparent;
    border-bottom: 28px solid #ff6a00;
    filter: drop-shadow(0 6px 8px rgba(0, 0, 0, 0.04));
    transform-origin: left top;
    z-index: 2;
    pointer-events: none;
  }

  @media (max-width: 480px) {
    .login-left::before {
      border-right: 70px solid transparent;
      border-bottom: 20px solid #ff6a00;
      top: -20px;
    }
  }

  /* logo styling: remove inset/background rectangle and use a subtle outer shadow */
  .login-left img {
    max-width: 66%;
    height: auto;
    display: inline-block;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    animation: gas-logo-float 4s ease-in-out infinite;
    position: relative;
    z-index: 3;
  }

  @keyframes gas-logo-float {
    0% {
      transform: translateY(0) rotate(-0.3deg);
    }

    25% {
      transform: translateY(-4px) rotate(0.2deg);
    }

    50% {
      transform: translateY(-8px) rotate(0.4deg);
    }

    75% {
      transform: translateY(-4px) rotate(0.2deg);
    }

    100% {
      transform: translateY(0) rotate(-0.3deg);
    }
  }

  /* remove glow under logo (user requested no shadow) */
  .login-left::after {
    content: none !important;
    display: none !important;
  }

  /* ensure right column is also full-height and centers its form */
  .login-right {
    position: fixed !important;
    right: 0;
    top: 0;
    bottom: 0;
    width: 40% !important;
    height: 100vh !important;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 40px 30px !important;
    margin: 0 !important;
    position: fixed;
    z-index: 9999;
    background: #fff;
  }

  /* cover any decorative bleed at the very top with a white strip */
  .login-right::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 28px;
    background: #fff;
    z-index: 10000;
    pointer-events: none;
  }

  @media (max-width: 900px) {

    /* revert to stacked flow on small screens */
    .login-left,
    .login-right {
      position: static !important;
      width: 100% !important;
      height: auto !important;
      overflow: visible !important;
    }

    .login-container {
      display: block;
    }

    .login-left img {
      max-width: 40%;
    }

    /* allow scrolling on small screens */
    html,
    body {
      overflow: auto;
    }
  }
</style>