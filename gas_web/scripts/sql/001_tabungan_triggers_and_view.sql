-- SQL script: 001_tabungan_triggers_and_view.sql
-- Purpose: Create t_keluar table (compat), view v_saldo_tabungan, triggers to enforce non-negative balances,
-- and audit table + indices. SAFE to run multiple times (uses IF NOT EXISTS / DROP TRIGGER IF EXISTS patterns).

-- IMPORTANT: Run this file in staging first and create proper backups. Triggers use SIGNAL SQLSTATE '45000'.

-- 1) Ensure t_keluar exists so admin UI expecting it works
CREATE TABLE IF NOT EXISTS t_keluar (
  id_keluar BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  no_keluar VARCHAR(128) NOT NULL UNIQUE,
  nama VARCHAR(255) NULL,
  id_tabungan VARCHAR(255) NULL,
  kelas VARCHAR(128) DEFAULT '-',
  tanggal DATE NULL,
  jumlah DECIMAL(30,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  keterangan TEXT NULL,
  status VARCHAR(32) DEFAULT 'pending',
  approved_by BIGINT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id_keluar),
  INDEX idx_t_keluar_no (no_keluar),
  INDEX idx_t_keluar_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Create saldo audit table (optional but recommended)
CREATE TABLE IF NOT EXISTS saldo_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengguna BIGINT NOT NULL,
  id_jenis_tabungan INT DEFAULT 1,
  event_type VARCHAR(64) NOT NULL,
  message TEXT,
  amount DECIMAL(30,2) DEFAULT 0,
  meta JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Indices for performance
ALTER TABLE tabungan_masuk ADD INDEX IF NOT EXISTS idx_masuk_user_jenis (id_pengguna, id_jenis_tabungan);
ALTER TABLE tabungan_keluar ADD INDEX IF NOT EXISTS idx_keluar_user_jenis (id_pengguna, id_jenis_tabungan);

-- Note: MySQL < 8 does not support ADD INDEX IF NOT EXISTS; the statement above may error on older versions.
-- If error occurs, run safer statements separately in your environment.

-- 4) Create or replace view v_saldo_tabungan (per user x jenis)
DROP VIEW IF EXISTS v_saldo_tabungan;
CREATE OR REPLACE VIEW v_saldo_tabungan AS
SELECT
  p.id AS id_pengguna,
  jt.id AS id_jenis_tabungan,
  ( COALESCE(m.total_masuk, 0) - COALESCE(k.total_keluar, 0) ) AS saldo
FROM pengguna p
CROSS JOIN jenis_tabungan jt
LEFT JOIN (
  SELECT id_pengguna, IFNULL(id_jenis_tabungan,1) AS id_jenis_tabungan, SUM(jumlah) AS total_masuk
  FROM tabungan_masuk
  GROUP BY id_pengguna, IFNULL(id_jenis_tabungan,1)
) m ON m.id_pengguna = p.id AND m.id_jenis_tabungan = jt.id
LEFT JOIN (
  SELECT id_pengguna, IFNULL(id_jenis_tabungan,1) AS id_jenis_tabungan, SUM(jumlah) AS total_keluar
  FROM tabungan_keluar
  GROUP BY id_pengguna, IFNULL(id_jenis_tabungan,1)
) k ON k.id_pengguna = p.id AND k.id_jenis_tabungan = jt.id;

-- 5) Triggers for tabungan_keluar --- enforce non-negative balances
-- BEFORE INSERT
DROP TRIGGER IF EXISTS trg_tab_keluar_before_insert;
DELIMITER $$
CREATE TRIGGER trg_tab_keluar_before_insert
BEFORE INSERT ON tabungan_keluar
FOR EACH ROW
BEGIN
  DECLARE total_masuk DECIMAL(30,2) DEFAULT 0;
  DECLARE total_keluar DECIMAL(30,2) DEFAULT 0;
  DECLARE saldo_now DECIMAL(30,2) DEFAULT 0;

  -- Validate amount
  IF NEW.jumlah IS NULL OR NEW.jumlah <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah penarikan harus lebih dari 0';
  END IF;

  SELECT IFNULL(SUM(jumlah),0) INTO total_masuk
  FROM tabungan_masuk
  WHERE id_pengguna = NEW.id_pengguna AND IFNULL(id_jenis_tabungan,1) = IFNULL(NEW.id_jenis_tabungan,1);

  SELECT IFNULL(SUM(jumlah),0) INTO total_keluar
  FROM tabungan_keluar
  WHERE id_pengguna = NEW.id_pengguna AND IFNULL(id_jenis_tabungan,1) = IFNULL(NEW.id_jenis_tabungan,1);

  SET saldo_now = total_masuk - total_keluar;

  IF NEW.jumlah > saldo_now THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('Saldo tabungan tidak mencukupi. Saldo tersedia: ', FORMAT(saldo_now,2));
  END IF;
END$$
DELIMITER ;

-- BEFORE UPDATE
DROP TRIGGER IF EXISTS trg_tab_keluar_before_update;
DELIMITER $$
CREATE TRIGGER trg_tab_keluar_before_update
BEFORE UPDATE ON tabungan_keluar
FOR EACH ROW
BEGIN
  DECLARE total_masuk DECIMAL(30,2) DEFAULT 0;
  DECLARE total_keluar DECIMAL(30,2) DEFAULT 0;
  DECLARE saldo_now DECIMAL(30,2) DEFAULT 0;
  DECLARE final_saldo DECIMAL(30,2) DEFAULT 0;

  IF NEW.jumlah IS NULL OR NEW.jumlah <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah penarikan harus lebih dari 0';
  END IF;

  SELECT IFNULL(SUM(jumlah),0) INTO total_masuk
  FROM tabungan_masuk
  WHERE id_pengguna = NEW.id_pengguna AND IFNULL(id_jenis_tabungan,1) = IFNULL(NEW.id_jenis_tabungan,1);

  SELECT IFNULL(SUM(jumlah),0) INTO total_keluar
  FROM tabungan_keluar
  WHERE id_pengguna = NEW.id_pengguna AND IFNULL(id_jenis_tabungan,1) = IFNULL(NEW.id_jenis_tabungan,1);

  SET saldo_now = total_masuk - total_keluar;

  SET final_saldo = saldo_now + OLD.jumlah - NEW.jumlah;

  IF final_saldo < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = CONCAT('Update dibatalkan: perubahan jumlah menyebabkan saldo negatif (sisa: ', FORMAT(final_saldo,2), ')');
  END IF;
END$$
DELIMITER ;

-- BEFORE DELETE
DROP TRIGGER IF EXISTS trg_tab_keluar_before_delete;
DELIMITER $$
CREATE TRIGGER trg_tab_keluar_before_delete
BEFORE DELETE ON tabungan_keluar
FOR EACH ROW
BEGIN
  -- If status column exists, only allow delete when status = 'pending'
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tabungan_keluar' AND COLUMN_NAME = 'status') = 1 THEN
    IF LOWER(IFNULL(OLD.status,'')) <> 'pending' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Penghapusan pencairan dilarang kecuali status = pending';
    END IF;
  ELSE
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Penghapusan pencairan dilarang (audit-only) - hubungi admin';
  END IF;
END$$
DELIMITER ;

-- EOF
