<?php

namespace App\Tenants\Modules\IAM\Services;

/**
 * RFC 6238 Time-based One-Time Password (TOTP) generator/verifier.
 *
 * Implemented in-house rather than pulling pragmarx/google2fa so the
 * dependency surface stays small. Compatible with Google Authenticator,
 * 1Password, Authy, etc. (SHA-1, 6 digits, 30-second window).
 */
class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD          = 30;
    private const DIGITS          = 6;
    private const SECRET_BYTES    = 20; // 160-bit, recommended by RFC 4226 §4.

    /**
     * Generate a fresh base32-encoded secret suitable for an authenticator app.
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    /**
     * Build the otpauth:// URI that authenticator apps consume via QR code.
     */
    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Verify a user-entered 6-digit code against the secret. Accepts the
     * adjacent ±1 time window to tolerate clock skew (standard practice).
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generateCode($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * HOTP code generation per RFC 4226 §5.3 (HMAC-SHA1 + dynamic truncation).
     */
    private function generateCode(string $secret, int $counter): string
    {
        $binarySecret  = $this->base32Decode($secret);
        $counterBytes  = pack('N*', 0, $counter); // 64-bit big-endian counter
        $hash          = hash_hmac('sha1', $counterBytes, $binarySecret, true);

        $offset        = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncatedHash = ((ord($hash[$offset]) & 0x7F) << 24)
                       | ((ord($hash[$offset + 1]) & 0xFF) << 16)
                       | ((ord($hash[$offset + 2]) & 0xFF) << 8)
                       | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncatedHash % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $binary = '';
        foreach (str_split($bytes) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);
        $out    = '';
        foreach ($chunks as $chunk) {
            $out .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        // No padding — authenticator apps don't require it for these secrets.
        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        $binary = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $secret[$i]);
            if ($pos === false) {
                continue; // skip unknown characters (whitespace, dashes from users)
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out    = '';
        $bytes  = str_split($binary, 8);
        foreach ($bytes as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
