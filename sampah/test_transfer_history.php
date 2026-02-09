<?php
require __DIR__ . '/../gas_web/flutter_api/connection.php';

// pick two users that exist
$from = 97;
$to = 95;
$nom = 1000;
// make transfer via function if available or by calling add_transfer.php wrapper
$script = __DIR__ . '/../gas_web/flutter_api/add_transfer.php';
$payload = "id_pengirim={$from}&id_penerima={$to}&pin=123456&keterangan=Transfer%20Test&nominal={$nom}";
$tmp = sys_get_temp_dir() . '/tf_' . uniqid() . '.php';
$wrap = "<?php\n\$_POST = array(); parse_str('" . addslashes($payload) . "', \$_POST); \$_SERVER['REQUEST_METHOD'] = 'POST'; include('" . addslashes($script) . "');\n";
file_put_contents($tmp, $wrap);
exec('php ' . escapeshellarg($tmp), $out, $rc);
@unlink($tmp);
$out_s = implode("\n", $out);
file_put_contents(__DIR__ . '/test_transfer_output.txt', $out_s);
echo "Transfer response: " . substr($out_s,0,400) . "\n";

// Now fetch history via get_history_by_jenis.php using wrapper
$histScript = __DIR__ . '/../gas_web/flutter_api/get_history_by_jenis.php';
$payload2 = "id_tabungan={$to}&jenis=1&limit=10"; // check receiver's ledger
$tmp2 = sys_get_temp_dir() . '/hist_' . uniqid() . '.php';
$wrap2 = "<?php\n\$_POST = array(); parse_str('" . addslashes($payload2) . "', \$_POST); \$_SERVER['REQUEST_METHOD'] = 'POST'; include('" . addslashes($histScript) . "');\n";
file_put_contents($tmp2, $wrap2);
exec('php ' . escapeshellarg($tmp2), $out2, $rc2);
@unlink($tmp2);
$out2_s = implode("\n", $out2);
file_put_contents(__DIR__ . '/test_transfer_history_output.json', $out2_s);
$json = json_decode($out2_s, true);
if (!$json) { echo "No JSON returned from history endpoint\n"; exit(1); }
$items = $json['data'] ?? [];
if (count($items) == 0) { echo "No history items returned\n"; exit(2); }
$first = $items[0];
echo "First item title: " . ($first['title'] ?? '<no title>') . "\n";
echo "First item type: " . ($first['type'] ?? '<no type>') . "\n";
if (strtolower($first['type'] ?? '') === 'transfer' || stripos($first['title'] ?? '', 'transfer') !== false) {
    echo "[OK] Transfer appears correctly in history\n";
} else {
    echo "[FAIL] Transfer not shown correctly: title='" . ($first['title'] ?? '') . "' type='" . ($first['type'] ?? '') . "'\n";
}
?>