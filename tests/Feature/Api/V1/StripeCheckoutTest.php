<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Subscription;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * @return array{success_url: string, cancel_url: string}
     */
    private function validPayload(): array
    {
        return [
            'success_url' => 'kolabing://subscription/success',
            'cancel_url' => 'kolabing://subscription/cancel',
        ];
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/subscription/checkout', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_checkout_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', $this->validPayload())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only business users can subscribe');
    }

    public function test_checkout_accepts_an_allowlisted_https_return_url(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn('https://checkout.stripe.com/c/pay/cs_test_https');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                // Mixed case must normalise to an allowlisted host, not 422.
                'success_url' => 'https://Kolabing.com/subscription/success',
                'cancel_url' => 'https://www.kolabing.com/subscription/cancel',
            ])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_https');
    }

    public function test_checkout_rejects_a_subdomain_open_redirect_trick(): void
    {
        $profile = $this->business();

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                // host = kolabing.com.evil.com — must NOT match the allowlist.
                'success_url' => 'https://kolabing.com.evil.com/steal',
                'cancel_url' => 'kolabing://subscription/cancel',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('success_url');
    }

    public function test_checkout_rejects_a_non_allowlisted_return_url(): void
    {
        $profile = $this->business();

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://evil.example.com/steal',
                'cancel_url' => 'kolabing://subscription/cancel',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('success_url');
    }

    public function test_business_gets_a_checkout_url(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn('https://checkout.stripe.com/c/pay/cs_test_123');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123');
    }

    public function test_webhook_rejects_an_invalid_signature(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(new SignatureVerificationException('bad signature'));
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'bad'])
            ->assertStatus(400);
    }

    public function test_webhook_activates_a_subscription_on_checkout_completed(): void
    {
        $profile = $this->business();

        $session = Session::constructFrom([
            'object' => 'checkout.session',
            'id' => 'cs_test_123',
            'client_reference_id' => $profile->id,
            'customer' => 'cus_test_123',
            'subscription' => 'sub_test_123',
            'metadata' => ['profile_id' => $profile->id],
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session->toArray()],
        ]);

        $stripeSubscription = Subscription::constructFrom([
            'object' => 'subscription',
            'id' => 'sub_test_123',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event, $stripeSubscription): void {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
            $mock->shouldReceive('retrieveSubscription')->with('sub_test_123')->andReturn($stripeSubscription);
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])
            ->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_customer_id' => 'cus_test_123',
            'status' => 'active',
            'source' => 'stripe',
        ]);
    }
}
