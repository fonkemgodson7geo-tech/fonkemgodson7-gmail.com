<?php
// API endpoints for external system integration
// This file handles API requests for interoperability

require_once '../config/config.php';
require_once '../includes/auth.php';

// Enable CORS for external API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// API Key authentication
function authenticateApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $_GET['api_key'] ?? null;
    
    if (!$apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        exit;
    }
    
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND status = 'active'");
        $stmt->execute([$apiKey]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }
        
        return $key;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

// Get request data
function getRequestData() {
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

// Send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Log API request
function logApiRequest($apiKey, $endpoint, $method, $statusCode) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (api_key_id, endpoint, method, status_code, ip_address, user_agent, request_time)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $apiKey['id'],
            $endpoint,
            $method,
            $statusCode,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Log to file if database logging fails
        error_log("API Log Error: " . $e->getMessage());
    }
}

// Route API requests
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Extract endpoint from URI
$endpoint = str_replace('/api/', '', parse_url($requestUri, PHP_URL_PATH));

// Authenticate API key for all requests except health check
if ($endpoint !== 'health') {
    $apiKey = authenticateApiKey();
}

// Log the request
logApiRequest($apiKey ?? ['id' => null], $endpoint, $method, 200);

try {
    $pdo = getDB();
    
    switch ($endpoint) {
        case 'health':
            // Health check endpoint
            sendResponse([
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => '1.0.0'
            ]);
            break;
            
        case 'patients':
            if ($method === 'GET') {
                // Get patients (with optional filters)
                $query = "SELECT id, first_name, last_name, date_of_birth, gender, phone, email, address, emergency_contact, medical_record_number FROM patients WHERE 1=1";
                $params = [];
                
                if (isset($_GET['id'])) {
                    $query .= " AND id = ?";
                    $params[] = $_GET['id'];
                }
                
                if (isset($_GET['medical_record_number'])) {
                    $query .= " AND medical_record_number = ?";
                    $params[] = $_GET['medical_record_number'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['patients' => $patients]);
                
            } elseif ($method === 'POST') {
                // Create new patient
                $data = getRequestData();
                
                $stmt = $pdo->prepare("
                    INSERT INTO patients (first_name, last_name, date_of_birth, gender, phone, email, address, emergency_contact, medical_record_number, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['date_of_birth'],
                    $data['gender'],
                    $data['phone'],
                    $data['email'] ?? null,
                    $data['address'] ?? null,
                    $data['emergency_contact'] ?? null,
                    $data['medical_record_number'] ?? null
                ]);
                
                sendResponse(['id' => $pdo->lastInsertId(), 'message' => 'Patient created successfully'], 201);
            }
            break;
            
        case 'appointments':
            if ($method === 'GET') {
                // Get appointments
                $query = "
                    SELECT a.*, p.first_name as patient_first, p.last_name as patient_last, 
                           u.first_name as doctor_first, u.last_name as doctor_last
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN users u ON a.doctor_id = u.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (isset($_GET['patient_id'])) {
                    $query .= " AND a.patient_id = ?";
                    $params[] = $_GET['patient_id'];
                }
                
                if (isset($_GET['doctor_id'])) {
                    $query .= " AND a.doctor_id = ?";
                    $params[] = $_GET['doctor_id'];
                }
                
                if (isset($_GET['date'])) {
                    $query .= " AND DATE(a.appointment_date) = ?";
                    $params[] = $_GET['date'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['appointments' => $appointments]);
                
            } elseif ($method === 'POST') {
                // Create appointment
                $data = getRequestData();
                
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())
                ");
                
                $stmt->execute([
                    $data['patient_id'],
                    $data['doctor_id'],
                    $data['appointment_date'],
                    $data['appointment_time'],
                    $data['reason'],
                    $data['notes'] ?? null
                ]);
                
                sendResponse(['id' => $pdo->lastInsertId(), 'message' => 'Appointment created successfully'], 201);
            }
            break;
            
        case 'consultations':
            if ($method === 'GET') {
                // Get consultations
                $query = "
                    SELECT c.*, p.first_name as patient_first, p.last_name as patient_last,
                           u.first_name as doctor_first, u.last_name as doctor_last
                    FROM consultations c
                    JOIN patients p ON c.patient_id = p.id
                    JOIN users u ON c.doctor_id = u.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (isset($_GET['patient_id'])) {
                    $query .= " AND c.patient_id = ?";
                    $params[] = $_GET['patient_id'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['consultations' => $consultations]);
            }
            break;
            
        case 'medications':
            if ($method === 'GET') {
                // Get medications
                $query = "SELECT * FROM medications WHERE stock_quantity > 0";
                $params = [];
                
                if (isset($_GET['category'])) {
                    $query .= " AND category_id = ?";
                    $params[] = $_GET['category'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['medications' => $medications]);
            }
            break;
            
        case 'lab_results':
            if ($method === 'GET') {
                // Get lab results
                $query = "
                    SELECT lr.*, p.first_name as patient_first, p.last_name as patient_last,
                           u.first_name as doctor_first, u.last_name as doctor_last
                    FROM lab_results lr
                    JOIN patients p ON lr.patient_id = p.id
                    LEFT JOIN users u ON lr.ordered_by = u.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (isset($_GET['patient_id'])) {
                    $query .= " AND lr.patient_id = ?";
                    $params[] = $_GET['patient_id'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['lab_results' => $results]);
            }
            break;
            
        case 'prescriptions':
            if ($method === 'GET') {
                // Get prescriptions
                $query = "
                    SELECT pr.*, p.first_name as patient_first, p.last_name as patient_last,
                           u.first_name as doctor_first, u.last_name as doctor_last,
                           m.name as medication_name
                    FROM prescriptions pr
                    JOIN patients p ON pr.patient_id = p.id
                    JOIN users u ON pr.doctor_id = u.id
                    JOIN medications m ON pr.medication_id = m.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (isset($_GET['patient_id'])) {
                    $query .= " AND pr.patient_id = ?";
                    $params[] = $_GET['patient_id'];
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendResponse(['prescriptions' => $prescriptions]);
            }
            break;
            
        case 'sync':
            if ($method === 'POST') {
                // Handle data synchronization from external systems
                $data = getRequestData();
                $syncResults = [];
                
                // Process patients sync
                if (isset($data['patients'])) {
                    foreach ($data['patients'] as $patient) {
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO patients (first_name, last_name, date_of_birth, gender, phone, email, address, medical_record_number, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE
                                first_name = VALUES(first_name),
                                last_name = VALUES(last_name),
                                phone = VALUES(phone),
                                email = VALUES(email),
                                address = VALUES(address)
                            ");
                            
                            $stmt->execute([
                                $patient['first_name'],
                                $patient['last_name'],
                                $patient['date_of_birth'] ?? null,
                                $patient['gender'] ?? null,
                                $patient['phone'] ?? null,
                                $patient['email'] ?? null,
                                $patient['address'] ?? null,
                                $patient['medical_record_number'] ?? null
                            ]);
                            
                            $syncResults['patients'][] = ['status' => 'success', 'id' => $pdo->lastInsertId()];
                        } catch (Exception $e) {
                            $syncResults['patients'][] = ['status' => 'error', 'error' => $e->getMessage()];
                        }
                    }
                }
                
                sendResponse(['sync_results' => $syncResults, 'message' => 'Sync completed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>