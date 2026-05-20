<?php

namespace App\Tenants\Modules\IAM\Services;

use Illuminate\Support\Facades\Auth;
use Exception;

class AuthService
{
    public function attemptLogin(array $credentials)
    {
        if (!Auth::attempt($credentials)) {
            throw new Exception("Invalid credentials");
        }

        $user = Auth::user();
        $token = $user->createToken('API Token')->accessToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }
}
