<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/shift_attendance.php';

requireLogin();
if (($_SESSION['user']['role'] ?? '') !== 'trainee') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$shiftMessage = '';
$shiftError = '';
$todayShift = null;

try {
    $pdo = getDB();
    $todayShift = shiftHandleAction($pdo, (int)$user['id'], $shiftMessage, $shiftError);
} catch (Throwable $e) {
    error_log('Trainee shift attendance error: ' . $e->getMessage());
    $shiftError = 'Unable to update shift attendance right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainee Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#6f42c1;">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Trainee Portal</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="../public_communications.php">Communications</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="container mt-4">
    <h2>Welcome, <?php echo htmlspecialchars((string)$user['first_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="text-muted">Training dashboard and resources.</p>
    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Shift Attendance</strong>
                <div class="text-muted small">
                    <?php if ($todayShift && !empty($todayShift['check_in'])): ?>
                        In: <?php echo htmlspecialchars((string)$todayShift['check_in'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($todayShift['check_out'])): ?> | Out: <?php echo htmlspecialchars((string)$todayShift['check_out'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    <?php else: ?>
                        No shift record yet for today.
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" class="w-100 mt-2">
                <?php echo csrfField(); ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-7">
                        <label for="shift_note" class="form-label mb-1">End-of-shift note (optional)</label>
                        <input class="form-control form-control-sm" id="shift_note" name="shift_note" value="<?php echo htmlspecialchars((string)($todayShift['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Handover note">
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button class="btn btn-success btn-sm" type="submit" name="shift_action" value="sign_in">Sign In</button>
                        <button class="btn btn-danger btn-sm" type="submit" name="shift_action" value="sign_out">Sign Out</button>
                    </div>
                </div>
            </form>
        </div>
        <?php if ($shiftMessage): ?><div class="alert alert-success mx-3 mb-3"><?php echo htmlspecialchars($shiftMessage, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($shiftError): ?><div class="alert alert-danger mx-3 mb-3"><?php echo htmlspecialchars($shiftError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    </div>
    <div class="card shadow-sm"><div class="card-body">Review assigned learning activities and announcements here.</div></div>
</div>
</body>
</html>
