<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InstagramAccount;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramAccount>
 */
class InstagramAccountFactory extends Factory
{
    protected $model = InstagramAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'ig_user_id' => (string) $this->faker->unique()->numberBetween(17841400000000000, 17841499999999999),
            'ig_app_scoped_id' => (string) $this->faker->unique()->numberBetween(1000000000, 9999999999),
            'username' => $this->faker->unique()->userName(),
            'name' => $this->faker->company(),
            'account_type' => 'BUSINESS',
            'profile_picture_url' => 'https://scontent.cdninstagram.com/v/pic.jpg',
            'access_token' => 'IGAA'.$this->faker->sha256(),
            'token_expires_at' => now()->addDays(60),
            'token_refreshed_at' => now(),
            'connected_at' => now(),
            'disconnected_at' => null,
            'last_synced_at' => null,
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => ['profile_id' => $profile->id]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (): array => [
            'access_token' => null,
            'disconnected_at' => now(),
        ]);
    }
}
