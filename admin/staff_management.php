<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

requireDesignatedAdmin();

$user = $_SESSION['user'];
$message = '';
$error = '';

// Get all staff from database or create default list
$staffList = [
    ['id' => 1, 'name' => 'Abeng', 'position' => 'IDE Accoucheur', 'phone' => '681 629 527', 'email' => 'abeng@cmds.cm'],
    ['id' => 2, 'name' => 'Mayan', 'position' => 'Infirmière PEV', 'phone' => '696 XXX XXX', 'email' => 'mayan@cmds.cm'],
    ['id' => 3, 'name' => 'Nloga', 'position' => 'Labo', 'phone' => '696 XXX XXX', 'email' => 'nloga@cmds.cm'],
    ['id' => 4, 'name' => 'Favour', 'position' => 'Journée longue', 'phone' => '696 XXX XXX', 'email' => 'favour@cmds.cm'],
    ['id' => 5, 'name' => 'Wiltitz', 'position' => 'Infirmier', 'phone' => '696 XXX XXX', 'email' => 'wiltitz@cmds.cm'],
    ['id' => 6, 'name' => 'Kadija', 'position' => 'Nuit', 'phone' => '696 XXX XXX', 'email' => 'kadija@cmds.cm'],
    ['id' => 7, 'name' => 'Nyanze', 'position' => 'Après-midi', 'phone' => '696 XXX XXX', 'email' => 'nyanze@cmds.cm'],
    ['id' => 8, 'name' => 'Mvogo', 'position' => 'Garde', 'phone' => '696 XXX XXX', 'email' => 'mvogo@cmds.cm'],
    ['id' => 9, 'name' => 'Nagayena', 'position' => 'Matin', 'phone' => '696 XXX XXX', 'email' => 'nagayena@cmds.cm'],
    ['id' => 10, 'name' => 'Ndong', 'position' => 'Après-midi', 'phone' => '696 XXX XXX', 'email' => 'ndong@cmds.cm'],
    ['id' => 11, 'name' => 'Zad', 'position' => 'Matin', 'phone' => '696 XXX XXX', 'email' => 'zad@cmds.cm'],
    ['id' => 12, 'name' => 'Florinda', 'position' => 'Après-midi', 'phone' => '696 XXX XXX', 'email' => 'florinda@cmds.cm'],
    ['id' => 13, 'name' => 'Cathrine', 'position' => 'Mixte', 'phone' => '696 XXX XXX', 'email' => 'cathrine@cmds.cm'],
    ['id' => 14, 'name' => 'Abanda', 'position' => 'Nuit', 'phone' => '696 XXX XXX', 'email' => 'abanda@cmds.cm'],
    ['id' => 15, 'name' => 'Saurel', 'position' => 'Journée', 'phone' => '696 XXX XXX', 'email' => 'saurel@cmds.cm'],
];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    verifyCsrf();
    
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    
    if ($staffId > 0 && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        // Update in staffList
        foreach ($staffList as &$staff) {
            if ($staff['id'] == $staffId) {
                $staff['email'] = $newEmail;
                $staff['phone'] = $newPhone;
                $message = "Staff info updated: " . htmlspecialchars($staff['name']);
                break;
            }
        }
        unset($staff);
    } else {
        $error = 'Invalid staff ID or email format.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <style>
        body { background: #f5f7fa; }
        .staff-container { max-width: 1200px; margin: 2rem auto; }
        .staff-header { background: linear-gradient(135deg, #0b2b3b 0%, #1a4a62 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .staff-header h1 { font-size: 2rem; font-weight: 600; }
        .staff-card { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid #0b2b3b; }
        .staff-card h5 { color: #0b2b3b; font-weight: 600; margin-bottom: 1rem; }
        .staff-field { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        .staff-field label { font-weight: 500; color: #555; font-size: 0.9rem; }
        .staff-field input { padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; }
        .btn-update { background: #0b2b3b; color: white; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-update:hover { background: #1a4a62; }
        @media print {
            .staff-header, .btn-update { display: none; }
        }
    </style>
</head>
<body>
<div class="staff-container">
    <div class="staff-header">
        <h1><i class="bi bi-people"></i> Staff Management</h1>
        <p>CENTRE MEDICAL DONS DE SOINS - 15 Staff Members</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div style="margin-bottom: 2rem;">
        <a href="timetable.php" class="btn btn-primary">
            <i class="bi bi-calendar3"></i> Back to Timetable
        </a>
        <a href="timetable_analytics.php" class="btn btn-outline-primary">
            <i class="bi bi-graph-up"></i> View Analytics
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print Staff List
        </button>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1.5rem;">
        <?php foreach ($staffList as $staff): ?>
            <div class="staff-card">
                <h5>
                    <i class="bi bi-person-badge"></i> 
                    <?php echo htmlspecialchars($staff['name']); ?>
                    <span style="font-size: 0.8rem; color: #999; font-weight: normal;">ID: #<?php echo $staff['id']; ?></span>
                </h5>
                
                <div style="margin-bottom: 1rem;">
                    <strong>Position:</strong> <?php echo htmlspecialchars($staff['position']); ?>
                </div>
                
                <form method="POST" class="staff-field">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                    
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($staff['email']); ?>" required>
                    </div>
                    
                    <div>
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($staff['phone']); ?>">
                    </div>
                    
                    <button type="submit" name="update_staff" class="btn-update" style="grid-column: 1 / -1;">
                        <i class="bi bi-check-lg"></i> Update Info
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
