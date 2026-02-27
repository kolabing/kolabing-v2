<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type AppleUserData array{
 *     apple_id: string,
 *     email: string|null,
 * }
 */
class AppleAuthService
{
    private const APPLE_JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const APPLE_ISSUER = 'https://appleid.apple.com';

    private const JWKS_CACHE_TTL = 3600;

    /**
     * Verify an Apple identity token and extract user data.
     *
     * @return AppleUserData|null
     */
    public function verifyIdentityToken(string $identityToken): ?array
    {
        try {
            $keys = $this->fetchApplePublicKeys();

            if (empty($keys)) {
                Log::warning('Apple Sign In: failed to fetch public keys');

                return null;
            }

            $jwks = JWK::parseKeySet(['keys' => $keys]);

            $payload = (array) JWT::decode($identityToken, $jwks);

            // Validate issuer
            if (($payload['iss'] ?? null) !== self::APPLE_ISSUER) {
                Log::warning('Apple Sign In: invalid issuer', ['iss' => $payload['iss'] ?? null]);

                return null;
            }

            // Validate audience (client bundle ID)
            $audience = $payload['aud'] ?? null;
            $validAudiences = array_filter([
                config('services.apple.client_id'),
            ]);

            if (! empty($validAudiences) && ! in_array($audience, $validAudiences, true)) {
                Log::warning('Apple Sign In: invalid audience', [
                    'expected' => $validAudiences,
                    'received' => $audience,
                ]);

                return null;
            }

            $sub = $payload['sub'] ?? null;

            if (empty($sub)) {
                Log::warning('Apple Sign In: missing sub claim');

                return null;
            }

            return [
                'apple_id' => (string) $sub,
                'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            ];
        } catch (\Exception $e) {
            Log::error('Apple Sign In: token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch Apple's public JWKS, cached for one hour.
     *
     * @return array<int, mixed>
     */
    private function fetchApplePublicKeys(): array
    {
        return Cache::remember('apple_jwks', self::JWKS_CACHE_TTL, function (): array {
            $response = Http::get(self::APPLE_JWKS_URL);

            if (! $response->successful()) {
                return [];
            }

            return $response->json('keys', []);
        });
    }
}
