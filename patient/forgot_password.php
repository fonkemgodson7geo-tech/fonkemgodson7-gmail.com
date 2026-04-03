<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    verifyCsrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $email === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND email = ? AND role = 'patient' LIMIT 1");
            $stmt->execute([$username, $email]);
            $userId = (int)$stmt->fetchColumn();

            if ($userId > 0) {
                $updateStmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateStmt->execute([hashPassword($newPassword), $userId]);
                $message = 'Password updated successfully. You can now sign in.';
            } else {
                $error = 'No patient account found with the provided username and email.';
            }
        } catch (PDOException $e) {
            error_log('Patient forgot password error: ' . $e->getMessage());
            $error = 'A system error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .wrap {
            width: 100%;
            max-width: 460px;
        }

        .card {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            background: linear-gradient(135deg, #0056b3 0%, #00438a 100%);
            color: #fff;
            padding: 1.5rem;
            text-align: center;
            border: none;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.7rem 0.9rem;
        }

        .btn-primary {
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-1"><i class="bi bi-key"></i> Forgot Password</h4>
            <small>Reset your patient account password</small>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?php echo csrfField(); ?>

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="bi bi-shield-lock"></i> Reset Password
                    </button>
                    <a href="login.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
