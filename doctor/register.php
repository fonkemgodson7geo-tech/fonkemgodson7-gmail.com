<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$message = '';
$username = '';
$email = '';
$firstName = '';
$lastName = '';
$specialization = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? 'General Medicine'));

    $errors = [];
    if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    }
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $errors[] = 'Username contains invalid characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    }
    if ($phone === '' || !preg_match('/^\+?[0-9][0-9\s\-\.]{6,19}$/', $phone)) {
        $errors[] = 'A valid phone number is required.';
    }

    if ($errors) {
        $message = implode(' ', $errors);
    } else {
        // Handle passport photo upload
        $photoPath = '';
        $uploadOk = false;
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
            $message = 'A passport size photo is required.';
        } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Photo upload failed (error code ' . (int)$_FILES['photo']['error'] . '). Please try again.';
        } else {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 2 * 1024 * 1024;
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($_FILES['photo']['tmp_name']);
            if (!in_array($mime, $allowedMimes, true)) {
                $message = 'Photo must be a JPG, PNG, WebP or GIF image.';
            } elseif ($_FILES['photo']['size'] > $maxSize) {
                $message = 'Photo must be smaller than 2 MB.';
            } else {
                $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                $ext = $extMap[$mime];
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username) . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../uploads/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $safeName)) {
                    $photoPath = 'uploads/photos/' . $safeName;
                    $uploadOk = true;
                } else {
                    $message = 'Failed to save photo. Please try again.';
                }
            }
        }

        if ($uploadOk) {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            $insertUser = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, photo) VALUES (?, ?, ?, 'doctor', ?, ?, ?, ?)");
            $insertUser->execute([$username, $email, hashPassword($password), $firstName, $lastName, $phone, $photoPath]);
            $userId = (int)$pdo->lastInsertId();

            $insertDoctor = $pdo->prepare('INSERT INTO doctors (user_id, specialization) VALUES (?, ?)');
            $insertDoctor->execute([$userId, $specialization !== '' ? $specialization : 'General Medicine']);

            $pdo->commit();
            $message = 'Doctor account created successfully. You can now sign in.';
            $username = '';
            $email = '';
            $firstName = '';
            $lastName = '';
            $specialization = '';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Doctor register DB error: ' . $e->getMessage());
            $message = 'Registration failed due to a system error.';
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
    <title>Create Doctor Account - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:620px;">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white"><h4 class="mb-0">Create Doctor Account</h4></div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert <?php echo str_contains($message, 'successfully') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" novalidate>
                <?php echo csrfField(); ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">First Name</label><input class="form-control" name="first_name" value="<?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Last Name</label><input class="form-control" name="last_name" value="<?php echo htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                </div>
                <div class="mt-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Specialization</label><input class="form-control" name="specialization" value="<?php echo htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="mt-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
                <div class="mt-3"><label class="form-label">Phone Number</label><input type="tel" class="form-control" name="phone" placeholder="+237 6XX XXX XXX" value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="mt-3"><label class="form-label">Passport Size Photo <span class="text-danger">*</span></label><input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required><div class="form-text">Upload a clear passport-size photo (JPG, PNG or WebP, max 2 MB).</div></div>
                <button class="btn btn-success w-100 mt-4" type="submit">Create Account</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="login.php">Back to doctor sign in</a></p>
        </div>
    </div>
</div>
</body>
</html>
