
<!-- [INFO] Bootstrap JS untuk komponen dropdown, modal, dll - jangan diubah -->
<script src="assets/dist/js/bootstrap.bundle.min.js"></script>

<!-- [EDIT] JAVASCRIPT UNTUK SMOOTH SCROLL TO TOP -->
<script>
  // [INFO] Script ini membuat halaman scroll smooth ke atas saat tombol diklik
  
  document.addEventListener('DOMContentLoaded', function() {
    // [INFO] DOMContentLoaded = jalankan script setelah halaman selesai dimuat
    
    // [INFO] Cari semua link yang href-nya '#' (tombol scroll to top kita)
    const scrollTopLinks = document.querySelectorAll('a[href="#"]');
    
    scrollTopLinks.forEach(function(link) {
      // [INFO] Loop untuk setiap link yang ditemukan
      
      link.addEventListener('click', function(e) {
        // [INFO] Tambahkan event listener untuk klik
        
        e.preventDefault();
        // [EDIT] Prevent default = cegah browser loncat ke # (refresh halaman)
        
        window.scrollTo({
          top: 0,
          // [EDIT] Scroll ke posisi paling atas (top = 0)
          
          behavior: 'smooth'
          // [EDIT] Behavior smooth = scroll dengan animasi halus, bukan langsung loncat
        });
      });
    });
  });
</script>