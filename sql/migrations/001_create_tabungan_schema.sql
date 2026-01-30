-- Migration: CREATE tabungan system schema (safe, idempotent statements)
-- Note: adjust character set/collation and engine to your environment
-- Run as an ops user with sufficient privileges.

-- ------------------------------------------------------------
-- Table: pengguna (users / wallet)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengguna (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_lengkap VARCHAR(191) NOT NULL,
  no_hp VARCHAR(50) DEFAULT NULL,
  nis VARCHAR(50) DEFAULT NULL,
  saldo DECIMAL(20,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pengguna_nohp (no_hp),
  INDEX idx_pengguna_nis (nis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: jenis_tabungan (type of savings)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jenis_tabungan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_jenis VARCHAR(191) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tabungan_masuk (deposits)
-- - Per-jenis ledger entries (do NOT mutate pengguna.saldo here for modern schemas)
-- - status: 'berhasil' indicates the deposit is valid and counted
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tabungan_masuk (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT UNSIGNED NOT NULL,
  id_jenis_tabungan INT UNSIGNED NOT NULL,
  jumlah DECIMAL(20,2) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'berhasil',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tm_user_jenis (id_pengguna, id_jenis_tabungan),
  CONSTRAINT fk_tm_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_jenis FOREIGN KEY (id_jenis_tabungan) REFERENCES jenis_tabungan(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: tabungan_keluar (withdrawal requests)
-- - This table records requested and eventually approved withdrawals
-- - For compatibility, include a 'status' column when possible: 'pending','approved','rejected'
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tabungan_keluar (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT UNSIGNED NOT NULL,
  id_jenis_tabungan INT UNSIGNED NOT NULL,
  jumlah DECIMAL(20,2) NOT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  keterangan VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tk_user_jenis (id_pengguna, id_jenis_tabungan),
  INDEX idx_tk_status (status),
  CONSTRAINT fk_tk_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id) ON DELETE CASCADE,
  CONSTRAINT fk_tk_jenis FOREIGN KEY (id_jenis_tabungan) REFERENCES jenis_tabungan(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: transaksi (audit / legacy)
-- - Records actions for traceability
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transaksi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_keluar VARCHAR(120) DEFAULT NULL,
  nama VARCHAR(191) DEFAULT NULL,
  id_tabungan VARCHAR(100) DEFAULT NULL,
  kelas VARCHAR(50) DEFAULT NULL,
  kegiatan VARCHAR(100) DEFAULT NULL,
  jumlah_masuk DECIMAL(20,2) DEFAULT 0,
  jumlah_keluar DECIMAL(20,2) DEFAULT 0,
  tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
  petugas VARCHAR(100) DEFAULT NULL,
  kegiatan2 VARCHAR(255) DEFAULT NULL,
  ip VARCHAR(50) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: saldo_audit (optional audit table)
-- - Useful for tracking wallet mutations and debugging
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS saldo_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT UNSIGNED NOT NULL,
  id_jenis_tabungan INT UNSIGNED DEFAULT NULL,
  event_type VARCHAR(64) NOT NULL,
  message VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(20,2) DEFAULT 0,
  meta JSON DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sa_user (id_pengguna),
  CONSTRAINT fk_sa_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TRIGGERS & VIEW
-- ------------------------------------------------------------

-- BEFORE INSERT trigger: prevent negative saldo
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_tabungan_keluar_before_insert
BEFORE INSERT ON tabungan_keluar
FOR EACH ROW
BEGIN
  -- Compute total_in and total_out for this user & jenis
  DECLARE total_in DECIMAL(20,2) DEFAULT 0;
  DECLARE total_out DECIMAL(20,2) DEFAULT 0;

  SELECT COALESCE(SUM(jumlah),0) INTO total_in
  FROM tabungan_masuk
  WHERE id_pengguna = NEW.id_pengguna
    AND id_jenis_tabungan = NEW.id_jenis_tabungan
    AND (status IS NULL OR status = 'berhasil');

  SELECT COALESCE(SUM(jumlah),0) INTO total_out
  FROM tabungan_keluar
  WHERE id_pengguna = NEW.id_pengguna
    AND id_jenis_tabungan = NEW.id_jenis_tabungan;

  IF NEW.jumlah > (total_in - total_out) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo tabungan tidak mencukupi';
  END IF;
END$$
DELIMITER ;

-- BEFORE UPDATE trigger: prevent updates causing negative saldo
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_tabungan_keluar_before_update
BEFORE UPDATE ON tabungan_keluar
FOR EACH ROW
BEGIN
  DECLARE total_in DECIMAL(20,2) DEFAULT 0;
  DECLARE total_out DECIMAL(20,2) DEFAULT 0;
  DECLARE final_saldo DECIMAL(20,2);

  SELECT COALESCE(SUM(jumlah),0) INTO total_in
  FROM tabungan_masuk
  WHERE id_pengguna = NEW.id_pengguna
    AND id_jenis_tabungan = NEW.id_jenis_tabungan
    AND (status IS NULL OR status = 'berhasil');

  SELECT COALESCE(SUM(jumlah),0) INTO total_out
  FROM tabungan_keluar
  WHERE id_pengguna = OLD.id_pengguna
    AND id_jenis_tabungan = OLD.id_jenis_tabungan;

  SET final_saldo = total_in - (total_out - OLD.jumlah + NEW.jumlah);
  IF final_saldo < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo tabungan tidak mencukupi';
  END IF;
END$$
DELIMITER ;

-- BEFORE DELETE trigger: optional protection against deleting processed rows
DELIMITER $$
CREATE DEFINER = CURRENT_USER TRIGGER trg_tabungan_keluar_before_delete
BEFORE DELETE ON tabungan_keluar
FOR EACH ROW
BEGIN
  IF OLD.status IS NOT NULL AND LOWER(OLD.status) <> 'pending' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Menghapus penarikan yang sudah diproses tidak diizinkan';
  END IF;
END$$
DELIMITER ;

-- VIEW: v_saldo_tabungan (real-time saldo per user x jenis)
CREATE OR REPLACE VIEW v_saldo_tabungan AS
SELECT
  u.id AS id_pengguna,
  jt.id AS id_jenis_tabungan,
  COALESCE(m.total_in, 0) - COALESCE(k.total_out, 0) AS saldo
FROM pengguna u
CROSS JOIN jenis_tabungan jt
LEFT JOIN (
  SELECT id_pengguna, id_jenis_tabungan, SUM(jumlah) AS total_in
  FROM tabungan_masuk
  WHERE (status IS NULL OR status = 'berhasil')
  GROUP BY id_pengguna, id_jenis_tabungan
) m ON m.id_pengguna = u.id AND m.id_jenis_tabungan = jt.id
LEFT JOIN (
  SELECT id_pengguna, id_jenis_tabungan, SUM(jumlah) AS total_out
  FROM tabungan_keluar
  GROUP BY id_pengguna, id_jenis_tabungan
) k ON k.id_pengguna = u.id AND k.id_jenis_tabungan = jt.id;

-- ------------------------------------------------------------
-- Down scripts (for rollback) - use with care!
-- ------------------------------------------------------------
-- DROP VIEW IF EXISTS v_saldo_tabungan;
-- DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_insert;
-- DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_update;
-- DROP TRIGGER IF EXISTS trg_tabungan_keluar_before_delete;
-- DROP TABLE IF EXISTS saldo_audit, transaksi, tabungan_keluar, tabungan_masuk, jenis_tabungan, pengguna;

-- End of migration
