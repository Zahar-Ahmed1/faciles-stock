<?php

declare(strict_types=1);

namespace App;

use App\Config\Config;
use App\Utils\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';

set_exception_handler(function (\Throwable $e): void {
    $isDev = Config::get('app.env') === 'development';

    $code = $e->getCode() ?: 500;
    if (!is_int($code) || $code < 100 || $code > 599) {
        $code = 500;
    }

    if ($isDev) {
        Response::serverError(sprintf(
            '%s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    Response::serverError('Internal server error');
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        if (!headers_sent()) {
            $isDev = Config::get('app.env') === 'development';
            if ($isDev) {
                Response::serverError(sprintf(
                    'Fatal error: %s in %s:%d',
                    $error['message'],
                    $error['file'],
                    $error['line']
                ));
            }
            Response::serverError('Internal server error');
        }
    }
});

Config::load();

date_default_timezone_set(Config::get('app.timezone', 'UTC'));

handleCors();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

$basePath = '';
if (str_ends_with($scriptName, '/api/index.php') || str_ends_with($scriptName, 'api/index.php')) {
    $basePath = '/api';
}

if ($uri === false) {
    Response::notFound();
}

$path = $uri;
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $path = substr($uri, strlen($basePath));
} elseif ($basePath === '' && str_starts_with($uri, '/api')) {
    $path = substr($uri, strlen('/api'));
    $basePath = '/api';
}

$path = '/' . ltrim((string)$path, '/');
if (str_starts_with($path, '/index.php')) {
    $path = substr($path, strlen('/index.php'));
    $path = ($path === '' || $path === false) ? '/' : '/' . ltrim($path, '/');
}

if ($path === '/') {
    Response::success([
        'name' => 'Facility Stock API',
        'version' => '1.0.0',
        'status' => 'running',
        'endpoints' => [
            'health' => ($basePath ?: '') . '/health',
            'auth_login' => ($basePath ?: '') . '/auth/login [POST]',
            'auth_me' => ($basePath ?: '') . '/auth/me [GET]',
        ],
        'authentication' => 'POST /auth/login (email + password, JWT returned)',
        'docs' => 'See README.md for full REST API documentation',
    ]);
}

if (str_starts_with($path, '/uploads/')) {
    serveUpload($path);
}

require_once __DIR__ . '/Routes/api.php';

function serveUpload(string $path): void
{
    $relative = ltrim(substr($path, strlen('/uploads/')), '/');
    $relative = str_replace(['..', '\\'], '', $relative);
    $backendRoot = dirname(__DIR__, 2);
    $candidates = [
        $backendRoot . '/public/uploads/' . $relative,
        dirname($backendRoot) . '/uploads/' . $relative,
    ];
    $file = null;
    foreach ($candidates as $candidate) {
        if ($relative !== '' && is_file($candidate)) {
            $file = $candidate;
            break;
        }
    }
    if ($file === null) {
        Response::notFound('Image introuvable');
    }
    $mime = mime_content_type($file) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

function handleCors(): void
{
    $allowedOrigins = Config::get('cors.allowed_origins', '*');
    $allowedMethods = Config::get('cors.allowed_methods', 'GET,POST,PUT,DELETE,OPTIONS');
    $allowedHeaders = Config::get('cors.allowed_headers', 'Content-Type,Authorization');

    if ($allowedOrigins === '*') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $origins = array_map('trim', explode(',', $allowedOrigins));
        if (in_array($origin, $origins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
    }

    header("Access-Control-Allow-Methods: {$allowedMethods}");
    header("Access-Control-Allow-Headers: {$allowedHeaders}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
