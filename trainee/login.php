<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'trainee'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verifyPassword($password, (string)$user['password'])) {
            loginUser($user);
            header('Location: dashboard.php');
            exit;
        }
        $message = 'Invalid credentials';
    } catch (PDOException $e) {
        error_log('Trainee login DB error: ' . $e->getMessage());
        $message = 'A system error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(appLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainee Sign In - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:460px;">
    <div class="card shadow-sm">
        <div class="card-header" style="background:#6f42c1;color:#fff;"><h4 class="mb-0">Trainee Portal</h4></div>
        <div class="card-body">
            <?php if ($message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <div class="text-end mb-2">
                <a href="?lang=en" class="small">EN</a> |
                <a href="?lang=fr" class="small">FR</a>
            </div>
            <form method="post">
                <?php echo csrfField(); ?>
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="mb-3"><label class="form-label">Password</label><input class="form-control" id="password" type="password" name="password" required><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="showPassword"><label class="form-check-label" for="showPassword">Show password</label></div></div>
                <button class="btn w-100" style="background:#6f42c1;color:#fff;" type="submit">Sign In</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="register.php">Create trainee account</a> &middot; <a href="../index.php">Back to Home</a></p>
        </div>
    </div>
</div>
<script>
    const passwordField = document.getElementById('password');
    const showPassword = document.getElementById('showPassword');
    if (passwordField && showPassword) {
        showPassword.addEventListener('change', function () {
            passwordField.type = this.checked ? 'text' : 'password';
        });
    }
</script>
</body>
</html>
