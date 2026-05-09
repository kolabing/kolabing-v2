<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralRedemption>
 */
class ReferralRedemptionFactory extends Factory
{
    protected $model = ReferralRedemption::class;

    public function definition(): array
    {
        return [
            'referral_code_id' => ReferralCode::factory(),
            'referred_profile_id' => Profile::factory()->business(),
            'business_subscription_id' => BusinessSubscription::factory()->apple(),
            'rewarded_at' => now(),
        ];
    }
}
