<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/realtime_gateway.php';

requireRole('patient');

$tx = trim((string)($_GET['tx'] ?? ''));

if ($tx !== '') {
    try {
        $pdo = getDB();
        $verifyFailure = null;
        $verification = rtVerifyCinetPayTransaction($tx, $verifyFailure);
        if ($verification !== null) {
            $status = (string)$verification['status'];
            $stmt = $pdo->prepare('UPDATE payments SET status = ? WHERE transaction_id = ?');
            $stmt->execute([$status, $tx]);

            if ($status === 'completed') {
                $_SESSION['payment_flash'] = 'Payment completed successfully. Transaction: ' . $tx;
            } elseif ($status === 'pending') {
                $_SESSION['payment_flash'] = 'Payment is still pending confirmation. Transaction: ' . $tx;
            } else {
                $_SESSION['payment_flash'] = 'Payment failed or was cancelled. Transaction: ' . $tx;
            }
        } else {
            $_SESSION['payment_flash'] = 'Could not verify gateway response: ' . ($verifyFailure ?: 'unknown error');
        }
    } catch (Throwable $e) {
        error_log('Payment return error: ' . $e->getMessage());
        $_SESSION['payment_flash'] = 'Payment return handling failed. Please check your payment history.';
    }
}

header('Location: payments.php');
exit;
