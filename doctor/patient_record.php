<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$doctor_id = $user['id'];

$message = '';

$patient_id = $_GET['id'] ?? null;
if (!$patient_id) {
    header('Location: patients.php');
    exit;
}

try {
    $pdo = getDB();
    
    // Get patient info
    $stmt = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.email, u.phone FROM patients p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: patients.php');
        exit;
    }
    
    // Handle new consultation
    if (isset($_POST['add_consultation'])) {
        $diagnosis = $_POST['diagnosis'];
        $treatment = $_POST['treatment'];
        $notes = $_POST['notes'];
        
        $stmt = $pdo->prepare("INSERT INTO consultations (appointment_id, doctor_id, patient_id, diagnosis, treatment, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['appointment_id'] ?? null, $doctor_id, $patient_id, $diagnosis, $treatment, $notes]);
        $message = 'Consultation added successfully';
    }
    
    // Handle new prescription
    if (isset($_POST['add_prescription'])) {
        $consultation_id = $_POST['consultation_id'];
        $medication = $_POST['medication'];
        $dosage = $_POST['dosage'];
        $frequency = $_POST['frequency'];
        $duration = $_POST['duration'];
        $instructions = $_POST['instructions'];
        
        $stmt = $pdo->prepare("INSERT INTO prescriptions (consultation_id, medication, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$consultation_id, $medication, $dosage, $frequency, $duration, $instructions]);
        $message = 'Prescription added successfully';
    }
    
    // Get consultations
    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE patient_id = ? ORDER BY created_at DESC");
    $stmt->execute([$patient_id]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Doctor patient_record database error: ' . $e->getMessage());
    header('Location: patients.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
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
                <a class="nav-link active" href="#">Patient Record</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Patient Record: <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
            <a href="patients.php" class="btn btn-secondary">Back to Patients</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Patient Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Patient Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date of Birth:</strong> <?php echo $patient['date_of_birth'] ? htmlspecialchars($patient['date_of_birth']) : 'Not specified'; ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
                        <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($patient['blood_type']); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Medical History:</strong> <?php echo htmlspecialchars($patient['medical_history']); ?></p>
                        <p><strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Consultation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Add New Consultation</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="treatment" class="form-label">Treatment</label>
                        <textarea class="form-control" id="treatment" name="treatment" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_consultation" class="btn btn-primary">Add Consultation</button>
                </form>
            </div>
        </div>

        <!-- Consultations History -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Consultation History</h5>
            </div>
            <div class="card-body">
                <?php foreach ($consultations as $consultation): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <h6><?php echo date('F j, Y', strtotime($consultation['created_at'])); ?></h6>
                            <button class="btn btn-sm btn-success" onclick="togglePrescription(<?php echo $consultation['id']; ?>)">Add Prescription</button>
                        </div>
                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($consultation['diagnosis']); ?></p>
                        <p><strong>Treatment:</strong> <?php echo htmlspecialchars($consultation['treatment']); ?></p>
                        <?php if ($consultation['notes']): ?>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($consultation['notes']); ?></p>
                        <?php endif; ?>
                        
                        <!-- Prescriptions for this consultation -->
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE consultation_id = ?");
                        $stmt->execute([$consultation['id']]);
                        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($prescriptions):
                        ?>
                            <h6>Prescriptions:</h6>
                            <ul>
                                <?php foreach ($prescriptions as $prescription): ?>
                                    <li><?php echo htmlspecialchars($prescription['medication']); ?> - <?php echo htmlspecialchars($prescription['dosage']); ?> - <?php echo htmlspecialchars($prescription['frequency']); ?> for <?php echo htmlspecialchars($prescription['duration']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <!-- Add Prescription Form (hidden by default) -->
                        <div id="prescription-form-<?php echo $consultation['id']; ?>" style="display: none;" class="mt-3">
                            <form method="post">
                                <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" name="medication" class="form-control mb-2" placeholder="Medication" required>
                                        <input type="text" name="dosage" class="form-control mb-2" placeholder="Dosage" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="frequency" class="form-control mb-2" placeholder="Frequency" required>
                                        <input type="text" name="duration" class="form-control mb-2" placeholder="Duration" required>
                                    </div>
                                </div>
                                <textarea name="instructions" class="form-control mb-2" placeholder="Instructions"></textarea>
                                <button type="submit" name="add_prescription" class="btn btn-sm btn-primary">Add Prescription</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function togglePrescription(consultationId) {
            const form = document.getElementById('prescription-form-' + consultationId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>