<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'doctor', 'manager'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intelligence & Reports - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Reports & Analytics</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="generate.php">Generate Report</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Business Intelligence Dashboard</h2>

        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Patients</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->query("SELECT COUNT(*) FROM patients");
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
                        <h5 class="card-title">Monthly Appointments</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())");
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
                        <h5 class="card-title">Revenue This Month</h5>
                        <h3>$<?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
                                $stmt->execute();
                                echo number_format($stmt->fetchColumn(), 2);
                            } catch (PDOException $e) {
                                echo '0.00';
                            }
                        ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Avg Patient Satisfaction</h5>
                        <h3><?php
                            // Placeholder - would need actual survey data
                            echo '4.2/5';
                        ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Appointments by Month</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Revenue by Month</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Patient Demographics</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="demographicsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Top Services</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="servicesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Recent Reports</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Type</th>
                            <th>Generated By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("
                                SELECT r.*, u.first_name, u.last_name
                                FROM reports r
                                JOIN users u ON r.generated_by = u.id
                                ORDER BY r.generated_at DESC
                                LIMIT 10
                            ");
                            $stmt->execute();
                            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($reports as $report) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($report['report_name']) . "</td>";
                                echo "<td>" . ucfirst($report['report_type']) . "</td>";
                                echo "<td>" . htmlspecialchars($report['first_name'] . ' ' . $report['last_name']) . "</td>";
                                echo "<td>" . date('Y-m-d H:i', strtotime($report['generated_at'])) . "</td>";
                                echo "<td><span class='badge bg-" . ($report['status'] == 'completed' ? 'success' : ($report['status'] == 'failed' ? 'danger' : 'warning')) . "'>" . ucfirst($report['status']) . "</span></td>";
                                echo "<td>";
                                if ($report['status'] == 'completed' && $report['file_path']) {
                                    echo "<a href='../" . htmlspecialchars($report['file_path']) . "' class='btn btn-sm btn-primary' target='_blank'>Download</a>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='6'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Appointments Chart
        const appointmentsCtx = document.getElementById('appointmentsChart').getContext('2d');
        new Chart(appointmentsCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Appointments',
                    data: [65, 59, 80, 81, 56, 85],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue ($)',
                    data: [12000, 19000, 3000, 5000, 2000, 3000],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            }
        });

        // Demographics Chart
        const demographicsCtx = document.getElementById('demographicsChart').getContext('2d');
        new Chart(demographicsCtx, {
            type: 'pie',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [45, 50, 5],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 205, 86, 0.5)'
                    ]
                }]
            }
        });

        // Services Chart
        const servicesCtx = document.getElementById('servicesChart').getContext('2d');
        new Chart(servicesCtx, {
            type: 'doughnut',
            data: {
                labels: ['General Checkup', 'Cardiology', 'Dermatology', 'Pediatrics', 'Orthopedics'],
                datasets: [{
                    data: [30, 20, 15, 25, 10],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 205, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)'
                    ]
                }]
            }
        });
    </script>
</body>
</html>