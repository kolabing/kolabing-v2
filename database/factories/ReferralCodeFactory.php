<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferralCode>
 */
class ReferralCodeFactory extends Factory
{
    protected $model = ReferralCode::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'code' => 'KOLAB-'.strtoupper(Str::random(4)),
            'total_conversions' => 0,
            'total_points_earned' => 0,
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function withConversions(int $count, int $pointsEarned): static
    {
        return $this->state(fn () => [
            'total_conversions' => $count,
            'total_points_earned' => $pointsEarned,
        ]);
    }
}
