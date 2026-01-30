<?php
// create_payment_request.php
// API to create a payment request
// - Accepts POST { user_id, amount (optional) }
// - Uses PDO prepared statements
// - Generates a secure unique request_code
// - Sets expired_at = now + 10 minutes
// - Returns JSON { success, request_code, expired_at, message }

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Optionally allow CORS for testing - adjust for production
// header('Access-Control-Allow-Origin: *');

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
        exit;
    }

    // Read JSON body if present, fallback to form data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $user_id = null;
    $amount = null;

    if (is_array($data)) {
        $user_id = $data['user_id'] ?? null;
        $amount = $data['amount'] ?? null;
    } else {
        $user_id = $_POST['user_id'] ?? null;
        $amount = $_POST['amount'] ?? null;
    }

    // Validate user_id
    if ($user_id === null || $user_id === '' || !is_numeric($user_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing "user_id"']);
        exit;
    }

    $userId = (int) $user_id;

    // Normalize amount: if empty -> NULL; otherwise validate numeric
    $amountValue = null;
    if ($amount !== null && $amount !== '') {
        if (!is_numeric($amount)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid "amount". Must be numeric.']);
            exit;
        }
        // Cast to string to preserve decimal precision for PDO
        $amountValue = number_format((float)$amount, 2, '.', '');
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

    // Prepare insert with retry in case of (very unlikely) request_code collision
    $maxAttempts = 5;
    $inserted = false;
    $lastException = null;

    $now = new DateTime();

    for ($attempt = 0; $attempt < $maxAttempts && !$inserted; $attempt++) {
        // Generate secure random code (not guessable)
        $requestCode = 'pr_' . bin2hex(random_bytes(16)); // ~32 hex chars, well under VARCHAR(60)

        $createdAt = $now->format('Y-m-d H:i:s');
        $expiredAt = (clone $now)->add(new DateInterval('PT10M'))->format('Y-m-d H:i:s');

        $sql = "INSERT INTO payment_request
            (request_code, user_id, amount, status, expired_at, created_at, updated_at)
            VALUES (:request_code, :user_id, :amount, 'pending', :expired_at, :created_at, :updated_at)";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':request_code', $requestCode, PDO::PARAM_STR);
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
            // success response
            // Build a QR payload string that clients can display. For production,
            // this should be generated/validated by the server (signed or in QRIS format).
            $qrPayload = 'REQUEST|CODE=' . $requestCode;
            if ($amountValue !== null) {
                $qrPayload .= '|AMOUNT=' . $amountValue;
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'request_code' => $requestCode,
                'expired_at' => $expiredAt,
                'qr_payload' => $qrPayload,
                'message' => 'Payment request created.'
            ]);
            exit;
        } catch (PDOException $e) {
            $lastException = $e;
            // If duplicate key (unique request_code), retry; otherwise fail
            $sqlState = $e->getCode();
            // MySQL duplicate entry SQLSTATE is 23000 and errorInfo[1] is 1062
            if ($sqlState === '23000') {
                // try again with a new code
                continue;
            }
            // For other DB errors, stop and return generic error
            break;
        }
    }

    // If we reach here, insertion failed
    error_log('create_payment_request error: ' . ($lastException ? $lastException->getMessage() : 'unknown'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to create payment request. Please try again later.']);
    exit;

} catch (Exception $ex) {
    // Log the real error server-side, but return a generic message to the client
    error_log('create_payment_request exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;
}
