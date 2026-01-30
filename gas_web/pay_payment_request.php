<?php
// pay_payment_request.php
// Endpoint to perform a payment (deduct payer, credit receiver)
// - Accepts JSON POST: { "request_code": "...", /* optional */ "amount": 1555, "payer_user_id": 2, "receiver_user_id": 5 /* optional if request_code provided */ }
// - If `request_code` is provided, the receiver is taken from the `payment_request` row
// - Uses PDO, transactions and SELECT ... FOR UPDATE to ensure atomicity
// - Inserts two ledger rows into `wallet_ledger` (debit for payer, credit for receiver)
// - Returns JSON responses

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight request
    http_response_code(204);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    // Read inputs (allow form fallback)
    $requestCode = null;
    $payerUserId = null;
    $amount = null;
    $receiverUserId = null;

    if (is_array($data)) {
        $requestCode = isset($data['request_code']) ? trim($data['request_code']) : null;
        $payerUserId = isset($data['payer_user_id']) ? $data['payer_user_id'] : null;
        $amount = isset($data['amount']) ? $data['amount'] : null;
        $receiverUserId = isset($data['receiver_user_id']) ? $data['receiver_user_id'] : null;
    } else {
        $requestCode = isset($_POST['request_code']) ? trim($_POST['request_code']) : null;
        $payerUserId = isset($_POST['payer_user_id']) ? $_POST['payer_user_id'] : null;
        $amount = isset($_POST['amount']) ? $_POST['amount'] : null;
        $receiverUserId = isset($_POST['receiver_user_id']) ? $_POST['receiver_user_id'] : null;
    }

    // Basic validation
    if ($payerUserId === null || $payerUserId === '' || !is_numeric($payerUserId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "payer_user_id"']);
        exit;
    }
    $payerUserId = (int)$payerUserId;

    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "amount"']);
        exit;
    }

    // Normalize amount to decimal cents precision
    $amount = (float)$amount;
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero']);
        exit;
    }

    // DB connection settings (follow project convention)
    $dbHost = '127.0.0.1';
    $dbName = 'gas';
    $dbUser = 'root';
    $dbPass = '';

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);

    // Start transaction
    $pdo->beginTransaction();

    $paymentRequestId = null;
    $receiverId = null;

    if ($requestCode !== null && $requestCode !== '') {
        // If request_code provided, load and lock the payment_request row
        $sql = "SELECT id, request_code, user_id, amount, status, expired_at FROM payment_request WHERE request_code = :request_code FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':request_code' => $requestCode]);
        $pr = $stmt->fetch();
        if (!$pr) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Payment request not found']);
            exit;
        }

        // Validate status
        if ($pr['status'] !== 'pending') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Payment request is not pending']);
            exit;
        }

        // Check expiration
        if (!empty($pr['expired_at'])) {
            $expiredAt = new DateTime($pr['expired_at']);
            $now = new DateTime();
            if ($expiredAt <= $now) {
                // mark expired
                $upd = $pdo->prepare("UPDATE payment_request SET status = 'expired', updated_at = :u WHERE id = :id AND status = 'pending'");
                $upd->execute([':u' => $now->format('Y-m-d H:i:s'), ':id' => (int)$pr['id']]);
                $pdo->commit();
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Payment request has expired']);
                exit;
            }
        }

        // If the request row specifies an amount, ensure it matches the provided amount
        if ($pr['amount'] !== null) {
            $prAmount = (float)$pr['amount'];
            if (abs($prAmount - $amount) > 0.0001) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Amount mismatch with payment request']);
                exit;
            }
        }

        $paymentRequestId = (int)$pr['id'];
        $receiverId = (int)$pr['user_id'];

        // Prevent payer paying themselves
        if ($payerUserId === $receiverId) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payer and receiver cannot be the same user']);
            exit;
        }
    } else {
        // No request_code: require explicit receiver_user_id in payload
        if ($receiverUserId === null || $receiverUserId === '' || !is_numeric($receiverUserId)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing "receiver_user_id" when request_code not provided']);
            exit;
        }
        $receiverId = (int)$receiverUserId;

        if ($payerUserId === $receiverId) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payer and receiver cannot be the same user']);
            exit;
        }
    }

    // Lock payer and receiver rows (table 'pengguna' with column 'saldo' is used in this project)
    $selectUserSql = "SELECT id, saldo FROM pengguna WHERE id = :id FOR UPDATE";
    $selectUserStmt = $pdo->prepare($selectUserSql);

    // Fetch payer
    $selectUserStmt->execute([':id' => $payerUserId]);
    $payer = $selectUserStmt->fetch();
    if (!$payer) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payer not found']);
        exit;
    }

    // Fetch receiver
    $selectUserStmt->execute([':id' => $receiverId]);
    $receiver = $selectUserStmt->fetch();
    if (!$receiver) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receiver not found']);
        exit;
    }

    $payerBalanceBefore = (float)$payer['saldo'];
    $receiverBalanceBefore = (float)$receiver['saldo'];

    // Sufficient funds check
    if ($payerBalanceBefore < $amount) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Insufficient funds']);
        exit;
    }

    // Deduct from payer (use conditional update to avoid races)
    $deductStmt = $pdo->prepare("UPDATE pengguna SET saldo = saldo - :amount WHERE id = :id AND saldo >= :amount");
    $deductStmt->execute([':amount' => number_format($amount, 2, '.', ''), ':id' => $payerUserId]);
    if ($deductStmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Insufficient funds or race condition']);
        exit;
    }

    // Credit receiver
    $creditStmt = $pdo->prepare("UPDATE pengguna SET saldo = saldo + :amount WHERE id = :id");
    $creditStmt->execute([':amount' => number_format($amount, 2, '.', ''), ':id' => $receiverId]);
    if ($creditStmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to credit receiver']);
        exit;
    }

    $now = new DateTime();
    $paidAt = $now->format('Y-m-d H:i:s');

    // If payment_request was used, mark it as paid
    if ($paymentRequestId !== null) {
        $updatePr = $pdo->prepare("UPDATE payment_request SET status = 'paid', paid_at = :paid_at, updated_at = :updated_at WHERE id = :id AND status = 'pending'");
        $updatePr->execute([':paid_at' => $paidAt, ':updated_at' => $paidAt, ':id' => $paymentRequestId]);
        if ($updatePr->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Payment could not be completed (race condition).']);
            exit;
        }
    }

    // Read updated balances
    $getBalStmt = $pdo->prepare("SELECT saldo FROM pengguna WHERE id = :id LIMIT 1");
    $getBalStmt->execute([':id' => $payerUserId]);
    $payerAfterRow = $getBalStmt->fetch();
    $getBalStmt->execute([':id' => $receiverId]);
    $receiverAfterRow = $getBalStmt->fetch();

    if (!$payerAfterRow || !$receiverAfterRow) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to read updated balances']);
        exit;
    }

    $payerBalanceAfter = number_format((float)$payerAfterRow['saldo'], 2, '.', '');
    $receiverBalanceAfter = number_format((float)$receiverAfterRow['saldo'], 2, '.', '');

    // Insert ledger entries
    // Recommended columns used: user_id, related_user_id, payment_request_id, amount, type, balance_before, balance_after, description, created_at
    $ledgerSql = "INSERT INTO wallet_ledger (user_id, related_user_id, payment_request_id, amount, type, balance_before, balance_after, description, created_at)
                  VALUES (:user_id, :related_user_id, :payment_request_id, :amount, :type, :balance_before, :balance_after, :description, :created_at)";
    $ledgerStmt = $pdo->prepare($ledgerSql);

    $descP = 'Debit for payment';
    if ($requestCode) $descP .= " (request {$requestCode})";
    $ok1 = $ledgerStmt->execute([
        ':user_id' => $payerUserId,
        ':related_user_id' => $receiverId,
        ':payment_request_id' => $paymentRequestId,
        ':amount' => number_format($amount, 2, '.', ''),
        ':type' => 'debit',
        ':balance_before' => number_format($payerBalanceBefore, 2, '.', ''),
        ':balance_after' => $payerBalanceAfter,
        ':description' => $descP,
        ':created_at' => $paidAt,
    ]);
    if ($ok1 === false) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write ledger (payer)']);
        exit;
    }

    $descR = 'Credit for payment';
    if ($requestCode) $descR .= " (request {$requestCode})";
    $ok2 = $ledgerStmt->execute([
        ':user_id' => $receiverId,
        ':related_user_id' => $payerUserId,
        ':payment_request_id' => $paymentRequestId,
        ':amount' => number_format($amount, 2, '.', ''),
        ':type' => 'credit',
        ':balance_before' => number_format($receiverBalanceBefore, 2, '.', ''),
        ':balance_after' => $receiverBalanceAfter,
        ':description' => $descR,
        ':created_at' => $paidAt,
    ]);
    if ($ok2 === false) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write ledger (receiver)']);
        exit;
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Payment successful']);
    exit;

} catch (Exception $ex) {
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        // ignore
    }

    error_log('pay_payment_request.php exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
