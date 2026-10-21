<?php

declare(strict_types=1);

$pathFile = __DIR__ . '/backend-path.php';
$backend = is_file($pathFile) ? require $pathFile : '';
if (!is_string($backend) || $backend === '') {
    $backend = dirname(__DIR__, 2) . '/facilty-api';
}

$backend = rtrim($backend, '/');
$entry = $backend . '/api/index.php';

if (!is_file($entry)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => 'Backend introuvable. Le dossier facilty-api doit être dans public_html.',
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require $entry;
