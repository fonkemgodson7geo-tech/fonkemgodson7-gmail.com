<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'doctor', 'staff'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

$message = '';

if (isset($_POST['dispense_medication'])) {
    verifyCsrf();

    $prescription_id = (int)($_POST['prescription_id'] ?? 0);
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $payment_status = (string)($_POST['payment_status'] ?? 'unpaid');
    if (!in_array($payment_status, ['paid', 'unpaid', 'partial'], true)) {
        $payment_status = 'unpaid';
    }
    
    try {
        $pdo = getDB();

        $rxStmt = $pdo->prepare("SELECT c.patient_id FROM prescriptions pr JOIN consultations c ON pr.consultation_id = c.id WHERE pr.id = ? LIMIT 1");
        $rxStmt->execute([$prescription_id]);
        $patient_id = (int)$rxStmt->fetchColumn();

        if ($patient_id <= 0 || $quantity <= 0 || $inventory_id <= 0) {
            $message = 'Invalid dispense request.';
        } else {
            // Check stock and selling price
            $stmt = $pdo->prepare("SELECT quantity, COALESCE(unit_price, 0) AS unit_price FROM pharmacy_inventory WHERE id = ?");
            $stmt->execute([$inventory_id]);
            $stockRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$stockRow) {
                $message = 'Inventory item not found.';
            } else {
                $current_stock = (int)$stockRow['quantity'];
                $unit_price = (float)$stockRow['unit_price'];

                if ($current_stock >= $quantity) {
                    $total_amount = $unit_price * $quantity;
                    $has_debt = $payment_status === 'paid' ? 0 : 1;

                    $pdo->beginTransaction();

                    // Update inventory
                    $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $inventory_id]);

                    // Record dispense
                    $stmt = $pdo->prepare("INSERT INTO prescriptions_fulfilled (prescription_id, inventory_id, quantity_dispensed, dispensed_by, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$prescription_id, $inventory_id, $quantity, $user_id, $notes !== '' ? $notes : null]);

                    // Record sale and debt status
                    $saleStmt = $pdo->prepare("INSERT INTO pharmacy_sales (prescription_id, patient_id, inventory_id, quantity_sold, unit_price, total_amount, payment_status, has_debt, sold_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $saleStmt->execute([$prescription_id, $patient_id, $inventory_id, $quantity, $unit_price, $total_amount, $payment_status, $has_debt, $user_id, $notes !== '' ? $notes : null]);

                    // Also mirror to payments table so debt can be tracked globally
                    $payStatus = $payment_status === 'paid' ? 'completed' : 'pending';
                    $txid = 'PHARM-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    $payStmt = $pdo->prepare("INSERT INTO payments (patient_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?)");
                    $payStmt->execute([$patient_id, $total_amount, 'pharmacy', $txid, $payStatus]);

                    $pdo->commit();

                    $message = $has_debt ? 'Medication dispensed and debt recorded.' : 'Medication dispensed and marked as paid.';
                } else {
                    $message = 'Insufficient stock available';
                }
            }
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Pharmacy dispense medication error: ' . $e->getMessage());
        $message = 'Error dispensing medication. Please try again.';
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
            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
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
                            <th>Debt</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare(" 
                                SELECT p.*, pr.*, u.first_name, u.last_name, c.created_at as consultation_date, c.patient_id,
                                       (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.patient_id = c.patient_id AND pay.status = 'pending') AS pending_debt
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
                                $pendingDebt = (float)($prescription['pending_debt'] ?? 0);
                                if ($pendingDebt > 0) {
                                    echo "<td><span class='badge bg-danger'>Debt: $" . number_format($pendingDebt, 2) . "</span></td>";
                                } else {
                                    echo "<td><span class='badge bg-success'>No Debt</span></td>";
                                }
                                echo "<td><span class='badge bg-warning'>Pending</span></td>";
                                echo "<td><button class='btn btn-sm btn-success' onclick='dispenseMedication(" . $prescription['id'] . ", \"" . addslashes($prescription['medication']) . "\")'>Dispense</button></td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log('Pharmacy dispense pending prescriptions error: ' . $e->getMessage());
                            echo "<tr><td colspan='7'>Database error</td></tr>";
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
                    <?php echo csrfField(); ?>
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
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-control" id="payment_status" name="payment_status" required>
                                <option value="unpaid" selected>Unpaid (create debt)</option>
                                <option value="partial">Partial (remaining debt)</option>
                                <option value="paid">Paid</option>
                            </select>
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