<?php
// pay_payment_request.php
// API to mark a payment request as paid
// - Accepts POST { request_code, payer_user_id }
// - Uses a DB transaction and SELECT ... FOR UPDATE to prevent double payments
// - Updates status='paid' and paid_at=NOW()

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
    $payer_user_id = null;

    if (is_array($data)) {
        $request_code = $data['request_code'] ?? null;
        $payer_user_id = $data['payer_user_id'] ?? null;
    } else {
        $request_code = $_POST['request_code'] ?? null;
        $payer_user_id = $_POST['payer_user_id'] ?? null;
    }

    if ($request_code === null || $request_code === '' || !is_string($request_code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "request_code"']);
        exit;
    }

    if ($payer_user_id === null || $payer_user_id === '' || !is_numeric($payer_user_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "payer_user_id"']);
        exit;
    }

    $payerUserId = (int)$payer_user_id;

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

    // Start transaction
    $pdo->beginTransaction();

    // Lock the row to prevent race conditions
    $selectSql = "SELECT id, request_code, user_id, amount, status, expired_at, paid_at
                  FROM payment_request
                  WHERE request_code = :request_code
                  FOR UPDATE";
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->bindValue(':request_code', $request_code, PDO::PARAM_STR);
    $selectStmt->execute();
    $row = $selectStmt->fetch();

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment request not found']);
        exit;
    }

    $id = (int)$row['id'];
    $status = $row['status'];
    $expired_at = $row['expired_at'];

    // If already paid or not pending, reject
    if ($status !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'status' => $status, 'message' => 'Payment request cannot be paid in its current state']);
        exit;
    }

    // Check expiration
    $now = new DateTime();
    $expiredAtDt = new DateTime($expired_at);
    if ($expiredAtDt <= $now) {
        // mark as expired
        $updateExpiredSql = "UPDATE payment_request
                             SET status = 'expired', updated_at = :updated_at
                             WHERE id = :id AND status = 'pending'";
        $updateExpiredStmt = $pdo->prepare($updateExpiredSql);
        $updateExpiredStmt->bindValue(':updated_at', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $updateExpiredStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $updateExpiredStmt->execute();

        $pdo->commit();
        http_response_code(409);
        echo json_encode(['success' => false, 'status' => 'expired', 'message' => 'Payment request has expired and cannot be paid']);
        exit;
    }

    // At this point, status is 'pending' and not expired => proceed with balance transfer and mark paid
    // Ensure amount exists
    if ($row['amount'] === null) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment request has no amount to transfer']);
        exit;
    }

    $amount = (float) $row['amount'];
    if ($amount <= 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit;
    }

    $receiverUserId = (int)$row['user_id'];

    // Prevent paying yourself
    if ($payerUserId === $receiverUserId) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payer and receiver cannot be the same user']);
        exit;
    }

    // Lock payer and receiver balance rows. This assumes user balances are stored in table `pengguna` with column `saldo`.
    // If your balances table is different (e.g., `users` or `balances`), adjust the table/column names.
    $selectUserSql = "SELECT id, saldo FROM pengguna WHERE id = :id FOR UPDATE";
    $selectUserStmt = $pdo->prepare($selectUserSql);

    // Fetch payer
    $selectUserStmt->execute([':id' => $payerUserId]);
    $payerRow = $selectUserStmt->fetch();
    if (!$payerRow) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payer not found']);
        exit;
    }

    // Fetch receiver
    $selectUserStmt->execute([':id' => $receiverUserId]);
    $receiverRow = $selectUserStmt->fetch();
    if (!$receiverRow) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Receiver not found']);
        exit;
    }

    $payerBalance = (float) $payerRow['saldo'];
    $receiverBalance = (float) $receiverRow['saldo'];

    // Check sufficient funds
    if ($payerBalance < $amount) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Insufficient funds', 'available' => number_format($payerBalance, 2, '.', '')]);
        exit;
    }

    // Deduct from payer using conditional update to avoid race issues
    $deductSql = "UPDATE pengguna SET saldo = saldo - :amount WHERE id = :id AND saldo >= :amount";
    $deductStmt = $pdo->prepare($deductSql);
    $deductStmt->bindValue(':amount', number_format($amount, 2, '.', ''), PDO::PARAM_STR);
    $deductStmt->bindValue(':id', $payerUserId, PDO::PARAM_INT);
    $deductStmt->execute();

    if ($deductStmt->rowCount() === 0) {
        // Not enough balance or race condition
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Insufficient funds or race condition']);
        exit;
    }

    // Credit receiver
    $creditSql = "UPDATE pengguna SET saldo = saldo + :amount WHERE id = :id";
    $creditStmt = $pdo->prepare($creditSql);
    $creditStmt->bindValue(':amount', number_format($amount, 2, '.', ''), PDO::PARAM_STR);
    $creditStmt->bindValue(':id', $receiverUserId, PDO::PARAM_INT);
    $creditStmt->execute();

    if ($creditStmt->rowCount() === 0) {
        // Unexpected: receiver row missing or failed
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to credit receiver balance']);
        exit;
    }

    // Update payment_request record to paid
    $paidAt = $now->format('Y-m-d H:i:s');
    $updateSql = "UPDATE payment_request
                  SET status = 'paid', paid_at = :paid_at, updated_at = :updated_at
                  WHERE id = :id AND status = 'pending'";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindValue(':paid_at', $paidAt, PDO::PARAM_STR);
    $updateStmt->bindValue(':updated_at', $paidAt, PDO::PARAM_STR);
    $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $updateStmt->execute();

    if ($updateStmt->rowCount() === 0) {
        // Another process likely changed the status
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Payment could not be completed (race condition).']);
        exit;
    }

    // Fetch updated balances for response (after the deduction/credit)
    $getBalStmt = $pdo->prepare("SELECT saldo FROM pengguna WHERE id = :id LIMIT 1");
    $getBalStmt->execute([':id' => $payerUserId]);
    $payerBalRow = $getBalStmt->fetch();
    $getBalStmt->execute([':id' => $receiverUserId]);
    $receiverBalRow = $getBalStmt->fetch();

    // Prepare ledger insertion
    // Recommended DDL for wallet_ledger (run once separately):
    // CREATE TABLE wallet_ledger (
    //   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    //   user_id INT NOT NULL,
    //   related_user_id INT NULL,
    //   payment_request_id INT NULL,
    //   amount DECIMAL(15,2) NOT NULL,
    //   type ENUM('debit','credit') NOT NULL,
    //   balance_before DECIMAL(15,2) NOT NULL,
    //   balance_after DECIMAL(15,2) NOT NULL,
    //   description VARCHAR(255) NULL,
    //   created_at DATETIME NOT NULL,
    //   INDEX (user_id), INDEX (payment_request_id)
    // ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    $payerBefore = number_format($payerBalance, 2, '.', '');
    $payerAfter = isset($payerBalRow['saldo']) ? number_format((float)$payerBalRow['saldo'], 2, '.', '') : null;
    $receiverBefore = number_format($receiverBalance, 2, '.', '');
    $receiverAfter = isset($receiverBalRow['saldo']) ? number_format((float)$receiverBalRow['saldo'], 2, '.', '') : null;

    if ($payerAfter === null || $receiverAfter === null) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to read updated balances for ledger']);
        exit;
    }

    $ledgerSql = "INSERT INTO wallet_ledger (user_id, related_user_id, payment_request_id, amount, type, balance_before, balance_after, description, created_at)
                  VALUES (:user_id, :related_user_id, :payment_request_id, :amount, :type, :balance_before, :balance_after, :description, :created_at)";
    $ledgerStmt = $pdo->prepare($ledgerSql);

    // Debit entry for payer
    $descriptionPayer = "Debit for payment request {$request_code} to user {$receiverUserId}";
    $ok = $ledgerStmt->execute([
        ':user_id' => $payerUserId,
        ':related_user_id' => $receiverUserId,
        ':payment_request_id' => $id,
        ':amount' => number_format($amount, 2, '.', ''),
        ':type' => 'debit',
        ':balance_before' => $payerBefore,
        ':balance_after' => $payerAfter,
        ':description' => $descriptionPayer,
        ':created_at' => $paidAt,
    ]);

    if ($ok === false) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write ledger (payer)']);
        exit;
    }

    // Credit entry for receiver
    $descriptionReceiver = "Credit for payment request {$request_code} from user {$payerUserId}";
    $ok2 = $ledgerStmt->execute([
        ':user_id' => $receiverUserId,
        ':related_user_id' => $payerUserId,
        ':payment_request_id' => $id,
        ':amount' => number_format($amount, 2, '.', ''),
        ':type' => 'credit',
        ':balance_before' => $receiverBefore,
        ':balance_after' => $receiverAfter,
        ':description' => $descriptionReceiver,
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
    echo json_encode([
        'success' => true,
        'status' => 'paid',
        'paid_at' => $paidAt,
        'message' => 'Payment request marked as paid.',
        'payer_balance' => $payerAfter,
        'receiver_balance' => $receiverAfter,
    ]);
    exit;

} catch (Exception $ex) {
    // Attempt rollback if transaction is active
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        // ignore rollback exceptions
    }

    error_log('pay_payment_request exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
