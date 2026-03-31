<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('admin');

$message = '';
$error = '';
$maxLogoBytes = 2 * 1024 * 1024;
$uploadDir = realpath(__DIR__ . '/../uploads');
if ($uploadDir === false) {
    $uploadDir = __DIR__ . '/../uploads';
}
$logoPointerFile = $uploadDir . '/site-logo.path';

function cleanupCustomLogos(string $uploadDir): void {
    foreach (glob($uploadDir . '/site-logo.*') ?: [] as $candidate) {
        if (str_ends_with($candidate, '.path')) {
            continue;
        }
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['upload_logo'])) {
        if (!isset($_FILES['logo_file']) || !is_array($_FILES['logo_file'])) {
            $error = 'Please choose an image file.';
        } else {
            $file = $_FILES['logo_file'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $error = 'Upload failed. Please try again.';
            } elseif (($file['size'] ?? 0) > $maxLogoBytes) {
                $error = 'Logo is too large. Maximum size is 2MB.';
            } else {
                $tmpPath = $file['tmp_name'] ?? '';
                $mime = '';
                if (is_file($tmpPath)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $mime = (string)finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                    }
                }

                $allowedMimeToExt = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedMimeToExt[$mime])) {
                    $error = 'Invalid logo format. Use PNG, JPG, or WEBP.';
                } else {
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                        $error = 'Upload folder is not writable.';
                    } else {
                        $ext = $allowedMimeToExt[$mime];
                        $relativePath = 'uploads/site-logo.' . $ext;
                        $targetFile = $uploadDir . '/site-logo.' . $ext;

                        cleanupCustomLogos($uploadDir);

                        if (!move_uploaded_file($tmpPath, $targetFile)) {
                            $error = 'Could not save the uploaded file.';
                        } else {
                            file_put_contents($logoPointerFile, $relativePath);
                            $message = 'Logo updated successfully.';
                        }
                    }
                }
            }
        }
    }

    if (isset($_POST['remove_logo'])) {
        cleanupCustomLogos($uploadDir);
        if (is_file($logoPointerFile)) {
            @unlink($logoPointerFile);
        }
        $message = 'Custom logo removed. Default logo is now active.';
    }
}

$currentLogo = SITE_LOGO_URL;
$usingCustomLogo = str_starts_with($currentLogo, 'uploads/site-logo.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branding - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="manage_logo.php">Branding</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link" href="manage_groups.php">Patient Groups</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="bi bi-image"></i> Branding</h2>
        <span class="badge bg-secondary">Max 2MB</span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Current Logo</h5>
                    <p class="text-muted">This is what users see in the site header.</p>
                    <div class="p-3 border rounded bg-white text-center">
                        <img
                            src="../<?php echo htmlspecialchars($currentLogo, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Current site logo"
                            style="max-width: 180px; max-height: 180px; width: auto; height: auto;"
                        >
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Active source:
                        <?php echo htmlspecialchars($usingCustomLogo ? 'Custom upload' : 'Default or environment logo', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Upload New Logo</h5>
                    <p class="text-muted">Accepted formats: PNG, JPG, WEBP.</p>

                    <form method="post" enctype="multipart/form-data" class="mb-3">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <input type="file" name="logo_file" class="form-control" accept="image/png,image/jpeg,image/webp" required>
                        </div>
                        <button type="submit" name="upload_logo" class="btn btn-danger">
                            <i class="bi bi-upload"></i> Upload Logo
                        </button>
                    </form>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <button type="submit" name="remove_logo" class="btn btn-outline-secondary" <?php echo $usingCustomLogo ? '' : 'disabled'; ?>>
                            <i class="bi bi-arrow-counterclockwise"></i> Revert To Default
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
