<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The commercial shape of the Checkout Session — which price it bills, whether a
 * promotion code can be redeemed, and how the buyer is identified to Stripe.
 */
class CheckoutSessionParamsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscriptions.business.stripe.monthly.stripe_price_id' => 'price_monthly_test',
            'subscriptions.business.stripe.three_months.stripe_price_id' => 'price_quarterly_test',
        ]);
    }

    private function business(array $attributes = []): Profile
    {
        $profile = Profile::factory()->business()->create($attributes);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function params(Profile $profile, string $plan = 'monthly'): array
    {
        return app(StripeService::class)->checkoutSessionParams(
            $profile,
            $plan,
            'https://app.kolabing.com/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'https://app.kolabing.com/subscription',
            null,
        );
    }

    public function test_a_new_buyer_gets_their_email_prefilled(): void
    {
        $profile = $this->business(['email' => 'owner@example.com']);

        $params = $this->params($profile);

        $this->assertSame('owner@example.com', $params['customer_email']);
        $this->assertArrayNotHasKey('customer', $params, 'Stripe rejects customer + customer_email together.');
    }

    public function test_a_returning_buyer_reuses_their_stripe_customer(): void
    {
        $profile = $this->business();

        BusinessSubscription::query()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_existing',
            'stripe_subscription_id' => 'sub_old',
            'status' => SubscriptionStatus::Cancelled,
            'source' => SubscriptionSource::Stripe,
        ]);

        $params = $this->params($profile->fresh());

        $this->assertSame('cus_existing', $params['customer']);
        $this->assertArrayNotHasKey('customer_email', $params);
    }

    public function test_promotion_codes_are_redeemable_at_checkout(): void
    {
        $this->assertTrue($this->params($this->business())['allow_promotion_codes']);
    }

    public function test_the_plan_selects_the_matching_stripe_price(): void
    {
        $profile = $this->business();

        $this->assertSame('price_monthly_test', $this->params($profile)['line_items'][0]['price']);
        $this->assertSame('price_quarterly_test', $this->params($profile, 'three_months')['line_items'][0]['price']);
    }

    public function test_the_checkout_is_localised_and_catalan_falls_back_to_spanish(): void
    {
        $profile = $this->business();

        $this->app->setLocale('es');
        $this->assertSame('es', $this->params($profile)['locale']);

        // Stripe Checkout has no Catalan locale.
        $this->app->setLocale('ca');
        $this->assertSame('es', $this->params($profile)['locale']);

        $this->app->setLocale('en');
        $this->assertSame('en', $this->params($profile)['locale']);
    }

    public function test_an_unconfigured_plan_is_refused_before_calling_stripe(): void
    {
        config(['subscriptions.business.stripe.monthly.stripe_price_id' => null]);

        $this->expectException(\RuntimeException::class);

        $this->params($this->business());
    }
}
