<?php
require_once '../config/config.php';

header('Content-Type: application/json; charset=UTF-8');

$response = [
    'service' => SITE_NAME,
    'status' => 'degraded',
    'generated_at' => gmdate('c'),
    'region' => 'global',
    'timezone' => 'UTC',
    'checks' => [
        'database' => 'down',
    ],
    'supported_locales' => ['en', 'fr'],
    'security' => [
        'transport' => 'https-ready',
        'auth' => 'role-based',
    ],
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
} catch (Throwable $e) {
    error_log('Health check failure: ' . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
