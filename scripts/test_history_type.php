<?php
$script = __DIR__ . '/../gas_web/flutter_api/get_history_by_jenis.php';
$payload = "id_tabungan=97&jenis=1&limit=5";
$tmp = sys_get_temp_dir() . '/hist_wrapper_' . uniqid() . '.php';
$wrap = "<?php\n\$_POST = []; parse_str('
?>";