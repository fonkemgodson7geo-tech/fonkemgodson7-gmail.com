<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';
$username = '';
$email = '';
$firstName = '';
$lastName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    $errors = [];
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) $errors[] = 'Username must be between 3 and 50 characters.';
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) $errors[] = 'Username contains invalid characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if ($firstName === '' || $lastName === '') $errors[] = 'First and last name are required.';
    if ($phone === '' || !preg_match('/^\+?[0-9][0-9\s\-\.]{6,19}$/', $phone)) $errors[] = 'A valid phone number is required.';

    if ($errors) {
        $message = implode(' ', $errors);
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES (?, ?, ?, 'staff', ?, ?, ?)");
            $stmt->execute([$username, $email, hashPassword($password), $firstName, $lastName, $phone]);
            $message = 'Staff account created successfully. You can now sign in.';
            $username = '';
            $email = '';
            $firstName = '';
            $lastName = '';
        } catch (PDOException $e) {
            error_log('Staff register DB error: ' . $e->getMessage());
            $message = 'Registration failed due to a system error.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(appLang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Staff Account - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:620px;">
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white"><h4 class="mb-0">Create Staff Account</h4></div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert <?php echo str_contains($message, 'successfully') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="text-end mb-2">
                <a href="?lang=en" class="small">EN</a> |
                <a href="?lang=fr" class="small">FR</a>
            </div>
            <form method="post" novalidate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">First Name</label><input class="form-control" name="first_name" value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Last Name</label><input class="form-control" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                </div>
                <div class="mt-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Password</label><input type="password" class="form-control" id="password" name="password" required></div>
                <div class="mt-3"><label class="form-label">Confirm Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required><div id="password_match_hint" class="form-text">Re-type your password to confirm.</div></div>
                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="showPasswords"><label class="form-check-label" for="showPasswords">Show passwords</label></div>
                <div class="mt-3"><label class="form-label">Phone Number</label><input type="tel" class="form-control" name="phone" placeholder="+237 6XX XXX XXX" value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <button class="btn btn-info text-white w-100 mt-4" type="submit">Create Account</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="login.php">Back to staff sign in</a></p>
        </div>
    </div>
</div>
<script>
    const pwd = document.getElementById('password');
    const confirmPwd = document.getElementById('confirm_password');
    const showPwds = document.getElementById('showPasswords');
    const hint = document.getElementById('password_match_hint');

    const updateHint = () => {
        if (!pwd || !confirmPwd || !hint) return;
        if (confirmPwd.value === '') {
            hint.textContent = 'Re-type your password to confirm.';
            hint.className = 'form-text';
            return;
        }
        if (pwd.value === confirmPwd.value) {
            hint.textContent = 'Passwords match.';
            hint.className = 'form-text text-success';
        } else {
            hint.textContent = 'Passwords do not match.';
            hint.className = 'form-text text-danger';
        }
    };

    if (showPwds && pwd && confirmPwd) {
        showPwds.addEventListener('change', function () {
            const type = this.checked ? 'text' : 'password';
            pwd.type = type;
            confirmPwd.type = type;
        });
    }
    if (pwd && confirmPwd) {
        pwd.addEventListener('input', updateHint);
        confirmPwd.addEventListener('input', updateHint);
    }
</script>
</body>
</html>
