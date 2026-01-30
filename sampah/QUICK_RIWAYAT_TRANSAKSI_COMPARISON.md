# 🎯 QUICK REFERENCE: 3 API Riwayat Transaksi

```
╔════════════════════════════════════════════════════════════════════════════╗
║                   MANA ENDPOINT YANG MEMBACA WITHDRAWAL?                   ║
╚════════════════════════════════════════════════════════════════════════════╝
```

## Tabel Perbandingan

```
┌────────────────────────────────────────┬───────┬──────────┬──────────┐
│ API ENDPOINT                           │ MASUK │ KELUAR   │ STATUS   │
├────────────────────────────────────────┼───────┼──────────┼──────────┤
│ get_riwayat_transaksi.php              │  ❌   │   ❌     │ ⚠️ Limited
│ (tabel: transaksi saja)                │       │          │          │
├────────────────────────────────────────┼───────┼──────────┼──────────┤
│ get_riwayat_tabungan.php               │  ✅   │   ❌     │ ❌ BROKEN
│ (MASALAH: hanya masuk, tidak keluar!)  │       │          │          │
├────────────────────────────────────────┼───────┼──────────┼──────────┤
│ get_history_by_jenis.php               │  ✅   │   ✅     │ ✅ OK
│ (lengkap, semua sumber)                │       │          │          │
└────────────────────────────────────────┴───────┴──────────┴──────────┘
```

---

## 1️⃣ get_riwayat_transaksi.php

**Path:** `gas_web/flutter_api/get_riwayat_transaksi.php`

**Baca dari:**
```
Tabel: transaksi
├─ jenis_transaksi: setoran, penarikan, transfer_masuk, transfer_keluar
├─ filter: id_anggota + status='approved'
└─ format: single table query
```

**Contoh Response:**
```json
{
  "data": [
    {
      "jenis_transaksi": "setoran",
      "jumlah": 100000,
      "keterangan": "Setoran manual",
      "created_at": "2026-01-25"
    }
  ]
}
```

**Digunakan:** `EventDb.getRiwayatTransaksi()` di Flutter

---

## 2️⃣ get_riwayat_tabungan.php 🚨 PROBLEM

**Path:** `gas_web/flutter_api/get_riwayat_tabungan.php`

**Baca dari:**
```
Step 1: Tabel mulai_nabung
        ├─ id_tabungan
        ├─ jenis_tabungan
        ├─ jumlah ✅ MASUK
        └─ status='berhasil'

Step 2: Tabel tabungan_masuk
        ├─ id_pengguna
        ├─ id_jenis_tabungan
        ├─ jumlah ✅ MASUK
        └─ created_at

Step 3: ❌ TIDAK ADA TABUNGAN_KELUAR!
```

**Yang Hilang:**
```
❌ NOT IMPLEMENTED:
   Tabel tabungan_keluar
   ├─ id_pengguna
   ├─ id_jenis_tabungan
   ├─ jumlah ❌ KELUAR (WITHDRAWAL)
   └─ created_at
```

**Contoh Response (sebelum fix):**
```json
{
  "data": [
    {
      "tanggal": "2026-01-25",
      "jenis_tabungan": "1",
      "jumlah": 50000,
      "sumber": "tabungan_masuk"
    }
    // ❌ WITHDRAWAL TIDAK ADA!
  ]
}
```

**Digunakan:** `EventDb.getRiwayatTabungan()` di Flutter

**Status:** ❌ **INCOMPLETE - PERLU FIX**

---

## 3️⃣ get_history_by_jenis.php ✅ OK

**Path:** `gas_web/flutter_api/get_history_by_jenis.php`

**Baca dari:**
```
Step 1: Tabel tabungan_masuk
        ├─ jumlah ✅ MASUK
        └─ created_at

Step 2: Tabel tabungan_keluar
        ├─ jumlah ✅ KELUAR (WITHDRAWAL)
        └─ created_at

Step 3: Tabel transaksi
        ├─ jenis_transaksi
        └─ semua jenis

Step 4: Tabel mulai_nabung (fallback)
        ├─ jumlah ✅ MASUK
        └─ status='berhasil'
```

**Contoh Response (lengkap):**
```json
{
  "data": [
    {
      "date": "2026-01-25",
      "title": "Cairkan Tabungan",
      "amount": -30000,  // ✅ WITHDRAWAL (negative)
      "type": "cairkan"
    },
    {
      "date": "2026-01-24",
      "title": "Setoran Manual",
      "amount": 50000,   // ✅ MASUK
      "type": "topup"
    }
  ]
}
```

**Digunakan:** `EventDb.getHistoryByJenis()` di Flutter

**Status:** ✅ **COMPLETE - SUDAH OK**

---

## 📊 Mana yang Digunakan?

Di `gas_mobile/lib/event/event_db.dart`:

```dart
// 1. getRiwayatTransaksi()
// → Panggil: get_riwayat_transaksi.php
// → Baca: tabel transaksi saja
// → Withdrawal: Tergantung apakah di transaksi atau tidak

// 2. getRiwayatTabungan() ⚠️ PROBLEM
// → Panggil: get_riwayat_tabungan.php
// → Baca: mulai_nabung + tabungan_masuk SAJA
// → Withdrawal: ❌ TIDAK MUNCUL

// 3. getHistoryByJenis() ✅ OK
// → Panggil: get_history_by_jenis.php
// → Baca: tabungan_masuk + tabungan_keluar ✅
// → Withdrawal: ✅ MUNCUL LENGKAP
```

---

## 🔧 Solusi Cepat

### Opsi A: Fix get_riwayat_tabungan.php (Recommended)
1. Buka `gas_web/flutter_api/get_riwayat_tabungan.php`
2. Cari: `$stmt->close();` setelah STEP 2 (tabungan_masuk)
3. Tambah STEP 3: Query `tabungan_keluar` (copy dari get_history_by_jenis.php)
4. Test: `php tmp_test_setor_flow.php`

**Effort:** 10 menit | **Risk:** Low

---

### Opsi B: Gunakan get_history_by_jenis.php
- Gunakan endpoint yang sudah benar ini
- Tidak perlu fix, sudah OK
- Hanya perlu update Flutter app untuk call endpoint yang tepat

**Effort:** 5 menit | **Risk:** None

---

## ✅ Kesimpulan

| Pertanyaan | Jawaban |
|-----------|---------|
| **Apakah withdrawal masuk ke riwayat?** | ❌ **TIDAK** (di get_riwayat_tabungan.php) |
| **Kenapa withdrawal tidak muncul?** | ❌ **Tidak ada query ke tabungan_keluar** |
| **File mana yang bermasalah?** | `gas_web/flutter_api/get_riwayat_tabungan.php` |
| **File mana yang OK?** | `gas_web/flutter_api/get_history_by_jenis.php` |
| **Solusi?** | Tambah query tabungan_keluar atau ganti endpoint |

---

## 📁 File Reference

### PHP API Files:
```
c:\xampp\htdocs\gas\gas_web\flutter_api\
├─ get_riwayat_transaksi.php      ← Limited
├─ get_riwayat_tabungan.php       ← ❌ BROKEN (hanya masuk)
└─ get_history_by_jenis.php       ← ✅ FIXED (masuk + keluar)
```

### Flutter Config:
```
c:\xampp\htdocs\gas\gas_mobile\lib\
├─ config\api.dart                 ← Endpoint definitions
└─ event\event_db.dart             ← API calls (lines 880, 1929, 1964)
```

### Documentation:
```
c:\xampp\htdocs\gas\
├─ ANALISIS_RIWAYAT_TRANSAKSI_2026_01_25.md    ← Full analysis
└─ FIX_RIWAYAT_TABUNGAN_WITHDRAWAL.md          ← Implementation guide
```

---

**Diproduksi:** 25 Januari 2026
**Versi:** 1.0 QUICK REFERENCE
