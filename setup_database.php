<?php
// Database Setup Script
require_once 'config/config.php';

try {
    // Connect to MySQL without specifying a database
    $pdo = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "✓ Database '" . DB_NAME . "' created successfully.\n";

    // Connect to the specific database
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and execute SQL file
    $sql = file_get_contents('database/install.sql');

    // Remove the CREATE DATABASE and USE statements since we already created it
    $sql = preg_replace('/CREATE DATABASE.*$/m', '', $sql);
    $sql = preg_replace('/USE.*$/m', '', $sql);

    // Split into individual statements and execute them
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $tableCount = 0;
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $pdo->exec($statement);
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE (\w+)/i', $statement, $matches);
                    if (isset($matches[1])) {
                        echo "✓ Created table: {$matches[1]}\n";
                        $tableCount++;
                    }
                }
            } catch (Exception $e) {
                echo "⚠ Warning with statement: " . substr($statement, 0, 50) . "...\n";
                echo "   Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n🎉 Database setup completed successfully!\n";
    echo "📊 Total tables created: $tableCount\n";

    // Verify some key tables exist
    $tables = ['users', 'patients', 'doctors', 'appointments', 'pharmacy_inventory', 'reports', 'audit_logs'];
    echo "\n🔍 Verifying key tables:\n";

    foreach ($tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $result->fetchColumn();
            echo "✓ $table: OK ($count records)\n";
        } catch (Exception $e) {
            echo "✗ $table: Missing or error\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in config/config.php\n";
    exit(1);
}
?>