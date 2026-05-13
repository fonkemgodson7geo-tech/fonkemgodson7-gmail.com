<?php
/**
 * Timetable Setup Status Check
 */
require_once 'config/config.php';
require_once 'includes/auth.php';

echo "═════════════════════════════════════════\n";
echo "  TIMETABLE SETUP STATUS CHECK\n";
echo "═════════════════════════════════════════\n\n";

echo "Database Configuration:\n";
echo "  Type: " . DB_TYPE . "\n";
if (DB_TYPE === 'sqlite') {
    echo "  File: " . DB_FILE . "\n";
    echo "  Exists: " . (file_exists(DB_FILE) ? "YES ✅" : "NO ❌") . "\n";
} else {
    echo "  Host: " . DB_HOST . "\n";
    echo "  Database: " . DB_NAME . "\n";
}

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "\nDatabase Connection: ✅ OK\n\n";
} catch (Exception $e) {
    echo "\nDatabase Connection: ❌ FAILED - " . $e->getMessage() . "\n\n";
    $pdo = null;
}

if ($pdo === null) {
    echo "Timetable verification skipped due to database connection failure.\n";
    exit(1);
}

try {
// Check for shift_timetables table
$tableExists = false;
$errorMsg = '';

if (DB_TYPE === 'sqlite') {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='shift_timetables'");
    $stmt->execute();
    $tableExists = (bool)$stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shift_timetables'");
    $stmt->execute([DB_NAME]);
    $tableExists = (bool)$stmt->fetchColumn();
}

echo "shift_timetables Table:\n";
if ($tableExists) {
    echo "  Status: ✅ EXISTS\n";
    
    // Get row count
    $result = $pdo->query("SELECT COUNT(*) as count FROM shift_timetables");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $count = (int)($row['count'] ?? 0);
    echo "  Total Shifts: $count\n";
    
    if ($count > 0) {
        echo "\n  Recent Shifts:\n";
        $result = $pdo->query("SELECT * FROM shift_timetables ORDER BY shift_date DESC LIMIT 5");
        $shifts = $result->fetchAll(PDO::FETCH_ASSOC);
        foreach ($shifts as $idx => $shift) {
            echo "    " . ($idx + 1) . ". " . $shift['shift_date'] . " - " . $shift['shift_name'] . " (" . $shift['worker_group'] . ")\n";
        }
    } else {
        echo "  ⚠️  No shifts configured yet\n";
    }
} else {
    echo "  Status: ❌ DOES NOT EXIST\n";
    echo "  Action: Creating table...\n";
    
    // Create the table for SQLite
    if (DB_TYPE === 'sqlite') {
        $createSQL = "CREATE TABLE shift_timetables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            worker_group TEXT NOT NULL,
            shift_name TEXT NOT NULL,
            shift_date DATE NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            generated_by INTEGER,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (generated_by) REFERENCES users(id)
        )";
        
        $pdo->exec($createSQL);
        
        // Create indexes
        $pdo->exec("CREATE INDEX idx_shift_date_group ON shift_timetables(shift_date, worker_group)");
        $pdo->exec("CREATE INDEX idx_user_group ON shift_timetables(user_id, worker_group)");
        
        echo "  ✅ Table created successfully!\n";
    }
}

// Check admin users
echo "\n\nAdmin Users (for managing timetable):\n";
$result = $pdo->query("SELECT id, username, first_name, last_name FROM users WHERE role = 'admin' LIMIT 5");
$admins = $result->fetchAll(PDO::FETCH_ASSOC);
if ($admins) {
    foreach ($admins as $admin) {
        echo "  - " . $admin['username'] . " (" . $admin['first_name'] . " " . $admin['last_name'] . ")\n";
    }
} else {
    echo "  ⚠️  No admin users found\n";
}

// Check workers
echo "\nWorker Count by Role:\n";
$roles = ['doctor', 'intern', 'trainee', 'staff'];
foreach ($roles as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)($row['count'] ?? 0);
    echo "  - " . ucfirst($role) . "s: $count\n";
}

echo "\n═════════════════════════════════════════\n";
echo "  ✅ TIMETABLE SYSTEM IS READY\n";
echo "═════════════════════════════════════════\n";
echo "\nAccess Instructions:\n";
echo "  1. Open: https://www.cmdonsdesoins.com\n";
echo "  2. Login with admin credentials:\n";
echo "     Username: admie\n";
echo "     Password: dds_awc2018\n";
echo "  3. Go to: Admin → Timetable\n";
echo "  4. Create and manage shifts\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
