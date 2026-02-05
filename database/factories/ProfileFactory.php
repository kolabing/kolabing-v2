<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Profile>
     */
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->optional()->e164PhoneNumber(),
            'user_type' => fake()->randomElement(UserType::cases()),
            'google_id' => Str::random(21),
            'avatar_url' => fake()->optional()->imageUrl(200, 200, 'people'),
            'email_verified_at' => now(),
        ];
    }

    /**
     * Indicate that the profile is for a business user.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::Business,
        ]);
    }

    /**
     * Indicate that the profile is for a community user.
     */
    public function community(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::Community,
        ]);
    }

    /**
     * Indicate that the profile is for an attendee user.
     */
    public function attendee(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => UserType::Attendee,
        ]);
    }

    /**
     * Indicate that the profile has not verified their email.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
