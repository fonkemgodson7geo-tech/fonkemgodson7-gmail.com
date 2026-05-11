<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$message = '';
$error = '';

$pdo = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    verifyCsrf();
    
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $evaluation_date = $_POST['evaluation_date'] ?? '';
    $assiduity = (float)($_POST['assiduity'] ?? 0);
    $punctuality = (float)($_POST['punctuality'] ?? 0);
    $productivity = (float)($_POST['productivity'] ?? 0);
    $illness_days = (int)($_POST['illness_days'] ?? 0);
    $permission_days = (int)($_POST['permission_days'] ?? 0);
    $absence_days = (int)($_POST['absence_days'] ?? 0);
    $sanctions = trim($_POST['sanctions'] ?? '');
    $suspension = isset($_POST['suspension']) ? 1 : 0;
    $query_letter = trim($_POST['query_letter'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($employee_id <= 0) {
        $error = 'Please select an employee.';
    } elseif (empty($evaluation_date)) {
        $error = 'Evaluation date is required.';
    } elseif ($assiduity < 1 || $assiduity > 5 || $punctuality < 1 || $punctuality > 5 || $productivity < 1 || $productivity > 5) {
        $error = 'Ratings must be between 1 and 5.';
    } else {
        try {
            $overall = round(($assiduity + $punctuality + $productivity) / 3, 1);
            
            $stmt = $pdo->prepare('INSERT INTO employee_evaluations 
                (employee_id, evaluation_date, evaluated_by, assiduity_rating, punctuality_rating, productivity_rating, 
                 illness_days, permission_days, absence_days, sanctions, suspension, query_letter, overall_rating, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                assiduity_rating = VALUES(assiduity_rating), punctuality_rating = VALUES(punctuality_rating), 
                productivity_rating = VALUES(productivity_rating), illness_days = VALUES(illness_days), 
                permission_days = VALUES(permission_days), absence_days = VALUES(absence_days), 
                sanctions = VALUES(sanctions), suspension = VALUES(suspension), query_letter = VALUES(query_letter), 
                overall_rating = VALUES(overall_rating), notes = VALUES(notes)');
            
            $stmt->execute([$employee_id, $evaluation_date, $_SESSION['user']['id'], $assiduity, $punctuality, $productivity, 
                           $illness_days, $permission_days, $absence_days, $sanctions, $suspension, $query_letter, $overall, $notes]);
            
            $message = 'Employee evaluation saved successfully.';
        } catch (Exception $e) {
            $error = 'Error saving evaluation: ' . $e->getMessage();
        }
    }
}

// Get employees (non-patient roles)
$employees = $pdo->query("SELECT id, username, first_name, last_name, role FROM users WHERE role != 'patient' ORDER BY role, first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get recent evaluations
$evaluations = $pdo->query("SELECT e.*, u.first_name, u.last_name, u.username, eb.first_name as eval_first, eb.last_name as eval_last 
                           FROM employee_evaluations e 
                           JOIN users u ON e.employee_id = u.id 
                           JOIN users eb ON e.evaluated_by = eb.id 
                           ORDER BY e.evaluation_date DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Evaluation - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .rating-stars { color: #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><?php echo SITE_NAME; ?> - Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_users.php">Users</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Employee Evaluation Sheet</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>New Evaluation</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php echo generateCsrfToken(); ?>
                            
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Select Employee</label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Choose employee...</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>">
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['role'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="evaluation_date" class="form-label">Evaluation Date</label>
                                <input type="date" class="form-control" id="evaluation_date" name="evaluation_date" required>
                            </div>
                            
                            <h6>Performance Ratings (1-5)</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="assiduity" class="form-label">Assiduity</label>
                                    <input type="number" class="form-control" id="assiduity" name="assiduity" min="1" max="5" step="0.1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="punctuality" class="form-label">Punctuality</label>
                                    <input type="number" class="form-control" id="punctuality" name="punctuality" min="1" max="5" step="0.1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="productivity" class="form-label">Productivity</label>
                                    <input type="number" class="form-control" id="productivity" name="productivity" min="1" max="5" step="0.1" required>
                                </div>
                            </div>
                            
                            <h6>Attendance Records</h6>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="illness_days" class="form-label">Illness Days</label>
                                    <input type="number" class="form-control" id="illness_days" name="illness_days" min="0" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="permission_days" class="form-label">Permission Days</label>
                                    <input type="number" class="form-control" id="permission_days" name="permission_days" min="0" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="absence_days" class="form-label">Absence Days</label>
                                    <input type="number" class="form-control" id="absence_days" name="absence_days" min="0" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sanctions" class="form-label">Sanctions</label>
                                <textarea class="form-control" id="sanctions" name="sanctions" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="suspension" name="suspension">
                                <label class="form-check-label" for="suspension">Suspension</label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="query_letter" class="form-label">Query Letter</label>
                                <textarea class="form-control" id="query_letter" name="query_letter" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>
                            
                            <button type="submit" name="submit_evaluation" class="btn btn-primary">Save Evaluation</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Evaluations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($evaluations)): ?>
                            <p class="text-muted">No evaluations yet.</p>
                        <?php else: ?>
                            <?php foreach ($evaluations as $eval): ?>
                                <div class="border-bottom mb-2 pb-2">
                                    <strong><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['last_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($eval['evaluation_date']); ?> by <?php echo htmlspecialchars($eval['eval_first'] . ' ' . $eval['eval_last']); ?></small><br>
                                    Overall: <span class="rating-stars"><?php echo str_repeat('★', round($eval['overall_rating'])); ?></span> (<?php echo $eval['overall_rating']; ?>/5)
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>