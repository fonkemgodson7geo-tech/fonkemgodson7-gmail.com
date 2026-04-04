<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

$user = $_SESSION['user'];

// Access control: Only admin and authorized doctors
$has_pharmacy_access = false;
if ($user['role'] === 'admin') {
    $has_pharmacy_access = true;
} elseif ($user['role'] === 'doctor') {
    // Check if doctor has pharmacy access
    try {
        $pdo = getDB();
        // Get doctor profile ID
        $doctorStmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $doctorStmt->execute([$user['id']]);
        $doctor_id = $doctorStmt->fetchColumn();
        
        if ($doctor_id) {
            $accessStmt = $pdo->prepare("SELECT id FROM pharmacy_doctors WHERE doctor_id = ?");
            $accessStmt->execute([$doctor_id]);
            $has_pharmacy_access = (bool)$accessStmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log('Pharmacy access check error: ' . $e->getMessage());
    }
}

if (!$has_pharmacy_access) {
    header('Location: ../index.php');
    exit;
}

$message = '';

if (isset($_POST['add_medication'])) {
    verifyCsrf();
    
    // Only admin can add medication
    if ($user['role'] !== 'admin') {
        $message = 'Only administrators can add medications.';
    } else {
        $medication_name = $_POST['medication_name'];
        $quantity = $_POST['quantity'];
        $unit_price = $_POST['unit_price'];
        $expiry_date = $_POST['expiry_date'];
        $min_stock = $_POST['min_stock_level'];
        
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("INSERT INTO pharmacy_inventory (medication_name, quantity, unit_price, expiry_date, min_stock_level) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$medication_name, $quantity, $unit_price, $expiry_date, $min_stock]);
            $message = 'Medication added successfully';
        } catch (PDOException $e) {
            error_log('Pharmacy dashboard add medication error: ' . $e->getMessage());
            $message = 'Error adding medication. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Pharmacy</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="inventory.php">Inventory</a>
                <a class="nav-link" href="dispense.php">Dispense</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Pharmacy Management Dashboard</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Medications</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->query("SELECT COUNT(*) FROM pharmacy_inventory");
                                echo $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                echo 'N/A';
                            }
                        ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->query("SELECT COUNT(*) FROM pharmacy_inventory WHERE quantity <= min_stock_level");
                                echo $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                echo 'N/A';
                            }
                        ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Expiring Soon</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_inventory WHERE expiry_date IS NOT NULL AND date(expiry_date) <= date('now', '+30 day')");
                                } else {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_inventory WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                                }
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                echo 'N/A';
                            }
                        ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Today's Dispenses</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions_fulfilled WHERE date(dispensed_at) = date('now')");
                                } else {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions_fulfilled WHERE DATE(dispensed_at) = CURDATE()");
                                }
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                echo 'N/A';
                            }
                        ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Medication - Admin Only -->
        <?php if ($user['role'] === 'admin'): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Add New Medication</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrfField(); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="medication_name" class="form-label">Medication Name</label>
                                <input type="text" class="form-control" id="medication_name" name="medication_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="unit_price" class="form-label">Unit Price</label>
                                <input type="number" step="0.01" class="form-control" id="unit_price" name="unit_price" required>
                            </div>
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="min_stock_level" class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="10">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_medication" class="btn btn-primary">Add Medication</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i> Only administrators can add new medications. You have read-only access to manage pharmacy inventory.
        </div>
        <?php endif; ?>

        <!-- Low Stock Alert -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Low Stock Alert</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Medication</th>
                            <th>Current Stock</th>
                            <th>Min Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT * FROM pharmacy_inventory WHERE quantity <= min_stock_level ORDER BY quantity ASC");
                            $low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($low_stock as $item) {
                                $status = $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                $badge_class = $item['quantity'] == 0 ? 'danger' : 'warning';
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($item['medication_name']) . "</td>";
                                echo "<td>" . $item['quantity'] . "</td>";
                                echo "<td>" . $item['min_stock_level'] . "</td>";
                                echo "<td><span class='badge bg-{$badge_class}'>{$status}</span></td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='4'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>