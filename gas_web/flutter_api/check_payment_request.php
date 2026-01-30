<?php
// check_payment_request.php
// API to check the status of a payment request
// - Accepts POST { request_code }
// - Returns JSON { success, status, amount, user_id, expired_at, message }
// - Marks expired requests as 'expired' when expired_at <= NOW()

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// header('Access-Control-Allow-Origin: *'); // Enable for testing if needed

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $request_code = null;
    if (is_array($data)) {
        $request_code = $data['request_code'] ?? null;
    } else {
        $request_code = $_POST['request_code'] ?? null;
    }

    if ($request_code === null || $request_code === '' || !is_string($request_code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "request_code"']);
        exit;
    }

    // Optionally restrict length to reasonable bounds
    if (strlen($request_code) > 60) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid "request_code"']);
        exit;
    }

    // === Database connection settings - change as needed ===
    $dbHost = '127.0.0.1';
    $dbName = 'gas';
    $dbUser = 'root';
    $dbPass = '';
    // =======================================================

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);

    // Fetch the payment request
    $sql = "SELECT id, request_code, user_id, amount, status, expired_at, paid_at, created_at, updated_at
            FROM payment_request
            WHERE request_code = :request_code
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':request_code', $request_code, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment request not found', 'status' => null, 'amount' => null, 'user_id' => null, 'expired_at' => null]);
        exit;
    }

    $id = (int)$row['id'];
    $status = $row['status'];
    $amount = $row['amount'] !== null ? (string)$row['amount'] : null;
    $user_id = isset($row['user_id']) ? (int)$row['user_id'] : null;
    $expired_at = $row['expired_at'];

    $now = new DateTime();

    // If status is pending, check expiration
    if ($status === 'pending') {
        $expiredAtDt = new DateTime($expired_at);
        if ($expiredAtDt <= $now) {
            // Mark as expired using conditional update to avoid races
            $updateSql = "UPDATE payment_request
                          SET status = 'expired', updated_at = :updated_at
                          WHERE id = :id AND status = 'pending' AND expired_at <= :now";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindValue(':updated_at', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $updateStmt->bindValue(':now', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $updateStmt->execute();

            // reflect change in response
            $status = 'expired';
        }
    }

    // Build response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => $status,
        'amount' => $amount,
        'user_id' => $user_id,
        'expired_at' => $expired_at,
        'message' => 'Payment request status retrieved.'
    ]);
    exit;

} catch (Exception $ex) {
    error_log('check_payment_request exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
