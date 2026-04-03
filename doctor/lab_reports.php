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
    error_log('Doctor lab reports profile lookup error: ' . $e->getMessage());
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_report'])) {
        $patient_id = $_POST['patient_id'];
        $test_name = $_POST['test_name'];
        $results = $_POST['results'];
        
        $image_path = null;
        if (isset($_FILES['report_image']) && $_FILES['report_image']['error'] == 0) {
            $upload_dir = UPLOAD_DIR;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = uniqid() . '_' . basename($_FILES['report_image']['name']);
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['report_image']['tmp_name'], $target_path)) {
                $image_path = $file_name;
            }
        }
        
        try {
            $pdo = getDB();
            if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                $stmt = $pdo->prepare("INSERT INTO lab_reports (patient_id, doctor_id, test_name, results, image_path, report_date) VALUES (?, ?, ?, ?, ?, date('now'))");
            } else {
                $stmt = $pdo->prepare("INSERT INTO lab_reports (patient_id, doctor_id, test_name, results, image_path, report_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
            }
            $stmt->execute([$patient_id, $doctor_id, $test_name, $results, $image_path]);
            $message = 'Lab report added successfully';
        } catch (PDOException $e) {
            error_log('Doctor add lab report error: ' . $e->getMessage());
            $message = 'Error adding lab report';
        }
    } elseif (isset($_POST['update_results'])) {
        $report_id = $_POST['report_id'];
        $results = $_POST['results'];
        
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE lab_reports SET results = ? WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$results, $report_id, $doctor_id]);
            $message = 'Results updated successfully';
        } catch (PDOException $e) {
            $message = 'Error updating results';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Reports - Doctor Dashboard</title>
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
                <a class="nav-link" href="consultations.php">Consultations</a>
                <a class="nav-link active" href="lab_reports.php">Lab Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Lab Reports Management</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Add New Report -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Add New Lab Report</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-control" id="patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php
                                    try {
                                        $pdo = getDB();
                                        $stmt = $pdo->prepare("
                                            SELECT DISTINCT p.id, u.first_name, u.last_name
                                            FROM patients p
                                            JOIN users u ON p.user_id = u.id
                                            WHERE p.id IN (SELECT patient_id FROM consultations WHERE doctor_id = ?)
                                            ORDER BY u.last_name
                                        ");
                                        $stmt->execute([$doctor_id]);
                                        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($patients as $patient) {
                                            echo "<option value='" . $patient['id'] . "'>" . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . "</option>";
                                        }
                                    } catch (PDOException $e) {
                                        // Handle error
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="test_name" class="form-label">Test Name</label>
                                <input type="text" class="form-control" id="test_name" name="test_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="results" class="form-label">Results</label>
                                <textarea class="form-control" id="results" name="results" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="report_image" class="form-label">Report Image</label>
                                <input type="file" class="form-control" id="report_image" name="report_image" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_report" class="btn btn-primary">Add Report</button>
                </form>
            </div>
        </div>

        <!-- Existing Reports -->
        <div class="card">
            <div class="card-header">
                <h5>My Lab Reports</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Results</th>
                            <th>Image</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT lr.*, u.first_name, u.last_name
                                FROM lab_reports lr
                                JOIN patients p ON lr.patient_id = p.id
                                LEFT JOIN users u ON p.user_id = u.id
                                WHERE lr.doctor_id = ?
                                ORDER BY lr.report_date DESC
                            ");
                            $stmt->execute([$doctor_id]);
                            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($reports as $report) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($report['first_name'] . ' ' . $report['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($report['test_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($report['report_date']) . "</td>";
                                echo "<td>" . htmlspecialchars(substr($report['results'], 0, 50)) . (strlen($report['results']) > 50 ? '...' : '') . "</td>";
                                echo "<td>";
                                if ($report['image_path']) {
                                    echo "<a href='../uploads/" . htmlspecialchars($report['image_path']) . "' target='_blank'>View</a>";
                                } else {
                                    echo "No image";
                                }
                                echo "</td>";
                                echo "<td>";
                                if (empty($report['results'])) {
                                    echo "<button class='btn btn-sm btn-warning' onclick='editResults(" . $report['id'] . ", \"" . addslashes($report['results']) . "\")'>Add Results</button>";
                                } else {
                                    echo "<button class='btn btn-sm btn-info' onclick='editResults(" . $report['id'] . ", \"" . addslashes($report['results']) . "\")'>Edit Results</button>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log('Doctor lab reports list error: ' . $e->getMessage());
                            echo "<tr><td colspan='6'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Results Modal -->
    <div class="modal fade" id="editResultsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Lab Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="report_id" id="edit_report_id">
                        <div class="mb-3">
                            <label for="edit_results" class="form-label">Results</label>
                            <textarea class="form-control" id="edit_results" name="results" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_results" class="btn btn-primary">Update Results</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editResults(reportId, currentResults) {
            document.getElementById('edit_report_id').value = reportId;
            document.getElementById('edit_results').value = currentResults;
            new bootstrap.Modal(document.getElementById('editResultsModal')).show();
        }
    </script>
</body>
</html>