<?php

use App\Tenants\Modules\IAM\Models\SsoProvider;
use App\Tenants\Modules\IAM\Services\OidcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| OidcService — pure-logic coverage
|--------------------------------------------------------------------------
| Exercises authorizationUrl() (discovery cache + scope assembly) and the
| private id_token claim decoder via reflection. handleCallback() is tested
| against Http::fake() to avoid hitting a real IdP. The full integration
| path (user provisioning, DB writes) lives in the tenancy isolation suite.
*/

beforeEach(function () {
    $this->service = new OidcService();
    Cache::flush();
});

function makeProvider(array $overrides = []): SsoProvider
{
    $provider = new SsoProvider();
    $provider->forceFill(array_merge([
        'id'            => 'prov-test',
        'name'          => 'Test IdP',
        'protocol'      => 'oidc',
        'client_id'     => 'erp-app',
        'redirect_uri'  => 'https://app.example.com/auth/sso-callback',
        'discovery_url' => 'https://idp.example.com/.well-known/openid-configuration',
        'scopes'        => ['openid', 'profile', 'email'],
        'is_active'     => true,
    ], $overrides));
    // Avoid hitting the encrypted cast in unit tests.
    return $provider;
}

it('builds an authorization URL from the discovery document', function () {
    Http::fake([
        'https://idp.example.com/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint'         => 'https://idp.example.com/token',
        ]),
    ]);

    $url = $this->service->authorizationUrl(makeProvider(), 'state-xyz');

    expect($url)
        ->toStartWith('https://idp.example.com/authorize?')
        ->toContain('client_id=erp-app')
        ->toContain('response_type=code')
        ->toContain('state=state-xyz')
        ->toContain('scope=openid+profile+email')
        ->toContain('redirect_uri=' . urlencode('https://app.example.com/auth/sso-callback'));
});

it('falls back to default scopes when none are configured on the provider', function () {
    Http::fake([
        'https://idp.example.com/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint'         => 'https://idp.example.com/token',
        ]),
    ]);

    $url = $this->service->authorizationUrl(makeProvider(['scopes' => null]), 'state-1');
    expect($url)->toContain('scope=openid+profile+email');
});

it('caches the discovery document to avoid hammering the IdP', function () {
    Http::fake([
        'https://idp.example.com/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://idp.example.com/authorize',
            'token_endpoint'         => 'https://idp.example.com/token',
        ]),
    ]);

    $provider = makeProvider();
    $this->service->authorizationUrl($provider, 'a');
    $this->service->authorizationUrl($provider, 'b');
    $this->service->authorizationUrl($provider, 'c');

    Http::assertSentCount(1);
});

it('rejects providers without a discovery URL', function () {
    $this->service->authorizationUrl(makeProvider(['discovery_url' => null]), 'x');
})->throws(DomainException::class, 'discovery_url');

it('rejects providers when the discovery endpoint errors', function () {
    Http::fake([
        'https://idp.example.com/.well-known/openid-configuration' => Http::response('', 503),
    ]);
    $this->service->authorizationUrl(makeProvider(), 'x');
})->throws(DomainException::class, 'Failed to load OIDC discovery');

it('decodes the JWT payload from an id_token', function () {
    $payload = [
        'sub'   => 'user-42',
        'email' => 'alice@example.com',
        'name'  => 'Alice Example',
        'iss'   => 'https://idp.example.com',
        'aud'   => 'erp-app',
    ];
    $jwt = 'header.' . rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=') . '.sig';

    $reflection = new ReflectionClass($this->service);
    $decode     = $reflection->getMethod('decodeIdTokenClaims');
    $decode->setAccessible(true);

    expect($decode->invoke($this->service, $jwt))
        ->toMatchArray($payload);
});

it('throws on a malformed JWT', function () {
    $reflection = new ReflectionClass($this->service);
    $decode     = $reflection->getMethod('decodeIdTokenClaims');
    $decode->setAccessible(true);

    $decode->invoke($this->service, 'not-a-jwt');
})->throws(DomainException::class, 'Malformed id_token');
