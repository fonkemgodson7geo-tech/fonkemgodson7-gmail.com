<?php
/**
 * Migration: Add shift_timetables table for timetable management
 * Run this script to add the missing shift_timetables table to the database
 */

require_once 'config/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table already exists
    if (DB_TYPE === 'mysql') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shift_timetables'");
        $stmt->execute([DB_NAME]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='shift_timetables'");
        $stmt->execute([]);
    }
    
    $tableExists = (bool)$stmt->fetchColumn();
    
    if ($tableExists) {
        echo "✓ shift_timetables table already exists.\n";
    } else {
        // Create the table
        if (DB_TYPE === 'mysql') {
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
        } else {
            $pdo->exec("CREATE TABLE shift_timetables (
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
            )");
        }
        
        echo "✓ shift_timetables table created successfully!\n";
    }
    
    // Verify the table can be queried
    $result = $pdo->query("SELECT COUNT(*) FROM shift_timetables");
    $count = $result->fetchColumn();
    echo "✓ Verification successful. Current shift_timetables count: $count\n";
    
    echo "\n🎉 Migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
