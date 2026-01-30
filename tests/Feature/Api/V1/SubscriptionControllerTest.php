<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Show Subscription Tests
    |--------------------------------------------------------------------------
    */

    public function test_show_subscription_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/subscription');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_show_subscription_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only business users can have subscriptions');
    }

    public function test_show_subscription_returns_null_when_no_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No subscription found');
    }

    public function test_show_subscription_returns_active_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', false)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'current_period_start',
                    'current_period_end',
                    'cancel_at_period_end',
                ],
            ]);
    }

    public function test_show_subscription_returns_cancelled_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->cancelled()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_at_period_end', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Checkout Session Tests
    |--------------------------------------------------------------------------
    */

    public function test_checkout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/subscription/checkout', [
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only business users can subscribe');
    }

    public function test_checkout_requires_success_url(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['success_url'],
            ]);
    }

    public function test_checkout_requires_cancel_url(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['cancel_url'],
            ]);
    }

    public function test_checkout_validates_url_format(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'not-a-valid-url',
                'cancel_url' => 'also-not-valid',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['success_url', 'cancel_url'],
            ]);
    }

    public function test_checkout_fails_if_already_subscribed(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
        ]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You already have an active subscription');
    }

    public function test_checkout_creates_session_for_new_subscriber(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        // Mock SubscriptionService to avoid real Stripe API calls
        $mock = Mockery::mock(SubscriptionService::class)->makePartial();
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                'session_id' => 'cs_test_123',
            ]);
        $this->app->instance(SubscriptionService::class, $mock);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'checkout_url',
                    'session_id',
                ],
            ]);

        $checkoutUrl = $response->json('data.checkout_url');
        $this->assertStringContainsString('checkout.stripe.com', $checkoutUrl);
    }

    public function test_checkout_creates_session_for_inactive_subscriber(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_existing123',
        ]);

        // Mock SubscriptionService to avoid real Stripe API calls
        $mock = Mockery::mock(SubscriptionService::class)->makePartial();
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_456',
                'session_id' => 'cs_test_456',
            ]);
        $this->app->instance(SubscriptionService::class, $mock);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'checkout_url',
                    'session_id',
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Billing Portal Tests
    |--------------------------------------------------------------------------
    */

    public function test_portal_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/subscription/portal');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_portal_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription/portal');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only business users can access billing portal');
    }

    public function test_portal_fails_without_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription/portal');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No subscription found for this user');
    }

    public function test_portal_returns_url_for_subscriber(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_test123',
        ]);

        // Mock SubscriptionService to avoid real Stripe API calls
        $mock = Mockery::mock(SubscriptionService::class)->makePartial();
        $mock->shouldReceive('getBillingPortalUrl')
            ->once()
            ->andReturn([
                'portal_url' => 'https://billing.stripe.com/p/session/test_portal_123',
            ]);
        $this->app->instance(SubscriptionService::class, $mock);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription/portal?return_url=https://example.com/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portal_url',
                ],
            ]);

        $portalUrl = $response->json('data.portal_url');
        $this->assertStringContainsString('billing.stripe.com', $portalUrl);
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Subscription Tests
    |--------------------------------------------------------------------------
    */

    public function test_cancel_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/subscription/cancel');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_cancel_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only business users can cancel subscriptions');
    }

    public function test_cancel_fails_without_active_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No active subscription found');
    }

    public function test_cancel_fails_for_inactive_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test123',
        ]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Subscription is not active');
    }

    public function test_cancel_sets_cancel_at_period_end(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'cancel_at_period_end' => false,
        ]);

        // Mock SubscriptionService to avoid real Stripe API calls
        $mock = Mockery::mock(SubscriptionService::class)->makePartial();
        $mock->shouldReceive('cancelSubscription')
            ->once()
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update(['cancel_at_period_end' => true]);

                return $subscription->fresh();
            });
        $this->app->instance(SubscriptionService::class, $mock);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription will be cancelled at the end of the billing period')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_at_period_end', true);

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'cancel_at_period_end' => true,
        ]);
    }

    public function test_cancel_is_idempotent(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'cancel_at_period_end' => false,
        ]);

        // Mock SubscriptionService for both calls
        $mock = Mockery::mock(SubscriptionService::class)->makePartial();
        $mock->shouldReceive('cancelSubscription')
            ->twice()
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update(['cancel_at_period_end' => true]);

                return $subscription->fresh();
            });
        $this->app->instance(SubscriptionService::class, $mock);

        // First cancellation
        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.cancel_at_period_end', true);

        // Second cancellation should also succeed
        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel')
            ->assertStatus(200)
            ->assertJsonPath('data.cancel_at_period_end', true);
    }
}
