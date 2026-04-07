<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];

$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'table_name' => trim((string)($_GET['table_name'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

$auditLogs = [];
$users = [];
$totalRecords = 0;
$totalPages = 0;

try {
    $pdo = getDB();

    $baseFrom = ' FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1';
    $whereClauses = '';
    $params = [];

    if ($filters['user_id'] !== '') {
        $whereClauses .= ' AND al.user_id = ?';
        $params[] = (int)$filters['user_id'];
    }

    if ($filters['table_name'] !== '') {
        $whereClauses .= ' AND al.table_name = ?';
        $params[] = $filters['table_name'];
    }

    if ($filters['action'] !== '') {
        $whereClauses .= ' AND al.action LIKE ?';
        $params[] = '%' . $filters['action'] . '%';
    }

    if ($filters['date_from'] !== '') {
        $whereClauses .= ' AND DATE(al.created_at) >= ?';
        $params[] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $whereClauses .= ' AND DATE(al.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    $countSql = 'SELECT COUNT(*)' . $baseFrom . $whereClauses;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $perPage) : 0;

    $querySql = 'SELECT al.id, al.user_id, al.action, al.table_name, al.record_id, al.old_values, al.new_values, al.ip_address, al.created_at, u.first_name, u.last_name, u.role'
        . $baseFrom
        . $whereClauses
        . ' ORDER BY al.created_at DESC LIMIT ? OFFSET ?';
    $queryParams = $params;
    $queryParams[] = $perPage;
    $queryParams[] = $offset;

    $stmt = $pdo->prepare($querySql);
    $stmt->execute($queryParams);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $usersStmt = $pdo->query('SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name');
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Unable to load audit logs: ' . $e->getMessage();
}

function actionBadgeClass(string $action): string {
    $normalized = strtolower(trim($action));
    if (strpos($normalized, 'create') === 0 || strpos($normalized, 'add') === 0) {
        return 'success';
    }
    if (strpos($normalized, 'update') === 0 || strpos($normalized, 'edit') === 0) {
        return 'primary';
    }
    if (strpos($normalized, 'delete') === 0 || strpos($normalized, 'remove') === 0) {
        return 'danger';
    }
    if (strpos($normalized, 'login') === 0 || strpos($normalized, 'signin') === 0) {
        return 'info';
    }
    if (strpos($normalized, 'logout') === 0 || strpos($normalized, 'signout') === 0) {
        return 'secondary';
    }
    return 'dark';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                    <a class="nav-link" href="manage_users.php">Manage Users</a>
                    <a class="nav-link" href="operations_records.php">Operations</a>
                    <a class="nav-link active" href="logs.php">Logs</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0"><i class="bi bi-journal-text"></i> Admin Audit Logs</h2>
                <small class="text-muted">Signed in as <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <strong>Filter Logs</strong>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">User</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">All users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo $filters['user_id'] !== '' && (int)$filters['user_id'] === (int)$u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="action" class="form-label">Action contains</label>
                        <input type="text" class="form-control" id="action" name="action" value="<?php echo htmlspecialchars($filters['action'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="create, update, login...">
                    </div>
                    <div class="col-md-2">
                        <label for="table_name" class="form-label">Table</label>
                        <input type="text" class="form-control" id="table_name" name="table_name" value="<?php echo htmlspecialchars($filters['table_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="users">
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply</button>
                        <a href="logs.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Entries</strong>
                <span class="badge bg-secondary"><?php echo $totalRecords; ?> total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>IP</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No log entries found for this filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <?php
                                    $actionText = (string)($log['action'] ?? 'unknown');
                                    $badgeClass = actionBadgeClass($actionText);
                                    $fullName = trim(((string)($log['first_name'] ?? '')) . ' ' . ((string)($log['last_name'] ?? '')));
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($fullName !== ''): ?>
                                                <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (!empty($log['role'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars((string)$log['role'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars((string)($log['table_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($log['record_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string)($log['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#detailsModal"
                                                    data-old="<?php echo htmlspecialchars((string)($log['old_values'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-new="<?php echo htmlspecialchars((string)($log['new_values'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    View
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

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Log page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page - 1])), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $i])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(array_merge($filters, ['page' => $page + 1])), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailsModalBody"></div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailsModal = document.getElementById('detailsModal');
        const detailsBody = document.getElementById('detailsModalBody');

        function safeJsonToPrettyText(value) {
            if (!value) {
                return 'None';
            }

            try {
                return JSON.stringify(JSON.parse(value), null, 2);
            } catch (e) {
                return value;
            }
        }

        detailsModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            const oldValues = trigger.getAttribute('data-old') || '';
            const newValues = trigger.getAttribute('data-new') || '';

            const oldText = safeJsonToPrettyText(oldValues)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const newText = safeJsonToPrettyText(newValues)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            detailsBody.innerHTML =
                '<div class="row g-3">' +
                    '<div class="col-md-6">' +
                        '<h6>Old Values</h6>' +
                        '<pre class="bg-light p-2 border rounded">' + oldText + '</pre>' +
                    '</div>' +
                    '<div class="col-md-6">' +
                        '<h6>New Values</h6>' +
                        '<pre class="bg-light p-2 border rounded">' + newText + '</pre>' +
                    '</div>' +
                '</div>';
        });
    </script>
</body>
</html>