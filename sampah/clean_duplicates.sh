#!/bin/bash
# Clean duplicate notifications using MySQL

mysql -u root tabungan <<EOF
-- Delete duplicate notifications, keeping only the first one (oldest)
DELETE FROM notifikasi 
WHERE id NOT IN (
    SELECT first_id FROM (
        SELECT MIN(id) as first_id
        FROM notifikasi
        WHERE type IS NOT NULL
        GROUP BY id_pengguna, type, title, message
    ) as t
);

-- Show result
SELECT 'Cleanup completed. Remaining notifications:' as status;
SELECT COUNT(*) as total_notifications FROM notifikasi;
SELECT id_pengguna, type, title, COUNT(*) as cnt FROM notifikasi WHERE type IS NOT NULL GROUP BY id_pengguna, type, title HAVING cnt > 1;

EOF
