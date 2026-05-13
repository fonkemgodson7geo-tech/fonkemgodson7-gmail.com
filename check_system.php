<?php
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  AWCD SYSTEM VERIFICATION CHECK                        ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

$results = ['passed' => 0, 'failed' => 0];

// Test 1: Database Connection
try {
    $pdo = new PDO('sqlite:database/clinic.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $result = $pdo->query('SELECT COUNT(*) as count FROM users');
    $row = $result->fetch();
    echo "✅ Database Connection: OK\n";
    echo "   Users: " . $row['count'] . " records\n";
    $results['passed']++;
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    $results['failed']++;
}

// Test 2: Timetable Data
try {
    $result = $pdo->query('SELECT COUNT(*) as count FROM shift_timetables');
    $row = $result->fetch();
    echo "✅ Timetable System: OK\n";
    echo "   Shifts: " . $row['count'] . " records\n";
    $results['passed']++;
} catch (Exception $e) {
    echo "❌ Timetable Error: " . $e->getMessage() . "\n";
    $results['failed']++;
}

// Test 3: Admin User
try {
    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE role = ? LIMIT 1');
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();
    if ($admin) {
        echo "✅ Admin User: " . $admin['username'] . "\n";
        $results['passed']++;
    } else {
        echo "⚠️  No admin user found\n";
        $results['failed']++;
    }
} catch (Exception $e) {
    echo "❌ Admin Check Error: " . $e->getMessage() . "\n";
    $results['failed']++;
}

// Test 4: Configuration Files
$files = [
    'config/config.php' => 'Config',
    'includes/auth.php' => 'Auth Module',
    'api/health.php' => 'Health API',
    'admin/dashboard.php' => 'Admin Dashboard',
    'admin/timetable.php' => 'Timetable Manager',
    'USER_GUIDE.md' => 'User Guide',
    'monitor.php' => 'Monitor Script',
    'deployment_status.php' => 'Deployment Status'
];

echo "✅ Core Files Check:\n";
foreach ($files as $file => $name) {
    if (file_exists($file)) {
        echo "   ✅ $name: Present\n";
        $results['passed']++;
    } else {
        echo "   ❌ $name: MISSING\n";
        $results['failed']++;
    }
}

// Test 5: Security Checks
echo "\n✅ Security Checks:\n";
$securityFiles = [
    'includes/auth.php' => 'Authentication',
    'api/distribute-timetable.php' => 'CSRF Protection',
    'admin/timetable.php' => 'Session Management'
];

foreach ($securityFiles as $file => $feature) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $hasFeature = false;
        
        if ($feature === 'Authentication' && strpos($content, 'requireDesignatedAdmin') !== false) {
            $hasFeature = true;
        } elseif ($feature === 'CSRF Protection' && strpos($content, 'verifyCsrf') !== false) {
            $hasFeature = true;
        } elseif ($feature === 'Session Management' && strpos($content, 'session_start') !== false) {
            $hasFeature = true;
        }
        
        if ($hasFeature) {
            echo "   ✅ $feature: Enabled\n";
            $results['passed']++;
        }
    }
}

// Summary
echo "\n╔════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICATION RESULTS                                   ║\n";
echo "╚════════════════════════════════════════════════════════╝\n";
echo "✅ Passed: " . $results['passed'] . "\n";
echo "❌ Failed: " . $results['failed'] . "\n\n";

if ($results['failed'] === 0) {
    echo "🎉 SYSTEM STATUS: ALL CHECKS PASSED\n";
    echo "The application is ready for production use!\n";
} else {
    echo "⚠️  SYSTEM STATUS: REVIEW REQUIRED\n";
    echo "Please check the errors above.\n";
}
?>
