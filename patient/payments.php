<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/realtime_gateway.php';

requireRole('patient');

$user = $_SESSION['user'];
$payments = [];
$error = '';
$message = '';
$totalPaid = 0.0;
$recipientDefault = (string)PAYMENT_NUMBER;
$paymentNumberValue = '';
$recipientNumberValue = $recipientDefault;
$amountValue = '';
$paymentMethodValue = '';
$awaitingApproval = false;
$pendingPayment = null;
$redirectUrl = null;

function maskPhone(string $value): string {
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === null || $digits === '') {
        return 'your number';
    }
    $last4 = substr($digits, -4);
    return '***' . $last4;
}

try {
    $pdo = getDB();

    // Resolve the current user's patient record so payment FK remains valid.
    $patientStmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
    $patientStmt->execute([(int)$user['id']]);
    $patientId = (int)($patientStmt->fetchColumn() ?: 0);

    if ($patientId <= 0) {
        $mrn = 'MRN-' . date('YmdHis') . '-' . random_int(100, 999);
        $createPatient = $pdo->prepare('INSERT INTO patients (user_id, medical_record_number) VALUES (?, ?)');
        $createPatient->execute([(int)$user['id'], $mrn]);
        $patientId = (int)$pdo->lastInsertId();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $allowedMethods = ['mtn_mobile_money', 'orange_money', 'card', 'bank_transfer', 'cash'];
        $configuredPincode = trim((string)PAYMENT_PINCODE);

        if (isset($_POST['make_payment'])) {
            $amountInput = trim((string)($_POST['amount'] ?? ''));
            $methodInput = trim((string)($_POST['payment_method'] ?? ''));
            $paymentNumberValue = trim((string)($_POST['payment_number'] ?? ''));
            $recipientNumberValue = trim((string)($_POST['recipient_number'] ?? $recipientDefault));
            $amountValue = $amountInput;
            $paymentMethodValue = $methodInput;

            if (!is_numeric($amountInput) || (float)$amountInput <= 0) {
                $error = 'Enter a valid payment amount greater than 0.';
            } elseif (!preg_match('/^\+?[0-9]{6,15}$/', $paymentNumberValue)) {
                $error = 'Enter a valid payment number.';
            } elseif ($recipientNumberValue !== $recipientDefault) {
                $error = 'Recipient number is invalid.';
            } elseif (!in_array($methodInput, $allowedMethods, true)) {
                $error = 'Select a valid payment method.';
            } else {
                $amount = round((float)$amountInput, 2);
                $showDevCode = (string)PAYMENT_DEV_SHOW_CODE;
                $smsFailureReason = null;
                $otpCode = rtIssueVerificationCode(
                    $pdo,
                    (int)$user['id'],
                    'sms',
                    $paymentNumberValue,
                    'Payment approval',
                    $smsFailureReason,
                    600
                );
                $smsSent = $otpCode !== null;

                if (!$smsSent && $showDevCode !== '1') {
                    $error = ($smsFailureReason ?: 'Approval code could not be sent by SMS.') . ' Configure Twilio credentials on the server first.';
                } else {
                    $_SESSION['pending_payment'] = [
                        'patient_id' => $patientId,
                        'amount' => $amount,
                        'payment_method' => $methodInput,
                        'payment_number' => $paymentNumberValue,
                        'recipient_number' => $recipientNumberValue,
                        'created_at' => time()
                    ];

                    $awaitingApproval = true;
                    $pendingPayment = $_SESSION['pending_payment'];
                    $message = 'A confirmation code has been sent to ' . maskPhone($paymentNumberValue) . '. Enter the code and your PIN to approve payment.';
                    if (!$smsSent) {
                        $message = ($smsFailureReason ?: 'SMS delivery is unavailable on this server.') . ' No real SMS was sent.';
                    }
                    if ($showDevCode === '1' && $otpCode !== null) {
                        $message .= ' Test code: ' . $otpCode;
                    }
                }
            }
        } elseif (isset($_POST['cancel_pending'])) {
            unset($_SESSION['pending_payment']);
            $pendingPayment = null;
            $awaitingApproval = false;
            $clearOtp = $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE user_id = ? AND type = ? AND used = 0');
            $clearOtp->execute([(int)$user['id'], 'sms']);
            $message = 'Pending payment cancelled successfully.';
        } elseif (isset($_POST['edit_pending'])) {
            $pendingPayment = $_SESSION['pending_payment'] ?? null;
            if (is_array($pendingPayment)) {
                $amountValue = (string)($pendingPayment['amount'] ?? '');
                $paymentMethodValue = (string)($pendingPayment['payment_method'] ?? '');
                $paymentNumberValue = (string)($pendingPayment['payment_number'] ?? '');
                $recipientNumberValue = (string)($pendingPayment['recipient_number'] ?? $recipientDefault);
                unset($_SESSION['pending_payment']);
                $pendingPayment = null;
                $awaitingApproval = false;
                $clearOtp = $pdo->prepare('UPDATE verification_codes SET used = 1 WHERE user_id = ? AND type = ? AND used = 0');
                $clearOtp->execute([(int)$user['id'], 'sms']);
                $message = 'You can now edit amount and contact, then resend approval code.';
            } else {
                $error = 'No pending payment available to edit.';
            }
        } elseif (isset($_POST['confirm_payment'])) {
            $otpInput = trim((string)($_POST['approval_code'] ?? ''));
            $pincodeInput = trim((string)($_POST['pincode'] ?? ''));
            $pendingPayment = $_SESSION['pending_payment'] ?? null;

            if (!is_array($pendingPayment)) {
                $error = 'No pending payment found. Please start payment again.';
            } elseif (!preg_match('/^[0-9]{6}$/', $otpInput)) {
                $error = 'Enter the 6-digit confirmation code sent to your number.';
            } elseif (!preg_match('/^[0-9]{4,8}$/', $pincodeInput)) {
                $error = 'Enter a valid PIN code (4 to 8 digits).';
            } elseif ($configuredPincode !== '' && !hash_equals($configuredPincode, $pincodeInput)) {
                $error = 'Invalid PIN code. Please try again.';
                $awaitingApproval = true;
            } else {
                    $verificationFailure = null;
                    if (!rtVerifyLatestCode($pdo, (int)$user['id'], 'sms', $otpInput, $verificationFailure)) {
                        $error = $verificationFailure ?: 'Invalid confirmation code.';
                        $awaitingApproval = true;
                    } else {
                        $paymentNumberTail = substr(preg_replace('/\D+/', '', (string)$pendingPayment['payment_number']), -4);
                        $transactionId = 'TXN-' . strtoupper(bin2hex(random_bytes(4))) . '-N' . $paymentNumberTail;

                        $method = (string)$pendingPayment['payment_method'];
                        $isGatewayMethod = in_array($method, ['mtn_mobile_money', 'orange_money'], true);

                        $insertStmt = $pdo->prepare(
                            'INSERT INTO payments (patient_id, amount, payment_method, transaction_id, status)
                             VALUES (?, ?, ?, ?, ?)'
                        );
                        $insertStmt->execute([
                            (int)$pendingPayment['patient_id'],
                            (float)$pendingPayment['amount'],
                            $method,
                            $transactionId,
                            $isGatewayMethod ? 'pending' : 'completed'
                        ]);

                        if ($isGatewayMethod) {
                            $baseUrl = SITE_URL !== '' ? SITE_URL : 'http://localhost:8000';
                            $gatewayFailure = null;
                            $cinetpay = rtCreateCinetPayPayment([
                                'transaction_id' => $transactionId,
                                'amount' => (float)$pendingPayment['amount'],
                                'currency' => 'XAF',
                                'description' => 'Patient payment',
                                'notify_url' => rtrim($baseUrl, '/') . '/api/cinetpay_callback.php',
                                'return_url' => rtrim($baseUrl, '/') . '/patient/payment_return.php?tx=' . urlencode($transactionId),
                                'channels' => 'MOBILE_MONEY',
                                'customer_name' => (string)($user['first_name'] ?? 'Patient'),
                                'customer_surname' => (string)($user['last_name'] ?? 'User'),
                                'customer_email' => (string)($user['email'] ?? 'patient@example.com'),
                                'customer_phone_number' => (string)$pendingPayment['payment_number'],
                            ], $gatewayFailure);

                            if ($cinetpay === null || empty($cinetpay['payment_url'])) {
                                $rollbackStmt = $pdo->prepare('UPDATE payments SET status = ? WHERE transaction_id = ?');
                                $rollbackStmt->execute(['failed', $transactionId]);
                                $error = 'Payment gateway error: ' . ($gatewayFailure ?: 'Unable to start checkout.');
                                $awaitingApproval = true;
                            } else {
                                unset($_SESSION['pending_payment']);
                                $_SESSION['payment_flash'] = 'Continue on secure gateway to complete your payment.';
                                $redirectUrl = (string)$cinetpay['payment_url'];
                            }
                        } else {
                            $confirmationFailure = null;
                            rtSendSms(
                                (string)$pendingPayment['payment_number'],
                                'Payment approved. Transaction ID: ' . $transactionId . '. Amount: ' . number_format((float)$pendingPayment['amount'], 2),
                                $confirmationFailure
                            );

                            if (!empty($user['email'])) {
                                $mailFailure = null;
                                rtSendEmail(
                                    (string)$user['email'],
                                    SITE_NAME . ' payment confirmation',
                                    '<p>Your payment has been approved.</p><p>Transaction ID: <strong>' . htmlspecialchars($transactionId, ENT_QUOTES, 'UTF-8') . '</strong></p>',
                                    'Your payment has been approved. Transaction ID: ' . $transactionId,
                                    $mailFailure
                                );
                            }

                            unset($_SESSION['pending_payment']);
                            $pendingPayment = null;
                            $message = 'Payment approved and completed. Confirmation notifications were sent.';
                            if ($confirmationFailure !== null && $confirmationFailure !== '') {
                                $message = 'Payment approved and completed, but SMS confirmation could not be sent.';
                            }
                        }
                }
            }
        }
    }

    if (!$awaitingApproval) {
        $pendingPayment = $_SESSION['pending_payment'] ?? null;
        if (is_array($pendingPayment)) {
            $awaitingApproval = true;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id, amount, payment_method, transaction_id, status, payment_date
         FROM payments
         WHERE patient_id = ?
         ORDER BY payment_date DESC'
    );
    $stmt->execute([$patientId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payments as $payment) {
        if (($payment['status'] ?? '') === 'completed') {
            $totalPaid += (float)($payment['amount'] ?? 0);
        }
    }
} catch (PDOException $e) {
    error_log('Patient payments page error: ' . $e->getMessage());
    $error = 'Could not load your payment history right now.';
}

if ($redirectUrl !== null && $redirectUrl !== '') {
    header('Location: ' . $redirectUrl);
    exit;
}

if (isset($_SESSION['payment_flash'])) {
    $message = (string)$_SESSION['payment_flash'];
    unset($_SESSION['payment_flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-heart-pulse"></i> Patient Portal</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="appointments.php">Appointments</a>
            <a class="nav-link" href="records.php">Medical Records</a>
            <a class="nav-link active" href="payments.php">Payments</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">My Payments</h2>
        <span class="badge bg-success fs-6">Completed Total: <?php echo htmlspecialchars(number_format($totalPaid, 2), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$awaitingApproval): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header"><strong>Step 1: Enter Payment Details</strong></div>
            <div class="card-body">
                <form method="post" action="payments.php" class="row g-3" novalidate>
                    <?php echo csrfField(); ?>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="form-control"
                            id="amount"
                            name="amount"
                            value="<?php echo htmlspecialchars($amountValue, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="e.g. 15000"
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="payment_number" class="form-label">Your Payment Number</label>
                        <input
                            type="text"
                            class="form-control"
                            id="payment_number"
                            name="payment_number"
                            value="<?php echo htmlspecialchars($paymentNumberValue, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="e.g. +2376XXXXXXX"
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="recipient_number" class="form-label">Recipient Number</label>
                        <input
                            type="text"
                            class="form-control"
                            id="recipient_number"
                            name="recipient_number"
                            value="<?php echo htmlspecialchars($recipientNumberValue, ENT_QUOTES, 'UTF-8'); ?>"
                            readonly
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Choose method</option>
                            <option value="mtn_mobile_money" <?php echo $paymentMethodValue === 'mtn_mobile_money' ? 'selected' : ''; ?>>MTN Mobile Money</option>
                            <option value="orange_money" <?php echo $paymentMethodValue === 'orange_money' ? 'selected' : ''; ?>>Orange Money</option>
                            <option value="card" <?php echo $paymentMethodValue === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="bank_transfer" <?php echo $paymentMethodValue === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="cash" <?php echo $paymentMethodValue === 'cash' ? 'selected' : ''; ?>>Cash</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <button type="submit" name="make_payment" class="btn btn-primary w-100">
                            <i class="bi bi-send-check"></i> Send Approval Code
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning"><strong>Step 2: Approve Payment</strong></div>
            <div class="card-body">
                <p class="mb-2">Confirmation code sent to <?php echo htmlspecialchars(maskPhone((string)($pendingPayment['payment_number'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>.</p>
                <p class="text-muted mb-3">Enter the code and your PIN to approve this payment.</p>

                <div class="row g-2 mb-3">
                    <div class="col-md-4"><strong>Amount:</strong> <?php echo htmlspecialchars(number_format((float)($pendingPayment['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="col-md-4"><strong>Method:</strong> <?php echo htmlspecialchars((string)($pendingPayment['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="col-md-4"><strong>Recipient:</strong> <?php echo htmlspecialchars((string)($pendingPayment['recipient_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <form method="post" action="payments.php" class="row g-3" novalidate>
                    <?php echo csrfField(); ?>
                    <div class="col-md-6">
                        <label for="approval_code" class="form-label">Approval Code</label>
                        <input
                            type="text"
                            class="form-control"
                            id="approval_code"
                            name="approval_code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="Enter 6-digit code"
                            required
                        >
                    </div>
                    <div class="col-md-6">
                        <label for="pincode" class="form-label">PIN Code</label>
                        <input
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]{4,8}"
                            class="form-control"
                            id="pincode"
                            name="pincode"
                            placeholder="Enter your PIN"
                            required
                        >
                    </div>
                    <div class="col-12">
                        <button type="submit" name="confirm_payment" class="btn btn-success w-100">
                            <i class="bi bi-shield-check"></i> Confirm And Approve Payment
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" name="edit_pending" class="btn btn-outline-primary w-100">
                            <i class="bi bi-pencil-square"></i> Edit Amount Or Contact
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" name="cancel_pending" class="btn btn-outline-danger w-100">
                            <i class="bi bi-x-circle"></i> Cancel Pending Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$payments): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No payments found yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <?php
                                $status = (string)($p['status'] ?? 'pending');
                                $badge = $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning');
                                $dtRaw = (string)($p['payment_date'] ?? '');
                                $dt = $dtRaw !== '' ? date('Y-m-d H:i', strtotime($dtRaw)) : '-';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dt, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($p['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($p['transaction_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badge; ?>">
                                            <?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>