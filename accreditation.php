<?php
require_once 'config/config.php';

$message = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['captcha'])) {
        // Simple math CAPTCHA
        $num1 = $_POST['num1'];
        $num2 = $_POST['num2'];
        $answer = $_POST['captcha_answer'];

        if ($answer == $num1 + $num2) {
            $step = 2;
            $message = 'CAPTCHA correct. Please enter your details for 2FA.';
        } else {
            $message = 'Incorrect CAPTCHA answer.';
        }
    } elseif (isset($_POST['verify'])) {
        // 2FA verification (placeholder)
        $email = $_POST['email'];
        $phone = $_POST['phone'];

        // Send verification code (placeholder)
        $code = rand(100000, 999999);
        // In real implementation, send SMS/email

        // For demo, just show the code
        $message = "Verification code sent: $code (demo)";
        $step = 3;
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
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if ($step == 1): ?>
                            <form method="post">
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
                            <p>Access granted. You can now view accredited content.</p>
                            <a href="accreditation_content.php" class="btn btn-success">View Content</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>