<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$error = '';
$rows = [];

try {
    $pdo = getDB();
    $stmt = $pdo->query(
        'SELECT a.id, a.date, a.check_in, a.check_out, a.status, a.notes,
                u.first_name, u.last_name, u.role
         FROM attendance a
         JOIN users u ON a.user_id = u.id
         ORDER BY a.date DESC, a.check_in DESC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Attendance page error: ' . $e->getMessage());
    $error = 'Could not load attendance records.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link" href="manage_groups.php">Patient Groups</a>
            <a class="nav-link active" href="attendance.php">Attendance</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Staff Attendance</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Role</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="7" class="text-center text-muted">No attendance records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($a['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($a['check_in'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($a['check_out'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $a['status'] === 'present' ? 'success' : ($a['status'] === 'late' ? 'warning' : 'secondary'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($a['status']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['notes'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
