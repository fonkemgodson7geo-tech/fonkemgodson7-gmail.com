<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'doctor', 'manager'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];

$message = '';
$reportData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = $_POST['report_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $filters = $_POST['filters'] ?? [];

    try {
        $pdo = getDB();
        
        switch ($reportType) {
            case 'patient_summary':
                $query = "
                    SELECT 
                        p.*,
                        COUNT(a.id) as appointment_count,
                        COUNT(DISTINCT c.id) as consultation_count,
                        COUNT(DISTINCT pr.id) as prescription_count
                    FROM patients p
                    LEFT JOIN appointments a ON p.id = a.patient_id AND a.appointment_date BETWEEN ? AND ?
                    LEFT JOIN consultations c ON p.id = c.patient_id AND c.consultation_date BETWEEN ? AND ?
                    LEFT JOIN prescriptions pr ON p.id = pr.patient_id AND pr.prescribed_date BETWEEN ? AND ?
                    GROUP BY p.id
                    ORDER BY p.created_at DESC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'appointment_report':
                $query = "
                    SELECT 
                        a.*,
                        p.first_name as patient_first, p.last_name as patient_last,
                        d.first_name as doctor_first, d.last_name as doctor_last,
                        s.name as service_name
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN users d ON a.doctor_id = d.id
                    LEFT JOIN services s ON a.service_id = s.id
                    WHERE a.appointment_date BETWEEN ? AND ?
                    ORDER BY a.appointment_date DESC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'revenue_report':
                $query = "
                    SELECT 
                        DATE_FORMAT(payment_date, '%Y-%m') as month,
                        SUM(amount) as total_revenue,
                        COUNT(*) as payment_count
                    FROM payments
                    WHERE payment_date BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                    ORDER BY month DESC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'inventory_report':
                $query = "
                    SELECT 
                        m.*,
                        s.name as supplier_name,
                        c.name as category_name,
                        (m.stock_quantity * m.unit_price) as total_value
                    FROM medications m
                    LEFT JOIN suppliers s ON m.supplier_id = s.id
                    LEFT JOIN medication_categories c ON m.category_id = c.id
                    WHERE m.created_at BETWEEN ? AND ?
                    ORDER BY m.stock_quantity ASC
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$startDate, $endDate]);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            default:
                $message = 'Invalid report type selected.';
        }

        if ($reportData !== null) {
            // Save report metadata
            $stmt = $pdo->prepare("
                INSERT INTO reports (report_name, report_type, generated_by, parameters, status, file_path)
                VALUES (?, ?, ?, ?, 'completed', ?)
            ");
            $reportName = ucfirst(str_replace('_', ' ', $reportType)) . ' Report';
            $parameters = json_encode(['start_date' => $startDate, 'end_date' => $endDate, 'filters' => $filters]);
            $filePath = 'reports/generated/' . $reportType . '_' . date('Y-m-d_H-i-s') . '.json';
            
            $stmt->execute([$reportName, $reportType, $user['id'], $parameters, $filePath]);
            
            // Save report data
            file_put_contents('../' . $filePath, json_encode($reportData, JSON_PRETTY_PRINT));
            
            $message = 'Report generated successfully!';
        }
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Generate Report</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="generate.php">Generate Report</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Generate Custom Report</h2>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>Report Parameters</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type" required>
                                    <option value="">Select Report Type</option>
                                    <option value="patient_summary">Patient Summary Report</option>
                                    <option value="appointment_report">Appointment Report</option>
                                    <option value="revenue_report">Revenue Report</option>
                                    <option value="inventory_report">Inventory Report</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div id="additional_filters" style="display: none;">
                        <h6>Additional Filters</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="filters[department]">
                                        <option value="">All Departments</option>
                                        <option value="cardiology">Cardiology</option>
                                        <option value="dermatology">Dermatology</option>
                                        <option value="pediatrics">Pediatrics</option>
                                        <option value="orthopedics">Orthopedics</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="filters[status]">
                                        <option value="">All Statuses</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="filters[priority]">
                                        <option value="">All Priorities</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </form>
            </div>
        </div>

        <?php if ($reportData): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Report Results</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <?php if (count($reportData) > 0): ?>
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($reportData[0]) as $column): ?>
                                            <th><?php echo ucfirst(str_replace('_', ' ', $column)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php else: ?>
                                <tbody>
                                    <tr>
                                        <td colspan="100%">No data found for the selected criteria.</td>
                                    </tr>
                                </tbody>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('report_type').addEventListener('change', function() {
            const filters = document.getElementById('additional_filters');
            if (this.value === 'appointment_report') {
                filters.style.display = 'block';
            } else {
                filters.style.display = 'none';
            }
        });
    </script>
</body>
</html>