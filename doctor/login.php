<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'doctor'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verifyPassword($password, $user['password'])) {
            loginUser($user);
            header('Location: dashboard.php');
            exit;
        } else {
            $message = 'Invalid credentials';
        }
    } catch (PDOException $e) {
        error_log('Doctor login DB error: ' . $e->getMessage());
        $message = 'A system error occurred. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Sign In - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f9b7a 0%, #0f7a9b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.45s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .login-header-icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }

        .login-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .login-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .login-body {
            padding: 1.75rem 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.72rem 0.9rem;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.12);
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            color: white;
            font-weight: 600;
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            transition: all 0.2s ease;
            margin-top: 0.4rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(25, 135, 84, 0.32);
        }

        .login-footer {
            text-align: center;
            padding: 1rem 1.25rem 1.25rem;
            color: #6b7280;
            background: #f8fafc;
            font-size: 0.9rem;
        }

        .login-footer a {
            color: #198754;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-header-icon">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <h2>Doctor Portal</h2>
                <p>Clinical Access</p>
            </div>

            <div class="login-body">
                <?php if ($message): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" novalidate>
                    <?php echo csrfField(); ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person"></i> Username
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="username"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock"></i> Password
                        </label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <i class="bi bi-house-door"></i>
                <a href="register.php">Create doctor account</a> &middot;
                <a href="../index.php">Back to home</a>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>