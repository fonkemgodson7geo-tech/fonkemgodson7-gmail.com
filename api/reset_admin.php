<?php
/**
 * One-time admin password reset endpoint.
 * Access: /api/reset_admin.php
 * WARNING: Delete this file after use for security.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$newPassword = 'awc_DDS2019';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo = getDB();
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $adminUsername = trim((string)ADMIN_LOGIN_USERNAME) ?: 'admie';
        
        // Try to update existing admin
        $stmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE role = ? AND lower(username) = lower(?)');
        $stmt->execute([$hashedPassword, 'admin', $adminUsername]);
        
        if ($stmt->rowCount() === 0) {
            // Create admin if doesn't exist
            $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role, first_name, last_name, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$adminUsername, $hashedPassword, ADMIN_LOGIN_EMAIL ?: 'admin@clinic.com', 'admin', 'Admin', 'User']);
        }
        
        $message = '✅ SUCCESS! Admin password has been reset to: ' . htmlspecialchars($newPassword) . '<br>You can now login at /admin/login.php with username <strong>' . htmlspecialchars($adminUsername) . '</strong>';
    } catch (Throwable $e) {
        $message = '❌ ERROR: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Password Reset</title>
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 2rem; }
        .box { max-width: 500px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .message { padding: 1rem; margin: 1rem 0; border-radius: 4px; background: #e8f5e9; color: #2e7d32; border: 1px solid #4caf50; }
        .message.error { background: #ffebee; color: #c62828; border-color: #f44336; }
        button { background: #dc3545; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔐 Admin Password Reset</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo str_contains($message, 'SUCCESS') ? '' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($message) || !str_contains($message, 'SUCCESS')): ?>
            <p>Click the button below to reset the admin password to: <strong>awc_DDS2019</strong></p>
            <form method="POST">
                <button type="submit" name="confirm" value="1">Reset Admin Password Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
