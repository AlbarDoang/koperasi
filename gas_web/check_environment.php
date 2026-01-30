<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment Check - GAS Koperasi</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }
        .check-item.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .check-item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .check-item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .check-icon {
            font-size: 24px;
            margin-right: 15px;
            min-width: 30px;
        }
        .check-content {
            flex: 1;
        }
        .check-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .check-desc {
            font-size: 13px;
            color: #666;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #FF4C00;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        .info-box h3 {
            color: #0056b3;
            margin-bottom: 10px;
        }
        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Environment Check</h1>
        <p class="subtitle">GAS Koperasi - System Requirements Validation</p>

        <div class="section-title">📦 PHP Environment</div>
        
        <?php
        // PHP Version Check
        $php_version = phpversion();
        $php_ok = version_compare($php_version, '7.4.0', '>=');
        ?>
        <div class="check-item <?= $php_ok ? 'success' : 'error' ?>">
            <div class="check-icon"><?= $php_ok ? '✅' : '❌' ?></div>
            <div class="check-content">
                <div class="check-title">PHP Version: <?= $php_version ?></div>
                <div class="check-desc">
                    <?= $php_ok ? 'PHP version OK (>= 7.4)' : 'PHP version terlalu lama. Butuh >= 7.4' ?>
                </div>
            </div>
        </div>

        <?php
        // Required Extensions
        $required_extensions = [
            'mysqli' => 'MySQL Database Connection',
            'json' => 'JSON Support',
            'mbstring' => 'Multi-byte String Support',
            'openssl' => 'SSL Support',
            'pdo' => 'PDO Database Support',
            'pdo_mysql' => 'PDO MySQL Driver',
            'gd' => 'Image Processing (GD Library)',
            'fileinfo' => 'File Type Detection',
            'curl' => 'cURL Support'
        ];

        foreach ($required_extensions as $ext => $desc) {
            $loaded = extension_loaded($ext);
            ?>
            <div class="check-item <?= $loaded ? 'success' : 'error' ?>">
                <div class="check-icon"><?= $loaded ? '✅' : '❌' ?></div>
                <div class="check-content">
                    <div class="check-title">PHP Extension: <?= $ext ?></div>
                    <div class="check-desc"><?= $desc ?> - <?= $loaded ? 'Installed' : 'Not Installed' ?></div>
                </div>
            </div>
            <?php
        }
        ?>

        <div class="section-title">🗄️ Database Connection</div>

        <?php
        require_once __DIR__ . '/../config/database.php';
        
        $db_connected = ($con && $con->ping());
        ?>
        <div class="check-item <?= $db_connected ? 'success' : 'error' ?>">
            <div class="check-icon"><?= $db_connected ? '✅' : '❌' ?></div>
            <div class="check-content">
                <div class="check-title">MySQL Connection</div>
                <div class="check-desc">
                    <?php if ($db_connected): ?>
                        Connected to: <?= DB_HOST ?> / Database: <?= DB_NAME ?>
                    <?php else: ?>
                        Cannot connect to database. Check MySQL service and database configuration.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($db_connected): ?>
            <?php
            // Check tables exist
            $tables = ['siswa', 'tabungan', 't_masuk', 't_keluar', 'transfer', 'transaksi'];
            $tables_exist = true;
            $missing_tables = [];
            
            foreach ($tables as $table) {
                $result = $con->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows == 0) {
                    $tables_exist = false;
                    $missing_tables[] = $table;
                }
            }
            ?>
            <div class="check-item <?= $tables_exist ? 'success' : 'warning' ?>">
                <div class="check-icon"><?= $tables_exist ? '✅' : '⚠️' ?></div>
                <div class="check-content">
                    <div class="check-title">Database Tables</div>
                    <div class="check-desc">
                        <?php if ($tables_exist): ?>
                            All required tables exist
                        <?php else: ?>
                            Missing tables: <?= implode(', ', $missing_tables) ?>
                            <br>Import database_structure.sql to create tables
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php
            // Check admin user exists
            $admin_check = $con->query("SELECT COUNT(*) as cnt FROM siswa WHERE role='admin' AND status='aktif'");
            $admin_row = $admin_check->fetch_assoc();
            $admin_exists = $admin_row['cnt'] > 0;
            ?>
            <div class="check-item <?= $admin_exists ? 'success' : 'warning' ?>">
                <div class="check-icon"><?= $admin_exists ? '✅' : '⚠️' ?></div>
                <div class="check-content">
                    <div class="check-title">Admin Account</div>
                    <div class="check-desc">
                        <?= $admin_exists ? 'Admin account exists' : 'No active admin account found' ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="section-title">📁 File Permissions</div>

        <?php
        $upload_dir = __DIR__ . '/../uploads/';
        $writable_dirs = [
            $upload_dir . 'ktp/' => 'Upload KTP Directory',
            $upload_dir . 'selfie/' => 'Upload Selfie Directory',
            __DIR__ . '/../logs/' => 'Logs Directory'
        ];

        foreach ($writable_dirs as $dir => $desc) {
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);
            
            // Try to create if not exists
            if (!$exists) {
                @mkdir($dir, 0777, true);
                $exists = is_dir($dir);
                $writable = $exists && is_writable($dir);
            }
            ?>
            <div class="check-item <?= $writable ? 'success' : ($exists ? 'warning' : 'error') ?>">
                <div class="check-icon"><?= $writable ? '✅' : ($exists ? '⚠️' : '❌') ?></div>
                <div class="check-content">
                    <div class="check-title"><?= $desc ?></div>
                    <div class="check-desc">
                        <?php if ($writable): ?>
                            Directory exists and writable
                        <?php elseif ($exists): ?>
                            Directory exists but not writable. Check permissions.
                        <?php else: ?>
                            Directory does not exist. Create it manually.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

        <div class="section-title">🔧 PHP Configuration</div>

        <?php
        $configs = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time') . ' seconds',
            'memory_limit' => ini_get('memory_limit'),
            'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
            'error_reporting' => ini_get('error_reporting')
        ];
        // Helper to parse ini size strings like '10M' => bytes
        function parse_size_str($size) {
            $unit = strtolower(substr(trim($size), -1));
            $val = (int)$size;
            if ($unit === 'g') $val *= 1024 * 1024 * 1024;
            elseif ($unit === 'm') $val *= 1024 * 1024;
            elseif ($unit === 'k') $val *= 1024;
            return $val;
        }

        // KYC recommended values (use central config when available)
        if (file_exists(__DIR__ . '/flutter_api/storage_config.php')) {
            include_once __DIR__ . '/flutter_api/storage_config.php';
        }
        $required_per_file = defined('KYC_MAX_FILE_SIZE') ? KYC_MAX_FILE_SIZE : (15 * 1024 * 1024);
        $required_post_size = 2 * $required_per_file + (1 * 1024 * 1024); // two files + 1MB overhead

        $current_upload = parse_size_str(ini_get('upload_max_filesize'));
        $current_post = parse_size_str(ini_get('post_max_size'));

        $upload_ok = $current_upload >= $required_per_file;
        $post_ok = $current_post >= $required_post_size;

        // Display php.ini related checks
        foreach ($configs as $key => $value) {
            $status = 'success';
            $icon = 'ℹ️';
            if ($key === 'upload_max_filesize') {
                $status = $upload_ok ? 'success' : 'warning';
                $icon = $upload_ok ? '✅' : '⚠️';
                $value .= $upload_ok ? ' (sufficient for KYC uploads)' : ' (too small for KYC uploads)';
            } elseif ($key === 'post_max_size') {
                $status = $post_ok ? 'success' : 'warning';
                $icon = $post_ok ? '✅' : '⚠️';
                $value .= $post_ok ? ' (sufficient for combined KYC uploads)' : ' (recommend >= ' . intval($required_post_size / (1024*1024)) . 'MB)';
            }
            ?>
            <div class="check-item <?= $status ?>">
                <div class="check-icon"><?= $icon ?></div>
                <div class="check-content">
                    <div class="check-title"><?= $key ?></div>
                    <div class="check-desc"><?= $value ?></div>
                </div>
            </div>
            <?php
        }
        ?>

        <div class="check-item <?= ($upload_ok && $post_ok) ? 'success' : 'warning' ?>">
            <div class="check-icon"><?= ($upload_ok && $post_ok) ? '✅' : '⚠️' ?></div>
            <div class="check-content">
                <div class="check-title">KYC upload requirements</div>
                <div class="check-desc">
                    Disarankan: per-file <= <?= intval($required_per_file / (1024*1024)) ?>MB; post_max_size >= <?= intval($required_post_size / (1024*1024)) ?>MB.
                    <?php if (!($upload_ok && $post_ok)): ?>
                        <br>Ubah <code>php.ini</code>: <code>upload_max_filesize=<?= intval($required_per_file / (1024*1024)) ?>M</code> dan <code>post_max_size=<?= intval($required_post_size / (1024*1024)) ?>M</code>, lalu restart web server.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="info-box">
            <h3>📱 Next Steps - Mobile App Setup</h3>
            <p>Untuk menghubungkan Flutter mobile app ke server ini:</p>
            
            <div class="code">
// File: gas_mobile/lib/config/api.dart

// Untuk Android Emulator:
static String baseUrl = "http://172.168.80.236/gas/gas_web/flutter_api";

// Untuk Device Fisik (ganti dengan IP laptop Anda):
<?php
// Get server IP
$ip = $_SERVER['SERVER_ADDR'];
if ($ip == '::1' || $ip == '127.0.0.1') {
    // Try to get local IP
    $output = shell_exec('ipconfig');
    preg_match('/IPv4 Address[^\d]+([\d\.]+)/', $output, $matches);
    if (isset($matches[1])) {
        $ip = $matches[1];
    }
}
?>
static String baseUrl = "http://<?= $ip ?>/gas/gas_web/flutter_api";
            </div>

            <p style="margin-top: 10px;">
                <strong>Your Server IP:</strong> <?= $ip ?>
            </p>
        </div>

        <div class="info-box" style="margin-top: 20px;">
            <h3>🧪 Testing</h3>
            <p>Test API endpoints:</p>
            <div class="code">
# Open in browser:
http://<?= $_SERVER['HTTP_HOST'] ?>/gas/gas_web/test_api_dashboard.html

# Or test directly:
http://<?= $_SERVER['HTTP_HOST'] ?>/gas/gas_web/flutter_api/login.php
            </div>
        </div>
    </div>
</body>
</html>
