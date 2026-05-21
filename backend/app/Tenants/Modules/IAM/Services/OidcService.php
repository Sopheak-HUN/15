<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\User;
use App\Tenants\Modules\IAM\Models\SsoProvider;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Minimal OpenID Connect Authorization Code flow. Discovery metadata is
 * cached for an hour; the full SAML protocol lives behind a separate service
 * once that backend lands.
 */
class OidcService
{
    private const DISCOVERY_CACHE_TTL = 3600;

    public function authorizationUrl(SsoProvider $provider, string $state): string
    {
        $meta = $this->discoveryDocument($provider);
        $query = http_build_query([
            'response_type' => 'code',
            'client_id'     => $provider->client_id,
            'redirect_uri'  => $provider->redirect_uri,
            'scope'         => implode(' ', $provider->scopes ?? ['openid', 'profile', 'email']),
            'state'         => $state,
        ]);
        return $meta['authorization_endpoint'] . '?' . $query;
    }

    /**
     * Exchange an authorization code for an ID token and resolve (or
     * provision) the local tenant user.
     */
    public function handleCallback(SsoProvider $provider, string $code): User
    {
        $meta = $this->discoveryDocument($provider);

        $response = Http::asForm()->post($meta['token_endpoint'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $provider->redirect_uri,
            'client_id'     => $provider->client_id,
            'client_secret' => $provider->client_secret,
        ]);

        if (! $response->ok()) {
            throw new DomainException('SSO token exchange failed: ' . $response->body());
        }

        $idToken = $response->json('id_token');
        if (! $idToken) {
            throw new DomainException('SSO response missing id_token.');
        }

        $claims = $this->decodeIdTokenClaims($idToken);

        $sub   = $claims['sub']   ?? null;
        $email = $claims['email'] ?? null;
        $name  = $claims['name']  ?? ($claims['preferred_username'] ?? $email);

        if (! $sub || ! $email) {
            throw new DomainException('SSO id_token missing required claims (sub, email).');
        }

        // 1) Existing SSO link wins.
        $user = User::where('sso_provider_id', $provider->id)
            ->where('sso_subject', $sub)
            ->first();

        // 2) Otherwise, link to an existing local account by email.
        if (! $user) {
            $user = User::where('email', $email)->first();
        }

        // 3) Otherwise, just-in-time provision.
        if (! $user) {
            $user = new User();
            $user->forceFill([
                'name'     => $name,
                'email'    => $email,
                'handle'   => Str::slug(Str::beforeLast($email, '@')) . '-' . Str::random(4),
                // SSO users have no password — set a strong random one to satisfy NOT NULL.
                'password' => bcrypt(Str::random(48)),
            ]);
        }

        $user->forceFill([
            'sso_provider_id' => $provider->id,
            'sso_subject'     => $sub,
        ])->save();

        return $user;
    }

    /**
     * Fetch and cache the OIDC discovery document so we don't hammer the IdP.
     */
    private function discoveryDocument(SsoProvider $provider): array
    {
        if (! $provider->discovery_url) {
            throw new DomainException('Provider has no discovery_url configured.');
        }

        return Cache::remember(
            'sso:discovery:' . $provider->id,
            self::DISCOVERY_CACHE_TTL,
            function () use ($provider) {
                $res = Http::get($provider->discovery_url);
                if (! $res->ok()) {
                    throw new DomainException('Failed to load OIDC discovery document.');
                }
                return $res->json();
            }
        );
    }

    /**
     * Decode the JWT payload WITHOUT verifying the signature. This is the
     * minimum viable shape for scaffolding — production must validate the
     * signature against the IdP's JWKS. Tracked as a follow-up.
     */
    private function decodeIdTokenClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new DomainException('Malformed id_token.');
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            throw new DomainException('id_token payload is not valid base64.');
        }
        return json_decode($payload, true) ?? [];
    }
}
