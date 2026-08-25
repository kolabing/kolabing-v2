<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\InvalidRequestException;
use Stripe\Subscription;
use Tests\TestCase;

/**
 * The return-from-Stripe confirmation: a paid buyer must be activated on the spot
 * rather than left waiting on the webhook.
 */
class CheckoutConfirmTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENDPOINT = '/api/v1/me/subscription/checkout/confirm';

    private function business(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function paidSession(string $profileId, string $sessionId = 'cs_test_confirm'): Session
    {
        return Session::constructFrom([
            'object' => 'checkout.session',
            'id' => $sessionId,
            'status' => 'complete',
            'payment_status' => 'paid',
            'client_reference_id' => $profileId,
            'customer' => 'cus_test_confirm',
            'subscription' => 'sub_test_confirm',
            'metadata' => ['profile_id' => $profileId],
        ]);
    }

    private function stripeSubscription(): Subscription
    {
        return Subscription::constructFrom([
            'object' => 'subscription',
            'id' => 'sub_test_confirm',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
        ]);
    }

    public function test_confirm_requires_authentication(): void
    {
        $this->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertStatus(401);
    }

    public function test_confirm_rejects_a_malformed_session_id(): void
    {
        $this->actingAs($this->business())
            ->postJson(self::ENDPOINT, ['session_id' => 'not-a-session'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_id');
    }

    public function test_confirm_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertStatus(403);
    }

    public function test_confirm_activates_the_subscription_for_a_paid_session(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->with('cs_test_confirm')
                ->andReturn($this->paidSession($profile->id));
            $mock->shouldReceive('retrieveSubscription')
                ->with('sub_test_confirm')
                ->andReturn($this->stripeSubscription());
        });

        $this->actingAs($profile)
            ->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.source', 'stripe');

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_confirm',
            'stripe_customer_id' => 'cus_test_confirm',
            'status' => 'active',
            'source' => 'stripe',
        ]);
    }

    public function test_confirm_rejects_a_session_belonging_to_another_profile(): void
    {
        $caller = $this->business();
        $other = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock) use ($other): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->andReturn($this->paidSession($other->id));
            // The activation path must never be reached.
            $mock->shouldNotReceive('retrieveSubscription');
        });

        $this->actingAs($caller)
            ->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('business_subscriptions', ['profile_id' => $caller->id]);
        $this->assertDatabaseMissing('business_subscriptions', ['profile_id' => $other->id]);
    }

    public function test_confirm_reports_pending_while_payment_is_unfinished(): void
    {
        $profile = $this->business();

        $unpaid = Session::constructFrom([
            'object' => 'checkout.session',
            'id' => 'cs_test_confirm',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'client_reference_id' => $profile->id,
            'metadata' => ['profile_id' => $profile->id],
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($unpaid): void {
            $mock->shouldReceive('retrieveCheckoutSession')->once()->andReturn($unpaid);
            $mock->shouldNotReceive('retrieveSubscription');
        });

        $this->actingAs($profile)
            ->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseMissing('business_subscriptions', ['profile_id' => $profile->id]);
    }

    public function test_confirm_is_idempotent(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock) use ($profile): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->twice()
                ->andReturn($this->paidSession($profile->id));
            $mock->shouldReceive('retrieveSubscription')
                ->andReturn($this->stripeSubscription());
        });

        $this->actingAs($profile)->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])->assertOk();
        $this->actingAs($profile)->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])->assertOk();

        $this->assertSame(1, BusinessSubscription::query()->where('profile_id', $profile->id)->count());
    }

    public function test_confirm_returns_502_when_stripe_is_unreachable(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveCheckoutSession')
                ->once()
                ->andThrow(new InvalidRequestException('no such checkout session'));
        });

        $this->actingAs($profile)
            ->postJson(self::ENDPOINT, ['session_id' => 'cs_test_confirm'])
            ->assertStatus(502);
    }
}
