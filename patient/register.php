<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verifyCsrf();
    $username   = trim($_POST['username']   ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');

    // Server-side validation
    $errors = [];
    if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, dots, underscores and hyphens.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (empty($first_name) || strlen($first_name) > 80) {
        $errors[] = 'First name is required (max 80 characters).';
    }
    if (empty($last_name) || strlen($last_name) > 80) {
        $errors[] = 'Last name is required (max 80 characters).';
    }
    if (!empty($errors)) {
        $message = implode(' ', $errors);
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name) VALUES (?, ?, ?, 'patient', ?, ?)");
            $stmt->execute([$username, $email, hashPassword($password), $first_name, $last_name]);
            $message = 'Registration successful. Please sign in.';
        } catch (PDOException $e) {
            error_log('Patient registration DB error: ' . $e->getMessage());
            $errorText = strtolower($e->getMessage());
            if (str_contains($errorText, 'unique') || str_contains($errorText, 'duplicate')) {
                $message = 'That username or email is already in use. Please choose a different one.';
            } else {
                $message = 'Registration failed due to a system error. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Patient Account - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(130deg, #1d4ed8 0%, #0ea5e9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-wrap {
            width: 100%;
            max-width: 650px;
        }

        .register-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
            animation: fadeInUp 0.45s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(25px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-head {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
            padding: 1.6rem;
            text-align: center;
        }

        .register-head h3 {
            margin: 0;
            font-weight: 700;
        }

        .register-head p {
            margin: 0.5rem 0 0;
            opacity: 0.92;
        }

        .register-body {
            padding: 1.6rem;
        }

        .form-label {
            font-weight: 600;
            color: #1f2937;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.7rem 0.85rem;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.13);
        }

        .password-meter {
            height: 6px;
            background: #e5e7eb;
            border-radius: 20px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-meter-bar {
            height: 100%;
            width: 0;
            transition: width 0.25s ease;
        }

        .password-hint {
            font-size: 0.82rem;
            color: #6b7280;
            margin-top: 6px;
        }

        .submit-btn {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
            font-weight: 600;
            padding: 0.82rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(29, 78, 216, 0.33);
        }
    </style>
</head>
<body>
    <div class="register-wrap">
        <div class="register-card card">
            <div class="register-head">
                <h3><i class="bi bi-person-plus"></i> Create Patient Account</h3>
                <p>Join the portal to manage appointments and records</p>
            </div>

            <div class="register-body">
                <?php if ($message): ?>
                    <div class="alert <?php echo str_contains($message, 'successful') ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" novalidate>
                    <?php echo csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="first_name"
                                name="first_name"
                                value="<?php echo htmlspecialchars($first_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="last_name"
                                name="last_name"
                                value="<?php echo htmlspecialchars($last_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="username" class="form-label">Username</label>
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

                    <div class="mt-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            name="email"
                            value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="mt-3 mb-2">
                        <label for="password" class="form-label">Create Password</label>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            required
                        >
                        <div class="password-meter">
                            <div id="password-meter-bar" class="password-meter-bar"></div>
                        </div>
                        <div id="password-hint" class="password-hint">Use at least 8 characters including letters and numbers.</div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="submit-btn">
                            <i class="bi bi-check-circle"></i> Create Account
                        </button>
                    </div>
                </form>

                <p class="mt-3 mb-0 text-center">
                    Already registered? <a href="login.php">Sign in to your account</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const meterBar = document.getElementById('password-meter-bar');
        const passwordHint = document.getElementById('password-hint');

        passwordInput.addEventListener('input', function () {
            const value = passwordInput.value;
            let score = 0;

            if (value.length >= 8) score += 1;
            if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
            if (/\d/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;

            const width = (score / 4) * 100;
            meterBar.style.width = width + '%';

            if (score <= 1) {
                meterBar.style.background = '#dc2626';
                passwordHint.textContent = 'Weak password';
            } else if (score <= 2) {
                meterBar.style.background = '#f59e0b';
                passwordHint.textContent = 'Fair password';
            } else if (score <= 3) {
                meterBar.style.background = '#2563eb';
                passwordHint.textContent = 'Good password';
            } else {
                meterBar.style.background = '#16a34a';
                passwordHint.textContent = 'Strong password';
            }
        });
    </script>
</body>
</html>