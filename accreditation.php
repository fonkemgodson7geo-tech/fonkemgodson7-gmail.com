<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/realtime_gateway.php';

$message = '';
$error = '';
$step = (int)($_SESSION['accreditation_step'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrf();
    if (isset($_POST['restart'])) {
        $_SESSION['accreditation_step'] = 1;
        unset($_SESSION['accreditation_contact']);
        $step = 1;
        $message = 'Accreditation flow restarted.';
    } elseif (isset($_POST['captcha'])) {
        // Simple math CAPTCHA
        $num1 = (int)($_POST['num1'] ?? 0);
        $num2 = (int)($_POST['num2'] ?? 0);
        $answer = (int)($_POST['captcha_answer'] ?? 0);

        if ($answer == $num1 + $num2) {
            $step = 2;
            $_SESSION['accreditation_step'] = 2;
            $message = 'CAPTCHA correct. Please enter your details for 2FA.';
        } else {
            $error = 'Incorrect CAPTCHA answer.';
        }
    } elseif (isset($_POST['verify'])) {
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
            $step = 2;
        } elseif (!preg_match('/^\+?[0-9]{6,15}$/', $phone)) {
            $error = 'Enter a valid phone number.';
            $step = 2;
        } else {
            try {
                $pdo = getDB();
                $smsFailure = null;
                $emailFailure = null;
                $smsCode = rtIssueVerificationCode($pdo, null, 'sms', $phone, 'Accreditation SMS verification', $smsFailure, 600);
                $emailCode = rtIssueVerificationCode($pdo, null, 'email', $email, 'Accreditation email verification', $emailFailure, 600);

                if ($smsCode === null && $emailCode === null) {
                    $error = 'Verification could not be sent. SMS: ' . ($smsFailure ?: 'unavailable') . ' Email: ' . ($emailFailure ?: 'unavailable');
                    $step = 2;
                } else {
                    $_SESSION['accreditation_step'] = 3;
                    $_SESSION['accreditation_contact'] = [
                        'email' => $email,
                        'phone' => $phone,
                    ];
                    $step = 3;
                    $message = 'Verification code sent in real time to your phone and email.';
                    if (PAYMENT_DEV_SHOW_CODE === '1') {
                        $message .= ' Test SMS code: ' . ($smsCode ?: 'n/a') . ' | Test email code: ' . ($emailCode ?: 'n/a');
                    }
                }
            } catch (Throwable $e) {
                error_log('Accreditation verification send error: ' . $e->getMessage());
                $error = 'Could not start verification right now.';
                $step = 2;
            }
        }
    } elseif (isset($_POST['complete_verification'])) {
        $smsCode = trim((string)($_POST['sms_code'] ?? ''));
        $emailCode = trim((string)($_POST['email_code'] ?? ''));

        try {
            $pdo = getDB();
            $smsFailure = null;
            $emailFailure = null;
            $smsValid = rtVerifyLatestCode($pdo, null, 'sms', $smsCode, $smsFailure);
            $emailValid = rtVerifyLatestCode($pdo, null, 'email', $emailCode, $emailFailure);

            if ($smsValid && $emailValid) {
                $_SESSION['accreditation_step'] = 4;
                $step = 4;
                $message = 'Access granted. Real-time verification completed successfully.';
            } else {
                $step = 3;
                $error = trim(($smsValid ? '' : ($smsFailure ?: 'Invalid SMS code.')) . ' ' . ($emailValid ? '' : ($emailFailure ?: 'Invalid email code.')));
            }
        } catch (Throwable $e) {
            error_log('Accreditation verification confirm error: ' . $e->getMessage());
            $step = 3;
            $error = 'Could not validate the verification codes right now.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accreditation - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Secure Accreditation Access</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($step == 1): ?>
                            <form method="post">
                                <?php echo csrfField(); ?>
                                <h5>Step 1: CAPTCHA Verification</h5>
                                <?php $num1 = rand(1, 10); $num2 = rand(1, 10); ?>
                                <p>What is <?php echo $num1; ?> + <?php echo $num2; ?>?</p>
                                <input type="hidden" name="num1" value="<?php echo $num1; ?>">
                                <input type="hidden" name="num2" value="<?php echo $num2; ?>">
                                <div class="mb-3">
                                    <input type="number" class="form-control" name="captcha_answer" required>
                                </div>
                                <button type="submit" name="captcha" class="btn btn-primary">Verify</button>
                            </form>
                        <?php elseif ($step == 2): ?>
                            <form method="post">
                                <?php echo csrfField(); ?>
                                <h5>Step 2: 2FA Setup</h5>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                                <button type="submit" name="verify" class="btn btn-primary">Send Verification</button>
                            </form>
                        <?php elseif ($step == 3): ?>
                            <form method="post">
                                <?php echo csrfField(); ?>
                                <h5>Step 3: Confirm Verification</h5>
                                <div class="mb-3">
                                    <label for="sms_code" class="form-label">SMS Code</label>
                                    <input type="text" class="form-control" id="sms_code" name="sms_code" maxlength="6" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email_code" class="form-label">Email Code</label>
                                    <input type="text" class="form-control" id="email_code" name="email_code" maxlength="6" required>
                                </div>
                                <button type="submit" name="complete_verification" class="btn btn-success">Approve Access</button>
                            </form>
                        <?php elseif ($step == 4): ?>
                            <p>Access granted. You can now view accredited content.</p>
                            <a href="accreditation_content.php" class="btn btn-success">View Content</a>
                        <?php endif; ?>

                        <hr>
                        <h5>Accreditation Summary</h5>
                        <p class="mb-2">This page confirms our healthcare quality and compliance standards:</p>
                        <ul>
                            <li>Certified clinical staff and licensed practitioners.</li>
                            <li>Secure data handling and privacy-oriented workflows.</li>
                            <li>Audit-ready records and compliance dashboards.</li>
                            <li>Documented emergency and patient safety procedures.</li>
                        </ul>

                        <div class="d-flex gap-2 mt-3">
                            <a href="accreditation_content.php" class="btn btn-outline-success">Open Accreditation Content</a>
                            <form method="post" class="d-inline">
                                <?php echo csrfField(); ?>
                                <button type="submit" name="restart" class="btn btn-outline-secondary">Restart Verification</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>