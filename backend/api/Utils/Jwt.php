<?php

declare(strict_types=1);

namespace App\Utils;

use App\Config\Config;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use DomainException;
use UnexpectedValueException;

class Jwt
{
    public static function generate(array $payload): string
    {
        Config::load();

        $secret = Config::get('jwt.secret');
        $expire = Config::get('jwt.expire');
        $algorithm = Config::get('jwt.algorithm');

        if (empty($secret)) {
            throw new \RuntimeException('JWT secret is not configured');
        }

        $now = time();

        $tokenPayload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expire,
        ], $payload);

        return FirebaseJWT::encode($tokenPayload, $secret, $algorithm);
    }

    public static function decode(string $token): ?object
    {
        Config::load();

        $secret = Config::get('jwt.secret');
        $algorithm = Config::get('jwt.algorithm');

        if (empty($secret)) {
            return null;
        }

        try {
            return FirebaseJWT::decode($token, new Key($secret, $algorithm));
        } catch (
            ExpiredException
            | SignatureInvalidException
            | BeforeValidException
            | DomainException
            | UnexpectedValueException
            | \InvalidArgumentException $e
        ) {
            return null;
        }
    }

    public static function extractFromHeader(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return trim(substr($authHeader, 7));
    }
}
