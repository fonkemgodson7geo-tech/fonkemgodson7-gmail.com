<?php
/**
 * Import April 2026 Sample Timetable into System
 * Access via: https://www.cmdonsdesoins.com/import_april_2026_timetable.php?token=import_2026
 * Or run: php import_april_2026_timetable.php
 */

// Skip token check if running from CLI
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['token']) || $_GET['token'] !== 'import_2026') {
        http_response_code(403);
        die('❌ Forbidden - Invalid token');
    }
}

require_once 'config/config.php';
require_once 'includes/auth.php';

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "═════════════════════════════════════════\n";
    echo "  APRIL 2026 TIMETABLE IMPORT\n";
    echo "═════════════════════════════════════════\n\n";
    
    // Define shift times for each department
    $shiftTimes = [
        'Doctors' => [
            'Morning' => ['09:00', '15:00'],
            'Evening' => ['15:00', '21:00']
        ],
        'Lab Workers' => [
            'Day' => ['09:00', '17:00'],
            'Evening' => ['17:00', '21:00']
        ],
        'Pharmacy Workers' => [
            'Day' => ['09:00', '21:00'],
            'Night' => ['21:00', '09:00']
        ],
        'Interns' => [
            'Morning' => ['09:00', '15:00'],
            'Evening' => ['15:00', '21:00'],
            'Night' => ['21:00', '09:00']
        ],
        'Trainees' => [
            'Morning' => ['09:00', '15:00'],
            'Evening' => ['15:00', '21:00'],
            'Night' => ['21:00', '09:00']
        ]
    ];
    
    // Define timetable data
    $timetableData = [
        'Doctors' => [
            ['date' => '2026-04-01', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-02', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-03', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-04', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-05', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-06', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-07', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-08', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-09', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-10', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-11', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-12', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-13', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-14', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-15', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-16', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-17', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-18', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-19', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-20', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-21', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-22', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-23', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-24', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-25', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-26', 'Morning' => 'G', 'Evening' => 'U'],
            ['date' => '2026-04-27', 'Morning' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-28', 'Morning' => 'M', 'Evening' => 'B'],
            ['date' => '2026-04-29', 'Morning' => 'B', 'Evening' => 'G'],
            ['date' => '2026-04-30', 'Morning' => 'G', 'Evening' => 'U'],
        ],
        'Lab Workers' => [
            ['date' => '2026-04-01', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-02', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-03', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-04', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-05', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-06', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-07', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-08', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-09', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-10', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-11', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-12', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-13', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-14', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-15', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-16', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-17', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-18', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-19', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-20', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-21', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-22', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-23', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-24', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-25', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-26', 'Day' => 'M', 'Evening' => 'R'],
            ['date' => '2026-04-27', 'Day' => 'R', 'Evening' => 'S'],
            ['date' => '2026-04-28', 'Day' => 'S', 'Evening' => 'U'],
            ['date' => '2026-04-29', 'Day' => 'U', 'Evening' => 'M'],
            ['date' => '2026-04-30', 'Day' => 'M', 'Evening' => 'R'],
        ],
        'Pharmacy Workers' => [
            ['date' => '2026-04-01', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-02', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-03', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-04', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-05', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-06', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-07', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-08', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-09', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-10', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-11', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-12', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-13', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-14', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-15', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-16', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-17', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-18', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-19', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-20', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-21', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-22', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-23', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-24', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-25', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-26', 'Day' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-27', 'Day' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-28', 'Day' => 'M', 'Night' => 'B'],
            ['date' => '2026-04-29', 'Day' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-30', 'Day' => 'G', 'Night' => 'U'],
        ],
        'Interns' => [
            ['date' => '2026-04-01', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-02', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-03', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-04', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-05', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'B'],
            ['date' => '2026-04-06', 'Morning' => 'S', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-07', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-08', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-09', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-10', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-11', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'B'],
            ['date' => '2026-04-12', 'Morning' => 'S', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-13', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-14', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-15', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-16', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-17', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'B'],
            ['date' => '2026-04-18', 'Morning' => 'S', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-19', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-20', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-21', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-22', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-23', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'B'],
            ['date' => '2026-04-24', 'Morning' => 'S', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-25', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-26', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-27', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-28', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-29', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'B'],
            ['date' => '2026-04-30', 'Morning' => 'S', 'Evening' => 'B', 'Night' => 'G'],
        ],
        'Trainees' => [
            ['date' => '2026-04-01', 'Morning' => 'T', 'Evening' => 'C', 'Night' => 'B'],
            ['date' => '2026-04-02', 'Morning' => 'C', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-03', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-04', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-05', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-06', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-07', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'T'],
            ['date' => '2026-04-08', 'Morning' => 'S', 'Evening' => 'T', 'Night' => 'C'],
            ['date' => '2026-04-09', 'Morning' => 'T', 'Evening' => 'C', 'Night' => 'B'],
            ['date' => '2026-04-10', 'Morning' => 'C', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-11', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-12', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-13', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-14', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-15', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'T'],
            ['date' => '2026-04-16', 'Morning' => 'S', 'Evening' => 'T', 'Night' => 'C'],
            ['date' => '2026-04-17', 'Morning' => 'T', 'Evening' => 'C', 'Night' => 'B'],
            ['date' => '2026-04-18', 'Morning' => 'C', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-19', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-20', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-21', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-22', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
            ['date' => '2026-04-23', 'Morning' => 'R', 'Evening' => 'S', 'Night' => 'T'],
            ['date' => '2026-04-24', 'Morning' => 'S', 'Evening' => 'T', 'Night' => 'C'],
            ['date' => '2026-04-25', 'Morning' => 'T', 'Evening' => 'C', 'Night' => 'B'],
            ['date' => '2026-04-26', 'Morning' => 'C', 'Evening' => 'B', 'Night' => 'G'],
            ['date' => '2026-04-27', 'Morning' => 'B', 'Evening' => 'G', 'Night' => 'U'],
            ['date' => '2026-04-28', 'Morning' => 'G', 'Evening' => 'U', 'Night' => 'M'],
            ['date' => '2026-04-29', 'Morning' => 'U', 'Evening' => 'M', 'Night' => 'R'],
            ['date' => '2026-04-30', 'Morning' => 'M', 'Evening' => 'R', 'Night' => 'S'],
        ]
    ];
    
    // Create/get worker IDs for codes
    $workerCodes = ['B', 'G', 'U', 'M', 'R', 'S', 'T', 'C'];
    $workerIds = [];
    
    echo "Creating/retrieving workers...\n";
    foreach ($workerCodes as $code) {
        $username = "worker_" . strtolower($code);
        
        // Check if worker exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Create worker
            $password_hash = password_hash('password123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $username,
                $password_hash,
                $username . "@clinic.local",
                'staff'
            ]);
            $workerId = $pdo->lastInsertId();
            echo "  ✓ Created worker $code (ID: $workerId)\n";
        } else {
            $workerId = $user['id'];
            echo "  ✓ Found worker $code (ID: $workerId)\n";
        }
        
        $workerIds[$code] = $workerId;
    }
    
    echo "\nImporting shifts...\n";
    $totalInserted = 0;
    
    foreach ($timetableData as $department => $days) {
        echo "\n  Department: $department\n";
        $count = 0;
        
        foreach ($days as $dayData) {
            $shiftDate = $dayData['date'];
            
            foreach ($dayData as $shiftType => $workerCode) {
                if ($shiftType === 'date') continue;
                
                $workerId = $workerIds[$workerCode];
                $startTime = $shiftTimes[$department][$shiftType][0];
                $endTime = $shiftTimes[$department][$shiftType][1];
                
                // Handle night shift crossing midnight
                if ($endTime === '09:00' && $startTime === '21:00') {
                    $startAt = $shiftDate . ' ' . $startTime . ':00';
                    $nextDay = date('Y-m-d', strtotime($shiftDate . ' +1 day'));
                    $endAt = $nextDay . ' ' . $endTime . ':00';
                } else {
                    $startAt = $shiftDate . ' ' . $startTime . ':00';
                    $endAt = $shiftDate . ' ' . $endTime . ':00';
                }
                
                // Check if shift already exists
                $stmt = $pdo->prepare("SELECT id FROM shift_timetables WHERE user_id = ? AND shift_date = ? AND shift_name = ?");
                $stmt->execute([$workerId, $shiftDate, $shiftType]);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO shift_timetables (user_id, worker_group, shift_name, shift_date, start_at, end_at, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $workerId,
                        $department,
                        $shiftType,
                        $shiftDate,
                        $startAt,
                        $endAt,
                        'April 2026 sample timetable'
                    ]);
                    $count++;
                    $totalInserted++;
                }
            }
        }
        
        echo "    Inserted: $count shifts\n";
    }
    
    echo "\n" . str_repeat("=", 41) . "\n";
    echo "✅ IMPORT COMPLETE\n";
    echo "   Total shifts imported: $totalInserted\n";
    echo "═════════════════════════════════════════\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString();
    exit(1);
}
?>
