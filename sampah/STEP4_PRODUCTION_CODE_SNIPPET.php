<?php
/**
 * PRODUCTION READY - STEP 4 CODE SNIPPET
 * Untuk diintegrasikan ke setor_manual_admin.php
 * 
 * Location: Antara STEP 3 (INSERT transaksi) dan STEP 5 (INSERT notifikasi)
 * 
 * Copy-paste code di bawah langsung ke file setor_manual_admin.php
 * Sesuaikan dengan existing code flow
 */

// ============================================================================
// COPY-PASTE DARI SINI KE setor_manual_admin.php
// ============================================================================

    // STEP 4: Insert ke tabel mulai_nabung untuk konsistensi data
    // Peringatan: WAJIB insert ke mulai_nabung agar setoran manual muncul di halaman "Tabungan Masuk"
    // Tabel mulai_nabung adalah sumber data untuk halaman riwayat setoran user
    try {
        // Cek apakah kolom 'sumber' ada di mulai_nabung
        $has_sumber_col = false;
        $col_check_mn = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'sumber'");
        if ($col_check_mn && $col_check_mn->num_rows > 0) {
            $has_sumber_col = true;
        }
        
        // Get user info (nomor_hp dan nama_pengguna) untuk mulai_nabung
        $stmt_user_info = $connect->prepare("SELECT no_hp, nama_lengkap FROM pengguna WHERE id = ? LIMIT 1");
        if (!$stmt_user_info) {
            throw new Exception('Prepare user_info error: ' . $connect->error);
        }
        $stmt_user_info->bind_param('i', $id_pengguna);
        $stmt_user_info->execute();
        $res_user_info = $stmt_user_info->get_result();
        
        $nomor_hp = '';
        $nama_pengguna = '';
        if ($res_user_info->num_rows > 0) {
            $row_user_info = $res_user_info->fetch_assoc();
            $nomor_hp = $row_user_info['no_hp'] ?? '';
            $nama_pengguna = $row_user_info['nama_lengkap'] ?? '';
        }
        $stmt_user_info->close();
        
        // Tentukan status untuk mulai_nabung
        // Status 'approved' di tabungan_masuk → 'berhasil' di mulai_nabung
        $status_mulai = 'berhasil';
        
        // Tentukan sumber
        $sumber_mulai = 'admin';
        
        // Build INSERT statement untuk mulai_nabung
        if ($has_sumber_col) {
            // Jika kolom sumber sudah ada, include ke dalam INSERT
            $stmt_mulai = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, sumber, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt_mulai) {
                throw new Exception('Prepare mulai_nabung error: ' . $connect->error);
            }
            $now_datetime = date('Y-m-d H:i:s');
            $stmt_mulai->bind_param('isssiisss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $sumber_mulai, $now_datetime);
        } else {
            // Jika kolom sumber belum ada, insert tanpa sumber
            $stmt_mulai = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt_mulai) {
                throw new Exception('Prepare mulai_nabung error: ' . $connect->error);
            }
            $now_datetime = date('Y-m-d H:i:s');
            $stmt_mulai->bind_param('isssiiss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $now_datetime);
        }
        
        if (!$stmt_mulai->execute()) {
            // Log error tapi jangan stop proses (non-fatal) - untuk backward compatibility
            error_log('WARNING [setor_manual] Insert mulai_nabung gagal (non-fatal): ' . $stmt_mulai->error);
            error_log('WARNING [setor_manual] Kolom sumber tersedia: ' . ($has_sumber_col ? 'YES' : 'NO'));
        } else {
            $mulai_nabung_id = $stmt_mulai->insert_id;
            error_log('[setor_manual] Insert mulai_nabung berhasil: id=' . $mulai_nabung_id);
        }
        $stmt_mulai->close();
        
    } catch (Exception $e) {
        // Log error tapi jangan stop transaksi (non-fatal)
        error_log('WARNING [setor_manual] mulai_nabung insert error (non-fatal): ' . $e->getMessage());
    }

// ============================================================================
// SAMPAI SINI - LANJUT DENGAN STEP 5 (INSERT notifikasi)
// ============================================================================

?>
