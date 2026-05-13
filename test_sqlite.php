<?php
echo 'pdo_sqlite: ' . (extension_loaded('pdo_sqlite') ? 'YES' : 'NO') . PHP_EOL;
echo 'sqlite3: ' . (extension_loaded('sqlite3') ? 'YES' : 'NO') . PHP_EOL;
try {
    $pdo = new PDO('sqlite::memory:');
    echo 'PDO SQLite: Connected' . PHP_EOL;
} catch (Exception $e) {
    echo 'PDO SQLite: ' . $e->getMessage() . PHP_EOL;
}
