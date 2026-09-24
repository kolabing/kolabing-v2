<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Subscription;
use Tests\TestCase;

/**
 * Venue Pro (BE-NF-68): the €299 plan, sold on the web only. The plan a
 * subscription is on always comes from the Stripe Price it bills — never from
 * the client — and Pro is a superset of the standard plan.
 */
class VenueProPlanTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscriptions.business.stripe.monthly.stripe_price_id' => 'price_monthly_test',
            'subscriptions.business.stripe.three_months.stripe_price_id' => 'price_quarterly_test',
            'subscriptions.business.stripe.pro_monthly.stripe_price_id' => 'price_pro_test',
        ]);
    }

    private function business(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function stripeSubscription(string $id, string $priceId, string $status = 'active'): Subscription
    {
        return Subscription::constructFrom([
            'object' => 'subscription',
            'id' => $id,
            'status' => $status,
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'items' => [
                'object' => 'list',
                'data' => [[
                    'object' => 'subscription_item',
                    'id' => 'si_test_1',
                    'price' => ['object' => 'price', 'id' => $priceId],
                ]],
            ],
        ]);
    }

    private function checkoutCompletedEvent(Profile $profile, string $subscriptionId): Event
    {
        $session = Session::constructFrom([
            'object' => 'checkout.session',
            'id' => 'cs_test_pro',
            'client_reference_id' => $profile->id,
            'customer' => 'cus_test_pro',
            'subscription' => $subscriptionId,
            'metadata' => ['profile_id' => $profile->id],
        ]);

        return Event::constructFrom([
            'id' => 'evt_test_pro',
            'type' => 'checkout.session.completed',
            'data' => ['object' => $session->toArray()],
        ]);
    }

    // ── Checkout ───────────────────────────────────────────────────────────

    public function test_checkout_accepts_the_pro_plan(): void
    {
        $profile = $this->business();

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (Profile $p, string $plan): bool => $plan === 'pro_monthly')
                ->andReturn('https://checkout.stripe.com/c/pay/cs_test_pro');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'plan' => 'pro_monthly',
                'success_url' => 'kolabing://subscription/success',
                'cancel_url' => 'kolabing://subscription/cancel',
            ])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_pro');
    }

    public function test_checkout_rejects_an_unknown_plan(): void
    {
        $this->actingAs($this->business())
            ->postJson('/api/v1/me/subscription/checkout', [
                'plan' => 'pro_yearly',
                'success_url' => 'kolabing://subscription/success',
                'cancel_url' => 'kolabing://subscription/cancel',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_the_pro_checkout_session_bills_the_pro_price(): void
    {
        $params = app(StripeService::class)->checkoutSessionParams(
            $this->business(),
            'pro_monthly',
            'https://app.kolabing.com/subscription/success',
            'https://app.kolabing.com/subscription',
            null,
        );

        $this->assertSame([['price' => 'price_pro_test', 'quantity' => 1]], $params['line_items']);
    }

    // ── Plan resolution from Stripe ────────────────────────────────────────

    public function test_webhook_activation_on_the_pro_price_stores_the_pro_plan(): void
    {
        $profile = $this->business();
        $event = $this->checkoutCompletedEvent($profile, 'sub_test_pro');
        $stripeSubscription = $this->stripeSubscription('sub_test_pro', 'price_pro_test');

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event, $stripeSubscription): void {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
            $mock->shouldReceive('retrieveSubscription')->with('sub_test_pro')->andReturn($stripeSubscription);
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'profile_id' => $profile->id,
            'plan' => 'pro',
            'status' => 'active',
        ]);
        $this->assertTrue($profile->fresh()->hasVenueProAccess());
        $this->assertTrue($profile->fresh()->hasActiveSubscription());
    }

    public function test_webhook_activation_on_an_unknown_price_never_grants_pro(): void
    {
        $profile = $this->business();
        // A lapsed maintainer Pro grant must not leak into a new purchase.
        BusinessSubscription::factory()->pro()->create([
            'profile_id' => $profile->id,
            'source' => SubscriptionSource::Maintainer,
            'status' => SubscriptionStatus::Inactive,
        ]);
        $event = $this->checkoutCompletedEvent($profile, 'sub_test_unknown');
        $stripeSubscription = $this->stripeSubscription('sub_test_unknown', 'price_not_in_config');

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event, $stripeSubscription): void {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
            $mock->shouldReceive('retrieveSubscription')->andReturn($stripeSubscription);
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', ['profile_id' => $profile->id, 'plan' => 'standard']);
        $this->assertFalse($profile->fresh()->hasVenueProAccess());
    }

    public function test_subscription_updated_webhook_moves_the_plan_with_the_price(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_swap',
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_swap',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $this->stripeSubscription('sub_test_swap', 'price_pro_test')->toArray()],
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event): void {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', ['profile_id' => $profile->id, 'plan' => 'pro']);
    }

    public function test_subscription_updated_webhook_with_an_unknown_price_keeps_the_stored_plan(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->pro()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_keep',
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test_keep',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $this->stripeSubscription('sub_test_keep', 'price_legacy')->toArray()],
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event): void {
            $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
        });

        $this->postJson('/api/v1/webhooks/stripe', [], ['Stripe-Signature' => 'valid'])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', ['profile_id' => $profile->id, 'plan' => 'pro']);
    }

    // ── Entitlement ────────────────────────────────────────────────────────

    public function test_only_an_active_pro_business_has_venue_pro_access(): void
    {
        $pro = $this->business();
        BusinessSubscription::factory()->active()->pro()->create(['profile_id' => $pro->id]);

        $standard = $this->business();
        BusinessSubscription::factory()->active()->create(['profile_id' => $standard->id]);

        $lapsedPro = $this->business();
        BusinessSubscription::factory()->cancelled()->pro()->create(['profile_id' => $lapsedPro->id]);

        $community = Profile::factory()->community()->create();

        $this->assertTrue($pro->fresh()->hasVenueProAccess());
        $this->assertTrue($pro->fresh()->hasActiveSubscription(), 'Pro is a superset of the standard plan.');
        $this->assertFalse($standard->fresh()->hasVenueProAccess());
        $this->assertFalse($lapsedPro->fresh()->hasVenueProAccess());
        $this->assertFalse($community->fresh()->hasVenueProAccess());
    }

    public function test_a_business_test_user_has_venue_pro_access(): void
    {
        $profile = Profile::factory()->business()->create(['is_test_user' => true]);

        $this->assertTrue($profile->hasVenueProAccess());
    }

    public function test_the_subscription_endpoint_names_the_plan(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->pro()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.plan_label', 'Venue Pro');
    }

    public function test_a_freshly_created_subscription_reports_the_standard_plan(): void
    {
        $subscription = BusinessSubscription::query()->create([
            'profile_id' => $this->business()->id,
            'source' => SubscriptionSource::AppleIap,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertSame(SubscriptionPlan::Standard, $subscription->plan);
    }

    // ── Changing plan ──────────────────────────────────────────────────────

    public function test_a_standard_subscriber_upgrades_to_pro_and_is_invoiced_now(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_up',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSubscription')->with('sub_test_up')
                ->andReturn($this->stripeSubscription('sub_test_up', 'price_monthly_test'));
            $mock->shouldReceive('changeSubscriptionPrice')->once()
                ->with('sub_test_up', 'price_pro_test', true)
                ->andReturn($this->stripeSubscription('sub_test_up', 'price_pro_test'));
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertOk()
            ->assertJsonPath('data.plan', 'pro')
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($profile->fresh()->hasVenueProAccess());
    }

    public function test_a_pro_subscriber_can_move_back_to_standard_without_an_immediate_invoice(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->pro()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_down',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSubscription')
                ->andReturn($this->stripeSubscription('sub_test_down', 'price_pro_test'));
            $mock->shouldReceive('changeSubscriptionPrice')->once()
                ->with('sub_test_down', 'price_monthly_test', false)
                ->andReturn($this->stripeSubscription('sub_test_down', 'price_monthly_test'));
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'monthly'])
            ->assertOk()
            ->assertJsonPath('data.plan', 'standard');
    }

    public function test_changing_to_the_current_plan_is_refused(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->pro()->create([
            'profile_id' => $profile->id,
            'stripe_subscription_id' => 'sub_test_same',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('retrieveSubscription')
                ->andReturn($this->stripeSubscription('sub_test_same', 'price_pro_test'));
            $mock->shouldNotReceive('changeSubscriptionPrice');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(409);
    }

    public function test_changing_plan_without_an_active_subscription_is_refused(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('changeSubscriptionPrice');
        });

        $this->actingAs($this->business())
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(409);
    }

    public function test_an_app_store_subscription_cannot_be_changed_on_the_web(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'source' => SubscriptionSource::AppleIap,
            'stripe_subscription_id' => null,
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('changeSubscriptionPrice');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Your plan is billed through the App Store. Cancel it there first, then subscribe on the web.');
    }

    public function test_a_maintainer_granted_subscription_cannot_be_changed_on_the_web(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'source' => SubscriptionSource::Maintainer,
            'stripe_subscription_id' => null,
        ]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(409);
    }

    public function test_a_community_cannot_change_plan(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(403);
    }

    public function test_change_plan_validates_the_plan(): void
    {
        $this->actingAs($this->business())
            ->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'enterprise'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_change_plan_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/subscription/change-plan', ['plan' => 'pro_monthly'])
            ->assertStatus(401);
    }

    // ── Maintainer grant ───────────────────────────────────────────────────

    public function test_a_maintainer_can_grant_venue_pro(): void
    {
        $profile = $this->business();

        $this->actingAs(User::factory()->create(['is_maintainer' => true]), 'admin')
            ->post(route('admin.users.subscription.grant', $profile), ['plan' => 'pro'])
            ->assertRedirect();

        $subscription = BusinessSubscription::query()->where('profile_id', $profile->id)->firstOrFail();

        $this->assertSame(SubscriptionPlan::Pro, $subscription->plan);
        $this->assertSame(SubscriptionSource::Maintainer, $subscription->source);
        $this->assertTrue($profile->fresh()->hasVenueProAccess());
    }

    public function test_the_plain_grant_button_still_grants_the_standard_plan(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->pro()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $this->actingAs(User::factory()->create(['is_maintainer' => true]), 'admin')
            ->post(route('admin.users.subscription.grant', $profile))
            ->assertRedirect();

        $this->assertDatabaseHas('business_subscriptions', ['profile_id' => $profile->id, 'plan' => 'standard']);
        $this->assertFalse($profile->fresh()->hasVenueProAccess());
    }

    // ── The web paywall ────────────────────────────────────────────────────

    public function test_the_paywall_offers_venue_pro_priced_from_config(): void
    {
        config(['subscriptions.business.stripe.pro_monthly.price' => 299]);

        $this->get('http://'.config('webapp.host').'/subscription')
            ->assertOk()
            ->assertSee('data-plan="pro_monthly"', false)
            ->assertSee('Venue Pro')
            ->assertSee('€299')
            ->assertSee('data-upgrade="pro"', false);
    }

    public function test_the_paywall_hides_venue_pro_until_its_stripe_price_exists(): void
    {
        config(['subscriptions.business.stripe.pro_monthly.stripe_price_id' => null]);

        $this->get('http://'.config('webapp.host').'/subscription')
            ->assertOk()
            ->assertSee('Monthly')
            ->assertDontSee('data-plan="pro_monthly"', false)
            ->assertDontSee('data-upgrade="pro"', false);
    }
}
