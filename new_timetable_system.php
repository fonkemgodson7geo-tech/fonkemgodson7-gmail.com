<?php
/**
 * New Timetable Management System
 * Based on the provided Node.js roster system, adapted for PHP/AWCD
 */

require_once 'config/config.php';
require_once 'includes/auth.php';

/**
 * Get database connection
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
                $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
            } else {
                $pdo = new PDO('sqlite:' . DB_FILE);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if (DB_TYPE === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

// Staff list for the roster
$staffList = [
    "Abeng", "Mayan", "Nloga", "Favour",
    "Wiltite", "Kadija", "Nyanze",
    "Mvogo", "Nagayena", "Ndong", "Zad", "Florinda"
];

// Shift definitions with times
$shiftDefinitions = [
    'M' => ['name' => 'Morning', 'start' => '09:00', 'end' => '15:00'],
    'A' => ['name' => 'Afternoon', 'start' => '15:00', 'end' => '21:00'],
    'N' => ['name' => 'Night', 'start' => '21:00', 'end' => '09:00'],
    'R' => ['name' => 'Rest', 'start' => '00:00', 'end' => '00:00']
];

// Permission/Punishment types
$specialTypes = [
    'OFF' => ['type' => 'permission', 'name' => 'Day Off'],
    'SICK' => ['type' => 'permission', 'name' => 'Sick Leave'],
    'LATE' => ['type' => 'punishment', 'name' => 'Late'],
    'ABSENT' => ['type' => 'punishment', 'name' => 'Absent']
];

/**
 * Validate shift sequence (smart rule)
 */
function isValidShiftSequence($prevShift, $nextShift) {
    // Night shift cannot be followed by Morning or Afternoon
    if ($prevShift === 'N' && in_array($nextShift, ['M', 'A'])) {
        return false;
    }
    return true;
}

/**
 * Auto-generate timetable for a month
 */
function generateTimetable($month, $year = null) {
    global $staffList, $shiftDefinitions;

    if ($year === null) {
        $year = date('Y');
    }

    $pdo = getDBConnection();
    $generatedBy = $_SESSION['user_id'] ?? 1; // Default to admin

    echo "Generating timetable for $year-$month...\n";

    foreach ($staffList as $staffName) {
        $prevShift = '';

        // Get the number of days in the month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            // Available shifts
            $availableShifts = ['M', 'A', 'N', 'R'];

            // Find a valid shift
            $selectedShift = 'R'; // Default to rest
            foreach ($availableShifts as $shift) {
                if (isValidShiftSequence($prevShift, $shift)) {
                    $selectedShift = $shift;
                    break;
                }
            }

            $prevShift = $selectedShift;

            // Insert into database
            $shiftInfo = $shiftDefinitions[$selectedShift];
            $startTime = $date . ' ' . $shiftInfo['start'] . ':00';
            $endTime = $date . ' ' . $shiftInfo['end'] . ':00';

            // Handle overnight shifts
            if ($selectedShift === 'N') {
                $endTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $shiftInfo['end'] . ':00 +1 day'));
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO shift_timetables
                    (user_id, worker_group, shift_name, shift_date, start_at, end_at, generated_by, note)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                // For now, we'll use a placeholder user_id
                // In a real system, you'd map staff names to actual user_ids
                $stmt->execute([
                    1, // placeholder user_id
                    'Staff',
                    $shiftInfo['name'],
                    $date,
                    $startTime,
                    $endTime,
                    $generatedBy,
                    "Auto-generated for $staffName"
                ]);

                echo "✓ Generated shift for $staffName on $date: $selectedShift\n";

            } catch (Exception $e) {
                echo "✗ Error generating shift for $staffName on $date: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nTimetable generation completed!\n";
}

/**
 * Get timetable for a specific month
 */
function getTimetable($month, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }

    $pdo = getDBConnection();
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = sprintf('%04d-%02d-31', $year, $month);

    $stmt = $pdo->prepare("
        SELECT * FROM shift_timetables
        WHERE shift_date BETWEEN ? AND ?
        ORDER BY shift_date, user_id
    ");

    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Display timetable in a simple HTML table
 */
function displayTimetable($month, $year = null) {
    global $staffList;

    if ($year === null) {
        $year = date('Y');
    }

    $timetableData = getTimetable($month, $year);
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    echo "<!DOCTYPE html>
<html>
<head>
    <title>Hospital Duty Roster - $year-$month</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f5f5f5; }
        .M { background-color: lightblue; }
        .A { background-color: lightgreen; }
        .N { background-color: navy; color: white; }
        .R { background-color: orange; }
        .legend { margin-top: 20px; padding: 10px; background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Hospital Duty Roster - " . date('F Y', strtotime("$year-$month-01")) . "</h1>

    <table>
        <tr>
            <th>Staff</th>";

    for ($day = 1; $day <= $daysInMonth; $day++) {
        echo "<th>$day</th>";
    }
    echo "</tr>";

    foreach ($staffList as $staffName) {
        echo "<tr><td>$staffName</td>";

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            // Find shift for this staff on this date
            $shift = '';
            foreach ($timetableData as $record) {
                if ($record['note'] && strpos($record['note'], "Auto-generated for $staffName") !== false &&
                    $record['shift_date'] === $date) {
                    // Map shift name back to code
                    $shiftName = $record['shift_name'];
                    if ($shiftName === 'Morning') $shift = 'M';
                    elseif ($shiftName === 'Afternoon') $shift = 'A';
                    elseif ($shiftName === 'Night') $shift = 'N';
                    elseif ($shiftName === 'Rest') $shift = 'R';
                    break;
                }
            }

            $class = $shift ? "class='$shift'" : '';
            echo "<td $class>$shift</td>";
        }

        echo "</tr>";
    }

    echo "</table>

    <div class='legend'>
        <strong>Legend:</strong><br>
        M = Morning (09:00-15:00) | A = Afternoon (15:00-21:00) | N = Night (21:00-09:00) | R = Rest
    </div>
</body>
</html>";
}

// Main execution
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'generate':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            generateTimetable($month, $year);
            break;

        case 'view':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            displayTimetable($month, $year);
            break;

        default:
            echo "Invalid action. Use ?action=generate or ?action=view";
    }
} else {
    echo "<h1>New Timetable Management System</h1>";
    echo "<p>The old timetable has been deleted. Use the following options to create a new one:</p>";
    echo "<ul>";
    echo "<li><a href='?action=generate&month=" . date('m') . "&year=" . date('Y') . "'>Generate New Timetable for Current Month</a></li>";
    echo "<li><a href='?action=view&month=" . date('m') . "&year=" . date('Y') . "'>View Current Timetable</a></li>";
    echo "</ul>";

    echo "<h2>Available Staff:</h2>";
    echo "<ul>";
    foreach ($staffList as $staff) {
        echo "<li>$staff</li>";
    }
    echo "</ul>";
}
?>