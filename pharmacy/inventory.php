<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/pharmacy_inventory.php';

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
            $accessStmt = $pdo->prepare("SELECT id FROM pharmacy_doctors WHERE doctor_id = ? AND doctor_id IN (SELECT doctor_id FROM pharmacy_doctors ORDER BY added_at ASC, id ASC LIMIT 2)");
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

if (isset($_GET['export_movements']) && $_GET['export_movements'] === '1') {
    try {
        $pdo = getDB();
        pharmacyEnsureStockMovementTable($pdo);

        $stmt = $pdo->query("SELECT m.created_at, pi.medication_name, m.movement_type, m.quantity_change, m.quantity_before, m.quantity_after, m.reason, m.note
                             FROM pharmacy_stock_movements m
                             JOIN pharmacy_inventory pi ON pi.id = m.inventory_id
                             ORDER BY m.created_at DESC, m.id DESC");

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="pharmacy_stock_movements_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['created_at', 'medication_name', 'movement_type', 'quantity_change', 'quantity_before', 'quantity_after', 'reason', 'note']);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                (string)($row['created_at'] ?? ''),
                (string)($row['medication_name'] ?? ''),
                (string)($row['movement_type'] ?? ''),
                (string)($row['quantity_change'] ?? ''),
                (string)($row['quantity_before'] ?? ''),
                (string)($row['quantity_after'] ?? ''),
                (string)($row['reason'] ?? ''),
                (string)($row['note'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    } catch (Throwable $e) {
        error_log('Pharmacy movement export error: ' . $e->getMessage());
        $message = 'Could not export stock movement CSV right now.';
    }
}

if (isset($_POST['update_stock'])) {
    verifyCsrf();
    
    // Only admin can update stock
    if ($user['role'] !== 'admin') {
        $message = 'Only administrators can update stock.';
    } else {
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        $adjustReason = trim((string)($_POST['adjust_reason'] ?? ''));
        $adjustNote = trim((string)($_POST['adjust_note'] ?? ''));
        
        try {
            $pdo = getDB();
            pharmacyEnsureStockMovementTable($pdo);

            $beforeStmt = $pdo->prepare("SELECT quantity FROM pharmacy_inventory WHERE id = ?");
            $beforeStmt->execute([$id]);
            $beforeQty = (int)$beforeStmt->fetchColumn();

            $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$quantity, $id]);

            if ($stmt->rowCount() > 0) {
                $quantityDiff = (int)$quantity - $beforeQty;
                $movementType = $quantityDiff >= 0 ? 'add' : 'adjust';
                pharmacyLogStockMovement(
                    $pdo,
                    (int)$id,
                    $movementType,
                    $quantityDiff,
                    $beforeQty,
                    (int)$quantity,
                    $adjustReason !== '' ? $adjustReason : 'Manual stock adjustment',
                    'inventory_update',
                    null,
                    (int)$user['id'],
                    $adjustNote !== '' ? $adjustNote : 'Stock adjusted from inventory page'
                );
            }

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
                <a class="nav-link" href="barcode_scanner.php">
                    <i class="bi bi-qr-code-scan"></i> Barcode Scanner
                </a>
                <a class="nav-link" href="dispense.php">Dispense</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Inventory Management</h2>
            <a href="barcode_scanner.php" class="btn btn-primary">
                <i class="bi bi-qr-code-scan"></i> Mobile Scanner
            </a>
        </div>
        
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
                            <th>Sold</th>
                            <th>Sales Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT pi.*, COALESCE(SUM(ps.quantity_sold), 0) AS sold_qty, COALESCE(SUM(ps.total_amount), 0) AS sold_amount FROM pharmacy_inventory pi LEFT JOIN pharmacy_sales ps ON ps.inventory_id = pi.id GROUP BY pi.id ORDER BY pi.medication_name");
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
                                    if ($days_to_expiry < 0) {
                                        $expiry_status = 'Expired';
                                        $badge_class = 'danger';
                                    } elseif ($days_to_expiry <= 7) {
                                        $expiry_status = 'Expiry <= 7 days';
                                        $badge_class = 'danger';
                                    } elseif ($days_to_expiry <= 30) {
                                        $expiry_status = 'Expiry <= 30 days';
                                        $badge_class = 'warning';
                                    } elseif ($days_to_expiry <= 60) {
                                        $expiry_status = 'Expiry <= 60 days';
                                        if ($badge_class === 'success') {
                                            $badge_class = 'info';
                                        }
                                    } elseif ($days_to_expiry <= 90) {
                                        $expiry_status = 'Expiry <= 90 days';
                                    }
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($item['medication_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($item['batch_number'] ?: 'N/A') . "</td>";
                                echo "<td>" . ($item['expiry_date'] ? htmlspecialchars($item['expiry_date']) : 'N/A') . "</td>";
                                echo "<td>" . $item['quantity'] . "</td>";
                                echo "<td>$" . number_format($item['unit_price'], 2) . "</td>";
                                echo "<td>" . (int)($item['sold_qty'] ?? 0) . "</td>";
                                echo "<td>$" . number_format((float)($item['sold_amount'] ?? 0), 2) . "</td>";
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
                            echo "<tr><td colspan='9'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Stock Movements</h5>
                <a class="btn btn-sm btn-outline-secondary" href="inventory.php?export_movements=1">Export CSV</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Medication</th>
                            <th>Type</th>
                            <th>Change</th>
                            <th>Before</th>
                            <th>After</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            pharmacyEnsureStockMovementTable($pdo);
                            $mvStmt = $pdo->query("SELECT m.created_at, m.movement_type, m.quantity_change, m.quantity_before, m.quantity_after, m.reason, pi.medication_name
                                                   FROM pharmacy_stock_movements m
                                                   JOIN pharmacy_inventory pi ON pi.id = m.inventory_id
                                                   ORDER BY m.created_at DESC, m.id DESC
                                                   LIMIT 50");
                            $movements = $mvStmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!$movements) {
                                echo "<tr><td colspan='7' class='text-muted text-center'>No stock movement records yet.</td></tr>";
                            } else {
                                foreach ($movements as $mv) {
                                    $change = (int)$mv['quantity_change'];
                                    $changeText = $change >= 0 ? '+' . $change : (string)$change;
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars((string)$mv['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td>' . htmlspecialchars((string)$mv['medication_name'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td>' . htmlspecialchars((string)$mv['movement_type'], ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td>' . htmlspecialchars($changeText, ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '<td>' . (int)$mv['quantity_before'] . '</td>';
                                    echo '<td>' . (int)$mv['quantity_after'] . '</td>';
                                    echo '<td>' . htmlspecialchars((string)($mv['reason'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                                    echo '</tr>';
                                }
                            }
                        } catch (PDOException $e) {
                            error_log('Pharmacy inventory movement ledger error: ' . $e->getMessage());
                            echo "<tr><td colspan='7' class='text-danger text-center'>Could not load movement ledger.</td></tr>";
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
                        <div class="mb-3">
                            <label for="adjust_reason" class="form-label">Adjustment Reason</label>
                            <select class="form-control" id="adjust_reason" name="adjust_reason">
                                <option value="Manual stock adjustment">Manual stock adjustment</option>
                                <option value="Restock received">Restock received</option>
                                <option value="Physical count correction">Physical count correction</option>
                                <option value="Damaged or wastage correction">Damaged or wastage correction</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="adjust_note" class="form-label">Note (optional)</label>
                            <textarea class="form-control" id="adjust_note" name="adjust_note" rows="2" maxlength="255"></textarea>
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