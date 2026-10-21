<?php

declare(strict_types=1);

namespace App;

use App\Config\Database;
use PDO;

require_once __DIR__ . '/vendor/autoload.php';

Config\Config::load();

$pdo = Database::getConnection();

echo "=== Seeding Facility Stock database (§26 complete) ===\n";

$password = 'password';
$passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

$clients = [
    ['name' => 'Oracle',            'code' => 'ORACLE', 'email' => 'contact@oracle.ma',        'phone' => '+212 5 37 00 00 00'],
    ['name' => 'Maroc Telecom',     'code' => 'IAM',    'email' => 'direction@iam.ma',         'phone' => '+212 5 37 77 77 77'],
    ['name' => 'Attijariwafa Bank', 'code' => 'AWB',    'email' => 'support.bureau@awb.ma',    'phone' => '+212 5 22 22 22 22'],
];

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE alerts');
$pdo->exec('TRUNCATE TABLE stock_movements');
$pdo->exec('TRUNCATE TABLE stocks');
$pdo->exec('TRUNCATE TABLE products');
$pdo->exec('TRUNCATE TABLE product_categories');
$pdo->exec('TRUNCATE TABLE users');
$pdo->exec('TRUNCATE TABLE sites');
$pdo->exec('TRUNCATE TABLE clients');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$clientStmt = $pdo->prepare('INSERT INTO clients (name, code, email, phone, address, active) VALUES (?, ?, ?, ?, NULL, 1)');
$clientIds = [];
foreach ($clients as $c) {
    $clientStmt->execute([$c['name'], $c['code'], $c['email'], $c['phone']]);
    $clientIds[] = (int)$pdo->lastInsertId();
}
echo "✓ Created " . count($clients) . " clients\n";

[$oracleId, $iamId, $awbId] = $clientIds;

$sites = [
    ['client_id' => $oracleId, 'code' => 'ORAC-RAB',  'name' => 'Oracle Rabat HQ',             'address' => 'Agdal, Rabat',                      'city' => 'Rabat',      'postal_code' => '10090'],
    ['client_id' => $oracleId, 'code' => 'ORAC-CAS',  'name' => 'Oracle Casablanca Office',    'address' => 'Maârif, Casablanca',                'city' => 'Casablanca', 'postal_code' => '20000'],
    ['client_id' => $iamId,    'code' => 'IAM-RAB',   'name' => 'IAM Centre Ville',            'address' => 'Boulevard Hassan II, Rabat',        'city' => 'Rabat',      'postal_code' => '10000'],
    ['client_id' => $iamId,    'code' => 'IAM-AGA',   'name' => 'IAM Agadir',                  'address' => 'Downtown Agadir',                   'city' => 'Agadir',     'postal_code' => '80000'],
    ['client_id' => $awbId,    'code' => 'AWB-CAS-HQ','name' => 'Attijariwafa Bank Siège',     'address' => 'Boulevard Moulay Youssef, Casa',    'city' => 'Casablanca', 'postal_code' => '20000'],
    ['client_id' => $awbId,    'code' => 'AWB-TECH',  'name' => 'AWB Centre Technologique',    'address' => 'Technopark, Rabat',                 'city' => 'Rabat',      'postal_code' => '10100'],
];

$siteStmt = $pdo->prepare('INSERT INTO sites (client_id, code, name, address, city, postal_code, country, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
$siteIds = [];
foreach ($sites as $s) {
    $siteStmt->execute([$s['client_id'], $s['code'], $s['name'], $s['address'], $s['city'], $s['postal_code'], 'Morocco']);
    $siteIds[] = (int)$pdo->lastInsertId();
}
echo "✓ Created " . count($sites) . " sites\n";

[$oracleRabatId, $oracleCasaId, $iamRabatId, $iamAgadirId, $awbSiegeId, $awbTechnoId] = $siteIds;

$users = [
    ['first_name' => 'Ahmed',   'last_name' => 'Admin',       'email' => 'admin@example.com',              'role' => 'ADMIN',         'site_id' => null],
    ['first_name' => 'Leila',   'last_name' => 'Manager',     'email' => 'manager@example.com',            'role' => 'MANAGER',       'site_id' => $oracleRabatId],
    ['first_name' => 'Yassine', 'last_name' => 'Benali',      'email' => 'stock.manager@example.com',      'role' => 'STOCK_MANAGER', 'site_id' => $oracleRabatId],
    ['first_name' => 'Sara',    'last_name' => 'El Amrani',   'email' => 'sara.elamrani@example.com',      'role' => 'STOCK_MANAGER', 'site_id' => $iamRabatId],
    ['first_name' => 'Karim',   'last_name' => 'Tazi',        'email' => 'karim.tazi@example.com',         'role' => 'MANAGER',       'site_id' => $iamAgadirId],
    ['first_name' => 'Salma',   'last_name' => 'Bennani',     'email' => 'salma.bennani@awb.ma',           'role' => 'MANAGER',       'site_id' => $awbSiegeId],
    ['first_name' => 'Mourad',  'last_name' => 'El Idrissi',  'email' => 'mourad.idrissi@awb.ma',          'role' => 'STOCK_MANAGER', 'site_id' => $awbTechnoId],
];

$userStmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, active, site_id) VALUES (?, ?, ?, ?, ?, 1, ?)');
foreach ($users as $u) {
    $userStmt->execute([$u['first_name'], $u['last_name'], $u['email'], $passwordHash, $u['role'], $u['site_id']]);
}
echo "✓ Created " . count($users) . " users (password: {$password})\n";
foreach ($users as $u) {
    echo "   - {$u['email']} / {$password}  [{$u['role']}]\n";
}

$categories = [
    ['name' => 'Eau & Boissons', 'description' => 'Bouteilles d\'eau, boissons fraîches, lait, café, thé'],
    ['name' => 'Papeterie',      'description' => 'Papier toilette, essuie-tout, mouchoirs, Papier A4'],
    ['name' => 'Hygiène',        'description' => 'Savon, gel hydroalcoolique, désinfectants'],
    ['name' => 'Nettoyage',      'description' => 'Produits d\'entretien ménager'],
    ['name' => 'Épicerie',       'description' => 'Café, thé, sucre, condiments consommables bureau'],
];

$catStmt = $pdo->prepare('INSERT INTO product_categories (name, description, active) VALUES (?, ?, 1)');
$catIds = [];
foreach ($categories as $c) {
    $catStmt->execute([$c['name'], $c['description']]);
    $catIds[] = (int)$pdo->lastInsertId();
}
echo "✓ Created " . count($categories) . " product categories\n";

[$eauId, $papierId, $hygieneId, $nettoyageId, $epicerieId] = $catIds;

$products = [
    ['category_id' => $eauId,      'name' => 'Eau minérale 1.5L',        'reference' => 'EAU-1500',  'sku' => 'EAU-1500',  'unit' => 'bouteilles', 'description' => 'Eau minérale 1,5L format bouteille PET',           'image_url' => '/assets/products/eau-1500.svg'],
    ['category_id' => $eauId,      'name' => 'Eau minérale 50cl',        'reference' => 'EAU-0500',  'sku' => 'EAU-0500',  'unit' => 'bouteilles', 'description' => 'Eau minérale 50cl format compact',                 'image_url' => '/assets/products/eau-0500.svg'],
    ['category_id' => $eauId,      'name' => 'Lait 1L',                  'reference' => 'LAIT-1L',   'sku' => 'LAIT-1L',   'unit' => 'bouteilles', 'description' => 'Lait demi-écrémé UHT 1L',                          'image_url' => '/assets/products/lait-1l.svg'],
    ['category_id' => $epicerieId, 'name' => 'Café moulu 500g',          'reference' => 'CAFE-500G', 'sku' => 'CAFE-500G', 'unit' => 'paquets',    'description' => 'Café moulu 500g pur arabica',                     'image_url' => '/assets/products/cafe-500g.svg'],
    ['category_id' => $epicerieId, 'name' => 'Thé vert 100 sachets',     'reference' => 'THE-VERT',  'sku' => 'THE-VERT',  'unit' => 'boites',     'description' => 'Thé vert 100 sachets',                             'image_url' => '/assets/products/the-vert.svg'],
    ['category_id' => $epicerieId, 'name' => 'Sucre en poudre 1kg',      'reference' => 'SUCRE-1KG', 'sku' => 'SUCRE-1KG', 'unit' => 'sachets',    'description' => 'Sucre en poudre 1kg',                              'image_url' => '/assets/products/sucre-1kg.svg'],
    ['category_id' => $papierId,   'name' => 'Papier A4 (ramette 500)',  'reference' => 'PAP-A4-500','sku' => 'PAP-A4-500','unit' => 'ramettes',   'description' => 'Papier A4 blanc 80g, ramette de 500 feuilles',     'image_url' => '/assets/products/pap-a4.svg'],
    ['category_id' => $papierId,   'name' => 'Papier toilette',          'reference' => 'PAP-TOIL',  'sku' => 'PAP-TOIL',  'unit' => 'rouleaux',  'description' => 'Papier toilette double épaisseur',                 'image_url' => '/assets/products/pap-toil.svg'],
    ['category_id' => $papierId,   'name' => 'Essuie-tout',              'reference' => 'PAP-ESS',   'sku' => 'PAP-ESS',   'unit' => 'rouleaux',  'description' => 'Essuie-tout ultra absorbant',                      'image_url' => '/assets/products/pap-ess.svg'],
    ['category_id' => $hygieneId,  'name' => 'Savon liquide 1L',         'reference' => 'HYG-SAV1L', 'sku' => 'HYG-SAV1L', 'unit' => 'bouteilles', 'description' => 'Savon liquide mains, 1L recharge',                 'image_url' => '/assets/products/hyg-sav1l.svg'],
    ['category_id' => $hygieneId,  'name' => 'Gel hydroalcoolique',      'reference' => 'HYG-GELHA', 'sku' => 'HYG-GELHA', 'unit' => 'flacons',   'description' => 'Gel hydroalcoolique 500ml',                        'image_url' => '/assets/products/hyg-gelha.svg'],
    ['category_id' => $nettoyageId,'name' => 'Détergent multi-usages',   'reference' => 'NET-MULTI', 'sku' => 'NET-MULTI', 'unit' => 'bouteilles', 'description' => 'Détergent multi-usages 2L',                        'image_url' => '/assets/products/net-multi.svg'],
    ['category_id' => $nettoyageId,'name' => 'Sacs poubelle 120L',        'reference' => 'NET-SAC120','sku' => 'NET-SAC120','unit' => 'rouleaux',  'description' => 'Sacs poubelle 120L résistants',                    'image_url' => '/assets/products/net-sac120.svg'],
];

$prodStmt = $pdo->prepare('INSERT INTO products (category_id, name, reference, sku, description, unit, image_url, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
$productIds = [];
foreach ($products as $p) {
    $prodStmt->execute([$p['category_id'], $p['name'], $p['reference'], $p['sku'], $p['description'], $p['unit'], $p['image_url']]);
    $productIds[] = (int)$pdo->lastInsertId();
}
echo "✓ Created " . count($products) . " products (incluant Lait/Café/Thé/Sucre/Papier A4)\n";

[$eau1500, $eau500, $lait1L, $cafe500g, $theVert, $sucre1kg, $papA4, $papToil, $essuieTout, $savon1L, $gelHA, $detergent, $sacs120] = $productIds;

$stockConfigs = [
    [
        'site_id' => $oracleRabatId,
        'min' => 20,
        'max' => 200,
        'quantities' => [
            $eau1500 => 180, $eau500 => 90, $lait1L => 60, $cafe500g => 30,
            $theVert => 25,  $sucre1kg => 40, $papA4 => 15, $papToil => 60,
            $essuieTout => 18, $savon1L => 40, $gelHA => 25, $detergent => 10, $sacs120 => 8,
        ],
    ],
    [
        'site_id' => $oracleCasaId,
        'min' => 15,
        'max' => 150,
        'quantities' => [
            $eau1500 => 120, $eau500 => 70, $lait1L => 50, $cafe500g => 22,
            $theVert => 18,  $sucre1kg => 30, $papA4 => 10, $papToil => 45,
            $essuieTout => 8, $savon1L => 30, $gelHA => 12, $detergent => 6, $sacs120 => 3,
        ],
    ],
    [
        'site_id' => $iamRabatId,
        'min' => 30,
        'max' => 250,
        'quantities' => [
            $eau1500 => 220, $eau500 => 140, $lait1L => 100, $cafe500g => 45,
            $theVert => 35,  $sucre1kg => 55, $papA4 => 25, $papToil => 80,
            $essuieTout => 20, $savon1L => 60, $gelHA => 40, $detergent => 15, $sacs120 => 10,
        ],
    ],
    [
        'site_id' => $iamAgadirId,
        'min' => 10,
        'max' => 100,
        'quantities' => [
            $eau1500 => 95, $eau500 => 50, $lait1L => 30, $cafe500g => 15,
            $theVert => 12, $sucre1kg => 20, $papA4 => 5, $papToil => 35,
            $essuieTout => 5, $savon1L => 20, $gelHA => 8, $detergent => 3,
            $sacs120 => 0,
        ],
    ],
    [
        'site_id' => $awbSiegeId,
        'min' => 40,
        'max' => 300,
        'quantities' => [
            $eau1500 => 280, $eau500 => 180, $lait1L => 120, $cafe500g => 60,
            $theVert => 50,  $sucre1kg => 70, $papA4 => 35, $papToil => 110,
            $essuieTout => 25, $savon1L => 75, $gelHA => 55, $detergent => 22, $sacs120 => 14,
        ],
    ],
    [
        'site_id' => $awbTechnoId,
        'min' => 25,
        'max' => 220,
        'quantities' => [
            $eau1500 => 210, $eau500 => 130, $lait1L => 0, $cafe500g => 3,
            $theVert => 5,   $sucre1kg => 8, $papA4 => 2, $papToil => 70,
            $essuieTout => 15, $savon1L => 50, $gelHA => 30, $detergent => 12, $sacs120 => 9,
        ],
    ],
];

$stockStmt = $pdo->prepare('INSERT INTO stocks (site_id, product_id, quantity, minimum_quantity, maximum_quantity) VALUES (?, ?, ?, ?, ?)');
$totalStocks = 0;
$lowStockCount = 0;
$outCount = 0;
foreach ($stockConfigs as $sc) {
    foreach ($sc['quantities'] as $pid => $qty) {
        $min = $sc['min'];
        $max = $sc['max'];
        $stockStmt->execute([$sc['site_id'], $pid, $qty, $min, $max]);
        $totalStocks++;
        if ($qty === 0) {
            $outCount++;
        } elseif ($qty <= $min) {
            $lowStockCount++;
        }
    }
}
echo "✓ Created {$totalStocks} stock records across " . count($stockConfigs) . " sites\n";
echo "  ({$outCount} en rupture (OUT_OF_STOCK), {$lowStockCount} stock faible (LOW_STOCK) détectés au démarrage)\n";

echo "\n=== Seeding completed successfully ===\n";
echo "\nDefault credentials (password '{$password}'):\n";
echo "  admin@example.com         / ADMIN - global\n";
echo "  manager@example.com       / MANAGER - Oracle Rabat HQ\n";
echo "  stock.manager@example.com / STOCK_MANAGER - Oracle Rabat HQ\n";
echo "  sara.elamrani@example.com / STOCK_MANAGER - IAM Centre Ville\n";
echo "  karim.tazi@example.com    / MANAGER - IAM Agadir\n";
echo "  salma.bennani@awb.ma      / MANAGER - Attijariwafa Bank Siège\n";
echo "  mourad.idrissi@awb.ma     / STOCK_MANAGER - AWB Centre Technologique\n";
