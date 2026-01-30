# Quick Reference: Database Tables Relationship

## 🗂️ Tabel Utama Sistem Tabungan

### 1. **pengguna** (User Wallet)
```
┌─────────────────────────────────┐
│         pengguna                │
├─────────────────────────────────┤
│ id (PK)                         │
│ nama_lengkap                    │
│ no_hp                           │
│ saldo (💰 saldo bebas/free)     │◄─── SALDO UTAMA USER
│ status                          │
│ created_at, updated_at          │
└─────────────────────────────────┘
```

### 2. **jenis_tabungan** (Savings Types)
```
┌─────────────────────────────────┐
│     jenis_tabungan              │
├─────────────────────────────────┤
│ id (PK)                         │
│ nama_jenis                      │
│   - "Tabungan Reguler"          │
│   - "Tabungan Lebaran"          │
│   - "Tabungan Umroh"            │
│   - etc                         │
│ description                     │
│ created_at, updated_at          │
└─────────────────────────────────┘
```

### 3. **mulai_nabung** (Top-up Request)
```
┌─────────────────────────────────────┐
│       mulai_nabung (REQUEST)        │
├─────────────────────────────────────┤
│ id_mulai_nabung (PK)                │
│ id_tabungan (user identifier)      │
│ nomor_hp                            │
│ nama_pengguna                       │
│ jumlah (nominal yang di-top-up)    │
│ jenis_tabungan                      │
│ status (enum):                      │
│   - 'menunggu_penyerahan'           │◄─ User belum bayar
│   - 'menunggu_admin'                │◄─ User sudah bayar, tunggu verifikasi
│   - 'berhasil'                      │◄─ Admin approve (→ update tabungan_masuk)
│   - 'ditolak'                       │◄─ Admin reject
│ created_at, updated_at              │
└─────────────────────────────────────┘

⚠️ Ini tabel REQUEST/LOG, BUKAN saldo actual
```

### 4. **tabungan_masuk** (Deposits Ledger) ⭐ IMPORTANT
```
┌──────────────────────────────────────┐
│     tabungan_masuk (LEDGER)          │
├──────────────────────────────────────┤
│ id (PK)                              │
│ id_pengguna (FK → pengguna.id)       │
│ id_jenis_tabungan (FK → jenis)       │
│ jumlah (💰 SALDO per jenis)          │◄─── PER-JENIS BALANCE
│ keterangan                           │
│ status ('berhasil', 'pending', etc)  │
│ created_at, updated_at               │
└──────────────────────────────────────┘

✅ Ini menyimpan ACTUAL BALANCE per jenis
✅ Di-update ketika mulai_nabung status='berhasil'
✅ Di-kurang ketika tabungan_keluar di-approve
```

### 5. **tabungan_keluar** (Withdrawal Request) ⭐ IMPORTANT
```
┌──────────────────────────────────────┐
│    tabungan_keluar (REQUEST)         │
├──────────────────────────────────────┤
│ id (PK)                              │
│ id_pengguna (FK → pengguna.id)       │
│ id_jenis_tabungan (FK → jenis)       │
│ jumlah (nominal pencairan)           │
│ status (enum):                       │
│   - 'pending'    ◄─ Request masuk    │
│   - 'approved'   ◄─ Admin approve    │
│   - 'rejected'   ◄─ Admin reject     │
│   - 'completed'  ◄─ Dana dicairkan   │
│ keterangan                           │
│ created_at, updated_at               │
└──────────────────────────────────────┘

✅ Ini tabel REQUEST, saldo sudah dikurang di CF
✅ Kalau di-reject, saldo di-restore
```

---

## 🔗 Hubungan Antar Tabel

```
pengguna
   │
   ├─→ saldo (💰 free balance - dashboard)
   │
   └─→ tabungan_masuk ┐ (per-jenis ledger)
       ├─ id_pengguna │
       ├─ id_jenis_tabungan
       ├─ jumlah (TOTAL per jenis)
       └─→ jenis_tabungan
                │
                └─→ Contoh saldo: 
                    {Tabungan Reguler: 500rb, Tabungan Lebaran: 200rb}
   
   └─→ tabungan_keluar (request only)
       ├─ id_pengguna
       └─ id_jenis_tabungan


FLOW:
  1. User mulai nabung Rp1jt → mulai_nabung.status='menunggu_admin'
  2. Admin approve → tabungan_masuk.jumlah += 1jt
  3. User cairkan 500rb → tabungan_keluar created, tabungan_masuk -= 500rb
  4. Admin approve pencairan → tabungan_keluar.status='approved'
```

---

## 📊 Contoh Data

### Scenario: User "Budi" dengan 2 jenis tabungan

**pengguna table:**
```
id | nama_lengkap | no_hp      | saldo (free)
---|--------------|------------|----------
3  | Budi Santoso | 0812xxx    | 2.000.000
```

**tabungan_masuk table:**
```
id | id_pengguna | id_jenis_tabungan | jumlah    | status
---|-------------|-------------------|-----------|--------
10 | 3           | 1 (Reguler)       | 5.000.000 | berhasil
11 | 3           | 2 (Lebaran)       | 3.000.000 | berhasil
```

**tabungan_keluar table:**
```
id | id_pengguna | id_jenis_tabungan | jumlah    | status
---|-------------|-------------------|-----------|----------
5  | 3           | 1 (Reguler)       | 1.000.000 | pending
```

**Interpretasi:**
- Budi punya saldo bebas: Rp2.000.000 (di dashboard)
- Saldo Tabungan Reguler: Rp5.000.000
- Saldo Tabungan Lebaran: Rp3.000.000
- Ada request pencairan Rp1.000.000 dari Tabungan Reguler (pending)
- Total tabungan: Rp8.000.000

---

## ✅ API Files Affected

| File | Fungsi | Status |
|------|--------|--------|
| `buat_mulai_nabung.php` | Create top-up request → INSERT mulai_nabung | ✅ Fixed |
| `update_status_mulai_nabung.php` | Update status to menunggu_admin | ✅ Fixed |
| `admin_verifikasi_mulai_nabung.php` | Admin approve → UPDATE tabungan_masuk + pengguna.saldo | ✅ Fixed |
| `cairkan_tabungan.php` | Create withdrawal request → INSERT tabungan_keluar | ✅ Already OK |
| `approve_penarikan.php` | Admin approve/reject withdrawal | ✅ Already OK |
| `get_saldo_tabungan.php` | Get per-jenis balance from tabungan_masuk | ✅ Already OK |

---

## 🚀 Testing Checklist

- [ ] Buat top-up request (mulai nabung) - harus masuk ke `mulai_nabung` table
- [ ] Approve top-up di admin - harus UPDATE `tabungan_masuk` dan `pengguna.saldo`
- [ ] Cek saldo di app - harus muncul di "Halaman Tabungan"
- [ ] Buat pencairan request - harus masuk ke `tabungan_keluar` table
- [ ] Approve pencairan di admin - harus UPDATE `tabungan_keluar` status
- [ ] Cek saldo setelah pencairan - harus berkurang di `tabungan_masuk`
