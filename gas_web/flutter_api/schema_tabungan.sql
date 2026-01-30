-- =====================================================
-- SQL: Tabel tabungan_masuk dan tabungan_keluar
-- Database: tabungan
-- =====================================================

-- Tabel: tabungan_masuk
-- Menyimpan data setoran/pemasukan tabungan
CREATE TABLE IF NOT EXISTS `tabungan_masuk` (
  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna` BIGINT NOT NULL,
  `id_jenis_tabungan` BIGINT NOT NULL,
  `jumlah` INT NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign Keys
  CONSTRAINT `fk_tabungan_masuk_pengguna` FOREIGN KEY (`id_pengguna`) 
    REFERENCES `pengguna`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tabungan_masuk_jenis` FOREIGN KEY (`id_jenis_tabungan`) 
    REFERENCES `jenis_tabungan`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Indexes untuk query cepat
  INDEX `idx_id_pengguna` (`id_pengguna`),
  INDEX `idx_id_jenis_tabungan` (`id_jenis_tabungan`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_pengguna_jenis` (`id_pengguna`, `id_jenis_tabungan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel: tabungan_keluar
-- Menyimpan data penarikan/pengeluaran tabungan
CREATE TABLE IF NOT EXISTS `tabungan_keluar` (
  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna` BIGINT NOT NULL,
  `id_jenis_tabungan` BIGINT NOT NULL,
  `jumlah` INT NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Foreign Keys
  CONSTRAINT `fk_tabungan_keluar_pengguna` FOREIGN KEY (`id_pengguna`) 
    REFERENCES `pengguna`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tabungan_keluar_jenis` FOREIGN KEY (`id_jenis_tabungan`) 
    REFERENCES `jenis_tabungan`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  
  -- Indexes untuk query cepat
  INDEX `idx_id_pengguna` (`id_pengguna`),
  INDEX `idx_id_jenis_tabungan` (`id_jenis_tabungan`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_pengguna_jenis` (`id_pengguna`, `id_jenis_tabungan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
