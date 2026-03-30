<?php
try {
    $pdo = new PDO('sqlite:database/clinic.db');
    $result = $pdo->query('SELECT name FROM sqlite_master WHERE type="table"');
    echo "Tables in database:\n";
    foreach($result as $row) {
        echo "- " . $row['name'] . "\n";
    }
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>