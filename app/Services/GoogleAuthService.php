<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type GoogleUserData array{
 *     google_id: string,
 *     email: string,
 *     avatar_url: string|null,
 *     email_verified: bool
 * }
 */
class GoogleAuthService
{
    private GoogleClient $client;

    public function __construct()
    {
        $this->client = new GoogleClient;

        // Set all client IDs for token verification
        $clientIds = array_filter([
            config('services.google.client_id'),
            config('services.google.client_id_ios'),
            config('services.google.client_id_android'),
        ]);

        if (! empty($clientIds)) {
            // The first client ID is the primary one
            $this->client->setClientId($clientIds[0]);
        }
    }

    /**
     * Verify a Google ID token and extract user data.
     *
     * @return GoogleUserData|null
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            // Get all valid client IDs
            $validClientIds = array_filter([
                config('services.google.client_id'),
                config('services.google.client_id_ios'),
                config('services.google.client_id_android'),
            ]);

            // Verify the token
            $payload = $this->client->verifyIdToken($idToken);

            if (! $payload) {
                Log::warning('Google ID token verification failed: payload is null');

                return null;
            }

            // Verify the audience (client ID) matches one of our configured client IDs
            $tokenAudience = $payload['aud'] ?? null;
            if (! in_array($tokenAudience, $validClientIds, true)) {
                Log::warning('Google ID token verification failed: invalid audience', [
                    'expected' => $validClientIds,
                    'received' => $tokenAudience,
                ]);

                return null;
            }

            // Extract user data from payload
            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'avatar_url' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Google ID token verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
