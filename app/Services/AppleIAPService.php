<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MissionTrigger;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppleIAPService
{
    private const PRODUCTION_API = 'https://api.storekit.itunes.apple.com';

    private const SANDBOX_API = 'https://api.storekit-sandbox.itunes.apple.com';

    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Verify a transaction with Apple's App Store Server API.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function verifyTransaction(string $transactionId): array
    {
        $baseUrl = $this->getApiBaseUrl();
        $environment = config('services.apple.iap_environment');

        try {
            $token = $this->generateApiToken();
        } catch (\RuntimeException $e) {
            Log::error('Apple IAP: could not generate App Store Server API token', [
                'error' => $e->getMessage(),
                'environment' => $environment,
                'has_private_key' => filled(config('services.apple.private_key')),
                'private_key_path' => config('services.apple.private_key_path'),
                'has_issuer_id' => filled(config('services.apple.issuer_id')),
                'has_key_id' => filled(config('services.apple.key_id')),
                'bundle_id' => config('services.apple.bundle_id'),
            ]);

            throw $e;
        }

        Log::info('Apple IAP: verifying transaction', [
            'transaction_id' => $transactionId,
            'environment' => $environment,
            'base_url' => $baseUrl,
        ]);

        $response = Http::withToken($token)
            ->get("{$baseUrl}/inApps/v1/transactions/{$transactionId}");

        if ($response->status() === 404) {
            Log::warning('Apple IAP: transaction not found at this environment', [
                'transaction_id' => $transactionId,
                'environment' => $environment,
                'base_url' => $baseUrl,
                'status' => 404,
                'body' => $response->body(),
                'hint' => 'A sandbox/TestFlight purchase is 404 on production API and vice versa. Check APPLE_IAP_ENVIRONMENT.',
            ]);

            throw new \RuntimeException('Transaction not found');
        }

        if (! $response->successful()) {
            Log::warning('Apple transaction verification failed', [
                'transaction_id' => $transactionId,
                'environment' => $environment,
                'base_url' => $baseUrl,
                'status' => $response->status(),
                'body' => $response->body(),
                'hint' => $response->status() === 401
                    ? 'HTTP 401 means Apple rejected the JWT: check APPLE_ISSUER_ID, APPLE_KEY_ID and the private key.'
                    : null,
            ]);

            throw new \RuntimeException('Apple transaction verification failed');
        }

        $signedTransactionInfo = $response->json('signedTransactionInfo');

        if (! $signedTransactionInfo) {
            Log::warning('Apple IAP: no signedTransactionInfo in successful response', [
                'transaction_id' => $transactionId,
                'environment' => $environment,
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('No signedTransactionInfo in Apple response');
        }

        $transaction = $this->decodeSignedJwt($signedTransactionInfo);

        $this->validateTransaction($transaction);

        return $transaction;
    }

    public function findSubscriptionByTransactionId(string $transactionId): ?BusinessSubscription
    {
        return BusinessSubscription::query()
            ->where('apple_transaction_id', $transactionId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $transactionData
     *
     * @throws \RuntimeException
     */
    public function assertTransactionMatchesRequest(
        array $transactionData,
        string $expectedProductId,
    ): void {
        if (($transactionData['productId'] ?? null) !== $expectedProductId) {
            throw new \RuntimeException('Apple product ID mismatch');
        }
    }

    /**
     * Find an existing subscription for the profile/transaction or activate one in place.
     *
     * @param  array<string, mixed>  $transactionData
     *
     * @throws \RuntimeException
     */
    public function findOrCreateSubscription(Profile $profile, array $transactionData): BusinessSubscription
    {
        $originalTransactionId = (string) ($transactionData['originalTransactionId'] ?? '');
        $transactionId = (string) ($transactionData['transactionId'] ?? '');

        try {
            return DB::transaction(function () use ($profile, $transactionData, $originalTransactionId, $transactionId): BusinessSubscription {
                $subscription = BusinessSubscription::query()
                    ->where('apple_original_transaction_id', $originalTransactionId)
                    ->lockForUpdate()
                    ->first();

                if ($subscription !== null && $subscription->profile_id !== $profile->id) {
                    throw new \RuntimeException('Apple transaction already belongs to another profile');
                }

                $existingByTransaction = BusinessSubscription::query()
                    ->where('apple_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->first();

                if ($existingByTransaction !== null && $existingByTransaction->profile_id !== $profile->id) {
                    throw new \RuntimeException('Apple transaction already belongs to another profile');
                }

                if ($subscription === null) {
                    $subscription = $existingByTransaction;
                }

                if ($subscription === null) {
                    $subscription = BusinessSubscription::query()
                        ->where('profile_id', $profile->id)
                        ->lockForUpdate()
                        ->first();
                }

                if (
                    $subscription !== null
                    && $subscription->apple_original_transaction_id !== null
                    && $subscription->apple_original_transaction_id !== $originalTransactionId
                ) {
                    throw new \RuntimeException('Profile already linked to another Apple subscription');
                }

                $attributes = $this->activeSubscriptionAttributes($transactionData);

                if ($subscription !== null) {
                    $subscription->fill($attributes);
                    $subscription->save();

                    return $subscription->fresh();
                }

                return BusinessSubscription::query()->create([
                    'profile_id' => $profile->id,
                    ...$attributes,
                ]);
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }

            $existing = BusinessSubscription::query()
                ->where('apple_transaction_id', $transactionId)
                ->orWhere('apple_original_transaction_id', $originalTransactionId)
                ->first();

            if ($existing !== null && $existing->profile_id === $profile->id) {
                return $existing;
            }

            throw new \RuntimeException('Apple transaction already belongs to another profile', previous: $e);
        }
    }

    /**
     * Decode Apple's JWS compact notation.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
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

        // JWT::decode() returns nested stdClass objects. A shallow `(array)` cast
        // only converts the top level, leaving nested claims (e.g. `data`) as
        // stdClass — which then crashes array access such as
        // $notification['data']['signedTransactionInfo'] with "Cannot use object
        // of type stdClass as array". Deep-convert so every claim is array-safe.
        // (Fixes PHP-LARAVEL-6.)
        return self::claimsToArray($decoded);
    }

    /**
     * Recursively convert a decoded JWT payload (nested stdClass) into a fully
     * associative array so deep array access on nested claims is always safe.
     *
     * @return array<string, mixed>
     */
    private static function claimsToArray(object $claims): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) json_encode($claims), true) ?? [];

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $transactionData
     * @param  array<string, mixed>|null  $renewalData
     */
    public function handleNotification(
        string $notificationType,
        array $transactionData,
        ?string $subtype = null,
        ?array $renewalData = null,
    ): void {
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
            'DID_FAIL_TO_RENEW' => $subtype === 'GRACE_PERIOD'
                ? $this->markGracePeriod($subscription, $transactionData, $renewalData)
                : $this->markPastDue($subscription, $transactionData),
            'EXPIRED', 'GRACE_PERIOD_EXPIRED', 'REFUND', 'REVOKE' => $this->deactivateSubscription($subscription, $transactionData),
            default => Log::info('Unhandled Apple notification type', ['type' => $notificationType]),
        };

        // Missions: only an actual auto-renewal (DID_RENEW) counts as a
        // "subscription renewed" event — SUBSCRIBED is the initial purchase, not
        // a renewal. plan_upgraded stays inert: there is no plan-tier model to
        // detect an upgrade from. Guarded so a mission failure never breaks the
        // webhook handler.
        if ($notificationType === 'DID_RENEW') {
            $this->recordRenewalMission($subscription);
        }
    }

    /**
     * Fire the subscription_renewed mission for the subscription's owning
     * business profile. Guarded — a mission failure must never break the
     * Apple webhook handler.
     */
    private function recordRenewalMission(BusinessSubscription $subscription): void
    {
        try {
            $profile = $subscription->profile;

            if ($profile === null) {
                return;
            }

            $this->missionService->record(
                $profile,
                MissionTrigger::SubscriptionRenewed,
                1,
                ['reference_id' => $subscription->id],
            );
        } catch (\Throwable $e) {
            Log::warning('Mission record failed (subscription_renewed)', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateApiToken(): string
    {
        $privateKey = $this->resolvePrivateKey();

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
     * Resolve the Apple App Store private key (.p8) contents.
     *
     * Prefers the inline APPLE_PRIVATE_KEY value so containerised deploys do not
     * depend on a file that may be absent after a rebuild. Falls back to the
     * configured file path, guarding the read so a missing file raises a clear
     * exception instead of a raw file_get_contents() PHP warning.
     */
    private function resolvePrivateKey(): string
    {
        $inlineKey = config('services.apple.private_key');

        if (is_string($inlineKey) && trim($inlineKey) !== '') {
            return $inlineKey;
        }

        $privateKeyPath = config('services.apple.private_key_path');

        if (! is_string($privateKeyPath) || ! is_readable($privateKeyPath)) {
            throw new \RuntimeException(
                'Apple private key not configured: set APPLE_PRIVATE_KEY or provide a readable '
                ."file at APPLE_PRIVATE_KEY_PATH (resolved: {$privateKeyPath})."
            );
        }

        $privateKey = file_get_contents($privateKeyPath);

        if ($privateKey === false) {
            throw new \RuntimeException("Apple private key could not be read at: {$privateKeyPath}");
        }

        return $privateKey;
    }

    /**
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
     * @param  array<string, mixed>  $transactionData
     */
    private function activateSubscription(BusinessSubscription $subscription, array $transactionData): void
    {
        $subscription->update($this->activeSubscriptionAttributes($transactionData));
    }

    /**
     * @param  array<string, mixed>  $transactionData
     * @param  array<string, mixed>|null  $renewalData
     */
    private function markGracePeriod(
        BusinessSubscription $subscription,
        array $transactionData,
        ?array $renewalData = null,
    ): void {
        $attributes = $this->appleIdentityAttributes($transactionData);
        $attributes['status'] = SubscriptionStatus::Active;
        $attributes['current_period_start'] = isset($transactionData['purchaseDate'])
            ? Carbon::createFromTimestampMs($transactionData['purchaseDate'])
            : $subscription->current_period_start;
        $attributes['current_period_end'] = isset($renewalData['gracePeriodExpiresDate'])
            ? Carbon::createFromTimestampMs($renewalData['gracePeriodExpiresDate'])
            : $this->resolveExpiresAt($transactionData);
        $attributes['cancel_at_period_end'] = false;

        $subscription->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $transactionData
     */
    private function markPastDue(BusinessSubscription $subscription, array $transactionData): void
    {
        $attributes = $this->appleIdentityAttributes($transactionData);
        $attributes['status'] = SubscriptionStatus::PastDue;

        $subscription->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $transactionData
     */
    private function deactivateSubscription(BusinessSubscription $subscription, array $transactionData): void
    {
        $attributes = $this->appleIdentityAttributes($transactionData);
        $attributes['status'] = SubscriptionStatus::Inactive;
        $attributes['cancel_at_period_end'] = false;
        $attributes['current_period_end'] = $this->resolveExpiresAt($transactionData) ?? $subscription->current_period_end;

        $subscription->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $transactionData
     * @return array<string, mixed>
     */
    private function activeSubscriptionAttributes(array $transactionData): array
    {
        return [
            ...$this->appleIdentityAttributes($transactionData),
            'status' => SubscriptionStatus::Active,
            'current_period_start' => isset($transactionData['purchaseDate'])
                ? Carbon::createFromTimestampMs($transactionData['purchaseDate'])
                : null,
            'current_period_end' => $this->resolveExpiresAt($transactionData),
            'cancel_at_period_end' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $transactionData
     * @return array<string, mixed>
     */
    private function appleIdentityAttributes(array $transactionData): array
    {
        return [
            'source' => SubscriptionSource::AppleIap,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'apple_original_transaction_id' => $transactionData['originalTransactionId'] ?? null,
            'apple_transaction_id' => $transactionData['transactionId'] ?? null,
            'apple_product_id' => $transactionData['productId'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $transactionData
     */
    private function resolveExpiresAt(array $transactionData): ?Carbon
    {
        return isset($transactionData['expiresDate'])
            ? Carbon::createFromTimestampMs($transactionData['expiresDate'])
            : null;
    }

    private function getApiBaseUrl(): string
    {
        return config('services.apple.iap_environment') === 'production'
            ? self::PRODUCTION_API
            : self::SANDBOX_API;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'constraint');
    }
}
