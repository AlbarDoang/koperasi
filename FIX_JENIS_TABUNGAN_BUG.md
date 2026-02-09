# Fix: Jenis Tabungan Display Bug

## Masalah
Di halaman "Rincian Transaksi", field "Jenis Tabungan" menampilkan jenis yang salah.

**Contoh:**
- Transaksi #298: Seharusnya "Tabungan Reguler" tetapi menampilkan "Tabungan Qurban"
- Ini terjadi untuk user dengan multiple transactions ketika transaksi terbaru memiliki jenis_tabungan berbeda

## Root Cause
Di file `gas_web/flutter_api/get_riwayat_transaksi.php`, saat mengambil `jenis_tabungan` untuk transaksi setoran:

**Query Lama (SALAH):**
```php
$sql_detail = "SELECT tm.id_jenis_tabungan, jt.nama_jenis 
              FROM tabungan_masuk tm
              LEFT JOIN jenis_tabungan jt ON jt.id = tm.id_jenis_tabungan
              WHERE tm.id_pengguna = ?
              ORDER BY tm.created_at DESC
              LIMIT 1";
```

**Masalah:**
- Query ini mengambil transaksi `tabungan_masuk` terbaru dari user, bukan yang sesuai dengan transaksi spesifik
- Ketika user memiliki multiple transaksi dengan jenis_tabungan berbeda, jenis terbaru dipakai untuk semua transaksi
- Contoh: User punya 2 setoran (Tabungan Reguler, Tabungan Qurban), semua akan ditampilkan sebagai Tabungan Qurban (jenis terbaru)

## Solusi
Memanfaatkan informasi `mulai_nabung` ID yang sudah tersimpan di field `keterangan` tabel `transaksi`:

**Contoh keterangan:**
- `"Mulai nabung tunai (mulai_nabung 293)"`
- `"Setoran Tabungan Ditolak (mulai_nabung 293)"`
- `"Setoran Tabungan Disetujui (mulai_nabung 293)"`

**Strategi:**
1. Extract `mulai_nabung` ID dari field `keterangan` menggunakan regex
2. Query langsung ke tabel `mulai_nabung` dengan ID yang diekstrak
3. Ambil `jenis_tabungan` yang benar untuk transaksi spesifik tersebut
4. Jika ekstrak gagal, fallback dengan timestamp matching

**Query Baru (BENAR):**
```php
// Extract mulai_nabung ID from keterangan
if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
    $mulai_nabung_id = intval($matches[1]);
    
    // Query langsung ke mulai_nabung dengan ID spesifik
    $sql_detail = "SELECT jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1";
    $stmt_detail = $connect->prepare($sql_detail);
    $stmt_detail->bind_param('i', $mulai_nabung_id);
    $stmt_detail->execute();
    $res = $stmt_detail->get_result();
    if ($res && $res->num_rows > 0) {
        $row_detail = $res->fetch_assoc();
        $jenis_tabungan = $row_detail['jenis_tabungan'];  // ✓ CORRECT!
    }
}
```

## Testing
Test hasil fix menunjukkan:

```
✅ TX #299 | Jenis Tabungan: Tabungan Qurban | Status: approved
❌ TX #298 | Jenis Tabungan: Tabungan Reguler | Status: rejected   ← BENAR!
✅ TX #301 | Jenis Tabungan: Tabungan Reguler | Status: approved
❌ TX #300 | Jenis Tabungan: Tabungan Reguler | Status: rejected
```

Transaksi #298 sekarang menampilkan **Tabungan Reguler** ✓

## File yang Diubah
- `c:\xampp\htdocs\gas\gas_web\flutter_api\get_riwayat_transaksi.php` - Memperbaiki query untuk setoran dan penarikan

## Backward Compatibility
✓ Kompatibel dengan data existing
✓ Tidak memerlukan perubahan database schema
✓ Fallback mechanism untuk keterangan lama

## Cara User Melihat Fix
1. Buka halaman "Riwayat Transaksi" atau "Notifikasi"
2. Buka detail transaksi yang memiliki multiple jenis_tabungan dari user sama
3. Jenis Tabungan sekarang akan menampilkan nilai yang benar sesuai transaksi tersebut
