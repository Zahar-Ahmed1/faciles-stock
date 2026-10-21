<?php

declare(strict_types=1);

namespace App\Utils;

class Response
{
    public static function send(mixed $data = null, int $statusCode = 200): never
    {
        self::setHeaders();
        http_response_code($statusCode);

        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
        ];

        if ($response['success']) {
            $response['data'] = $data;
        } else {
            $response['error'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, int $statusCode = 200): never
    {
        self::send($data, $statusCode);
    }

    public static function error(
        string $code,
        string $message,
        int $statusCode = 400,
        ?array $details = null
    ): never {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        self::send($error, $statusCode);
    }

    public static function created(mixed $data = null): never
    {
        self::success($data, 201);
    }

    public static function noContent(): never
    {
        self::setHeaders();
        http_response_code(204);
        exit;
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error('NOT_FOUND', $message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error('UNAUTHORIZED', $message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error('FORBIDDEN', $message, 403);
    }

    public static function badRequest(string $message = 'Bad request', ?array $details = null): never
    {
        self::error('BAD_REQUEST', $message, 400, $details);
    }

    public static function validationError(array $errors): never
    {
        self::error('VALIDATION_ERROR', 'Validation failed', 422, $errors);
    }

    public static function conflict(string $message = 'Conflict'): never
    {
        self::error('CONFLICT', $message, 409);
    }

    public static function serverError(string $message = 'Internal server error'): never
    {
        self::error('SERVER_ERROR', $message, 500);
    }

    private static function setHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }
}
