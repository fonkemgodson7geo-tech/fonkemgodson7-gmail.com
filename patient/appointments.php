<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$user = $_SESSION['user'];
$rows = [];
$error = '';

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT a.id, a.appointment_date, a.service_type, a.status, a.notes,
                u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
         FROM appointments a
         LEFT JOIN users u ON a.doctor_id = u.id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Patient appointments page error: ' . $e->getMessage());
    $error = 'Could not load your appointments.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-heart-pulse"></i> Patient Portal</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="appointments.php">Appointments</a>
            <a class="nav-link" href="records.php">Medical Records</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">My Appointments</h2>
        <a href="book_appointment.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Book New</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="5" class="text-center text-muted">No appointments yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($a['appointment_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php
                                        $docName = trim(($a['doctor_first_name'] ?? '') . ' ' . ($a['doctor_last_name'] ?? ''));
                                        echo htmlspecialchars($docName !== '' ? ('Dr. ' . $docName) : 'Unassigned', ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['service_type'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $a['status'] === 'confirmed' ? 'success' : ($a['status'] === 'pending' ? 'warning' : ($a['status'] === 'completed' ? 'info' : 'secondary')); ?>">
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
