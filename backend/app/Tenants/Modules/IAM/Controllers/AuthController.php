<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\IAM\Services\AuthService;
use App\Tenants\Modules\IAM\Services\TotpService;
use Exception;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected TotpService $totp,
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        try {
            $result = $this->authService->attemptLogin($request->only('email', 'password'));
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 401);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    /**
     * Issue a fresh TOTP secret + provisioning URI. The secret is stored on
     * the user but mfa_enabled stays false until verifyMfa() succeeds, so a
     * half-finished setup never locks anyone out.
     */
    public function setupMfa(Request $request)
    {
        $user   = $request->user();
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'mfa_secret'  => $secret,
            'mfa_enabled' => false,
        ])->save();

        $issuer = config('app.name', 'ERP');
        if (function_exists('tenant') && tenant('handle')) {
            $issuer .= ' (' . tenant('handle') . ')';
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'secret'           => $secret,
                'provisioning_uri' => $this->totp->provisioningUri($secret, $user->email, $issuer),
            ],
        ]);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();
        if (! $user->mfa_secret) {
            return response()->json([
                'success' => false,
                'message' => 'MFA has not been initialized. Call /mfa/setup first.',
            ], 422);
        }

        if (! $this->totp->verify($user->mfa_secret, $request->input('code'))) {
            return response()->json(['success' => false, 'message' => 'Invalid code.'], 422);
        }

        if (! $user->mfa_enabled) {
            $user->forceFill(['mfa_enabled' => true])->save();
        }

        return response()->json(['success' => true, 'message' => 'MFA verified successfully.']);
    }
}
