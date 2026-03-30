<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$patient_id = $_SESSION['user']['id'];
$medical_records = [];
$error = '';

try {
    $pdo = getDB();
    
    // Get medical records (consultations with doctor info and diagnoses)
    $stmt = $pdo->prepare('
        SELECT c.id, c.consultation_date as record_date, c.diagnosis, c.treatment, c.notes, 
               u.first_name, u.last_name
        FROM consultations c
        JOIN users u ON c.doctor_id = u.id
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

        <?php if (count($medical_records) > 0): ?>
            <div>
                <?php foreach ($medical_records as $record): ?>
                    <div class="record-card">
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
</body>
</html>
