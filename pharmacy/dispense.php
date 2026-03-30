<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'doctor', 'pharmacist'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

$message = '';

if (isset($_POST['dispense_medication'])) {
    $prescription_id = $_POST['prescription_id'];
    $inventory_id = $_POST['inventory_id'];
    $quantity = $_POST['quantity'];
    $notes = $_POST['notes'];
    
    try {
        $pdo = getDB();
        
        // Check if enough stock
        $stmt = $pdo->prepare("SELECT quantity FROM pharmacy_inventory WHERE id = ?");
        $stmt->execute([$inventory_id]);
        $current_stock = $stmt->fetchColumn();
        
        if ($current_stock >= $quantity) {
            // Update inventory
            $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $inventory_id]);
            
            // Record dispense
            $stmt = $pdo->prepare("INSERT INTO prescriptions_fulfilled (prescription_id, inventory_id, quantity_dispensed, dispensed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$prescription_id, $inventory_id, $quantity, $user_id, $notes]);
            
            $message = 'Medication dispensed successfully';
        } else {
            $message = 'Insufficient stock available';
        }
    } catch (PDOException $e) {
        $message = 'Error dispensing medication: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Medication - Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Pharmacy</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="inventory.php">Inventory</a>
                <a class="nav-link active" href="dispense.php">Dispense</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Dispense Medication</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Pending Prescriptions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Pending Prescriptions</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT p.*, pr.*, u.first_name, u.last_name, c.created_at as consultation_date
                                FROM prescriptions pr
                                JOIN consultations c ON pr.consultation_id = c.id
                                JOIN patients p ON c.patient_id = p.id
                                JOIN users u ON p.user_id = u.id
                                LEFT JOIN prescriptions_fulfilled pf ON pr.id = pf.prescription_id
                                WHERE pf.id IS NULL
                                ORDER BY c.created_at DESC
                            ");
                            $stmt->execute();
                            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($prescriptions as $prescription) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($prescription['medication']) . "</td>";
                                echo "<td>" . htmlspecialchars($prescription['dosage']) . "</td>";
                                echo "<td>" . htmlspecialchars($prescription['quantity'] ?: 'Not specified') . "</td>";
                                echo "<td><span class='badge bg-warning'>Pending</span></td>";
                                echo "<td><button class='btn btn-sm btn-success' onclick='dispenseMedication(" . $prescription['id'] . ", \"" . addslashes($prescription['medication']) . "\")'>Dispense</button></td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='6'>Database error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Dispense Modal -->
    <div class="modal fade" id="dispenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dispense Medication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="prescription_id" id="dispense_prescription_id">
                        <p><strong>Medication:</strong> <span id="dispense_medication"></span></p>
                        
                        <div class="mb-3">
                            <label for="inventory_id" class="form-label">Select Inventory Item</label>
                            <select class="form-control" id="inventory_id" name="inventory_id" required>
                                <option value="">Choose medication from inventory</option>
                                <?php
                                try {
                                    $pdo = getDB();
                                    $stmt = $pdo->query("SELECT * FROM pharmacy_inventory WHERE quantity > 0 ORDER BY medication_name");
                                    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($inventory as $item) {
                                        echo "<option value='" . $item['id'] . "'>" . htmlspecialchars($item['medication_name']) . " (Stock: " . $item['quantity'] . ")</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Handle error
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity to Dispense</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="dispense_medication" class="btn btn-primary">Dispense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function dispenseMedication(prescriptionId, medication) {
            document.getElementById('dispense_prescription_id').value = prescriptionId;
            document.getElementById('dispense_medication').textContent = medication;
            new bootstrap.Modal(document.getElementById('dispenseModal')).show();
        }
    </script>
</body>
</html>