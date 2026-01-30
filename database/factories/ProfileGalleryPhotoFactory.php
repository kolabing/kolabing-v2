<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileGalleryPhoto>
 */
class ProfileGalleryPhotoFactory extends Factory
{
    protected $model = ProfileGalleryPhoto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'url' => $this->faker->imageUrl(800, 600),
            'caption' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Set the profile for this photo.
     */
    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => [
            'profile_id' => $profile->id,
        ]);
    }
}
