
        <div
          class="sidebar border border-right col-md-3 col-lg-2 p-0 bg-body-tertiary"
        >
          <div
            class="offcanvas-lg offcanvas-end bg-body-tertiary"
            tabindex="-1"
            id="sidebarMenu"
            aria-labelledby="sidebarMenuLabel"
          >
            <div class="offcanvas-header">
              <!-- <h5 class="offcanvas-title" id="sidebarMenuLabel">
                Company name
              </h5> -->
              <button
                type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                data-bs-target="#sidebarMenu"
                aria-label="Close"
              ></button>
            </div>
            <div
              class="offcanvas-body d-md-flex flex-column p-0 pt-lg-3 overflow-y-auto"
            >
              <ul class="nav flex-column">
                <li class="nav-item">
                  <a
                    class="nav-link d-flex align-items-center gap-2 active"
                    aria-current="page"
                    href="../dashboard/"
                  >
                    <i class="fe fe-home"></i>
                    Dashboard
                  </a>
                </li>

              <hr class="my-3" />

                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../transaksi/">
                    <i class="fe fe-dollar-sign"></i>
                    Transaksi
                  </a>
                </li>

              <hr class="my-3" />

                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../masuk/">
                    <i class="zmdi zmdi-money"></i>
                    Tabungan Masuk
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../keluar/">
                    <i class="zmdi zmdi-money-off"></i>
                    Penarikan Tabungan
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../approval/">
                    <i class="fe fe-check-circle"></i>
                    Approval Pinjaman
                  </a>
                </li>

              <hr class="my-3" />

                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../siswa/">
                    <i class="fe fe-user-check"></i>
                    Anggota
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../siswa/saldo">
                    <i class="fa fa-money"></i>
                    Check Saldo Anggota
                  </a>
                </li>

              <hr class="my-3" />

                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../rekap/">
                    <i class="fe fe-database"></i>
                    Rekap Transaksi
                  </a>
                </li>

                <li class="nav-item">
                  <a class="nav-link d-flex align-items-center gap-2" href="../transfer/">
                    <i class="zmdi zmdi-money-box"></i>
                    Rekap Transfer
                  </a>
                </li>

              <hr class="my-3" />

              <!-- Profile, Pengaturan, Keluar moved to header dropdown -->
            </div>
          </div>
        </div>