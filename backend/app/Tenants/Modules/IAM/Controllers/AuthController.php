<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenants\Modules\IAM\Services\AuthService;
use Exception;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $result = $this->authService->attemptLogin($request->only('email', 'password'));
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        }
    }
    
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function setupMfa(Request $request)
    {
        // Placeholder for TOTP generation logic
        return response()->json(['success' => true, 'message' => 'MFA setup initialized']);
    }

    public function verifyMfa(Request $request)
    {
        // Placeholder for TOTP verification logic
        return response()->json(['success' => true, 'message' => 'MFA verified successfully']);
    }
}
