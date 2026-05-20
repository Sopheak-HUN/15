<?php

namespace App\Models;

use Laravel\Passport\Client as BaseClient;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PassportClient extends BaseClient
{
    /**
     * Interact with the client's grant types.
     */
    protected function grantTypes(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value) && ! empty($value)) {
                    return json_decode($value, true) ?: [];
                }

                return array_keys(array_filter([
                    'authorization_code' => ! empty($this->redirect_uris),
                    'client_credentials' => $this->confidential() && $this->firstParty(),
                    'implicit' => ! empty($this->redirect_uris),
                    'password' => $this->password_client,
                    'personal_access' => $this->personal_access_client && $this->confidential(),
                    'refresh_token' => true,
                    'urn:ietf:params:oauth:grant-type:device_code' => true,
                ]));
            }
        );
    }

    /**
     * Interact with the client's redirect URIs.
     */
    protected function redirectUris(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes): array {
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value) && ! empty($value)) {
                    return json_decode($value, true) ?: [];
                }
                if (! empty($attributes['redirect'])) {
                    return explode(',', $attributes['redirect']);
                }

                return [];
            }
        );
    }
}
