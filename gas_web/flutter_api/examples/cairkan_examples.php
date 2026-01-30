<?php
// Example snippets: mysqli and PDO implementations for checking balance and inserting withdrawal
// These are simplified examples for demonstration only. Use prepared statements and proper error handling in production.

// ------------------- MySQLi native example -------------------
function example_mysqli_cairkan($mysqli, $user_id, $id_jenis, $nominal, $keterangan = '') {
    try {
        // Begin transaction
        $mysqli->begin_transaction();

        // 1) Check balance per jenis (use nominal or jumlah with COALESCE)
        $amountColIn = 'nominal';
        $res = $mysqli->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'nominal'");
        if (!$res || $res->num_rows == 0) {
            $res = $mysqli->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'jumlah'");
            if ($res && $res->num_rows > 0) $amountColIn = 'jumlah';
        }

        $amountColOut = 'nominal';
        $res = $mysqli->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'nominal'");
        if (!$res || $res->num_rows == 0) {
            $res = $mysqli->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'jumlah'");
            if ($res && $res->num_rows > 0) $amountColOut = 'jumlah';
        }

        $stmt = $mysqli->prepare("SELECT COALESCE(SUM($amountColIn),0) AS s FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
        $stmt->bind_param('ii', $user_id, $id_jenis);
        $stmt->execute(); $r = $stmt->get_result(); $total_in = intval($r->fetch_assoc()['s'] ?? 0); $stmt->close();

        $stmt = $mysqli->prepare("SELECT COALESCE(SUM($amountColOut),0) AS s FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
        $stmt->bind_param('ii', $user_id, $id_jenis);
        $stmt->execute(); $r = $stmt->get_result(); $total_out = intval($r->fetch_assoc()['s'] ?? 0); $stmt->close();

        $available = $total_in - $total_out;
        if ($available < $nominal) {
            $mysqli->rollback();
            return ['status' => false, 'message' => 'Saldo tidak mencukupi', 'available' => $available];
        }

        // 2) Insert into tabungan_keluar
        $now = date('Y-m-d H:i:s');
        $stmt = $mysqli->prepare("INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, nominal, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiisss', $user_id, $id_jenis, $nominal, $keterangan, $now, $now);
        $ok = $stmt->execute(); $stmt->close();
        if (!$ok) throw new Exception('Insert tabungan_keluar failed');

        // Optional: wallet credit helper or update pengguna.saldo here as required, then commit
        $mysqli->commit();

        // Return new saldo if you fetched it, or success boolean
        return ['status' => true, 'message' => 'Tabungan berhasil dicairkan'];

    } catch (Exception $e) {
        $mysqli->rollback();
        return ['status' => false, 'message' => 'Gagal mencairkan tabungan'];
    }
}


// ------------------- PDO example -------------------
function example_pdo_cairkan($pdo, $user_id, $id_jenis, $nominal, $keterangan = '') {
    try {
        $pdo->beginTransaction();

        // detect columns similarly
        $amountColIn = 'nominal';
        $res = $pdo->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'nominal'");
        if ($res->rowCount() == 0) {
            $res = $pdo->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'jumlah'");
            if ($res->rowCount() > 0) $amountColIn = 'jumlah';
        }
        $amountColOut = 'nominal';
        $res = $pdo->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'nominal'");
        if ($res->rowCount() == 0) {
            $res = $pdo->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'jumlah'");
            if ($res->rowCount() > 0) $amountColOut = 'jumlah';
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM($amountColIn),0) AS s FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
        $stmt->execute([$user_id, $id_jenis]); $row = $stmt->fetch(); $total_in = intval($row['s'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM($amountColOut),0) AS s FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
        $stmt->execute([$user_id, $id_jenis]); $row = $stmt->fetch(); $total_out = intval($row['s'] ?? 0);

        $available = $total_in - $total_out;
        if ($available < $nominal) { $pdo->rollBack(); return ['status' => false, 'message' => 'Saldo tidak mencukupi', 'available' => $available]; }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, nominal, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $id_jenis, $nominal, $keterangan, $now, $now]);

        $pdo->commit();
        return ['status' => true, 'message' => 'Tabungan berhasil dicairkan'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => false, 'message' => 'Gagal mencairkan tabungan'];
    }
}

// Example JSON responses:
// Success: { "status": true, "message": "Tabungan berhasil dicairkan", "saldo": 10000 }
// Fail (insufficient): { "status": false, "message": "Saldo tidak mencukupi", "available": 0 }
// Fail (invalid jenis): { "status": false, "message": "Jenis tabungan tidak valid" }
// Fail (jenis not found): { "status": false, "message": "Jenis tabungan tidak ditemukan" }

// Additional helper example: validate incoming POST and return early when id_jenis_tabungan invalid
function validate_request_payload() {
    // Simulate $_POST
    $post = $_POST;
    if (!isset($post['id_jenis_tabungan']) || trim($post['id_jenis_tabungan']) === '' || !ctype_digit($post['id_jenis_tabungan'])) {
        echo json_encode(['status' => false, 'message' => 'Jenis tabungan tidak valid']);
        exit;
    }
    $idJenis = intval($post['id_jenis_tabungan']);
    // Validate exists in DB (example, $connect assumed)
    global $connect;
    $chk = $connect->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $idJenis);
    $chk->execute(); $cres = $chk->get_result(); $chk->close();
    if (!($cres && $cres->num_rows > 0)) {
        echo json_encode(['status' => false, 'message' => 'Jenis tabungan tidak ditemukan']);
        exit;
    }
    // OK: proceed
}

?>