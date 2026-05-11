<?php
/**
 * Timetable Management Web Interface
 * Based on the provided Node.js/Express roster system
 * Access at: http://localhost/AWCD/timetable_manager.php
 */

// For web access, allow basic access without full authentication
$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Simple response handler
header('Content-Type: application/json; charset=UTF-8');

// Try to get database connection
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (file_exists(__DIR__ . '/database/clinic.db')) {
                // Use SQLite via CLI
                return null;
            } else {
                // Try MySQL
                $pdo = new PDO(
                    'mysql:host=localhost;dbname=clinic',
                    'root',
                    ''
                );
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            return null;
        }
    }
    return $pdo;
}

// Staff list
$staffList = [
    "Abeng", "Mayan", "Nloga", "Favour",
    "Wiltite", "Kadija", "Nyanze",
    "Mvogo", "Nagayena", "Ndong", "Zad", "Florinda"
];

$response = [];

// Handle different actions
switch ($action) {
    case 'info':
        $response = [
            'status' => 'success',
            'message' => 'Old timetable has been deleted. Ready for new timetable data.',
            'staffList' => $staffList,
            'shiftTypes' => [
                'M' => 'Morning (09:00-15:00)',
                'A' => 'Afternoon (15:00-21:00)',
                'N' => 'Night (21:00-09:00)',
                'R' => 'Rest',
                'OFF' => 'Day Off',
                'SICK' => 'Sick Leave',
                'LATE' => 'Late',
                'ABSENT' => 'Absent'
            ],
            'instructions' => 'Ready to import new timetable data. Please provide the timetable data with staff names, dates, and shift assignments.'
        ];
        break;

    case 'import':
        // Handle timetable import
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['month']) || !isset($input['data'])) {
                http_response_code(400);
                $response = [
                    'status' => 'error',
                    'message' => 'Missing required fields: month and data'
                ];
            } else {
                $response = [
                    'status' => 'success',
                    'message' => 'Timetable data received. Ready to import.',
                    'recordsReceived' => count($input['data']),
                    'month' => $input['month'],
                    'note' => 'Data validation and database import requires direct CLI/database access.'
                ];
            }
        }
        break;

    case 'view':
        // Display timetable for the month
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        $response = [
            'status' => 'success',
            'message' => 'Timetable management system is ready',
            'month' => $month,
            'year' => $year,
            'staffList' => $staffList,
            'note' => 'Use the HTML interface below to manage the timetable'
        ];
        break;

    default:
        http_response_code(400);
        $response = [
            'status' => 'error',
            'message' => 'Invalid action. Use: info, import, or view'
        ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>