<?php
/**
 * Quick Check: Count of shifts in April 2026 timetable
 */
require_once 'config/config.php';
require_once 'includes/auth.php';

try {
    $pdo = getDB();
    $result = $pdo->query("SELECT COUNT(*) as count, worker_group, shift_name FROM shift_timetables WHERE shift_date LIKE '2026-04%' GROUP BY worker_group, shift_name");
    $shifts = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Shifts in April 2026:\n";
    $total = 0;
    foreach ($shifts as $s) {
        $count = (int)$s['count'];
        echo "  " . $s['worker_group'] . " - " . $s['shift_name'] . ": $count\n";
        $total += $count;
    }
    echo "\nTotal April 2026 shifts: $total\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
