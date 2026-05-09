<?php
require_once 'config/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if shift_timetables table exists
    if (DB_TYPE === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='shift_timetables'");
        $stmt->execute();
        $tableExists = (bool)$stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shift_timetables'");
        $stmt->execute([DB_NAME]);
        $tableExists = (bool)$stmt->fetchColumn();
    }
    
    if ($tableExists) {
        echo "✅ shift_timetables table EXISTS\n";
        
        // Check row count
        $result = $pdo->query("SELECT COUNT(*) as count FROM shift_timetables");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $count = $row['count'] ?? 0;
        echo "   Total shifts: $count\n";
        
        // Check table structure
        if (DB_TYPE === 'sqlite') {
            $result = $pdo->query("PRAGMA table_info(shift_timetables)");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            echo "   Columns: " . count($columns) . "\n";
            foreach ($columns as $col) {
                echo "   - " . $col['name'] . " (" . $col['type'] . ")\n";
            }
        }
        
        // Show recent shifts if any
        if ($count > 0) {
            echo "\n   Recent shifts:\n";
            $result = $pdo->query("SELECT * FROM shift_timetables ORDER BY shift_date DESC LIMIT 3");
            $shifts = $result->fetchAll(PDO::FETCH_ASSOC);
            foreach ($shifts as $shift) {
                echo "   - " . $shift['shift_date'] . ": " . $shift['shift_name'] . " (" . $shift['worker_group'] . ")\n";
            }
        }
    } else {
        echo "❌ shift_timetables table DOES NOT EXIST\n";
        echo "   Please run: https://www.cmdonsdesoins.com/setup_shift_timetables.php?token=setup_token_2026\n";
    }
    
    // Check database connection
    echo "\n✅ Database Connection: OK\n";
    echo "   Type: " . DB_TYPE . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
    exit(1);
}
?>
