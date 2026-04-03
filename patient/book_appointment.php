<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('patient');

$user = $_SESSION['user'];
$message = '';
$error = '';

$patientName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
if ($patientName === '') {
    $patientName = (string)($user['username'] ?? '');
}

$appointmentDate = $_POST['appointment_date'] ?? date('Y-m-d', strtotime('+1 day'));
$appointmentTime = $_POST['appointment_time'] ?? '09:00';
$appointmentDay = $_POST['appointment_day'] ?? date('l', strtotime($appointmentDate));
$reason = trim((string)($_POST['reason'] ?? ''));
$selectedDoctorId = (int)($_POST['doctor_id'] ?? 0);

if (!in_array($appointmentDay, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true)) {
    $appointmentDay = date('l', strtotime($appointmentDate));
}

$selectedDateTime = $appointmentDate . ' ' . $appointmentTime . ':00';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    verifyCsrf();

    $patientName = trim((string)($_POST['patient_name'] ?? $patientName));
    $selectedDoctorId = (int)($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim((string)($_POST['appointment_date'] ?? ''));
    $appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
    $appointmentDay = trim((string)($_POST['appointment_day'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($patientName === '' || $selectedDoctorId <= 0 || $appointmentDate === '' || $appointmentTime === '' || $appointmentDay === '' || $reason === '') {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo = getDB();

            $computedDay = date('l', strtotime($appointmentDate));
            if ($computedDay !== $appointmentDay) {
                $error = 'Selected day does not match the selected date.';
            } else {
                $selectedDateTime = $appointmentDate . ' ' . $appointmentTime . ':00';
                if (strtotime($selectedDateTime) === false || strtotime($selectedDateTime) <= time()) {
                    $error = 'Appointment must be scheduled for a future date and time.';
                } else {
                    $availabilityStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed')");
                    $availabilityStmt->execute([$selectedDoctorId, $selectedDateTime]);
                    $isTaken = (int)$availabilityStmt->fetchColumn() > 0;

                    if ($isTaken) {
                        $error = 'The selected doctor is not available at that time. Please choose another doctor or time.';
                    } else {
                        $patientRecordStmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
                        $patientRecordStmt->execute([(int)$user['id']]);
                        $patientId = (int)$patientRecordStmt->fetchColumn();

                        if ($patientId <= 0) {
                            $mrn = 'MRN-' . date('YmdHis') . '-' . (int)$user['id'];
                            $createPatientStmt = $pdo->prepare('INSERT INTO patients (user_id, medical_record_number) VALUES (?, ?)');
                            $createPatientStmt->execute([(int)$user['id'], $mrn]);
                            $patientId = (int)$pdo->lastInsertId();
                        }

                        $notes = "Patient Name: {$patientName}\nDay: {$appointmentDay}\nTime: {$appointmentTime}\nReason: {$reason}";
                        $insertStmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, service_type, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
                        $insertStmt->execute([$patientId, $selectedDoctorId, $selectedDateTime, 'General Consultation', 'pending', $notes, (int)$user['id']]);

                        $message = 'Appointment booked successfully! Awaiting doctor confirmation.';
                        $reason = '';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Book appointment error: ' . $e->getMessage());
            $error = 'Could not book appointment. Please try again.';
        }
    }
}

$doctors = [];
$availableDoctorCount = 0;
try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT d.id AS doctor_profile_id, u.id AS doctor_user_id, u.first_name, u.last_name, d.specialization,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM appointments a
                        WHERE a.doctor_id = d.id
                          AND a.appointment_date = ?
                          AND a.status IN ('pending', 'confirmed')
                    ) THEN 0
                    ELSE 1
                END AS is_available
         FROM users u
         JOIN doctors d ON d.user_id = u.id
         WHERE u.role = 'doctor'
         ORDER BY u.first_name ASC, u.last_name ASC"
    );
    $stmt->execute([$selectedDateTime]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($doctors as $doctorRow) {
        if ((int)$doctorRow['is_available'] === 1) {
            $availableDoctorCount++;
        }
    }
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
                <?php echo csrfField(); ?>

                <div class="alert alert-info">
                    <i class="bi bi-person-check"></i>
                    Doctors available for selected slot: <strong><?php echo htmlspecialchars((string)$availableDoctorCount, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>

                <div class="form-group mb-3">
                    <label for="patient_name" class="form-label"><i class="bi bi-person"></i> Patient Name *</label>
                    <input type="text" class="form-control" id="patient_name" name="patient_name" value="<?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label for="appointment_day" class="form-label"><i class="bi bi-calendar-week"></i> Day *</label>
                            <select class="form-select" id="appointment_day" name="appointment_day" required>
                                <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                    <option value="<?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $appointmentDay === $day ? 'selected' : ''; ?>><?php echo htmlspecialchars($day, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label for="appointment_date" class="form-label"><i class="bi bi-calendar-date"></i> Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" value="<?php echo htmlspecialchars($appointmentDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label for="appointment_time" class="form-label"><i class="bi bi-clock"></i> Time *</label>
                            <input type="time" class="form-control" id="appointment_time" name="appointment_time" value="<?php echo htmlspecialchars($appointmentTime, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="doctor_id" class="form-label"><i class="bi bi-person-badge"></i> Select Doctor *</label>
                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                        <option value="">-- Choose a doctor --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars((string)$doctor['doctor_profile_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDoctorId === (int)$doctor['doctor_profile_id'] ? 'selected' : ''; ?> <?php echo (int)$doctor['is_available'] === 0 ? 'disabled' : ''; ?>>
                                Dr. <?php echo htmlspecialchars(trim((string)$doctor['first_name'] . ' ' . (string)$doctor['last_name']), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($doctor['specialization'])): ?>
                                    - <?php echo htmlspecialchars((string)$doctor['specialization'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                                <?php echo (int)$doctor['is_available'] === 1 ? ' (Available)' : ' (Unavailable at selected time)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="reason" class="form-label"><i class="bi bi-chat-dots"></i> Reason for Appointment *</label>
                    <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="Describe your symptoms or medical concern..." required><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></textarea>
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
    <script>
        (function () {
            var dateInput = document.getElementById('appointment_date');
            var dayInput = document.getElementById('appointment_day');
            if (!dateInput || !dayInput) {
                return;
            }

            function syncDayFromDate() {
                if (!dateInput.value) {
                    return;
                }
                var d = new Date(dateInput.value + 'T00:00:00');
                var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var dayName = dayNames[d.getDay()];
                if (dayName) {
                    dayInput.value = dayName;
                }
            }

            dateInput.addEventListener('change', syncDayFromDate);
        })();
    </script>
</body>
</html>
