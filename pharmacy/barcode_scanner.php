<?php
/**
 * Pharmacy Barcode Scanner Interface
 * Web-based barcode scanning for adding and selling drugs
 * Mobile-friendly design for use on phones and tablets
 */

require_once '../config/config.php';
require_once '../includes/auth.php';

requireLogin();

// Check if user has pharmacy access
if (!in_array($_SESSION['user']['role'], ['pharmacist', 'staff', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$message = '';
$error = '';
$scanResult = null;

// Get API key for barcode endpoint
$apiKey = hash_hmac('sha256', SITE_URL, ENCRYPTION_KEY);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner - <?php echo SITE_NAME; ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/css/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .scanner-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
        }

        .scanner-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            padding: 30px;
            margin-bottom: 20px;
        }

        .scanner-title {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .scanner-title h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .scanner-title p {
            color: #666;
            font-size: 14px;
        }

        .barcode-input {
            font-size: 16px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            letter-spacing: 2px;
        }

        .barcode-input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .tab-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px;
            border: none;
            border-radius: 8px;
            background: #f0f0f0;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #667eea;
            color: white;
        }

        .tab-btn:hover {
            transform: translateY(-2px);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            border-radius: 8px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            border: none;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #48bb78;
            border: none;
        }

        .result-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .result-box.success {
            border-left-color: #48bb78;
            background: #f0fdf4;
        }

        .result-box.error {
            border-left-color: #f56565;
            background: #fdf2f2;
        }

        .drug-info {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .drug-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .drug-info-row:last-child {
            border-bottom: none;
        }

        .drug-info-label {
            font-weight: 600;
            color: #666;
        }

        .drug-info-value {
            color: #333;
            font-weight: 500;
        }

        .success-checkmark {
            color: #48bb78;
            font-size: 32px;
            text-align: center;
            margin-bottom: 10px;
        }

        .error-icon {
            color: #f56565;
            font-size: 32px;
            text-align: center;
            margin-bottom: 10px;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px;
            color: #1e40af;
            font-size: 14px;
            margin-top: 15px;
        }

        .camera-view {
            width: 100%;
            max-width: 400px;
            height: 300px;
            background: #000;
            border-radius: 8px;
            margin: 15px 0;
        }

        .camera-container {
            text-align: center;
        }

        .camera-view-wrapper {
            position: relative;
            display: inline-block;
        }

        #interactive {
            position: relative;
        }

        #interactive video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        #interactive canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .camera-status {
            font-size: 12px;
        }

        .instruction {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 12px;
            color: #92400e;
            font-size: 13px;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            .scanner-container {
                margin: 10px;
                padding: 10px;
            }

            .scanner-card {
                padding: 20px;
            }

            .tab-buttons {
                gap: 5px;
            }

            .tab-btn {
                font-size: 12px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(0, 0, 0, 0.3);">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="bi bi-hospital"></i> <?php echo SITE_NAME; ?>
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link text-white">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['first_name']); ?>
            </span>
            <a class="nav-link text-white" href="<?php echo $_SESSION['user']['role'] === 'admin' ? '../admin/dashboard.php' : '../pharmacy/dashboard.php'; ?>">
                Dashboard
            </a>
            <a class="nav-link text-white" href="../index.php?logout">Logout</a>
        </div>
    </div>
</nav>

<div class="scanner-container">
    <div class="scanner-card">
        <div class="scanner-title">
            <h2><i class="bi bi-qr-code-scan"></i> Barcode Scanner</h2>
            <p>Scan or manually enter barcodes to manage pharmacy inventory</p>
        </div>

        <!-- Camera Controls -->
        <div class="camera-controls mb-3">
            <div class="d-flex justify-content-center gap-2">
                <button id="start-camera" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-camera"></i> Start Camera
                </button>
                <button id="stop-camera" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="bi bi-camera-video-off"></i> Stop Camera
                </button>
            </div>
        </div>

        <!-- Camera View -->
        <div id="camera-container" class="camera-container mb-3" style="display: none;">
            <div class="camera-view-wrapper">
                <div id="interactive" class="viewport">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="canvas" class="drawingBuffer"></canvas>
                </div>
            </div>
            <div class="camera-status text-center mt-2">
                <small id="camera-status" class="text-muted">Camera ready</small>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('lookup')">
                <i class="bi bi-search"></i> Lookup
            </button>
            <button class="tab-btn" onclick="switchTab('add')">
                <i class="bi bi-plus-circle"></i> Add Drug
            </button>
            <button class="tab-btn" onclick="switchTab('sell')">
                <i class="bi bi-bag-check"></i> Sell
            </button>
            <button class="tab-btn" onclick="switchTab('stock')">
                <i class="bi bi-box-seam"></i> Update Stock
            </button>
        </div>

        <!-- LOOKUP TAB -->
        <div id="lookup" class="tab-content active">
            <div class="instruction">
                <i class="bi bi-info-circle"></i> Use camera to scan barcode or enter it manually below
            </div>
            <div class="form-group">
                <label class="form-label">Barcode</label>
                <input type="text" id="lookup_barcode" class="form-control barcode-input" 
                       placeholder="Scan or type barcode..." autofocus>
            </div>
            <button class="btn btn-primary w-100" onclick="lookupDrug()">
                <i class="bi bi-search"></i> Lookup Drug
            </button>
            <div id="lookup_result"></div>
        </div>

        <!-- ADD DRUG TAB -->
        <div id="add" class="tab-content">
            <div class="instruction">
                <i class="bi bi-info-circle"></i> Scan drug barcode with camera or enter manually to add new drug
            </div>
            <form id="add_form">
                <div class="form-group">
                    <label class="form-label">Barcode *</label>
                    <input type="text" id="add_barcode" class="form-control" placeholder="Scan or enter barcode" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Drug Name *</label>
                    <input type="text" id="add_name" class="form-control" placeholder="Enter drug name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select id="add_category" class="form-control">
                        <option value="">Select category</option>
                        <option value="Antibiotics">Antibiotics</option>
                        <option value="Painkillers">Painkillers</option>
                        <option value="Vitamins">Vitamins</option>
                        <option value="Antacids">Antacids</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Price (FCFA) *</label>
                    <input type="number" id="add_price" class="form-control" placeholder="0.00" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity in Stock *</label>
                    <input type="number" id="add_quantity" class="form-control" placeholder="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" id="add_manufacturer" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" id="add_expiry" class="form-control">
                </div>
                <button type="button" class="btn btn-primary w-100" onclick="addDrug()">
                    <i class="bi bi-plus-circle"></i> Add Drug
                </button>
                <div id="add_result"></div>
            </form>
        </div>

        <!-- SELL TAB -->
        <div id="sell" class="tab-content">
            <div class="instruction">
                <i class="bi bi-info-circle"></i> Scan drug barcode with camera, enter quantity to sell
            </div>
            <div class="form-group">
                <label class="form-label">Barcode *</label>
                <input type="text" id="sell_barcode" class="form-control barcode-input" 
                       placeholder="Scan barcode..." required>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity to Sell *</label>
                <input type="number" id="sell_quantity" class="form-control" placeholder="Enter quantity" min="1" required>
            </div>
            <button class="btn btn-success w-100" onclick="sellDrug()">
                <i class="bi bi-bag-check"></i> Sell Drug
            </button>
            <div id="sell_result"></div>
        </div>

        <!-- UPDATE STOCK TAB -->
        <div id="stock" class="tab-content">
            <div class="instruction">
                <i class="bi bi-info-circle"></i> Scan drug barcode with camera and update stock quantity
            </div>
            <div class="form-group">
                <label class="form-label">Barcode *</label>
                <input type="text" id="stock_barcode" class="form-control barcode-input" 
                       placeholder="Scan barcode..." required>
            </div>
            <div class="form-group">
                <label class="form-label">New Quantity *</label>
                <input type="number" id="stock_quantity" class="form-control" placeholder="Enter new quantity" required>
            </div>
            <button class="btn btn-primary w-100" onclick="updateStock()">
                <i class="bi bi-box-seam"></i> Update Stock
            </button>
            <div id="stock_result"></div>
        </div>
    </div>

    <div class="scanner-card" style="text-align: center; font-size: 13px; color: #666;">
        <i class="bi bi-shield-check"></i> Secure API Connection<br>
        All barcode scans are encrypted and logged for audit purposes
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    const API_KEY = '<?php echo $apiKey; ?>';
    const BARCODE_ENDPOINT = '../api/barcode.php';

    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');

        // Focus on appropriate input
        const inputs = {
            'lookup': 'lookup_barcode',
            'add': 'add_barcode',
            'sell': 'sell_barcode',
            'stock': 'stock_barcode'
        };
        if (inputs[tabName]) {
            setTimeout(() => document.getElementById(inputs[tabName]).focus(), 100);
        }
    }

    function lookupDrug() {
        const barcode = document.getElementById('lookup_barcode').value.trim();
        if (!barcode) {
            showResult('lookup_result', 'error', 'Please enter a barcode');
            return;
        }

        fetch(`${BARCODE_ENDPOINT}?action=lookup&barcode=${encodeURIComponent(barcode)}&api_key=${API_KEY}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'found') {
                    const drug = data.drug;
                    const html = `
                        <div class="result-box success">
                            <div class="success-checkmark"><i class="bi bi-check-circle"></i></div>
                            <strong>Drug Found!</strong>
                            <div class="drug-info">
                                <div class="drug-info-row">
                                    <span class="drug-info-label">Name:</span>
                                    <span class="drug-info-value">${drug.name}</span>
                                </div>
                                <div class="drug-info-row">
                                    <span class="drug-info-label">Barcode:</span>
                                    <span class="drug-info-value">${drug.barcode}</span>
                                </div>
                                <div class="drug-info-row">
                                    <span class="drug-info-label">Unit Price:</span>
                                    <span class="drug-info-value">${drug.unit_price} FCFA</span>
                                </div>
                                <div class="drug-info-row">
                                    <span class="drug-info-label">Stock:</span>
                                    <span class="drug-info-value">${drug.quantity_in_stock} units</span>
                                </div>
                                ${drug.category ? `<div class="drug-info-row">
                                    <span class="drug-info-label">Category:</span>
                                    <span class="drug-info-value">${drug.category}</span>
                                </div>` : ''}
                                ${drug.expiry_date ? `<div class="drug-info-row">
                                    <span class="drug-info-label">Expiry:</span>
                                    <span class="drug-info-value">${drug.expiry_date}</span>
                                </div>` : ''}
                            </div>
                        </div>
                    `;
                    document.getElementById('lookup_result').innerHTML = html;
                } else {
                    showResult('lookup_result', 'error', 'Drug not found. Add it to the system?');
                }
            })
            .catch(err => showResult('lookup_result', 'error', 'Error: ' + err.message));
    }

    function addDrug() {
        const data = {
            barcode: document.getElementById('add_barcode').value.trim(),
            name: document.getElementById('add_name').value.trim(),
            category: document.getElementById('add_category').value || null,
            unit_price: parseFloat(document.getElementById('add_price').value),
            quantity_in_stock: parseInt(document.getElementById('add_quantity').value),
            manufacturer: document.getElementById('add_manufacturer').value || null,
            expiry_date: document.getElementById('add_expiry').value || null
        };

        if (!data.barcode || !data.name || !data.unit_price || !data.quantity_in_stock) {
            showResult('add_result', 'error', 'Please fill all required fields');
            return;
        }

        fetch(`${BARCODE_ENDPOINT}?action=add_drug&api_key=${API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'created') {
                showResult('add_result', 'success', data.message);
                document.getElementById('add_form').reset();
                setTimeout(() => document.getElementById('add_barcode').focus(), 500);
            } else {
                showResult('add_result', 'error', data.error || 'Failed to add drug');
            }
        })
        .catch(err => showResult('add_result', 'error', 'Error: ' + err.message));
    }

    function sellDrug() {
        const barcode = document.getElementById('sell_barcode').value.trim();
        const quantity = parseInt(document.getElementById('sell_quantity').value);

        if (!barcode || !quantity || quantity < 1) {
            showResult('sell_result', 'error', 'Please enter valid barcode and quantity');
            return;
        }

        const data = { barcode, quantity };

        fetch(`${BARCODE_ENDPOINT}?action=sell&api_key=${API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'sold') {
                const html = `
                    <div class="result-box success">
                        <div class="success-checkmark"><i class="bi bi-check-circle"></i></div>
                        <strong>Sale Recorded!</strong>
                        <div class="drug-info">
                            <div class="drug-info-row">
                                <span class="drug-info-label">Quantity Sold:</span>
                                <span class="drug-info-value">${data.quantity_sold} units</span>
                            </div>
                            <div class="drug-info-row">
                                <span class="drug-info-label">Unit Price:</span>
                                <span class="drug-info-value">${data.unit_price} FCFA</span>
                            </div>
                            <div class="drug-info-row">
                                <span class="drug-info-label">Total Amount:</span>
                                <span class="drug-info-value"><strong>${data.total_amount} FCFA</strong></span>
                            </div>
                            <div class="drug-info-row">
                                <span class="drug-info-label">Stock Remaining:</span>
                                <span class="drug-info-value">${data.remaining_stock} units</span>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('sell_result').innerHTML = html;
                document.getElementById('sell_barcode').value = '';
                document.getElementById('sell_quantity').value = '';
                setTimeout(() => document.getElementById('sell_barcode').focus(), 500);
            } else {
                showResult('sell_result', 'error', data.error || 'Failed to record sale');
            }
        })
        .catch(err => showResult('sell_result', 'error', 'Error: ' + err.message));
    }

    function updateStock() {
        const barcode = document.getElementById('stock_barcode').value.trim();
        const quantity = parseInt(document.getElementById('stock_quantity').value);

        if (!barcode || !quantity || quantity < 0) {
            showResult('stock_result', 'error', 'Please enter valid barcode and quantity');
            return;
        }

        const data = { barcode, quantity };

        fetch(`${BARCODE_ENDPOINT}?action=update_stock&api_key=${API_KEY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'updated') {
                showResult('stock_result', 'success', data.message);
                document.getElementById('stock_barcode').value = '';
                document.getElementById('stock_quantity').value = '';
                setTimeout(() => document.getElementById('stock_barcode').focus(), 500);
            } else {
                showResult('stock_result', 'error', data.error || 'Failed to update stock');
            }
        })
        .catch(err => showResult('stock_result', 'error', 'Error: ' + err.message));
    }

    function showResult(elementId, type, message) {
        const html = `
            <div class="result-box ${type}">
                <div class="${type === 'success' ? 'success-checkmark' : 'error-icon'}">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'x-circle'}"></i>
                </div>
                ${message}
            </div>
        `;
        document.getElementById(elementId).innerHTML = html;
    }

    // Auto-focus and submit on barcode entry
    ['lookup_barcode', 'sell_barcode', 'stock_barcode'].forEach(id => {
        document.getElementById(id).addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                if (id === 'lookup_barcode') lookupDrug();
                else if (id === 'sell_barcode') sellDrug();
                else if (id === 'stock_barcode') updateStock();
            }
        });
    });

    // Camera and barcode scanning functionality
    let scannerActive = false;
    let currentTab = 'lookup';

    document.getElementById('start-camera').addEventListener('click', startScanner);
    document.getElementById('stop-camera').addEventListener('click', stopScanner);

    function startScanner() {
        const cameraContainer = document.getElementById('camera-container');
        const startBtn = document.getElementById('start-camera');
        const stopBtn = document.getElementById('stop-camera');
        const status = document.getElementById('camera-status');

        cameraContainer.style.display = 'block';
        startBtn.disabled = true;
        stopBtn.disabled = false;
        status.textContent = 'Initializing camera...';

        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#interactive'),
                constraints: {
                    width: 640,
                    height: 480,
                    facingMode: "environment" // Use back camera on mobile
                }
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: 2,
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader",
                    "code_39_reader",
                    "code_39_vin_reader",
                    "codabar_reader",
                    "upc_reader",
                    "upc_e_reader",
                    "i2of5_reader"
                ]
            },
            locate: true
        }, function(err) {
            if (err) {
                console.error(err);
                status.textContent = 'Camera error: ' + err.message;
                stopScanner();
                return;
            }
            status.textContent = 'Camera active - point at barcode';
            Quagga.start();
            scannerActive = true;
        });

        Quagga.onDetected(function(result) {
            const code = result.codeResult.code;
            status.textContent = 'Barcode detected: ' + code;

            // Fill the current active barcode input
            const activeInput = getActiveBarcodeInput();
            if (activeInput) {
                activeInput.value = code;
                // Auto-submit based on current tab
                setTimeout(() => {
                    switch(currentTab) {
                        case 'lookup':
                            lookupDrug();
                            break;
                        case 'sell':
                            document.getElementById('sell_quantity').focus();
                            break;
                        case 'stock':
                            document.getElementById('stock_quantity').focus();
                            break;
                        case 'add':
                            document.getElementById('add_name').focus();
                            break;
                    }
                }, 500);
            }

            // Stop scanning after successful detection
            setTimeout(stopScanner, 1000);
        });
    }

    function stopScanner() {
        const cameraContainer = document.getElementById('camera-container');
        const startBtn = document.getElementById('start-camera');
        const stopBtn = document.getElementById('stop-camera');
        const status = document.getElementById('camera-status');

        if (scannerActive) {
            Quagga.stop();
            scannerActive = false;
        }

        cameraContainer.style.display = 'none';
        startBtn.disabled = false;
        stopBtn.disabled = true;
        status.textContent = 'Camera stopped';
    }

    function getActiveBarcodeInput() {
        switch(currentTab) {
            case 'lookup':
                return document.getElementById('lookup_barcode');
            case 'add':
                return document.getElementById('add_barcode');
            case 'sell':
                return document.getElementById('sell_barcode');
            case 'stock':
                return document.getElementById('stock_barcode');
            default:
                return null;
        }
    }

    // Update current tab when switching
    function switchTab(tabName) {
        currentTab = tabName;

        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab
        document.getElementById(tabName).classList.add('active');
        event.target.closest('.tab-btn').classList.add('active');

        // Focus on appropriate input
        const inputs = {
            'lookup': 'lookup_barcode',
            'add': 'add_barcode',
            'sell': 'sell_barcode',
            'stock': 'stock_barcode'
        };
        if (inputs[tabName]) {
            setTimeout(() => document.getElementById(inputs[tabName]).focus(), 100);
        }
    }
</script>
</body>
</html>
