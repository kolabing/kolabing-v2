# Apple IAP Backend API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Apple IAP subscription support (verify, restore, webhook) alongside existing Stripe flow, without touching any existing Stripe endpoints.

**Architecture:** New `SubscriptionSource` enum tracks payment source. `AppleIAPService` handles JWT generation for App Store Server API auth, transaction verification via HTTP, and JWS decoding for webhook notifications. Controller tests mock `AppleIAPService` entirely.

**Tech Stack:** Laravel 12, PHP 8.4, `firebase/php-jwt` (already installed), `Illuminate\Support\Facades\Http` for Apple API calls, PHPUnit with `LazilyRefreshDatabase`.

---

## Context (read before starting)

- `Profile` is the authenticatable model (not `User`). Tests use `$this->actingAs($profile)`.
- Response format: `{"success": true, "data": {...}, "message": "..."}` — match existing pattern exactly.
- Tests use `LazilyRefreshDatabase` trait (not `RefreshDatabase`).
- `BusinessSubscription` model has `HasUuids` — UUIDs as PKs.
- Existing `SubscriptionResource` is at `app/Http/Resources/Api/V1/SubscriptionResource.php`.
- Existing subscription tests: `tests/Feature/Api/V1/SubscriptionControllerTest.php` — update tests here when resource changes.
- Stripe webhook is at `app/Http/Controllers/Api/V1/StripeWebhookController.php` — use same pattern for Apple webhook.
- Apple timestamps are in **milliseconds** (not seconds). Use `Carbon::createFromTimestampMs()`.
- `firebase/php-jwt` is already in vendor — use `Firebase\JWT\JWT` and `Firebase\JWT\Key`.

---

## Task 1: Database Migration

**Files:**
- Create: `database/migrations/2026_03_27_000001_add_apple_iap_fields_to_business_subscriptions_table.php`

**Step 1: Write the failing test (TDD for migration)**

Add this test to `tests/Feature/Api/V1/SubscriptionControllerTest.php` inside the class:

```php
public function test_subscription_has_source_field(): void
{
    $profile = Profile::factory()->business()->create();
    BusinessProfile::factory()->create(['profile_id' => $profile->id]);
    BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);

    $response = $this->actingAs($profile)->getJson('/api/v1/me/subscription');

    $response->assertStatus(200)
        ->assertJsonPath('data.source', 'stripe');
}
```

**Step 2: Run to verify it fails**

```bash
php artisan test --compact tests/Feature/Api/V1/SubscriptionControllerTest.php --filter=test_subscription_has_source_field
```
Expected: FAIL (column missing / field not in resource)

**Step 3: Create the migration**

```bash
php artisan make:migration add_apple_iap_fields_to_business_subscriptions_table --no-interaction
```

Fill the generated file with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->string('source', 20)->default('stripe')->after('cancel_at_period_end');
            $table->string('apple_original_transaction_id')->nullable()->after('source');
            $table->string('apple_transaction_id')->nullable()->after('apple_original_transaction_id');
            $table->string('apple_product_id')->nullable()->after('apple_transaction_id');

            $table->index('apple_original_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['apple_original_transaction_id']);
            $table->dropColumn(['source', 'apple_original_transaction_id', 'apple_transaction_id', 'apple_product_id']);
        });
    }
};
```

**Step 4: Run migration**

```bash
php artisan migrate --no-interaction
```

---

## Task 2: SubscriptionSource Enum

**Files:**
- Create: `app/Enums/SubscriptionSource.php`

**Step 1: Create the enum**

```bash
php artisan make:class app/Enums/SubscriptionSource --no-interaction
```

Replace file contents with:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionSource: string
{
    case Stripe = 'stripe';
    case AppleIap = 'apple_iap';
}
```

---

## Task 3: Update BusinessSubscription Model and Factory

**Files:**
- Modify: `app/Models/BusinessSubscription.php`
- Modify: `database/factories/BusinessSubscriptionFactory.php`

**Step 1: Update the model**

In `app/Models/BusinessSubscription.php`:

1. Add `use App\Enums\SubscriptionSource;` to imports.
2. Add PHPDoc properties after existing ones:
```php
 * @property SubscriptionSource $source
 * @property string|null $apple_original_transaction_id
 * @property string|null $apple_transaction_id
 * @property string|null $apple_product_id
```
3. Add to `$fillable` array:
```php
'source',
'apple_original_transaction_id',
'apple_transaction_id',
'apple_product_id',
```
4. Add to `casts()` method:
```php
'source' => SubscriptionSource::class,
```

**Step 2: Update the factory**

In `database/factories/BusinessSubscriptionFactory.php`:

1. Add `use App\Enums\SubscriptionSource;` to imports.
2. Add `'source' => SubscriptionSource::Stripe,` to the `definition()` return array.
3. Add an `apple()` state method after `pastDue()`:

```php
/**
 * Indicate that the subscription is from Apple IAP.
 */
public function apple(): static
{
    return $this->state(fn (array $attributes) => [
        'source' => SubscriptionSource::AppleIap,
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'apple_original_transaction_id' => '2000000' . fake()->numerify('#########'),
        'apple_transaction_id' => '2000000' . fake()->numerify('#########'),
        'apple_product_id' => 'com.kolabing.app.subscription.monthly',
        'status' => SubscriptionStatus::Active,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'cancel_at_period_end' => false,
    ]);
}
```

**Step 3: Run migration fresh for tests**

```bash
php artisan test --compact tests/Feature/Api/V1/SubscriptionControllerTest.php --filter=test_show_subscription_returns_active_subscription
```
Expected: still PASS (model now has new fillable but existing tests unaffected)

---

## Task 4: Update SubscriptionStatus Enum

**Files:**
- Modify: `app/Enums/SubscriptionStatus.php`

**Step 1: Add label() method**

Add this method to the enum:

```php
/**
 * Get the human-readable label for the status.
 */
public function label(): string
{
    return match ($this) {
        self::Active => 'Active',
        self::Cancelled => 'Cancelled',
        self::PastDue => 'Past Due',
        self::Inactive => 'Inactive',
    };
}
```

---

## Task 5: Update SubscriptionResource

**Files:**
- Modify: `app/Http/Resources/Api/V1/SubscriptionResource.php`
- Modify: `tests/Feature/Api/V1/SubscriptionControllerTest.php`

**Step 1: Update SubscriptionResource**

Replace the `toArray()` method entirely:

```php
use App\Enums\SubscriptionSource;

public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'status' => $this->status->value,
        'status_label' => $this->status->label(),
        'source' => $this->source->value,
        'current_period_start' => $this->current_period_start?->toIso8601String(),
        'current_period_end' => $this->current_period_end?->toIso8601String(),
        'cancel_at_period_end' => $this->cancel_at_period_end,
        'is_active' => $this->isActive(),
        'days_remaining' => $this->current_period_end
            ? (int) max(0, now()->diffInDays($this->current_period_end, false))
            : null,
        'apple_product_id' => $this->source === SubscriptionSource::AppleIap
            ? $this->apple_product_id
            : null,
    ];
}
```

Also add `use App\Enums\SubscriptionSource;` to imports.

**Step 2: Update existing tests that check assertJsonStructure for subscription**

In `tests/Feature/Api/V1/SubscriptionControllerTest.php`, find `test_show_subscription_returns_active_subscription` — update its `assertJsonStructure` to include the new fields:

```php
->assertJsonStructure([
    'success',
    'data' => [
        'id',
        'status',
        'status_label',
        'source',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'is_active',
        'days_remaining',
        'apple_product_id',
    ],
]);
```

Also add assertion for `data.source` and `data.is_active` in that same test:

```php
->assertJsonPath('data.source', 'stripe')
->assertJsonPath('data.is_active', true)
->assertJsonPath('data.status_label', 'Active')
```

**Step 3: Run the failing test from Task 1 + existing tests**

```bash
php artisan test --compact tests/Feature/Api/V1/SubscriptionControllerTest.php
```
Expected: ALL PASS (including the new `test_subscription_has_source_field`)

**Step 4: Commit**

```bash
git add database/migrations/ app/Enums/ app/Models/BusinessSubscription.php database/factories/BusinessSubscriptionFactory.php app/Http/Resources/Api/V1/SubscriptionResource.php tests/Feature/Api/V1/SubscriptionControllerTest.php
git commit -m "feat: add Apple IAP fields to subscriptions — migration, enum, resource"
```

---

## Task 6: Update Config

**Files:**
- Modify: `config/services.php`

**Step 1: Extend the `apple` config block**

Replace the existing `apple` block:

```php
'apple' => [
    'client_id' => env('APPLE_CLIENT_ID'),
    'bundle_id' => env('APPLE_BUNDLE_ID', 'com.serragcvc.kolabing'),
    'issuer_id' => env('APPLE_ISSUER_ID'),
    'key_id' => env('APPLE_KEY_ID'),
    'private_key_path' => env('APPLE_PRIVATE_KEY_PATH', storage_path('app/apple/AuthKey.p8')),
    'iap_environment' => env('APPLE_IAP_ENVIRONMENT', 'sandbox'),
],
```

> **Note for developer:** Add these to your `.env`:
> ```
> APPLE_BUNDLE_ID=com.serragcvc.kolabing
> APPLE_ISSUER_ID=<from App Store Connect → Keys>
> APPLE_KEY_ID=<from App Store Connect → Keys>
> APPLE_PRIVATE_KEY_PATH=storage/app/apple/AuthKey_XXXXX.p8
> APPLE_IAP_ENVIRONMENT=sandbox
> ```
> Place the `.p8` private key file at `storage/app/apple/AuthKey_XXXXX.p8`.

---

## Task 7: Create AppleIAPService

**Files:**
- Create: `app/Services/AppleIAPService.php`

**Step 1: Create the service**

```bash
php artisan make:class app/Services/AppleIAPService --no-interaction
```

Replace the file with:

```php
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
            . chunk_split($header['x5c'][0], 64, "\n")
            . "-----END CERTIFICATE-----";

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
```

**Step 2: No unit test for service directly** — service talks to Apple's API; test it through controller tests with mocks. Service is well-factored enough.

---

## Task 8: Create Form Requests

**Files:**
- Create: `app/Http/Requests/Api/V1/VerifyAppleTransactionRequest.php`
- Create: `app/Http/Requests/Api/V1/RestoreApplePurchasesRequest.php`

**Step 1: Create VerifyAppleTransactionRequest**

```bash
php artisan make:request Api/V1/VerifyAppleTransactionRequest --no-interaction
```

Replace with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAppleTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string'],
            'original_transaction_id' => ['required', 'string'],
            'product_id' => ['required', 'string'],
        ];
    }
}
```

**Step 2: Create RestoreApplePurchasesRequest**

```bash
php artisan make:request Api/V1/RestoreApplePurchasesRequest --no-interaction
```

Replace with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RestoreApplePurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.transaction_id' => ['required', 'string'],
            'transactions.*.original_transaction_id' => ['required', 'string'],
            'transactions.*.product_id' => ['required', 'string'],
        ];
    }
}
```

---

## Task 9: Create AppleIAPController

**Files:**
- Create: `app/Http/Controllers/Api/V1/AppleIAPController.php`

**Step 1: Write the failing tests first**

Create `tests/Feature/Api/V1/AppleIAPControllerTest.php`:

```bash
php artisan make:test Api/V1/AppleIAPControllerTest --phpunit --no-interaction
```

Replace contents with:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppleIAPControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function mockAppleIAPService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(AppleIAPService::class);
        $this->app->instance(AppleIAPService::class, $mock);

        return $mock;
    }

    private function fakeTransactionData(array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => 'com.kolabing.app.subscription.monthly',
            'bundleId' => 'com.serragcvc.kolabing',
            'purchaseDate' => now()->subMinute()->timestampMs(),
            'expiresDate' => now()->addMonth()->timestampMs(),
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Tests
    |--------------------------------------------------------------------------
    */

    public function test_verify_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '123',
            'original_transaction_id' => '123',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_verify_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '123',
            'original_transaction_id' => '123',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_verify_validates_required_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['transaction_id', 'original_transaction_id', 'product_id'],
            ]);
    }

    public function test_verify_returns_400_when_apple_rejects_transaction(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('transactionAlreadyRecorded')->once()->andReturn(false);
        $mock->shouldReceive('verifyTransaction')->once()->andThrow(new \RuntimeException('Apple verification failed'));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'apple_verification_failed');
    }

    public function test_verify_creates_new_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $subscription = BusinessSubscription::factory()->apple()->create([
            'profile_id' => $profile->id,
        ]);

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('transactionAlreadyRecorded')->once()->andReturn(false);
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());
        $mock->shouldReceive('findOrCreateSubscription')->once()->andReturn($subscription);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_verify_returns_409_when_transaction_already_recorded(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $subscription = BusinessSubscription::factory()->apple()->create([
            'profile_id' => $profile->id,
            'apple_transaction_id' => '2000000111111111',
        ]);

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('transactionAlreadyRecorded')->once()->andReturn(true);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Transaction already verified.');
    }

    /*
    |--------------------------------------------------------------------------
    | Restore Tests
    |--------------------------------------------------------------------------
    */

    public function test_restore_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/subscription/apple-restore', [
            'transactions' => [['transaction_id' => '123', 'original_transaction_id' => '123', 'product_id' => 'x']],
        ]);

        $response->assertStatus(401);
    }

    public function test_restore_validates_transactions_array(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-restore', [
            'transactions' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_restore_returns_404_when_no_valid_subscription_found(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andThrow(new \RuntimeException('Not found'));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-restore', [
            'transactions' => [
                ['transaction_id' => '999', 'original_transaction_id' => '999', 'product_id' => 'x'],
            ],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('is_active', false);
    }

    public function test_restore_returns_subscription_when_found(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $subscription = BusinessSubscription::factory()->apple()->create([
            'profile_id' => $profile->id,
        ]);

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());
        $mock->shouldReceive('findOrCreateSubscription')->once()->andReturn($subscription);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-restore', [
            'transactions' => [
                ['transaction_id' => '2000000111111111', 'original_transaction_id' => '2000000000000001', 'product_id' => 'com.kolabing.app.subscription.monthly'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('message', 'Subscription restored successfully.');
    }
}
```

**Step 2: Run tests to confirm they fail**

```bash
php artisan test --compact tests/Feature/Api/V1/AppleIAPControllerTest.php
```
Expected: FAIL (controller and routes don't exist yet)

**Step 3: Create the controller**

```bash
php artisan make:controller Api/V1/AppleIAPController --no-interaction
```

Replace contents with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RestoreApplePurchasesRequest;
use App\Http\Requests\Api\V1\VerifyAppleTransactionRequest;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Profile;
use App\Services\AppleIAPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AppleIAPController extends Controller
{
    public function __construct(
        private readonly AppleIAPService $appleIAPService
    ) {}

    /**
     * Verify an Apple IAP transaction and create/update subscription.
     *
     * POST /api/v1/me/subscription/apple-verify
     */
    public function verify(VerifyAppleTransactionRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        $transactionId = $request->input('transaction_id');
        $originalTransactionId = $request->input('original_transaction_id');

        // Idempotency: return 409 if this exact transaction_id was already recorded
        if ($this->appleIAPService->transactionAlreadyRecorded($transactionId)) {
            $subscription = $profile->subscription;

            return response()->json([
                'success' => true,
                'data' => new SubscriptionResource($subscription),
                'message' => __('Transaction already verified.'),
            ], 409);
        }

        try {
            $transactionData = $this->appleIAPService->verifyTransaction($transactionId);
        } catch (\RuntimeException $e) {
            Log::warning('Apple IAP verify failed', ['error' => $e->getMessage(), 'transaction_id' => $transactionId]);

            return response()->json([
                'success' => false,
                'message' => __('Invalid transaction. Could not verify with Apple.'),
                'error' => 'apple_verification_failed',
            ], 400);
        }

        $subscription = $this->appleIAPService->findOrCreateSubscription($profile, $transactionData);

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Restore Apple IAP purchases.
     *
     * POST /api/v1/me/subscription/apple-restore
     */
    public function restore(RestoreApplePurchasesRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => __('Only business users can subscribe'),
            ], 403);
        }

        $transactions = $request->input('transactions');

        foreach ($transactions as $tx) {
            try {
                $transactionData = $this->appleIAPService->verifyTransaction($tx['transaction_id']);
                $subscription = $this->appleIAPService->findOrCreateSubscription($profile, $transactionData);

                return response()->json([
                    'success' => true,
                    'data' => new SubscriptionResource($subscription),
                    'message' => __('Subscription restored successfully.'),
                ]);
            } catch (\RuntimeException) {
                // Try next transaction
                continue;
            }
        }

        return response()->json([
            'success' => false,
            'message' => __('No active subscription found for this Apple account.'),
            'is_active' => false,
        ], 404);
    }
}
```

---

## Task 10: Create AppleWebhookController

**Files:**
- Create: `app/Http/Controllers/Api/V1/AppleWebhookController.php`
- Create: `tests/Feature/Api/V1/AppleWebhookControllerTest.php`

**Step 1: Write the failing tests first**

```bash
php artisan make:test Api/V1/AppleWebhookControllerTest --phpunit --no-interaction
```

Replace contents with:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppleWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function mockAppleIAPService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(AppleIAPService::class);
        $this->app->instance(AppleIAPService::class, $mock);

        return $mock;
    }

    private function fakeTransactionData(array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => 'com.kolabing.app.subscription.monthly',
            'purchaseDate' => now()->subMinute()->timestampMs(),
            'expiresDate' => now()->addMonth()->timestampMs(),
        ], $overrides);
    }

    public function test_webhook_returns_200_for_missing_payload(): void
    {
        $response = $this->postJson('/api/v1/webhooks/apple', []);

        $response->assertStatus(200);
    }

    public function test_webhook_returns_200_when_jws_decode_fails(): void
    {
        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')->once()->andThrow(new \RuntimeException('Bad JWS'));

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'invalid.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_handles_did_renew_notification(): void
    {
        $transactionData = $this->fakeTransactionData();

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'DID_RENEW',
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($transactionData);
        $mock->shouldReceive('handleNotification')
            ->once()
            ->with('DID_RENEW', $transactionData, '');

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_handles_expired_notification(): void
    {
        $transactionData = $this->fakeTransactionData();

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'EXPIRED',
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($transactionData);
        $mock->shouldReceive('handleNotification')
            ->once()
            ->with('EXPIRED', $transactionData, '');

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_ignores_notifications_without_transaction_info(): void
    {
        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->andReturn([
                'notificationType' => 'TEST',
                'subtype' => '',
                'data' => [], // no signedTransactionInfo
            ]);

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'some.jws.payload',
        ]);

        $response->assertStatus(200);
    }
}
```

**Step 2: Run to confirm failure**

```bash
php artisan test --compact tests/Feature/Api/V1/AppleWebhookControllerTest.php
```
Expected: FAIL

**Step 3: Create the controller**

```bash
php artisan make:controller Api/V1/AppleWebhookController --no-interaction
```

Replace contents with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppleIAPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppleWebhookController extends Controller
{
    public function __construct(
        private readonly AppleIAPService $appleIAPService
    ) {}

    /**
     * Handle incoming Apple Server Notifications V2.
     *
     * POST /api/v1/webhooks/apple
     * No auth — Apple sends signed JWS payload.
     * Must return 200 within 5 seconds or Apple retries.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload');

        if (! $signedPayload) {
            return response()->json([], 200);
        }

        try {
            $notification = $this->appleIAPService->decodeSignedJwt($signedPayload);
        } catch (\Exception $e) {
            Log::warning('Apple webhook JWS decode failed', ['error' => $e->getMessage()]);

            return response()->json([], 200);
        }

        $notificationType = $notification['notificationType'] ?? null;
        $subtype = $notification['subtype'] ?? '';
        $signedTransactionInfo = $notification['data']['signedTransactionInfo'] ?? null;

        if (! $notificationType || ! $signedTransactionInfo) {
            return response()->json([], 200);
        }

        try {
            $transactionData = $this->appleIAPService->decodeSignedJwt($signedTransactionInfo);
            $this->appleIAPService->handleNotification($notificationType, $transactionData, $subtype);

            Log::info('Apple webhook processed', [
                'type' => $notificationType,
                'original_transaction_id' => $transactionData['originalTransactionId'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Apple webhook processing failed', [
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([], 200);
    }
}
```

---

## Task 11: Register Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add imports at the top of routes/api.php**

Add these two use statements alongside the existing imports:

```php
use App\Http\Controllers\Api\V1\AppleIAPController;
use App\Http\Controllers\Api\V1\AppleWebhookController;
```

**Step 2: Add Apple webhook route (public, near Stripe webhook)**

After the Stripe webhook route:

```php
// Apple Server Notifications V2 Webhook (public — verified via JWS signature)
Route::post('webhooks/apple', AppleWebhookController::class)
    ->name('api.v1.webhooks.apple');
```

**Step 3: Add Apple IAP routes (protected, near subscription routes)**

Inside the `auth:sanctum` middleware group, after the existing subscription routes:

```php
// Apple IAP (iOS only)
Route::post('me/subscription/apple-verify', [AppleIAPController::class, 'verify'])
    ->name('api.v1.me.subscription.apple-verify');

Route::post('me/subscription/apple-restore', [AppleIAPController::class, 'restore'])
    ->name('api.v1.me.subscription.apple-restore');
```

**Step 4: Run all new tests**

```bash
php artisan test --compact tests/Feature/Api/V1/AppleIAPControllerTest.php tests/Feature/Api/V1/AppleWebhookControllerTest.php
```
Expected: ALL PASS

**Step 5: Run pint**

```bash
vendor/bin/pint --dirty
```

**Step 6: Run the full subscription test suite to check nothing broke**

```bash
php artisan test --compact tests/Feature/Api/V1/SubscriptionControllerTest.php
```
Expected: ALL PASS

**Step 7: Commit**

```bash
git add app/Services/AppleIAPService.php \
        app/Http/Controllers/Api/V1/AppleIAPController.php \
        app/Http/Controllers/Api/V1/AppleWebhookController.php \
        app/Http/Requests/Api/V1/VerifyAppleTransactionRequest.php \
        app/Http/Requests/Api/V1/RestoreApplePurchasesRequest.php \
        config/services.php \
        routes/api.php \
        tests/Feature/Api/V1/AppleIAPControllerTest.php \
        tests/Feature/Api/V1/AppleWebhookControllerTest.php
git commit -m "feat: add Apple IAP verify, restore, and webhook endpoints"
```

---

## Task 12: Final Verification

**Step 1: Run all tests**

```bash
php artisan test --compact
```
Expected: ALL PASS (no regressions)

**Step 2: Verify routes registered correctly**

```bash
php artisan route:list --name=apple --no-interaction
```
Expected: 3 routes shown — `api.v1.webhooks.apple`, `api.v1.me.subscription.apple-verify`, `api.v1.me.subscription.apple-restore`

---

## Developer Notes: Sandbox Testing

Once `.env` is configured with real Apple credentials:

1. Create sandbox tester in App Store Connect → Users → Sandbox Testers
2. On device: Settings → App Store → sign out → sign in with sandbox tester account
3. Set `APPLE_IAP_ENVIRONMENT=sandbox` in `.env`
4. Mobile app subscribes → Apple shows `[Environment: Sandbox]` dialog
5. Mobile sends transaction to `POST /api/v1/me/subscription/apple-verify`
6. Backend verifies against `https://api.storekit-sandbox.itunes.apple.com`
7. Sandbox renewals happen every 5 minutes — test full renewal cycle quickly

For webhook testing locally, use Apple's App Store Server Notifications test tool in App Store Connect or use `ngrok` to expose local endpoint.
