<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessSubscription>
 */
class BusinessSubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BusinessSubscription>
     */
    protected $model = BusinessSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->business(),
            'source' => SubscriptionSource::Stripe,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'status' => SubscriptionStatus::Inactive,
            'current_period_start' => null,
            'current_period_end' => null,
            'cancel_at_period_end' => false,
        ];
    }

    /**
     * Indicate that the subscription is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'stripe_subscription_id' => 'sub_'.Str::random(14),
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'stripe_subscription_id' => 'sub_'.Str::random(14),
            'status' => SubscriptionStatus::Cancelled,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now(),
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Indicate that the subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'stripe_subscription_id' => 'sub_'.Str::random(14),
            'status' => SubscriptionStatus::PastDue,
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDays(5),
            'cancel_at_period_end' => false,
        ]);
    }

    /**
     * Indicate that the subscription is from Apple IAP.
     */
    public function apple(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => SubscriptionSource::AppleIap,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
            'apple_original_transaction_id' => '2000000'.fake()->numerify('#########'),
            'apple_transaction_id' => '2000000'.fake()->numerify('#########'),
            'apple_product_id' => 'com.kolabing.app.subscription.monthly',
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
        ]);
    }
}
