<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Only designated admin
requireDesignatedAdmin();

$message = '';
$error = '';
$max_pharmacy_doctors = 2;

try {
    $pdo = getDB();

    $countStmt = $pdo->query('SELECT COUNT(*) FROM pharmacy_doctors');
    $current_pharmacy_doctors_count = (int)$countStmt->fetchColumn();

    // Handle adding doctor to pharmacy
    if (isset($_POST['add_pharmacy_doctor'])) {
        verifyCsrf();
        $doctor_id = (int)$_POST['doctor_id'];

        try {
            // Check if doctor already has access
            $checkStmt = $pdo->prepare("SELECT id FROM pharmacy_doctors WHERE doctor_id = ?");
            $checkStmt->execute([$doctor_id]);
            if ($checkStmt->fetchColumn()) {
                $error = 'This doctor already has pharmacy access.';
            } elseif ($current_pharmacy_doctors_count >= $max_pharmacy_doctors) {
                $error = 'Only two doctors can have pharmacy access at a time. Remove one first.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO pharmacy_doctors (doctor_id, added_by) VALUES (?, ?)");
                $stmt->execute([$doctor_id, $_SESSION['user']['id']]);
                $accessId = (int)$pdo->lastInsertId();
                writeAuditLog(
                    'add pharmacy doctor access',
                    'pharmacy_doctors',
                    $accessId,
                    null,
                    ['doctor_id' => $doctor_id, 'added_by' => (int)$_SESSION['user']['id']]
                );
                $message = 'Doctor added to pharmacy access successfully.';
                $current_pharmacy_doctors_count++;
            }
        } catch (PDOException $e) {
            error_log('Add pharmacy doctor error: ' . $e->getMessage());
            $error = 'Error adding doctor to pharmacy.';
        }
    }

    // Handle removing doctor from pharmacy
    if (isset($_POST['remove_pharmacy_doctor'])) {
        verifyCsrf();
        $pharmacy_doctor_id = (int)$_POST['pharmacy_doctor_id'];

        try {
            $existingStmt = $pdo->prepare("SELECT id, doctor_id, added_by, added_at FROM pharmacy_doctors WHERE id = ? LIMIT 1");
            $existingStmt->execute([$pharmacy_doctor_id]);
            $existingAccess = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $stmt = $pdo->prepare("DELETE FROM pharmacy_doctors WHERE id = ?");
            $stmt->execute([$pharmacy_doctor_id]);
            if ($existingAccess) {
                writeAuditLog(
                    'remove pharmacy doctor access',
                    'pharmacy_doctors',
                    $pharmacy_doctor_id,
                    $existingAccess,
                    null
                );
            }
            $message = 'Doctor removed from pharmacy access.';
            if ($current_pharmacy_doctors_count > 0) {
                $current_pharmacy_doctors_count--;
            }
        } catch (PDOException $e) {
            error_log('Remove pharmacy doctor error: ' . $e->getMessage());
            $error = 'Error removing doctor from pharmacy.';
        }
    }

    // Get all doctors with pharmacy access
    $pharmacy_doctors = [];
    try {
        $stmt = $pdo->prepare('
            SELECT pd.id as access_id, d.id, u.id as user_id, u.first_name, u.last_name, d.specialization, pd.added_at
            FROM pharmacy_doctors pd
            JOIN doctors d ON pd.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            ORDER BY pd.added_at DESC
        ');
        $stmt->execute();
        $pharmacy_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Fetch pharmacy doctors error: ' . $e->getMessage());
    }

    // Get all doctors not yet added to pharmacy
    $available_doctors = [];
    try {
        $stmt = $pdo->prepare('
            SELECT d.id, u.first_name, u.last_name, d.specialization
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.id NOT IN (SELECT doctor_id FROM pharmacy_doctors)
            ORDER BY u.first_name, u.last_name
        ');
        $stmt->execute();
        $available_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Fetch available doctors error: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log('Pharmacy doctors management error: ' . $e->getMessage());
    $error = 'Database error occurred.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Doctor Access - <?php echo SITE_NAME; ?></title>
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
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .badge {
            padding: 0.5rem 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?> - Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                    <a class="nav-link" href="manage_users.php">Users</a>
                    <a class="nav-link active" href="manage_pharmacy_doctors.php">Pharmacy Access</a>
                    <a class="nav-link" href="change_password.php">Password</a>
                    <a class="nav-link" href="../index.php?logout">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Doctor Section -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add Doctor to Pharmacy</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_doctors) && $current_pharmacy_doctors_count < $max_pharmacy_doctors): ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <div class="mb-3">
                                    <label for="doctor_id" class="form-label">Select Doctor</label>
                                    <select name="doctor_id" id="doctor_id" class="form-select" required>
                                        <option value="">-- Choose a doctor --</option>
                                        <?php foreach ($available_doctors as $doc): ?>
                                            <option value="<?php echo $doc['id']; ?>">
                                                Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                                <?php if ($doc['specialization']): ?> 
                                                    (<?php echo htmlspecialchars($doc['specialization']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="add_pharmacy_doctor" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add to Pharmacy
                                </button>
                            </form>
                        <?php elseif ($current_pharmacy_doctors_count >= $max_pharmacy_doctors): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-shield-lock"></i> Access limit reached: only 2 doctors can use pharmacy/inventory.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> All doctors have been added to pharmacy access.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="text-success"><?php echo count($pharmacy_doctors); ?> / <?php echo $max_pharmacy_doctors; ?></h3>
                                    <p class="mb-0 text-muted">Doctors With Access (Max 2)</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="text-info"><?php echo count($available_doctors); ?></h3>
                                    <p class="mb-0 text-muted">Available Doctors</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctors with Pharmacy Access Table -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Doctors With Pharmacy Access</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($pharmacy_doctors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th>Doctor Name</th>
                                    <th>Specialization</th>
                                    <th>Added Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pharmacy_doctors as $doc): ?>
                                    <tr>
                                        <td>
                                            <strong>Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($doc['specialization'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($doc['added_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="pharmacy_doctor_id" value="<?php echo $doc['access_id']; ?>">
                                                <button type="submit" name="remove_pharmacy_doctor" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Remove this doctor from pharmacy access?')">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No doctors have been added to pharmacy access yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
