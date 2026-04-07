<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

if (!in_array($_SESSION['user']['role'], ['admin', 'compliance_officer'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];

// Handle filters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'action' => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'table_name' => $_GET['table_name'] ?? ''
];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$query = "
    SELECT al.*, u.first_name, u.last_name, u.role
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    WHERE 1=1
";

$params = [];

if (!empty($filters['user_id'])) {
    $query .= " AND al.user_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['action'])) {
    $query .= " AND al.action LIKE ?";
    $params[] = '%' . $filters['action'] . '%';
}

if (!empty($filters['date_from'])) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['table_name'])) {
    $query .= " AND al.table_name = ?";
    $params[] = $filters['table_name'];
}

$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

try {
    $pdo = getDB();
    
    // Get total count for pagination
    $countQuery = str_replace("SELECT al.*, u.first_name, u.last_name, u.role FROM audit_logs al JOIN users u ON al.user_id = u.id WHERE 1=1", "SELECT COUNT(*) FROM audit_logs al JOIN users u ON al.user_id = u.id WHERE 1=1", $query);
    $countQuery = str_replace(" ORDER BY al.created_at DESC LIMIT ? OFFSET ?", "", $countQuery);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute(array_slice($params, 0, -2));
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get audit logs
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get users for filter dropdown
    $usersStmt = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $auditLogs = [];
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-warning">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo SITE_NAME; ?> - Audit Logs</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="audit_logs.php">Audit Logs</a>
                <a class="nav-link" href="../index.php">Back to Main</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Audit Logs</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $filters['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="action" class="form-label">Action</label>
                        <select class="form-select" id="action" name="action">
                            <option value="">All Actions</option>
                            <option value="create" <?php echo $filters['action'] == 'create' ? 'selected' : ''; ?>>Create</option>
                            <option value="update" <?php echo $filters['action'] == 'update' ? 'selected' : ''; ?>>Update</option>
                            <option value="delete" <?php echo $filters['action'] == 'delete' ? 'selected' : ''; ?>>Delete</option>
                            <option value="login" <?php echo $filters['action'] == 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="logout" <?php echo $filters['action'] == 'logout' ? 'selected' : ''; ?>>Logout</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="table_name" class="form-label">Table</label>
                        <select class="form-select" id="table_name" name="table_name">
                            <option value="">All Tables</option>
                            <option value="patients" <?php echo $filters['table_name'] == 'patients' ? 'selected' : ''; ?>>Patients</option>
                            <option value="users" <?php echo $filters['table_name'] == 'users' ? 'selected' : ''; ?>>Users</option>
                            <option value="appointments" <?php echo $filters['table_name'] == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                            <option value="consultations" <?php echo $filters['table_name'] == 'consultations' ? 'selected' : ''; ?>>Consultations</option>
                            <option value="medications" <?php echo $filters['table_name'] == 'medications' ? 'selected' : ''; ?>>Medications</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="audit_logs.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Audit Log Entries (<?php echo $totalRecords; ?> total)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No audit log entries found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                            <br><small class="text-muted"><?php echo ucfirst($log['role']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $actionText = strtolower((string)($log['action'] ?? ''));
                                            $badgeClass = 'secondary';
                                            if (strpos($actionText, 'create') === 0 || strpos($actionText, 'add') === 0) {
                                                $badgeClass = 'success';
                                            } elseif (strpos($actionText, 'update') === 0 || strpos($actionText, 'edit') === 0) {
                                                $badgeClass = 'primary';
                                            } elseif (strpos($actionText, 'delete') === 0 || strpos($actionText, 'remove') === 0) {
                                                $badgeClass = 'danger';
                                            } elseif (strpos($actionText, 'login') !== false) {
                                                $badgeClass = 'info';
                                            } elseif (strpos($actionText, 'logout') !== false) {
                                                $badgeClass = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars((string)($log['action'] ?? 'unknown')); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['table_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td>
                                            <?php if ($log['old_values'] || $log['new_values']): ?>
                                                <button
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#detailsModal"
                                                    data-old="<?php echo htmlspecialchars((string)$log['old_values'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-new="<?php echo htmlspecialchars((string)$log['new_values'], ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    View Details
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Audit log pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&amp;<?php echo htmlspecialchars(http_build_query($filters), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&amp;<?php echo htmlspecialchars(http_build_query($filters), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&amp;<?php echo htmlspecialchars(http_build_query($filters), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Audit Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailsModal = document.getElementById('detailsModal');
        detailsModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const oldValues = button.getAttribute('data-old') || '';
            const newValues = button.getAttribute('data-new') || '';

            let content = '<div class="row">';
            if (oldValues) {
                content += '<div class="col-md-6"><h6>Old Values:</h6><pre>' + oldValues + '</pre></div>';
            }
            if (newValues) {
                content += '<div class="col-md-6"><h6>New Values:</h6><pre>' + newValues + '</pre></div>';
            }
            content += '</div>';

            document.getElementById('detailsContent').innerHTML = content;
        });
    </script>
</body>
</html>