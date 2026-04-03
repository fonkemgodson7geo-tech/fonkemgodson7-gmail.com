<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/realtime_gateway.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $payload = $_POST;
    if (empty($payload)) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $transactionId = trim((string)($payload['cpm_trans_id'] ?? $payload['transaction_id'] ?? ''));
    if ($transactionId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing transaction id']);
        exit;
    }

    $pdo = getDB();
    $verifyFailure = null;
    $verification = rtVerifyCinetPayTransaction($transactionId, $verifyFailure);
    if ($verification === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $verifyFailure ?: 'verification failed']);
        exit;
    }

    $status = (string)$verification['status'];
    $update = $pdo->prepare('UPDATE payments SET status = ? WHERE transaction_id = ?');
    $update->execute([$status, $transactionId]);

    if ($status === 'completed') {
        $query = $pdo->prepare('SELECT p.amount, p.transaction_id, pa.user_id, u.email, u.phone FROM payments p JOIN patients pa ON p.patient_id = pa.id LEFT JOIN users u ON pa.user_id = u.id WHERE p.transaction_id = ? LIMIT 1');
        $query->execute([$transactionId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (!empty($row['phone'])) {
                $smsFailure = null;
                rtSendSms((string)$row['phone'], 'Payment successful. Transaction ' . $transactionId . ' Amount ' . number_format((float)$row['amount'], 2), $smsFailure);
            }
            if (!empty($row['email'])) {
                $mailFailure = null;
                rtSendEmail((string)$row['email'], SITE_NAME . ' payment completed', '<p>Your payment was completed successfully.</p><p>Transaction: <strong>' . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . '</strong></p>', 'Payment completed. Transaction: ' . $transactionId, $mailFailure);
            }
        }
    }

    echo json_encode(['ok' => true, 'transaction_id' => $transactionId, 'status' => $status]);
} catch (Throwable $e) {
    error_log('CinetPay callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal error']);
}
