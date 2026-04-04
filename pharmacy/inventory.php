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

if (isset($_POST['update_stock'])) {
    verifyCsrf();
    
    // Only admin can update stock
    if ($user['role'] !== 'admin') {
        $message = 'Only administrators can update stock.';
    } else {
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$quantity, $id]);
            $message = 'Stock updated successfully';
        } catch (PDOException $e) {
            error_log('Pharmacy inventory update stock error: ' . $e->getMessage());
            $message = 'Error updating stock';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Pharmacy</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="inventory.php">Inventory</a>
                <a class="nav-link" href="dispense.php">Dispense</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Inventory Management</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Medication</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT * FROM pharmacy_inventory ORDER BY medication_name");
                            $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($inventory as $item) {
                                $status = 'Normal';
                                $badge_class = 'success';
                                
                                if ($item['quantity'] <= $item['min_stock_level']) {
                                    $status = $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                    $badge_class = $item['quantity'] == 0 ? 'danger' : 'warning';
                                }
                                
                                $expiry_status = '';
                                if ($item['expiry_date']) {
                                    $days_to_expiry = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                    if ($days_to_expiry <= 30) {
                                        $expiry_status = 'Expiring Soon';
                                        $badge_class = 'danger';
                                    }
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($item['medication_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['batch_number'] ?: 'N/A') . "</td>";
                                echo "<td>" . ($item['expiry_date'] ? htmlspecialchars($item['expiry_date']) : 'N/A') . "</td>";
                                echo "<td>" . $item['quantity'] . "</td>";
                                echo "<td>$" . number_format($item['unit_price'], 2) . "</td>";
                                echo "<td><span class='badge bg-{$badge_class}'>{$status}" . ($expiry_status ? " / {$expiry_status}" : "") . "</span></td>";
                                echo "<td>";
                                if ($_SESSION['user']['role'] === 'admin') {
                                    echo "<button class='btn btn-sm btn-primary' onclick='updateStock(" . $item['id'] . ", " . $item['quantity'] . ")'>Update Stock</button>";
                                } else {
                                    echo "<span class='text-muted'>View Only</span>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            error_log('Pharmacy inventory list error: ' . $e->getMessage());
                            echo "<tr><td colspan='7'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <?php echo csrfField(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="update_id">
                        <div class="mb-3">
                            <label for="update_quantity" class="form-label">New Quantity</label>
                            <input type="number" class="form-control" id="update_quantity" name="quantity" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_stock" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStock(id, currentQuantity) {
            document.getElementById('update_id').value = id;
            document.getElementById('update_quantity').value = currentQuantity;
            new bootstrap.Modal(document.getElementById('updateStockModal')).show();
        }
    </script>
</body>
</html>