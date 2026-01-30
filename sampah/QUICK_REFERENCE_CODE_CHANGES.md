## 🔧 QUICK REFERENCE - EXACT CODE CHANGES

### FILE 1: `/gas_web/flutter_api/setor_manual_admin.php`

#### CHANGE 1 (Line 176-186): Add "Tabungan" Prefix
```php
// FIND THIS:
    } else {
        $row_jenis = $res_jenis->fetch_assoc();
        $nama_jenis_tabungan = $row_jenis['nama_jenis'] ?? 'Tabungan Reguler';
    }
    $stmt_jenis->close();

// REPLACE WITH:
    } else {
        $row_jenis = $res_jenis->fetch_assoc();
        $nama_jenis_tabungan = $row_jenis['nama_jenis'] ?? 'Tabungan Reguler';
    }
    $stmt_jenis->close();

    // Ensure nama_jenis_tabungan has "Tabungan " prefix for display
    if (strpos($nama_jenis_tabungan, 'Tabungan') === false) {
        $nama_jenis_tabungan = 'Tabungan ' . $nama_jenis_tabungan;
    }
```

#### CHANGE 2 (Line 375): Fix Type Mismatch in mulai_nabung bind_param (WITH sumber column)
```php
// FIND THIS:
            $stmt_mulai->bind_param('isssiisss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $sumber_mulai, $now_datetime);

// REPLACE WITH:
            $stmt_mulai->bind_param('isssdssss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $sumber_mulai, $now_datetime);
```

#### CHANGE 3 (Line 387): Fix Type Mismatch in mulai_nabung bind_param (WITHOUT sumber column)
```php
// FIND THIS:
            $stmt_mulai->bind_param('isssiiss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $now_datetime);

// REPLACE WITH:
            $stmt_mulai->bind_param('isssdsss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $now_datetime);
```

**Key Point:** Change `i` (integer) to `d` (double) for `$jumlah` parameter

---

### FILE 2: `/gas_web/login/admin/masuk/index.php`

#### CHANGE: Add "Tabungan" Prefix to Dropdown Options (Line 854-859)

```javascript
// FIND THIS:
            response.data.forEach(function(jenis) {
              var $option = $('<option></option>')
                .attr('value', jenis.id)
                .text(jenis.nama_jenis || jenis.nama || 'Unknown');
              
              $select.append($option);
            });

// REPLACE WITH:
            response.data.forEach(function(jenis) {
              var displayText = jenis.nama_jenis || jenis.nama || 'Unknown';
              // Add "Tabungan " prefix if not already present
              if (!displayText.toLowerCase().startsWith('tabungan')) {
                displayText = 'Tabungan ' + displayText;
              }
              var $option = $('<option></option>')
                .attr('value', jenis.id)
                .text(displayText);
              
              $select.append($option);
            });
```

---

### FILE 3: `/gas_mobile/lib/page/riwayat.dart`

#### CHANGE: Replace _load() Function (Line 36-165)

```dart
// FIND THIS ENTIRE FUNCTION:
  Future<void> _load() async {
    loading = true;
    setState(() {});
    final prefs = await SharedPreferences.getInstance();
    final txns = prefs.getString('transactions');
    final pengajuan = prefs.getString('pengajuan_list');

    List<Map<String, dynamic>> list = [];
    if (txns != null) {
      final parsed = jsonDecode(txns) as List;
      for (var e in parsed) {
        // ... existing code ...
      }
    }
    if (pengajuan != null) {
      final parsed = jsonDecode(pengajuan) as List;
      for (var e in parsed) {
        final m = Map<String, dynamic>.from(e);
        m['type'] = 'pinjaman';
        list.add(m);
      }
    }
    // ... rest of function ...
  }

// REPLACE WITH:
  Future<void> _load() async {
    loading = true;
    setState(() {});
    
    List<Map<String, dynamic>> list = [];
    
    // Get user ID from controller
    final userCtrl = Get.find<UserController>();
    final userId = userCtrl.data.value?.id ?? 0;
    
    // STEP 1: Fetch fresh data from API get_riwayat_transaksi.php
    if (userId > 0) {
      try {
        final resp = await http
            .post(
              Uri.parse('${Api.baseUrl}/get_riwayat_transaksi.php'),
              body: {'id_pengguna': userId.toString()},
            )
            .timeout(const Duration(seconds: 10));
        
        if (resp.statusCode == 200) {
          final json = jsonDecode(resp.body);
          if (json['success'] == true && json['data'] != null) {
            final transactions = (json['data'] as List).cast<Map<String, dynamic>>();
            for (var txn in transactions) {
              final item = Map<String, dynamic>.from(txn);
              
              // MAP jenis_transaksi ke type
              if (item.containsKey('jenis_transaksi')) {
                final jenisTrans = (item['jenis_transaksi'] ?? '').toString().toLowerCase();
                
                if (jenisTrans == 'setoran') {
                  item['type'] = 'topup';
                  item['title'] = 'Setoran Tabungan';
                } else if (jenisTrans == 'penarikan' || jenisTrans == 'transfer_keluar') {
                  item['type'] = 'transfer';
                  item['title'] = 'Penarikan Tabungan';
                } else if (jenisTrans == 'transfer_masuk') {
                  item['type'] = 'transfer_masuk';
                  item['title'] = 'Transfer Masuk';
                } else {
                  item['type'] = 'lainnya';
                  item['title'] = jenisTrans;
                }
              }
              
              // MAP jumlah ke amount jika belum ada
              if (!item.containsKey('amount') && item.containsKey('jumlah')) {
                item['amount'] = item['jumlah'];
              }
              
              // STATUS approved = success
              if (item['status'] == 'approved') {
                item['status'] = 'success';
              }
              
              list.add(item);
            }
          }
        }
      } catch (e) {
        // Fallback ke SharedPreferences jika API gagal
        print('[RiwayatTransaksi] API error: $e');
      }
    }
    
    // STEP 2: Jika API gagal/kosong, fallback ke SharedPreferences
    if (list.isEmpty) {
      final prefs = await SharedPreferences.getInstance();
      final txns = prefs.getString('transactions');
      
      if (txns != null) {
        final parsed = jsonDecode(txns) as List;
        for (var e in parsed) {
          final item = Map<String, dynamic>.from(e);
          
          // MAP data dari tabel transaksi yang baru
          // Jika field 'type' belum ada, generate dari jenis_transaksi
          if (!item.containsKey('type') && item.containsKey('jenis_transaksi')) {
            final jenisTrans = (item['jenis_transaksi'] ?? '').toString().toLowerCase();
            
            if (jenisTrans == 'setoran') {
              item['type'] = 'topup';
              item['title'] = 'Setoran Tabungan';
            } else if (jenisTrans == 'penarikan' || jenisTrans == 'transfer_keluar') {
              item['type'] = 'transfer';
              item['title'] = 'Penarikan Tabungan';
            } else if (jenisTrans == 'transfer_masuk') {
              item['type'] = 'transfer_masuk';
              item['title'] = 'Transfer Masuk';
            } else {
              item['type'] = 'lainnya';
              item['title'] = jenisTrans;
            }
          }
          
          // MAP jumlah ke amount jika belum ada
          if (!item.containsKey('amount') && item.containsKey('jumlah')) {
            item['amount'] = item['jumlah'];
          }
          
          // STATUS approved = success (untuk data dari transaksi table)
          if (item['status'] == 'approved') {
            item['status'] = 'success';
          }
          
          list.add(item);
        }
      }
    }
    
    // STEP 3: Tambah pengajuan pinjaman dari SharedPreferences jika ada
    final prefs = await SharedPreferences.getInstance();
    final pengajuan = prefs.getString('pengajuan_list');
    if (pengajuan != null) {
      final parsed = jsonDecode(pengajuan) as List;
      for (var e in parsed) {
        final m = Map<String, dynamic>.from(e);
        m['type'] = 'pinjaman';
        list.add(m);
      }
    }

    // ... rest of function (keep existing code) ...
```

---

## ✅ VALIDATION

After changes, verify:

1. **setor_manual_admin.php:**
   - Line 176-186: Contains `if (strpos($nama_jenis_tabungan, 'Tabungan') === false)`
   - Line 375: Contains `'isssdssss'` (with d for jumlah)
   - Line 387: Contains `'isssdsss'` (with d for jumlah)

2. **admin/masuk/index.php:**
   - Line 854-859: Contains `displayText = 'Tabungan ' + displayText;`

3. **riwayat.dart:**
   - Line 36-165: Function starts with API call to `get_riwayat_transaksi.php`
   - Contains mapping for `jenis_transaksi='setoran'` to `'topup'`

---

**Last Updated:** 2026-01-26  
**Verified:** YES ✅
