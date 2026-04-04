<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'compliance_officer'])) {
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
    <title>Compliance Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-warning">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Compliance</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="audit_logs.php">Audit Logs</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Compliance & Regulatory Dashboard</h2>

        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Active Audits</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->query("SELECT COUNT(*) FROM compliance_audits WHERE status IN ('planned', 'in_progress', 'active')");
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
                        <h5 class="card-title">Compliance Score</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();

                                $stmt = $pdo->query("SELECT COUNT(*) FROM compliance_issues");
                                $totalIssues = (int)$stmt->fetchColumn();

                                $stmt = $pdo->query("SELECT COUNT(*) FROM compliance_issues WHERE status IN ('resolved', 'closed')");
                                $resolvedIssues = (int)$stmt->fetchColumn();

                                $score = $totalIssues > 0 ? round(($resolvedIssues / $totalIssues) * 100) : 100;
                                echo $score . '%';
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
                        <h5 class="card-title">Open Issues</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->query("SELECT COUNT(*) FROM compliance_issues WHERE status IN ('open', 'in_progress')");
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
                        <h5 class="card-title">Upcoming Deadlines</h5>
                        <h3><?php
                            try {
                                $pdo = getDB();
                                if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM regulatory_requirements WHERE due_date BETWEEN date('now') AND date('now', '+30 day')");
                                } else {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM regulatory_requirements WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
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

        <!-- Recent Compliance Activities -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Audit Logs</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->prepare("
                                    SELECT al.*, u.first_name, u.last_name
                                    FROM audit_logs al
                                    JOIN users u ON al.user_id = u.id
                                    ORDER BY al.created_at DESC
                                    LIMIT 5
                                ");
                                $stmt->execute();
                                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($logs as $log) {
                                    $badgeClass = 'secondary';
                                    $actionText = (string)($log['action'] ?? '');
                                    if (stripos($actionText, 'create') === 0 || stripos($actionText, 'add') === 0) {
                                        $badgeClass = 'success';
                                    } elseif (stripos($actionText, 'update') === 0 || stripos($actionText, 'edit') === 0) {
                                        $badgeClass = 'primary';
                                    } elseif (stripos($actionText, 'delete') === 0 || stripos($actionText, 'remove') === 0) {
                                        $badgeClass = 'danger';
                                    } elseif (stripos($actionText, 'login') !== false) {
                                        $badgeClass = 'info';
                                    }
                                    
                                    echo "<div class='list-group-item'>";
                                    echo "<div class='d-flex w-100 justify-content-between'>";
                                    echo "<h6 class='mb-1'>" . htmlspecialchars($actionText !== '' ? $actionText : 'audit entry') . "</h6>";
                                    echo "<small class='text-muted'>" . date('M d, H:i', strtotime($log['created_at'])) . "</small>";
                                    echo "</div>";
                                    echo "<p class='mb-1'>By: " . htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) . "</p>";
                                    echo "<span class='badge bg-" . $badgeClass . "'>" . htmlspecialchars($actionText !== '' ? $actionText : 'unknown') . "</span>";
                                    echo "</div>";
                                }
                            } catch (PDOException $e) {
                                echo "<div class='list-group-item'>Database error</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Compliance Issues</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php
                            try {
                                $pdo = getDB();
                                $stmt = $pdo->prepare("
                                    SELECT ci.*, u.first_name, u.last_name
                                    FROM compliance_issues ci
                                    LEFT JOIN users u ON ci.assigned_to = u.id
                                    WHERE ci.status IN ('open', 'in_progress')
                                    ORDER BY ci.severity DESC, ci.created_at DESC
                                    LIMIT 5
                                ");
                                $stmt->execute();
                                $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($issues as $issue) {
                                    $severityClass = 'secondary';
                                    if ($issue['severity'] === 'critical') {
                                        $severityClass = 'danger';
                                    } elseif ($issue['severity'] === 'high') {
                                        $severityClass = 'warning';
                                    } elseif ($issue['severity'] === 'medium') {
                                        $severityClass = 'info';
                                    } elseif ($issue['severity'] === 'low') {
                                        $severityClass = 'secondary';
                                    }
                                    
                                    echo "<div class='list-group-item'>";
                                    echo "<div class='d-flex w-100 justify-content-between'>";
                                    echo "<h6 class='mb-1'>" . htmlspecialchars($issue['title']) . "</h6>";
                                    echo "<span class='badge bg-" . $severityClass . "'>" . ucfirst($issue['severity']) . "</span>";
                                    echo "</div>";
                                    echo "<p class='mb-1'>" . htmlspecialchars(substr($issue['description'], 0, 100)) . "...</p>";
                                    if ($issue['assigned_to']) {
                                        echo "<small class='text-muted'>Assigned to: " . htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']) . "</small>";
                                    }
                                    echo "</div>";
                                }
                            } catch (PDOException $e) {
                                echo "<div class='list-group-item'>Database error</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regulatory Requirements -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Upcoming Regulatory Deadlines</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Regulation</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $pdo = getDB();
                            if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
                                $stmt = $pdo->prepare("\
                                    SELECT * FROM regulatory_requirements 
                                    WHERE due_date >= date('now')
                                    ORDER BY due_date ASC
                                    LIMIT 10
                                ");
                            } else {
                                $stmt = $pdo->prepare("\
                                    SELECT * FROM regulatory_requirements 
                                    WHERE due_date >= CURDATE()
                                    ORDER BY due_date ASC
                                    LIMIT 10
                                ");
                            }
                            $stmt->execute();
                            $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($requirements as $req) {
                                $statusClass = 'secondary';
                                if ($req['status'] === 'completed') {
                                    $statusClass = 'success';
                                } elseif ($req['status'] === 'in_progress') {
                                    $statusClass = 'warning';
                                } elseif ($req['status'] === 'pending') {
                                    $statusClass = 'secondary';
                                } elseif ($req['status'] === 'overdue') {
                                    $statusClass = 'danger';
                                }
                                
                                $priorityClass = 'secondary';
                                if ($req['priority'] === 'high') {
                                    $priorityClass = 'danger';
                                } elseif ($req['priority'] === 'medium') {
                                    $priorityClass = 'warning';
                                } elseif ($req['priority'] === 'low') {
                                    $priorityClass = 'info';
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($req['requirement_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($req['regulation_type']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($req['due_date'])) . "</td>";
                                echo "<td><span class='badge bg-" . $statusClass . "'>" . ucfirst($req['status']) . "</span></td>";
                                echo "<td><span class='badge bg-" . $priorityClass . "'>" . ucfirst($req['priority']) . "</span></td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='5'>Database error</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Compliance Metrics -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Compliance Metrics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6>Data Privacy Compliance</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-success" style="width: 95%"></div>
                        </div>
                        <small class="text-muted">GDPR/HIPAA Compliance: 95%</small>
                    </div>
                    <div class="col-md-4">
                        <h6>Security Standards</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-info" style="width: 88%"></div>
                        </div>
                        <small class="text-muted">ISO 27001: 88%</small>
                    </div>
                    <div class="col-md-4">
                        <h6>Quality Assurance</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-warning" style="width: 92%"></div>
                        </div>
                        <small class="text-muted">Clinical Standards: 92%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>