<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];

$shiftFilters = [
    'event_type' => trim((string)($_GET['shift_event_type'] ?? '')),
    'username' => trim((string)($_GET['shift_username'] ?? '')),
    'date_from' => trim((string)($_GET['shift_date_from'] ?? '')),
    'date_to' => trim((string)($_GET['shift_date_to'] ?? '')),
];

$salesFilters = [
    'payment_status' => trim((string)($_GET['sales_payment_status'] ?? '')),
    'has_debt' => trim((string)($_GET['sales_has_debt'] ?? '')),
    'patient_name' => trim((string)($_GET['sales_patient_name'] ?? '')),
    'date_from' => trim((string)($_GET['sales_date_from'] ?? '')),
    'date_to' => trim((string)($_GET['sales_date_to'] ?? '')),
];

$shiftEvents = [];
$salesRows = [];
$error = '';

function csvOutput(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

try {
    $pdo = getDB();

    // Shift events query
    $shiftSql = "
        SELECT se.id, se.event_type, se.event_time, se.shift_date, se.status, se.note,
               u.username AS worker_username, u.role AS worker_role,
               pu.username AS partner_username
        FROM shift_events se
        JOIN users u ON se.user_id = u.id
        LEFT JOIN users pu ON se.partner_user_id = pu.id
        WHERE 1=1
    ";
    $shiftParams = [];

    if ($shiftFilters['event_type'] !== '') {
        $shiftSql .= ' AND se.event_type = ?';
        $shiftParams[] = $shiftFilters['event_type'];
    }
    if ($shiftFilters['username'] !== '') {
        $shiftSql .= ' AND lower(u.username) LIKE ?';
        $shiftParams[] = '%' . strtolower($shiftFilters['username']) . '%';
    }
    if ($shiftFilters['date_from'] !== '') {
        $shiftSql .= ' AND DATE(se.event_time) >= ?';
        $shiftParams[] = $shiftFilters['date_from'];
    }
    if ($shiftFilters['date_to'] !== '') {
        $shiftSql .= ' AND DATE(se.event_time) <= ?';
        $shiftParams[] = $shiftFilters['date_to'];
    }

    $shiftSql .= ' ORDER BY se.event_time DESC LIMIT 250';

    $shiftStmt = $pdo->prepare($shiftSql);
    $shiftStmt->execute($shiftParams);
    $shiftEvents = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

    // Sales query
    $salesSql = "
        SELECT ps.id, ps.sold_at, ps.quantity_sold, ps.unit_price, ps.total_amount,
               ps.payment_status, ps.has_debt, ps.note,
               seller.username AS sold_by_username,
               inv.medication_name,
               pu.first_name AS patient_first_name, pu.last_name AS patient_last_name
        FROM pharmacy_sales ps
        LEFT JOIN users seller ON ps.sold_by = seller.id
        LEFT JOIN pharmacy_inventory inv ON ps.inventory_id = inv.id
        LEFT JOIN patients p ON ps.patient_id = p.id
        LEFT JOIN users pu ON p.user_id = pu.id
        WHERE 1=1
    ";
    $salesParams = [];

    if ($salesFilters['payment_status'] !== '') {
        $salesSql .= ' AND ps.payment_status = ?';
        $salesParams[] = $salesFilters['payment_status'];
    }
    if ($salesFilters['has_debt'] !== '') {
        $salesSql .= ' AND ps.has_debt = ?';
        $salesParams[] = (int)$salesFilters['has_debt'];
    }
    if ($salesFilters['patient_name'] !== '') {
        $needle = '%' . strtolower($salesFilters['patient_name']) . '%';
        $salesSql .= ' AND (lower(COALESCE(pu.first_name, "")) LIKE ? OR lower(COALESCE(pu.last_name, "")) LIKE ?)';
        $salesParams[] = $needle;
        $salesParams[] = $needle;
    }
    if ($salesFilters['date_from'] !== '') {
        $salesSql .= ' AND DATE(ps.sold_at) >= ?';
        $salesParams[] = $salesFilters['date_from'];
    }
    if ($salesFilters['date_to'] !== '') {
        $salesSql .= ' AND DATE(ps.sold_at) <= ?';
        $salesParams[] = $salesFilters['date_to'];
    }

    $salesSql .= ' ORDER BY ps.sold_at DESC LIMIT 250';

    $salesStmt = $pdo->prepare($salesSql);
    $salesStmt->execute($salesParams);
    $salesRows = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

    // CSV exports
    $export = trim((string)($_GET['export'] ?? ''));
    if ($export === 'shift') {
        $csvRows = [];
        foreach ($shiftEvents as $row) {
            $csvRows[] = [
                (string)$row['event_time'],
                (string)$row['worker_username'],
                (string)$row['worker_role'],
                (string)$row['event_type'],
                (string)($row['partner_username'] ?? ''),
                (string)$row['status'],
                (string)($row['note'] ?? ''),
            ];
        }
        csvOutput('shift_events.csv', ['Event Time', 'Worker', 'Role', 'Event Type', 'Partner', 'Status', 'Note'], $csvRows);
    }

    if ($export === 'sales') {
        $csvRows = [];
        foreach ($salesRows as $row) {
            $patientName = trim((string)($row['patient_first_name'] ?? '') . ' ' . (string)($row['patient_last_name'] ?? ''));
            $csvRows[] = [
                (string)$row['sold_at'],
                $patientName,
                (string)($row['medication_name'] ?? ''),
                (string)$row['quantity_sold'],
                (string)$row['unit_price'],
                (string)$row['total_amount'],
                (string)$row['payment_status'],
                ((int)$row['has_debt'] === 1 ? 'Yes' : 'No'),
                (string)($row['sold_by_username'] ?? ''),
                (string)($row['note'] ?? ''),
            ];
        }
        csvOutput('pharmacy_sales.csv', ['Sold At', 'Patient', 'Medication', 'Qty', 'Unit Price', 'Total', 'Payment Status', 'Has Debt', 'Sold By', 'Note'], $csvRows);
    }
} catch (PDOException $e) {
    $error = 'Unable to load operations records: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Records - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?></a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link" href="logs.php">Logs</a>
            <a class="nav-link active" href="operations_records.php">Operations</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clipboard-data"></i> Operations Records</h2>
            <small class="text-muted">Shift events and pharmacy sales/debt records</small>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Shift Event Records</strong>
            <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'shift'])); ?>">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Event Type</label>
                    <select class="form-select" name="shift_event_type">
                        <option value="">All</option>
                        <?php foreach (['sign_in', 'sign_out', 'shift_change', 'shift_swap'] as $type): ?>
                            <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $shiftFilters['event_type'] === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Worker Username</label>
                    <input type="text" class="form-control" name="shift_username" value="<?php echo htmlspecialchars($shiftFilters['username'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="username">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="shift_date_from" value="<?php echo htmlspecialchars($shiftFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="shift_date_to" value="<?php echo htmlspecialchars($shiftFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button class="btn btn-danger" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="operations_records.php">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Event Time</th>
                            <th>Worker</th>
                            <th>Role</th>
                            <th>Event Type</th>
                            <th>Partner</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$shiftEvents): ?>
                            <tr><td colspan="7" class="text-center text-muted">No shift events found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($shiftEvents as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$row['event_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row['worker_username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row['worker_role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$row['event_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars((string)($row['partner_username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Pharmacy Sales and Debt Records</strong>
            <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'sales'])); ?>">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Payment Status</label>
                    <select class="form-select" name="sales_payment_status">
                        <option value="">All</option>
                        <?php foreach (['paid', 'partial', 'unpaid'] as $status): ?>
                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $salesFilters['payment_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Has Debt</label>
                    <select class="form-select" name="sales_has_debt">
                        <option value="">All</option>
                        <option value="1" <?php echo $salesFilters['has_debt'] === '1' ? 'selected' : ''; ?>>Yes</option>
                        <option value="0" <?php echo $salesFilters['has_debt'] === '0' ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control" name="sales_patient_name" value="<?php echo htmlspecialchars($salesFilters['patient_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="patient name">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="sales_date_from" value="<?php echo htmlspecialchars($salesFilters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="sales_date_to" value="<?php echo htmlspecialchars($salesFilters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-danger" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary" href="operations_records.php">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Sold At</th>
                            <th>Patient</th>
                            <th>Medication</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Debt</th>
                            <th>Sold By</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$salesRows): ?>
                            <tr><td colspan="10" class="text-center text-muted">No sales records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($salesRows as $row): ?>
                                <?php $patientName = trim((string)($row['patient_first_name'] ?? '') . ' ' . (string)($row['patient_last_name'] ?? '')); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$row['sold_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($patientName !== '' ? $patientName : 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($row['medication_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$row['quantity_sold']; ?></td>
                                    <td>$<?php echo number_format((float)$row['unit_price'], 2); ?></td>
                                    <td>$<?php echo number_format((float)$row['total_amount'], 2); ?></td>
                                    <td><span class="badge bg-<?php echo ($row['payment_status'] === 'paid' ? 'success' : ($row['payment_status'] === 'partial' ? 'warning' : 'danger')); ?>"><?php echo htmlspecialchars((string)$row['payment_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo ((int)$row['has_debt'] === 1 ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($row['sold_by_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
