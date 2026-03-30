<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if ((defined('DB_TYPE') ? DB_TYPE : 'sqlite') !== 'sqlite') {
    echo "Skipping SQLite index bootstrap because DB_TYPE is not sqlite\n";
    exit(0);
}

$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents(__DIR__ . '/performance_indexes.sql');
if ($sql === false) {
    throw new RuntimeException('Unable to read performance_indexes.sql');
}

$statements = array_filter(array_map('trim', explode(';', $sql)));
$applied = 0;
$skipped = 0;

$tableRows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$existingTables = array_flip($tableRows ?: []);

foreach ($statements as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }

    if (preg_match('/\bON\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $statement, $match) === 1) {
        $tableName = $match[1];
        if (!isset($existingTables[$tableName])) {
            $skipped++;
            continue;
        }
    }

    $pdo->exec($statement);
    $applied++;
}

echo "Applied {$applied} index statements; skipped {$skipped} for missing tables\n";
