#!/usr/bin/env php
<?php
/**
 * Production Monitoring Script
 * Run this script periodically to check system health
 */

echo "🔍 AWCD Production Monitoring\n";
echo "==============================\n\n";

$checks = [
    'health_api' => [
        'url' => 'https://awcd.onrender.com/api/health.php',
        'name' => 'Health API',
        'type' => 'http_json'
    ],
    'main_site' => [
        'url' => 'https://www.cmdonsdesoins.com/',
        'name' => 'Main Website',
        'type' => 'http_status'
    ],
    'database_backup' => [
        'path' => 'database/clinic.db',
        'name' => 'Database File',
        'type' => 'file_exists'
    ]
];

$results = ['passed' => 0, 'failed' => 0, 'warnings' => 0];

foreach ($checks as $check) {
    echo "Checking: {$check['name']}\n";

    switch ($check['type']) {
        case 'http_json':
            $result = checkHttpJson($check['url']);
            break;
        case 'http_status':
            $result = checkHttpStatus($check['url']);
            break;
        case 'file_exists':
            $result = checkFileExists($check['path']);
            break;
        default:
            $result = ['status' => 'error', 'message' => 'Unknown check type'];
    }

    if ($result['status'] === 'ok') {
        echo "  ✅ PASSED: {$result['message']}\n";
        $results['passed']++;
    } elseif ($result['status'] === 'warning') {
        echo "  ⚠️  WARNING: {$result['message']}\n";
        $results['warnings']++;
    } else {
        echo "  ❌ FAILED: {$result['message']}\n";
        $results['failed']++;
    }

    echo "\n";
}

echo "📊 Monitoring Summary\n";
echo "=====================\n";
echo "✅ Passed: {$results['passed']}\n";
echo "⚠️  Warnings: {$results['warnings']}\n";
echo "❌ Failed: {$results['failed']}\n\n";

if ($results['failed'] > 0) {
    echo "🚨 ALERT: System issues detected! Check logs and investigate.\n";
    exit(1);
} elseif ($results['warnings'] > 0) {
    echo "⚠️  WARNING: Some checks have warnings. Monitor closely.\n";
    exit(0);
} else {
    echo "✅ All systems operational.\n";
    exit(0);
}

function checkHttpJson($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => 'Accept: application/json'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['status' => 'error', 'message' => 'Cannot connect to API'];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => 'Invalid JSON response'];
    }

    if (($data['status'] ?? '') === 'operational') {
        return ['status' => 'ok', 'message' => 'API operational, database connected'];
    } elseif (($data['status'] ?? '') === 'degraded') {
        return ['status' => 'warning', 'message' => 'API degraded, some services down'];
    } else {
        return ['status' => 'error', 'message' => 'API not operational'];
    }
}

function checkHttpStatus($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'HEAD'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['status' => 'error', 'message' => 'Cannot connect to website'];
    }

    // Check if we got a successful HTTP status
    $headers = http_get_last_response_headers();
    if ($headers) {
        $statusLine = $headers[0];
        if (preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $statusLine, $matches)) {
            $statusCode = (int)$matches[1];
            if ($statusCode >= 200 && $statusCode < 400) {
                return ['status' => 'ok', 'message' => "HTTP $statusCode - Website accessible"];
            } else {
                return ['status' => 'error', 'message' => "HTTP $statusCode - Website error"];
            }
        }
    }

    return ['status' => 'warning', 'message' => 'Cannot determine HTTP status'];
}

function checkFileExists($path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        return ['status' => 'ok', 'message' => "File exists ({$size} bytes, modified {$modified})"];
    } else {
        return ['status' => 'error', 'message' => 'File does not exist'];
    }
}
?></content>
<parameter name="filePath">c:\Users\TECHWAVE\Desktop\AWCD\monitor.php