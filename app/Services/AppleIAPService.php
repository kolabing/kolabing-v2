<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppleIAPService
{
    private const PRODUCTION_API = 'https://api.storekit.itunes.apple.com';

    private const SANDBOX_API = 'https://api.storekit-sandbox.itunes.apple.com';

    /**
     * Verify a transaction with Apple's App Store Server API.
     * Returns the decoded transaction data array.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when Apple verification fails
     */
    public function verifyTransaction(string $transactionId): array
    {
        $baseUrl = $this->getApiBaseUrl();
        $token = $this->generateApiToken();

        $response = Http::withToken($token)
            ->get("{$baseUrl}/inApps/v1/transactions/{$transactionId}");

        if ($response->status() === 404) {
            throw new \RuntimeException('Transaction not found');
        }

        if (! $response->successful()) {
            Log::warning('Apple transaction verification failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Apple transaction verification failed');
        }

        $signedTransactionInfo = $response->json('signedTransactionInfo');

        if (! $signedTransactionInfo) {
            throw new \RuntimeException('No signedTransactionInfo in Apple response');
        }

        $transaction = $this->decodeSignedJwt($signedTransactionInfo);

        $this->validateTransaction($transaction);

        return $transaction;
    }

    /**
     * Find an existing subscription by original transaction ID, or create a new one.
     *
     * @param  array<string, mixed>  $transactionData
     */
    public function findOrCreateSubscription(Profile $profile, array $transactionData): BusinessSubscription
    {
        $originalTransactionId = $transactionData['originalTransactionId'];

        /** @var BusinessSubscription|null $subscription */
        $subscription = BusinessSubscription::query()
            ->where('apple_original_transaction_id', $originalTransactionId)
            ->first();

        $periodStart = Carbon::createFromTimestampMs($transactionData['purchaseDate']);
        $periodEnd = Carbon::createFromTimestampMs($transactionData['expiresDate']);

        if ($subscription) {
            $subscription->update([
                'apple_transaction_id' => $transactionData['transactionId'],
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
            ]);

            return $subscription->fresh();
        }

        return BusinessSubscription::query()->create([
            'profile_id' => $profile->id,
            'source' => SubscriptionSource::AppleIap,
            'apple_original_transaction_id' => $originalTransactionId,
            'apple_transaction_id' => $transactionData['transactionId'],
            'apple_product_id' => $transactionData['productId'],
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Check if a transaction_id has already been recorded (idempotency).
     */
    public function transactionAlreadyRecorded(string $transactionId): bool
    {
        return BusinessSubscription::query()
            ->where('apple_transaction_id', $transactionId)
            ->exists();
    }

    /**
     * Decode Apple's JWS (JSON Web Signature) compact notation.
     * Used for both webhook signedPayload and signedTransactionInfo.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException on invalid or unverifiable JWS
     */
    public function decodeSignedJwt(string $jws): array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWS format');
        }

        $headerJson = base64_decode(strtr($parts[0], '-_', '+/'));
        $header = json_decode($headerJson, true);

        if (empty($header['x5c']) || ! is_array($header['x5c'])) {
            throw new \RuntimeException('Missing x5c certificate chain in JWS header');
        }

        $leafCert = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($header['x5c'][0], 64, "\n")
            .'-----END CERTIFICATE-----';

        $certResource = openssl_x509_read($leafCert);

        if ($certResource === false) {
            throw new \RuntimeException('Failed to read leaf certificate');
        }

        $publicKeyResource = openssl_pkey_get_public($certResource);

        if ($publicKeyResource === false) {
            throw new \RuntimeException('Failed to extract public key from certificate');
        }

        $decoded = JWT::decode($jws, new Key($publicKeyResource, 'ES256'));

        return (array) $decoded;
    }

    /**
     * Update a subscription based on Apple Server Notification type.
     *
     * @param  array<string, mixed>  $transactionData
     */
    public function handleNotification(string $notificationType, array $transactionData, ?string $subtype = null): void
    {
        $originalTransactionId = $transactionData['originalTransactionId'] ?? null;

        if (! $originalTransactionId) {
            return;
        }

        /** @var BusinessSubscription|null $subscription */
        $subscription = BusinessSubscription::query()
            ->where('apple_original_transaction_id', $originalTransactionId)
            ->first();

        if (! $subscription) {
            Log::warning('Apple notification: subscription not found', [
                'original_transaction_id' => $originalTransactionId,
                'notification_type' => $notificationType,
            ]);

            return;
        }

        match ($notificationType) {
            'SUBSCRIBED', 'DID_RENEW' => $this->activateSubscription($subscription, $transactionData),
            'DID_CHANGE_RENEWAL_STATUS' => $subscription->update([
                'cancel_at_period_end' => $subtype === 'AUTO_RENEW_DISABLED',
            ]),
            'DID_FAIL_TO_RENEW' => $subscription->update(['status' => SubscriptionStatus::PastDue]),
            'EXPIRED', 'GRACE_PERIOD_EXPIRED', 'REFUND', 'REVOKE' => $subscription->update([
                'status' => SubscriptionStatus::Inactive,
                'cancel_at_period_end' => false,
            ]),
            default => Log::info('Unhandled Apple notification type', ['type' => $notificationType]),
        };
    }

    /**
     * Generate a signed JWT for authenticating with App Store Server API.
     */
    private function generateApiToken(): string
    {
        $privateKeyPath = config('services.apple.private_key_path');
        $privateKey = file_get_contents($privateKeyPath);

        if ($privateKey === false) {
            throw new \RuntimeException("Apple private key not found at: {$privateKeyPath}");
        }

        $payload = [
            'iss' => config('services.apple.issuer_id'),
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'appstoreconnect-v1',
            'bid' => config('services.apple.bundle_id'),
        ];

        return JWT::encode($payload, $privateKey, 'ES256', config('services.apple.key_id'));
    }

    /**
     * Validate that a decoded transaction belongs to this app.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function validateTransaction(array $transaction): void
    {
        $expectedBundleId = config('services.apple.bundle_id');

        if (isset($transaction['bundleId']) && $transaction['bundleId'] !== $expectedBundleId) {
            throw new \RuntimeException('Transaction bundle ID mismatch');
        }

        if (isset($transaction['revocationDate'])) {
            throw new \RuntimeException('Transaction has been revoked');
        }
    }

    /**
     * Activate or renew a subscription from transaction data.
     *
     * @param  array<string, mixed>  $transactionData
     */
    private function activateSubscription(BusinessSubscription $subscription, array $transactionData): void
    {
        $periodStart = Carbon::createFromTimestampMs($transactionData['purchaseDate']);
        $periodEnd = Carbon::createFromTimestampMs($transactionData['expiresDate']);

        $subscription->update([
            'apple_transaction_id' => $transactionData['transactionId'],
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => false,
        ]);
    }

    private function getApiBaseUrl(): string
    {
        return config('services.apple.iap_environment') === 'production'
            ? self::PRODUCTION_API
            : self::SANDBOX_API;
    }
}
