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
            'purchaseDate' => now()->subMinute()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
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

        BusinessSubscription::factory()->apple()->create([
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
