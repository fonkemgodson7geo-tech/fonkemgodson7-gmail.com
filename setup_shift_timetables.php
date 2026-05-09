<?php
/**
 * Quick setup to add missing shift_timetables table
 * Access this via: https://www.cmdonsdesoins.com/setup_shift_timetables.php
 */

// Simple security check - remove in production after running once
if (!isset($_GET['token']) || $_GET['token'] !== 'setup_token_2026') {
    http_response_code(403);
    die('Forbidden');
}

require_once 'config/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table already exists
    if (DB_TYPE === 'mysql') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shift_timetables'");
        $stmt->execute([DB_NAME]);
        $tableExists = (bool)$stmt->fetchColumn();
        
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE shift_timetables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                worker_group VARCHAR(50) NOT NULL,
                shift_name VARCHAR(100) NOT NULL,
                shift_date DATE NOT NULL,
                start_at DATETIME NOT NULL,
                end_at DATETIME NOT NULL,
                generated_by INT,
                note TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (generated_by) REFERENCES users(id),
                INDEX idx_shift_date_group (shift_date, worker_group),
                INDEX idx_user_group (user_id, worker_group)
            )");
            echo "✓ shift_timetables table created successfully!\n";
        } else {
            echo "✓ shift_timetables table already exists.\n";
        }
    }
    
    // Verify the table
    $result = $pdo->query("SELECT COUNT(*) FROM shift_timetables");
    $count = $result->fetchColumn();
    echo "✓ Current shifts: $count\n";
    echo "✓ All checks passed!\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
    exit(1);
}
?>
