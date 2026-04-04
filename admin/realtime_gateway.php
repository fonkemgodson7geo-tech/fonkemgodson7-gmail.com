<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/realtime_gateway.php';

requireDesignatedAdmin();

$status = rtGatewayStatus();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'sms') {
        $phone = trim((string)($_POST['sms_phone'] ?? ''));
        $body = trim((string)($_POST['sms_body'] ?? 'Realtime SMS test from ' . SITE_NAME));
        $failure = null;
        if (rtSendSms($phone, $body, $failure)) {
            $message = 'SMS sent successfully.';
        } else {
            $error = 'SMS failed: ' . ($failure ?: 'unknown error');
        }
    } elseif ($action === 'email') {
        $email = trim((string)($_POST['email_to'] ?? ''));
        $subject = trim((string)($_POST['email_subject'] ?? (SITE_NAME . ' realtime email test')));
        $body = trim((string)($_POST['email_body'] ?? 'Realtime email test message.'));
        $failure = null;
        if (rtSendEmail($email, $subject, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')), $body, $failure)) {
            $message = 'Email sent successfully.';
        } else {
            $error = 'Email failed: ' . ($failure ?: 'unknown error');
        }
    } elseif ($action === 'voice') {
        $phone = trim((string)($_POST['voice_phone'] ?? ''));
        $failure = null;
        if (rtStartVoiceCall($phone, $failure)) {
            $message = 'Voice call initiated successfully.';
        } else {
            $error = 'Voice call failed: ' . ($failure ?: 'unknown error');
        }
    } elseif ($action === 'alert') {
        $sms = trim((string)($_POST['alert_sms'] ?? ''));
        $email = trim((string)($_POST['alert_email'] ?? ''));
        $voice = trim((string)($_POST['alert_voice'] ?? ''));
        $subject = trim((string)($_POST['alert_subject'] ?? (SITE_NAME . ' realtime alert')));
        $body = trim((string)($_POST['alert_body'] ?? 'Realtime alert test from admin console.'));
        $failure = null;
        $results = rtSendAlert(['sms', 'email', 'voice'], ['sms' => $sms, 'email' => $email, 'voice' => $voice], $subject, $body, $failure);
        $message = 'Alert dispatch results: ' . json_encode($results);
        if ($failure !== null && $failure !== '') {
            $error = 'Some channels failed: ' . $failure;
        }
    }

    $status = rtGatewayStatus();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realtime Gateway Console - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="realtime_gateway.php">Realtime</a>
            <a class="nav-link" href="logs.php">Logs</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Realtime Gateway Console</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Gateway Status</strong></div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-2">SMS: <span class="badge bg-<?php echo $status['sms'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['sms'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="col-md-2">Email: <span class="badge bg-<?php echo $status['email'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['email'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="col-md-2">Voice: <span class="badge bg-<?php echo $status['voice'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['voice'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="col-md-2">Payments: <span class="badge bg-<?php echo $status['payments'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['payments'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="col-md-2">Alerts: <span class="badge bg-<?php echo $status['alerts'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['alerts'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="col-md-2">Verification: <span class="badge bg-<?php echo $status['verification'] === 'configured' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($status['verification'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Send SMS</div><div class="card-body">
                <form method="post" class="row g-2">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="sms">
                    <div class="col-12"><input class="form-control" name="sms_phone" placeholder="Phone (+237...)" required></div>
                    <div class="col-12"><textarea class="form-control" name="sms_body" rows="3" placeholder="Message" required></textarea></div>
                    <div class="col-12"><button class="btn btn-primary w-100" type="submit">Send SMS</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Send Email</div><div class="card-body">
                <form method="post" class="row g-2">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="email">
                    <div class="col-12"><input class="form-control" name="email_to" placeholder="Email" required></div>
                    <div class="col-12"><input class="form-control" name="email_subject" placeholder="Subject" required></div>
                    <div class="col-12"><textarea class="form-control" name="email_body" rows="3" placeholder="Message" required></textarea></div>
                    <div class="col-12"><button class="btn btn-primary w-100" type="submit">Send Email</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Start Voice Call</div><div class="card-body">
                <form method="post" class="row g-2">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="voice">
                    <div class="col-12"><input class="form-control" name="voice_phone" placeholder="Phone (+237...)" required></div>
                    <div class="col-12"><button class="btn btn-primary w-100" type="submit">Start Call</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Send Multi-Channel Alert</div><div class="card-body">
                <form method="post" class="row g-2">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="alert">
                    <div class="col-12"><input class="form-control" name="alert_sms" placeholder="SMS phone (optional)"></div>
                    <div class="col-12"><input class="form-control" name="alert_email" placeholder="Email (optional)"></div>
                    <div class="col-12"><input class="form-control" name="alert_voice" placeholder="Voice phone (optional)"></div>
                    <div class="col-12"><input class="form-control" name="alert_subject" placeholder="Subject" required></div>
                    <div class="col-12"><textarea class="form-control" name="alert_body" rows="3" placeholder="Alert message" required></textarea></div>
                    <div class="col-12"><button class="btn btn-danger w-100" type="submit">Dispatch Alert</button></div>
                </form>
            </div></div>
        </div>
    </div>
</div>
</body>
</html>
