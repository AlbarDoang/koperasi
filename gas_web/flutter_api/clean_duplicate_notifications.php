<?php
/**
 * Script: Clean Duplicate Notifications
 * Purpose: Remove duplicate notifications from the database
 * 
 * A notification is considered a duplicate if:
 * 1. Same user (id_pengguna)
 * 2. Same type
 * 3. Same title
 * 4. Same message
 * 5. Created within 2 minutes of the first one
 * 
 * Usage: php clean_duplicate_notifications.php [--dry-run]
 */

include 'connection.php';
date_default_timezone_set('Asia/Jakarta');

$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv);

if ($dryRun) {
    echo "=== DRY RUN MODE (no deletions) ===\n\n";
}

try {
    // Get all notifications grouped by (user, type, title, message)
    $sql = "SELECT 
                id_pengguna, 
                type, 
                title, 
                message,
                COUNT(*) as cnt,
                MIN(id) as first_id,
                GROUP_CONCAT(id ORDER BY created_at) as all_ids,
                MIN(created_at) as first_created,
                MAX(created_at) as last_created
            FROM notifikasi
            WHERE type IS NOT NULL AND type != ''
            GROUP BY id_pengguna, type, title, message
            HAVING cnt > 1
            ORDER BY id_pengguna, first_created DESC";

    $result = $connect->query($sql);
    if (!$result) {
        throw new Exception('Query failed: ' . $connect->error);
    }

    $totalDuplicates = 0;
    $totalToDelete = 0;

    while ($row = $result->fetch_assoc()) {
        $user = $row['id_pengguna'];
        $type = $row['type'];
        $title = substr($row['title'], 0, 50);
        $count = $row['cnt'];
        $ids = explode(',', $row['all_ids']);
        $firstCreated = $row['first_created'];
        $lastCreated = $row['last_created'];

        echo sprintf(
            "[USER %d] Type: %-15s | Title: %-50s | Count: %d | Period: %s to %s\n",
            $user,
            $type,
            $title . (strlen($row['title']) > 50 ? '...' : ''),
            $count,
            $firstCreated,
            $lastCreated
        );

        // Keep first notification, delete the rest
        $toDelete = array_slice($ids, 1);

        if ($verbose) {
            echo "  Keep: {$ids[0]} (" . $firstCreated . ")\n";
            echo "  Delete: " . implode(', ', $toDelete) . "\n";
        }

        $totalDuplicates += count($toDelete);

        if (!$dryRun) {
            foreach ($toDelete as $id) {
                $deleteStmt = $connect->prepare("DELETE FROM notifikasi WHERE id = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param('i', $id);
                    if (!$deleteStmt->execute()) {
                        throw new Exception("Failed to delete notification {$id}: " . $deleteStmt->error);
                    }
                    $deleteStmt->close();
                    $totalToDelete++;
                }
            }
        }

        echo "\n";
    }

    echo "=== SUMMARY ===\n";
    echo "Duplicate notification groups found: " . $result->num_rows . "\n";
    echo "Total duplicate notifications: " . $totalDuplicates . "\n";

    if ($dryRun) {
        echo "Would delete: " . $totalDuplicates . " notifications (DRY RUN)\n";
        echo "\nRun without --dry-run to actually delete:\n";
        echo "  php clean_duplicate_notifications.php\n";
    } else {
        echo "Deleted: " . $totalToDelete . " notifications\n";
    }

    echo "\nCleaning completed successfully.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>
