<?php

declare(strict_types=1);

namespace App;

use App\Config\Database;
use PDO;

require_once __DIR__ . '/backend/vendor/autoload.php';

Config\Config::load();

$pdo = Database::getConnection();

function columnExists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ');
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

foreach ([
    ['clients', 'email', 'VARCHAR(255) NULL'],
    ['clients', 'phone', 'VARCHAR(50) NULL'],
    ['clients', 'address', 'VARCHAR(500) NULL'],
    ['clients', 'active', 'TINYINT(1) NOT NULL DEFAULT 1'],
    ['sites',   'code',  'VARCHAR(50) NULL'],
    ['sites',   'active', 'TINYINT(1) NOT NULL DEFAULT 1'],
    ['products','reference','VARCHAR(80) NULL'],
] as [$table, $col, $def]) {
    if (!columnExists($pdo, $table, $col)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
        echo "+ Added {$table}.{$col}\n";
    } else {
        echo "= Exists {$table}.{$col}\n";
    }
}

if (!indexExists($pdo, 'sites', 'uk_sites_code')) {
    $pdo->exec('ALTER TABLE sites ADD UNIQUE KEY uk_sites_code (code)');
    echo "+ Added uk_sites_code\n";
}
if (!indexExists($pdo, 'products', 'uk_products_reference')) {
    $pdo->exec('ALTER TABLE products ADD UNIQUE KEY uk_products_reference (`reference`)');
    echo "+ Added uk_products_reference\n";
}
echo "Schema patched successfully.\n";
