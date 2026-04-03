<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/realtime_gateway.php';

header('Content-Type: application/json; charset=UTF-8');

$response = [
    'service' => SITE_NAME,
    'status' => 'degraded',
    'generated_at' => gmdate('c'),
    'region' => 'global',
    'timezone' => 'UTC',
    'checks' => [
        'database' => 'down',
        'sms_gateway' => 'down',
        'email_gateway' => 'down',
        'voice_gateway' => 'down',
        'payment_gateway' => 'down',
    ],
    'supported_locales' => ['en', 'fr'],
    'security' => [
        'transport' => 'https-ready',
        'auth' => 'role-based',
    ],
    'gateways' => rtGatewayStatus(),
];

try {
    if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    } else {
        $pdo = new PDO('sqlite:' . DB_FILE);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query('SELECT 1');

    $response['status'] = 'operational';
    $response['checks']['database'] = 'up';

    $gatewayStatus = rtGatewayStatus();
    $response['gateways'] = $gatewayStatus;
    $response['checks']['sms_gateway'] = $gatewayStatus['sms'] === 'configured' ? 'up' : 'down';
    $response['checks']['email_gateway'] = $gatewayStatus['email'] === 'configured' ? 'up' : 'down';
    $response['checks']['voice_gateway'] = $gatewayStatus['voice'] === 'configured' ? 'up' : 'down';
    $response['checks']['payment_gateway'] = $gatewayStatus['payments'] === 'configured' ? 'up' : 'down';
} catch (Throwable $e) {
    error_log('Health check failure: ' . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
