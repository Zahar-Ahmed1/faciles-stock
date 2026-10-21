<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Utils\Response;
use App\Utils\Validator;

class AuthController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService()
    ) {
    }

    public function login(array $params, array $data): void
    {
        $validator = Validator::make($data, [
            'email' => 'required|string|email',
            'password' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $valid = $validator->validated();

        $result = $this->auth->login(
            trim((string)$valid['email']),
            (string)$valid['password']
        );

        Response::success($result);
    }
}
