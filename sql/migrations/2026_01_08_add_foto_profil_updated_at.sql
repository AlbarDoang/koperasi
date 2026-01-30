-- Add foto_profil_updated_at to pengguna to track image changes
ALTER TABLE pengguna
  ADD COLUMN foto_profil_updated_at INT(11) UNSIGNED NULL DEFAULT NULL AFTER foto_profil;

-- Optional: index for quick ordering/queries
ALTER TABLE pengguna
  ADD INDEX idx_foto_profil_updated_at (foto_profil_updated_at);
