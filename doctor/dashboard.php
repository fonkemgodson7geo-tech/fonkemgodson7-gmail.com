<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/shift_attendance.php';

requireLogin();

if ($_SESSION['user']['role'] !== 'doctor') {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$doctor_user_id = (int)$user['id'];
$doctor_id = $doctor_user_id;
$doctor_profile_id = 0;
$shiftMessage = '';
$shiftError = '';
$todayShift = null;

try {
    $shiftPdo = getDB();
    $profileStmt = $shiftPdo->prepare('SELECT id FROM doctors WHERE user_id = ? LIMIT 1');
    $profileStmt->execute([$doctor_user_id]);
    $doctor_profile_id = (int)$profileStmt->fetchColumn();
    if ($doctor_profile_id > 0) {
        $doctor_id = $doctor_profile_id;
    }
    $todayShift = shiftHandleAction($shiftPdo, (int)$doctor_id, $shiftMessage, $shiftError);
} catch (Throwable $e) {
    error_log('Doctor shift attendance error: ' . $e->getMessage());
    $shiftError = 'Unable to update shift attendance right now.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.2);
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 1rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
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
            border-left: 4px solid #28a745;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.appointments {
            border-left-color: #28a745;
        }

        .stat-card.patients {
            border-left-color: #007bff;
        }

        .stat-card.reports {
            border-left-color: #ffc107;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .stat-icon.appointments {
            color: #28a745;
        }

        .stat-icon.patients {
            color: #007bff;
        }

        .stat-icon.reports {
            color: #ffc107;
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

        .stat-link {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .stat-link:hover {
            color: #20c997;
            text-decoration: underline;
        }

        .recent-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-top: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }

        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .section-body {
            padding: 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background-color: #f8f9fa;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
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

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 2rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-hospital"></i> <?php echo SITE_NAME; ?>
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
                    <a class="nav-link" href="patients.php">
                        <i class="bi bi-people"></i> My Patients
                    </a>
                    <a class="nav-link" href="../pharmacy/dashboard.php">
                        <i class="bi bi-capsule-pill"></i> Pharmacy
                    </a>
                    <a class="nav-link" href="consultations.php">
                        <i class="bi bi-chat-text"></i> Consultations
                    </a>
                    <a class="nav-link" href="lab_reports.php">
                        <i class="bi bi-file-earmark-medical"></i> Lab Reports
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
                    <h1><i class="bi bi-person-badge"></i> Welcome, Dr. <?php echo htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p><?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div style="font-size: 3rem; opacity: 0.3;">
                    <i class="bi bi-hospital"></i>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1"><i class="bi bi-clock-history"></i> Shift Attendance</h5>
                        <div class="text-muted">
                            <?php if ($todayShift && !empty($todayShift['check_in'])): ?>
                                In: <?php echo htmlspecialchars((string)$todayShift['check_in'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($todayShift['check_out'])): ?>
                                    | Out: <?php echo htmlspecialchars((string)$todayShift['check_out'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                No shift record yet for today.
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" class="w-100 mt-2">
                        <?php echo csrfField(); ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-6">
                                <label for="shift_note" class="form-label mb-1">End-of-shift note (optional)</label>
                                <input
                                    class="form-control form-control-sm"
                                    id="shift_note"
                                    name="shift_note"
                                    placeholder="Handover, pending tasks, notes"
                                    value="<?php echo htmlspecialchars((string)($todayShift['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button class="btn btn-success btn-sm" type="submit" name="shift_action" value="sign_in"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
                                <button class="btn btn-danger btn-sm" type="submit" name="shift_action" value="sign_out"><i class="bi bi-box-arrow-left"></i> Sign Out</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php if ($shiftMessage): ?>
                    <div class="alert alert-success mt-3 mb-0"><?php echo htmlspecialchars($shiftMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if ($shiftError): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($shiftError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="stat-card appointments">
                    <div class="stat-icon appointments">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                    <div class="stat-title">Today's Appointments</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND date(appointment_date) = date('now') AND status = 'confirmed'");
                            } else {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'confirmed'");
                            }
                            $stmt->execute([$doctor_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <a href="appointments.php" class="stat-link">View All →</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="stat-card patients">
                    <div class="stat-icon patients">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-title">Total Patients</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM consultations WHERE doctor_id = ?");
                            $stmt->execute([$doctor_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <a href="patients.php" class="stat-link">View List →</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="stat-card reports">
                    <div class="stat-icon reports">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <div class="stat-title">Pending Lab Reports</div>
                    <div class="stat-number"><?php
                        try {
                            $pdo = getDB();
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lab_reports WHERE doctor_id = ? AND results IS NULL");
                            $stmt->execute([$doctor_id]);
                            echo $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            echo 'N/A';
                        }
                    ?></div>
                    <div class="stat-footer">
                        <a href="lab_reports.php" class="stat-link">Manage →</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="recent-section">
            <div class="section-header">
                <h5><i class="bi bi-clock-history"></i> Recent Consultations</h5>
            </div>
            <div class="section-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="bi bi-person"></i> Patient</th>
                                <th><i class="bi bi-calendar"></i> Date</th>
                                <th><i class="bi bi-clipboard"></i> Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->prepare("
                                    SELECT c.*, u.first_name, u.last_name
                                    FROM consultations c
                                    JOIN patients p ON c.patient_id = p.id
                                    LEFT JOIN users u ON p.user_id = u.id
                                    WHERE c.doctor_id = ?
                                    ORDER BY c.created_at DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$doctor_id]);
                                $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($consultations) > 0) {
                                    foreach ($consultations as $consultation) {
                                        echo "<tr>";
                                        echo "<td><strong>" . htmlspecialchars($consultation['first_name'] . ' ' . $consultation['last_name'], ENT_QUOTES, 'UTF-8') . "</strong></td>";
                                        echo "<td>" . date('M d, Y', strtotime($consultation['created_at'])) . "</td>";
                                        echo "<td>" . htmlspecialchars(substr($consultation['diagnosis'] ?? 'N/A', 0, 40), ENT_QUOTES, 'UTF-8') . (strlen($consultation['diagnosis'] ?? '') > 40 ? '...' : '') . "</td>";
                                        echo "<td><a href='patient_record.php?id=" . htmlspecialchars($consultation['patient_id'], ENT_QUOTES, 'UTF-8') . "' class='btn btn-sm btn-success'><i class='bi bi-eye'></i> View</a></td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center text-muted'>No consultations found</td></tr>";
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='4' class='text-center text-danger'><i class='bi bi-exclamation-triangle'></i> Database error</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
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