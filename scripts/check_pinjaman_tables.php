<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$tables = ['pinjaman', 'pinjaman_biasa', 'pinjaman_kredit'];
foreach ($tables as $t) {
    $q = $con->query("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $con->real_escape_string($t) . "'");
    $r = $q->fetch_assoc();
    echo $t . ': ' . ($r['cnt'] ? 'exists' : 'missing') . "\n";
}
?>