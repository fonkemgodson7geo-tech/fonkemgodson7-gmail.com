<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('admin');

$user = $_SESSION['user'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbUser || strcasecmp((string)$dbUser['username'], ADMIN_LOGIN_USERNAME) !== 0 || (string)$dbUser['role'] !== 'admin') {
                $error = 'Only the designated admin account can change this password here.';
            } elseif (!verifyPassword($currentPassword, (string)$dbUser['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = hashPassword($newPassword);
                $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upd->execute([$newHash, (int)$dbUser['id']]);
                $message = 'Password updated successfully.';
            }
        } catch (PDOException $e) {
            error_log('Admin change password error: ' . $e->getMessage());
            $error = 'Could not update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Users</a>
            <a class="nav-link" href="manage_pharmacy_doctors.php">Pharmacy Access</a>
            <a class="nav-link active" href="change_password.php">Password</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5" style="max-width: 680px;">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h4 class="mb-0"><i class="bi bi-key"></i> Change Admin Password</h4>
            <small class="text-muted">Account: <?php echo htmlspecialchars(ADMIN_LOGIN_USERNAME, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <?php echo csrfField(); ?>
                <div class="mb-3">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New Password</label>
                    <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" required>
                    <small class="text-muted">Use at least 8 characters.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-danger">
                    <i class="bi bi-check2-circle"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
