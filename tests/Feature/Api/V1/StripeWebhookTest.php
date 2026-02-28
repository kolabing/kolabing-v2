<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Validation
    |--------------------------------------------------------------------------
    */

    public function test_webhook_rejects_missing_signature(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', []);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Missing signature or webhook secret');
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $response = $this->postJson('/api/v1/webhooks/stripe', [], [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'Invalid signature');
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Service: Checkout Completed
    |--------------------------------------------------------------------------
    */

    public function test_handle_checkout_completed_activates_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_test123',
            'status' => SubscriptionStatus::Inactive,
        ]);

        // Build Stripe objects from arrays (Stripe SDK supports this)
        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_test_new',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
        ]);

        $session = CheckoutSession::constructFrom([
            'id' => 'cs_test_session',
            'subscription' => 'sub_test_new',
            'metadata' => ['profile_id' => $profile->id],
        ]);

        // Use partial mock to override the retrieveStripeSubscription method
        $service = $this->getMockBuilder(SubscriptionService::class)
            ->onlyMethods(['retrieveStripeSubscription'])
            ->getMock();
        $service->expects($this->once())
            ->method('retrieveStripeSubscription')
            ->with('sub_test_new')
            ->willReturn($stripeSubscription);

        $service->handleCheckoutCompleted($session);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertEquals('sub_test_new', $subscription->stripe_subscription_id);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Service: Subscription Updated
    |--------------------------------------------------------------------------
    */

    public function test_handle_subscription_updated_syncs_status(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_updated',
        ]);

        $service = new SubscriptionService;

        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_updated',
            'status' => 'past_due',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
        ]);

        $service->handleSubscriptionUpdated($stripeSubscription);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);
    }

    public function test_handle_subscription_updated_with_cancel_at_period_end(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_cancelling',
            'cancel_at_period_end' => false,
        ]);

        $service = new SubscriptionService;

        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_cancelling',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => true,
        ]);

        $service->handleSubscriptionUpdated($stripeSubscription);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Service: Subscription Deleted
    |--------------------------------------------------------------------------
    */

    public function test_handle_subscription_deleted_cancels_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_to_cancel',
        ]);

        $service = new SubscriptionService;

        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_to_cancel',
            'status' => 'canceled',
        ]);

        $service->handleSubscriptionDeleted($stripeSubscription);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertFalse($subscription->cancel_at_period_end);
    }

    public function test_handle_subscription_deleted_ignores_unknown_subscription(): void
    {
        $service = new SubscriptionService;

        $stripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_nonexistent',
            'status' => 'canceled',
        ]);

        $service->handleSubscriptionDeleted($stripeSubscription);

        $this->assertTrue(true);
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Service: Payment Failed
    |--------------------------------------------------------------------------
    */

    public function test_handle_payment_failed_marks_past_due(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_past_due',
        ]);

        $service = new SubscriptionService;
        $service->handlePaymentFailed('sub_past_due');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::PastDue, $subscription->status);
    }

    public function test_handle_payment_failed_ignores_unknown_subscription(): void
    {
        $service = new SubscriptionService;

        $service->handlePaymentFailed('sub_nonexistent');

        $this->assertTrue(true);
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook Service: Payment Succeeded
    |--------------------------------------------------------------------------
    */

    public function test_handle_payment_succeeded_restores_past_due_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->pastDue()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_restored',
        ]);

        $service = new SubscriptionService;
        $service->handlePaymentSucceeded('sub_restored');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_handle_payment_succeeded_ignores_already_active_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        $subscription = BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_already_active',
        ]);

        $service = new SubscriptionService;
        $service->handlePaymentSucceeded('sub_already_active');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_handle_payment_succeeded_ignores_unknown_subscription(): void
    {
        $service = new SubscriptionService;

        $service->handlePaymentSucceeded('sub_unknown_xyz');

        $this->assertTrue(true);
    }
}
