<?php
/**
 * Barcode Scanner API Endpoint
 * Handles barcode scans from mobile devices and scanning interfaces
 * Manages drug lookups and sales via barcode
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=UTF-8');

// Simple API key check for mobile device security
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? null;
$validApiKey = hash_hmac('sha256', SITE_URL, ENCRYPTION_KEY);

if (!$apiKey || !hash_equals($validApiKey, $apiKey)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'lookup':
            // Lookup drug by barcode
            $barcode = trim($_GET['barcode'] ?? $_POST['barcode'] ?? '');
            if (empty($barcode)) {
                http_response_code(400);
                echo json_encode(['error' => 'Barcode required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id, name, barcode, category, unit_price, quantity_in_stock, 
                    expiry_date, manufacturer, created_at
                FROM pharmacy_inventory
                WHERE barcode = ? OR UPPER(barcode) = UPPER(?)
                LIMIT 1
            ");
            $stmt->execute([$barcode, $barcode]);
            $drug = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($drug) {
                echo json_encode([
                    'status' => 'found',
                    'drug' => $drug
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'not_found',
                    'barcode' => $barcode
                ]);
            }
            break;
        
        case 'add_drug':
            // Add new drug with barcode
            $data = json_decode(file_get_contents('php://input'), true);
            
            $required = ['name', 'barcode', 'unit_price', 'quantity_in_stock'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }
            
            // Check if barcode already exists
            $check = $pdo->prepare("SELECT id FROM pharmacy_inventory WHERE barcode = ?");
            $check->execute([$data['barcode']]);
            if ($check->fetchColumn()) {
                http_response_code(409);
                echo json_encode(['error' => 'Barcode already exists']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO pharmacy_inventory 
                (name, barcode, category, unit_price, quantity_in_stock, manufacturer, expiry_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['barcode'],
                $data['category'] ?? null,
                (float)$data['unit_price'],
                (int)$data['quantity_in_stock'],
                $data['manufacturer'] ?? null,
                $data['expiry_date'] ?? null
            ]);
            
            $drugId = $pdo->lastInsertId();
            
            // Log initial stock movement
            require_once __DIR__ . '/../includes/pharmacy_inventory.php';
            pharmacyLogStockMovement(
                $pdo,
                (int)$drugId,
                'add',
                (int)$data['quantity_in_stock'],
                0,
                (int)$data['quantity_in_stock'],
                'New drug added via barcode scanner',
                'barcode_add',
                null,
                null,
                "Initial stock of {$data['quantity_in_stock']} units added via mobile barcode scanner"
            );
            
            echo json_encode([
                'status' => 'created',
                'drug_id' => $drugId,
                'message' => 'Drug added successfully'
            ]);
            break;
        
        case 'sell':
            // Record drug sale via barcode
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['barcode']) || empty($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Barcode and quantity required']);
                exit;
            }
            
            // Get drug by barcode
            $stmt = $pdo->prepare("SELECT id, quantity_in_stock, unit_price FROM pharmacy_inventory WHERE barcode = ?");
            $stmt->execute([$data['barcode']]);
            $drug = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$drug) {
                http_response_code(404);
                echo json_encode(['error' => 'Drug not found']);
                exit;
            }
            
            $quantity = (int)$data['quantity'];
            if ($quantity > $drug['quantity_in_stock']) {
                http_response_code(409);
                echo json_encode(['error' => 'Insufficient quantity in stock']);
                exit;
            }
            
            // Update inventory
            $newQuantity = $drug['quantity_in_stock'] - $quantity;
            $update = $pdo->prepare("UPDATE pharmacy_inventory SET quantity_in_stock = ? WHERE id = ?");
            $update->execute([$newQuantity, $drug['id']]);
            
            // Log stock movement
            require_once __DIR__ . '/../includes/pharmacy_inventory.php';
            pharmacyLogStockMovement(
                $pdo,
                (int)$drug['id'],
                'dispense',
                -$quantity,
                (int)$drug['quantity_in_stock'],
                $newQuantity,
                'Sale via barcode scanner',
                'barcode_sale',
                null,
                null, // performed_by - could be added if we track user sessions
                "Sold $quantity units via mobile barcode scanner"
            );
            
            // Calculate sale amount
            $saleAmount = $quantity * $drug['unit_price'];
            
            echo json_encode([
                'status' => 'sold',
                'drug_id' => $drug['id'],
                'quantity_sold' => $quantity,
                'unit_price' => $drug['unit_price'],
                'total_amount' => $saleAmount,
                'remaining_stock' => $newQuantity
            ]);
            break;
        
        case 'update_stock':
            // Update stock via barcode
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['barcode']) || !isset($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Barcode and quantity required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT id, quantity_in_stock FROM pharmacy_inventory WHERE barcode = ?");
            $stmt->execute([$data['barcode']]);
            $drug = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$drug) {
                http_response_code(404);
                echo json_encode(['error' => 'Drug not found']);
                exit;
            }
            
            $newQuantity = (int)$data['quantity'];
            $quantityChange = $newQuantity - $drug['quantity_in_stock'];
            
            $update = $pdo->prepare("UPDATE pharmacy_inventory SET quantity_in_stock = ? WHERE id = ?");
            $update->execute([$newQuantity, $drug['id']]);
            
            // Log stock movement
            require_once __DIR__ . '/../includes/pharmacy_inventory.php';
            pharmacyLogStockMovement(
                $pdo,
                (int)$drug['id'],
                $quantityChange >= 0 ? 'add' : 'adjust',
                $quantityChange,
                (int)$drug['quantity_in_stock'],
                $newQuantity,
                'Stock update via barcode scanner',
                'barcode_update',
                null,
                null,
                "Stock adjusted to $newQuantity units via mobile barcode scanner"
            );
            
            echo json_encode([
                'status' => 'updated',
                'message' => 'Stock updated successfully'
            ]);
            break;
        
        case 'list_drugs':
            // Get all drugs (for mobile app)
            $stmt = $pdo->query("
                SELECT id, name, barcode, category, unit_price, quantity_in_stock, expiry_date
                FROM pharmacy_inventory
                ORDER BY name ASC
            ");
            $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'total' => count($drugs),
                'drugs' => $drugs
            ]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Barcode API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
