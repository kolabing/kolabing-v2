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
            $validClientIds = array_values(array_filter([
                config('services.google.client_id'),
                config('services.google.client_id_ios'),
                config('services.google.client_id_android'),
            ]));

            // Try each client ID because the token audience varies by platform
            $payload = null;
            foreach ($validClientIds as $clientId) {
                $this->client->setClientId($clientId);
                $result = $this->client->verifyIdToken($idToken);
                if ($result) {
                    $payload = $result;
                    break;
                }
            }

            if (! $payload) {
                Log::warning('Google ID token verification failed: no matching client ID', [
                    'checked_client_ids' => $validClientIds,
                ]);

                return null;
            }

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
