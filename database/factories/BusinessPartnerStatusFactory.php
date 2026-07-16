<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PartnerStatusTier;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BusinessPartnerStatus>
 */
class BusinessPartnerStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->business(),
            'status' => PartnerStatusTier::NewPartner,
            'completed_kolabs_count' => 0,
            'review_count' => 0,
            'repeat_partner_count' => 0,
            'average_rating' => null,
            'recalculated_at' => now(),
        ];
    }
}
