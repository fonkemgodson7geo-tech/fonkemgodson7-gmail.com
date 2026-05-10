<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/attendance_payroll.php';

requireDesignatedAdmin();

$message = '';
$error = '';
$report = null;
$selectedUser = null;
$selectedYear = (int)($_GET['year'] ?? date('Y'));
$selectedMonth = (int)($_GET['month'] ?? date('m'));
$userId = (int)($_GET['user_id'] ?? 0);

// Get all staff users for the dropdown
$staffUsers = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.role, u.username
        FROM users u
        WHERE u.role IN ('doctor', 'staff', 'intern', 'trainee')
        ORDER BY u.role, u.first_name, u.last_name
    ");
    $staffUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Could not load staff list.';
}

// Generate report if user is selected
if ($userId > 0) {
    try {
        $report = payrollGetMonthlyReport($pdo, $userId, $selectedYear, $selectedMonth);

        // Get user details
        foreach ($staffUsers as $user) {
            if ($user['id'] == $userId) {
                $selectedUser = $user;
                break;
            }
        }
    } catch (PDOException $e) {
        $error = 'Could not generate attendance report.';
    }
}

// Handle payroll calculation
if (isset($_POST['calculate_payroll']) && $userId > 0) {
    try {
        // Create or get payroll period
        $periodName = sprintf('%04d-%02d Monthly Payroll', $selectedYear, $selectedMonth);
        $startDate = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
        $endDate = date('Y-m-t', strtotime($startDate));

        // Check if period exists
        $periodStmt = $pdo->prepare("SELECT id FROM payroll_periods WHERE start_date = ? AND end_date = ?");
        $periodStmt->execute([$startDate, $endDate]);
        $periodId = $periodStmt->fetchColumn();

        if (!$periodId) {
            $periodId = payrollCreatePeriod($pdo, $periodName, $startDate, $endDate, $_SESSION['user']['id']);
        }

        if ($periodId && payrollCalculateForPeriod($pdo, $periodId, $userId, $_SESSION['user']['id'])) {
            $message = 'Payroll calculated successfully!';
            // Refresh report
            $report = payrollGetMonthlyReport($pdo, $userId, $selectedYear, $selectedMonth);
        } else {
            $error = 'Failed to calculate payroll.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Attendance Reports - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .summary-card { transition: transform 0.2s; }
        .summary-card:hover { transform: translateY(-2px); }
        .status-present { background-color: #d4edda; }
        .status-late { background-color: #fff3cd; }
        .status-absent { background-color: #f8d7da; }
        .status-half-day { background-color: #e2e3e5; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link" href="manage_groups.php">Patient Groups</a>
            <a class="nav-link active" href="attendance_reports.php">Attendance Reports</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Monthly Attendance Reports</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- Report Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="user_id" class="form-label">Select Staff Member</label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">Choose staff member...</option>
                        <?php foreach ($staffUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . ucfirst($user['role']) . ')', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Generate Report</button>
                    <?php if ($userId > 0): ?>
                        <form method="post" class="d-inline">
                            <?php echo csrfField(); ?>
                            <button type="submit" name="calculate_payroll" class="btn btn-success">
                                <i class="bi bi-calculator"></i> Calculate Payroll
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($report && $selectedUser): ?>
        <!-- Employee Summary -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <?php echo htmlspecialchars($selectedUser['first_name'] . ' ' . $selectedUser['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                    - <?php echo date('F Y', strtotime($report['period']['start'])); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="summary-card card text-center p-3 mb-3">
                            <h6 class="text-muted">Present Days</h6>
                            <h3 class="text-success"><?php echo $report['summary']['present_days']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card text-center p-3 mb-3">
                            <h6 class="text-muted">Late Days</h6>
                            <h3 class="text-warning"><?php echo $report['summary']['late_days']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card text-center p-3 mb-3">
                            <h6 class="text-muted">Absent Days</h6>
                            <h3 class="text-danger"><?php echo $report['summary']['absent_days']; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card text-center p-3 mb-3">
                            <h6 class="text-muted">Punctuality</h6>
                            <h3 class="text-info"><?php echo $report['summary']['punctuality_rating']; ?>%</h3>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <strong>Total Hours:</strong> <?php echo number_format($report['summary']['total_hours'], 1); ?>h
                    </div>
                    <div class="col-md-4">
                        <strong>Overtime Hours:</strong> <?php echo number_format($report['summary']['overtime_hours'], 1); ?>h
                    </div>
                    <div class="col-md-4">
                        <strong>Total Late Minutes:</strong> <?php echo $report['summary']['late_minutes']; ?> min
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Information -->
        <?php if ($report['payroll']): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Payroll Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Salary Details</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Base Salary:</td>
                                <td class="text-end"><?php echo number_format($report['payroll']['base_salary'], 0); ?> CFA</td>
                            </tr>
                            <tr>
                                <td>Overtime Pay:</td>
                                <td class="text-end"><?php echo number_format($report['payroll']['overtime_pay'], 0); ?> CFA</td>
                            </tr>
                            <tr>
                                <td>Late Deductions:</td>
                                <td class="text-end text-danger">-<?php echo number_format($report['payroll']['late_deductions'], 0); ?> CFA</td>
                            </tr>
                            <tr class="table-active">
                                <td><strong>Net Pay:</strong></td>
                                <td class="text-end"><strong><?php echo number_format($report['payroll']['net_pay'], 0); ?> CFA</strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Attendance Summary</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Work Days:</td>
                                <td class="text-end"><?php echo $report['payroll']['total_work_days']; ?></td>
                            </tr>
                            <tr>
                                <td>Present Days:</td>
                                <td class="text-end"><?php echo $report['payroll']['total_present_days']; ?></td>
                            </tr>
                            <tr>
                                <td>Late Days:</td>
                                <td class="text-end"><?php echo $report['payroll']['total_late_days']; ?></td>
                            </tr>
                            <tr>
                                <td>Overtime Hours:</td>
                                <td class="text-end"><?php echo number_format($report['payroll']['total_overtime_hours'], 1); ?>h</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Daily Attendance Records -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Daily Attendance Records</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Hours</th>
                                <th>Late (min)</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['attendance_records'] as $record): ?>
                                <tr class="status-<?php echo str_replace('_', '-', $record['status']); ?>">
                                    <td><?php echo htmlspecialchars($record['attendance_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('l', strtotime($record['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $record['actual_check_in'] ? htmlspecialchars(date('H:i', strtotime($record['actual_check_in'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $record['actual_check_out'] ? htmlspecialchars(date('H:i', strtotime($record['actual_check_out'])), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                    <td><?php echo $record['total_hours'] ? number_format($record['total_hours'], 1) . 'h' : '-'; ?></td>
                                    <td><?php echo $record['late_minutes'] ? $record['late_minutes'] : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $record['status'] === 'present' ? 'success' :
                                                 ($record['status'] === 'late' ? 'warning' :
                                                 ($record['status'] === 'absent' ? 'danger' : 'secondary'));
                                        ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $record['status'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['notes'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>