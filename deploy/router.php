<?php

declare(strict_types=1);

/**
 * Serveur local qui imite Hostinger : fichiers Angular + /api vers le PHP.
 * Usage, depuis la racine du projet :
 *   php -S localhost:8899 -t frontend/dist/facility-stock-frontend deploy/router.php
 */

$root = dirname(__DIR__);
$public = $root . '/frontend/dist/facility-stock-frontend';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($uri, '/api')) {
    require $root . '/backend/api/index.php';
    return true;
}

$publicReal = realpath($public);
$target = $publicReal ? realpath($public . $uri) : false;
if ($uri !== '/' && $publicReal && $target && str_starts_with($target, $publicReal) && is_file($target)) {
    return false;
}

$index = $public . '/index.html';
if (!is_file($index)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Build manquant. Lancez : cd frontend && npm run build\n";
    return true;
}

header('Content-Type: text/html; charset=utf-8');
readfile($index);
return true;
