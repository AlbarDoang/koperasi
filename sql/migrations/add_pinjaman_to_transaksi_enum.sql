-- Migration: Add pinjaman_biasa and pinjaman_kredit to transaksi.jenis_transaksi enum
-- Date: 2026-02-11
-- Purpose: Allow pinjaman transactions to be stored in the central transaksi table
--          so that all transaction types share a single sequential id_transaksi

ALTER TABLE `transaksi` 
MODIFY COLUMN `jenis_transaksi` ENUM(
    'setoran',
    'penarikan',
    'transfer_masuk',
    'transfer_keluar',
    'pinjaman_biasa',
    'pinjaman_kredit'
) NOT NULL;
