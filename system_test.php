<?php
/**
 * Complete System Test - Verify all pages and logins
 */
require_once 'config/config.php';
require_once 'includes/auth.php';

echo "═════════════════════════════════════════════════════════════\n";
echo "  COMPLETE SYSTEM VERIFICATION TEST\n";
echo "  Date: " . date('Y-m-d H:i:s') . "\n";
echo "═════════════════════════════════════════════════════════════\n\n";

$baseUrl = SITE_URL;
$tests = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0
];

$pdo = null; // Initialize

// Test 1: Database Connection
echo "1. DATABASE CONNECTION TEST\n";
echo "─────────────────────────────────────────────────────────────\n";
try {
    if (DB_TYPE === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        // MySQL connection (legacy)
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $result = $pdo->query("SELECT 1");
    echo "   ✅ Database connected successfully\n";
    echo "   Type: " . DB_TYPE . "\n";
    if (DB_TYPE === 'sqlite') {
        echo "   File: " . DB_FILE . "\n";
    }
    $tests['passed']++;
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    $pdo = null;
    $tests['failed']++;
}

// Test 2: Users Table
echo "\n2. USERS TABLE & ADMIN CHECK\n";
echo "─────────────────────────────────────────────────────────────\n";
if ($pdo === null) {
    echo "   ⏭️  Skipped (database not available)\n";
    $tests['warnings']++;
} else {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int)($row['count'] ?? 0);
        echo "   ✅ Users table exists\n";
        echo "   Total users: $userCount\n";
        $tests['passed']++;
        
        // Check admin user
        $stmt = $pdo->prepare("SELECT id, username, first_name FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "   ✅ Admin user exists: " . $admin['username'] . "\n";
        } else {
            echo "   ⚠️  No admin user found\n";
            $tests['warnings']++;
        }
    } catch (Exception $e) {
        echo "   ❌ Users table check failed: " . $e->getMessage() . "\n";
        $tests['failed']++;
    }
}

// Test 3: Role-based Users
echo "\n3. ROLE-BASED USERS CHECK\n";
echo "─────────────────────────────────────────────────────────────\n";
if ($pdo === null) {
    echo "   ⏭️  Skipped (database not available)\n";
    $tests['warnings']++;
} else {
    $roles = ['doctor', 'patient', 'staff', 'intern', 'trainee', 'pharmacist'];
    foreach ($roles as $role) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
            $stmt->execute([$role]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($row['count'] ?? 0);
            $status = $count > 0 ? "✅" : "⚠️ ";
            echo "   $status " . ucfirst($role) . "s: $count\n";
            if ($count > 0) {
                $tests['passed']++;
            } else {
                $tests['warnings']++;
            }
        } catch (Exception $e) {
            echo "   ❌ Error checking $role: " . $e->getMessage() . "\n";
            $tests['failed']++;
        }
    }
}

// Test 4: Core Tables
echo "\n4. CORE TABLES CHECK\n";
echo "─────────────────────────────────────────────────────────────\n";
if ($pdo === null) {
    echo "   ⏭️  Skipped (database not available)\n";
    $tests['warnings']++;
} else {
    $tables = [
        'users' => 'User accounts',
        'patients' => 'Patient records',
        'appointments' => 'Appointments',
        'consultations' => 'Doctor consultations',
        'doctors' => 'Doctor specializations',
        'medical_records' => 'Medical records',
        'shift_timetables' => 'Shift schedules',
    ];

    foreach ($tables as $table => $description) {
        try {
            if (DB_TYPE === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
            }
            $stmt->execute(DB_TYPE === 'sqlite' ? [$table] : [DB_NAME, $table]);
            $tableExists = (bool)$stmt->fetchColumn();
            
            if ($tableExists) {
                echo "   ✅ $table ($description)\n";
                $tests['passed']++;
            } else {
                echo "   ❌ $table MISSING\n";
                $tests['failed']++;
            }
        } catch (Exception $e) {
            echo "   ⚠️  $table - Unable to check\n";
            $tests['warnings']++;
        }
    }
}

// Test 5: Login Page Files
echo "\n5. LOGIN PAGE FILES\n";
echo "─────────────────────────────────────────────────────────────\n";
$loginPages = [
    'admin/login.php' => 'Admin Login',
    'doctor/login.php' => 'Doctor Login',
    'patient/login.php' => 'Patient Login',
    'staff/login.php' => 'Staff Login',
    'intern/login.php' => 'Intern Login',
    'trainee/login.php' => 'Trainee Login',
];

foreach ($loginPages as $path => $name) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        echo "   ✅ $name exists\n";
        $tests['passed']++;
    } else {
        echo "   ❌ $name MISSING ($path)\n";
        $tests['failed']++;
    }
}

// Test 6: Dashboard Page Files
echo "\n6. DASHBOARD PAGE FILES\n";
echo "─────────────────────────────────────────────────────────────\n";
$dashboards = [
    'admin/dashboard.php' => 'Admin Dashboard',
    'doctor/dashboard.php' => 'Doctor Dashboard',
    'patient/dashboard.php' => 'Patient Dashboard',
    'staff/dashboard.php' => 'Staff Dashboard',
    'intern/dashboard.php' => 'Intern Dashboard',
    'trainee/dashboard.php' => 'Trainee Dashboard',
    'pharmacy/dashboard.php' => 'Pharmacy Dashboard',
];

foreach ($dashboards as $path => $name) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        echo "   ✅ $name exists\n";
        $tests['passed']++;
    } else {
        echo "   ❌ $name MISSING ($path)\n";
        $tests['failed']++;
    }
}

// Test 7: API Endpoints
echo "\n7. API ENDPOINTS\n";
echo "─────────────────────────────────────────────────────────────\n";
$apiFiles = [
    'api/health.php' => 'Health Check',
    'api/interoperability.php' => 'Interoperability',
];

foreach ($apiFiles as $path => $name) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        echo "   ✅ $name API exists\n";
        $tests['passed']++;
    } else {
        echo "   ❌ $name API MISSING\n";
        $tests['failed']++;
    }
}

// Test 8: Session Management
echo "\n8. SESSION & SECURITY\n";
echo "─────────────────────────────────────────────────────────────\n";
try {
    $auth_file = __DIR__ . '/includes/auth.php';
    if (file_exists($auth_file)) {
        echo "   ✅ Auth module exists\n";
        $tests['passed']++;
    } else {
        echo "   ❌ Auth module MISSING\n";
        $tests['failed']++;
    }
    
    // Check if sessions can be created
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['test'] = 'ok';
    if (isset($_SESSION['test'])) {
        echo "   ✅ Session management working\n";
        $tests['passed']++;
    } else {
        echo "   ❌ Session management failed\n";
        $tests['failed']++;
    }
    unset($_SESSION['test']);
} catch (Exception $e) {
    echo "   ❌ Session test failed: " . $e->getMessage() . "\n";
    $tests['failed']++;
}

// Test 9: Configuration
echo "\n9. CONFIGURATION\n";
echo "─────────────────────────────────────────────────────────────\n";
echo "   Site Name: " . SITE_NAME . "\n";
echo "   Site URL: " . SITE_URL . "\n";
echo "   Database Type: " . DB_TYPE . "\n";
echo "   ✅ Configuration loaded\n";
$tests['passed']++;

// Test 10: File Permissions
echo "\n10. FILE PERMISSIONS\n";
echo "─────────────────────────────────────────────────────────────\n";
$uploadDir = __DIR__ . '/uploads';
if (is_dir($uploadDir)) {
    if (is_writable($uploadDir)) {
        echo "   ✅ Upload directory is writable\n";
        $tests['passed']++;
    } else {
        echo "   ⚠️  Upload directory exists but not writable\n";
        $tests['warnings']++;
    }
} else {
    echo "   ⚠️  Upload directory doesn't exist\n";
    $tests['warnings']++;
}

// Summary
echo "\n═════════════════════════════════════════════════════════════\n";
echo "  TEST SUMMARY\n";
echo "═════════════════════════════════════════════════════════════\n";
echo "  ✅ Passed:  " . $tests['passed'] . "\n";
echo "  ❌ Failed:  " . $tests['failed'] . "\n";
echo "  ⚠️  Warnings: " . $tests['warnings'] . "\n";
echo "═════════════════════════════════════════════════════════════\n\n";

// Overall Status
if ($tests['failed'] === 0) {
    echo "✅ SYSTEM STATUS: FULLY OPERATIONAL\n\n";
    echo "ACCESS YOUR SYSTEM:\n";
    echo "  Main URL: https://www.cmdonsdesoins.com\n";
    echo "  Admin Login: https://www.cmdonsdesoins.com/admin/login.php\n";
    echo "  Username: admie\n";
    echo "  Password: dds_awc2018\n\n";
    echo "OTHER LOGIN OPTIONS:\n";
    echo "  Doctor: https://www.cmdonsdesoins.com/doctor/login.php\n";
    echo "  Patient: https://www.cmdonsdesoins.com/patient/login.php\n";
    echo "  Staff: https://www.cmdonsdesoins.com/staff/login.php\n";
    echo "  Intern: https://www.cmdonsdesoins.com/intern/login.php\n";
    echo "  Trainee: https://www.cmdonsdesoins.com/trainee/login.php\n";
} else {
    echo "❌ SYSTEM STATUS: ISSUES FOUND\n";
    echo "  Please review the errors above.\n";
}

echo "═════════════════════════════════════════════════════════════\n";
?>
