<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireRole('admin');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || strlen($name) > 100) {
        $error = 'Group name is required (max 100 chars).';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('INSERT INTO patient_groups (name, description, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$name, $description, $_SESSION['user']['id']]);
            $message = 'Group created successfully.';
        } catch (PDOException $e) {
            error_log('Manage groups create error: ' . $e->getMessage());
            $error = 'Could not create patient group.';
        }
    }
}

$groups = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query(
        'SELECT pg.id, pg.name, pg.description, pg.created_at, u.first_name, u.last_name, COUNT(pgm.id) AS members
         FROM patient_groups pg
         LEFT JOIN users u ON pg.created_by = u.id
         LEFT JOIN patient_group_members pgm ON pgm.group_id = pg.id
         GROUP BY pg.id, pg.name, pg.description, pg.created_at, u.first_name, u.last_name
         ORDER BY pg.created_at DESC'
    );
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Manage groups list error: ' . $e->getMessage());
    $error = 'Could not load groups.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Groups - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link" href="manage_users.php">Manage Users</a>
            <a class="nav-link active" href="manage_groups.php">Patient Groups</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Patient Groups</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><strong>Create Group</strong></div>
                <div class="card-body">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label" for="name">Group Name</label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" name="create_group" class="btn btn-danger w-100">Create</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Members</th>
                                    <th>Created By</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$groups): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No groups available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($groups as $g): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($g['description'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="badge bg-info"><?php echo (int)$g['members']; ?></span></td>
                                            <td><?php echo htmlspecialchars(trim(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? '')) ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($g['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
