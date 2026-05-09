<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeviceToken;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'token' => fake()->unique()->sha256(),
            'platform' => fake()->randomElement(['ios', 'android']),
            'app_version' => '1.0.0',
            'locale' => 'en',
            'timezone' => 'Europe/Madrid',
            'last_location_lat' => null,
            'last_location_lng' => null,
            'location_permission_granted_at' => null,
            'is_active' => true,
            'last_seen_at' => now(),
            'last_delivered_at' => null,
            'invalidated_at' => null,
            'invalid_reason' => null,
        ];
    }

    public function withLocation(float $latitude, float $longitude): static
    {
        return $this->state(fn (): array => [
            'last_location_lat' => $latitude,
            'last_location_lng' => $longitude,
            'location_permission_granted_at' => now(),
        ]);
    }
}
