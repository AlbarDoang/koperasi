-- Migration: create pinjaman_kredit and pinjaman_kredit_log
-- Run this in your development database (MySQL)

CREATE TABLE IF NOT EXISTS `pinjaman_kredit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pengguna` BIGINT UNSIGNED NOT NULL,
  `nama_barang` VARCHAR(255) NOT NULL,
  `harga` BIGINT UNSIGNED NOT NULL,
  `dp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `pokok` BIGINT UNSIGNED NOT NULL,
  `tenor` INT UNSIGNED NOT NULL,
  `cicilan_per_bulan` BIGINT UNSIGNED NOT NULL,
  `total_bayar` BIGINT UNSIGNED NOT NULL,
  `link_bukti_harga` VARCHAR(1024) DEFAULT NULL,
  `foto_barang` VARCHAR(1024) DEFAULT NULL,
  `link_bukti_harga` VARCHAR(1024) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','cancelled','berjalan','lunas') NOT NULL DEFAULT 'pending',
  `catatan_admin` TEXT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`id_pengguna`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log table to record status transitions and admin notes (immutable audit trail)
CREATE TABLE IF NOT EXISTS `pinjaman_kredit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pinjaman_id` BIGINT UNSIGNED NOT NULL,
  `previous_status` VARCHAR(32) NOT NULL,
  `new_status` VARCHAR(32) NOT NULL,
  `changed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`pinjaman_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Safety note: this migration only creates new tables and does not touch existing pinjaman_biasa or pinjaman tables.
