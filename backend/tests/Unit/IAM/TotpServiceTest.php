<?php

use App\Tenants\Modules\IAM\Services\TotpService;

/*
|--------------------------------------------------------------------------
| TotpService — RFC 6238 conformance
|--------------------------------------------------------------------------
| Pure unit tests (no DB). Validates:
|   - secrets are base32 of the expected length
|   - provisioning URIs round-trip the secret + label
|   - generated codes verify against themselves
|   - skewed codes verify inside the ±1 window
|   - bad/short/non-numeric codes are rejected
*/

beforeEach(function () {
    $this->service = new TotpService();
});

it('generates a 32-character base32 secret', function () {
    $secret = $this->service->generateSecret();
    expect($secret)
        ->toBeString()
        ->toHaveLength(32)                       // 20 bytes → 32 base32 chars
        ->toMatch('/^[A-Z2-7]+$/');             // RFC 4648 alphabet, no padding
});

it('builds an otpauth URI with the issuer + account label', function () {
    $secret = $this->service->generateSecret();
    $uri    = $this->service->provisioningUri($secret, 'user@example.com', 'ERP Test');

    expect($uri)
        ->toStartWith('otpauth://totp/')
        ->toContain(rawurlencode('ERP Test:user@example.com'))
        ->toContain('secret=' . $secret)
        ->toContain('issuer=ERP+Test')
        ->toContain('digits=6')
        ->toContain('period=30');
});

it('verifies a freshly generated code', function () {
    $secret = $this->service->generateSecret();

    // Use reflection to generate the "current" code the same way verify() does.
    $reflection = new ReflectionClass($this->service);
    $generate   = $reflection->getMethod('generateCode');
    $generate->setAccessible(true);

    $counter = intdiv(time(), 30);
    $code    = $generate->invoke($this->service, $secret, $counter);

    expect($this->service->verify($secret, $code))->toBeTrue();
});

it('tolerates the adjacent ±1 time-step window', function () {
    $secret = $this->service->generateSecret();

    $reflection = new ReflectionClass($this->service);
    $generate   = $reflection->getMethod('generateCode');
    $generate->setAccessible(true);

    $counter = intdiv(time(), 30);
    $prev    = $generate->invoke($this->service, $secret, $counter - 1);
    $next    = $generate->invoke($this->service, $secret, $counter + 1);

    expect($this->service->verify($secret, $prev))->toBeTrue();
    expect($this->service->verify($secret, $next))->toBeTrue();
});

it('rejects malformed codes', function () {
    $secret = $this->service->generateSecret();

    expect($this->service->verify($secret, ''))->toBeFalse();
    expect($this->service->verify($secret, 'abcdef'))->toBeFalse();
    expect($this->service->verify($secret, '12345'))->toBeFalse();
    expect($this->service->verify($secret, '1234567'))->toBeFalse();
    expect($this->service->verify($secret, '000000'))->toBeFalse(); // overwhelmingly unlikely to match a random secret
});

it('rejects a code from a different secret', function () {
    $secretA = $this->service->generateSecret();
    $secretB = $this->service->generateSecret();

    $reflection = new ReflectionClass($this->service);
    $generate   = $reflection->getMethod('generateCode');
    $generate->setAccessible(true);

    $codeForA = $generate->invoke($this->service, $secretA, intdiv(time(), 30));
    expect($this->service->verify($secretB, $codeForA))->toBeFalse();
});
