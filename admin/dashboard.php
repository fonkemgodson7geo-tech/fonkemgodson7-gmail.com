<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'admin') {
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
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .navbar {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            box-shadow: 0 2px 10px rgba(220, 53, 69, 0.2);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            transition: all 0.3s ease;
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

        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        .dashboard-header h1 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: none;
            border-left: 4px solid #dc3545;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.users {
            border-left-color: #007bff;
        }

        .stat-card.appointments {
            border-left-color: #28a745;
        }

        .stat-card.reports {
            border-left-color: #ffc107;
        }

        .stat-card.groups {
            border-left-color: #17a2b8;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .stat-icon.users {
            color: #007bff;
        }

        .stat-icon.appointments {
            color: #28a745;
        }

        .stat-icon.reports {
            color: #ffc107;
        }

        .stat-icon.groups {
            color: #17a2b8;
        }

        .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #212529;
        }

        .stat-footer {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .action-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-link:hover {
            color: #c82333;
            text-decoration: underline;
        }

        .footer-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-top: 2rem;
            text-align: center;
            color: #6c757d;
        }

        .footer-section p {
            margin: 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="bi bi-people"></i> Manage Users
                    </a>
                    <a class="nav-link" href="manage_groups.php">
                        <i class="bi bi-diagram-3"></i> Patient Groups
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="bi bi-calendar-check"></i> Attendance
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-person-check"></i> Welcome, Admin</h1>
                    <p><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card users">
                    <div class="stat-icon users">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-title">Total Users</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <a href="manage_users.php" class="action-link">Manage Users →</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card appointments">
                    <div class="stat-icon appointments">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-title">Today's Appointments</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()");
                            $stmt->execute();
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <span style="color: #28a745;">Updated just now</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card reports">
                    <div class="stat-icon reports">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <div class="stat-title">Lab Reports</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT COUNT(*) FROM lab_reports");
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        All reports on file
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="stat-card groups">
                    <div class="stat-icon groups">
                        <i class="bi bi-collection"></i>
                    </div>
                    <div class="stat-title">Patient Groups</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->query("SELECT COUNT(*) FROM patient_groups");
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <a href="manage_groups.php" class="action-link">View Groups →</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-section">
            <p><i class="bi bi-info-circle"></i> Last updated: <span id="update-time"><?php echo date('M d, Y H:i:s'); ?></span></p>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>