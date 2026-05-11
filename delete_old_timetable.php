<?php
/**
 * Delete Old Timetable Data
 * This script removes all existing shift timetable records from the database
 */

require_once 'config/config.php';

try {
    if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    } else {
        $pdo = new PDO('sqlite:' . DB_FILE);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "═════════════════════════════════════════\n";
    echo "  DELETING OLD TIMETABLE DATA\n";
    echo "═════════════════════════════════════════\n\n";

    // Get current count before deletion
    $stmt = $pdo->query("SELECT COUNT(*) FROM shift_timetables");
    $countBefore = $stmt->fetchColumn();

    echo "📊 Current timetable records: $countBefore\n\n";

    if ($countBefore > 0) {
        // Delete all records from shift_timetables table
        $deleteStmt = $pdo->prepare("DELETE FROM shift_timetables");
        $deleteStmt->execute();
        $deletedCount = $deleteStmt->rowCount();

        echo "✅ Successfully deleted $deletedCount timetable records\n";

        // Verify deletion
        $stmt = $pdo->query("SELECT COUNT(*) FROM shift_timetables");
        $countAfter = $stmt->fetchColumn();

        echo "📊 Records after deletion: $countAfter\n";

        if ($countAfter == 0) {
            echo "\n🎉 Old timetable data has been completely removed!\n";
            echo "The system is now ready for new timetable data.\n";
        } else {
            echo "\n⚠️  Warning: Some records may still remain. Please check manually.\n";
        }
    } else {
        echo "ℹ️  No timetable records found to delete.\n";
        echo "The timetable is already empty.\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n═════════════════════════════════════════\n";
echo "  OPERATION COMPLETE\n";
echo "═════════════════════════════════════════\n";
?>