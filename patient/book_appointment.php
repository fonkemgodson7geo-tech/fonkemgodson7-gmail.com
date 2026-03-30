<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$message = '';
$error = '';
$patient_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    verifyCsrf();
    
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($doctor_id <= 0 || empty($appointment_date) || empty($reason)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $reason, 'pending']);
            $message = 'Appointment booked successfully! Awaiting doctor confirmation.';
        } catch (PDOException $e) {
            error_log('Book appointment error: ' . $e->getMessage());
            $error = 'Could not book appointment. Please try again.';
        }
    }
}

$doctors = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, first_name, last_name FROM users WHERE role = "doctor" ORDER BY first_name ASC');
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Load doctors error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo SITE_NAME; ?></title>
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
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        .form-group label {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.75rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
            color: white;
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .back-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            color: #0056b3;
            text-decoration: underline;
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
                    <a class="nav-link active" href="book_appointment.php"><i class="bi bi-calendar-event"></i> Book Appointment</a>
                    <a class="nav-link" href="appointments.php"><i class="bi bi-list-check"></i> My Appointments</a>
                    <a class="nav-link" href="records.php"><i class="bi bi-file-earmark-medical"></i> Medical Records</a>
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="page-header">
            <h1><i class="bi bi-calendar-plus"></i> Book an Appointment</h1>
            <p>Schedule a consultation with one of our doctors</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="post" action="book_appointment.php" novalidate>
                <?php csrf_token(); ?>
                
                <div class="form-group mb-3">
                    <label for="doctor_id" class="form-label"><i class="bi bi-person-badge"></i> Select Doctor *</label>
                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                        <option value="">-- Choose a doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars($doctor['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="appointment_date" class="form-label"><i class="bi bi-calendar"></i> Preferred Date & Time *</label>
                    <input type="datetime-local" class="form-control" id="appointment_date" name="appointment_date" required>
                    <small class="text-muted">Please select a date at least 24 hours in advance</small>
                </div>

                <div class="form-group mb-3">
                    <label for="reason" class="form-label"><i class="bi bi-chat-dots"></i> Reason for Appointment *</label>
                    <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="Describe your symptoms or medical concern..." required></textarea>
                    <small class="text-muted">This helps our doctors prepare for your visit</small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <button type="submit" name="book_appointment" class="btn btn-submit w-100">
                            <i class="bi bi-check"></i> Book Appointment
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div style="text-align: center; color: #6c757d; margin-top: 2rem;">
            <p><a href="appointments.php" class="back-link">← View My Appointments</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
