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
    error_log('Doctor patients profile lookup error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Doctor</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="appointments.php">Appointments</a>
                <a class="nav-link active" href="patients.php">My Patients</a>
                <a class="nav-link" href="consultations.php">Consultations</a>
                <a class="nav-link" href="lab_reports.php">Lab Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>My Patients</h2>
        
        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Last Consultation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT p.*, u.first_name, u.last_name, u.email, u.phone,
                                       (SELECT MAX(created_at) FROM consultations c WHERE c.patient_id = p.id AND c.doctor_id = ?) as last_consultation
                                FROM patients p
                                JOIN users u ON p.user_id = u.id
                                WHERE p.id IN (
                                    SELECT DISTINCT patient_id FROM consultations WHERE doctor_id = ?
                                )
                                ORDER BY last_consultation DESC
                            ");
                            $stmt->execute([$doctor_id, $doctor_id]);
                            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($patients as $patient) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($patient['phone']) . "</td>";
                                echo "<td>" . htmlspecialchars($patient['email']) . "</td>";
                                echo "<td>" . ($patient['last_consultation'] ? date('Y-m-d', strtotime($patient['last_consultation'])) : 'Never') . "</td>";
                                echo "<td>";
                                echo "<a href='patient_record.php?id=" . $patient['id'] . "' class='btn btn-sm btn-primary'>View Record</a>";
                                echo "<a href='appointments.php' class='btn btn-sm btn-info ms-1'>Appointments</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log('Doctor patients list error: ' . $e->getMessage());
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