<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();
if (($_SESSION['user']['role'] ?? '') !== 'trainee') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'confirm') {
        logout();
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container" style="max-width:480px;">
    <div class="card shadow-sm"><div class="card-body text-center">
        <h4>Confirm Logout</h4>
        <form method="post" class="d-flex gap-2 justify-content-center">
            <?php echo csrfField(); ?>
            <button class="btn btn-danger" type="submit" name="action" value="confirm">Logout</button>
            <button class="btn btn-secondary" type="button" onclick="window.history.back();">Cancel</button>
        </form>
    </div></div>
</div>
</body>
</html>
