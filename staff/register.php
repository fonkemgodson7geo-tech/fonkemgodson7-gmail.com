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
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));

    $errors = [];
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) $errors[] = 'Username must be between 3 and 50 characters.';
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) $errors[] = 'Username contains invalid characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($firstName === '' || $lastName === '') $errors[] = 'First and last name are required.';

    if ($errors) {
        $message = implode(' ', $errors);
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name) VALUES (?, ?, ?, 'staff', ?, ?)");
            $stmt->execute([$username, $email, hashPassword($password), $firstName, $lastName]);
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
<html lang="en">
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
            <form method="post" novalidate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">First Name</label><input class="form-control" name="first_name" value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Last Name</label><input class="form-control" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                </div>
                <div class="mt-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
                <button class="btn btn-info text-white w-100 mt-4" type="submit">Create Account</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="login.php">Back to staff sign in</a></p>
        </div>
    </div>
</div>
</body>
</html>
