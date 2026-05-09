<?php
require_once 'config/config.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check table existence
    if (DB_TYPE === 'sqlite') {
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='shift_timetables'");
        $tableExists = (bool)$result->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shift_timetables'");
        $stmt->execute([DB_NAME]);
        $tableExists = (bool)$stmt->fetchColumn();
    }
    
    if ($tableExists) {
        echo "✅ shift_timetables table EXISTS\n";
        
        // Try to count records
        $count = $pdo->query("SELECT COUNT(*) FROM shift_timetables")->fetchColumn();
        echo "Current records: $count\n";
    } else {
        echo "❌ shift_timetables table DOES NOT EXIST\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
