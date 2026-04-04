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

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt', 'dcm'];
$maxUploadSize = 15 * 1024 * 1024; // 15 MB

function handleLabReportUpload(string $fieldName, array $allowedExtensions, int $maxUploadSize): ?array {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload failed. Please try again.'];
    }

    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > $maxUploadSize) {
        return ['error' => 'File size must be between 1 byte and 15 MB.'];
    }

    $originalName = (string)($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return ['error' => 'Unsupported file type. Allowed: JPG, PNG, WEBP, PDF, DOC, DOCX, TXT, DCM.'];
    }

    $uploadRoot = rtrim((string)UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
    $relativeDir = 'lab_reports';
    $targetDir = $uploadRoot . $relativeDir . DIRECTORY_SEPARATOR;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return ['error' => 'Unable to create upload directory.'];
    }

    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $safeBase = trim((string)$safeBase, '._-');
    if ($safeBase === '') {
        $safeBase = 'attachment';
    }

    $finalName = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '_' . $safeBase . '.' . $extension;
    $targetPath = $targetDir . $finalName;

    if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
        return ['error' => 'Could not save uploaded file.'];
    }

    return ['path' => $relativeDir . '/' . $finalName];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_report'])) {
        verifyCsrf();
        $patient_id = $_POST['patient_id'];
        $test_name = $_POST['test_name'];
        $results = $_POST['results'];
        
        $image_path = null;
        $upload = handleLabReportUpload('report_file', $allowedExtensions, $maxUploadSize);
        if (is_array($upload) && isset($upload['error'])) {
            $message = $upload['error'];
        } elseif (is_array($upload) && isset($upload['path'])) {
            $image_path = $upload['path'];
        }

        if ($message === '') {
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
        }
    } elseif (isset($_POST['update_results'])) {
        verifyCsrf();
        $report_id = $_POST['report_id'];
        $results = $_POST['results'];
        $newAttachmentPath = null;

        $upload = handleLabReportUpload('edit_report_file', $allowedExtensions, $maxUploadSize);
        if (is_array($upload) && isset($upload['error'])) {
            $message = $upload['error'];
        } elseif (is_array($upload) && isset($upload['path'])) {
            $newAttachmentPath = $upload['path'];
        }
        
        if ($message === '') {
            try {
                $pdo = getDB();
                if ($newAttachmentPath !== null) {
                    $stmt = $pdo->prepare("UPDATE lab_reports SET results = ?, image_path = ? WHERE id = ? AND doctor_id = ?");
                    $stmt->execute([$results, $newAttachmentPath, $report_id, $doctor_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE lab_reports SET results = ? WHERE id = ? AND doctor_id = ?");
                    $stmt->execute([$results, $report_id, $doctor_id]);
                }
                $message = 'Results updated successfully';
            } catch (PDOException $e) {
                error_log('Doctor update lab report error: ' . $e->getMessage());
                $message = 'Error updating results';
            }
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
            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Add New Report -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Add New Lab Report</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
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
                                <label for="report_file" class="form-label">Lab Attachment (Microscope/Ecography/Scan)</label>
                                <input type="file" class="form-control" id="report_file" name="report_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt,.dcm">
                                <small class="text-muted">Allowed: images, PDF, DOC, DOCX, TXT, DCM (max 15 MB)</small>
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
                            <th>Attachment</th>
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
                                    echo "<a href='../uploads/" . htmlspecialchars($report['image_path']) . "' target='_blank' rel='noopener'>Open File</a>";
                                } else {
                                    echo "No file";
                                }
                                echo "</td>";
                                echo "<td>";
                                if (empty($report['results'])) {
                                    echo "<button class='btn btn-sm btn-warning' data-report-id='" . (int)$report['id'] . "' data-results='" . htmlspecialchars((string)$report['results'], ENT_QUOTES, 'UTF-8') . "' onclick='openEditResultsModal(this)'>Add Results</button>";
                                } else {
                                    echo "<button class='btn btn-sm btn-info' data-report-id='" . (int)$report['id'] . "' data-results='" . htmlspecialchars((string)$report['results'], ENT_QUOTES, 'UTF-8') . "' onclick='openEditResultsModal(this)'>Edit Results</button>";
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
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="report_id" id="edit_report_id">
                        <div class="mb-3">
                            <label for="edit_results" class="form-label">Results</label>
                            <textarea class="form-control" id="edit_results" name="results" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_report_file" class="form-label">Replace Attachment (Optional)</label>
                            <input type="file" class="form-control" id="edit_report_file" name="edit_report_file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt,.dcm">
                            <small class="text-muted">Leave empty to keep the current file.</small>
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
        function openEditResultsModal(buttonElement) {
            const reportId = buttonElement.getAttribute('data-report-id') || '';
            const currentResults = buttonElement.getAttribute('data-results') || '';
            document.getElementById('edit_report_id').value = reportId;
            document.getElementById('edit_results').value = currentResults;
            new bootstrap.Modal(document.getElementById('editResultsModal')).show();
        }
    </script>
</body>
</html>