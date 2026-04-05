<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient'])) {
    verifyCsrf();
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId <= 0) {
        $error = 'Invalid request.';
    } else {
        try {
            $pdo = getDB();

            // Verify target is a patient (non-admin) and not the designated admin
            $uStmt = $pdo->prepare('SELECT id, username, role, first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
            $uStmt->execute([$userId]);
            $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$targetUser) {
                $error = 'User not found.';
            } elseif ((string)($targetUser['role'] ?? '') !== 'patient') {
                $error = 'Only patient accounts can be deleted through this action.';
            } elseif (strcasecmp((string)($targetUser['username'] ?? ''), ADMIN_LOGIN_USERNAME) === 0) {
                $error = 'The designated admin account cannot be deleted.';
            } else {
                $pdo->beginTransaction();

                // Collect the patient row id (if exists)
                $patStmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ? LIMIT 1');
                $patStmt->execute([$userId]);
                $patientId = (int)($patStmt->fetchColumn() ?: 0);

                // Helper: silently delete from a table if it exists
                $safeDelete = function (string $sql, array $params) use ($pdo): void {
                    try {
                        $s = $pdo->prepare($sql);
                        $s->execute($params);
                    } catch (PDOException $ignored) {
                        // table may not exist yet — skip
                    }
                };

                if ($patientId > 0) {
                    // Collect consultation IDs for child-prescription cleanup
                    $consultIds = [];
                    try {
                        $cStmt = $pdo->prepare('SELECT id FROM consultations WHERE patient_id = ?');
                        $cStmt->execute([$patientId]);
                        $consultIds = $cStmt->fetchAll(PDO::FETCH_COLUMN);
                    } catch (PDOException $ignored) {}

                    // Prescriptions → depends on consultations
                    foreach ($consultIds as $cid) {
                        $safeDelete('DELETE FROM prescriptions WHERE consultation_id = ?', [(int)$cid]);
                    }

                    // All direct patient_id linked tables
                    foreach (['consultations', 'appointments', 'lab_reports', 'payments', 'certificates', 'patient_group_members'] as $tbl) {
                        $safeDelete("DELETE FROM $tbl WHERE patient_id = ?", [$patientId]);
                    }

                    // Patient row
                    $safeDelete('DELETE FROM patients WHERE id = ?', [$patientId]);
                }

                // Finally delete the user account itself
                $delUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $delUser->execute([$userId]);

                writeAuditLog(
                    'delete patient',
                    'users',
                    $userId,
                    [
                        'username'   => (string)($targetUser['username']   ?? ''),
                        'email'      => (string)($targetUser['email']      ?? ''),
                        'first_name' => (string)($targetUser['first_name'] ?? ''),
                        'last_name'  => (string)($targetUser['last_name']  ?? ''),
                        'role'       => 'patient',
                        'patient_id' => $patientId ?: null,
                    ],
                    null
                );

                $pdo->commit();
                $message = 'Patient and all associated records deleted successfully.';
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Delete patient error: ' . $e->getMessage());
            $error = 'Could not delete patient. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    verifyCsrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? '';
    $allowedRoles = ['patient', 'doctor', 'admin', 'staff', 'intern', 'trainee'];

    if ($userId <= 0 || !in_array($role, $allowedRoles, true)) {
        $error = 'Invalid update request.';
    } else {
        try {
            $pdo = getDB();

            $userStmt = $pdo->prepare('SELECT username, role, email, first_name, last_name FROM users WHERE id = ? LIMIT 1');
            $userStmt->execute([$userId]);
            $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $targetUsername = (string)($targetUser['username'] ?? '');
            $currentRole = (string)($targetUser['role'] ?? '');

            if ($targetUsername !== '' && strcasecmp($targetUsername, ADMIN_LOGIN_USERNAME) === 0 && $role !== 'admin') {
                $error = 'The designated admin account cannot be changed to another role.';
            } elseif ($role === 'admin' && ($targetUsername === '' || strcasecmp($targetUsername, ADMIN_LOGIN_USERNAME) !== 0)) {
                $error = 'Only the designated admin account can have admin role.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$role, $userId]);
                writeAuditLog(
                    'update user role',
                    'users',
                    $userId,
                    [
                        'username' => $targetUsername,
                        'role' => $currentRole,
                    ],
                    [
                        'username' => $targetUsername,
                        'role' => $role,
                    ]
                );
                $message = 'User role updated successfully.';
            }
        } catch (PDOException $e) {
            error_log('Manage users update role error: ' . $e->getMessage());
            $error = 'Could not update user role.';
        }
    }
}

$users = [];
try {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, username, email, first_name, last_name, role, photo, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Manage users list error: ' . $e->getMessage());
    $error = 'Could not load users list.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="bi bi-shield-check"></i> Admin</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">Dashboard</a>
            <a class="nav-link active" href="manage_users.php">Manage Users</a>
            <a class="nav-link" href="manage_groups.php">Patient Groups</a>
            <a class="nav-link" href="attendance.php">Attendance</a>
            <a class="nav-link" href="change_password.php">Password</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Manage Users</h2>
        <span class="badge bg-secondary">Total: <?php echo count($users); ?></span>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$users): ?>
                            <tr><td colspan="7" class="text-center text-muted">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($u['photo']) && in_array($u['role'], ['doctor', 'trainee'], true)): ?>
                                            <img src="<?php echo htmlspecialchars('../' . $u['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Photo" style="width:40px;height:40px;object-fit:cover;border-radius:50%;border:2px solid #dee2e6;">
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($u['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'doctor' ? 'success' : 'primary'); ?>">
                                            <?php echo htmlspecialchars(ucfirst($u['role']), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <form method="post" class="d-flex gap-2">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <select name="role" class="form-select form-select-sm">
                                                    <?php foreach (['patient', 'doctor', 'admin', 'staff', 'intern', 'trainee'] as $r): ?>
                                                        <?php if ($r === 'admin' && strcasecmp((string)$u['username'], ADMIN_LOGIN_USERNAME) !== 0) { continue; } ?>
                                                        <option value="<?php echo htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(ucfirst($r), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_role" class="btn btn-sm btn-danger">Save</button>
                                            </form>
                                            <?php if ($u['role'] === 'patient' && strcasecmp((string)$u['username'], ADMIN_LOGIN_USERNAME) !== 0): ?>
                                            <form method="post" onsubmit="return confirm('DELETE patient <?php echo htmlspecialchars(addslashes(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: $u['username']), ENT_QUOTES, 'UTF-8'); ?> and ALL their records? This cannot be undone.');">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <button type="submit" name="delete_patient" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Delete Patient
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
