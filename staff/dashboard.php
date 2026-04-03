<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/shift_attendance.php';

requireLogin();
if (($_SESSION['user']['role'] ?? '') !== 'staff') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$totals = [
    'patients' => 'N/A',
    'appointments_today' => 'N/A',
    'reports' => 'N/A',
];
$recentAppointments = [];
$recentPayments = [];
$shiftMessage = '';
$shiftError = '';
$todayShift = null;

try {
    $pdo = getDB();
    $todayShift = shiftHandleAction($pdo, (int)$user['id'], $shiftMessage, $shiftError);
    $totals['patients'] = (string)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $totals['appointments_today'] = (string)$pdo->query("SELECT COUNT(*) FROM appointments WHERE date(appointment_date) = date('now')")->fetchColumn();
    } else {
        $totals['appointments_today'] = (string)$pdo->query('SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()')->fetchColumn();
    }
    $totals['reports'] = (string)$pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();

    $recentAppointmentsStmt = $pdo->query('SELECT id, appointment_date, service_type, status FROM appointments ORDER BY appointment_date DESC LIMIT 5');
    $recentAppointments = $recentAppointmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $recentPaymentsStmt = $pdo->query('SELECT amount, payment_method, status, payment_date FROM payments ORDER BY payment_date DESC LIMIT 5');
    $recentPayments = $recentPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Staff dashboard stats error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-info">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-briefcase"></i> Staff Portal</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link active" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="../pharmacy/dashboard.php">Pharmacy</a>
            <a class="nav-link" href="../public_communications.php">Communications</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2>Welcome, <?php echo htmlspecialchars((string)$user['first_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="text-muted">Staff operations dashboard</p>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>Shift Attendance</strong>
                <div class="text-muted small">
                    <?php if ($todayShift && !empty($todayShift['check_in'])): ?>
                        In: <?php echo htmlspecialchars((string)$todayShift['check_in'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!empty($todayShift['check_out'])): ?>
                            | Out: <?php echo htmlspecialchars((string)$todayShift['check_out'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
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

    <div class="row g-3">
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Total Patients</div><h3><?php echo htmlspecialchars($totals['patients'], ENT_QUOTES, 'UTF-8'); ?></h3></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Today's Appointments</div><h3><?php echo htmlspecialchars($totals['appointments_today'], ENT_QUOTES, 'UTF-8'); ?></h3></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Generated Reports</div><h3><?php echo htmlspecialchars($totals['reports'], ENT_QUOTES, 'UTF-8'); ?></h3></div></div></div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Recent Appointments</strong></div>
                <div class="card-body">
                    <?php if (!$recentAppointments): ?>
                        <p class="text-muted mb-0">No appointments available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>ID</th><th>Date</th><th>Service</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentAppointments as $a): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$a['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)$a['appointment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($a['service_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($a['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Recent Payments</strong></div>
                <div class="card-body">
                    <?php if (!$recentPayments): ?>
                        <p class="text-muted mb-0">No payments available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentPayments as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$p['payment_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(number_format((float)($p['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($p['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($p['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header"><strong>Quick Actions</strong></div>
        <div class="card-body d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="../pharmacy/dashboard.php"><i class="bi bi-capsule"></i> Open Pharmacy</a>
            <a class="btn btn-outline-info" href="../public_communications.php"><i class="bi bi-megaphone"></i> Post Communication</a>
            <a class="btn btn-outline-primary" href="../patient/appointments.php"><i class="bi bi-calendar-event"></i> View Patient Appointments</a>
            <a class="btn btn-outline-success" href="../reports/dashboard.php"><i class="bi bi-graph-up"></i> Reports Overview</a>
        </div>
    </div>
</div>
</body>
</html>
