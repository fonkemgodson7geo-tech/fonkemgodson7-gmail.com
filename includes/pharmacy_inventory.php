<?php

function pharmacyEnsureStockMovementTable(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $isSqlite = defined('DB_TYPE') && DB_TYPE === 'sqlite';

    if ($isSqlite) {
        $sql = "CREATE TABLE IF NOT EXISTS pharmacy_stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inventory_id INTEGER NOT NULL,
            movement_type TEXT NOT NULL CHECK (movement_type IN ('add', 'adjust', 'dispense', 'return', 'wastage')),
            quantity_change INTEGER NOT NULL,
            quantity_before INTEGER NOT NULL,
            quantity_after INTEGER NOT NULL,
            reason TEXT,
            reference_type TEXT,
            reference_id INTEGER,
            performed_by INTEGER,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
            FOREIGN KEY (performed_by) REFERENCES users(id)
        )";
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS pharmacy_stock_movements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inventory_id INT NOT NULL,
            movement_type ENUM('add', 'adjust', 'dispense', 'return', 'wastage') NOT NULL,
            quantity_change INT NOT NULL,
            quantity_before INT NOT NULL,
            quantity_after INT NOT NULL,
            reason VARCHAR(255),
            reference_type VARCHAR(50),
            reference_id INT,
            performed_by INT,
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_movement_inventory_created (inventory_id, created_at),
            FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
            FOREIGN KEY (performed_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    $pdo->exec($sql);
    $initialized = true;
}

function pharmacyLogStockMovement(
    PDO $pdo,
    int $inventoryId,
    string $movementType,
    int $quantityChange,
    int $quantityBefore,
    int $quantityAfter,
    string $reason,
    ?string $referenceType,
    ?int $referenceId,
    ?int $performedBy,
    ?string $note = null
): void {
    pharmacyEnsureStockMovementTable($pdo);

    $stmt = $pdo->prepare('INSERT INTO pharmacy_stock_movements (inventory_id, movement_type, quantity_change, quantity_before, quantity_after, reason, reference_type, reference_id, performed_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $inventoryId,
        $movementType,
        $quantityChange,
        $quantityBefore,
        $quantityAfter,
        $reason,
        $referenceType,
        $referenceId,
        $performedBy,
        $note,
    ]);
}
