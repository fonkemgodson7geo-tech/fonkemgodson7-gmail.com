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
$doctor_profile_id = 0;

$message = '';

try {
    $pdo = getDB();
    $profileStmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ? LIMIT 1');
    $profileStmt->execute([$doctor_user_id]);
    $doctor_profile_id = (int)$profileStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Doctor appointments profile lookup error: ' . $e->getMessage());
}

if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$status, $appointment_id, $doctor_profile_id]);
        $message = 'Appointment status updated successfully';
    } catch (PDOException $e) {
        $message = 'Error updating appointment';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Doctor</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="appointments.php">Appointments</a>
                <a class="nav-link" href="patients.php">My Patients</a>
                <a class="nav-link" href="consultations.php">Consultations</a>
                <a class="nav-link" href="lab_reports.php">Lab Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>My Appointments</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT a.*, p.first_name, p.last_name, u.phone
                                FROM appointments a
                                JOIN patients p ON a.patient_id = p.id
                                JOIN users u ON p.user_id = u.id
                                WHERE a.doctor_id = ?
                                ORDER BY a.appointment_date ASC
                            ");
                            $stmt->execute([$doctor_profile_id]);
                            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($appointments as $appointment) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']) . "<br><small>" . htmlspecialchars($appointment['phone']) . "</small></td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($appointment['appointment_date'])) . "</td>";
                                echo "<td>" . htmlspecialchars($appointment['service_type']) . "</td>";
                                echo "<td><span class='badge bg-" . ($appointment['status'] == 'confirmed' ? 'success' : ($appointment['status'] == 'pending' ? 'warning' : 'secondary')) . "'>" . ucfirst($appointment['status']) . "</span></td>";
                                echo "<td>";
                                echo "<form method='post' style='display:inline;'>";
                                echo "<input type='hidden' name='appointment_id' value='" . $appointment['id'] . "'>";
                                echo "<select name='status' class='form-select form-select-sm d-inline-block w-auto me-1'>";
                                echo "<option value='pending'" . ($appointment['status'] == 'pending' ? ' selected' : '') . ">Pending</option>";
                                echo "<option value='confirmed'" . ($appointment['status'] == 'confirmed' ? ' selected' : '') . ">Confirmed</option>";
                                echo "<option value='completed'" . ($appointment['status'] == 'completed' ? ' selected' : '') . ">Completed</option>";
                                echo "<option value='cancelled'" . ($appointment['status'] == 'cancelled' ? ' selected' : '') . ">Cancelled</option>";
                                echo "</select>";
                                echo "<button type='submit' name='update_status' class='btn btn-sm btn-primary'>Update</button>";
                                echo "</form>";
                                echo "<a href='patient_record.php?id=" . $appointment['patient_id'] . "' class='btn btn-sm btn-info ms-1'>View Patient</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='5'>Database error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>