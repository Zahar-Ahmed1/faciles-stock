<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->safeLoad();

        self::$config = [
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? 'facility_stock',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
            ],
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET'] ?? '',
                'expire' => (int)($_ENV['JWT_EXPIRE'] ?? 3600),
                'algorithm' => 'HS256',
            ],
            'app' => [
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'timezone' => 'UTC',
            ],
            'upload' => [
                'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880),
                'allowed_types' => array_map('trim', explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'jpg,jpeg,png,webp')),
                'products_dir' => self::productsDir(),
            ],
            'cors' => [
                'allowed_origins' => $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*',
                'allowed_methods' => $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,DELETE,OPTIONS',
                'allowed_headers' => $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization',
            ],
        ];

        self::$loaded = true;
    }

    private static function productsDir(): string
    {
        $backendRoot = dirname(__DIR__, 2);
        if (basename($backendRoot) === 'facilty-api') {
            return dirname($backendRoot) . '/uploads/products';
        }

        return $backendRoot . '/public/uploads/products';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}
