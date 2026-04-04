<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$patient_user_id = (int)$_SESSION['user']['id'];
$patient_id = $patient_user_id;
$medical_records = [];
$error = '';
$success = '';

try {
    $pdo = getDB();

    $patientStmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
    $patientStmt->execute([$patient_user_id]);
    $patientProfileId = (int)$patientStmt->fetchColumn();
    if ($patientProfileId > 0) {
        $patient_id = $patientProfileId;
    }
    
    // Handle delete record
    if (isset($_POST['delete_record'])) {
        verifyCsrf();
        $record_id = (int)$_POST['record_id'];
        
        try {
            // Verify this record belongs to the patient
            $verifyStmt = $pdo->prepare('SELECT patient_id FROM consultations WHERE id = ?');
            $verifyStmt->execute([$record_id]);
            $recordPatient = (int)$verifyStmt->fetchColumn();
            
            if ($recordPatient === (int)$patient_id) {
                $deleteStmt = $pdo->prepare('DELETE FROM consultations WHERE id = ?');
                $deleteStmt->execute([$record_id]);
                $success = 'Medical record deleted successfully.';
            } else {
                $error = 'Unauthorized to delete this record.';
            }
        } catch (PDOException $e) {
            error_log('Delete record error: ' . $e->getMessage());
            $error = 'Error deleting record.';
        }
    }
    
    // Handle edit record
    if (isset($_POST['edit_record'])) {
        verifyCsrf();
        $record_id = (int)$_POST['record_id'];
        $diagnosis = $_POST['diagnosis'] ?? '';
        $treatment = $_POST['treatment'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        try {
            // Verify this record belongs to the patient
            $verifyStmt = $pdo->prepare('SELECT patient_id FROM consultations WHERE id = ?');
            $verifyStmt->execute([$record_id]);
            $recordPatient = (int)$verifyStmt->fetchColumn();
            
            if ($recordPatient === (int)$patient_id) {
                $updateStmt = $pdo->prepare('UPDATE consultations SET diagnosis = ?, treatment = ?, notes = ? WHERE id = ?');
                $updateStmt->execute([$diagnosis, $treatment, $notes, $record_id]);
                $success = 'Medical record updated successfully.';
            } else {
                $error = 'Unauthorized to edit this record.';
            }
        } catch (PDOException $e) {
            error_log('Edit record error: ' . $e->getMessage());
            $error = 'Error updating record.';
        }
    }
    
    // Get medical records (consultations with doctor info and diagnoses)
    $stmt = $pdo->prepare('
        SELECT c.id, c.consultation_date as record_date, c.diagnosis, c.treatment, c.notes, 
               u.first_name, u.last_name
        FROM consultations c
        LEFT JOIN doctors d ON c.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE c.patient_id = ?
        ORDER BY c.consultation_date DESC
    ');
    $stmt->execute([$patient_id]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Patient records fetch error: ' . $e->getMessage());
    $error = 'Could not load medical records.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
            padding: 1rem 0;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
        }
        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            border-radius: 6px;
            padding: 0.5rem 1rem !important;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .page-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 123, 255, 0.3);
        }
        .page-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .record-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .record-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .record-date {
            font-weight: 600;
            color: #007bff;
            font-size: 1.1rem;
        }
        .record-doctor {
            color: #6c757d;
            font-size: 0.95rem;
        }
        .record-label {
            font-weight: 600;
            color: #212529;
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
        }
        .record-value {
            color: #495057;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .record-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }
        .record-actions .btn {
            font-size: 0.85rem;
            padding: 0.375rem 0.75rem;
        }
        @media print {
            .navbar, .page-header p, .record-actions, .sidebar {
                display: none;
            }
            .record-card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-pulse"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a class="nav-link" href="book_appointment.php"><i class="bi bi-calendar-event"></i> Book Appointment</a>
                    <a class="nav-link" href="appointments.php"><i class="bi bi-list-check"></i> My Appointments</a>
                    <a class="nav-link active" href="records.php"><i class="bi bi-file-earmark-medical"></i> Medical Records</a>
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="page-header">
            <h1><i class="bi bi-file-earmark-medical"></i> Your Medical Records</h1>
            <p>Access your complete medical history and consultation notes</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (count($medical_records) > 0): ?>
            <div>
                <?php foreach ($medical_records as $record): ?>
                    <div class="record-card" data-record-id="<?php echo (int)$record['id']; ?>">
                        <div class="record-header">
                            <div>
                                <div class="record-date">
                                    <i class="bi bi-calendar"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($record['record_date'] ?? date('Y-m-d'))); ?>
                                </div>
                                <div class="record-doctor">
                                    <i class="bi bi-person-badge"></i> Dr. <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($record['diagnosis'])): ?>
                            <div>
                                <div class="record-label"><i class="bi bi-clipboard-check"></i> Diagnosis</div>
                                <div class="record-value"><?php echo htmlspecialchars($record['diagnosis'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['treatment'])): ?>
                            <div>
                                <div class="record-label"><i class="bi bi-capsule"></i> Treatment</div>
                                <div class="record-value"><?php echo htmlspecialchars($record['treatment'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['notes'])): ?>
                            <div>
                                <div class="record-label"><i class="bi bi-sticky"></i> Additional Notes</div>
                                <div class="record-value"><?php echo htmlspecialchars($record['notes'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="record-actions">
                            <button class="btn btn-sm btn-primary" onclick="printRecord(<?php echo $record['id']; ?>)">
                                <i class="bi bi-printer"></i> Print
                            </button>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $record['id']; ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" style="display:inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                <button type="submit" name="delete_record" class="btn btn-sm btn-danger" 
                                    onclick="return confirm('Are you sure you want to delete this record?')">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?php echo $record['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Medical Record</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="diagnosis<?php echo $record['id']; ?>" class="form-label">Diagnosis</label>
                                            <textarea class="form-control" id="diagnosis<?php echo $record['id']; ?>" name="diagnosis" rows="3"><?php echo htmlspecialchars($record['diagnosis'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="treatment<?php echo $record['id']; ?>" class="form-label">Treatment</label>
                                            <textarea class="form-control" id="treatment<?php echo $record['id']; ?>" name="treatment" rows="3"><?php echo htmlspecialchars($record['treatment'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="notes<?php echo $record['id']; ?>" class="form-label">Additional Notes</label>
                                            <textarea class="form-control" id="notes<?php echo $record['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($record['notes'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="edit_record" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="record-card">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p><strong>No Medical Records Found</strong></p>
                    <p>Your medical records will appear here after your first consultation.</p>
                    <a href="book_appointment.php" class="btn btn-primary mt-3">
                        <i class="bi bi-calendar-plus"></i> Schedule Your First Appointment
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printRecord(recordId) {
            const targetRecord = document.querySelector(`.record-card[data-record-id="${recordId}"]`);
            if (!targetRecord) {
                return;
            }

            const printContent = document.createElement('div');
            const header = document.querySelector('.page-header').cloneNode(true);
            printContent.appendChild(header);

            const recordClone = targetRecord.cloneNode(true);
            const actions = recordClone.querySelector('.record-actions');
            if (actions) {
                actions.remove();
            }
            printContent.appendChild(recordClone);
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Medical Record</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
                    <style>
                        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; }
                        .page-header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; margin-bottom: 20px; }
                        .record-card { border-left: 4px solid #007bff; padding: 15px; background: white; }
                        @media print { body { padding: 0; } }
                    </style>
                </head>
                <body>
                    ${printContent.innerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }
    </script>
