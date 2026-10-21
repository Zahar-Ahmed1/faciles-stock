<?php

declare(strict_types=1);

namespace App\Routes;

use App\Utils\Response;

class Router
{
    private array $routes = [];
    private string $basePath;
    private string $method;
    private string $path;

    public function __construct(string $method, string $path, string $basePath = '')
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->basePath = $basePath;
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->addRoute('PATCH', $pattern, $handler);
    }

    public function group(callable $callback): void
    {
        $callback($this);
    }

    public function run(): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $this->method) {
                continue;
            }

            $pattern = $this->convertPattern($route['pattern']);
            if (preg_match($pattern, $this->path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                ($route['handler'])($params, $this->getRequestData());
                return;
            }
        }

        Response::notFound();
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    private function convertPattern(string $pattern): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    private function getRequestData(): array
    {
        $data = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                $json = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $data = $json;
                }
            }
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            $data = array_merge($_POST, $_FILES);
        } else {
            $data = array_merge($_POST, $_GET);

            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                $json = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $data = array_merge($data, $json);
                }
            }
        }

        return $data;
    }
}
