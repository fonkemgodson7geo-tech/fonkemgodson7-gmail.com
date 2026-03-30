<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$patient_id = $user['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
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
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 123, 255, 0.3);
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

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 2rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: none;
            text-align: center;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .action-icon.appointments {
            color: #007bff;
        }

        .action-icon.records {
            color: #28a745;
        }

        .action-icon.payments {
            color: #ffc107;
        }

        .action-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.8rem;
        }

        .action-description {
            font-size: 0.95rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .action-button {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
            border: none;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-appointments {
            background: #007bff;
            color: white;
        }

        .btn-appointments:hover {
            background: #0056b3;
            color: white;
        }

        .btn-records {
            background: #28a745;
            color: white;
        }

        .btn-records:hover {
            background: #218838;
            color: white;
        }

        .btn-payments {
            background: #ffc107;
            color: #212529;
        }

        .btn-payments:hover {
            background: #e0a800;
            color: #212529;
        }

        .health-info-card {
            background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            border-left: 4px solid #007bff;
        }

        .health-info-title {
            font-weight: 600;
            color: #007bff;
            margin-bottom: 1rem;
        }

        .health-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0, 123, 255, 0.1);
        }

        .health-info-item:last-child {
            border-bottom: none;
        }

        .health-info-label {
            color: #495057;
            font-weight: 500;
        }

        .health-info-value {
            color: #212529;
            font-weight: 600;
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

        .quick-links {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-top: 2rem;
        }

        .quick-links-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .quick-links-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .quick-links-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .quick-links-list li:last-child {
            border-bottom: none;
        }

        .quick-links-list a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .quick-links-list a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-heart-pulse"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="appointments.php">
                        <i class="bi bi-calendar-event"></i> Appointments
                    </a>
                    <a class="nav-link" href="records.php">
                        <i class="bi bi-file-earmark-medical"></i> Medical Records
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
                    <h1><i class="bi bi-person-circle"></i> Welcome Back, <?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p>Let's keep you healthy and informed</p>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="bi bi-heart-pulse"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <div class="action-icon appointments">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <h3 class="action-title">Book Appointment</h3>
                    <p class="action-description">Schedule a consultation with a qualified doctor at your convenience.</p>
                    <a href="book_appointment.php" class="action-button btn-appointments">
                        <i class="bi bi-plus-circle"></i> Book Now
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <div class="action-icon records">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <h3 class="action-title">Medical Records</h3>
                    <p class="action-description">Access your complete medical history and historical records securely.</p>
                    <a href="records.php" class="action-button btn-records">
                        <i class="bi bi-eye"></i> View Records
                    </a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="action-card">
                    <div class="action-icon payments">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <h3 class="action-title">Payments</h3>
                    <p class="action-description">View billing information and make secure online payments.</p>
                    <a href="payments.php" class="action-button btn-payments">
                        <i class="bi bi-wallet2"></i> Manage Payments
                    </a>
                </div>
            </div>
        </div>

        <div class="health-info-card">
            <div class="health-info-title">
                <i class="bi bi-info-circle"></i> Your Health Information
            </div>
            <div class="health-info-item">
                <span class="health-info-label">Full Name:</span>
                <span class="health-info-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="health-info-item">
                <span class="health-info-label">Email:</span>
                <span class="health-info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="health-info-item">
                <span class="health-info-label">Member Since:</span>
                <span class="health-info-value"><?php echo date('M d, Y', strtotime($user['created_at'] ?? date('Y-m-d'))); ?></span>
            </div>
        </div>

        <div class="quick-links">
            <div class="quick-links-title">
                <i class="bi bi-lightning"></i> Quick Links
            </div>
            <ul class="quick-links-list">
                <li><a href="appointments.php"><i class="bi bi-check-circle"></i> View My Appointments</a></li>
                <li><a href="records.php"><i class="bi bi-clipboard"></i> Download Medical Records</a></li>
                <li><a href="../index.php"><i class="bi bi-house"></i> Go to Home Page</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <p><i class="bi bi-shield-check"></i> Your health information is secure and confidential | Last updated: <?php echo date('M d, Y H:i:s'); ?></p>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>