<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$doctor_user_id = (int)$user['id'];
$doctor_id = $doctor_user_id;

try {
    $pdo = getDB();
    $profileStmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ? LIMIT 1');
    $profileStmt->execute([$doctor_user_id]);
    $doctorProfileId = (int)$profileStmt->fetchColumn();
    if ($doctorProfileId > 0) {
        $doctor_id = $doctorProfileId;
    }
} catch (PDOException $e) {
    error_log('Doctor consultations profile lookup error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultations - Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Doctor</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="appointments.php">Appointments</a>
                <a class="nav-link" href="patients.php">My Patients</a>
                <a class="nav-link active" href="consultations.php">Consultations</a>
                <a class="nav-link" href="lab_reports.php">Lab Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Consultation History</h2>
        
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT c.*, u.first_name, u.last_name
                                FROM consultations c
                                JOIN patients p ON c.patient_id = p.id
                                LEFT JOIN users u ON p.user_id = u.id
                                WHERE c.doctor_id = ?
                                ORDER BY c.created_at DESC
                            ");
                            $stmt->execute([$doctor_id]);
                            $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($consultations as $consultation) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($consultation['first_name'] . ' ' . $consultation['last_name']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($consultation['created_at'])) . "</td>";
                                echo "<td>" . htmlspecialchars(substr($consultation['diagnosis'], 0, 50)) . (strlen($consultation['diagnosis']) > 50 ? '...' : '') . "</td>";
                                echo "<td>" . htmlspecialchars(substr($consultation['treatment'], 0, 50)) . (strlen($consultation['treatment']) > 50 ? '...' : '') . "</td>";
                                echo "<td><a href='patient_record.php?id=" . $consultation['patient_id'] . "' class='btn btn-sm btn-primary'>View Full Record</a></td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log('Doctor consultations list error: ' . $e->getMessage());
                            echo "<tr><td colspan='5'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>