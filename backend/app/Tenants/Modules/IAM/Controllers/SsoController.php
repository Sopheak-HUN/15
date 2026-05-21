<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\IAM\Models\SsoProvider;
use App\Tenants\Modules\IAM\Services\OidcService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function __construct(protected OidcService $oidc) {}

    public function index()
    {
        $providers = SsoProvider::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'protocol']);
        return response()->json(['data' => $providers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:120',
            'protocol'       => 'required|in:oidc,saml',
            'issuer'         => 'nullable|string|max:255',
            'client_id'      => 'nullable|string|max:255',
            'client_secret'  => 'nullable|string',
            'discovery_url'  => 'nullable|url',
            'redirect_uri'   => 'nullable|url',
            'scopes'         => 'nullable|array',
            'metadata'       => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        $provider = SsoProvider::create($data);
        return response()->json(['success' => true, 'data' => $provider]);
    }

    public function update(Request $request, SsoProvider $provider)
    {
        $data = $request->validate([
            'name'           => 'sometimes|string|max:120',
            'protocol'       => 'sometimes|in:oidc,saml',
            'issuer'         => 'nullable|string|max:255',
            'client_id'      => 'nullable|string|max:255',
            'client_secret'  => 'nullable|string',
            'discovery_url'  => 'nullable|url',
            'redirect_uri'   => 'nullable|url',
            'scopes'         => 'nullable|array',
            'metadata'       => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        $provider->update($data);
        return response()->json(['success' => true, 'data' => $provider]);
    }

    public function destroy(SsoProvider $provider)
    {
        $provider->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Kick off an OIDC login: cache a one-shot state value to defeat CSRF,
     * then redirect to the IdP authorization endpoint.
     */
    public function redirect(SsoProvider $provider)
    {
        if ($provider->protocol !== 'oidc') {
            return response()->json(['success' => false, 'message' => 'Only OIDC is currently wired.'], 422);
        }

        $state = Str::random(40);
        cache()->put("sso:state:{$state}", $provider->id, now()->addMinutes(10));

        return response()->json([
            'success' => true,
            'data'    => ['authorization_url' => $this->oidc->authorizationUrl($provider, $state)],
        ]);
    }

    public function callback(Request $request)
    {
        $request->validate([
            'code'  => 'required|string',
            'state' => 'required|string',
        ]);

        $providerId = cache()->pull("sso:state:{$request->state}");
        if (! $providerId) {
            return response()->json(['success' => false, 'message' => 'State is invalid or expired.'], 422);
        }

        $provider = SsoProvider::findOrFail($providerId);
        if (! $provider->is_active) {
            return response()->json(['success' => false, 'message' => 'Provider is disabled.'], 422);
        }

        try {
            $user = $this->oidc->handleCallback($provider, $request->input('code'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $token = $user->createToken('SSO Token')->accessToken;

        return response()->json([
            'success' => true,
            'data'    => ['user' => $user, 'token' => $token],
        ]);
    }
}
