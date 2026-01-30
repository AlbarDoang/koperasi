<?php
// generate_qris_payload.php
// Endpoint to return a QRIS-style payload (signed) for a payment request.
// - Accepts POST JSON with either:
//    - request_code (to generate payload for existing request), OR
//    - user_id (required) and amount (optional) to create a new payment_request and return payload
// - Uses prepared statements and returns JSON { success, request_code, qris_payload, signature, expired_at, message }
// - Signature is HMAC-SHA256 over canonical JSON payload. Replace with real QRIS signing in production.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// header('Access-Control-Allow-Origin: *'); // Enable if cross-origin access is required

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $request_code = $data['request_code'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $amount = $data['amount'] ?? null;

    if ($request_code === null && ($user_id === null || $user_id === '' || !is_numeric($user_id))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Provide either "request_code" or valid "user_id"']);
        exit;
    }

    $dbHost = '127.0.0.1';
    $dbName = 'gas';
    $dbUser = 'root';
    $dbPass = '';
    $qrSecret = 'CHANGE_ME_SIGNING_SECRET'; // IMPORTANT: replace with secure secret from env in production

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);

    // If request_code not provided, create a new payment_request similar to create_payment_request.php
    if ($request_code === null) {
        // normalize amount
        $amountValue = null;
        if ($amount !== null && $amount !== '') {
            if (!is_numeric($amount)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid "amount". Must be numeric.']);
                exit;
            }
            $amountValue = number_format((float)$amount, 2, '.', '');
        }

        $userId = (int)$user_id;

        $now = new DateTime();
        $createdAt = $now->format('Y-m-d H:i:s');
        $expiredAt = (clone $now)->add(new DateInterval('PT10M'))->format('Y-m-d H:i:s');

        $insertSql = "INSERT INTO payment_request (request_code, user_id, amount, status, expired_at, created_at, updated_at)
                      VALUES (:request_code, :user_id, :amount, 'pending', :expired_at, :created_at, :updated_at)";

        $maxAttempts = 5;
        $inserted = false;
        for ($i = 0; $i < $maxAttempts && !$inserted; $i++) {
            $generatedCode = 'pr_' . bin2hex(random_bytes(16));
            $stmt = $pdo->prepare($insertSql);
            $stmt->bindValue(':request_code', $generatedCode, PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            if ($amountValue === null) {
                $stmt->bindValue(':amount', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':amount', $amountValue, PDO::PARAM_STR);
            }
            $stmt->bindValue(':expired_at', $expiredAt, PDO::PARAM_STR);
            $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', $createdAt, PDO::PARAM_STR);

            try {
                $stmt->execute();
                $inserted = true;
                $request_code = $generatedCode;
            } catch (PDOException $e) {
                // If duplicate code retry
                if ($e->getCode() === '23000') {
                    continue;
                }
                // otherwise log and error
                error_log('generate_qris_payload insert error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
                exit;
            }
        }

        if (!$inserted) {
            error_log('generate_qris_payload failed to insert after attempts');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create payment request']);
            exit;
        }
    }

    // Fetch the payment_request row
    $selectSql = "SELECT id, request_code, user_id, amount, status, expired_at, created_at FROM payment_request WHERE request_code = :request_code LIMIT 1";
    $stmt = $pdo->prepare($selectSql);
    $stmt->bindValue(':request_code', $request_code, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment request not found']);
        exit;
    }

    // Check status
    if ($row['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Payment request is not pending', 'status' => $row['status']]);
        exit;
    }

    $now = new DateTime();
    $expiredAtDt = new DateTime($row['expired_at']);
    if ($expiredAtDt <= $now) {
        // mark expired
        $updateExpiredSql = "UPDATE payment_request SET status = 'expired', updated_at = :updated_at WHERE id = :id AND status = 'pending'";
        $u = $pdo->prepare($updateExpiredSql);
        $u->bindValue(':updated_at', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $u->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
        $u->execute();

        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Payment request expired']);
        exit;
    }

    // Build canonical payload (associative array) and sign it
    $payload = [
        'request_code' => $row['request_code'],
        'amount' => $row['amount'] !== null ? number_format((float)$row['amount'], 2, '.', '') : null,
        'merchant_id' => 'YOUR_MERCHANT_ID', // replace with real merchant configuration
        'created_at' => $row['created_at'],
        'expired_at' => $row['expired_at'],
    ];

    // Remove nulls to create deterministic payload if desired
    $payloadFiltered = array_filter($payload, function ($v) { return $v !== null; });

    // Sort keys for canonical representation
    ksort($payloadFiltered);

    $canonical = json_encode($payloadFiltered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($canonical === false) {
        error_log('generate_qris_payload: failed to json_encode payload');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }

    $signature = hash_hmac('sha256', $canonical, $qrSecret);

    // Build final QR payload string as base64(canonical).signature
    $qris_payload = base64_encode($canonical) . '.' . $signature;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'request_code' => $row['request_code'],
        'qris_payload' => $qris_payload,
        'signature' => $signature,
        'expired_at' => $row['expired_at'],
        'message' => 'QRIS payload generated.'
    ]);
    exit;

} catch (Exception $ex) {
    error_log('generate_qris_payload exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
