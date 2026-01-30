  <!-- FOOTER -->
  <footer class="container">
    <!-- [INFO] Bagian float-end dihapus karena tombol pindah ke fixed position di bawah -->
    <p style="text-align: center;">&copy; PT. Gusti Global Group - 2025. Gusti Artha Sejahtera.</p>
  </footer>

  <!-- [EDIT] TOMBOL SCROLL TO TOP - PINDAH KE POJOK KANAN BAWAH LAYAR -->
  <!-- [INFO] Tombol ini akan selalu terlihat di pojok kanan bawah layar, tidak ikut scroll -->
  <a href="#" style="
    position: fixed; 
    /* [EDIT] Position fixed = tombol menempel di layar, tidak ikut scroll */
    
    bottom: 30px; 
    /* [EDIT] Jarak 30px dari bawah layar - bisa diubah sesuai selera */
    
    right: 30px; 
    /* [EDIT] Jarak 30px dari kanan layar - bisa diubah sesuai selera */
    
    z-index: 9999; 
    /* [EDIT] Z-index tinggi agar tombol selalu di atas elemen lain */
    
    text-decoration: none;
    /* [INFO] Hilangkan underline pada link */
  ">
    <button type="button"
      class="btn btn-sm btn-icon"
      title="Kembali ke Atas"
      style="
              background-color: #FF4C00 !important; 
              /* [EDIT] Warna background oranye koperasi */
              
              border-color: #FF4C00 !important; 
              /* [EDIT] Warna border sama dengan background */
              
              color: #ffffff !important; 
              /* [EDIT] Warna icon panah putih agar kontras dengan background oranye */
              
              box-shadow: 0 4px 12px rgba(255, 76, 0, 0.4); 
              /* [EDIT] Bayangan oranye agar tombol lebih menonjol */
              
              width: 45px; 
              /* [EDIT] Lebar tombol 45px - bisa diubah */
              
              height: 45px; 
              /* [EDIT] Tinggi tombol 45px - membuat tombol bulat sempurna */
              
              border-radius: 50%; 
              /* [EDIT] Border radius 50% membuat tombol jadi bulat penuh */
              
              display: flex; 
              align-items: center; 
              justify-content: center;
              /* [EDIT] Flex untuk menempatkan icon di tengah-tengah tombol */
              
              transition: all 0.3s ease;
              /* [INFO] Transisi smooth saat hover - jangan diubah */
            "
      onmouseover="this.style.backgroundColor='#dd4200'; this.style.transform='translateY(-5px)'; this.style.boxShadow='0 6px 20px rgba(255, 76, 0, 0.6)';"
      onmouseout="this.style.backgroundColor='#FF4C00'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 76, 0, 0.4)';"
      /* [EDIT] Hover effect: tombol jadi lebih gelap dan naik sedikit saat di-hover mouse */
      /* [INFO] onmouseover=saat mouse masuk ke tombol */
      /* [INFO] onmouseout=saat mouse keluar dari tombol */>
      <i class="fe fe-arrow-up-circle" style="font-size: 24px;"></i>
      <!-- [INFO] Icon panah ke atas - ukuran 24px -->
      <!-- [INFO] Class 'fe fe-arrow-up-circle' adalah Feather Icons untuk panah ke atas -->
    </button>
  </a>