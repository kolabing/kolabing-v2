<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Enums\ProductType;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\OfferOption;
use App\Services\AppleIAPService;
use App\Support\OfferOptionValues;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CitySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Real-user subscription/payment flow.
 *
 * A freshly-registered business starts unpaid, is blocked by the publish
 * paywall, completes a (faked) Apple IAP purchase, and is then able to publish —
 * proving the payment is what unlocks the gated capability.
 *
 * Apple is never contacted: AppleIAPService::verifyTransaction is mocked to
 * return a valid transaction (the "fake payment"); everything else runs for real.
 */
class SubscriptionPaymentFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CitySeeder::class, BusinessTypeSeeder::class]);
    }

    public function test_business_registers_pays_fake_apple_iap_then_can_publish(): void
    {
        $city = City::query()->firstOrFail();

        // 1. Business registers (creates an INACTIVE subscription row) and logs in.
        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'pay.business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Pay Flow Cafe',
            'business_type' => BusinessType::query()->firstOrFail()->slug,
            'has_venue' => false,
            'city_id' => $city->id,
        ])->assertCreated();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'pay.business@example.com',
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        // 2. Before paying, the subscription is not active.
        $this->asToken($token)->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', false);

        // 3. Create a draft kolab.
        $kolabId = $this->asToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'product_promotion',
            'title' => 'Promote our cold brew at events',
            'description' => 'We supply ready-to-serve cold brew for community events across the city.',
            'preferred_city' => $city->name,
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'product_name' => 'Cold brew keg',
            'product_type' => ProductType::values()[0],
        ])->assertCreated()->json('data.id');

        // 4. Publish is blocked by the paywall while unpaid (402, subscription_required).
        $this->asToken($token)->postJson("/api/v1/kolabs/{$kolabId}/publish")
            ->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'subscription_required');

        // 5. Fake the Apple payment: mock the receipt verification, everything else real.
        $productId = (string) config('subscriptions.business.apple.monthly.apple_product_id');
        $mock = Mockery::mock(AppleIAPService::class)->makePartial();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => $productId,
            'bundleId' => 'com.serragcvc.kolabing',
            'purchaseDate' => now()->subMinute()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
        ]);
        $this->app->instance(AppleIAPService::class, $mock);

        $this->asToken($token)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $productId,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true);

        // 6. Subscription now reads as active.
        $this->asToken($token)->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        // 7. With the payment done, publishing now succeeds.
        $this->asToken($token)->postJson("/api/v1/kolabs/{$kolabId}/publish")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
    }

    /**
     * Authenticate the next request as the bearer-token's owner, re-resolving
     * from the token each time. Sanctum caches the resolved profile (and its
     * loaded relations) for the whole test, so without this a later read would
     * see a stale subscription relation after the DB was updated mid-test.
     */
    private function asToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
