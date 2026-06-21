<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\Wallet;
use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppleIAPControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function partialAppleIAPService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(AppleIAPService::class)->makePartial();
        $this->app->instance(AppleIAPService::class, $mock);

        return $mock;
    }

    private function fakeTransactionData(array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => $this->monthlyAppleProductId(),
            'bundleId' => 'com.serragcvc.kolabing',
            'purchaseDate' => now()->subMinute()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
        ], $overrides);
    }

    private function monthlyAppleProductId(): string
    {
        return (string) config('subscriptions.business.apple.monthly.apple_product_id');
    }

    private function threeMonthAppleProductId(): string
    {
        return (string) config('subscriptions.business.apple.three_months.apple_product_id');
    }

    public function test_verify_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '123',
            'original_transaction_id' => '123',
            'product_id' => $this->monthlyAppleProductId(),
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
            'product_id' => $this->monthlyAppleProductId(),
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_verify_validates_required_fields(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['transaction_id', 'original_transaction_id', 'product_id'],
            ]);
    }

    public function test_verify_rejects_unknown_product_id_before_calling_apple(): void
    {
        $profile = $this->createBusinessProfile();

        $mock = $this->partialAppleIAPService();
        $mock->shouldNotReceive('verifyTransaction');

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => 'com.kolabing.old.subscription.monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['product_id'],
            ]);
    }

    public function test_verify_returns_400_when_apple_rejects_transaction(): void
    {
        $profile = $this->createBusinessProfile();

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andThrow(new \RuntimeException('Apple verification failed'));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->monthlyAppleProductId(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'apple_verification_failed');
    }

    public function test_verify_activates_existing_inactive_subscription_and_rewards_valid_referral(): void
    {
        $profile = $this->createBusinessProfile();
        $existingSubscription = BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
            'source' => 'stripe',
        ]);

        $referrer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $referrer->id]);
        ReferralCode::factory()->forProfile($referrer)->create([
            'code' => 'KOLAB-TEST',
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->monthlyAppleProductId(),
            'referral_code' => '  kolab-test  ',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $existingSubscription->id)
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $existingSubscription->id,
            'source' => 'apple_iap',
            'status' => 'active',
            'apple_transaction_id' => '2000000111111111',
            'apple_original_transaction_id' => '2000000000000001',
            'apple_product_id' => $this->monthlyAppleProductId(),
        ]);

        $this->assertDatabaseHas('referral_codes', [
            'profile_id' => $referrer->id,
            'code' => 'KOLAB-TEST',
            'total_conversions' => 1,
            'total_points_earned' => 50,
        ]);

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $referrer->id,
            'points' => 50,
        ]);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $referrer->id,
            'points' => 50,
            'event_type' => 'referral_conversion',
            'reference_id' => $existingSubscription->id,
        ]);
    }

    public function test_verify_accepts_the_three_month_plan_product_id(): void
    {
        $profile = $this->createBusinessProfile();
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData([
            'productId' => $this->threeMonthAppleProductId(),
            'expiresDate' => now()->addMonths(3)->getTimestampMs(),
        ]));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->threeMonthAppleProductId(),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('data.apple_product_id', $this->threeMonthAppleProductId());

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'source' => 'apple_iap',
            'status' => 'active',
            'apple_product_id' => $this->threeMonthAppleProductId(),
        ]);
    }

    public function test_verify_is_idempotent_and_does_not_duplicate_referral_reward(): void
    {
        $profile = $this->createBusinessProfile();
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $referrer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $referrer->id]);
        ReferralCode::factory()->forProfile($referrer)->create([
            'code' => 'KOLAB-TEST',
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());

        $payload = [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->monthlyAppleProductId(),
            'referral_code' => 'KOLAB-TEST',
        ];

        $firstResponse = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', $payload);
        $secondResponse = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', $payload);

        $firstResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'apple_iap');

        $secondResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $firstResponse->json('data.id'))
            ->assertJsonPath('data.source', 'apple_iap');

        $this->assertSame(1, BusinessSubscription::query()->where('profile_id', $profile->id)->count());
        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $referrer->id)
            ->where('event_type', 'referral_conversion')
            ->count());
        $this->assertSame(50, Wallet::query()->where('profile_id', $referrer->id)->value('points'));
        $this->assertDatabaseHas('referral_codes', [
            'profile_id' => $referrer->id,
            'code' => 'KOLAB-TEST',
            'total_conversions' => 1,
            'total_points_earned' => 50,
        ]);
    }

    public function test_verify_returns_422_for_invalid_referral_code(): void
    {
        $profile = $this->createBusinessProfile();
        $existingSubscription = BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->monthlyAppleProductId(),
            'referral_code' => 'KOLAB-MISS',
        ]);

        $response->assertStatus(422)
            ->assertExactJson([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'referral_code' => ['The selected referral code is invalid.'],
                ],
            ]);

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $existingSubscription->id,
            'status' => 'inactive',
            'apple_transaction_id' => null,
        ]);
    }

    public function test_verify_returns_400_when_request_payload_does_not_match_apple_transaction(): void
    {
        $profile = $this->createBusinessProfile();
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData([
            'productId' => 'com.kolabing.kolabingApp.subscription.three_months',
        ]));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => $this->monthlyAppleProductId(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'apple_verification_failed');
    }

    /**
     * Regression: in StoreKit2 every renewal has a new transactionId but the SAME
     * originalTransactionId. In sandbox a monthly sub renews in minutes, so the
     * client may send a stale/wrong original_transaction_id. Apple's verified
     * response is authoritative — verification must succeed and the subscription
     * must be stored under Apple's originalTransactionId so webhooks match.
     */
    public function test_verify_succeeds_when_client_original_transaction_id_differs_from_apple_renewal_chain(): void
    {
        $profile = $this->createBusinessProfile();

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData([
            'transactionId' => '2000001191883535',
            'originalTransactionId' => '2000001189129494',
        ]));

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000001191883535',
            'original_transaction_id' => '2000001191883535',
            'product_id' => $this->monthlyAppleProductId(),
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'apple_original_transaction_id' => '2000001189129494',
            'apple_transaction_id' => '2000001191883535',
        ]);
    }

    public function test_restore_returns_subscription_when_found(): void
    {
        $profile = $this->createBusinessProfile();

        $subscription = BusinessSubscription::factory()->apple()->create([
            'profile_id' => $profile->id,
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn($this->fakeTransactionData());
        $mock->shouldReceive('findOrCreateSubscription')->once()->andReturn($subscription);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-restore', [
            'transactions' => [
                ['transaction_id' => '2000000111111111', 'original_transaction_id' => '2000000000000001', 'product_id' => $this->monthlyAppleProductId()],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('message', 'Subscription restored successfully.');
    }

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }
}
