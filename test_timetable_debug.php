<?php
try {
    require_once 'config/config.php';
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if shift_timetables exists
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='shift_timetables'");
    $tableExists = (bool)$result->fetchColumn();
    echo "Table exists: " . ($tableExists ? 'YES' : 'NO') . "\n";
    
    // Check doctors
    $stmt = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'doctor'");
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Doctors found: " . count($doctors) . "\n";
    foreach ($doctors as $doc) {
        echo "  - " . $doc['first_name'] . " " . $doc['last_name'] . " (" . $doc['username'] . ") ID=" . $doc['id'] . "\n";
    }
    
    // Check if shift_timetables table schema
    if ($tableExists) {
        $schema = $pdo->query("PRAGMA table_info(shift_timetables)")->fetchAll(PDO::FETCH_ASSOC);
        echo "\nShift_timetables columns:\n";
        foreach ($schema as $col) {
            echo "  - " . $col['name'] . " (" . $col['type'] . ")\n";
        }
    }
    
    // Try generating a sample shift
    echo "\nTrying to insert test shift...\n";
    if ($tableExists && count($doctors) >= 1) {
        $insert = $pdo->prepare('INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([
            (int)$doctors[0]['id'],
            'doctor',
            'morning',
            '2026-05-01',
            '2026-05-01 09:00:00',
            '2026-05-01 15:00:00',
            1,
            'Test insert'
        ]);
        echo "Test insert successful!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
