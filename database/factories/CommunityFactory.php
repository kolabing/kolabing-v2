<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_profile_id' => Profile::factory()->community(),
            'community_profile_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'type' => CommunityType::Other->value,
            'description' => fake()->optional()->sentence(),
            'avatar_url' => null,
            'is_primary' => true,
            'join_policy' => JoinPolicy::Open->value,
        ];
    }

    public function forOwner(Profile $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_profile_id' => $owner->id,
        ]);
    }

    public function inviteOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'join_policy' => JoinPolicy::InviteOnly->value,
        ]);
    }
}
